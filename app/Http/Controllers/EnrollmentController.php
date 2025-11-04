<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EnrollmentController extends Controller
{
    // [ADMIN] Inscribir un usuario en un curso
    public function enrollUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'custom_monthly_fee' => 'nullable|numeric|min:0',
        ]);

        $userId = $request->user_id;
        $courseId = $request->course_id;
        $customFee = $request->custom_monthly_fee;

        // Verificar si ya existe una inscripción
        $existingEnrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if ($existingEnrollment) {
            // Si ya existe, activarla si está inactiva
            if ($existingEnrollment->status !== 'active') {
                $existingEnrollment->activate();
                
                // Actualizar precio personalizado si se proporcionó
                if ($customFee !== null) {
                    $existingEnrollment->update(['custom_monthly_fee' => $customFee]);
                }
                
                return response()->json([
                    'message' => 'Inscripción reactivada exitosamente',
                    'enrollment' => $existingEnrollment->load('user', 'course')
                ]);
            }
            
            return response()->json([
                'message' => 'El usuario ya está inscrito en este curso',
                'enrollment' => $existingEnrollment->load('user', 'course')
            ], 409);
        }

        $course = \App\Models\Course::findOrFail($courseId);

        // Crear nueva inscripción
        $enrollment = Enrollment::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => $course->requires_payment ? 'pending' : 'active',
            'enrolled_at' => now(),
            'custom_monthly_fee' => $customFee,
        ]);

        // Mantener compatibilidad con la tabla pivot course_user
        $user = \App\Models\User::find($userId);
        $user->courses()->syncWithoutDetaching([$courseId]);

        // Si el curso requiere pago, generar la primera matrícula
        if ($course->requires_payment) {
            // La primera matrícula vence al día siguiente a la 1:00 AM
            $firstDueDate = now()->addDay()->setTime(1, 0, 0);

            // Usar precio personalizado si existe, sino el del curso
            $feeAmount = $customFee ?? $course->monthly_fee;

            \Log::info('[EnrollmentController] Intentando crear TuitionFee', [
                'user_id' => $userId,
                'course_id' => $courseId,
                'enrollment_id' => $enrollment->id,
                'feeAmount' => $feeAmount,
                'due_date' => $firstDueDate,
            ]);

            $tuitionFee = \App\Models\TuitionFee::create([
                'enrollment_id' => $enrollment->id,
                'amount' => $feeAmount,
                'due_date' => $firstDueDate,
                'status' => 'pending',
            ]);

            \Log::info('[EnrollmentController] TuitionFee creado', [
                'tuition_fee_id' => $tuitionFee ? $tuitionFee->id : null,
                'user_id' => $userId,
                'course_id' => $courseId,
                'enrollment_id' => $enrollment->id,
                'feeAmount' => $feeAmount,
                'due_date' => $firstDueDate,
            ]);

            $message = 'Usuario inscrito exitosamente. Primera matrícula generada con vencimiento mañana (' . $firstDueDate->format('d/m/Y H:i') . '). Monto: $' . number_format($feeAmount, 2) . '. El curso se activará al confirmar el pago.';
        } else {
            $message = 'Usuario inscrito exitosamente en curso gratuito.';
        }

        return response()->json([
            'message' => $message,
            'enrollment' => $enrollment->load('user', 'course')
        ], 201);
    }

    // [ADMIN] Desinscribir un usuario de un curso
    public function unenrollUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
        ]);

        $userId = $request->user_id;
        $courseId = $request->course_id;

        // Desactivar la inscripción
        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'No existe una inscripción para este usuario y curso'
            ], 404);
        }

        $enrollment->delete();

        // Mantener compatibilidad con la tabla pivot course_user
        $user = \App\Models\User::find($userId);
        $user->courses()->detach($courseId);

        return response()->json(['message' => 'Usuario desinscrito exitosamente']);
    }

    // [ADMIN] Obtener todas las inscripciones
    public function index(Request $request)
    {
        $query = Enrollment::with(['user', 'course']);

        // Filtros opcionales
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $enrollments = $query->orderBy('enrolled_at', 'desc')->get();

        return response()->json($enrollments);
    }

    // [USER] Obtener las inscripciones del usuario autenticado
    public function myEnrollments()
    {
        $user = Auth::user();
        $enrollments = Enrollment::where('user_id', $user->id)
            ->with('course')
            ->orderBy('enrolled_at', 'desc')
            ->get();

        return response()->json($enrollments);
    }

    // Marcar una lección como completada
    public function completeLesson(Lesson $lesson)
    {
        $user = Auth::user();
        $user->completedLessons()->syncWithoutDetaching([
            $lesson->id => ['completed_at' => now()]
        ]);
        return response()->json(['message' => 'Lección marcada como completada']);
    }

    // Quitar el estado de completada de una lección
    public function uncompleteLesson(Lesson $lesson)
    {
        $user = Auth::user();
        $user->completedLessons()->detach($lesson->id);
        return response()->json(['message' => 'Lección marcada como no completada']);
    }

    // Obtener el progreso del usuario en un curso
    public function courseProgress(Course $course)
    {
        $user = Auth::user();
        $totalLessons = $course->levels()->withCount('lessons')->get()->sum('lessons_count');
        $completedLessons = $user->completedLessons()->whereHas('level', function($q) use ($course) {
            $q->where('course_id', $course->id);
        })->count();
        return response()->json([
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'progress_percent' => $totalLessons > 0 ? round($completedLessons / $totalLessons * 100, 2) : 0
        ]);
    }

    // [ADMIN] Actualizar el estado de una inscripción
    public function updateStatus(Request $request, Enrollment $enrollment)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,pending',
        ]);

        $oldStatus = $enrollment->status;
        $newStatus = $request->status;

        // Actualizar el estado
        $enrollment->update(['status' => $newStatus]);

        // Si se está activando una inscripción, verificar si necesita generar matrículas
        if ($newStatus === 'active' && $oldStatus !== 'active') {
            $course = $enrollment->course;

            // Si el curso requiere pago y no hay matrículas activas, generar una
            if ($course->requires_payment) {
                $activeFees = $enrollment->tuitionFees()->where('status', 'paid')->count();

                if ($activeFees === 0) {
                    // Generar primera matrícula si no hay ninguna pagada
                    $firstDueDate = now()->addDay()->setTime(1, 0, 0);
                    $feeAmount = $enrollment->custom_monthly_fee ?? $course->monthly_fee;

                    \App\Models\TuitionFee::create([
                        'enrollment_id' => $enrollment->id,
                        'amount' => $feeAmount,
                        'due_date' => $firstDueDate,
                        'status' => 'pending',
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Estado de la inscripción actualizado exitosamente',
            'enrollment' => $enrollment->load('user', 'course')
        ]);
    }
}
