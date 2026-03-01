<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductCategoryAdminController extends Controller
{
    /**
     * List all categories
     */
    public function index()
    {
        $categories = ProductCategory::withCount('products')
            ->ordered()
            ->get();

        return view('admin.shop.categories.index', compact('categories'));
    }

    /**
     * Store new category
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        ProductCategory::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')) . '-' . uniqid(),
            'description' => $request->input('description'),
            'image' => $imagePath,
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => true,
        ]);

        return redirect()->route('admin.shop.categories.index')
            ->with('success', 'Category created successfully');
    }

    /**
     * Update category
     */
    public function update(Request $request, $id)
    {
        $category = ProductCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $imagePath = $request->file('image')->store('categories', 'public');
            $category->image = $imagePath;
        }

        $category->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'sort_order' => $request->input('sort_order', $category->sort_order),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.shop.categories.index')
            ->with('success', 'Category updated successfully');
    }

    /**
     * Delete category
     */
    public function destroy($id)
    {
        $category = ProductCategory::findOrFail($id);

        // Delete image
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return redirect()->route('admin.shop.categories.index')
            ->with('success', 'Category deleted successfully');
    }

    /**
     * Toggle category status
     */
    public function toggleStatus($id)
    {
        $category = ProductCategory::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);

        return response()->json([
            'success' => true,
            'status' => $category->is_active,
            'message' => 'Category status updated',
        ]);
    }
}
