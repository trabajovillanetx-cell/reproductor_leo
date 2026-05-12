<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybackToken extends Model
{
    use Prunable;

    protected $fillable = [
        'user_id',
        'customer_profile_id',
        'content_id',
        'token',
        'expires_at',
        'user_agent',
        'ip_address',
        'last_seen_at',
        'playback_status',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDay());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
