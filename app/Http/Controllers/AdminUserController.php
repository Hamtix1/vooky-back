<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // Solo admins: listar todos los usuarios
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role')->orderBy('name')->get();
        return response()->json(['data' => $users]);
    }

    // Solo admins: crear un usuario
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,parent',
        ]);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
        ]);
        return response()->json(['message' => 'Usuario creado correctamente', 'data' => $user], 201);
    }

    // Solo admins: ver un usuario específico
    public function show($userId)
    {
        $user = User::select('id', 'name', 'email', 'role')->findOrFail($userId);
        return response()->json(['data' => $user]);
    }

    // Solo admins: actualizar un usuario
    public function update(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $userId,
            'password' => 'sometimes|nullable|string|min:6',
            'role' => 'sometimes|required|in:admin,parent',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (isset($validated['password']) && !empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }
        if (isset($validated['role'])) {
            $user->role = $validated['role'];
        }

        $user->save();

        return response()->json(['message' => 'Usuario actualizado correctamente', 'data' => $user]);
    }

    // Solo admins: eliminar un usuario
    public function destroy($userId)
    {
        $user = User::findOrFail($userId);
        $user->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente'], 200);
    }

    // Inscribir un usuario a un curso (solo admin)
    public function enrollUserToCourse($userId, $courseId)
    {
        $user = User::findOrFail($userId);
        $user->courses()->syncWithoutDetaching([$courseId]);
        return response()->json(['message' => 'Usuario inscrito correctamente al curso']);
    }

    // Desinscribir un usuario de un curso (solo admin)
    public function unenrollUserFromCourse($userId, $courseId)
    {
        $user = User::findOrFail($userId);
        $user->courses()->detach($courseId);
        return response()->json(['message' => 'Usuario desinscrito correctamente del curso']);
    }

    // Obtener los cursos de un usuario (solo admin)
    public function userCourses($userId)
    {
        $user = User::findOrFail($userId);
        $courses = $user->courses()->select('courses.id', 'courses.title', 'courses.slug', 'courses.description')->get();
        return response()->json(['data' => $courses]);
    }
}

