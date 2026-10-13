<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainRuleException;
use App\Models\FamilyRelationship;
use App\Models\Household;
use App\Models\Member;
use App\Models\MembershipStatusHistory;
use App\Models\Role;
use App\Services\InvitationService;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $private = $user->hasPermission('members.view_private');
        $status = $request->query('status');

        $members = Member::query()->with(['household', 'user.roles'])
            ->when($status && $private, fn ($q) => $q->where('membership_status', $status), fn ($q) => $q->directory())
            // Children and non-member relatives are only listed for people with private access.
            ->when(! $private, fn ($q) => $q->where('is_minor', false))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$s}%")->orWhere('preferred_name', 'like', "%{$s}%")))
            ->when($request->query('household'), fn ($q, $h) => $q->where('household_id', $h))
            ->orderBy('full_name')->get();

        return view('members.index', [
            'members' => $members,
            'households' => Household::orderBy('name')->get(),
            'private' => $private,
            'counts' => $private ? Member::selectRaw('membership_status, count(*) c')->groupBy('membership_status')->pluck('c', 'membership_status') : collect(),
        ]);
    }

    public function show(Request $request, Member $member): View
    {
        $user = $request->user();
        $private = $user->hasPermission('members.view_private') || $user->member_id === $member->id;
        abort_if($member->is_minor && ! $private, 404);
        abort_if(! in_array($member->membership_status, ['active', 'invited', 'suspended'], true) && ! $private, 404);

        $member->load(['household.members', 'relationships.related', 'user.roles']);

        return view('members.show', [
            'member' => $member,
            'private' => $private,
            'canManage' => $user->hasPermission('members.manage'),
            'canRoles' => $user->hasPermission('roles.manage'),
            'roles' => Role::orderBy('sort')->get(),
            'history' => $private ? $member->statusHistory()->with('changer.member')->get() : collect(),
            'others' => $user->hasPermission('members.manage') ? Member::where('id', '!=', $member->id)->orderBy('full_name')->get(['id', 'full_name']) : collect(),
        ]);
    }

    public function photo(Request $request, Member $member)
    {
        $user = $request->user();
        abort_if($member->is_minor && ! $user->hasPermission('members.view_private') && $user->member_id !== $member->id, 404);
        abort_unless($member->photo_path && Storage::disk('private')->exists($member->photo_path), 404);

        return Storage::disk('private')->response($member->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function create(): View
    {
        return view('members.form', ['member' => new Member(['membership_status' => 'invited', 'birthday_visibility' => 'members']), 'households' => Household::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $member = Member::create($data);
        MembershipStatusHistory::create(['member_id' => $member->id, 'to_status' => $member->membership_status, 'reason' => 'Record created', 'changed_by' => $request->user()->id]);
        Audit::record('members.created', $member, 'Member record created: '.$member->full_name);

        return redirect()->route('members.show', $member)->with('status', __('Mwanafamilia ameongezwa. Unaweza sasa kumtumia mwaliko.'));
    }

    public function edit(Member $member): View
    {
        return view('members.form', ['member' => $member, 'households' => Household::orderBy('name')->get()]);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $data = $this->validated($request, $member);
        unset($data['membership_status']); // status changes go through status() with a reason
        if (($data['phone_e164'] ?? null) !== $member->phone_e164 && $member->user) {
            throw ValidationException::withMessages(['phone' => __('Namba ya mwenye akaunti hubadilishwa na yeye mwenyewe kupitia uthibitisho wa WhatsApp.')]);
        }
        $member->update($data);
        Audit::record('members.updated', $member, 'Member record updated', ['fields' => array_keys($member->getChanges())]);

        return redirect()->route('members.show', $member)->with('status', __('Taarifa zimehifadhiwa.'));
    }

    /** Controlled membership status change — always with a reason, always in history. */
    public function status(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Member::STATUSES)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        if ($data['status'] === $member->membership_status) {
            return back();
        }
        MembershipStatusHistory::create(['member_id' => $member->id, 'from_status' => $member->membership_status, 'to_status' => $data['status'], 'reason' => $data['reason'], 'changed_by' => $request->user()->id]);
        $member->update(['membership_status' => $data['status']]);
        Audit::record('members.status_changed', $member, "Status → {$data['status']}", ['reason' => $data['reason']]);

        return back()->with('status', __('Hali ya uanachama imebadilishwa na kuhifadhiwa kwenye historia.'));
    }

    public function invite(Request $request, Member $member, InvitationService $invitations): RedirectResponse
    {
        if ($member->user?->phone_verified_at) {
            return back()->with('error', __('Mwanafamilia huyu tayari ana akaunti iliyothibitishwa.'));
        }
        try {
            $result = $invitations->invite($member, $request->user());
        } catch (DomainRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $result['sent'] ? __('Mwaliko umetumwa kwa WhatsApp.') : __('Mwaliko umeundwa. Nakili kiungo hiki na umtumie binafsi:'))
            ->with('invite_url', $result['url']);
    }

    public function addRelationship(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate([
            'related_member_id' => ['required', 'integer', 'exists:members,id', 'different:member'],
            'type' => ['required', Rule::in(['parent_of', 'spouse_of', 'sibling_of', 'guardian_of', 'other'])],
            'notes' => ['nullable', 'string', 'max:120'],
        ]);
        abort_if((int) $data['related_member_id'] === $member->id, 422);
        FamilyRelationship::firstOrCreate(['member_id' => $member->id, 'related_member_id' => $data['related_member_id'], 'type' => $data['type']], ['notes' => $data['notes'] ?? null, 'recorded_by' => $request->user()->id]);
        Audit::record('members.relationship_added', $member, "Relationship {$data['type']} recorded");

        return back()->with('status', __('Uhusiano umehifadhiwa.'));
    }

    public function removeRelationship(Request $request, Member $member, FamilyRelationship $relationship): RedirectResponse
    {
        abort_unless($relationship->member_id === $member->id, 404);
        $relationship->delete();
        Audit::record('members.relationship_removed', $member, 'Relationship removed');

        return back();
    }

    public function roles(Request $request, Member $member): RedirectResponse
    {
        $user = $member->user;
        abort_unless($user, 422);
        $data = $request->validate(['roles' => ['array'], 'roles.*' => ['integer', 'exists:roles,id']]);
        $ids = collect($data['roles'] ?? [])->map(fn ($i) => (int) $i);
        $superId = Role::where('key', 'super_admin')->value('id');
        // Only a super admin may grant or remove super admin.
        if (! $request->user()->hasRole('super_admin')) {
            $ids = $ids->reject(fn ($i) => $i === $superId);
            if ($user->roles->contains('id', $superId)) {
                $ids->push($superId);
            }
        }
        if ($user->id === $request->user()->id && $user->hasRole('super_admin') && ! $ids->contains($superId)) {
            return back()->with('error', __('Huwezi kujiondolea nafasi ya Msimamizi Mkuu mwenyewe.'));
        }
        $before = $user->roles->pluck('key')->all();
        $user->roles()->sync($ids->unique()->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $request->user()->id, 'assigned_at' => now()]])->all());
        $user->flushPermissionCache();
        Audit::record('roles.changed', $member, 'Roles changed', ['before' => $before, 'after' => $user->fresh()->roles->pluck('key')->all()]);

        return back()->with('status', __('Nafasi zimehifadhiwa.'));
    }

    public function storeHousehold(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        Household::create($data);

        return back()->with('status', __('Kaya imeongezwa.'));
    }

    private function validated(Request $request, ?Member $member = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'preferred_name' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'birthday_visibility' => ['required', Rule::in(['members', 'leaders', 'private'])],
            'household_id' => ['nullable', 'integer', 'exists:households,id'],
            'relationship_note' => ['nullable', 'string', 'max:120'],
            'is_minor' => ['boolean'],
            'membership_status' => [$member ? 'nullable' : 'required', Rule::in(Member::STATUSES)],
            'membership_category' => ['nullable', 'string', 'max:32'],
            'joined_on' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'leader_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $phone = null;
        if (filled($data['phone'] ?? null)) {
            $phone = Phone::normalize($data['phone']);
            if (! $phone) {
                throw ValidationException::withMessages(['phone' => __('auth.phone_invalid')]);
            }
            if (Member::where('phone_e164', $phone)->when($member, fn ($q) => $q->where('id', '!=', $member->id))->exists()) {
                throw ValidationException::withMessages(['phone' => __('auth.phone_taken')]);
            }
        }
        unset($data['phone']);
        $data['phone_e164'] = $phone;
        $data['is_minor'] = $request->boolean('is_minor');
        $data['membership_category'] = $data['membership_category'] ?: 'standard';
        if ($data['is_minor'] && $data['birthday_visibility'] === 'members' && ! $member?->is_minor) {
            $data['birthday_visibility'] = 'leaders';
        }

        return $data;
    }
}
