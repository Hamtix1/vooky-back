<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TuitionFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    /**
     * Relación con la inscripción
     */
    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Marcar como pagado
     */
    public function markAsPaid($paymentMethod = null, $notes = null)
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'notes' => $notes,
        ]);

        // Cargar la relación si no está cargada
        if (!$this->relationLoaded('enrollment')) {
            $this->load('enrollment');
        }

        // Activar la inscripción inmediatamente cuando se paga
        if ($this->enrollment && in_array($this->enrollment->status, ['inactive', 'pending'])) {
            $this->enrollment->activate();
            $this->enrollment->refresh();
        }

        // YA NO generamos la siguiente matrícula aquí
        // Se generará automáticamente 7 días antes del vencimiento mediante el comando programado
    }

    /**
     * Verificar si está vencido
     */
    public function isOverdue()
    {
        return $this->status === 'pending' && 
               Carbon::parse($this->due_date)->isPast();
    }

    /**
     * Marcar como vencido
     */
    public function markAsOverdue()
    {
        if ($this->isOverdue()) {
            $this->update(['status' => 'overdue']);
            
            // Cargar la relación si no está cargada
            if (!$this->relationLoaded('enrollment')) {
                $this->load('enrollment');
            }
            
            // Desactivar la inscripción
            if ($this->enrollment) {
                $this->enrollment->deactivate();
            }
        }
    }

    /**
     * Scope para pagos pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para pagos vencidos
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope para pagos pagados
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
