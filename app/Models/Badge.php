<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'description',
        'image',
        'lessons_required',
    ];

    protected $casts = [
        'lessons_required' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Relación: Una insignia pertenece a un curso
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Relación: Usuarios que han obtenido esta insignia
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'badge_user')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    /**
     * Verifica si un usuario ha obtenido esta insignia
     */
    public function isEarnedByUser($userId)
    {
        return $this->users()->where('user_id', $userId)->exists();
    }
}
