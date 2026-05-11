<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XtreamSource extends Model
{
    protected $fillable = [
        'name',
        'host',
        'username',
        'password',
        'is_active',
        'live_category_id',
        'vod_category_id',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'password' => 'encrypted',
        ];
    }

    public function liveCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'live_category_id');
    }

    public function vodCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'vod_category_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }
}
