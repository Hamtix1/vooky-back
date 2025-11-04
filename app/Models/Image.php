<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = ['url', 'audio_url', 'description', 'level_id', 'dia', 'category_id', 'type'];
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relación: Una imagen puede tener muchas subcategorías
     */
    public function subcategories()
    {
        return $this->belongsToMany(Subcategory::class, 'image_subcategory');
    }
    
    protected $appends = ['file_url', 'audio_file_url'];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->url ? Storage::url($this->url) : null,
        );
    }
    protected function audioFileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->audio_url ? Storage::url($this->audio_url) : null,
        );
    }
}
