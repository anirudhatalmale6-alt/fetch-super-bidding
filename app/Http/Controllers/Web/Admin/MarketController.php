<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Web\BaseController;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketController extends BaseController
{
    /**
     * Display a listing of products
     */
    public function index()
    {
        $pageTitle = 'Market Management';
        $main_menu = 'market';
        $sub_menu = 'market_products';
        $page = 'market_products';
        return view('admin.market.index', compact('pageTitle', 'main_menu', 'sub_menu', 'page'));
    }

    /**
     * Fetch products for datatable
     */
    public function fetch(Request $request)
    {
        $query = Product::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->paginate(15);

        return view('admin.market._products', compact('products'));
    }

    /**
     * Show the form for creating a new product
     */
    public function create()
    {
        $pageTitle = 'Add New Product';
        $main_menu = 'market';
        $sub_menu = 'market_create';
        $page = 'market_create';
        $categories = $this->getCategories();
        $targetAudiences = $this->getTargetAudiences();
        return view('admin.market.create', compact('pageTitle', 'main_menu', 'sub_menu', 'page', 'categories', 'targetAudiences'));
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'category' => 'required|string|max:100',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'video_url' => 'nullable|url|max:500',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'banner_video_url' => 'nullable|url|max:500',
            'is_featured' => 'boolean',
            'status' => 'boolean',
            'target_audience' => 'required|in:all,company,driver,user',
        ]);

        // Handle multiple product images
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products/images', 'public');
                $images[] = Storage::url($path);
            }
        }

        // Handle banner image
        $bannerImage = null;
        if ($request->hasFile('banner_image')) {
            $path = $request->file('banner_image')->store('products/banners', 'public');
            $bannerImage = Storage::url($path);
        }

        // Generate SKU if not provided
        if (empty($validated['sku'])) {
            $validated['sku'] = 'PRD-' . strtoupper(Str::random(8));
        }

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'discount_price' => $validated['discount_price'],
            'stock_quantity' => $validated['stock_quantity'],
            'sku' => $validated['sku'],
            'category' => $validated['category'],
            'images' => $images,
            'video_url' => $validated['video_url'],
            'banner_image' => $bannerImage,
            'banner_video_url' => $validated['banner_video_url'],
            'is_featured' => $request->boolean('is_featured', false),
            'status' => $request->boolean('status', true),
            'target_audience' => $validated['target_audience'],
        ]);

        return redirect()->route('market.index')->with('success', 'Product created successfully!');
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /**
     * Show the form for editing the specified product
     */
    public function edit(Product $product)
    {
        $pageTitle = 'Edit Product';
        $main_menu = 'market';
        $sub_menu = 'market_products';
        $page = 'market_products';
        $categories = $this->getCategories();
        $targetAudiences = $this->getTargetAudiences();
        return view('admin.market.edit', compact('product', 'pageTitle', 'main_menu', 'sub_menu', 'page', 'categories', 'targetAudiences'));
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'category' => 'required|string|max:100',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'video_url' => 'nullable|url|max:500',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'banner_video_url' => 'nullable|url|max:500',
            'is_featured' => 'boolean',
            'status' => 'boolean',
            'target_audience' => 'required|in:all,company,driver,user',
        ]);

        // Handle multiple product images
        $images = $product->images ?? [];
        if ($request->hasFile('images')) {
            // Delete old images if replacing
            if ($request->boolean('replace_images', false)) {
                foreach ($images as $oldImage) {
                    $oldPath = str_replace('/storage/', '', $oldImage);
                    Storage::disk('public')->delete($oldPath);
                }
                $images = [];
            }

            foreach ($request->file('images') as $image) {
                $path = $image->store('products/images', 'public');
                $images[] = Storage::url($path);
            }
        }

        // Handle banner image
        if ($request->hasFile('banner_image')) {
            // Delete old banner
            if ($product->banner_image) {
                $oldPath = str_replace('/storage/', '', $product->banner_image);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('banner_image')->store('products/banners', 'public');
            $bannerImage = Storage::url($path);
        } else {
            $bannerImage = $product->banner_image;
        }

        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'discount_price' => $validated['discount_price'],
            'stock_quantity' => $validated['stock_quantity'],
            'sku' => $validated['sku'] ?? $product->sku,
            'category' => $validated['category'],
            'images' => $images,
            'video_url' => $validated['video_url'],
            'banner_image' => $bannerImage,
            'banner_video_url' => $validated['banner_video_url'],
            'is_featured' => $request->boolean('is_featured', false),
            'status' => $request->boolean('status', true),
            'target_audience' => $validated['target_audience'],
        ]);

        return redirect()->route('market.index')->with('success', 'Product updated successfully!');
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        // Delete associated images
        if ($product->images && is_array($product->images)) {
            foreach ($product->images as $image) {
                $path = str_replace('/storage/', '', $image);
                Storage::disk('public')->delete($path);
            }
        }

        // Delete banner image
        if ($product->banner_image) {
            $path = str_replace('/storage/', '', $product->banner_image);
            Storage::disk('public')->delete($path);
        }

        $product->delete();

        return response()->json(['success' => 'Product deleted successfully!']);
    }

    /**
     * Toggle product status
     */
    public function toggleStatus(Product $product)
    {
        $product->status = !$product->status;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Product status updated successfully!',
            'status' => $product->status
        ]);
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(Product $product)
    {
        $product->is_featured = !$product->is_featured;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Featured status updated successfully!',
            'is_featured' => $product->is_featured
        ]);
    }

    /**
     * Delete a specific image from product
     */
    public function deleteImage(Request $request, Product $product)
    {
        $imageIndex = $request->input('image_index');
        $images = $product->images ?? [];

        if (isset($images[$imageIndex])) {
            $path = str_replace('/storage/', '', $images[$imageIndex]);
            Storage::disk('public')->delete($path);
            unset($images[$imageIndex]);
            $product->images = array_values($images);
            $product->save();

            return response()->json(['success' => true, 'message' => 'Image deleted successfully!']);
        }

        return response()->json(['success' => false, 'message' => 'Image not found!'], 404);
    }

    /**
     * Get product categories
     */
    private function getCategories()
    {
        return [
            'Helmets' => 'Helmets',
            'Electric Bikes' => 'Electric Bikes',
            'Cargo Equipment' => 'Cargo Equipment',
            'Safety Gear' => 'Safety Gear',
            'Accessories' => 'Accessories',
            'Spare Parts' => 'Spare Parts',
            'Uniforms' => 'Uniforms',
            'Electronics' => 'Electronics',
            'Other' => 'Other',
        ];
    }

    /**
     * Get target audiences
     */
    private function getTargetAudiences()
    {
        return [
            'all' => 'All Users',
            'company' => 'Company/Fleet Owners',
            'driver' => 'Drivers',
            'user' => 'Passengers/Users',
        ];
    }
}
