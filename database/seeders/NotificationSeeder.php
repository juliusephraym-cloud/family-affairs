<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\ReminderRule;
use Illuminate\Database\Seeder;

/**
 * Suggested WhatsApp templates. Each must be created and approved in the
 * NextSMS dashboard (Channels → Whatsapp Channel → Whatsapp Gateway); the
 * administrator then enters the provider template name here and marks it
 * approved. Until then nothing can be sent with it.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['member_invitation', 'security', 'Mwaliko wa kujiunga na mfumo', 'Habari {name}, umealikwa kujiunga na mfumo wa familia wa Family Affairs. Fungua kiungo hiki binafsi (usimpe mtu): {portal_link}', ['name', 'portal_link'], false],
            ['otp', 'security', 'Nambari ya uthibitisho (OTP)', '{code} ni nambari yako ya uthibitisho ya Family Affairs. Usimpe mtu yeyote.', [], false],
            ['contribution_reminder', 'contributions', 'Ukumbusho wa mchango wa mwezi', 'Habari {name}, huu ni ukumbusho wa upendo wa mchango wa mwezi {period} wa Family Affairs. Ingia hapa kuona hali yako: {portal_link}', ['name', 'period', 'portal_link'], false],
            ['private_balance', 'balances', 'Salio binafsi (siri)', 'Habari {name}, kwa mujibu wa kumbukumbu za Mhazini una salio la {amount} (miezi {months}). Kama umeshalipa, tuma risiti kupitia {portal_link}. Asante.', ['name', 'amount', 'months', 'portal_link'], true],
            ['meeting_reminder', 'meetings', 'Ukumbusho wa kikao', 'Habari {name}, kikao "{title}" kitafanyika {date} — {place}. Agenda: {portal_link}', ['name', 'title', 'date', 'place', 'portal_link'], false],
            ['event_reminder', 'events', 'Ukumbusho wa tukio', 'Habari {name}, tukio la familia "{title}" ni {date} — {place}. Thibitisha kuhudhuria: {portal_link}', ['name', 'title', 'date', 'place', 'portal_link'], false],
            ['birthday_greeting', 'birthdays', 'Heri ya siku ya kuzaliwa', 'Heri ya siku ya kuzaliwa {name}! Familia nzima ya Family Affairs inakutakia baraka tele, afya njema na furaha. Tunakupenda.', ['name'], false],
            ['task_assignment', 'committee_tasks', 'Jukumu la kamati', 'Habari {name}, umepangiwa jukumu "{title}" kwa tukio {event}, mwisho {date}. Maelezo: {portal_link}', ['name', 'title', 'event', 'date', 'portal_link'], false],
            ['welfare_notice', 'welfare', 'Taarifa ya msiba / jamii', 'Ndugu {name}, {title}. Tafadhali soma taarifa kamili na utaratibu wa mchango hapa: {portal_link}', ['name', 'title', 'portal_link'], false],
            ['secreto_deadline', 'secreto', 'Secreto inakaribia', 'Habari {name}! {title} ni {date}. Andaa zawadi ya Secreto wako na usisahau ni siri. {portal_link}', ['name', 'title', 'date', 'portal_link'], false],
            ['general_notice', 'governance', 'Tangazo rasmi', 'Habari {name}, tangazo rasmi kutoka uongozi: {title}. Soma zaidi: {portal_link}', ['name', 'title', 'portal_link'], false],
            ['charity_notice', 'charity', 'Sadaka ya wahitaji', 'Habari {name}, {title}. Maelezo na usajili wa kushiriki: {portal_link}', ['name', 'title', 'portal_link'], false],
        ];
        foreach ($templates as [$key, $cat, $name, $body, $vars, $sensitive]) {
            NotificationTemplate::firstOrCreate(['key' => $key], [
                'category' => $cat, 'name' => $name, 'body_preview' => $body, 'variables' => $vars, 'is_sensitive' => $sensitive,
            ]);
        }

        $rules = [
            ['monthly_contribution', 'contributions', 'contribution_reminder', ['day' => 25, 'time' => '09:00']],
            ['private_balance', 'balances', 'private_balance', ['day' => 5, 'time' => '10:00']],
            ['birthday_greeting', 'birthdays', 'birthday_greeting', ['time' => '07:30']],
            ['meeting_reminder', 'meetings', 'meeting_reminder', ['hours_before' => 24]],
            ['event_reminder', 'events', 'event_reminder', ['days_before' => 3]],
            ['secreto_deadline', 'secreto', 'secreto_deadline', ['days_before' => 7]],
        ];
        foreach ($rules as [$key, $cat, $tpl, $config]) {
            ReminderRule::firstOrCreate(['key' => $key], [
                'category' => $cat,
                'notification_template_id' => NotificationTemplate::where('key', $tpl)->value('id'),
                'enabled' => false,
                'config' => $config,
            ]);
        }
    }
}
