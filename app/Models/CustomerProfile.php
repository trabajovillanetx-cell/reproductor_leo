<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    public const PER_ACCOUNT_LIMIT = 5;

    protected $fillable = [
        'user_id',
        'name',
        'avatar_url',
        'pin_hash',
        'sort_order',
        'is_sold',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_sold' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
