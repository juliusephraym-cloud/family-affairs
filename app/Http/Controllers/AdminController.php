<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\NotificationDelivery;
use App\Models\NotificationJob;
use App\Models\NotificationTemplate;
use App\Models\OtpChallenge;
use App\Models\Permission;
use App\Models\ReminderRule;
use App\Models\Role;
use App\Models\User;
use App\Services\Messaging\WhatsAppProvider;
use App\Support\Audit;
use App\Support\ImageStore;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request, WhatsAppProvider $provider): View
    {
        $user = $request->user();
        abort_unless($user->hasAnyPermission('admin.settings', 'roles.manage', 'admin.audit', 'members.import'), 403);
        $since = now()->subDay();

        return view('admin.index', [
            'health' => [
                'database' => rescue(fn () => DB::select('select 1') !== null, false, false),
                'queue_backlog' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'scheduler_last_run' => Cache::get('scheduler:heartbeat'),
                'storage_writable' => is_writable(storage_path('app/private')) || is_writable(storage_path('app')),
                'env' => app()->environment(),
                'debug' => config('app.debug'),
            ],
            'provider' => ['name' => $provider->name(), 'configured' => $provider->isConfigured(), 'test_mode' => config('family.nextsms.test_mode'),
                'balance' => $user->hasPermission('admin.settings') ? Cache::remember('provider-balance', 600, fn () => $provider->balance()) : null,
                'webhook' => filled(config('family.nextsms.webhook_token')), 'otp_template' => config('family.nextsms.otp_template')],
            'deliveries' => NotificationDelivery::where('created_at', '>=', now()->subDays(7))->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'failedJobs' => NotificationJob::where('status', 'failed')->latest()->limit(5)->get(),
            'security' => [
                'otp_failures' => AuditLog::where('action', 'auth.otp_failed')->where('created_at', '>=', $since)->count(),
                'otp_issued' => OtpChallenge::where('created_at', '>=', $since)->count(),
                'otp_send_failed' => OtpChallenge::where('created_at', '>=', $since)->where('delivery_status', 'failed')->count(),
                'invite_mismatch' => AuditLog::where('action', 'auth.invite_phone_mismatch')->where('created_at', '>=', $since)->count(),
                'rejected_webhooks' => DB::table('provider_webhook_events')->where('is_valid', false)->where('created_at', '>=', $since)->count(),
            ],
            'users' => User::with('member', 'roles')->whereHas('roles', fn ($q) => $q->where('key', '!=', 'member'))->get(),
            'pendingInvites' => Invitation::with('member')->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->get(),
            'unverified' => Member::whereIn('membership_status', ['active', 'invited'])->whereDoesntHave('user', fn ($q) => $q->whereNotNull('phone_verified_at'))->count(),
            'recentAudit' => $user->hasPermission('admin.audit') ? AuditLog::with('actor.member')->latest('created_at')->limit(10)->get() : collect(),
            'settings' => collect(Settings::DEFAULTS)->keys()->mapWithKeys(fn ($k) => [$k => Settings::get($k)]),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'family_name' => ['required', 'string', 'max:80'],
            'family_welcome' => ['nullable', 'string', 'max:300'],
            'family_motto' => ['nullable', 'string', 'max:160'],
            'payments_instructions' => ['nullable', 'string', 'max:1500'],
            'quiet_start' => ['nullable', 'date_format:H:i'],
            'quiet_end' => ['nullable', 'date_format:H:i'],
            'emblem' => ['nullable', 'image', 'mimes:png,jpeg,webp', 'max:2048'],
            'remove_emblem' => ['boolean'],
        ]);
        $uid = $request->user()->id;
        Settings::set('family.name', $d['family_name'], $uid);
        Settings::set('family.welcome', $d['family_welcome'] ?? null, $uid);
        Settings::set('family.motto', $d['family_motto'] ?? null, $uid);
        Settings::set('payments.instructions', $d['payments_instructions'] ?? null, $uid);
        Settings::set('notifications.quiet_hours', ['start' => $d['quiet_start'] ?? null, 'end' => $d['quiet_end'] ?? null], $uid);
        if ($request->hasFile('emblem')) {
            Settings::set('family.emblem_path', ImageStore::storeResized($request->file('emblem'), 'branding', 512, 90), $uid);
        } elseif ($request->boolean('remove_emblem')) {
            Settings::set('family.emblem_path', null, $uid);
        }
        Audit::record('admin.settings', null, 'System settings updated');

        return back()->with('status', __('Mipangilio imehifadhiwa.'));
    }

    public function roles(): View
    {
        return view('admin.roles', ['roles' => Role::with('permissions')->withCount('users')->orderBy('sort')->get(), 'permissions' => Permission::orderBy('group')->orderBy('key')->get()->groupBy('group')]);
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->key === 'super_admin', 422);
        $d = $request->validate(['permissions' => ['array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);
        $before = $role->permissions->pluck('key')->all();
        $role->permissions()->sync($d['permissions'] ?? []);
        Audit::record('roles.permissions_changed', $role, "Permissions changed for role {$role->key}", ['before' => $before, 'after' => $role->fresh()->permissions->pluck('key')->all()]);

        return back()->with('status', __('Ruhusa za nafasi zimehifadhiwa.'));
    }

    public function audit(Request $request): View
    {
        return view('admin.audit', [
            'logs' => AuditLog::with('actor.member')
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->when($request->query('from'), fn ($q, $f) => $q->where('created_at', '>=', $f))
                ->latest('created_at')->paginate(50)->withQueryString(),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function templates(): View
    {
        return view('admin.templates', ['templates' => NotificationTemplate::orderBy('category')->get(), 'rules' => ReminderRule::with('template')->orderBy('key')->get()]);
    }

    public function updateTemplate(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $d = $request->validate([
            'provider_template' => ['nullable', 'string', 'max:120'],
            'provider_approval' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'body_preview' => ['required', 'string', 'max:1024'],
            'variables' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        $template->update([
            'provider_template' => $d['provider_template'] ?? null, 'provider_approval' => $d['provider_approval'], 'body_preview' => $d['body_preview'],
            'variables' => array_values(array_filter(array_map('trim', explode(',', (string) ($d['variables'] ?? ''))))), 'is_active' => $request->boolean('is_active'),
        ]);
        Audit::record('admin.template_updated', null, 'WhatsApp template updated: '.$template->key, ['approval' => $d['provider_approval']]);

        return back()->with('status', __('Kiolezo kimehifadhiwa.'));
    }

    public function updateReminder(Request $request, ReminderRule $rule): RedirectResponse
    {
        $d = $request->validate(['enabled' => ['boolean'], 'day' => ['nullable', 'integer', 'between:1,28'], 'time' => ['nullable', 'date_format:H:i'], 'hours_before' => ['nullable', 'integer', 'between:1,168'], 'days_before' => ['nullable', 'integer', 'between:1,60']]);
        $config = array_merge($rule->config, array_filter(collect($d)->except('enabled')->all(), fn ($v) => $v !== null));
        $rule->update(['enabled' => $request->boolean('enabled'), 'config' => $config]);
        Audit::record('admin.reminder_rule', $rule, "Reminder {$rule->key} ".($rule->enabled ? 'enabled' : 'disabled'), ['config' => $config]);

        return back()->with('status', __('Kikumbusho kimehifadhiwa.'));
    }
}
