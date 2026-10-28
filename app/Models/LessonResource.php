<?php

namespace App\Models;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 #[Fillable(['lesson_id', 'original_name', 'file_path', 'file_size'])]
class LessonResource extends Model
{
      public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
