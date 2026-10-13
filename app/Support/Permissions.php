<?php

namespace App\Support;

/**
 * Single source of truth for permissions and the default role matrix.
 * Access is always checked by permission key, never by role name.
 * Roles can be re-shaped in Admin → Roles without code changes.
 */
final class Permissions
{
    /** key => [group, description] */
    public const ALL = [
        'members.view' => ['members', 'See the family directory'],
        'members.view_private' => ['members', 'See private fields: birth year, phone, emergency contact, children, leader notes'],
        'members.manage' => ['members', 'Add/edit members, invite, change membership status, record relationships'],
        'members.import' => ['members', 'Review and confirm imported member lists'],
        'roles.manage' => ['admin', 'Assign roles and edit role permissions'],

        'finance.view_own' => ['finance', 'See own contribution statement'],
        'finance.submit_payment' => ['finance', 'Submit a payment notification with receipt'],
        'finance.view_summary' => ['finance', 'See aggregate fund balances and approved family statements'],
        'finance.view_all' => ['finance', 'See every member statement, the ledger and reconciliation'],
        'finance.verify' => ['finance', 'Verify/reject payment submissions and record received payments'],
        'finance.manage' => ['finance', 'Generate obligations, waive, transfers, opening balances, valuations, penalties'],
        'finance.reverse' => ['finance', 'Reverse a confirmed payment or transaction (with reason)'],
        'finance.request_expense' => ['finance', 'Request an expense'],
        'finance.approve_expense' => ['finance', 'Approve or reject expenses (never own requests)'],
        'finance.pay_expense' => ['finance', 'Record payment of an approved expense'],
        'finance.export' => ['finance', 'Download financial reports (PDF/CSV)'],
        'finance.rules' => ['finance', 'Draft contribution rules (activation needs governance.confirm_rules)'],

        'governance.manage_meetings' => ['governance', 'Create meetings, agendas, attendance, minutes, decisions, action items'],
        'governance.approve_minutes' => ['governance', 'Approve submitted minutes (never minutes you prepared)'],
        'governance.confirm_rules' => ['governance', 'Confirm or change family rules, approve contribution rules and constitution versions'],
        'governance.propose_amendment' => ['governance', 'Propose a constitution amendment'],
        'governance.manage_constitution' => ['governance', 'Edit constitution drafts and review amendments'],
        'governance.compliance' => ['governance', 'Run the arrears warning/review process'],

        'events.manage' => ['events', 'Create and manage events, committees, budgets, vendors'],
        'events.manage_tasks' => ['events', 'Update committee tasks and checklists'],

        'welfare.submit' => ['welfare', 'Report a bereavement or welfare need'],
        'welfare.approve' => ['welfare', 'Approve or reject welfare cases'],
        'welfare.view_private' => ['welfare', 'See private case details and private cases'],
        'welfare.disburse' => ['welfare', 'Record welfare support payments'],
        'charity.manage' => ['welfare', 'Plan charity activities and record outcomes'],

        'secreto.manage' => ['secreto', 'Run a Secreto season (open registration, draw) — cannot see pairs'],
        'secreto.reveal' => ['secreto', 'Break-glass: reveal all Secreto pairs (audited)'],

        'community.post' => ['community', 'Write family posts, comments and reactions'],
        'community.announce_official' => ['community', 'Publish official announcements'],
        'community.moderate' => ['community', 'Hide posts/comments, pin notices'],
        'community.create_poll' => ['community', 'Create polls'],

        'memories.upload' => ['memories', 'Upload photos'],
        'memories.manage' => ['memories', 'Create albums, handle takedown requests'],

        'notifications.manage' => ['notifications', 'Compose and schedule WhatsApp notifications'],
        'notifications.approve' => ['notifications', 'Approve large broadcasts (never own)'],
        'notifications.view_logs' => ['notifications', 'See delivery logs and provider responses'],

        'admin.settings' => ['admin', 'System settings, templates, reminder rules, integration status'],
        'admin.audit' => ['admin', 'Read the audit log'],
    ];

    private const MEMBER = [
        'members.view', 'finance.view_own', 'finance.submit_payment', 'finance.view_summary',
        'governance.propose_amendment', 'welfare.submit', 'community.post', 'memories.upload',
    ];

    /** role key => [name_sw, name_en, sort, permissions] */
    public static function roles(): array
    {
        $leaderBase = array_merge(self::MEMBER, ['members.view_private', 'community.create_poll', 'events.manage_tasks', 'finance.request_expense']);

        return [
            'super_admin' => ['Msimamizi Mkuu wa Mfumo', 'Super Administrator', 1, ['*']],
            'chairperson' => ['Mwenyekiti', 'Chairperson', 10, array_merge($leaderBase, [
                'finance.view_all', 'finance.reverse', 'finance.approve_expense', 'finance.export',
                'governance.manage_meetings', 'governance.approve_minutes', 'governance.confirm_rules', 'governance.compliance',
                'events.manage', 'welfare.approve', 'welfare.view_private', 'charity.manage', 'secreto.manage',
                'community.announce_official', 'community.moderate', 'memories.manage',
                'notifications.manage', 'notifications.approve', 'notifications.view_logs', 'admin.audit', 'members.manage',
            ])],
            'vice_chairperson' => ['Mwenyekiti Msaidizi', 'Vice Chairperson', 20, array_merge($leaderBase, [
                'finance.approve_expense', 'governance.manage_meetings', 'governance.approve_minutes', 'governance.confirm_rules',
                'events.manage', 'welfare.approve', 'welfare.view_private', 'charity.manage', 'secreto.manage',
                'community.announce_official', 'community.moderate', 'memories.manage', 'notifications.manage', 'notifications.approve',
            ])],
            'secretary' => ['Katibu', 'Secretary', 30, array_merge($leaderBase, [
                'members.manage', 'members.import', 'governance.manage_meetings', 'governance.manage_constitution', 'governance.compliance',
                'events.manage', 'welfare.view_private', 'charity.manage', 'secreto.manage',
                'community.announce_official', 'community.moderate', 'memories.manage', 'notifications.manage', 'notifications.view_logs',
            ])],
            'assistant_secretary' => ['Katibu Msaidizi', 'Assistant Secretary', 40, array_merge($leaderBase, [
                'governance.manage_meetings', 'events.manage', 'community.announce_official', 'notifications.manage',
            ])],
            'treasurer' => ['Mhazini', 'Treasurer', 50, array_merge($leaderBase, [
                'finance.view_all', 'finance.verify', 'finance.manage', 'finance.reverse', 'finance.pay_expense', 'finance.export', 'finance.rules',
                'governance.compliance', 'welfare.view_private', 'welfare.disburse', 'notifications.manage', 'members.import',
            ])],
            'assistant_treasurer' => ['Mhazini Msaidizi', 'Assistant Treasurer', 60, array_merge($leaderBase, [
                'finance.view_all', 'finance.verify', 'finance.export',
            ])],
            'committee_leader' => ['Kiongozi wa Kamati', 'Committee Leader', 70, array_merge(self::MEMBER, ['events.manage_tasks', 'finance.request_expense'])],
            'auditor' => ['Mkaguzi', 'Auditor (read-only)', 80, array_merge(self::MEMBER, ['finance.view_all', 'finance.export', 'admin.audit', 'notifications.view_logs'])],
            'member' => ['Mwanachama', 'General Member', 90, self::MEMBER],
        ];
    }
}
