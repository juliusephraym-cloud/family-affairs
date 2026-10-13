<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'is_sensitive' => 'boolean', 'is_active' => 'boolean'];
    }

    /** Render the human preview by substituting {placeholders}. */
    public function render(array $vars): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $this->body_preview);
    }

    public function isSendable(): bool
    {
        return $this->is_active && filled($this->provider_template) && $this->provider_approval === 'approved';
    }
}
