<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Obtener estadísticas públicas de la plataforma
     */
    public function public()
    {
        // Contar usuarios activos (excluyendo admins)
        $activeUsers = User::where('role', '!=', 'admin')->count();
        
        // Contar cursos disponibles
        $availableCourses = Course::count();
        
        // Calcular porcentaje de satisfacción basado en progreso
        // (usuarios que han completado al menos una lección vs total)
        $usersWithProgress = DB::table('lesson_user')
            ->distinct('user_id')
            ->count('user_id');
        
        $satisfactionRate = $activeUsers > 0 
            ? round(($usersWithProgress / $activeUsers) * 100) 
            : 0;
        
        // Total de lecciones completadas
        $completedLessons = DB::table('lesson_user')
            ->whereNotNull('completed_at')
            ->count();

        return response()->json([
            'active_users' => $activeUsers,
            'available_courses' => $availableCourses,
            'satisfaction_rate' => $satisfactionRate,
            'completed_lessons' => $completedLessons,
        ]);
    }

    /**
     * Obtener estadísticas detalladas del dashboard (solo admin)
     */
    public function dashboard()
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'active' => User::where('role', '!=', 'admin')->count(),
                'admins' => User::where('role', 'admin')->count(),
            ],
            'courses' => [
                'total' => Course::count(),
                'with_enrollments' => DB::table('course_user')->distinct('course_id')->count('course_id'),
            ],
            'lessons' => [
                'total' => Lesson::count(),
                'completed' => DB::table('lesson_user')->whereNotNull('completed_at')->count(),
                'in_progress' => DB::table('lesson_user')->whereNull('completed_at')->count(),
            ],
            'engagement' => [
                'total_enrollments' => DB::table('course_user')->count(),
                'completion_rate' => $this->calculateCompletionRate(),
                'average_accuracy' => $this->calculateAverageAccuracy(),
            ],
            'recent_activity' => [
                'last_7_days' => DB::table('lesson_user')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'today' => DB::table('lesson_user')
                    ->whereDate('created_at', today())
                    ->count(),
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Calcular tasa de completación global
     */
    private function calculateCompletionRate()
    {
        $totalAttempts = DB::table('lesson_user')->count();
        
        if ($totalAttempts === 0) {
            return 0;
        }

        $completed = DB::table('lesson_user')
            ->whereNotNull('completed_at')
            ->count();

        return round(($completed / $totalAttempts) * 100);
    }

    /**
     * Calcular precisión promedio
     */
    private function calculateAverageAccuracy()
    {
        $avgAccuracy = DB::table('lesson_user')
            ->whereNotNull('accuracy')
            ->avg('accuracy');

        return $avgAccuracy ? round($avgAccuracy, 1) : 0;
    }
}
