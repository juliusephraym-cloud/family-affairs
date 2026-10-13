<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail for sensitive operations. Callers must never pass
 * secrets (OTP codes, tokens) or full message bodies in $properties.
 */
final class Audit
{
    public static function record(string $action, ?Model $subject, string $description, array $properties = []): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 255),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : 'console',
        ]);
    }
}
