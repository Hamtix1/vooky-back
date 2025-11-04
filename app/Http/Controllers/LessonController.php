<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Audio;
use App\Models\Image;
use App\Models\Level;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Level $level): AnonymousResourceCollection
    {
        return LessonResource::collection($level->lessons);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLessonRequest $request, Level $level): JsonResponse
    {
        $data = $request->validated();

        // Calcular dia máximo disponible en las imágenes del nivel
        $maxDia = $level->images()->max('dia') ?? 0;

        if (isset($data['dia']) && $maxDia > 0 && $data['dia'] > $maxDia) {
            return response()->json(['message' => "El campo 'dia' no puede ser mayor a {$maxDia}."], 422);
        }

        $lesson = $level->lessons()->create($data);

        return response()->json([
            'message' => 'Lección creada correctamente',
            'data' => new LessonResource($lesson)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Level $level, Lesson $lesson): LessonResource
    {
        // Con el "scoped binding" de Laravel, la comprobación de que la lección
        // pertenece al nivel se hace automáticamente. Si no, se lanzará un 404.
        return new LessonResource($lesson);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLessonRequest $request, Level $level, Lesson $lesson): JsonResponse
    {
        $data = $request->validated();

        // Si se está actualizando el día, validar contra el máximo disponible
        if (isset($data['dia'])) {
            $maxDia = $level->images()->max('dia') ?? 0;
            if ($maxDia > 0 && $data['dia'] > $maxDia) {
                return response()->json(['message' => "El campo 'dia' no puede ser mayor a {$maxDia}."], 422);
            }
        }

        $lesson->update($data);

        return response()->json([
            'message' => 'Lección actualizada correctamente',
            'data' => new LessonResource($lesson)
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Level $level, Lesson $lesson): JsonResponse
    {
        $lesson->delete();

        return response()->json(['message' => 'Lección eliminada correctamente'], 200);
    }

    /**
     * Get all the available images and audios for a lesson's level and previous levels.
     */
    public function getQuestionPool(Lesson $lesson): JsonResponse
    {
        $currentLevel = $lesson->level;
        $course = $currentLevel->course;

        // Obtener todos los niveles del curso hasta el actual (inclusive)
        $levels = $course->levels()->where('order', '<=', $currentLevel->order)->with('lessons.questions')->get();

        // Extraer todos los IDs de las imágenes y audios de las preguntas de esos niveles
        $imageIds = $levels->pluck('lessons.*.questions.*.optionA_id')->flatten()->merge(
            $levels->pluck('lessons.*.questions.*.optionB_id')->flatten()
        )->unique()->filter();

        $audioIds = $levels->pluck('lessons.*.questions.*.audio_id')->flatten()->unique()->filter();

        $images = Image::whereIn('id', $imageIds)->get();
        $audios = Audio::whereIn('id', $audioIds)->get();

        return response()->json(['images' => $images, 'audios' => $audios]);
    }
}
