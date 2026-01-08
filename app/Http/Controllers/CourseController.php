<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Course;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user && $user->role === 'admin') {
            $courses = Course::with('levels')->get();
        } else if ($user) {
            $courses = $user->courses()->with('levels')->get();
        } else {
            $courses = collect();
        }
        return CourseResource::collection($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request)
    {
        // La validación se ejecuta automáticamente gracias al FormRequest.
        $course = Course::create($request->validated());

        return response()->json([
            'message' => 'Curso creado correctamente',
            'data' => new CourseResource($course)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course, Request $request)
    {
        // Laravel inyecta automáticamente el curso encontrado por su slug.
        // Cargamos diferentes relaciones según si es admin o usuario normal
        $user = $request->user();
        $isAdmin = $user && $user->role === 'admin';
        
        if ($isAdmin) {
            // Admins necesitan todo para editar
            $course->load('levels.images.subcategories', 'levels.lessons');
        } else {
            // Usuarios normales solo necesitan niveles y lecciones
            // IMPORTANTE: Cargar progress del usuario para evitar N+1 queries
            $course->load([
                'levels' => function ($query) {
                    $query->orderBy('order');
                },
                'levels.lessons' => function ($query) {
                    $query->select('id', 'title', 'content_type', 'level_id', 'dia')
                          ->orderBy('dia');
                }
            ]);
            
            // Pre-cargar el progreso del usuario en una sola query
            if ($user) {
                $this->preloadUserProgress($course, $user);
            }
        }

        return new CourseResource($course);
    }
    
    /**
     * Pre-carga el progreso de todas las lecciones del usuario en una sola query
     * para evitar N+1 queries después
     */
    private function preloadUserProgress(Course $course, $user)
    {
        // Obtener todos los lesson_ids del curso
        $lessonIds = $course->levels
            ->flatMap(fn($level) => $level->lessons)
            ->pluck('id')
            ->toArray();
        
        if (empty($lessonIds)) {
            return;
        }
        
        // Cargar TODO el progreso en una sola query
        $progressData = DB::table('lesson_user')
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->select('lesson_id', 'completed_at', 'accuracy', 'game_score', 'correct_answers', 'total_questions')
            ->get()
            ->keyBy('lesson_id');
        
        // Adjuntar al objeto course para que esté disponible en el frontend
        $course->setAttribute('_user_progress', $progressData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course)
    {
        // El curso ya está disponible gracias a la inyección de modelos.
        // La validación también es automática.
        $course->update($request->validated());

        return response()->json([
            'message' => 'Curso actualizado correctamente',
            'data' => new CourseResource($course)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        // El curso ya está disponible, solo lo eliminamos.
        $course->delete();

        return response()->json(['message' => 'Curso eliminado correctamente']);
    }
}
