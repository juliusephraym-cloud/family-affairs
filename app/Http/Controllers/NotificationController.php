<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Member;
use App\Models\NotificationJob;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Services\Messaging\WhatsAppProvider;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request, WhatsAppProvider $provider): View
    {
        $user = $request->user();
        abort_unless($user->hasAnyPermission('notifications.manage', 'notifications.view_logs'), 403);

        return view('notifications.index', [
            'jobs' => NotificationJob::with('template', 'creator.member', 'approver.member')->withCount([
                'deliveries', 'deliveries as delivered_count' => fn ($q) => $q->whereIn('status', ['delivered', 'read']),
                'deliveries as failed_count' => fn ($q) => $q->whereIn('status', ['failed', 'undeliverable']),
            ])->latest()->paginate(20),
            'provider' => ['name' => $provider->name(), 'configured' => $provider->isConfigured(), 'test_mode' => config('family.nextsms.test_mode')],
        ]);
    }

    public function create(Request $request): View
    {
        return view('notifications.create', [
            'templates' => NotificationTemplate::where('is_active', true)->where('key', '!=', 'otp')->orderBy('category')->get(),
            'roles' => Role::where('key', '!=', 'super_admin')->orderBy('sort')->get(),
            'events' => Event::whereIn('status', ['planned', 'confirmed'])->orderBy('starts_at')->get(),
            'members' => Member::whereNotNull('phone_e164')->orderBy('full_name')->get(['id', 'full_name']),
            'price' => (int) config('family.nextsms.price_per_message'),
        ]);
    }

    /** Live preview + recipient count + cost estimate (JSON, used by the compose form). */
    public function preview(Request $request): JsonResponse
    {
        [$template, $audience, $vars] = $this->parse($request);
        $recipients = $this->notifications->resolveAudience($audience, $template->category);

        return response()->json([
            'preview' => $this->notifications->previewFor($template, $vars, $recipients->first()),
            'recipients' => $recipients->count(),
            'cost' => $recipients->count() * (int) config('family.nextsms.price_per_message'),
            'needs_approval' => $recipients->count() > (int) config('family.messaging.approval_threshold'),
            'sendable' => $template->isSendable(),
            'sensitive' => $template->is_sensitive,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$template, $audience, $vars] = $this->parse($request);
        $d = $request->validate(['title' => ['required', 'string', 'max:160'], 'when' => ['required', Rule::in(['now', 'later'])], 'scheduled_at' => ['nullable', 'required_if:when,later', 'date', 'after:now']]);
        $at = $d['when'] === 'now' ? now() : Carbon::parse($d['scheduled_at']);
        $job = $this->notifications->schedule($template, $d['title'], $audience, $at, $vars, $request->user());

        return redirect()->route('notifications.show', $job)->with('status', $job->status === 'pending_approval'
            ? __('Ujumbe unasubiri idhini ya kiongozi mwingine kwa sababu unawafikia watu wengi.')
            : __('Ujumbe umepangwa. Utatumwa na mfumo kwa wakati — hata kama kivinjari kimefungwa.'));
    }

    public function show(Request $request, NotificationJob $job): View
    {
        $user = $request->user();
        abort_unless($user->hasAnyPermission('notifications.manage', 'notifications.view_logs'), 403);
        $job->load('template', 'creator.member', 'approver.member');

        return view('notifications.show', [
            'job' => $job,
            'deliveries' => $job->deliveries()->with('member')->orderBy('status')->paginate(50),
            'counts' => $job->deliveries()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'canLogs' => $user->hasPermission('notifications.view_logs'),
        ]);
    }

    public function approve(Request $request, NotificationJob $job): RedirectResponse
    {
        $this->notifications->approve($job, $request->user());

        return back()->with('status', __('Umeidhinisha. Ujumbe utatumwa kwa wakati uliopangwa.'));
    }

    public function cancel(Request $request, NotificationJob $job): RedirectResponse
    {
        $this->notifications->cancel($job, $request->user());

        return back()->with('status', __('Ujumbe umesitishwa.'));
    }

    public function retry(Request $request, NotificationJob $job): RedirectResponse
    {
        $n = $this->notifications->retryFailed($job, $request->user());

        return back()->with('status', __('Jumbe :n zilizoshindwa zimerudishwa kwenye foleni.', ['n' => $n]));
    }

    /** @return array{0: NotificationTemplate, 1: array, 2: array} */
    private function parse(Request $request): array
    {
        $d = $request->validate([
            'template_id' => ['required', 'exists:notification_templates,id'],
            'audience' => ['required', Rule::in(NotificationService::AUDIENCES)],
            'role' => ['nullable', 'string', 'exists:roles,key'],
            'event_id' => ['nullable', 'exists:events,id'],
            'member_ids' => ['nullable', 'array'], 'member_ids.*' => ['integer'],
            'vars' => ['nullable', 'array'], 'vars.*' => ['nullable', 'string', 'max:300'],
        ]);
        $template = NotificationTemplate::findOrFail($d['template_id']);
        $audience = array_filter([
            'type' => $d['audience'], 'role' => $d['role'] ?? null, 'event_id' => $d['event_id'] ?? null,
            'member_ids' => $d['audience'] === 'members' ? array_map('intval', $d['member_ids'] ?? []) : null,
        ], fn ($v) => $v !== null);
        // Members' names, balances and portal links are filled per recipient by the system, never typed.
        $vars = collect($d['vars'] ?? [])->except(['name', 'amount', 'months'])->filter(fn ($v) => filled($v))->all();

        return [$template, $audience, $vars];
    }
}
