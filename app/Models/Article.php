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

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (is_string($value) && $value !== '' && ! in_array($key, ['slug', 'external_id', 'locale'])) {
            $value = \App\Support\PlaceholderResolver::resolve($value);
        }

        if (app()->getLocale() === 'zh-cn' && in_array($key, ['title', 'body', 'body_text'])) {
            if (is_string($value) && $value !== '') {
                return \App\Support\Translator::translate($value);
            }
        }

        return $value;
    }

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

        if (app()->getLocale() === 'zh-cn') {
            $term = \App\Support\Translator::translateToEnglish($term);
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
