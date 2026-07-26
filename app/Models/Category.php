<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $guarded = [];

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (is_string($value) && $value !== '' && ! in_array($key, ['slug', 'external_id', 'locale'])) {
            $value = \App\Support\PlaceholderResolver::resolve($value);
        }

        if (app()->getLocale() === 'zh-cn' && in_array($key, ['name', 'description'])) {
            if (is_string($value) && $value !== '') {
                return \App\Support\Translator::translate($value);
            }
        }

        return $value;
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    public function rootSections(): HasMany
    {
        return $this->hasMany(Section::class)
            ->whereNull('parent_external_id')
            ->orderBy('position');
    }

    public function articles()
    {
        return $this->hasManyThrough(Article::class, Section::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
