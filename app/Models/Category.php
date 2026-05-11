<?php

namespace App\Models;

use App\Enums\ContentType;
use App\Models\Content;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => ContentType::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('name');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    public static function orderedTreeOptions(bool $onlyActive = false): Collection
    {
        $walk = function (?int $parentId, int $depth) use (&$walk, $onlyActive): Collection {
            $out = collect();
            $query = static::query()
                ->where('parent_id', $parentId)
                ->when($onlyActive, fn ($q) => $q->where('is_active', true))
                ->orderBy('name');

            foreach ($query->get() as $cat) {
                $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                $out->push([
                    'id' => $cat->id,
                    'label' => $prefix.$cat->name.' ('.$cat->type->value.')',
                ]);
                $out = $out->merge($walk($cat->id, $depth + 1));
            }

            return $out;
        };

        return $walk(null, 0);
    }

    /**
     * @return list<int>
     */
    public function descendantIdsIncludingSelf(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            foreach ($child->descendantIdsIncludingSelf() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
