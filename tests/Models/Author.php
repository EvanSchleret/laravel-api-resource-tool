<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    protected $guarded = [];

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
