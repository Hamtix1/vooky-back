<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;
    protected $fillable = ['level_id', 'title', 'content_type', 'dia', 'order'];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
    
    // Usuarios que han completado esta lección
    public function completedByUsers()
    {
        return $this->belongsToMany(User::class, 'lesson_user')
            ->withPivot('completed_at', 'score', 'correct_answers', 'total_questions')
            ->withTimestamps();
    }
}
