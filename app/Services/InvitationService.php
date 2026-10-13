<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Create an invitation. Returns the plain token exactly once (only its
     * SHA-256 is stored). If the "member_invitation" WhatsApp template is
     * approved the link is also sent; otherwise the leader shares it manually.
     *
     * @return array{invitation: Invitation, url: string, sent: bool}
     */
    public function invite(Member $member, User $by): array
    {
        if (! $member->phone_e164) {
            throw new DomainRuleException(__('auth.invite_needs_phone'));
        }
        Invitation::where('member_id', $member->id)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $token = Str::random(48);
        $invitation = Invitation::create([
            'member_id' => $member->id,
            'token_hash' => hash('sha256', $token),
            'phone_e164' => $member->phone_e164,
            'invited_by' => $by->id,
            'expires_at' => now()->addDays((int) config('family.invite_ttl_days')),
        ]);
        if (in_array($member->membership_status, ['non_member'], true)) {
            $member->update(['membership_status' => 'invited']);
        }
        $url = route('invitation.show', $token);

        $sent = false;
        $tpl = NotificationTemplate::where('key', 'member_invitation')->first();
        if ($tpl?->isSendable()) {
            $this->notifications->schedule($tpl, 'Mwaliko: '.$member->displayName(), ['type' => 'members', 'member_ids' => [$member->id]], now(),
                ['portal_link' => $url], $by, $invitation, 'invite:'.$invitation->id, true);
            $sent = true;
        }
        Audit::record('members.invited', $member, 'Invitation created', ['sent_by_whatsapp' => $sent]);

        return compact('invitation', 'url', 'sent');
    }
}
