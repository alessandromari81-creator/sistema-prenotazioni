<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un file PDF allegato a un corso (dispensa, scheda di allenamento...).
 */
class CourseMaterial extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'title', 'file_path', 'size_bytes'];

    /** Conversioni automatiche dei campi quando si leggono dal database. */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** Il corso a cui appartiene il materiale. */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
