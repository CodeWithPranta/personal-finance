<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = $request->user()->categories()->orderBy('name')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'type' => ['nullable', 'in:income,expense'],
        ]);

        $category = $request->user()->categories()->create($data);

        return response()->json([
            'message' => 'Category created.',
            'data' => $category,
        ], 201);
    }

    public function show(Request $request, int $category): JsonResponse
    {
        $category = $request->user()->categories()->findOrFail($category);

        return response()->json([
            'data' => $category,
        ]);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $category = $request->user()->categories()->findOrFail($category);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories')->where(fn ($query) => $query->where('user_id', $request->user()->id))->ignore($category->id),
            ],
            'type' => ['nullable', 'in:income,expense'],
        ]);

        $category->update($data);

        return response()->json([
            'message' => 'Category updated.',
            'data' => $category->fresh(),
        ]);
    }

    public function destroy(Request $request, int $category): JsonResponse
    {
        $category = $request->user()->categories()->findOrFail($category);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted.',
        ]);
    }
}
