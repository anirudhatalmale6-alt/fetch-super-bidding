<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Web\BaseController;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends BaseController
{
    /**
     * Display a listing of banners
     */
    public function index()
    {
        $pageTitle = 'Banner Management';
        $main_menu = 'market';
        $sub_menu = 'banners';
        $page = 'banners';
        return view('admin.banners.index', compact('pageTitle', 'main_menu', 'sub_menu', 'page'));
    }

    /**
     * Fetch banners for datatable
     */
    public function fetch(Request $request)
    {
        $query = Banner::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $banners = $query->orderBy('sort_order', 'asc')->latest()->paginate(15);

        return view('admin.banners._banners', compact('banners'));
    }

    /**
     * Show the form for creating a new banner
     */
    public function create()
    {
        $pageTitle = 'Add New Banner';
        $main_menu = 'market';
        $sub_menu = 'banners';
        $page = 'banners';
        $positions = $this->getPositions();
        return view('admin.banners.create', compact('pageTitle', 'main_menu', 'sub_menu', 'page', 'positions'));
    }

    /**
     * Store a newly created banner
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'video' => 'nullable|file|mimes:mp4,webm,ogg|max:51200',
            'video_url' => 'nullable|url|max:500',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:255',
            'position' => 'required|in:shop,company_store,company_dashboard,both,all',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'media_type' => 'nullable|in:image,video',
        ]);

        // Handle banner image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('banners', 'public');
        }

        // Handle video upload
        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('banners/videos', 'public');
        }

        $banner = Banner::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'image' => $imagePath,
            'video' => $videoPath,
            'video_url' => $validated['video_url'],
            'media_type' => $request->input('media_type', ($videoPath || $validated['video_url']) ? 'video' : 'image'),
            'button_text' => $validated['button_text'],
            'button_link' => $validated['button_link'],
            'position' => $validated['position'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return redirect()->route('banners.index')->with('success', 'Banner created successfully!');
    }

    /**
     * Show the form for editing the specified banner
     */
    public function edit(Banner $banner)
    {
        $pageTitle = 'Edit Banner';
        $main_menu = 'market';
        $sub_menu = 'banners';
        $page = 'banners';
        $positions = $this->getPositions();
        return view('admin.banners.edit', compact('banner', 'pageTitle', 'main_menu', 'sub_menu', 'page', 'positions'));
    }

    /**
     * Update the specified banner
     */
    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'video' => 'nullable|file|mimes:mp4,webm,ogg|max:51200',
            'video_url' => 'nullable|url|max:500',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:255',
            'position' => 'required|in:shop,company_store,company_dashboard,both,all',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'media_type' => 'nullable|in:image,video',
        ]);

        // Handle banner image
        if ($request->hasFile('image')) {
            // Delete old image
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $imagePath = $request->file('image')->store('banners', 'public');
            $banner->image = $imagePath;
        }

        // Handle video upload
        if ($request->hasFile('video')) {
            // Delete old video
            if ($banner->video) {
                Storage::disk('public')->delete($banner->video);
            }
            $videoPath = $request->file('video')->store('banners/videos', 'public');
            $banner->video = $videoPath;
        }

        $banner->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'video_url' => $validated['video_url'],
            'media_type' => $request->input('media_type', ($banner->video || $validated['video_url']) ? 'video' : 'image'),
            'button_text' => $validated['button_text'],
            'button_link' => $validated['button_link'],
            'position' => $validated['position'],
            'sort_order' => $validated['sort_order'] ?? $banner->sort_order,
            'is_active' => $request->boolean('is_active', true),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return redirect()->route('banners.index')->with('success', 'Banner updated successfully!');
    }

    /**
     * Remove the specified banner
     */
    public function destroy(Banner $banner)
    {
        // Delete associated image
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return response()->json(['success' => 'Banner deleted successfully!']);
    }

    /**
     * Toggle banner status
     */
    public function toggleStatus(Banner $banner)
    {
        $banner->is_active = !$banner->is_active;
        $banner->save();

        return response()->json([
            'success' => true,
            'message' => 'Banner status updated successfully!',
            'status' => $banner->is_active
        ]);
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*.id' => 'required|exists:banners,id',
            'banners.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->banners as $bannerData) {
            Banner::where('id', $bannerData['id'])->update(['sort_order' => $bannerData['sort_order']]);
        }

        return response()->json(['success' => true, 'message' => 'Sort order updated!']);
    }

    /**
     * Get positions array
     */
    private function getPositions()
    {
        return [
            'shop' => 'Shop Page Only',
            'company_store' => 'Company Store Only',
            'company_dashboard' => 'Company Dashboard Only',
            'both' => 'Shop & Company Store',
            'all' => 'All Pages',
        ];
    }
}
