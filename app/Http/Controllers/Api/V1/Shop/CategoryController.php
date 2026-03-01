<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ProductCategory;

class CategoryController extends BaseController
{
    /**
     * List all active categories
     */
    public function index()
    {
        $categories = ProductCategory::withCount(['products' => function ($query) {
                $query->where('status', true);
            }])
            ->where('is_active', true)
            ->ordered()
            ->get();

        return $this->respondSuccess([
            'categories' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image' => $category->image_url,
                    'product_count' => $category->product_count,
                ];
            }),
        ]);
    }

    /**
     * Get single category with products
     */
    public function show($id)
    {
        $category = ProductCategory::with(['products' => function ($query) {
                $query->where('status', true)
                      ->where(function ($q) {
                          $q->where('target_audience', 'all')
                            ->orWhere('target_audience', 'company');
                      });
            }])
            ->where('is_active', true)
            ->findOrFail($id);

        return $this->respondSuccess([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image_url,
                'products' => $category->products->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'discount_price' => $product->discount_price,
                    'final_price' => $product->final_price,
                    'first_image' => $product->first_image,
                    'stock_quantity' => $product->stock_quantity,
                ]),
            ],
        ]);
    }
}
