<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\Enrollment;
use Symfony\Component\HttpFoundation\Response;

class CheckEnrollment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Si el usuario es admin, permitir acceso sin restricciones
        if ($user && $user->role === 'admin') {
            return $next($request);
        }

        // Obtener el ID de la lección desde la ruta
        $lessonId = $request->route('lesson');
        
        if (!$lessonId) {
            return response()->json([
                'message' => 'No se pudo identificar la lección'
            ], 400);
        }

        // Buscar la lección y su curso
        $lesson = Lesson::with('level.course')->find($lessonId);
        
        if (!$lesson) {
            return response()->json([
                'message' => 'Lección no encontrada'
            ], 404);
        }

        $course = $lesson->level->course;

        // Verificar si el usuario tiene una inscripción activa en el curso
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'No estás inscrito en este curso. Contacta al administrador para inscribirte.',
                'error' => 'NOT_ENROLLED'
            ], 403);
        }

        // Verificar si la inscripción está activa
        if ($enrollment->status !== 'active') {
            $reason = 'Tu acceso al curso está inactivo.';
            
            if ($enrollment->status === 'pending') {
                $reason = 'Tu inscripción está pendiente de activación. Por favor, realiza el pago de la matrícula.';
            } elseif ($enrollment->status === 'inactive') {
                // Verificar si hay pagos vencidos
                $overdueFees = $enrollment->overdueFees()->count();
                if ($overdueFees > 0) {
                    $reason = 'Tu acceso al curso ha sido suspendido por pagos vencidos. Por favor, contacta al administrador.';
                }
            }

            return response()->json([
                'message' => $reason,
                'error' => 'ENROLLMENT_NOT_ACTIVE',
                'status' => $enrollment->status
            ], 403);
        }

        // Todo está bien, permitir el acceso
        return $next($request);
    }
}
