<?php

use App\Http\Controllers;
use App\Http\Controllers\Auth\OtpAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (pre-login) — no family information is ever rendered here
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');
Route::post('/locale', Controllers\LocaleController::class)->name('locale');
Route::get('/branding/emblem', [Controllers\BrandingController::class, 'emblem'])->name('branding.emblem');

Route::middleware('guest')->group(function () {
    Route::get('/login', [OtpAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [OtpAuthController::class, 'sendLogin'])->middleware('throttle:otp-send')->name('login.send');
    Route::get('/invite/{token}', [OtpAuthController::class, 'showInvitation'])->middleware('throttle:invite')->name('invitation.show');
    Route::post('/invite/{token}', [OtpAuthController::class, 'sendInvitationCode'])->middleware(['throttle:invite', 'throttle:otp-send'])->name('invitation.send');
});
Route::get('/verify', [OtpAuthController::class, 'showVerify'])->name('verify.show');
Route::post('/verify', [OtpAuthController::class, 'verify'])->middleware('throttle:otp-verify')->name('verify.check');
Route::post('/verify/resend', [OtpAuthController::class, 'resend'])->middleware('throttle:otp-send')->name('verify.resend');

// NextSMS delivery reports. Authenticated by the shared verify token in the payload; CSRF-exempt.
Route::post('/webhooks/nextsms', [Controllers\WebhookController::class, 'nextsms'])->middleware('throttle:webhooks')->name('webhooks.nextsms');

if (app()->environment('local')) {
    Route::get('/dev/outbox', Controllers\DevOutboxController::class)->name('dev.outbox');
}

/*
|--------------------------------------------------------------------------
| Members' area
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'member'])->group(function () {
    Route::post('/logout', [OtpAuthController::class, 'logout'])->name('logout');
    Route::get('/welcome', [Controllers\ProfileController::class, 'welcome'])->name('welcome.show');
    Route::post('/welcome', [Controllers\ProfileController::class, 'completeWelcome'])->name('welcome.store');

    Route::get('/dashboard', Controllers\DashboardController::class)->name('dashboard');

    // Profile
    Route::get('/profile', [Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [Controllers\ProfileController::class, 'photo'])->middleware('throttle:uploads')->name('profile.photo');
    Route::put('/profile/notifications', [Controllers\ProfileController::class, 'notifications'])->name('profile.notifications');
    Route::post('/profile/phone', [OtpAuthController::class, 'sendPhoneChange'])->middleware('throttle:otp-send')->name('profile.phone');
    Route::post('/profile/request', [Controllers\ProfileController::class, 'request'])->name('profile.request');

    // Directory & member admin
    Route::get('/members', [Controllers\MemberController::class, 'index'])->middleware('can:members.view')->name('members.index');
    Route::get('/members/create', [Controllers\MemberController::class, 'create'])->middleware('can:members.manage')->name('members.create');
    Route::post('/members', [Controllers\MemberController::class, 'store'])->middleware('can:members.manage')->name('members.store');
    Route::get('/members/{member}', [Controllers\MemberController::class, 'show'])->middleware('can:members.view')->name('members.show');
    Route::get('/members/{member}/photo', [Controllers\MemberController::class, 'photo'])->name('members.photo');
    Route::get('/members/{member}/edit', [Controllers\MemberController::class, 'edit'])->middleware('can:members.manage')->name('members.edit');
    Route::put('/members/{member}', [Controllers\MemberController::class, 'update'])->middleware('can:members.manage')->name('members.update');
    Route::post('/members/{member}/status', [Controllers\MemberController::class, 'status'])->middleware('can:members.manage')->name('members.status');
    Route::post('/members/{member}/invite', [Controllers\MemberController::class, 'invite'])->middleware('can:members.manage')->name('members.invite');
    Route::post('/members/{member}/relationships', [Controllers\MemberController::class, 'addRelationship'])->middleware('can:members.manage')->name('members.relationships.store');
    Route::delete('/members/{member}/relationships/{relationship}', [Controllers\MemberController::class, 'removeRelationship'])->middleware('can:members.manage')->name('members.relationships.destroy');
    Route::post('/members/{member}/roles', [Controllers\MemberController::class, 'roles'])->middleware('can:roles.manage')->name('members.roles');
    Route::post('/households', [Controllers\MemberController::class, 'storeHousehold'])->middleware('can:members.manage')->name('households.store');

    // My finances
    Route::get('/finance', [Controllers\MyFinanceController::class, 'index'])->middleware('can:finance.view_own')->name('finance.mine');
    Route::get('/finance/pay', [Controllers\MyFinanceController::class, 'create'])->middleware('can:finance.submit_payment')->name('finance.pay');
    Route::post('/finance/pay', [Controllers\MyFinanceController::class, 'store'])->middleware(['can:finance.submit_payment', 'throttle:uploads'])->name('finance.pay.store');
    Route::post('/finance/submissions/{submission}/withdraw', [Controllers\MyFinanceController::class, 'withdraw'])->name('finance.withdraw');
    Route::get('/finance/statement.pdf', [Controllers\MyFinanceController::class, 'statementPdf'])->middleware('can:finance.view_own')->name('finance.statement');
    Route::get('/finance/submissions/{submission}/receipt', [Controllers\MyFinanceController::class, 'receipt'])->name('finance.receipt');

    // Treasury
    Route::prefix('treasury')->name('treasury.')->group(function () {
        Route::get('/', [Controllers\TreasuryController::class, 'index'])->name('index');
        Route::get('/submissions', [Controllers\PaymentReviewController::class, 'index'])->middleware('can:finance.verify')->name('submissions');
        Route::post('/submissions/{submission}/verify', [Controllers\PaymentReviewController::class, 'verify'])->middleware('can:finance.verify')->name('submissions.verify');
        Route::post('/submissions/{submission}/reject', [Controllers\PaymentReviewController::class, 'reject'])->middleware('can:finance.verify')->name('submissions.reject');
        Route::get('/payments/record', [Controllers\PaymentReviewController::class, 'createDirect'])->middleware('can:finance.verify')->name('payments.create');
        Route::post('/payments/record', [Controllers\PaymentReviewController::class, 'storeDirect'])->middleware('can:finance.verify')->name('payments.store');
        Route::post('/payments/{payment}/reverse', [Controllers\PaymentReviewController::class, 'reverse'])->middleware('can:finance.reverse')->name('payments.reverse');

        Route::get('/members', [Controllers\TreasuryController::class, 'members'])->middleware('can:finance.view_all')->name('members');
        Route::get('/members/{member}', [Controllers\TreasuryController::class, 'member'])->middleware('can:finance.view_all')->name('member');
        Route::get('/members/{member}/statement.pdf', [Controllers\TreasuryController::class, 'memberPdf'])->middleware('can:finance.export')->name('member.pdf');
        Route::post('/obligations/generate', [Controllers\TreasuryController::class, 'generate'])->middleware('can:finance.manage')->name('obligations.generate');
        Route::post('/obligations', [Controllers\TreasuryController::class, 'addObligation'])->middleware('can:finance.manage')->name('obligations.store');
        Route::post('/obligations/{obligation}/waive', [Controllers\TreasuryController::class, 'waive'])->middleware('can:finance.manage')->name('obligations.waive');
        Route::get('/penalties', [Controllers\TreasuryController::class, 'penalties'])->middleware('can:finance.manage')->name('penalties');
        Route::post('/penalties', [Controllers\TreasuryController::class, 'applyPenalties'])->middleware('can:finance.manage')->name('penalties.apply');

        Route::get('/ledger', [Controllers\LedgerController::class, 'index'])->middleware('can:finance.view_all')->name('ledger');
        Route::get('/ledger/{transaction}', [Controllers\LedgerController::class, 'show'])->middleware('can:finance.view_all')->name('ledger.show');
        Route::post('/ledger/{transaction}/reverse', [Controllers\LedgerController::class, 'reverse'])->middleware('can:finance.reverse')->name('ledger.reverse');
        Route::get('/accounts', [Controllers\LedgerController::class, 'accounts'])->middleware('can:finance.manage')->name('accounts');
        Route::post('/accounts/transfer', [Controllers\LedgerController::class, 'transfer'])->middleware('can:finance.manage')->name('transfer');
        Route::post('/accounts/interfund', [Controllers\LedgerController::class, 'interfund'])->middleware('can:finance.manage')->name('interfund');
        Route::post('/accounts/opening', [Controllers\LedgerController::class, 'opening'])->middleware('can:finance.manage')->name('opening');
        Route::post('/accounts/return', [Controllers\LedgerController::class, 'investmentReturn'])->middleware('can:finance.manage')->name('return');
        Route::post('/accounts/valuation', [Controllers\LedgerController::class, 'valuation'])->middleware('can:finance.manage')->name('valuation');

        Route::get('/expenses', [Controllers\ExpenseController::class, 'index'])->name('expenses');
        Route::post('/expenses', [Controllers\ExpenseController::class, 'store'])->middleware('can:finance.request_expense')->name('expenses.store');
        Route::post('/expenses/{expense}/decide', [Controllers\ExpenseController::class, 'decide'])->middleware('can:finance.approve_expense')->name('expenses.decide');
        Route::post('/expenses/{expense}/pay', [Controllers\ExpenseController::class, 'pay'])->middleware('can:finance.pay_expense')->name('expenses.pay');
        Route::get('/expenses/{expense}/document', [Controllers\ExpenseController::class, 'document'])->name('expenses.document');

        Route::get('/rules', [Controllers\ContributionRuleController::class, 'index'])->middleware('can:finance.view_all')->name('rules');
        Route::post('/rules', [Controllers\ContributionRuleController::class, 'store'])->middleware('can:finance.rules')->name('rules.store');
        Route::post('/rules/{rule}/approve', [Controllers\ContributionRuleController::class, 'approve'])->middleware('can:governance.confirm_rules')->name('rules.approve');
        Route::post('/rules/{rule}/retire', [Controllers\ContributionRuleController::class, 'retire'])->middleware('can:governance.confirm_rules')->name('rules.retire');

        Route::get('/reports', [Controllers\ReportController::class, 'index'])->middleware('can:finance.view_summary')->name('reports');
        Route::get('/reports/{report}.{format}', [Controllers\ReportController::class, 'download'])->where('format', 'pdf|csv')->middleware('can:finance.export')->name('reports.download');
    });

    // Governance
    Route::get('/governance', [Controllers\GovernanceController::class, 'index'])->name('governance.index');
    Route::get('/governance/constitution/{version}', [Controllers\GovernanceController::class, 'version'])->name('governance.version');
    Route::post('/governance/constitution', [Controllers\GovernanceController::class, 'storeVersion'])->middleware('can:governance.manage_constitution')->name('governance.version.store');
    Route::post('/governance/constitution/{version}/approve', [Controllers\GovernanceController::class, 'approveVersion'])->middleware('can:governance.confirm_rules')->name('governance.version.approve');
    Route::post('/governance/amendments', [Controllers\GovernanceController::class, 'propose'])->middleware('can:governance.propose_amendment')->name('governance.amendments.store');
    Route::post('/governance/amendments/{amendment}/review', [Controllers\GovernanceController::class, 'review'])->middleware('can:governance.manage_constitution')->name('governance.amendments.review');
    Route::post('/governance/rules/{rule}', [Controllers\GovernanceController::class, 'confirmRule'])->middleware('can:governance.confirm_rules')->name('governance.rules.confirm');
    Route::get('/governance/compliance', [Controllers\ComplianceController::class, 'index'])->middleware('can:governance.compliance')->name('governance.compliance');
    Route::post('/governance/compliance', [Controllers\ComplianceController::class, 'open'])->middleware('can:governance.compliance')->name('governance.compliance.open');
    Route::post('/governance/compliance/{case}', [Controllers\ComplianceController::class, 'advance'])->middleware('can:governance.compliance')->name('governance.compliance.advance');

    // Meetings
    Route::get('/meetings', [Controllers\MeetingController::class, 'index'])->name('meetings.index');
    Route::post('/meetings', [Controllers\MeetingController::class, 'store'])->middleware('can:governance.manage_meetings')->name('meetings.store');
    Route::get('/meetings/{meeting}', [Controllers\MeetingController::class, 'show'])->name('meetings.show');
    Route::put('/meetings/{meeting}', [Controllers\MeetingController::class, 'update'])->middleware('can:governance.manage_meetings')->name('meetings.update');
    Route::post('/meetings/{meeting}/agenda', [Controllers\MeetingController::class, 'addAgenda'])->middleware('can:governance.manage_meetings')->name('meetings.agenda.store');
    Route::put('/meetings/{meeting}/agenda/{item}', [Controllers\MeetingController::class, 'updateAgenda'])->middleware('can:governance.manage_meetings')->name('meetings.agenda.update');
    Route::delete('/meetings/{meeting}/agenda/{item}', [Controllers\MeetingController::class, 'removeAgenda'])->middleware('can:governance.manage_meetings')->name('meetings.agenda.destroy');
    Route::post('/meetings/{meeting}/attendance', [Controllers\MeetingController::class, 'attendance'])->middleware('can:governance.manage_meetings')->name('meetings.attendance');
    Route::post('/meetings/{meeting}/decisions', [Controllers\MeetingController::class, 'addDecision'])->middleware('can:governance.manage_meetings')->name('meetings.decisions.store');
    Route::post('/meetings/{meeting}/actions', [Controllers\MeetingController::class, 'addAction'])->middleware('can:governance.manage_meetings')->name('meetings.actions.store');
    Route::post('/actions/{action}/status', [Controllers\MeetingController::class, 'actionStatus'])->name('actions.status');
    Route::get('/meetings/{meeting}/minutes', [Controllers\MinutesController::class, 'edit'])->middleware('can:governance.manage_meetings')->name('minutes.edit');
    Route::put('/meetings/{meeting}/minutes', [Controllers\MinutesController::class, 'update'])->middleware('can:governance.manage_meetings')->name('minutes.update');
    Route::post('/meetings/{meeting}/minutes/submit', [Controllers\MinutesController::class, 'submit'])->middleware('can:governance.manage_meetings')->name('minutes.submit');
    Route::post('/meetings/{meeting}/minutes/decide', [Controllers\MinutesController::class, 'decide'])->middleware('can:governance.approve_minutes')->name('minutes.decide');
    Route::get('/meetings/{meeting}/minutes.pdf', [Controllers\MinutesController::class, 'pdf'])->name('minutes.pdf');
    Route::post('/meetings/{meeting}/fines', [Controllers\MeetingController::class, 'absenceFines'])->middleware('can:finance.manage')->name('meetings.fines');

    // Events
    Route::get('/events', [Controllers\EventController::class, 'index'])->name('events.index');
    Route::post('/events', [Controllers\EventController::class, 'store'])->middleware('can:events.manage')->name('events.store');
    Route::get('/events/{event}', [Controllers\EventController::class, 'show'])->name('events.show');
    Route::put('/events/{event}', [Controllers\EventController::class, 'update'])->middleware('can:events.manage')->name('events.update');
    Route::post('/events/{event}/rsvp', [Controllers\EventController::class, 'rsvp'])->name('events.rsvp');
    Route::post('/events/{event}/committees', [Controllers\EventPlanningController::class, 'storeCommittee'])->middleware('can:events.manage')->name('events.committees.store');
    Route::post('/events/{event}/committees/{committee}/members', [Controllers\EventPlanningController::class, 'committeeMembers'])->middleware('can:events.manage')->name('events.committees.members');
    Route::post('/events/{event}/tasks', [Controllers\EventPlanningController::class, 'storeTask'])->middleware('can:events.manage_tasks')->name('events.tasks.store');
    Route::post('/events/{event}/tasks/{task}/status', [Controllers\EventPlanningController::class, 'taskStatus'])->name('events.tasks.status');
    Route::post('/events/{event}/budget', [Controllers\EventPlanningController::class, 'storeBudgetLine'])->middleware('can:events.manage')->name('events.budget.store');
    Route::post('/events/{event}/budget/{line}', [Controllers\EventPlanningController::class, 'updateBudgetLine'])->middleware('can:events.manage')->name('events.budget.update');
    Route::post('/vendors', [Controllers\EventPlanningController::class, 'storeVendor'])->middleware('can:events.manage')->name('vendors.store');

    // Welfare & charity
    Route::get('/welfare', [Controllers\WelfareController::class, 'index'])->name('welfare.index');
    Route::post('/welfare', [Controllers\WelfareController::class, 'store'])->middleware('can:welfare.submit')->name('welfare.store');
    Route::get('/welfare/{case}', [Controllers\WelfareController::class, 'show'])->name('welfare.show');
    Route::post('/welfare/{case}/approve', [Controllers\WelfareController::class, 'approve'])->middleware('can:welfare.approve')->name('welfare.approve');
    Route::post('/welfare/{case}/reject', [Controllers\WelfareController::class, 'reject'])->middleware('can:welfare.approve')->name('welfare.reject');
    Route::post('/welfare/{case}/disburse', [Controllers\WelfareController::class, 'disburse'])->middleware('can:welfare.disburse')->name('welfare.disburse');
    Route::post('/welfare/{case}/close', [Controllers\WelfareController::class, 'close'])->middleware('can:welfare.approve')->name('welfare.close');
    Route::post('/charity', [Controllers\CharityController::class, 'store'])->middleware('can:charity.manage')->name('charity.store');
    Route::get('/charity/{activity}', [Controllers\CharityController::class, 'show'])->name('charity.show');
    Route::put('/charity/{activity}', [Controllers\CharityController::class, 'update'])->middleware('can:charity.manage')->name('charity.update');
    Route::post('/charity/{activity}/volunteer', [Controllers\CharityController::class, 'volunteer'])->name('charity.volunteer');

    // Secreto
    Route::get('/secreto', [Controllers\SecretoController::class, 'index'])->name('secreto.index');
    Route::post('/secreto/seasons', [Controllers\SecretoController::class, 'store'])->middleware('can:secreto.manage')->name('secreto.store');
    Route::put('/secreto/{season}', [Controllers\SecretoController::class, 'update'])->middleware('can:secreto.manage')->name('secreto.update');
    Route::post('/secreto/{season}/join', [Controllers\SecretoController::class, 'join'])->name('secreto.join');
    Route::post('/secreto/{season}/leave', [Controllers\SecretoController::class, 'leave'])->name('secreto.leave');
    Route::post('/secreto/{season}/me', [Controllers\SecretoController::class, 'updateMe'])->name('secreto.me');
    Route::post('/secreto/{season}/draw', [Controllers\SecretoController::class, 'draw'])->middleware('can:secreto.manage')->name('secreto.draw');
    Route::post('/secreto/{season}/reset', [Controllers\SecretoController::class, 'reset'])->middleware('can:secreto.manage')->name('secreto.reset');
    Route::post('/secreto/{season}/reveal', [Controllers\SecretoController::class, 'reveal'])->middleware('can:secreto.reveal')->name('secreto.reveal');

    // Memories
    Route::get('/memories', [Controllers\MemoriesController::class, 'index'])->name('memories.index');
    Route::post('/memories/albums', [Controllers\MemoriesController::class, 'storeAlbum'])->middleware('can:memories.manage')->name('memories.albums.store');
    Route::get('/memories/albums/{album}', [Controllers\MemoriesController::class, 'show'])->name('memories.show');
    Route::post('/memories/albums/{album}/upload', [Controllers\MemoriesController::class, 'upload'])->middleware(['can:memories.upload', 'throttle:uploads'])->name('memories.upload');
    Route::get('/memories/media/{media}/{variant}', [Controllers\MemoriesController::class, 'file'])->where('variant', 'thumb|full|download')->name('memories.file');
    Route::post('/memories/media/{media}/caption', [Controllers\MemoriesController::class, 'caption'])->name('memories.caption');
    Route::post('/memories/media/{media}/takedown', [Controllers\MemoriesController::class, 'takedown'])->name('memories.takedown');
    Route::post('/memories/takedowns/{takedown}', [Controllers\MemoriesController::class, 'resolveTakedown'])->middleware('can:memories.manage')->name('memories.takedown.resolve');

    // Community
    Route::get('/community', [Controllers\CommunityController::class, 'index'])->name('community.index');
    Route::post('/community/posts', [Controllers\CommunityController::class, 'store'])->middleware('can:community.post')->name('community.store');
    Route::get('/community/posts/{announcement}', [Controllers\CommunityController::class, 'show'])->name('community.show');
    Route::post('/community/posts/{announcement}/comments', [Controllers\CommunityController::class, 'comment'])->middleware('can:community.post')->name('community.comment');
    Route::post('/community/posts/{announcement}/react', [Controllers\CommunityController::class, 'react'])->middleware('can:community.post')->name('community.react');
    Route::post('/community/posts/{announcement}/moderate', [Controllers\CommunityController::class, 'moderate'])->middleware('can:community.moderate')->name('community.moderate');
    Route::post('/community/polls', [Controllers\PollController::class, 'store'])->middleware('can:community.create_poll')->name('polls.store');
    Route::get('/community/polls/{poll}', [Controllers\PollController::class, 'show'])->name('polls.show');
    Route::post('/community/polls/{poll}/vote', [Controllers\PollController::class, 'vote'])->name('polls.vote');

    // WhatsApp notifications
    Route::get('/notifications', [Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/compose', [Controllers\NotificationController::class, 'create'])->middleware('can:notifications.manage')->name('notifications.create');
    Route::post('/notifications/preview', [Controllers\NotificationController::class, 'preview'])->middleware('can:notifications.manage')->name('notifications.preview');
    Route::post('/notifications', [Controllers\NotificationController::class, 'store'])->middleware('can:notifications.manage')->name('notifications.store');
    Route::get('/notifications/{job}', [Controllers\NotificationController::class, 'show'])->name('notifications.show');
    Route::post('/notifications/{job}/approve', [Controllers\NotificationController::class, 'approve'])->middleware('can:notifications.approve')->name('notifications.approve');
    Route::post('/notifications/{job}/cancel', [Controllers\NotificationController::class, 'cancel'])->middleware('can:notifications.manage')->name('notifications.cancel');
    Route::post('/notifications/{job}/retry', [Controllers\NotificationController::class, 'retry'])->middleware('can:notifications.manage')->name('notifications.retry');

    // Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [Controllers\AdminController::class, 'index'])->name('index');
        Route::put('/settings', [Controllers\AdminController::class, 'settings'])->middleware('can:admin.settings')->name('settings');
        Route::get('/roles', [Controllers\AdminController::class, 'roles'])->middleware('can:roles.manage')->name('roles');
        Route::put('/roles/{role}', [Controllers\AdminController::class, 'updateRole'])->middleware('can:roles.manage')->name('roles.update');
        Route::get('/audit', [Controllers\AdminController::class, 'audit'])->middleware('can:admin.audit')->name('audit');
        Route::get('/templates', [Controllers\AdminController::class, 'templates'])->middleware('can:admin.settings')->name('templates');
        Route::put('/templates/{template}', [Controllers\AdminController::class, 'updateTemplate'])->middleware('can:admin.settings')->name('templates.update');
        Route::put('/reminders/{rule}', [Controllers\AdminController::class, 'updateReminder'])->middleware('can:admin.settings')->name('reminders.update');
        Route::get('/imports', [Controllers\ImportController::class, 'index'])->middleware('can:members.import')->name('imports');
        Route::post('/imports', [Controllers\ImportController::class, 'upload'])->middleware(['can:members.import', 'throttle:uploads'])->name('imports.upload');
        Route::get('/imports/{batch}', [Controllers\ImportController::class, 'show'])->middleware('can:members.import')->name('imports.show');
        Route::post('/imports/candidates/{candidate}', [Controllers\ImportController::class, 'decide'])->middleware('can:members.import')->name('imports.decide');
    });

    Route::get('/documents/{document}', [Controllers\DocumentController::class, 'download'])->name('documents.download');
});
