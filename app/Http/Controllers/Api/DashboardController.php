<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalIncome = (float) $user->transactions()->where('type', 'income')->sum('amount');
        $totalExpense = (float) $user->transactions()->where('type', 'expense')->sum('amount');

        $byCategory = $user->transactions()
            ->selectRaw('category, type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category', 'type')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => [
                'total_income' => number_format($totalIncome, 2, '.', ''),
                'total_expense' => number_format($totalExpense, 2, '.', ''),
                'balance' => number_format($totalIncome - $totalExpense, 2, '.', ''),
                'transaction_count' => $user->transactions()->count(),
                'by_category' => $byCategory,
                'recent' => $user->transactions()->orderByDesc('transaction_date')->orderByDesc('id')->limit(5)->get(),
            ],
        ]);
    }
}
