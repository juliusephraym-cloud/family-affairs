<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\MembershipStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Passwordless sign-in and invitation-based registration over WhatsApp OTP.
 *
 * Anti-enumeration: /login answers identically for registered and
 * unregistered numbers. For unknown numbers a decoy challenge id is put in
 * the session so the verify step also behaves identically (it just never matches).
 */
class OtpAuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function sendLogin(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = Phone::normalize($request->input('phone'));
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => __('auth.phone_invalid')]);
        }

        try {
            $challenge = $this->otp->issue($phone, 'login', $request->ip());
        } catch (DomainRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $request->session()->put('otp', [
            'challenge' => $challenge?->id ?? (string) Str::ulid(),
            'phone' => $phone,
            'purpose' => 'login',
            'remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('verify.show');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $otp = $request->session()->get('otp');
        if (! $otp) {
            return redirect()->route('login');
        }

        return view('auth.verify', ['phone' => $otp['phone'], 'wait' => $this->otp->secondsUntilResend($otp['phone'])]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $otp = $request->session()->get('otp');
        if (! $otp) {
            return redirect()->route('login');
        }
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $result = $this->otp->verify($otp['challenge'], $request->input('code'), $otp['phone']);
        if (! $result['ok']) {
            $msg = match ($result['reason']) {
                'expired' => __('auth.otp_expired'),
                'locked', 'closed' => __('auth.otp_locked'),
                default => __('auth.otp_wrong'),
            };

            throw ValidationException::withMessages(['code' => $msg]);
        }

        $challenge = $result['challenge'];

        return match ($challenge->purpose) {
            'login' => $this->completeLogin($request, User::findOrFail($challenge->user_id), (bool) ($otp['remember'] ?? false)),
            'register' => $this->completeRegistration($request, Invitation::findOrFail($challenge->invitation_id)),
            'phone_change' => $this->completePhoneChange($request, User::findOrFail($challenge->user_id), $challenge->new_phone_e164),
        };
    }

    public function resend(Request $request): RedirectResponse
    {
        $otp = $request->session()->get('otp');
        if (! $otp) {
            return redirect()->route('login');
        }
        try {
            $challenge = match ($otp['purpose']) {
                'login' => $this->otp->issue($otp['phone'], 'login', $request->ip()),
                'register' => $this->otp->issue($otp['phone'], 'register', $request->ip(), null, Invitation::find($otp['invitation'] ?? 0)),
                'phone_change' => $this->otp->issue($otp['phone'], 'phone_change', $request->ip(), $request->user(), null, $otp['phone']),
            };
        } catch (DomainRuleException $e) {
            return back()->with('error', $e->getMessage());
        }
        $otp['challenge'] = $challenge?->id ?? (string) Str::ulid();
        $request->session()->put('otp', $otp);

        return back()->with('status', __('auth.otp_resent'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Audit::record('auth.logout', $request->user(), 'Signed out');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ---------------------------------------------------------------- invitations

    public function showInvitation(Request $request, string $token): View|RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        if (! $invitation) {
            return redirect()->route('login')->with('error', __('auth.invite_invalid'));
        }

        return view('auth.invitation', ['invitation' => $invitation, 'token' => $token]);
    }

    public function sendInvitationCode(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        if (! $invitation) {
            return redirect()->route('login')->with('error', __('auth.invite_invalid'));
        }
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = Phone::normalize($request->input('phone'));

        // The code always goes to the number the leaders invited — a forwarded link
        // is useless to anyone who does not hold that phone.
        if (! $phone || ! hash_equals($invitation->phone_e164, $phone)) {
            Audit::record('auth.invite_phone_mismatch', $invitation->member, 'Invitation used with a different phone number');
            throw ValidationException::withMessages(['phone' => __('auth.invite_phone_mismatch')]);
        }
        if (User::where('phone_e164', $phone)->where('member_id', '!=', $invitation->member_id)->exists()) {
            throw ValidationException::withMessages(['phone' => __('auth.phone_taken')]);
        }

        try {
            $challenge = $this->otp->issue($phone, 'register', $request->ip(), null, $invitation);
        } catch (DomainRuleException $e) {
            return back()->with('error', $e->getMessage());
        }
        $request->session()->put('otp', ['challenge' => $challenge->id, 'phone' => $phone, 'purpose' => 'register', 'invitation' => $invitation->id, 'remember' => false]);

        return redirect()->route('verify.show');
    }

    // ---------------------------------------------------------------- phone change (signed-in)

    public function sendPhoneChange(Request $request): RedirectResponse
    {
        $request->validate(['new_phone' => ['required', 'string', 'max:30']]);
        $new = Phone::normalize($request->input('new_phone'));
        if (! $new) {
            throw ValidationException::withMessages(['new_phone' => __('auth.phone_invalid')]);
        }
        if (User::where('phone_e164', $new)->exists() || Member::where('phone_e164', $new)->where('id', '!=', $request->user()->member_id)->exists()) {
            throw ValidationException::withMessages(['new_phone' => __('auth.phone_taken')]);
        }
        try {
            // The code goes to the NEW number: proving possession of it is the whole point.
            $challenge = $this->otp->issue($new, 'phone_change', $request->ip(), $request->user(), null, $new);
        } catch (DomainRuleException $e) {
            return back()->with('error', $e->getMessage());
        }
        $request->session()->put('otp', ['challenge' => $challenge->id, 'phone' => $new, 'purpose' => 'phone_change', 'remember' => false]);

        return redirect()->route('verify.show');
    }

    // ---------------------------------------------------------------- completion

    private function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        if (! $user->isUsable()) {
            throw ValidationException::withMessages(['code' => __('auth.account_unavailable')]);
        }
        Auth::login($user, $remember);
        $request->session()->forget('otp');
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_active_at' => now()])->save();
        Audit::record('auth.login', $user, 'Signed in with WhatsApp OTP');

        return redirect()->intended(route('dashboard'));
    }

    private function completeRegistration(Request $request, Invitation $invitation): RedirectResponse
    {
        $user = DB::transaction(function () use ($invitation) {
            $inv = Invitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! $inv->isUsable()) {
                throw ValidationException::withMessages(['code' => __('auth.invite_invalid')]);
            }
            $member = $inv->member;
            $user = User::firstOrNew(['member_id' => $member->id]);
            $user->fill(['phone_e164' => $inv->phone_e164, 'phone_verified_at' => now(), 'status' => 'active', 'locale' => app()->getLocale()]);
            $user->save();
            if ($user->roles()->doesntExist()) {
                $user->roles()->attach(Role::where('key', 'member')->value('id'), ['assigned_by' => $inv->invited_by, 'assigned_at' => now()]);
            }
            if ($member->membership_status === 'invited') {
                MembershipStatusHistory::create(['member_id' => $member->id, 'from_status' => 'invited', 'to_status' => 'active', 'reason' => 'Invitation accepted; phone verified by WhatsApp OTP', 'changed_by' => $user->id]);
                $member->membership_status = 'active';
            }
            $member->phone_e164 = $inv->phone_e164;
            $member->save();
            $inv->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->forget('otp');
        $request->session()->regenerate();
        Audit::record('auth.registered', $user, 'Invitation accepted and phone verified');

        return redirect()->route('welcome.show');
    }

    private function completePhoneChange(Request $request, User $user, string $newPhone): RedirectResponse
    {
        if (! $request->user() || $request->user()->id !== $user->id) {
            abort(403);
        }
        $old = $user->phone_e164;
        DB::transaction(function () use ($user, $newPhone) {
            $user->update(['phone_e164' => $newPhone, 'phone_verified_at' => now()]);
            $user->member->update(['phone_e164' => $newPhone]);
        });
        $request->session()->forget('otp');
        Audit::record('auth.phone_changed', $user, 'Phone number changed after OTP verification', ['from' => Phone::mask($old), 'to' => Phone::mask($newPhone)]);

        return redirect()->route('profile.edit')->with('status', __('auth.phone_changed'));
    }

    private function findInvitation(string $token): ?Invitation
    {
        $inv = Invitation::where('token_hash', hash('sha256', $token))->with('member')->first();

        return $inv && $inv->isUsable() ? $inv : null;
    }
}
