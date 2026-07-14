<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $guarded = [];

    protected $casts = [
        'promoted' => 'boolean',
        'remote_created_at' => 'datetime',
        'remote_updated_at' => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $driver = $query->getConnection()->getDriverName();
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        if (in_array($driver, ['mysql', 'mariadb'])) {
            return $query->whereRaw(
                'MATCH(title, body_text) AGAINST (? IN NATURAL LANGUAGE MODE)',
                [$term]
            )->orderByRaw(
                'MATCH(title, body_text) AGAINST (?) DESC',
                [$term]
            );
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
              ->orWhere('body_text', 'like', $like);
        });
    }
}
