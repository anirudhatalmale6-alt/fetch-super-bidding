<?php

namespace App\Http\Middleware;

use App\Models\Interstate\TruckingCompany;
use Closure;
use Illuminate\Http\Request;

class VerifyCompanyAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Get company from user
        $company = TruckingCompany::where('user_id', $user->id)->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'You are not associated with a trucking company',
            ], 403);
        }

        // Attach company to request
        $request->attributes->set('trucking_company', $company);

        // If accessing a specific request, verify company has access
        $interstateRequest = $request->route('request')
            ?? $request->route('interstateRequest')
            ?? $request->input('request_id');

        if ($interstateRequest) {
            // If it's just an ID, look up the request
            if (is_string($interstateRequest)) {
                $interstateRequest = \App\Models\Request\Request::find($interstateRequest);
            }

            if ($interstateRequest && $interstateRequest->delivery_mode === 'interstate') {
                // Check if company is assigned to this request
                if ($interstateRequest->trucking_company_id !== $company->id) {
                    // Check if company has placed a bid on this request
                    $hasBid = \App\Models\Interstate\InterstateBid::forRequest($interstateRequest->id)
                        ->byCompany($company->id)
                        ->exists();

                    if (!$hasBid) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You do not have access to this request',
                        ], 403);
                    }
                }
            }
        }

        return $next($request);
    }
}
