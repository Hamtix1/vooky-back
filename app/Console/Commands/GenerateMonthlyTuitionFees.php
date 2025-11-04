<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\TuitionFee;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyTuitionFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tuition:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar matrículas mensuales 7 días antes del vencimiento de cada usuario';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generando matrículas mensuales (7 días antes del vencimiento)...');

        $today = Carbon::today();
        
        // Obtener todas las inscripciones activas de cursos que requieren pago
        $enrollments = Enrollment::with('course', 'user', 'tuitionFees')
            ->where('status', 'active')
            ->whereHas('course', function ($query) {
                $query->where('requires_payment', true);
            })
            ->get();

        $generatedCount = 0;

        foreach ($enrollments as $enrollment) {
            // Obtener la última matrícula de esta inscripción
            $lastFee = $enrollment->tuitionFees()
                ->orderBy('due_date', 'desc')
                ->first();

            if (!$lastFee) {
                // No debería pasar, pero por si acaso
                $this->warn("Inscripción {$enrollment->id} no tiene matrículas. Saltando...");
                continue;
            }

            // Calcular cuándo vence la siguiente matrícula (1 mes después de la última)
            $nextDueDate = Carbon::parse($lastFee->due_date)->addMonth();
            
            // Calcular cuándo debemos generar la factura (7 días antes del vencimiento)
            $generateDate = $nextDueDate->copy()->subDays(7);

            // Si hoy es el día de generación o ya pasó (y no existe la factura)
            if ($today->greaterThanOrEqualTo($generateDate)) {
                // Verificar si ya existe una matrícula para esa fecha de vencimiento
                $existingFee = $enrollment->tuitionFees()
                    ->whereDate('due_date', $nextDueDate)
                    ->first();

                if (!$existingFee) {
                    // Usar precio personalizado si existe, sino el del curso
                    $feeAmount = $enrollment->getMonthlyFee();
                    
                    TuitionFee::create([
                        'enrollment_id' => $enrollment->id,
                        'amount' => $feeAmount,
                        'due_date' => $nextDueDate,
                        'status' => 'pending',
                    ]);

                    $generatedCount++;

                    $this->info("Matrícula generada para {$enrollment->user->name} - {$enrollment->course->title} (Monto: \${$feeAmount}, Vence: {$nextDueDate->format('d/m/Y')})");
                }
            }
        }

        $this->info("Se generaron {$generatedCount} matrículas.");

        return 0;
    }
}
