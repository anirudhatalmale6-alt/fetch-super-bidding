<?php

namespace App\Http\Controllers\Web\Company;

use App\Http\Controllers\Web\BaseController;
use App\Models\CompanyBanner;
use App\Models\Banner;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BannerController extends BaseController
{
    /**
     * Display banners management page
     */
    public function index()
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        // Get company-specific banners
        $banners = CompanyBanner::where('trucking_company_id', $company->id)
            ->orderBy('position')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('company.banners.index', compact('banners', 'company'));
    }

    /**
     * Display active banners for company dashboard
     * This is used to show banners in the dashboard layout
     */
    public function display()
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $now = Carbon::now();

        // Get active company banners
        $companyBanners = CompanyBanner::where('trucking_company_id', $company->id)
            ->where('is_active', true)
            ->where(function($query) use ($now) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function($query) use ($now) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->orderBy('position')
            ->get();

        // Also get system banners for company_dashboard placement
        $systemBanners = Banner::where('status', 'active')
            ->where('placement', 'company_dashboard')
            ->where(function($query) use ($now) {
                $query->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->where(function($query) use ($now) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->orderBy('position')
            ->get();

        // Merge and return view
        return view('company.partials.banners', compact('companyBanners', 'systemBanners'));
    }

    /**
     * Get banners as JSON for AJAX loading
     */
    public function json()
    {
        $owner = auth()->user()->owner;
        $company = $owner->truckingCompany;

        $now = Carbon::now();

        $banners = CompanyBanner::where('trucking_company_id', $company->id)
            ->where('is_active', true)
            ->where(function($query) use ($now) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function($query) use ($now) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->orderBy('position')
            ->get()
            ->map(function($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'image_url' => $banner->image_url,
                    'video_url' => $banner->video_url,
                    'link' => $banner->link,
                    'button_text' => $banner->button_text,
                    'position' => $banner->position,
                ];
            });

        return response()->json(['banners' => $banners]);
    }
}
