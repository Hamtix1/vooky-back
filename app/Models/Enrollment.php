<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'enrolled_at',
        'expires_at',
        'custom_monthly_fee',
    ];

    protected $casts = [
        'enrolled_at' => 'date',
        'expires_at' => 'date',
        'custom_monthly_fee' => 'decimal:2',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el curso
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Relación con las matrículas (pagos)
     */
    public function tuitionFees()
    {
        return $this->hasMany(TuitionFee::class);
    }

    /**
     * Obtener matrículas pendientes
     */
    public function pendingFees()
    {
        return $this->tuitionFees()->where('status', 'pending');
    }

    /**
     * Obtener matrículas vencidas
     */
    public function overdueFees()
    {
        return $this->tuitionFees()->where('status', 'overdue');
    }

    /**
     * Verificar si la inscripción está activa
     */
    public function isActive()
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Si el curso no requiere pago, siempre está activo
        if (!$this->course->requires_payment) {
            return true;
        }

        // Verificar si hay pagos vencidos
        $overdueFees = $this->overdueFees()->count();
        
        if ($overdueFees > 0) {
            // Si tiene pagos vencidos, desactivar
            $this->update(['status' => 'inactive']);
            return false;
        }

        return true;
    }

    /**
     * Activar inscripción
     */
    public function activate()
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Desactivar inscripción
     */
    public function deactivate()
    {
        $this->update(['status' => 'inactive']);
    }

    /**
     * Obtener el precio mensual para esta inscripción
     * Retorna el precio personalizado si existe, sino el precio del curso
     */
    public function getMonthlyFee()
    {
        return $this->custom_monthly_fee ?? $this->course->monthly_fee;
    }
}
