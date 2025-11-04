<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UserProfileController extends Controller
{
    /**
     * Obtener perfil del usuario autenticado con puntaje global
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Calcular puntaje global (suma de todos los game_scores)
        $globalScore = DB::table('lesson_user')
            ->where('user_id', $user->id)
            ->sum('game_score');
        
        // Contar lecciones completadas
        $completedLessons = DB::table('lesson_user')
            ->where('user_id', $user->id)
            ->count();
        
        // Obtener accuracy promedio
        $avgAccuracy = DB::table('lesson_user')
            ->where('user_id', $user->id)
            ->avg('accuracy');
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ],
            'stats' => [
                'global_score' => (int) $globalScore,
                'completed_lessons' => $completedLessons,
                'average_accuracy' => $avgAccuracy ? round($avgAccuracy, 2) : 0,
            ]
        ]);
    }

    /**
     * Actualizar perfil del usuario (nombre, email, contraseña)
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ]);

        // Si se está cambiando la contraseña, verificar la contraseña actual
        if (isset($validated['password'])) {
            if (!isset($validated['current_password'])) {
                return response()->json([
                    'message' => 'Se requiere la contraseña actual para cambiarla.',
                    'errors' => ['current_password' => ['La contraseña actual es requerida']]
                ], 422);
            }
            
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'La contraseña actual es incorrecta.',
                    'errors' => ['current_password' => ['La contraseña actual es incorrecta']]
                ], 422);
            }
            
            $user->password = Hash::make($validated['password']);
        }

        // Actualizar nombre si se proporcionó
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        // Actualizar email si se proporcionó
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * Obtener ranking global de los mejores 10 usuarios
     */
    public function ranking()
    {
        $topUsers = DB::table('users')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COALESCE(SUM(lesson_user.game_score), 0) as total_score'),
                DB::raw('COUNT(lesson_user.lesson_id) as completed_lessons'),
                DB::raw('COALESCE(AVG(lesson_user.accuracy), 0) as average_accuracy')
            )
            ->leftJoin('lesson_user', 'users.id', '=', 'lesson_user.user_id')
            ->where('users.role', '!=', 'admin') // Excluir administradores del ranking
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_score')
            ->limit(10)
            ->get();

        // Agregar posición al ranking
        $ranking = $topUsers->map(function ($user, $index) {
            return [
                'position' => $index + 1,
                'id' => $user->id,
                'name' => $user->name,
                'total_score' => (int) $user->total_score,
                'completed_lessons' => $user->completed_lessons,
                'average_accuracy' => round($user->average_accuracy, 2),
            ];
        });

        return response()->json([
            'ranking' => $ranking
        ]);
    }

    /**
     * Obtener posición del usuario autenticado en el ranking global
     */
    public function myRanking(Request $request)
    {
        $user = $request->user();
        
        // Calcular puntaje del usuario
        $userScore = DB::table('lesson_user')
            ->where('user_id', $user->id)
            ->sum('game_score');
        
        // Contar cuántos usuarios tienen más puntos
        $position = DB::table('users')
            ->select('users.id')
            ->leftJoin('lesson_user', 'users.id', '=', 'lesson_user.user_id')
            ->where('users.role', '!=', 'admin')
            ->where('users.id', '!=', $user->id)
            ->groupBy('users.id')
            ->havingRaw('COALESCE(SUM(lesson_user.game_score), 0) > ?', [$userScore])
            ->count();
        
        return response()->json([
            'position' => $position + 1,
            'total_score' => (int) $userScore,
        ]);
    }
}
