<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'in:income,expense'],
            'category' => ['sometimes', 'string', 'max:100'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $query = $request->user()->transactions()->orderByDesc('transaction_date')->orderByDesc('id');

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (isset($validated['from'])) {
            $query->whereDate('transaction_date', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('transaction_date', '<=', $validated['to']);
        }

        return response()->json([
            'data' => $query->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'description' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $transaction = $request->user()->transactions()->create($data);

        return response()->json([
            'message' => 'Transaction created.',
            'data' => $transaction->fresh(),
        ], 201);
    }

    public function show(Request $request, int $transaction): JsonResponse
    {
        $transaction = $request->user()->transactions()->findOrFail($transaction);

        return response()->json([
            'data' => $transaction,
        ]);
    }

    public function update(Request $request, int $transaction): JsonResponse
    {
        $transaction = $request->user()->transactions()->findOrFail($transaction);

        $data = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'description' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $transaction->update($data);

        return response()->json([
            'message' => 'Transaction updated.',
            'data' => $transaction->fresh(),
        ]);
    }

    public function destroy(Request $request, int $transaction): JsonResponse
    {
        $transaction = $request->user()->transactions()->findOrFail($transaction);
        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted.',
        ]);
    }
}
