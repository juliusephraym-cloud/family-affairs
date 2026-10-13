<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Swahili-first labels (translated through lang/en.json) and badge styles for status values. */
final class Labels
{
    public const STATUS = [
        // generic
        'active' => ['badge-green', 'Hai'], 'invited' => ['badge-gold', 'Amealikwa'], 'suspended' => ['badge-clay', 'Amesimamishwa'],
        'resigned' => ['badge-grey', 'Amejiondoa'], 'archived' => ['badge-grey', 'Kumbukumbu'], 'non_member' => ['badge-grey', 'Si mwanachama'],
        'pending' => ['badge-gold', 'Inasubiri'], 'verified' => ['badge-green', 'Imethibitishwa'], 'rejected' => ['badge-clay', 'Imekataliwa'], 'withdrawn' => ['badge-grey', 'Imeondolewa'],
        'open' => ['badge-clay', 'Haijalipwa'], 'partially_paid' => ['badge-gold', 'Imelipwa kiasi'], 'paid' => ['badge-green', 'Imelipwa'], 'waived' => ['badge-grey', 'Imesamehewa'], 'cancelled' => ['badge-grey', 'Imefutwa'],
        'submitted' => ['badge-gold', 'Imewasilishwa'], 'approved' => ['badge-green', 'Imeidhinishwa'], 'draft' => ['badge-grey', 'Rasimu'],
        'reversed' => ['badge-grey', 'Imerudishwa'], 'posted' => ['badge-green', 'Imerekodiwa'],
        'planned' => ['badge-gold', 'Imepangwa'], 'confirmed' => ['badge-green', 'Imethibitishwa'], 'done' => ['badge-green', 'Imekamilika'], 'held' => ['badge-green', 'Kimefanyika'],
        'todo' => ['badge-grey', 'Bado'], 'doing' => ['badge-gold', 'Inaendelea'], 'blocked' => ['badge-clay', 'Imekwama'],
        'in_progress' => ['badge-gold', 'Inaendelea'],
        'collecting' => ['badge-gold', 'Michango inaendelea'], 'disbursed' => ['badge-green', 'Msaada umetolewa'], 'closed' => ['badge-grey', 'Imefungwa'],
        'proposed' => ['badge-gold', 'Pendekezo'], 'under_review' => ['badge-gold', 'Inapitiwa'], 'superseded' => ['badge-grey', 'Imepitwa'],
        'source_unconfirmed' => ['badge-clay', 'Inahitaji uthibitisho'], 'retired' => ['badge-grey', 'Imestaafishwa'], 'needs_confirmation' => ['badge-clay', 'Inahitaji uthibitisho'], 'recorded' => ['badge-grey', 'Imerekodiwa'],
        'registration' => ['badge-gold', 'Usajili wazi'], 'drawn' => ['badge-green', 'Majina yamechaguliwa'], 'exchanged' => ['badge-green', 'Zawadi zimetolewa'],
        'scheduled' => ['badge-gold', 'Imepangwa'], 'pending_approval' => ['badge-clay', 'Inasubiri idhini'], 'processing' => ['badge-gold', 'Inatumwa'], 'completed' => ['badge-green', 'Imekamilika'], 'failed' => ['badge-clay', 'Imeshindwa'],
        'queued' => ['badge-grey', 'Kwenye foleni'], 'sending' => ['badge-gold', 'Inatumwa'], 'delivered' => ['badge-green', 'Imefika'], 'read' => ['badge-green', 'Imesomwa'], 'undeliverable' => ['badge-clay', 'Haikufika'], 'skipped' => ['badge-grey', 'Imerukwa'],
        'warning' => ['badge-gold', 'Onyo'], 'reminder' => ['badge-gold', 'Ukumbusho'], 'review' => ['badge-clay', 'Mapitio'], 'decided' => ['badge-green', 'Uamuzi umefanyika'],
        'present' => ['badge-green', 'Alihudhuria'], 'apology' => ['badge-gold', 'Udhuru'], 'absent' => ['badge-clay', 'Hakuhudhuria'], 'excused' => ['badge-grey', 'Ameruhusiwa'],
        'not_started' => ['badge-grey', 'Bado'], 'preparing' => ['badge-gold', 'Naandaa'], 'ready' => ['badge-green', 'Iko tayari'], 'brought' => ['badge-green', 'Imeletwa'], 'by_proxy' => ['badge-green', 'Kupitia mwakilishi'],
        'visible' => ['badge-green', 'Inaonekana'], 'hidden' => ['badge-grey', 'Imefichwa'],
    ];

    public static function status(?string $value): string
    {
        return __(self::STATUS[$value][1] ?? Str::headline((string) $value));
    }

    public static function badge(?string $value): string
    {
        return self::STATUS[$value][0] ?? 'badge-grey';
    }

    /** @param  array<string>  $values */
    public static function options(array $values): array
    {
        return collect($values)->mapWithKeys(fn ($v) => [$v => self::status($v)])->all();
    }
}
