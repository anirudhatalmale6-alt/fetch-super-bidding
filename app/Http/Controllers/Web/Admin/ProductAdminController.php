<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductAdminController extends Controller
{
    /**
     * List all products
     */
    public function index(Request $request)
    {
        $query = Product::with('categories')
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('product_categories.id', $request->input('category_id'));
            });
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->paginate(20);
        $categories = ProductCategory::active()->ordered()->get();

        return view('admin.shop.products.index', compact('products', 'categories'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $categories = ProductCategory::active()->ordered()->get();
        return view('admin.shop.products.create', compact('categories'));
    }

    /**
     * Store new product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'nullable|string|max:100|unique:products',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:product_categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'video_url' => 'nullable|url|max:500',
            'is_featured' => 'boolean',
            'status' => 'boolean',
            'target_audience' => 'required|in:all,users,companies',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products', 'public');
            }
        }

        // Generate SKU if not provided
        $sku = $request->input('sku') ?? 'PRD-' . strtoupper(Str::random(8));

        $product = Product::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'discount_price' => $request->input('discount_price'),
            'stock_quantity' => $request->input('stock_quantity'),
            'sku' => $sku,
            'images' => $imagePaths,
            'video_url' => $request->input('video_url'),
            'is_featured' => $request->boolean('is_featured', false),
            'status' => $request->boolean('status', true),
            'target_audience' => $request->input('target_audience', 'all'),
        ]);

        // Attach categories
        if ($request->has('category_ids')) {
            $product->categories()->attach($request->input('category_ids'));
        }

        return redirect()->route('admin.shop.products.index')
            ->with('success', 'Product created successfully');
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $product = Product::with('categories')->findOrFail($id);
        $categories = ProductCategory::active()->ordered()->get();
        return view('admin.shop.products.edit', compact('product', 'categories'));
    }

    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:product_categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'video_url' => 'nullable|url|max:500',
            'is_featured' => 'boolean',
            'status' => 'boolean',
            'target_audience' => 'required|in:all,users,companies',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle new image uploads
        $imagePaths = $product->images ?? [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products', 'public');
            }
        }

        $product->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'discount_price' => $request->input('discount_price'),
            'stock_quantity' => $request->input('stock_quantity'),
            'sku' => $request->input('sku', $product->sku),
            'images' => $imagePaths,
            'video_url' => $request->input('video_url'),
            'is_featured' => $request->boolean('is_featured', false),
            'status' => $request->boolean('status', true),
            'target_audience' => $request->input('target_audience', 'all'),
        ]);

        // Sync categories
        if ($request->has('category_ids')) {
            $product->categories()->sync($request->input('category_ids'));
        }

        return redirect()->route('admin.shop.products.index')
            ->with('success', 'Product updated successfully');
    }

    /**
     * Delete product
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Delete images
        if ($product->images) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $product->delete();

        return redirect()->route('admin.shop.products.index')
            ->with('success', 'Product deleted successfully');
    }

    /**
     * Delete product image
     */
    public function deleteImage(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $imageIndex = $request->input('image_index');

        $images = $product->images ?? [];
        if (isset($images[$imageIndex])) {
            Storage::disk('public')->delete($images[$imageIndex]);
            unset($images[$imageIndex]);
            $product->update(['images' => array_values($images)]);
        }

        return back()->with('success', 'Image deleted');
    }

    /**
     * Toggle product status
     */
    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => !$product->status]);

        return response()->json([
            'success' => true,
            'status' => $product->status,
            'message' => 'Product status updated',
        ]);
    }
}
