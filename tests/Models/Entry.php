<?php

namespace Tests\Models;

use EvanSchleret\LaravelApiResourceTool\Concerns\HasFilterOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\Enums\EntryStatus;

class Entry extends Model
{
    use HasFilterOptions;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'status' => EntryStatus::class,
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
