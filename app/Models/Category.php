<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model    
{
    use HasFactory;
    
    protected $fillable = ['course_id', 'name'];

    /**
     * Una categoría pertenece a un curso
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Una categoría puede tener muchas imágenes
     */
    public function images()
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Una categoría puede tener muchas subcategorías
     */
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }
}
