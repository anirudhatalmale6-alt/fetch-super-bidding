<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends BaseController
{
    /**
     * Get company cart
     */
    public function index(Request $request)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        $cartItems = Cart::with('product')   // eager-load prevents N+1
            ->forCompany($company->id)
            ->get();

        // Compute summary from already-loaded collection — no extra DB queries
        $subtotal   = $cartItems->sum('total_price');
        $itemCount  = $cartItems->sum('quantity');

        return $this->respondSuccess([
            'items' => $cartItems->map(fn($item) => [
                'id'            => $item->id,
                'product_id'    => $item->product_id,
                'product_name'  => optional($item->product)->name,
                'product_image' => optional($item->product)->first_image,
                'quantity'      => $item->quantity,
                'unit_price'    => $item->unit_price,
                'total_price'   => $item->total_price,
            ]),
            'summary' => [
                'item_count' => $itemCount,
                'subtotal'   => $subtotal,
                'tax'        => 0,
                'total'      => $subtotal,
            ],
        ]);
    }

    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        $product = Product::findOrFail($request->input('product_id'));

        if (!$product->status) {
            return $this->respondError('Product is not available', 400);
        }

        // Check if item already in cart
        $cartItem = Cart::forCompany($company->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            // Update quantity
            $cartItem->update([
                'quantity' => $cartItem->quantity + $request->input('quantity'),
            ]);
        } else {
            // Create new cart item
            Cart::create([
                'company_id' => $company->id,
                'user_id' => auth()->id(),
                'product_id' => $product->id,
                'quantity' => $request->input('quantity'),
                'unit_price' => $product->final_price,
            ]);
        }

        return $this->respondSuccess([
            'cart_count' => Cart::getCartCount($company->id),
            'cart_total' => Cart::getCartTotal($company->id),
        ], 'Item added to cart');
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->respondWithValidationErrors($validator);
        }

        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        $cartItem = Cart::forCompany($company->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->update([
            'quantity' => $request->input('quantity'),
        ]);

        return $this->respondSuccess([
            'cart_count' => Cart::getCartCount($company->id),
            'cart_total' => Cart::getCartTotal($company->id),
        ], 'Cart updated');
    }

    /**
     * Remove item from cart
     */
    public function destroy($id)
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        $cartItem = Cart::forCompany($company->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->delete();

        return $this->respondSuccess([
            'cart_count' => Cart::getCartCount($company->id),
            'cart_total' => Cart::getCartTotal($company->id),
        ], 'Item removed from cart');
    }

    /**
     * Clear entire cart
     */
    public function clear()
    {
        $company = auth()->user()->truckingCompany;
        
        if (!$company) {
            return $this->respondError('Company not found', 404);
        }

        Cart::clearCart($company->id);

        return $this->respondSuccess([], 'Cart cleared');
    }

    protected function respondWithValidationErrors($validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }
}
