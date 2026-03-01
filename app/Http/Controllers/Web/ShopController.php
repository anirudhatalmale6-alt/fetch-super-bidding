<?php

namespace App\Http\Controllers\Web;

use App\Models\Product;
use App\Models\Banner;
use App\Models\Cms\FrontPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopController extends BaseController
{
    /**
     * Get upload path for CMS files
     */
    public function uploadPath()
    {
        return config('base.cms.upload.web-picture.path');
    }
    
    /**
     * Display the main shop page
     */
    public function index()
    {
        $pageTitle = 'Shop';
        
        // Get footer data (needed for web_footer layout)
        $data = FrontPage::first();
        $p = Storage::disk(env('FILESYSTEM_DRIVER'))->url(file_path($this->uploadPath(), ''));
        
        // Get banners from Banner table (Super Admin controlled slider)
        $shopBanners = Banner::active()
            ->forShop()
            ->orderBy('sort_order')
            ->get();
        
        // Fallback: Get banner from featured products with banner if no banners configured
        $bannerProduct = null;
        if ($shopBanners->isEmpty()) {
            $bannerProduct = Product::active()
                ->where(function ($query) {
                    $query->whereNotNull('banner_image')
                          ->orWhereNotNull('banner_video_url');
                })
                ->latest()
                ->first();
        }
        
        // Get featured products for carousel
        $featuredProducts = Product::active()
            ->forAudience('user')
            ->featured()
            ->take(8)
            ->get();
        
        // Get all products paginated
        $allProducts = Product::active()
            ->forAudience('user')
            ->latest()
            ->paginate(12);
        
        // Get categories
        $categories = Product::active()
            ->forAudience('user')
            ->select('category')
            ->distinct()
            ->pluck('category');
        
        // Get new arrivals
        $newArrivals = Product::active()
            ->forAudience('user')
            ->latest()
            ->take(4)
            ->get();
        
        return view('webfront.shop', compact(
            'pageTitle',
            'shopBanners',
            'bannerProduct',
            'featuredProducts',
            'allProducts',
            'categories',
            'newArrivals',
            'data',
            'p'
        ));
    }

    /**
     * Fetch products via AJAX
     */
    public function fetchProducts(Request $request)
    {
        $query = Product::active()->forAudience('user');

        // Filter by category
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        // Filter by price range
        if ($request->has('min_price') && !empty($request->min_price)) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price') && !empty($request->max_price)) {
            $query->where('price', '<=', $request->max_price);
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
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            default:
                $query->latest();
        }

        $products = $query->paginate(12);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $products,
                'html' => view('webfront._shop_products', compact('products'))->render()
            ]);
        }

        return view('webfront.shop', compact('products'));
    }

    /**
     * Show product details
     */
    public function show($slug)
    {
        $product = Product::active()
            ->forAudience('user')
            ->where('id', $slug)
            ->orWhere('sku', $slug)
            ->firstOrFail();

        $pageTitle = $product->name;
        
        // Get related products
        $relatedProducts = Product::active()
            ->forAudience('user')
            ->where('category', $product->category)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();
        
        // Get more from seller/category
        $moreProducts = Product::active()
            ->forAudience('user')
            ->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->take(4)
            ->get();

        return view('webfront.shop_details', compact(
            'product',
            'pageTitle',
            'relatedProducts',
            'moreProducts'
        ));
    }

    /**
     * Get products by category
     */
    public function category($category)
    {
        $pageTitle = ucfirst($category) . ' - Shop';
        
        $products = Product::active()
            ->forAudience('user')
            ->where('category', $category)
            ->latest()
            ->paginate(12);
        
        $categories = Product::active()
            ->forAudience('user')
            ->select('category')
            ->distinct()
            ->pluck('category');

        return view('webfront.shop_category', compact('products', 'category', 'categories', 'pageTitle'));
    }

    /**
     * Search products
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $pageTitle = 'Search Results: ' . $query;
        
        $products = Product::active()
            ->forAudience('user')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('category', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->latest()
            ->paginate(12);

        return view('webfront.shop_search', compact('products', 'query', 'pageTitle'));
    }

    /**
     * Get shop banner
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
