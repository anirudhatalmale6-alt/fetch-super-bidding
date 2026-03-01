<?php

namespace App\Http\Controllers\Web\Admin\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Product;
use Illuminate\Http\Request;

class StoreController extends BaseController
{
    /**
     * Display the store page for company/fleet
     */
    public function index()
    {
        $pageTitle = 'Equipment Store';
        
        // Get banner from any featured product or the first product with banner
        $bannerProduct = Product::active()
            ->whereNotNull('banner_image')
            ->orWhereNotNull('banner_video_url')
            ->first();
        
        // Get products available for company and all users
        $featuredProducts = Product::active()
            ->forAudience('company')
            ->featured()
            ->take(6)
            ->get();
        
        $allProducts = Product::active()
            ->forAudience('company')
            ->latest()
            ->paginate(12);
        
        $categories = Product::active()
            ->forAudience('company')
            ->select('category')
            ->distinct()
            ->pluck('category');
        
        return view('admin.company.store.index', compact(
            'pageTitle', 
            'bannerProduct', 
            'featuredProducts', 
            'allProducts',
            'categories'
        ));
    }

    /**
     * Fetch products for AJAX requests
     */
    public function fetchProducts(Request $request)
    {
        $query = Product::active()->forAudience('company');

        // Filter by category
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        // Filter by search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            default:
                $query->latest();
        }

        $products = $query->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $products,
            'html' => view('admin.company.store._products_grid', compact('products'))->render()
        ]);
    }

    /**
     * Show product details
     */
    public function show(Product $product)
    {
        // Check if product is available for company
        if (!$product->status || ($product->target_audience !== 'all' && $product->target_audience !== 'company')) {
            abort(404);
        }

        $pageTitle = $product->name;
        
        // Get related products
        $relatedProducts = Product::active()
            ->forAudience('company')
            ->where('category', $product->category)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('admin.company.store.show', compact('product', 'pageTitle', 'relatedProducts'));
    }

    /**
     * Get store banner
     */
    public function getBanner()
    {
        $banner = Product::active()
            ->where(function ($query) {
                $query->whereNotNull('banner_image')
                      ->orWhereNotNull('banner_video_url');
            })
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'banner' => $banner
        ]);
    }
}
