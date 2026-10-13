<?php

namespace Database\Seeders;

use App\Models\ActionItem;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\CharityActivity;
use App\Models\ContributionRule;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Fund;
use App\Models\Household;
use App\Models\LedgerAccount;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Member;
use App\Models\MembershipStatusHistory;
use App\Models\PaymentSubmission;
use App\Models\Poll;
use App\Models\Role;
use App\Models\SecretoParticipant;
use App\Models\SecretoSeason;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WelfareCase;
use App\Services\ContributionService;
use App\Services\TreasuryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DEMO DATA — every name is fictional and every record is flagged is_demo
 * where the table supports it. Phone numbers use the unallocated
 * +255 700 xxx xxx range. Never run in production (guarded below).
 *
 * Sign in locally with 0700 000 001 (Chair + Super Admin) or 0700 000 005
 * (Treasurer); the OTP appears at /dev/outbox.
 */
class DemoSeeder extends Seeder
{
    private array $m = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder refuses to run in production.');

            return;
        }
        if (Member::where('is_demo', true)->exists()) {
            $this->command?->warn('Demo data already present — skipping.');

            return;
        }
        $this->people();
        $this->finance();
        $this->governance();
        $this->events();
        $this->community();
        $this->memories();
    }

    private function people(): void
    {
        $hh = collect(['Familia ya Mwakyusa (DEMO)', 'Familia ya Lyimo (DEMO)', 'Familia ya Massawe (DEMO)', 'Familia ya Temba (DEMO)', 'Familia ya Shirima (DEMO)'])
            ->mapWithKeys(fn ($n) => [$n => Household::create(['name' => $n])]);
        $h = $hh->values();

        // key => [full name, preferred, phone suffix, household idx, roles, dob]
        $people = [
            'admin' => ['Msimamizi wa Mfumo (DEMO)', 'Msimamizi', '000', null, ['super_admin'], null],
            'chair' => ['Baraka Mwakyusa', 'Kaka Baraka', '001', 0, ['chairperson', 'super_admin'], '1979-12-23'],
            'vice' => ['Neema Lyimo', 'Da Neema', '002', 1, ['vice_chairperson'], '1984-02-13'],
            'sec' => ['Rehema Massawe', 'Rehema', '003', 2, ['secretary'], '1986-07-17'],
            'asec' => ['Daudi Shirima', 'Daudi', '004', 4, ['assistant_secretary'], '1990-02-25'],
            'treas' => ['Upendo Temba', 'Mama Upendo', '005', 3, ['treasurer'], '1982-02-01'],
            'atreas' => ['Grace Minja', 'Da Grace', '006', 2, ['assistant_treasurer'], '1977-06-21'],
            'joseph' => ['Joseph Mallya', 'Joe', '007', 0, ['committee_leader'], '1985-06-09'],
            'esther' => ['Esther Kimaro', 'Esther', '008', 1, [], '1991-10-13'],
            'peter' => ['Peter Swai', 'Peter', '009', 3, [], '1988-12-24'],
            'agnes' => ['Agnes Urio', 'Agnes', '010', 4, [], '1993-03-01'],
            'samweli' => ['Samweli Moshi', 'Sam', '011', 2, [], '1980-07-02'],
            'faraja' => ['Faraja Mrisho', 'Faraja', '012', 1, [], '1995-06-10'],
            'tumaini' => ['Tumaini Lema', 'Tumaini', '013', 3, [], '1989-01-08'],
        ];
        $memberRole = Role::where('key', 'member')->value('id');
        foreach ($people as $key => [$name, $pref, $suffix, $hIdx, $roles, $dob]) {
            $member = Member::create([
                'full_name' => $name, 'preferred_name' => $pref, 'phone_e164' => '255700000'.$suffix,
                'household_id' => $hIdx === null ? null : $h[$hIdx]->id, 'membership_status' => 'active', 'joined_on' => '2021-07-01',
                'date_of_birth' => $dob, 'birthday_visibility' => 'members', 'is_demo' => true, 'whatsapp_opt_in_at' => now()->subMonths(2),
                'bio' => $key === 'chair' ? 'Mpenzi wa familia, nyama choma na mpira. (Wasifu wa mfano.)' : null,
            ]);
            MembershipStatusHistory::create(['member_id' => $member->id, 'to_status' => 'active', 'reason' => 'DEMO record']);
            $user = User::create(['member_id' => $member->id, 'phone_e164' => $member->phone_e164, 'phone_verified_at' => now()->subMonths(2), 'status' => 'active', 'last_active_at' => now()->subDays(rand(0, 20))]);
            $ids = Role::whereIn('key', $roles)->pluck('id')->push($memberRole)->unique();
            $user->roles()->sync($ids->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all());
            $this->m[$key] = $member;
        }
        // An invited (not yet verified) member and two children (non-members, protected).
        $this->m['halima'] = Member::create(['full_name' => 'Halima Mrema', 'phone_e164' => '255700000014', 'household_id' => $h[4]->id, 'membership_status' => 'invited', 'is_demo' => true, 'birthday_visibility' => 'members']);
        Member::create(['full_name' => 'Amani Mwakyusa', 'household_id' => $h[0]->id, 'membership_status' => 'non_member', 'is_minor' => true, 'date_of_birth' => '2014-11-18', 'birthday_visibility' => 'members', 'relationship_note' => 'Mtoto wa Baraka (DEMO)', 'is_demo' => true]);
        Member::create(['full_name' => 'Zawadi Temba', 'household_id' => $h[3]->id, 'membership_status' => 'non_member', 'is_minor' => true, 'date_of_birth' => '2016-05-18', 'birthday_visibility' => 'leaders', 'relationship_note' => 'Mtoto wa Upendo (DEMO)', 'is_demo' => true]);
    }

    private function finance(): void
    {
        $contrib = app(ContributionService::class);
        $treasury = app(TreasuryService::class);
        $treasurer = $this->m['treas']->user;
        $assistant = $this->m['atreas']->user;
        $chair = $this->m['chair']->user;
        $bank = LedgerAccount::byCode('1000');
        $utt = LedgerAccount::byCode('1100');

        $rule = ContributionRule::create([
            'name' => 'DEMO: Mchango wa mwezi', 'amount' => 20000, 'fund_splits' => [['fund' => 'general', 'amount' => 10000], ['fund' => 'welfare', 'amount' => 10000]],
            'due_day' => 28, 'effective_from' => '2026-06-01', 'status' => 'approved', 'source_reference' => 'DEMO — si kanuni halisi ya familia',
            'created_by' => $treasurer->id, 'approved_by' => $chair->id, 'approved_at' => now()->subMonths(4),
        ]);

        $treasury->openingBalance($bank, Fund::byKey('general'), 2400000, '2026-05-31', 'DEMO opening balance', $treasurer);
        $treasury->openingBalance($bank, Fund::byKey('welfare'), 650000, '2026-05-31', 'DEMO opening balance', $treasurer);
        $treasury->openingBalance($bank, Fund::byKey('emergency'), 1000000, '2026-05-31', 'DEMO opening balance', $treasurer);
        $treasury->openingBalance($utt, Fund::byKey('investment'), 5000000, '2026-05-31', 'DEMO opening balance (UTT at cost)', $treasurer);
        $treasury->recordValuation($utt, 5318400, now()->subDays(12)->toDateString(), 'DEMO statement', 'Mfano tu', $treasurer);

        $months = [];
        for ($d = Carbon::create(2026, 6, 1); $d->lte(now()->startOfMonth()); $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }
        foreach ($months as $p) {
            $contrib->generateMonthly($p, $treasurer);
        }

        // Payment patterns: how many months each person has paid (from June).
        $paid = ['chair' => 5, 'vice' => 5, 'sec' => 4, 'asec' => 4, 'treas' => 5, 'atreas' => 4, 'joseph' => 2, 'esther' => 4, 'peter' => 1, 'agnes' => 4, 'samweli' => 5, 'faraja' => 3, 'tumaini' => 4, 'admin' => 4];
        $ref = 1000;
        foreach ($paid as $key => $n) {
            $member = $this->m[$key];
            $verifier = $member->id === $this->m['treas']->id ? $assistant : $treasurer;
            for ($i = 0; $i < min($n, count($months)); $i++) {
                $contrib->recordPayment($member, 20000, Carbon::createFromFormat('Y-m', $months[$i])->day(20)->toDateString(), 'bank_transfer', $bank, 'DEMO'.$ref++, 'contributions', null, $verifier);
            }
        }
        // An overpayment that becomes credit for future months.
        $contrib->recordPayment($this->m['samweli'], 30000, now()->subDays(3)->toDateString(), 'mobile_money', LedgerAccount::byCode('1010'), 'DEMOMP77', 'contributions', null, $treasurer);
        // Sadaka
        foreach (['chair', 'vice', 'esther', 'agnes'] as $k) {
            $contrib->recordPayment($this->m[$k], 50000, '2026-06-27', 'cash', LedgerAccount::byCode('1020'), null, 'sadaka', null, $k === 'treas' ? $assistant : $treasurer);
        }

        // Pending submissions (one re-uses an already verified reference → duplicate warning).
        PaymentSubmission::create(['member_id' => $this->m['peter']->id, 'submitted_by' => $this->m['peter']->user->id, 'amount' => 40000, 'paid_on' => now()->subDay(), 'method' => 'mobile_money', 'channel_detail' => 'M-Pesa', 'transaction_reference' => 'DEMO-QX81', 'reference_normalized' => 'DEMOQX81', 'purpose' => 'contributions', 'note' => 'Julai na Agosti (DEMO)', 'status' => 'pending']);
        PaymentSubmission::create(['member_id' => $this->m['joseph']->id, 'submitted_by' => $this->m['joseph']->user->id, 'amount' => 20000, 'paid_on' => now()->subDays(2), 'method' => 'bank_transfer', 'channel_detail' => 'CRDB', 'transaction_reference' => 'DEMO1003', 'reference_normalized' => 'DEMO1003', 'purpose' => 'contributions', 'status' => 'pending']);

        // Expenses: one paid (June gathering), one awaiting approval.
        $e = $treasury->submitExpense(['title' => 'DEMO: Mkusanyiko wa Juni — chakula na vinywaji', 'category' => 'event', 'amount' => 1250000, 'expense_date' => '2026-06-26', 'purpose' => 'Bajeti ya mkusanyiko wa familia (mfano).', 'fund_id' => Fund::byKey('general')->id], $this->m['vice']->user);
        $treasury->decideExpense($e, true, $chair);
        $treasury->payExpense($e->fresh(), $bank, '2026-06-26', 'DEMO-EXP-01', $treasurer);
        $treasury->submitExpense(['title' => 'DEMO: Kamera stand na kipaza sauti', 'category' => 'admin', 'amount' => 180000, 'expense_date' => now()->toDateString(), 'purpose' => 'Kwa kurekodi vikao na mahojiano ya watoto.', 'fund_id' => Fund::byKey('general')->id], $this->m['joseph']->user);
    }

    private function governance(): void
    {
        $sec = $this->m['sec']->user;
        $chair = $this->m['chair']->user;
        $past = Meeting::create(['title' => 'DEMO: Kikao cha Familia — Juni 2026', 'type' => 'general', 'starts_at' => '2026-06-27 14:00:00', 'location' => 'Nyumbani kwa Familia ya Temba (DEMO)', 'organizer_member_id' => $this->m['sec']->id, 'status' => 'held', 'is_demo' => true, 'created_by' => $sec->id]);
        $items = ['Sala fupi', 'Shukrani na utambulisho', 'Mapitio ya katiba', 'Taarifa ya fedha na uwekezaji', 'Sadaka ya wahitaji', 'Secreto', 'Tarehe ya kikao kijacho', 'Kufunga kwa sala'];
        foreach ($items as $i => $t) {
            $past->agendaItems()->create(['position' => $i + 1, 'title' => $t, 'discussion' => $i === 3 ? 'Mhazini aliwasilisha taarifa (mfano). Kiasi cha jumla kilichokusanywa: KINAHITAJI UTHIBITISHO.' : null]);
        }
        foreach (array_keys($this->m) as $k) {
            if ($k === 'halima') {
                continue;
            }
            MeetingAttendance::create(['meeting_id' => $past->id, 'member_id' => $this->m[$k]->id, 'status' => in_array($k, ['peter', 'admin']) ? ($k === 'peter' ? 'apology' : 'absent') : 'present', 'recorded_by' => $sec->id]);
        }
        $agenda = $past->agendaItems()->get();
        $past->decisions()->create(['agenda_item_id' => $agenda[2]->id, 'title' => 'DEMO: Vikao viwili vya mtandaoni', 'body' => 'Kutakuwa na vikao vya mtandaoni Septemba na Desemba (mfano).', 'type' => 'amendment', 'status' => 'recorded', 'recorded_by' => $sec->id]);
        $past->decisions()->create(['agenda_item_id' => $agenda[3]->id, 'title' => 'DEMO: Faini kuanza', 'body' => 'Faini za kuchelewa zitaanza — kiasi hakikuandikwa (mfano wa uamuzi usiokamilika).', 'type' => 'financial', 'status' => 'needs_confirmation', 'recorded_by' => $sec->id]);
        $past->actionItems()->create(['title' => 'Kutafiti fursa za kununua hisa', 'assignee_member_id' => $this->m['joseph']->id, 'due_on' => now()->subDays(10), 'created_by' => $sec->id]);
        $past->actionItems()->create(['title' => 'Kuandaa barua ya ziara ya hospitali', 'assignee_member_id' => $this->m['asec']->id, 'due_on' => now()->addDays(10), 'status' => 'done', 'completed_at' => now(), 'created_by' => $sec->id]);
        $past->minutes()->create(['status' => 'approved', 'opening_notes' => 'Kikao kilifunguliwa kwa sala (mfano).', 'next_meeting_note' => 'Kikao kijacho: mtandaoni, Desemba', 'prepared_by' => $sec->id, 'submitted_at' => now()->subMonths(3), 'approved_by' => $chair->id, 'approved_at' => now()->subMonths(3)]);

        Meeting::create(['title' => 'DEMO: Kikao cha mtandaoni — Desemba', 'type' => 'online', 'starts_at' => now()->addDays(9)->setTime(20, 0), 'online_method' => 'WhatsApp video call', 'organizer_member_id' => $this->m['sec']->id, 'status' => 'planned', 'is_demo' => true, 'created_by' => $sec->id])
            ->agendaItems()->createMany(collect(['Utangulizi', 'Yatokanayo na kikao kilichopita', 'Taarifa ya fedha', 'Mambo mengineyo (AOB)'])->map(fn ($t, $i) => ['position' => $i + 1, 'title' => $t])->all());

        ActionItem::create(['title' => 'DEMO: Ombi la kurekebisha tarehe ya kuzaliwa', 'notes' => 'Mfano wa ombi la mwanachama.', 'assignee_member_id' => $this->m['sec']->id, 'due_on' => now()->addDays(5), 'created_by' => $this->m['esther']->user->id]);

        WelfareCase::create(['type' => 'bereavement', 'title' => 'DEMO: Msiba wa baba wa Faraja', 'summary' => 'Mwanafamilia mwenzetu Faraja amefiwa na baba yake mzazi. Taarifa za maziko zitatolewa (mfano).', 'private_details' => 'Taarifa za siri za mfano.', 'beneficiary_member_id' => $this->m['faraja']->id, 'relationship' => 'parent', 'visibility' => 'members', 'status' => 'submitted', 'submitted_by' => $this->m['vice']->user->id, 'is_demo' => true]);
        $charity = CharityActivity::create(['title' => 'DEMO: Ziara ya hospitali — kulipia bili za wagonjwa', 'beneficiary' => 'Hospitali ya mfano', 'planned_for' => now()->subDays(40)->setTime(11, 0), 'status' => 'done', 'budget' => 650000, 'coordinator_member_id' => $this->m['vice']->id, 'report' => 'Tuliwatembelea wagonjwa na kulipia bili za wagonjwa watatu (mfano).', 'created_by' => $this->m['vice']->user->id, 'is_demo' => true]);
        $charity->volunteers()->attach([$this->m['vice']->id => ['attended' => true], $this->m['esther']->id => ['attended' => true], $this->m['agnes']->id => ['attended' => false]]);
    }

    private function events(): void
    {
        $vice = $this->m['vice'];
        $event = Event::create(['type' => 'gathering', 'title' => 'DEMO: Mkusanyiko wa Familia wa Desemba', 'description' => 'Siku ya familia: chakula, michezo ya watoto, picha ya pamoja ya mwaka na Secreto.', 'starts_at' => now()->addDays(25)->setTime(13, 0), 'venue' => 'Ukumbi wa mfano, Goba', 'organizer_member_id' => $vice->id, 'visibility' => 'members', 'budget_amount' => 1560000, 'expected_attendance' => 45, 'status' => 'confirmed', 'fund_id' => Fund::byKey('general')->id, 'is_demo' => true, 'created_by' => $vice->user->id]);
        $committees = [];
        foreach (['Chakula' => 'esther', 'Mapambo' => 'agnes', 'Vinywaji' => 'peter', 'Picha na Video' => 'joseph', 'Watoto' => 'faraja', 'Keki' => 'vice'] as $name => $lead) {
            $committees[$name] = $event->committees()->create(['name' => $name, 'lead_member_id' => $this->m[$lead]->id, 'sort' => count($committees)]);
            $committees[$name]->members()->attach([$this->m[$lead]->id]);
        }
        $tasks = [
            ['Kuthibitisha ukumbi', 'Chakula', 'vice', -5, 'done'], ['Kuagiza mbuzi na kuku', 'Chakula', 'esther', 7, 'doing'], ['Menyu ya mwisho', 'Chakula', 'esther', -2, 'todo'],
            ['Rangi na stage', 'Mapambo', 'agnes', 10, 'todo'], ['Maji, soda, juisi za watoto', 'Vinywaji', 'peter', 12, 'todo'], ['Kamera na stand', 'Picha na Video', 'joseph', 15, 'blocked'],
            ['Jumping castle', 'Watoto', 'faraja', 9, 'doing'], ['Keki na cupcakes', 'Keki', 'vice', 20, 'todo'],
        ];
        foreach ($tasks as $i => [$title, $c, $owner, $days, $status]) {
            $event->tasks()->create(['title' => $title, 'event_committee_id' => $committees[$c]->id, 'owner_member_id' => $this->m[$owner]->id, 'due_at' => now()->addDays($days)->setTime(18, 0), 'status' => $status, 'sort' => $i, 'completed_at' => $status === 'done' ? now() : null]);
        }
        $vendor = Vendor::create(['name' => 'Mpishi wa mfano', 'service' => 'Chakula', 'phone' => '255700000099', 'notes' => 'DEMO']);
        foreach ([['Mbuzi 1', 350000, 'Chakula'], ['Kuku wa kienyeji 6', 160000, 'Chakula'], ['Vimchicha 5L × 6', 138000, 'Vinywaji'], ['Keki na cupcakes', 160000, 'Keki'], ['Mchomaji', 150000, 'Chakula']] as [$label, $amt, $c]) {
            $event->budgetLines()->create(['label' => $label, 'estimated_amount' => $amt, 'event_committee_id' => $committees[$c]->id, 'vendor_id' => $c === 'Chakula' ? $vendor->id : null]);
        }
        foreach (['chair' => 'yes', 'vice' => 'yes', 'esther' => 'yes', 'agnes' => 'maybe', 'peter' => 'no', 'samweli' => 'yes'] as $k => $r) {
            EventRsvp::create(['event_id' => $event->id, 'member_id' => $this->m[$k]->id, 'response' => $r, 'guests' => $r === 'yes' ? rand(0, 3) : 0]);
        }
        Event::create(['type' => 'milestone', 'title' => 'DEMO: Ubarikio wa vijana wetu', 'starts_at' => now()->subMonths(10)->setTime(10, 0), 'venue' => 'Ukumbi wa mfano', 'visibility' => 'members', 'status' => 'done', 'report' => 'Sherehe ilienda vizuri; kamati 10 zilifanya kazi (mfano).', 'lessons' => 'Kuthibitisha ukumbi mapema.', 'is_demo' => true]);

        $season = SecretoSeason::create(['year' => now()->year + 1, 'title' => 'Secreto '.(now()->year + 1).' (DEMO)', 'gift_minimum' => null, 'allow_vouchers' => true, 'status' => 'registration', 'registration_closes_at' => now()->addDays(20), 'exchange_at' => now()->addMonths(8), 'venue' => 'Mkusanyiko wa Juni', 'rules' => "Zawadi iletwe mkononi au kupitia mwakilishi.\n(Kiasi cha chini kinasubiri uthibitisho wa uongozi.)", 'created_by' => $this->m['sec']->user->id]);
        foreach (['chair', 'vice', 'sec', 'esther', 'agnes', 'samweli', 'faraja', 'tumaini'] as $k) {
            SecretoParticipant::create(['secreto_season_id' => $season->id, 'member_id' => $this->m[$k]->id, 'wishlist' => $k === 'esther' ? 'Vitabu, mimea ya ndani, rangi ya kijani.' : null]);
        }
        SecretoSeason::create(['year' => now()->year, 'title' => 'Secreto '.now()->year.' (DEMO)', 'status' => 'exchanged', 'memories' => 'Shangwe na vifijo! Kila mmoja alileta zawadi yake (mfano).']);
    }

    private function community(): void
    {
        $sec = $this->m['sec']->user;
        Announcement::create(['title' => 'DEMO: Michango sasa kupitia akaunti ya pamoja', 'body' => "Wapendwa, kuanzia sasa michango ya mwezi itumwe kwenye akaunti ya pamoja ya familia na risiti itumwe kupitia mfumo huu.\nMaelekezo kamili yako kwenye ukurasa wa \"Tuma taarifa ya malipo\".", 'kind' => 'official', 'is_important' => true, 'is_pinned' => true, 'visibility' => 'members', 'author_user_id' => $this->m['vice']->user->id, 'published_at' => now()->subDays(6), 'is_demo' => true]);
        Announcement::create(['title' => 'DEMO: Kikao cha mtandaoni Desemba', 'body' => 'Kikao kitafanyika kwa WhatsApp video call saa 2 usiku. Agenda iko kwenye ukurasa wa Vikao.', 'kind' => 'official', 'visibility' => 'members', 'author_user_id' => $sec->id, 'published_at' => now()->subDays(2), 'is_demo' => true]);
        $post = Announcement::create(['title' => 'Asanteni kwa siku nzuri!', 'body' => 'Tumefika salama. Ilikuwa siku ya furaha sana — Mungu atuweke pamoja. (Mfano)', 'kind' => 'post', 'visibility' => 'members', 'author_user_id' => $this->m['esther']->user->id, 'published_at' => now()->subDays(1), 'is_demo' => true]);
        $post->comments()->create(['user_id' => $this->m['chair']->user->id, 'body' => 'Amina! Tunawapenda sana.']);
        $post->reactions()->create(['user_id' => $this->m['vice']->user->id, 'emoji' => '❤️']);
        $post->reactions()->create(['user_id' => $this->m['agnes']->user->id, 'emoji' => '🙏']);

        $poll = Poll::create(['question' => 'DEMO: Mkusanyiko wa Pasaka 2027 ufanyike wapi?', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(5), 'eligibility' => 'members', 'result_visibility' => 'after_vote', 'created_by' => $this->m['vice']->user->id, 'is_demo' => true]);
        foreach (['Nyumbani kwa familia mojawapo', 'Ufukweni (Bahari Beach)', 'Safari nje ya Dar'] as $i => $o) {
            $poll->options()->create(['label' => $o, 'sort' => $i]);
        }
        $opts = $poll->options()->pluck('id');
        foreach (['vice' => 1, 'esther' => 1, 'peter' => 0, 'agnes' => 2] as $k => $i) {
            $poll->votes()->create(['poll_option_id' => $opts[$i], 'user_id' => $this->m[$k]->user->id]);
        }
    }

    /** Abstract warm placeholder images (no real people) so the gallery can be previewed. */
    private function memories(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }
        $album = Album::create(['title' => 'DEMO: Mkusanyiko wa Juni '.now()->year, 'description' => 'Picha za mfano (michoro tu, si watu halisi).', 'year' => (int) now()->year, 'is_annual_portrait' => true, 'visibility' => 'members', 'created_by' => $this->m['sec']->user->id, 'is_demo' => true]);
        $palettes = [[[20, 50, 34], [201, 154, 46]], [[192, 103, 77], [251, 247, 239]], [[35, 87, 61], [239, 220, 166]], [[138, 63, 43], [221, 182, 90]], [[27, 68, 48], [192, 103, 77]], [[132, 95, 20], [227, 238, 228]]];
        foreach ($palettes as $i => [$a, $b]) {
            [$w, $hgt] = $i % 3 === 0 ? [1200, 1500] : [1500, 1000];
            $img = imagecreatetruecolor($w, $hgt);
            for ($y = 0; $y < $hgt; $y++) {
                $t = $y / $hgt;
                imageline($img, 0, $y, $w, $y, imagecolorallocate($img, (int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t)));
            }
            for ($c = 0; $c < 7; $c++) {
                $col = imagecolorallocatealpha($img, 255, 253, 248, rand(90, 115));
                imagefilledellipse($img, rand(0, $w), rand(0, $hgt), $r = rand(120, 520), $r, $col);
            }
            $name = 'albums/'.$album->public_id.'/'.Str::random(32);
            foreach (['' => [$w, 85], '_t' => [640, 78]] as $suffix => [$tw, $q]) {
                $th = (int) round($hgt * $tw / $w);
                $out = imagecreatetruecolor($tw, $th);
                imagecopyresampled($out, $img, 0, 0, 0, 0, $tw, $th, $w, $hgt);
                ob_start();
                imagejpeg($out, null, $q);
                Storage::disk('private')->put($name.$suffix.'.jpg', ob_get_clean());
            }
            $media = $album->media()->create(['uploaded_by' => $this->m['sec']->user->id, 'path' => $name.'.jpg', 'thumb_path' => $name.'_t.jpg', 'original_name' => 'demo-'.($i + 1).'.jpg', 'mime' => 'image/jpeg', 'size' => Storage::disk('private')->size($name.'.jpg'), 'width' => $w, 'height' => $hgt, 'caption' => ['Picha ya pamoja (mfano)', 'Watoto wakicheza', 'Meza ya chakula', 'Keki ya familia', 'Jioni njema', 'Secreto!'][$i]]);
            $album->cover_media_id ??= $media->id;
        }
        $album->save();
    }
}
