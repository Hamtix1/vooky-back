<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Image;
use App\Models\Level;
use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LessonGameController extends Controller
{
    /**
     * Obtiene las preguntas para una lección específica
     */
    public function getQuestions(Request $request, $lessonId)
    {
        $lesson = Lesson::with('level')->findOrFail($lessonId);
        $level = $lesson->level;
        
        // Obtener todas las imágenes disponibles DENTRO del mismo curso:
        // - De niveles anteriores dentro del mismo curso (todos los días)
        // - Del nivel actual dentro del mismo curso (hasta el día de la lección)
        // Usamos la columna `order` en la tabla levels para comparar posiciones dentro del curso
        $availableImages = Image::whereHas('level', function($q) use ($level) {
                $q->where('course_id', $level->course_id)
                  ->where(function($qq) use ($level) {
                      // Niveles anteriores dentro del mismo curso (todas las imágenes)
                      $qq->where('order', '<', $level->order)
                         // O nivel actual (mismo order)
                         ->orWhere('order', $level->order);
                  });
            })
            ->where(function($q) use ($level, $lesson) {
                // Filtro adicional para el campo 'dia' que está en la tabla images
                $q->whereHas('level', function($qq) use ($level) {
                    // De niveles anteriores: todas las imágenes
                    $qq->where('course_id', $level->course_id)
                       ->where('order', '<', $level->order);
                })
                ->orWhere(function($qq) use ($level, $lesson) {
                    // Del nivel actual: solo hasta el día de la lección
                    $qq->whereHas('level', function($qqq) use ($level) {
                        $qqq->where('course_id', $level->course_id)
                            ->where('order', $level->order);
                    })
                    ->where('dia', '<=', $lesson->dia);
                });
            })
            ->with(['category', 'subcategories']) // Cargar categoría y subcategorías
            ->get();

        if ($availableImages->count() < 2) {
            return response()->json([
                'message' => 'No hay suficientes imágenes disponibles para generar preguntas.',
                'available_images' => $availableImages->count(),
                'level_id' => $level->id,
                'lesson_dia' => $lesson->dia
            ], 400);
        }

        // Generar 20 preguntas según el tipo de contenido
        $questions = $this->generateQuestions($lesson, $availableImages);

        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'content_type' => $lesson->content_type,
                'dia' => $lesson->dia,
            ],
            'questions' => $questions,
            'total_questions' => count($questions)
        ]);
    }

    /**
     * Genera preguntas basadas en el tipo de contenido de la lección
     */
    private function generateQuestions($lesson, $availableImages)
    {
        $questions = [];
        $totalQuestions = 20;

        // Normalizar el content_type: quitar acentos, a minúsculas
        $contentType = strtolower($lesson->content_type);
        $contentType = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $contentType);

        
        // Manejar diferentes variaciones del content_type
        if (in_array($contentType, ['combinadas', 'combinado', 'combinada'])) {
            $questions = $this->generateMixedCategoryQuestions($availableImages, $totalQuestions, $lesson->dia);
        } elseif (in_array($contentType, ['enlace de categorias', 'enlace de categoria', 'enlace_categoria', 'enlace categoria', 'misma_categoria', 'misma categoria', 'misma categoría'])) {
            $questions = $this->generateSameCategoryQuestions($availableImages, $totalQuestions, $lesson->dia);
        } elseif ($contentType === 'mixto') {
            // 10 preguntas combinadas + 10 de enlace de categoría
            $mixed = $this->generateMixedCategoryQuestions($availableImages, 10, $lesson->dia);
            $sameCategory = $this->generateSameCategoryQuestions($availableImages, 10, $lesson->dia);
            $questions = array_merge($mixed, $sameCategory);
            // Mezclar las preguntas
            shuffle($questions);
            // Renumerar después de mezclar
            foreach ($questions as $index => &$question) {
                $question['question_number'] = $index + 1;
            }
        } else {
            // Por defecto, usar preguntas combinadas
            Log::warning("Unknown content_type '{$lesson->content_type}', using mixed categories as default");
            $questions = $this->generateMixedCategoryQuestions($availableImages, $totalQuestions, $lesson->dia);
        }

        return $questions;
    }

    /**
     * Genera preguntas con imágenes de diferentes categorías
     */
    private function generateMixedCategoryQuestions($images, $count, $lessonDay = null)
    {
        $questions = [];
        
        // Separar imágenes del día actual y días anteriores (trabajar con Collection)
        $currentDayImages = collect();
        $previousDaysImages = collect();
        
        if ($lessonDay !== null) {
            $currentDayImages = $images->filter(function($image) use ($lessonDay) {
                return $image->dia == $lessonDay;
            });
            $previousDaysImages = $images->filter(function($image) use ($lessonDay) {
                return $image->dia != $lessonDay;
            });
        } else {
            $previousDaysImages = $images;
        }
        
        // Calcular cuántas preguntas del día actual (50% del total, permitiendo repetición)
        $currentDayQuestions = $currentDayImages->isNotEmpty() ? (int)ceil($count / 2) : 0;
        $previousDaysQuestions = $count - $currentDayQuestions;
        
    Log::info("Mixed questions: {$currentDayQuestions} from day {$lessonDay}, {$previousDaysQuestions} from previous days");
    Log::info("Available images for day {$lessonDay}: " . $currentDayImages->count());
        
        // Generar preguntas del día actual (PRIMERO, sin mezclar - permite repetición)
        for ($i = 0; $i < $currentDayQuestions; $i++) {
            if ($currentDayImages->isEmpty()) break;
            
            // Permitir repetición de imágenes si no hay suficientes únicas
            $correctImage = $currentDayImages->random();
            
            // Buscar una imagen válida aplicando reglas anti-ambigüedad
            $incorrectImage = $this->findValidIncorrectImage($correctImage, $images);
            
            if ($incorrectImage) {
                $questions[] = $this->formatQuestion($correctImage, $incorrectImage, $i + 1);
            }
        }
        
        // Generar preguntas de días anteriores (DESPUÉS, sin mezclar)
        for ($i = 0; $i < $previousDaysQuestions; $i++) {
            if ($previousDaysImages->isEmpty()) {
                // Fallback: usar todas las imágenes si no hay de días anteriores
                $previousDaysImages = $images;
            }
            
            $correctImage = $previousDaysImages->random();
            
            // Buscar una imagen válida aplicando reglas anti-ambigüedad
            $incorrectImage = $this->findValidIncorrectImage($correctImage, $images);
            
            if ($incorrectImage) {
                $questions[] = $this->formatQuestion($correctImage, $incorrectImage, count($questions) + 1);
            }
        }
        
        // NO mezclar - mantener las preguntas del día actual primero
        
        return $questions;
    }

    /**
     * Genera preguntas con imágenes de la misma categoría
     * Aplica reglas anti-ambigüedad de subcategorías
     */
    private function generateSameCategoryQuestions($images, $count, $lessonDay = null)
    {
        $questions = [];
        
        // Separar imágenes del día actual y días anteriores
        $currentDayImages = collect();
        $previousDaysImages = collect();
        
        if ($lessonDay !== null) {
            $currentDayImages = $images->filter(function($image) use ($lessonDay) {
                return $image->dia == $lessonDay;
            });
            $previousDaysImages = $images->filter(function($image) use ($lessonDay) {
                return $image->dia != $lessonDay;
            });
        } else {
            $previousDaysImages = $images;
        }
        
        // Agrupar por categoría
        $currentDayByCategory = $currentDayImages->groupBy('category_id');
        $previousDaysByCategory = $previousDaysImages->groupBy('category_id');
        $allByCategory = $images->groupBy('category_id');
        
        // Calcular cuántas preguntas del día actual (50%)
        $currentDayQuestions = $currentDayByCategory->isNotEmpty() ? (int)ceil($count / 2) : 0;
        $previousDaysQuestions = $count - $currentDayQuestions;
        
        // Generar preguntas del día actual
        for ($i = 0; $i < $currentDayQuestions; $i++) {
            if ($currentDayByCategory->isEmpty()) break;
            
            // Seleccionar una categoría aleatoria
            $categoryId = $currentDayByCategory->keys()->random();
            $categoryImages = $currentDayByCategory->get($categoryId);
            
            // Seleccionar imagen correcta aleatoria
            $correctImage = $categoryImages->random();
            
            // Buscar imagen incorrecta de la misma categoría que sea válida
            $incorrectImage = $this->findValidIncorrectImageSameCategory($correctImage, $categoryImages);
            
            if ($incorrectImage) {
                $questions[] = $this->formatQuestion($correctImage, $incorrectImage, $i + 1);
            } else {
                // Si no hay válida en misma categoría, usar el método mixto
                $incorrectImage = $this->findValidIncorrectImage($correctImage, $images);
                if ($incorrectImage) {
                    $questions[] = $this->formatQuestion($correctImage, $incorrectImage, $i + 1);
                }
            }
        }
        
        // Generar preguntas de días anteriores
        for ($i = 0; $i < $previousDaysQuestions; $i++) {
            $sourceByCategory = $previousDaysByCategory->isNotEmpty() ? $previousDaysByCategory : $allByCategory;
            
            if ($sourceByCategory->isEmpty()) break;
            
            $categoryId = $sourceByCategory->keys()->random();
            $categoryImages = $sourceByCategory->get($categoryId);
            
            $correctImage = $categoryImages->random();
            
            // Buscar imagen incorrecta de la misma categoría que sea válida
            $incorrectImage = $this->findValidIncorrectImageSameCategory($correctImage, $categoryImages);
            
            if ($incorrectImage) {
                $questions[] = $this->formatQuestion($correctImage, $incorrectImage, count($questions) + 1);
            } else {
                // Fallback a método mixto
                $incorrectImage = $this->findValidIncorrectImage($correctImage, $images);
                if ($incorrectImage) {
                    $questions[] = $this->formatQuestion($correctImage, $incorrectImage, count($questions) + 1);
                }
            }
        }
        
        // Si no se generaron suficientes preguntas, usar método mixto
        if (count($questions) < $count) {
            return $this->generateMixedCategoryQuestions($images, $count, $lessonDay);
        }
        
        return $questions;
    }

    /**
     * Encuentra una imagen incorrecta válida dentro de la misma categoría
     * 
     * @param mixed $correctImage La imagen correcta
     * @param \Illuminate\Support\Collection $categoryImages Imágenes de la misma categoría
     * @return mixed|null
     */
    private function findValidIncorrectImageSameCategory($correctImage, $categoryImages)
    {
        $validCandidates = $categoryImages->filter(function($candidate) use ($correctImage) {
            return $this->canImagesAppearTogether($correctImage, $candidate);
        });

        return $validCandidates->isNotEmpty() ? $validCandidates->random() : null;
    }

    /**
     * Encuentra una imagen incorrecta válida para emparejar con la imagen correcta
     * Aplica las reglas anti-ambigüedad basadas en subcategorías
     * 
     * @param mixed $correctImage La imagen correcta (con el audio que suena)
     * @param \Illuminate\Support\Collection $allImages Todas las imágenes disponibles
     * @return mixed|null
     */
    private function findValidIncorrectImage($correctImage, $allImages)
    {
        // Filtrar imágenes que pueden aparecer con la correcta
        $validCandidates = $allImages->filter(function($candidate) use ($correctImage) {
            return $this->canImagesAppearTogether($correctImage, $candidate);
        });

        // Prioridad 1: Buscar imágenes de diferente categoría
        $differentCategory = $validCandidates->filter(function($img) use ($correctImage) {
            return $img->category_id !== $correctImage->category_id;
        });

        if ($differentCategory->isNotEmpty()) {
            return $differentCategory->random();
        }

        // Prioridad 2: Si no hay de diferente categoría, usar de la misma (ya validadas)
        if ($validCandidates->isNotEmpty()) {
            return $validCandidates->random();
        }

        // Fallback: cualquier imagen diferente (última opción)
        $anyOther = $allImages->filter(function($img) use ($correctImage) {
            return $img->id !== $correctImage->id;
        });

        return $anyOther->isNotEmpty() ? $anyOther->random() : null;
    }

    /**
     * Valida si dos imágenes pueden aparecer juntas en una pregunta
     * 
     * Reglas anti-ambigüedad actualizadas:
     * 1. Diferentes categorías → Siempre válido
     * 2. Misma categoría:
     *    a. Correcta tiene subcategoría + Candidata tiene DIFERENTES subcategorías → VÁLIDO
     *    b. Comparten AL MENOS UNA subcategoría → NO VÁLIDO (ambigüedad)
     *    c. Correcta tiene subcategoría + Candidata NO tiene → VÁLIDO
     *    d. Correcta NO tiene + Candidata SÍ tiene → NO VÁLIDO (ambigüedad)
     *    e. Ninguna tiene subcategoría → NO VÁLIDO (no es útil como pregunta)
     * 
     * @param mixed $correctImage Imagen correcta (la que tiene el audio que suena)
     * @param mixed $candidateImage Imagen candidata a ser la opción incorrecta
     * @return bool
     */
    private function canImagesAppearTogether($correctImage, $candidateImage)
    {
        // No puede ser la misma imagen
        if ($correctImage->id === $candidateImage->id) {
            return false;
        }

        // Si son de categorías diferentes, siempre válido
        if ($correctImage->category_id !== $candidateImage->category_id) {
            return true;
        }

        // Ambas son de la MISMA categoría - aplicar reglas de subcategorías
        
        $correctSubcategories = $correctImage->subcategories;
        $candidateSubcategories = $candidateImage->subcategories;
        
        $correctHasSubcategories = $correctSubcategories->isNotEmpty();
        $candidateHasSubcategories = $candidateSubcategories->isNotEmpty();

        // Regla CRÍTICA: Si comparten AL MENOS UNA subcategoría → NO válido (ambigüedad)
        if ($correctHasSubcategories && $candidateHasSubcategories) {
            $correctSubcategoryIds = $correctSubcategories->pluck('id')->toArray();
            $candidateSubcategoryIds = $candidateSubcategories->pluck('id')->toArray();
            
            // Si hay intersección → comparten al menos una subcategoría → NO válido
            if (array_intersect($correctSubcategoryIds, $candidateSubcategoryIds)) {
                return false;
            }
            
            // Si NO comparten ninguna subcategoría → VÁLIDO
            // (Ej: libro rojo vs libro azul - subcategorías diferentes)
            return true;
        }

        // Regla: Correcta tiene subcategoría + Candidata NO tiene → VÁLIDO
        // (Ej: Audio "libro rojo" vs imagen "libro genérico")
        if ($correctHasSubcategories && !$candidateHasSubcategories) {
            return true;
        }

        // Regla: Correcta NO tiene subcategoría + Candidata SÍ tiene → NO VÁLIDO
        // (Ej: Audio "libro genérico" vs imagen "libro rojo" - ambigüedad)
        if (!$correctHasSubcategories && $candidateHasSubcategories) {
            return false;
        }

        // Regla: Ninguna tiene subcategorías → NO VÁLIDO
        // (Dos imágenes genéricas de la misma categoría no son buena pregunta)
        return false;
    }

    /**
     * Formatea una pregunta con dos opciones (correcta e incorrecta)
     */
    private function formatQuestion($correctImage, $incorrectImage, $questionNumber)
    {
        // Aleatorizar la posición de la respuesta correcta (izquierda o derecha)
        $isCorrectOnLeft = rand(0, 1) === 1;
        
        return [
            'question_number' => $questionNumber,
            'audio_url' => $correctImage->audio_file_url,
            'correct_image_id' => $correctImage->id,
            'dia' => $correctImage->dia, // Día de la imagen correcta
            'options' => [
                'left' => [
                    'id' => $isCorrectOnLeft ? $correctImage->id : $incorrectImage->id,
                    'url' => $isCorrectOnLeft ? $correctImage->file_url : $incorrectImage->file_url,
                    'description' => $isCorrectOnLeft ? $correctImage->description : $incorrectImage->description,
                    'dia' => $isCorrectOnLeft ? $correctImage->dia : $incorrectImage->dia, // Día de la opción
                ],
                'right' => [
                    'id' => $isCorrectOnLeft ? $incorrectImage->id : $correctImage->id,
                    'url' => $isCorrectOnLeft ? $incorrectImage->file_url : $correctImage->file_url,
                    'description' => $isCorrectOnLeft ? $incorrectImage->description : $correctImage->description,
                    'dia' => $isCorrectOnLeft ? $incorrectImage->dia : $correctImage->dia, // Día de la opción
                ]
            ]
        ];
    }

    /**
     * Guarda el resultado de una lección completada
     */
    public function saveResult(Request $request, $lessonId)
    {
        $request->validate([
            'correct_answers' => 'required|integer|min:0|max:20',
            'total_questions' => 'required|integer|min:1|max:20',
            'game_score' => 'nullable|integer|min:0', // Score del juego con combos (sin límite superior)
        ]);

        $user = $request->user();
        $lesson = Lesson::findOrFail($lessonId);
        
        // Calcular accuracy actual (0-100) - porcentaje de aciertos
        $newAccuracy = ($request->correct_answers / $request->total_questions) * 100;
        $roundedNewAccuracy = round($newAccuracy);
        
        // Game score (puntuación con combos y bonos) - viene del frontend
        $newGameScore = $request->input('game_score', $roundedNewAccuracy);
        
        // Determinar si aprobó en este intento (75% o más de accuracy)
        $passedNow = $newAccuracy >= 75;
        
        // Obtener el progreso previo (si existe)
        $previousProgress = DB::table('lesson_user')
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();
        
        // Determinar los valores finales
        $finalAccuracy = $roundedNewAccuracy;
        $finalGameScore = $newGameScore;
        $finalCorrectAnswers = $request->correct_answers;
        $finalTotalQuestions = $request->total_questions;
        $finalCompletedAt = null;
        $wasAlreadyCompleted = false;
        
        if ($previousProgress) {
            // Ya existe un registro previo
            $wasAlreadyCompleted = !is_null($previousProgress->completed_at);
            
            // Mantener la accuracy más alta
            if ($previousProgress->accuracy && $previousProgress->accuracy > $roundedNewAccuracy) {
                $finalAccuracy = $previousProgress->accuracy;
                $finalCorrectAnswers = $previousProgress->correct_answers;
                $finalTotalQuestions = $previousProgress->total_questions;
            }
            
            // Mantener el game_score más alto
            if ($previousProgress->game_score && $previousProgress->game_score > $newGameScore) {
                $finalGameScore = $previousProgress->game_score;
            }
            
            // Mantener completed_at si ya estaba aprobada, o actualizar si aprueba ahora
            if ($wasAlreadyCompleted) {
                // Ya estaba aprobada, mantener la fecha original
                $finalCompletedAt = $previousProgress->completed_at;
            } elseif ($passedNow) {
                // Primera vez que aprueba
                $finalCompletedAt = now();
            }
        } else {
            // Primer intento
            if ($passedNow) {
                $finalCompletedAt = now();
            }
        }
        
        // Actualizar o crear el registro
        DB::table('lesson_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id
            ],
            [
                'accuracy' => $finalAccuracy,
                'game_score' => $finalGameScore,
                'correct_answers' => $finalCorrectAnswers,
                'total_questions' => $finalTotalQuestions,
                'completed_at' => $finalCompletedAt,
                'updated_at' => now(),
            ]
        );
        
        // Determinar el estado final (aprobada si tiene completed_at)
        $finalPassed = !is_null($finalCompletedAt);
        
        // Mensajes descriptivos
        $message = '';
        if ($passedNow && !$wasAlreadyCompleted) {
            $message = '¡Lección aprobada por primera vez!';
        } elseif ($passedNow && $wasAlreadyCompleted && $roundedNewAccuracy > $previousProgress->accuracy) {
            $message = '¡Nueva mejor puntuación! Lección ya aprobada anteriormente.';
        } elseif ($passedNow && $wasAlreadyCompleted && $roundedNewAccuracy <= $previousProgress->accuracy) {
            $message = 'Lección ya aprobada. Puntuación anterior es mejor.';
        } elseif (!$passedNow && $wasAlreadyCompleted) {
            $message = 'Lección sigue aprobada. Este intento no superó el 75%.';
        } else {
            $message = 'Lección no aprobada - Necesitas 75% o más.';
        }

        // Verificar y otorgar insignias si completó la lección por primera vez
        $newBadges = [];
        if ($passedNow && !$wasAlreadyCompleted) {
            $newBadges = $this->checkAndAwardBadges($user->id, $lesson->level->course_id);
        }

        return response()->json([
            'message' => $message,
            'accuracy' => $finalAccuracy, // Mejor accuracy (porcentaje de aciertos)
            'game_score' => $finalGameScore, // Mejor game score (con combos)
            'current_attempt_accuracy' => $roundedNewAccuracy, // Accuracy de este intento
            'current_attempt_score' => $newGameScore, // Game score de este intento
            'correct_answers' => $finalCorrectAnswers,
            'total_questions' => $finalTotalQuestions,
            'passed' => $finalPassed,
            'improved' => $previousProgress && $roundedNewAccuracy > $previousProgress->accuracy,
            'was_already_completed' => $wasAlreadyCompleted,
            'new_badges' => $newBadges, // Nuevas insignias obtenidas
        ]);
    }

    /**
     * Verificar y otorgar insignias a un usuario basado en lecciones completadas
     */
    private function checkAndAwardBadges($userId, $courseId)
    {
        // Contar lecciones completadas del usuario en este curso
        $completedLessonsCount = DB::table('lesson_user')
            ->join('lessons', 'lessons.id', '=', 'lesson_user.lesson_id')
            ->join('levels', 'levels.id', '=', 'lessons.level_id')
            ->where('lesson_user.user_id', $userId)
            ->where('levels.course_id', $courseId)
            ->whereNotNull('lesson_user.completed_at')
            ->count();

        // Obtener insignias del curso que el usuario aún no tiene y ha alcanzado
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
            $newBadges[] = [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'image' => $badge->image,
                'lessons_required' => $badge->lessons_required,
            ];
        }

        return $newBadges;
    }

    /**
     * Obtiene el progreso del usuario en una lección
     */
    public function getProgress(Request $request, $lessonId)
    {
        try {
            $user = $request->user();
            
            // Validar que lessonId sea un número válido
            if (!is_numeric($lessonId) || $lessonId <= 0) {
                Log::warning('getProgress: lesson_id inválido', ['lesson_id' => $lessonId]);
                return response()->json([
                    'error' => 'Invalid lesson ID',
                    'message' => 'El ID de la lección no es válido'
                ], 400);
            }
            
            Log::debug('getProgress: Obteniendo progreso', ['user_id' => $user->id, 'lesson_id' => $lessonId]);
            
            $progress = DB::table('lesson_user')
                ->where('user_id', $user->id)
                ->where('lesson_id', (int)$lessonId)
                ->first();

            if (!$progress) {
                Log::debug('getProgress: No hay progreso registrado', ['user_id' => $user->id, 'lesson_id' => $lessonId]);
                return response()->json([
                    'completed' => false,
                    'accuracy' => null,
                    'game_score' => null,
                    'correct_answers' => null,
                    'total_questions' => null,
                    'completed_at' => null,
                ]);
            }

            Log::debug('getProgress: Progreso encontrado', ['progress' => $progress]);
            
            return response()->json([
                'completed' => !is_null($progress->completed_at),
                'accuracy' => $progress->accuracy ?? null,
                'game_score' => $progress->game_score ?? null,
                'correct_answers' => $progress->correct_answers ?? null,
                'total_questions' => $progress->total_questions ?? null,
                'completed_at' => $progress->completed_at,
            ]);
        } catch (\Exception $e) {
            Log::error('getProgress: Error al obtener el progreso', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'lesson_id' => $lessonId
            ]);
            return response()->json([
                'error' => 'Error al obtener el progreso',
                'message' => 'Ocurrió un error al obtener el progreso',
            ], 500);
        }
    }

    /**
     * Obtiene el progreso del usuario para MÚLTIPLES lecciones de una vez
     * OPTIMIZATION: Evita N+1 queries cuando se necesita el progreso de muchas lecciones
     * 
     * Request body: { "lesson_ids": [1, 2, 3, 4, 5...] }
     */
    public function getBatchProgress(Request $request)
    {
        try {
            // Verificar que el usuario está autenticado
            $user = $request->user();
            if (!$user) {
                Log::warning('getBatchProgress: Usuario no autenticado');
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Validar que se envíen lesson_ids
            $validated = $request->validate([
                'lesson_ids' => 'required|array|min:1|max:100',
                'lesson_ids.*' => 'required|integer|min:1',
            ]);

            $lessonIds = $validated['lesson_ids'];
            Log::debug('getBatchProgress: Solicitando progreso para lessons', ['lesson_ids' => $lessonIds, 'user_id' => $user->id]);
            
            // Filtrar IDs válidos directamente sin hacer consulta adicional
            // Simplemente usar los IDs tal como vienen (confianza en el cliente)
            $validLessonIds = array_filter($lessonIds, fn($id) => is_numeric($id) && $id > 0);
            
            if (empty($validLessonIds)) {
                Log::warning('getBatchProgress: No hay IDs de lección válidos', ['original' => $lessonIds]);
                return response()->json([
                    'data' => [],
                    'count' => 0
                ]);
            }
            
            // Una única query para obtener TODO el progreso
            // Usar whereIn directamente con los IDs sin hacer consulta de verificación primero
            $progressData = DB::table('lesson_user')
                ->where('user_id', $user->id)
                ->whereIn('lesson_id', $validLessonIds)
                ->select('lesson_id', 'completed_at', 'accuracy', 'game_score', 'correct_answers', 'total_questions')
                ->get();

            Log::debug('getBatchProgress: Datos obtenidos de BD', ['count' => $progressData->count()]);

            // Convertir a un mapa keyed por lesson_id
            $progressMap = [];
            foreach ($progressData as $progress) {
                $progressMap[$progress->lesson_id] = $progress;
            }

            // Construir respuesta con progreso para cada lección (null si no existe)
            $result = [];
            foreach ($validLessonIds as $lessonId) {
                $lessonProgress = $progressMap[$lessonId] ?? null;
                
                if ($lessonProgress) {
                    $result[$lessonId] = [
                        'lesson_id' => $lessonId,
                        'completed' => !is_null($lessonProgress->completed_at),
                        'accuracy' => $lessonProgress->accuracy ?? null,
                        'game_score' => $lessonProgress->game_score ?? null,
                        'correct_answers' => $lessonProgress->correct_answers ?? null,
                        'total_questions' => $lessonProgress->total_questions ?? null,
                        'completed_at' => $lessonProgress->completed_at,
                    ];
                } else {
                    $result[$lessonId] = [
                        'lesson_id' => $lessonId,
                        'completed' => false,
                        'accuracy' => null,
                        'game_score' => null,
                        'correct_answers' => null,
                        'total_questions' => null,
                        'completed_at' => null,
                    ];
                }
            }

            Log::debug('getBatchProgress: Respuesta construida', ['result_count' => count($result)]);
            
            return response()->json([
                'data' => $result,
                'count' => count($result)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('getBatchProgress: Validation error', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Validation error',
                'message' => 'Los datos enviados no son válidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('getBatchProgress: Error general', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener el progreso en lote',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
