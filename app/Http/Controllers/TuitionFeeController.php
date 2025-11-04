<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\TuitionFee;
use App\Models\Course;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TuitionFeeController extends Controller
{
    /**
     * Obtener todas las matrículas de un usuario
     */
    public function userFees(Request $request)
    {
        $user = $request->user();

        $fees = TuitionFee::whereHas('enrollment', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['enrollment.course'])
            ->orderBy('due_date', 'desc')
            ->get();

        return response()->json($fees);
    }

    /**
     * Obtener matrículas pendientes de un usuario
     */
    public function userPendingFees(Request $request)
    {
        $user = $request->user();

        $fees = TuitionFee::whereHas('enrollment', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->whereIn('status', ['pending', 'overdue'])
            ->with(['enrollment.course'])
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json($fees);
    }

    /**
     * Obtener todas las matrículas (admin)
     */
    public function index(Request $request)
    {
        $query = TuitionFee::with(['enrollment.user', 'enrollment.course']);

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->whereHas('enrollment', function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }

        if ($request->has('course_id')) {
            $query->whereHas('enrollment', function ($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        $fees = $query->orderBy('due_date', 'desc')->get();

        return response()->json($fees);
    }

    /**
     * Marcar matrícula como pagada
     */
    public function markAsPaid(Request $request, $id)
    {
        $fee = TuitionFee::findOrFail($id);
        
        $request->validate([
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Cargar la relación antes de marcar como pagado
        $fee->load('enrollment');

        $fee->markAsPaid(
            $request->payment_method,
            $request->notes
        );

        // Refrescar para obtener el estado actualizado
        $fee->refresh();
        $fee->load(['enrollment.course', 'enrollment.user']);

        return response()->json([
            'message' => 'Matrícula marcada como pagada y curso activado',
            'fee' => $fee,
        ]);
    }

    /**
     * Crear matrícula manualmente (admin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
        ]);

        $fee = TuitionFee::create([
            'enrollment_id' => $request->enrollment_id,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Matrícula creada exitosamente',
            'fee' => $fee->load(['enrollment.course', 'enrollment.user']),
        ], 201);
    }

    /**
     * Actualizar matrícula (admin)
     */
    public function update(Request $request, $id)
    {
        $fee = TuitionFee::findOrFail($id);
        
        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:pending,paid,overdue',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $fee->update($request->only([
            'amount',
            'due_date',
            'status',
            'payment_method',
            'notes'
        ]));

        return response()->json([
            'message' => 'Matrícula actualizada exitosamente',
            'fee' => $fee->load(['enrollment.course', 'enrollment.user']),
        ]);
    }

    /**
     * Eliminar matrícula (admin)
     */
    public function destroy($id)
    {
        $fee = TuitionFee::findOrFail($id);
        $fee->delete();

        return response()->json([
            'message' => 'Matrícula eliminada exitosamente',
        ]);
    }

    /**
     * Estadísticas de pagos (admin)
     */
    public function statistics()
    {
        $stats = [
            'total_fees' => TuitionFee::count(),
            'pending' => TuitionFee::where('status', 'pending')->count(),
            'paid' => TuitionFee::where('status', 'paid')->count(),
            'overdue' => TuitionFee::where('status', 'overdue')->count(),
            'total_amount' => [
                'pending' => TuitionFee::where('status', 'pending')->sum('amount'),
                'paid' => TuitionFee::where('status', 'paid')->sum('amount'),
                'overdue' => TuitionFee::where('status', 'overdue')->sum('amount'),
            ],
            'this_month' => [
                'pending' => TuitionFee::where('status', 'pending')
                    ->whereMonth('due_date', now()->month)
                    ->whereYear('due_date', now()->year)
                    ->count(),
                'paid' => TuitionFee::where('status', 'paid')
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->count(),
            ],
        ];

        return response()->json($stats);
    }
}
