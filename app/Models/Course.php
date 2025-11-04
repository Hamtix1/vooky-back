<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 
        'description', 
        'slug', 
        'monthly_fee', 
        'requires_payment'
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'requires_payment' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     *
     * Se ejecuta automáticamente para generar el slug.
     */
    protected static function booted(): void
    {
        // Se ejecuta solo al CREAR un nuevo curso.
        static::creating(function (Course $course) {
            $course->slug = static::createUniqueSlug($course->title);
        });

        // Se ejecuta solo al ACTUALIZAR un curso existente.
        static::updating(function (Course $course) {
            // Solo actualiza el slug si el título ha cambiado.
            if ($course->isDirty('title')) {
                $course->slug = static::createUniqueSlug($course->title, $course->id);
            }
        });
    }

    /**
     * Genera un slug único para el curso.
     * Si el slug ya existe, le añade un sufijo numérico (ej: mi-curso-2).
     */
    private static function createUniqueSlug(string $title, int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->when($excludeId, fn($query) => $query->where('id', '!=', $excludeId))->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function levels()
    {
        return $this->hasMany(Level::class)->orderBy('order');
    }

    // Usuarios inscritos en el curso
    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    // Categorías del curso
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    // Subcategorías del curso
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    // Insignias del curso
    public function badges()
    {
        return $this->hasMany(Badge::class)->orderBy('order');
    }

    // Inscripciones del curso
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Inscripciones activas
     */
    public function activeEnrollments()
    {
        return $this->enrollments()->where('status', 'active');
    }
}