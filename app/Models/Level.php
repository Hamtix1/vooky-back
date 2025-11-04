<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Level extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'slug', 'order', 'course_id'];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Se ejecuta solo al CREAR un nuevo nivel
        static::creating(function (Level $level) {
            // Asigna el slug
            $level->slug = static::createUniqueSlug($level->title, $level->course_id);

            // Si no se especifica un orden, lo asigna al final
            if (is_null($level->order)) {
                $level->order = static::where('course_id', $level->course_id)->max('order') + 1;
            }
        });

        // Se ejecuta solo al ACTUALIZAR un nivel existente.
        static::updating(function (Level $level) {
            if ($level->isDirty('title')) {
                $level->slug = static::createUniqueSlug($level->title, $level->course_id, $level->id);
            }
        });
    }

    /**
     * Genera un slug único para el nivel dentro de su curso.
     */
    private static function createUniqueSlug(string $title, int $courseId, int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;
 
        // La consulta debe verificar la unicidad DENTRO del mismo curso.
        while (true) {
            $query = static::where('course_id', $courseId)->where('slug', $slug);
 
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
 
            if (!$query->exists()) {
                break;
            }
 
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }
}
