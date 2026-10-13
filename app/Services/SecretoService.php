<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Member;
use App\Models\SecretoAssignment;
use App\Models\SecretoParticipant;
use App\Models\SecretoSeason;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Secreto (the family's Secret-Santa tradition).
 *
 * Secrecy guarantees:
 *  - The draw is a random derangement (nobody draws themselves; optionally
 *    nobody draws their own household) using random_int (CSPRNG).
 *  - The recipient is stored only encrypted and bound to the giver id.
 *  - The only read path is myAssignment(): derived from the logged-in user,
 *    no id in the URL, so it cannot be enumerated.
 *  - Organisers see progress (how many drawn, gift status), not pairs.
 *    revealAll() is a break-glass action requiring secreto.reveal and is audited.
 */
class SecretoService
{
    public function join(SecretoSeason $season, Member $member, ?string $wishlist = null): SecretoParticipant
    {
        if (! $season->registrationOpen()) {
            throw new DomainRuleException(__('secreto.registration_closed'));
        }

        return SecretoParticipant::firstOrCreate(
            ['secreto_season_id' => $season->id, 'member_id' => $member->id],
            ['wishlist' => $wishlist],
        );
    }

    public function leave(SecretoSeason $season, Member $member): void
    {
        if (! $season->registrationOpen()) {
            throw new DomainRuleException(__('secreto.registration_closed'));
        }
        SecretoParticipant::where('secreto_season_id', $season->id)->where('member_id', $member->id)->delete();
    }

    public function draw(SecretoSeason $season, User $by): int
    {
        return DB::transaction(function () use ($season, $by) {
            $s = SecretoSeason::whereKey($season->id)->lockForUpdate()->firstOrFail();
            if (! in_array($s->status, ['registration', 'draft'], true)) {
                throw new DomainRuleException(__('secreto.already_drawn'));
            }
            $participants = $s->participants()->with('member')->get()->values();
            if ($participants->count() < 3) {
                throw new DomainRuleException(__('secreto.need_three'));
            }

            $order = $this->derangement($participants->all(), $s->avoid_same_household);
            foreach ($participants as $i => $giver) {
                SecretoAssignment::create([
                    'secreto_season_id' => $s->id,
                    'giver_participant_id' => $giver->id,
                    'recipient_payload' => ['giver' => $giver->id, 'recipient' => $order[$i]->id],
                ]);
            }
            $s->update(['status' => 'drawn', 'drawn_by' => $by->id, 'drawn_at' => now()]);
            Audit::record('secreto.drawn', $s, 'Secreto draw completed', ['participants' => $participants->count()]);

            return $participants->count();
        });
    }

    /** Undo a draw (e.g. someone withdrew). Audited; everyone must be told to re-check. */
    public function resetDraw(SecretoSeason $season, string $reason, User $by): void
    {
        if (trim($reason) === '' || $season->status !== 'drawn') {
            throw new DomainRuleException(__('finance.reason_required'));
        }
        DB::transaction(function () use ($season) {
            $season->assignments()->delete();
            $season->update(['status' => 'registration', 'drawn_at' => null, 'drawn_by' => null]);
        });
        Audit::record('secreto.draw_reset', $season, 'Secreto draw reset', ['reason' => $reason]);
    }

    /** The recipient for the logged-in member, or null. */
    public function myAssignment(SecretoSeason $season, User $user): ?SecretoParticipant
    {
        $me = SecretoParticipant::where('secreto_season_id', $season->id)->where('member_id', $user->member_id)->first();
        if (! $me) {
            return null;
        }
        $a = SecretoAssignment::where('giver_participant_id', $me->id)->first();
        $rid = $a?->recipientParticipantId();

        return $rid ? SecretoParticipant::with('member')->find($rid) : null;
    }

    /** Break-glass: full list of pairs. @return array<int, array{giver: string, recipient: string}> */
    public function revealAll(SecretoSeason $season, User $by, string $reason): array
    {
        if (! $by->hasPermission('secreto.reveal') || trim($reason) === '') {
            throw new DomainRuleException(__('secreto.reveal_denied'));
        }
        Audit::record('secreto.revealed', $season, 'Secreto pairs revealed (break-glass)', ['reason' => $reason]);
        $participants = $season->participants()->with('member')->get()->keyBy('id');

        return $season->assignments()->get()->map(fn (SecretoAssignment $a) => [
            'giver' => $participants[$a->giver_participant_id]?->member->displayName(),
            'recipient' => $participants[$a->recipientParticipantId()]?->member->displayName(),
        ])->all();
    }

    /** Random derangement via shuffle + constraint check (fine for family-sized groups). */
    private function derangement(array $people, bool $avoidHousehold): array
    {
        $n = count($people);
        for ($attempt = 0; $attempt < 5000; $attempt++) {
            $perm = $people;
            for ($i = $n - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$perm[$i], $perm[$j]] = [$perm[$j], $perm[$i]];
            }
            $ok = true;
            foreach ($people as $i => $giver) {
                $r = $perm[$i];
                $sameHousehold = $avoidHousehold && $giver->member->household_id && $giver->member->household_id === $r->member->household_id;
                if ($r->id === $giver->id || $sameHousehold) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $perm;
            }
        }
        throw new DomainRuleException(__('secreto.draw_impossible'));
    }
}
