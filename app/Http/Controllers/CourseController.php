<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use Illuminate\Http\Request;
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
    public function show(Course $course)
    {
        // Laravel inyecta automáticamente el curso encontrado por su slug.
        // Solo cargamos las relaciones que necesitamos.
        $course->load('levels.images.subcategories', 'levels.lessons');

        return new CourseResource($course);
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
