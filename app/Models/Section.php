<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $guarded = [];

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (is_string($value) && $value !== '' && ! in_array($key, ['slug', 'external_id', 'locale', 'parent_external_id'])) {
            $value = \App\Support\PlaceholderResolver::resolve($value);
        }

        if (app()->getLocale() === 'zh-cn' && in_array($key, ['name', 'description'])) {
            if (is_string($value) && $value !== '') {
                return \App\Support\Translator::translate($value);
            }
        }

        return $value;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderBy('position');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'parent_external_id', 'external_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Section::class, 'parent_external_id', 'external_id');
    }
}
