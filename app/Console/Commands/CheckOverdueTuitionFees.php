<?php

namespace App\Console\Commands;

use App\Models\TuitionFee;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckOverdueTuitionFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tuition:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar matrículas vencidas y desactivar cursos automáticamente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verificando matrículas vencidas...');

        // Obtener todas las matrículas pendientes cuya fecha de vencimiento ya pasó
        $overdueFees = TuitionFee::with(['enrollment.course', 'enrollment.user'])
            ->where('status', 'pending')
            ->where('due_date', '<', Carbon::today())
            ->get();

        $deactivatedCount = 0;

        foreach ($overdueFees as $fee) {
            // Marcar como vencida
            $fee->markAsOverdue();

            $userName = $fee->enrollment->user->name;
            $courseName = $fee->enrollment->course->title;
            $dueDate = $fee->due_date->format('d/m/Y');

            $this->warn("Matrícula vencida: {$userName} - {$courseName} (Vencimiento: {$dueDate})");
            $this->warn("Curso desactivado automáticamente");

            $deactivatedCount++;
        }

        if ($deactivatedCount > 0) {
            $this->info("Se procesaron {$deactivatedCount} matrículas vencidas.");
        } else {
            $this->info("No hay matrículas vencidas.");
        }

        return 0;
    }
}
