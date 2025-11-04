<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'course_id'];

    /**
     * Relación: Una subcategoría pertenece a un curso
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Relación: Una subcategoría puede tener muchas imágenes (many-to-many)
     */
    public function images()
    {
        return $this->belongsToMany(Image::class, 'image_subcategory');
    }
}
