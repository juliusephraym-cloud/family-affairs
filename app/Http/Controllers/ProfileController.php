<?php

namespace App\Http\Controllers;

use App\Models\ActionItem;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Audit;
use App\Support\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function welcome(Request $request): View
    {
        return view('profile.welcome', ['member' => $request->user()->member]);
    }

    /** First-time completion: approved profile fields + explicit WhatsApp consent. */
    public function completeWelcome(Request $request): RedirectResponse
    {
        $member = $request->user()->member;
        $data = $request->validate([
            'preferred_name' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'birthday_visibility' => ['required', Rule::in(['members', 'leaders', 'private'])],
            'whatsapp_consent' => ['accepted'],
        ]);
        $member->update([
            'preferred_name' => $data['preferred_name'] ?? $member->preferred_name,
            'email' => $data['email'] ?? $member->email,
            // A member may add a missing date of birth; changing an existing one is a correction request.
            'date_of_birth' => $member->date_of_birth ?? ($data['date_of_birth'] ?? null),
            'birthday_visibility' => $data['birthday_visibility'],
            'whatsapp_opt_in_at' => now(),
            'whatsapp_opt_out_at' => null,
        ]);
        Audit::record('profile.consent', $member, 'WhatsApp messaging consent given');

        return redirect()->route('dashboard')->with('status', __('Karibu nyumbani! Akaunti yako iko tayari.'));
    }

    public function edit(Request $request): View
    {
        $member = $request->user()->member->load('notificationPreferences', 'household');

        return view('profile.edit', [
            'member' => $member,
            'user' => $request->user(),
            'categories' => NotificationService::CATEGORIES,
            'mandatory' => NotificationService::MANDATORY,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $request->user()->member;
        $data = $request->validate([
            'preferred_name' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'birthday_visibility' => ['required', Rule::in(['members', 'leaders', 'private'])],
            'bio' => ['nullable', 'string', 'max:1000'],
            'interests' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['required', Rule::in(config('family.locales'))],
        ]);
        $request->user()->update(['locale' => $data['locale']]);
        unset($data['locale']);
        $member->update($data);

        return back()->with('status', __('Wasifu wako umehifadhiwa.'));
    }

    public function photo(Request $request): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:'.config('family.uploads.photo_max_kb')]]);
        $member = $request->user()->member;
        $path = ImageStore::storeResized($request->file('photo'), 'members/'.$member->public_id, 600);
        $member->update(['photo_path' => $path]);

        return back()->with('status', __('Picha yako imewekwa.'));
    }

    public function notifications(Request $request): RedirectResponse
    {
        $member = $request->user()->member;
        $enabled = (array) $request->input('categories', []);
        foreach (NotificationService::CATEGORIES as $cat) {
            if (in_array($cat, NotificationService::MANDATORY, true)) {
                continue;
            }
            NotificationPreference::updateOrCreate(['member_id' => $member->id, 'category' => $cat], ['enabled' => in_array($cat, $enabled, true)]);
        }
        if ($request->boolean('opt_out_all')) {
            $member->update(['whatsapp_opt_out_at' => now()]);
            Audit::record('profile.opt_out', $member, 'Opted out of all WhatsApp messages');
        } elseif ($member->whatsapp_opt_out_at) {
            $member->update(['whatsapp_opt_out_at' => null, 'whatsapp_opt_in_at' => now()]);
            Audit::record('profile.consent', $member, 'WhatsApp messaging consent renewed');
        }

        return back()->with('status', __('Mapendeleo ya ujumbe yamehifadhiwa.'));
    }

    /** Requests to correct protected fields, or to delete data, go to the Secretary's action list. */
    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['correction', 'deletion'])],
            'details' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $secretary = User::whereHas('roles', fn ($q) => $q->where('key', 'secretary'))->first();
        $item = ActionItem::create([
            'title' => ($data['type'] === 'correction' ? 'Ombi la kurekebisha taarifa: ' : 'Ombi la kufuta taarifa: ').$request->user()->member->full_name,
            'notes' => $data['details'],
            'assignee_member_id' => $secretary?->member_id,
            'due_on' => now()->addDays(14),
            'created_by' => $request->user()->id,
        ]);
        Audit::record('profile.request_'.$data['type'], $item, 'Member submitted a data '.$data['type'].' request');

        return back()->with('status', __('Ombi lako limetumwa kwa Katibu. Utajulishwa likishughulikiwa.'));
    }
}
