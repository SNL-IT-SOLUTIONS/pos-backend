<?php

namespace App\Http\Controllers;

use App\Models\Tags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TagsController extends Controller
{
    public function __construct()
    {
        // ✅ Protect all endpoints with Sanctum
        $this->middleware('auth:sanctum');
    }

    // ✅ Get all tags (exclude archived)
    public function getTags()
    {
        try {
            $tags = Tags::where('is_archived', 0)
                ->orderBy('tag_name')
                ->get();

            if ($tags->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No active tags found.',
                ], 404);
            }

            return response()->json([
                'isSuccess' => true,
                'tags'      => $tags,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve tags.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Get single tag by ID
    public function getTagById($id)
    {
        try {
            $tag = Tags::where('id', $id)
                ->where('is_archived', 0)
                ->first();

            if (!$tag) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Tag not found.',
                ], 404);
            }

            return response()->json([
                'isSuccess' => true,
                'tag'       => $tag,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to fetch tag.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Create new tag
    public function createTag(Request $request)
    {
        try {
            $validated = $request->validate([
                'tag_name'    => 'required|string|max:150|unique:tags,tag_name',
                'description' => 'nullable|string|max:255',
            ]);

            $tag = Tags::create(array_merge($validated, [
                'is_archived' => 0,
                'created_by'  => Auth::id(),
                'updated_by'  => Auth::id(),
            ]));

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Tag created successfully.',
                'tag'       => $tag,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to create tag.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Update existing tag
    public function updateTag(Request $request, $id)
    {
        try {
            $tag = Tags::where('id', $id)
                ->where('is_archived', 0)
                ->first();

            if (!$tag) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Tag not found.',
                ], 404);
            }

            $validated = $request->validate([
                'tag_name'    => 'required|string|max:150|unique:tags,tag_name,' . $id,
                'description' => 'nullable|string|max:255',
            ]);

            $tag->update(array_merge($validated, [
                'updated_by' => Auth::id(),
            ]));

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Tag updated successfully.',
                'tag'       => $tag,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update tag.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ Archive tag (soft delete)
    public function archiveTag($id)
    {
        try {
            $tag = Tags::where('id', $id)
                ->where('is_archived', 0)
                ->first();

            if (!$tag) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Tag not found or already archived.',
                ], 404);
            }

            $tag->update([
                'is_archived' => 1,
                'updated_by'  => Auth::id(),
            ]);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Tag archived successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to archive tag.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }
}
