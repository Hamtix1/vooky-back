<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Course;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    /**
     * Obtener todas las insignias (para admin)
     */
    public function all()
    {
        $badges = Badge::with('course:id,title')
            ->orderBy('course_id')
            ->orderBy('lessons_required')
            ->get();

        return response()->json($badges);
    }

    /**
     * Obtener todas las insignias de un curso específico
     */
    public function index($courseId)
    {
        $badges = Badge::where('course_id', $courseId)
            ->orderBy('order')
            ->get();

        return response()->json($badges);
    }

    /**
     * Crear una nueva insignia
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'lessons_required' => 'required|integer|min:0',
        ]);

        // Subir imagen
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('badges', 'public');
            $validated['image'] = $imagePath;
        }

        $badge = Badge::create($validated);
        $badge->load('course:id,title');

        return response()->json([
            'message' => 'Insignia creada exitosamente',
            'badge' => $badge
        ], 201);
    }

    /**
     * Mostrar una insignia específica
     */
    public function show($id)
    {
        $badge = Badge::with('course')->findOrFail($id);
        return response()->json($badge);
    }

    /**
     * Actualizar una insignia
     */
    public function update(Request $request, $id)
    {
        $badge = Badge::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'lessons_required' => 'sometimes|integer|min:0',
        ]);

        // Subir nueva imagen si se proporcionó
        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe
            if ($badge->image && \Storage::disk('public')->exists($badge->image)) {
                \Storage::disk('public')->delete($badge->image);
            }
            
            $imagePath = $request->file('image')->store('badges', 'public');
            $validated['image'] = $imagePath;
        }

        $badge->update($validated);
        $badge->load('course:id,title');

        return response()->json([
            'message' => 'Insignia actualizada exitosamente',
            'badge' => $badge
        ]);
    }

    /**
     * Eliminar una insignia
     */
    public function destroy($id)
    {
        $badge = Badge::findOrFail($id);
        
        // Eliminar imagen si existe
        if ($badge->image && \Storage::disk('public')->exists($badge->image)) {
            \Storage::disk('public')->delete($badge->image);
        }
        
        $badge->delete();

        return response()->json([
            'message' => 'Insignia eliminada exitosamente'
        ]);
    }

    /**
     * Obtener insignias del usuario autenticado
     */
    public function userBadges(Request $request)
    {
        $user = $request->user();
        
        // Obtener todas las insignias que el usuario ha ganado
        $earnedBadges = $user->badges()
            ->with('course:id,title')
            ->orderBy('earned_at', 'desc')
            ->get()
            ->map(function ($badge) {
                return [
                    'id' => $badge->pivot->id,
                    'badge_id' => $badge->id,
                    'user_id' => $badge->pivot->user_id,
                    'earned_at' => $badge->pivot->earned_at,
                    'badge' => [
                        'id' => $badge->id,
                        'course_id' => $badge->course_id,
                        'name' => $badge->name,
                        'description' => $badge->description,
                        'image' => $badge->image,
                        'lessons_required' => $badge->lessons_required,
                        'course' => $badge->course,
                    ],
                ];
            });

        return response()->json($earnedBadges);
    }

    /**
     * Obtener insignias de un usuario en un curso específico
     */
    public function userCourseBadges($userId, $courseId)
    {
        $course = Course::findOrFail($courseId);
        
        // Obtener todas las insignias del curso con información de si el usuario las tiene
        $badges = Badge::where('course_id', $courseId)
            ->orderBy('order')
            ->get()
            ->map(function ($badge) use ($userId) {
                $userBadge = $badge->users()->where('user_id', $userId)->first();
                
                return [
                    'id' => $badge->id,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon' => $badge->icon,
                    'color' => $badge->color,
                    'lessons_required' => $badge->lessons_required,
                    'order' => $badge->order,
                    'earned' => $userBadge !== null,
                    'earned_at' => $userBadge ? $userBadge->pivot->earned_at : null,
                ];
            });

        return response()->json($badges);
    }

    /**
     * Verificar y otorgar insignias a un usuario basado en lecciones completadas
     * Este método se llamará automáticamente después de completar una lección
     */
    public function checkAndAwardBadges($userId, $courseId)
    {
        // Contar lecciones completadas del usuario en este curso
        $completedLessonsCount = \DB::table('lesson_user')
            ->join('lessons', 'lessons.id', '=', 'lesson_user.lesson_id')
            ->join('levels', 'levels.id', '=', 'lessons.level_id')
            ->where('lesson_user.user_id', $userId)
            ->where('levels.course_id', $courseId)
            ->count();

        // Obtener insignias del curso que el usuario aún no tiene
        $availableBadges = Badge::where('course_id', $courseId)
            ->where('lessons_required', '<=', $completedLessonsCount)
            ->whereDoesntHave('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();

        $newBadges = [];

        // Otorgar cada insignia disponible
        foreach ($availableBadges as $badge) {
            $badge->users()->attach($userId, ['earned_at' => now()]);
            $newBadges[] = $badge;
        }

        return response()->json([
            'message' => count($newBadges) > 0 ? 'Nuevas insignias obtenidas' : 'Sin nuevas insignias',
            'new_badges' => $newBadges,
            'completed_lessons' => $completedLessonsCount
        ]);
    }
}
