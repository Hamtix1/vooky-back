<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Course;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories for a specific course.
     */
    public function index($courseSlug)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        return response()->json(['data' => $course->categories]);
    }

    /**
     * Store a newly created category for a specific course.
     */
    public function store(Request $request, $courseSlug)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Verificar que no exista una categoría con el mismo nombre en este curso
        $exists = Category::where('course_id', $course->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe una categoría con ese nombre en este curso'
            ], 422);
        }

        $category = Category::create([
            'course_id' => $course->id,
            'name' => $validated['name'],
        ]);

        return response()->json(['data' => $category], 201);
    }

    /**
     * Display the specified category.
     */
    public function show($courseSlug, $id)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $category = Category::where('course_id', $course->id)->findOrFail($id);
        return response()->json(['data' => $category]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, $courseSlug, $id)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $category = Category::where('course_id', $course->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Verificar que no exista otra categoría con el mismo nombre en este curso
        $exists = Category::where('course_id', $course->id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe una categoría con ese nombre en este curso'
            ], 422);
        }

        $category->update($validated);
        return response()->json(['data' => $category]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy($courseSlug, $id)
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $category = Category::where('course_id', $course->id)->findOrFail($id);
        $category->delete();
        return response()->json(null, 204);
    }
}
