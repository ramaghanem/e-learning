<?php

namespace App\Models;

use App\Models\Course;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;



#[Fillable([
    'name',
    'description',
])]
class Category extends Model
{
    public function courses(): HasMany
{
    return $this->hasMany(Course::class);
}
}
