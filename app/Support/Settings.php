<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/** Admin-editable settings (branding, quiet hours, payment instructions). Cached per request cycle. */
final class Settings
{
    public const DEFAULTS = [
        'family.name' => 'Family Affairs',
        // Placeholders until the family approves their own wording; motto/emblem stay empty on purpose.
        'family.welcome' => 'Nyumbani kwa familia yetu — mahali pa kukumbuka, kushirikiana na kusaidiana.',
        'family.motto' => null,
        'family.emblem_path' => null,
        'payments.instructions' => null,
        'notifications.quiet_hours' => ['start' => '21:00', 'end' => '07:00'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('system-settings', 300, fn () => SystemSetting::pluck('value', 'key')->all());
        $value = array_key_exists($key, $all) ? $all[$key] : null;
        if (is_array($value) && array_key_exists('v', $value) && count($value) === 1) {
            $value = $value['v'];
        }

        return $value ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        SystemSetting::updateOrCreate(['key' => $key], [
            'value' => is_array($value) ? $value : ['v' => $value],
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);
        Cache::forget('system-settings');
    }
}
