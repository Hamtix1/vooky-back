<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\AudioController;
// use App\Http\Controllers\QuestionController; // TODO: Crear controlador
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LessonGameController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TuitionFeeController;
use App\Http\Controllers\SubcategoryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- RUTAS PÚBLICAS ---
// Cualquiera puede intentar iniciar sesión.
Route::post('/login', [AuthController::class, 'login']);

// Estadísticas públicas de la plataforma
Route::get('/stats/public', [StatsController::class, 'public']);


// --- RUTAS PROTEGIDAS POR AUTENTICACIÓN ---
// Solo los usuarios que han iniciado sesión (admins y parents) pueden acceder a estas rutas.
Route::middleware('auth:sanctum')->group(function () {
    
    // Rutas de autenticación para usuarios logueados
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Rutas de perfil de usuario
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::put('/profile', [UserProfileController::class, 'update']);
    Route::get('/profile/ranking', [UserProfileController::class, 'myRanking']);
    Route::get('/profile/badges', [BadgeController::class, 'userBadges']);
    
    // Ranking global (disponible para todos los usuarios autenticados)
    Route::get('/ranking', [UserProfileController::class, 'ranking']);

    // --- RUTAS SOLO PARA ADMINISTRADORES ---
    // Solo los usuarios con rol 'admin' pueden acceder a este grupo.
    Route::middleware('admin')->group(function () {
    
    // Gestión de usuarios (CRUD completo)
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
    
    // Gestión de inscripciones de usuarios a cursos
    Route::get('/admin/users/{user}/courses', [AdminUserController::class, 'userCourses']);
    Route::post('/admin/users/{user}/enroll/{course}', [AdminUserController::class, 'enrollUserToCourse']);
    Route::delete('/admin/users/{user}/unenroll/{course}', [AdminUserController::class, 'unenrollUserFromCourse']);

    // Toda la gestión de contenido (crear, editar, borrar) es solo para admins.
    Route::apiResource('courses', CourseController::class);
    Route::apiResource('courses.levels', LevelController::class);
    Route::apiResource('courses.categories', CategoryController::class);
    Route::apiResource('levels.lessons', LessonController::class);
    Route::apiResource('images', ImageController::class);
    Route::apiResource('audios', AudioController::class);
    
    // Gestión de subcategorías (CRUD completo)
    Route::get('courses/{course}/subcategories', [SubcategoryController::class, 'index']);
    Route::post('courses/{course}/subcategories', [SubcategoryController::class, 'store']);
    Route::put('courses/{course}/subcategories/{subcategory}', [SubcategoryController::class, 'update']);
    Route::delete('courses/{course}/subcategories/{subcategory}', [SubcategoryController::class, 'destroy']);
    
    // Route::apiResource('questions', QuestionController::class); // TODO: Crear controlador
    
    // Gestión de insignias (CRUD completo para admin)
    Route::get('badges', [BadgeController::class, 'all']);
    Route::get('courses/{course}/badges', [BadgeController::class, 'index']);
    Route::post('badges', [BadgeController::class, 'store']);
    Route::get('badges/{badge}', [BadgeController::class, 'show']);
    Route::put('badges/{badge}', [BadgeController::class, 'update']);
    Route::delete('badges/{badge}', [BadgeController::class, 'destroy']);
    
    // Estadísticas del dashboard (solo admin)
    Route::get('stats/dashboard', [StatsController::class, 'dashboard']);
    
        // Gestión de matrículas (admin)
    Route::get('admin/tuition-fees', [TuitionFeeController::class, 'index']);
    Route::post('admin/tuition-fees', [TuitionFeeController::class, 'store']);
    Route::put('admin/tuition-fees/{tuitionFee}', [TuitionFeeController::class, 'update']);
    Route::delete('admin/tuition-fees/{tuitionFee}', [TuitionFeeController::class, 'destroy']);
    Route::post('admin/tuition-fees/{tuitionFee}/mark-paid', [TuitionFeeController::class, 'markAsPaid']);
    Route::get('admin/tuition-fees/statistics', [TuitionFeeController::class, 'statistics']);
    
    // Gestión de inscripciones (admin) - NUEVO SISTEMA
    Route::get('admin/enrollments', [EnrollmentController::class, 'index']);
    Route::post('admin/enrollments', [EnrollmentController::class, 'enrollUser']);
    Route::delete('admin/enrollments', [EnrollmentController::class, 'unenrollUser']);

    // Actualizar estado de inscripción
    Route::put('admin/enrollments/{enrollment}/status', [EnrollmentController::class, 'updateStatus']);
    
    }); // fin middleware admin

    // --- RUTAS PARA USUARIOS AUTENTICADOS (NO ADMIN) ---
    // Estas rutas están disponibles para todos los usuarios autenticados.
    // Si quieres restringir algo solo a 'parent', podrías usar otro middleware.
    // podrías definir rutas de solo lectura aquí. Por ejemplo:
    
    Route::get('courses', [CourseController::class, 'index']);
    Route::get('courses/{course}', [CourseController::class, 'show']);
    
    //Endpoint para regresar pool de imagenes
    Route::get('lessons/{lesson}/question-pool', [LessonController::class, 'getQuestionPool']);

    // Ver mis inscripciones (usuario autenticado)
    Route::get('my-enrollments', [EnrollmentController::class, 'myEnrollments']);
    
    // Progreso de lecciones
    Route::post('lessons/{lesson}/complete', [EnrollmentController::class, 'completeLesson'])->middleware('enrolled');
    Route::post('lessons/{lesson}/uncomplete', [EnrollmentController::class, 'uncompleteLesson'])->middleware('enrolled');
    Route::get('courses/{course}/progress', [EnrollmentController::class, 'courseProgress']);
    
    // OPTIMIZACIÓN: Obtener progreso de MÚLTIPLES lecciones en una sola llamada
    // NO requiere 'enrolled' porque es solo lectura
    Route::post('lessons/batch/progress', [LessonGameController::class, 'getBatchProgress']);
    
    // Rutas del juego de lecciones - REQUIEREN INSCRIPCIÓN ACTIVA
    Route::middleware('enrolled')->group(function () {
        Route::get('lessons/{lesson}/questions', [LessonGameController::class, 'getQuestions']);
        Route::post('lessons/{lesson}/result', [LessonGameController::class, 'saveResult']);
        Route::get('lessons/{lesson}/progress', [LessonGameController::class, 'getProgress']);
    });
    
    // Rutas de insignias para usuarios (solo lectura)
    Route::get('users/{user}/courses/{course}/badges', [BadgeController::class, 'userBadges']);
    
    // Rutas de matrículas para usuarios
    Route::get('tuition-fees', [TuitionFeeController::class, 'userFees']);
    Route::get('tuition-fees/pending', [TuitionFeeController::class, 'userPendingFees']);
    
}); // fin middleware auth:sanctum