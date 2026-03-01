<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\Interstate\TruckingCompany;
use App\Models\Interstate\InterstateBid;
use App\Models\ShopOrder;
use App\Models\Banner;
use App\Models\Request\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends BaseController
{
    /**
     * Company Dashboard Overview
     */
    public function index()
    {
        // ── Safe auth resolution (use web guard explicitly) ──
        $user    = auth('web')->user();
        $owner   = $user?->owner;
        $company = $owner?->truckingCompany
                ?? TruckingCompany::where('user_id', $user?->id)->first();

        // ── Stats (single aggregated query where possible) ────
        $stats = $this->getStats($company);

        // ── Recent Activity ───────────────────────────────────
        $recentBids      = $this->getRecentBids($company, 5);
        $recentShipments = $this->getRecentShipments($company, 5);
        $recentOrders    = $this->getRecentShopOrders($company, 5);

        // ── Charts (single GROUP BY query — no N+1) ───────────
        $monthlyRevenue  = $this->getMonthlyRevenueData($company);
        $bidSuccessRate  = $this->getBidSuccessRate($company);

        // ── Banners ───────────────────────────────────────────
        $banners = $this->getDashboardBanners();

        return view('company.dashboard.index', compact(
            'stats',
            'recentBids',
            'recentShipments',
            'recentOrders',
            'monthlyRevenue',
            'bidSuccessRate',
            'banners',
            'owner',
            'company'
        ));
    }

    // ─────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────

    private function getStats(?TruckingCompany $company): array
    {
        if (!$company) {
            return array_fill_keys([
                'active_bids','won_bids','active_shipments',
                'completed_deliveries','shop_orders','pending_approvals',
                'total_revenue','rating',
            ], 0);
        }

        // Bids: one query, group by status
        $bidCounts = InterstateBid::where('trucking_company_id', $company->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Requests: one query, group by inspection_status
        $requestCounts = Request::where('trucking_company_id', $company->id)
            ->where('delivery_type', 'interstate')
            ->select('inspection_status', DB::raw('count(*) as total'))
            ->groupBy('inspection_status')
            ->pluck('total', 'inspection_status');

        // Revenue (use total_bid_amount since final_cost doesn't exist)
        $totalRevenue = InterstateBid::where('trucking_company_id', $company->id)
            ->where('status', 'accepted')
            ->sum('total_bid_amount') ?: 0;

        // Shop orders for this company
        $shopOrders = ShopOrder::where('company_id', $company->id)->count();

        return [
            'active_bids'          => $bidCounts->get('pending', 0),
            'won_bids'             => $bidCounts->get('accepted', 0),
            'active_shipments'     => $requestCounts->except(['inspection_completed', 'cancelled'])->sum(),
            'completed_deliveries' => $requestCounts->get('inspection_completed', 0),
            'shop_orders'          => $shopOrders,
            'pending_approvals'    => $requestCounts->get('awaiting_user_approval', 0),
            'total_revenue'        => $totalRevenue,
            'rating'               => $company->rating ?? 0,
        ];
    }

    private function getRecentBids(?TruckingCompany $company, int $limit = 5)
    {
        if (!$company) return collect();
        return InterstateBid::where('trucking_company_id', $company->id)
            ->with('request')
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function getRecentShipments(?TruckingCompany $company, int $limit = 5)
    {
        if (!$company) return collect();
        return Request::where('trucking_company_id', $company->id)
            ->where('delivery_type', 'interstate')
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function getRecentShopOrders(?TruckingCompany $company, int $limit = 5)
    {
        if (!$company) return collect();
        return ShopOrder::where('company_id', $company->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function getMonthlyRevenueData(?TruckingCompany $company): array
    {
        if (!$company) return [];

        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $rows = InterstateBid::where('trucking_company_id', $company->id)
            ->where('status', 'accepted')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->select(
                DB::raw('YEAR(created_at) as yr'),
                DB::raw('MONTH(created_at) as mo'),
                DB::raw('COALESCE(SUM(total_bid_amount), 0) as revenue')
            )
            ->groupBy('yr', 'mo')
            ->orderBy('yr')
            ->orderBy('mo')
            ->get()
            ->keyBy(fn($r) => "{$r->yr}-{$r->mo}");

        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $key   = $month->year . '-' . $month->month;
            $data[] = [
                'month'   => $month->format('M Y'),
                'revenue' => $rows->get($key)?->revenue ?? 0,
            ];
        }

        return $data;
    }

    private function getBidSuccessRate(?TruckingCompany $company): float|int
    {
        if (!$company) return 0;

        $counts = InterstateBid::where('trucking_company_id', $company->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = $counts->sum();
        $won   = $counts->get('accepted', 0);

        return $total > 0 ? round(($won / $total) * 100, 1) : 0;
    }

    private function getDashboardBanners()
    {
        try {
            return Banner::active()
                ->where(function ($q) {
                    $q->where('position', 'company_dashboard')
                      ->orWhere('position', 'company_store')
                      ->orWhere('position', 'both')
                      ->orWhere('position', 'all');
                })
                ->orderBy('sort_order')
                ->get();
        } catch (\Exception $e) {
            Log::error('DashboardController::getDashboardBanners failed: ' . $e->getMessage());
            return collect();
        }
    }
}
