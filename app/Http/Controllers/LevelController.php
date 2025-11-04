<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLevelRequest;
use App\Http\Requests\UpdateLevelRequest;
use App\Http\Resources\LevelResource;
use Illuminate\Http\Request;
use App\Models\Level;
use App\Models\Course;

class LevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Course $course)
    {
        // Laravel carga automáticamente el curso y sus niveles gracias a la relación.
        return LevelResource::collection($course->levels);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLevelRequest $request, Course $course)
    {
        // El curso ya está cargado gracias al Route Model Binding.
        // Usamos la relación para crear el nivel, asegurando la consistencia.
        $level = $course->levels()->create($request->validated());

        return response()->json([
            'message' => 'Nivel creado correctamente',
            'data' => new LevelResource($level)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course, Level $level)
    {
        // Con el "scoped binding", Laravel ya verificó que este nivel pertenece al curso.
        // Cargamos las lecciones asociadas.
        return new LevelResource($level->load('lessons'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLevelRequest $request, Course $course, Level $level)
    {
        // Laravel ya se encargó de encontrar el curso y el nivel, y de verificar la pertenencia.
        $level->update($request->validated());

        return response()->json([
            'message' => 'Nivel actualizado correctamente',
            'data' => new LevelResource($level)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course, Level $level)
    {
        \Log::info('Intentando eliminar nivel', [
            'course_id' => $course->id,
            'level_id' => $level->id,
            'level_course_id' => $level->course_id,
            'course_match' => $level->course_id == $course->id,
            'route_course_id' => $course->id,
            'route_level_id' => $level->id,
        ]);
        $level->delete();
        \Log::info('Nivel eliminado exitosamente', [
            'level_id' => $level->id,
            'course_id' => $course->id,
        ]);
        return response()->json(['message' => 'Nivel eliminado correctamente']);
    }
}
