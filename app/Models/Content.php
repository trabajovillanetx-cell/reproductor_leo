<?php

namespace App\Models;

use App\Enums\ContentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Content extends Model
{
    protected $fillable = [
        'category_id',
        'xtream_source_id',
        'stream_id',
        'source_type',
        'title',
        'description',
        'type',
        'stream_url',
        'poster_url',
        'library_folder',
        'duration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => ContentType::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function xtreamSource(): BelongsTo
    {
        return $this->belongsTo(XtreamSource::class);
    }

    public function playbackTokens(): HasMany
    {
        return $this->hasMany(PlaybackToken::class);
    }

    /**
     * Contenido cuyo streaming es una URL http(s) — el patrón habitual al importar listas M3U.
     * RaiDrive y subidas locales registran URLs con prefijo local:.
     */
    public function scopeWhereRemoteStreamUrl(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('stream_url', 'like', 'http://%')
                ->orWhere('stream_url', 'like', 'https://%');
        });
    }
}
