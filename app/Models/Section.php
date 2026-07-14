<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $guarded = [];

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
