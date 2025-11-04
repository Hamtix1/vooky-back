<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    // Cursos en los que el usuario está inscrito
    public function courses()
    {
        return $this->belongsToMany(Course::class)->withTimestamps();
    }

    // Lecciones completadas por el usuario
    public function completedLessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_user')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    // Insignias obtenidas por el usuario
    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'badge_user')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    // Inscripciones del usuario
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

    /**
     * Obtener todas las matrículas del usuario
     */
    public function tuitionFees()
    {
        return $this->hasManyThrough(TuitionFee::class, Enrollment::class);
    }

    /**
     * Matrículas pendientes
     */
    public function pendingFees()
    {
        return $this->tuitionFees()->where('status', 'pending');
    }

    /**
     * Matrículas vencidas
     */
    public function overdueFees()
    {
        return $this->tuitionFees()->where('status', 'overdue');
    }
}
