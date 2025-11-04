<?php

namespace App\Http\Controllers;

use App\Models\Subcategory;
use App\Models\Course;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    /**
     * Obtener todas las subcategorías de un curso
     */
    public function index($courseSlug)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $subcategories = Subcategory::where('course_id', $course->id)
            ->orderBy('name')
            ->get();

        return response()->json($subcategories);
    }

    /**
     * Crear una nueva subcategoría
     */
    public function store(Request $request, $courseSlug)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $subcategory = Subcategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'course_id' => $course->id,
        ]);

        return response()->json([
            'message' => 'Subcategoría creada exitosamente',
            'subcategory' => $subcategory
        ], 201);
    }

    /**
     * Actualizar una subcategoría
     */
    public function update(Request $request, $courseSlug, $id)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $subcategory = Subcategory::where('course_id', $course->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $subcategory->update($validated);

        return response()->json([
            'message' => 'Subcategoría actualizada exitosamente',
            'subcategory' => $subcategory
        ]);
    }

    /**
     * Eliminar una subcategoría
     */
    public function destroy($courseSlug, $id)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $subcategory = Subcategory::where('course_id', $course->id)
            ->findOrFail($id);

        $subcategory->delete();

        return response()->json([
            'message' => 'Subcategoría eliminada exitosamente'
        ]);
    }
}
