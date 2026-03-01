<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Banner;
use App\Models\Cart;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Interstate\TruckingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Company Shop Controller - Ecommerce for Companies
 * 
 * Allows company/fleet owners to browse and purchase products
 * uploaded by Super Admin
 */
class CompanyShopController extends Controller
{
    /**
     * Get authenticated company
     */
    private function getAuthenticatedCompany(): ?TruckingCompany
    {
        $user = Auth::user();
        if (!$user) return null;
        return TruckingCompany::where('user_id', $user->id)->first();
    }

    /**
     * Display shop - browse products from Super Admin
     */
    public function index()
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        // Get banners for company shop - try company_store first, then fall back to 'shop' or 'both'
        $banners = Banner::active()
            ->where(function($q) {
                $q->where('position', 'company_store')
                  ->orWhere('position', 'shop')
                  ->orWhere('position', 'both');
            })
            ->orderBy('sort_order')
            ->get();
        
        // Fallback: If no banners, get any active banners as default
        if ($banners->isEmpty()) {
            $banners = Banner::active()
                ->orderBy('sort_order')
                ->take(3)
                ->get();
        }

        // Get products available for companies
        $products = Product::active()
            ->forAudience('company')
            ->when(request('category'), function($query) {
                $query->where('category', request('category'));
            })
            ->when(request('search'), function($query) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . request('search') . '%')
                      ->orWhere('description', 'like', '%' . request('search') . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        // Get categories
        $categories = Product::active()
            ->forAudience('company')
            ->select('category')
            ->distinct()
            ->pluck('category');

        return view('company.shop.index', compact('company', 'banners', 'products', 'categories'));
    }

    /**
     * Display shopping cart
     */
    public function cart()
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        return view('company.shop.cart', compact('company'));
    }

    /**
     * Add to cart (AJAX)
     */
    public function addToCart(Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        
        if (!$product->status) {
            return response()->json(['success' => false, 'message' => 'Product not available'], 400);
        }

        // Check if item already in cart
        $cartItem = Cart::forCompany($company->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $cartItem->update(['quantity' => $cartItem->quantity + $validated['quantity']]);
        } else {
            Cart::create([
                'company_id' => $company->id,
                'user_id' => auth()->id(),
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $product->final_price,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Added to cart successfully',
            'cart_count' => Cart::getCartCount($company->id),
        ]);
    }

    /**
     * Display checkout page
     */
    public function checkout()
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        return view('company.shop.checkout', compact('company'));
    }

    /**
     * Process checkout
     */
    public function processCheckout(Request $request)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        // Validate request
        $validated = $request->validate([
            'delivery_contact_name' => 'required|string|max:255',
            'delivery_contact_phone' => 'required|string|max:20',
            'delivery_address' => 'required|string',
            'delivery_notes' => 'nullable|string',
            'delivery_type' => 'required|in:metro,interstate',
            'payment_method' => 'required|in:flutterwave,bank_transfer',
        ]);

        // Get cart items
        $cartItems = Cart::with('product')
            ->forCompany($company->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('company.shop.cart')
                ->with('error', 'Your cart is empty');
        }

        // Calculate totals
        $subtotal = $cartItems->sum('total_price');
        $deliveryFee = $validated['delivery_type'] === 'metro'
            ? ($subtotal > 50000 ? 0 : 2500)
            : ($subtotal > 100000 ? 0 : 5000);
        $tax = $subtotal * 0.075; // 7.5% VAT
        $total = $subtotal + $deliveryFee + $tax;

        try {
            DB::beginTransaction();

            // Create order
            $order = ShopOrder::create([
                'order_number' => ShopOrder::generateOrderNumber(),
                'company_id' => $company->id,
                'user_id' => auth()->id(),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $total,
                'payment_method' => $validated['payment_method'],
                'payment_status' => ShopOrder::PAYMENT_PENDING,
                'status' => ShopOrder::STATUS_PENDING,
                'delivery_type' => $validated['delivery_type'],
                'delivery_status' => 'pending',
                'delivery_contact_name' => $validated['delivery_contact_name'],
                'delivery_contact_phone' => $validated['delivery_contact_phone'],
                'delivery_address' => $validated['delivery_address'],
                'delivery_notes' => $validated['delivery_notes'],
            ]);

            // Create order items
            foreach ($cartItems as $cartItem) {
                ShopOrderItem::create([
                    'shop_order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product ? $cartItem->product->name : 'Unknown Product',
                    'product_sku' => $cartItem->product ? $cartItem->product->sku : null,
                    'product_image' => ($cartItem->product && !empty($cartItem->product->images)) ? $cartItem->product->images[0] : null,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'total_price' => $cartItem->total_price,
                ]);

                // Update product stock
                if ($cartItem->product) {
                    $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
                }
            }

            // Clear cart
            Cart::clearCart($company->id);

            DB::commit();

            // Redirect based on payment method
            if ($validated['payment_method'] === 'flutterwave') {
                // Redirect to payment gateway (would integrate with actual payment)
                return redirect()->route('company.shop.orders')
                    ->with('success', 'Order placed successfully! Order #: ' . $order->order_number);
            } else {
                // Bank transfer - show instructions
                return redirect()->route('company.shop.orders')
                    ->with('success', 'Order placed! Please complete bank transfer. Order #: ' . $order->order_number);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to place order: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display orders
     */
    public function orders()
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $orders = ShopOrder::where('company_id', $company->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('company.shop.orders', compact('company', 'orders'));
    }

    /**
     * Display order detail
     */
    public function orderDetail($id)
    {
        $company = $this->getAuthenticatedCompany();
        if (!$company) {
            return redirect()->back()->with('error', 'Company not found');
        }

        $order = ShopOrder::where('company_id', $company->id)
            ->findOrFail($id);

        return view('company.shop.order_detail', compact('company', 'order'));
    }
}
