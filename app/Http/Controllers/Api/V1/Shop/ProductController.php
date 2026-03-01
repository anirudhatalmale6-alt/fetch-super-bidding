<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    /**
     * List all active products for shop
     */
    public function index(Request $request)
    {
        $query = Product::with('categories')
            ->where('status', true)
            ->where(function ($q) {
                $q->where('target_audience', 'all')
                  ->orWhere('target_audience', 'company');
            });

        // Filter by category
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('product_categories.id', $request->input('category_id'));
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $products = $query->paginate($request->input('per_page', 12));

        return $this->respondSuccess([
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->sku,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount_price' => $product->discount_price,
                    'final_price' => $product->final_price,
                    'has_discount' => $product->has_discount,
                    'discount_percentage' => $product->discount_percentage,
                    'stock_quantity' => $product->stock_quantity,
                    'sku' => $product->sku,
                    'images' => $product->images,
                    'video_url' => $product->video_url,
                    'first_image' => $product->first_image,
                    'is_featured' => $product->is_featured,
                    'categories' => $product->categories->map(fn($cat) => [
                        'id' => $cat->id,
                        'name' => $cat->name,
                    ]),
                ];
            }),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Get featured products
     */
    public function featured(Request $request)
    {
        $products = Product::with('categories')
            ->where('status', true)
            ->where('is_featured', true)
            ->where(function ($q) {
                $q->where('target_audience', 'all')
                  ->orWhere('target_audience', 'company');
            })
            ->limit($request->input('limit', 8))
            ->get();

        return $this->respondSuccess([
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'discount_price' => $product->discount_price,
                    'final_price' => $product->final_price,
                    'has_discount' => $product->has_discount,
                    'first_image' => $product->first_image,
                    'is_featured' => $product->is_featured,
                ];
            }),
        ]);
    }

    /**
     * Get single product details
     */
    public function show($id)
    {
        $product = Product::with('categories')
            ->where('status', true)
            ->where(function ($q) {
                $q->where('target_audience', 'all')
                  ->orWhere('target_audience', 'company');
            })
            ->findOrFail($id);

        return $this->respondSuccess([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'discount_price' => $product->discount_price,
                'final_price' => $product->final_price,
                'has_discount' => $product->has_discount,
                'discount_percentage' => $product->discount_percentage,
                'stock_quantity' => $product->stock_quantity,
                'sku' => $product->sku,
                'images' => $product->images,
                'video_url' => $product->video_url,
                'banner_image' => $product->banner_image,
                'banner_video_url' => $product->banner_video_url,
                'is_featured' => $product->is_featured,
                'categories' => $product->categories->map(fn($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ]),
            ],
        ]);
    }
}
