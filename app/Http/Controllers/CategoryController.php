<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function __construct()
    {
        //  Protect all routes with Sanctum
        $this->middleware('auth:sanctum');
    }

    //  Get all categories (exclude archived)
    public function getCategories(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);

            $categories = Category::where('is_archived', 0)
                ->orderBy('category_name')
                ->paginate($perPage);

            if ($categories->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No active categories found.',
                ], 404);
            }

            return response()->json([
                'isSuccess'  => true,
                'categories' => $categories->items(),
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'per_page'     => $categories->perPage(),
                    'total'        => $categories->total(),
                    'last_page'    => $categories->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve categories.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }


    // Get single category
    public function getCategoryById($id)
    {
        try {
            $category = Category::where('id', $id)
                ->where('is_archived', 0)
                ->first();

            if (!$category) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Category not found.',
                ], 404);
            }

            return response()->json([
                'isSuccess' => true,
                'category'  => $category,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to fetch category.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    //  Create category
    public function createCategory(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_name' => 'required|string|max:150|unique:categories,category_name',
                'description'   => 'nullable|string|max:255',
            ]);

            $category = Category::create(array_merge($validated, [
                'is_archived' => 0,
                'created_by'  => Auth::id(),
                'updated_by'  => Auth::id(),
            ]));

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Category created successfully.',
                'category'  => $category,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to create category.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // Update category
    public function updateCategory(Request $request, $id)
    {
        try {
            $category = Category::where('id', $id)
                ->where('is_archived', 0)
                ->first();

            if (!$category) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Category not found.',
                ], 404);
            }

            $validated = $request->validate([
                'category_name' => 'required|string|max:150|unique:categories,category_name,' . $id,
                'description'   => 'nullable|string|max:255',
            ]);

            $category->update(array_merge($validated, [
                'updated_by' => Auth::id(),
            ]));

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Category updated successfully.',
                'category'  => $category,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update category.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // Soft delete category (set is_archived = 1)
    public function archiveCategory($id)
    {
        try {
            $category = Category::where('id', $id)
                ->where('is_archived', 0)
                ->first();

            if (!$category) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Category not found or already archived.',
                ], 404);
            }

            $category->update([
                'is_archived' => 1,
                'updated_by'  => Auth::id(),
            ]);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Category archived successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to archive category.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }
}
