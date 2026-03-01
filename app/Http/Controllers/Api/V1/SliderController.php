<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SliderController extends BaseController
{
    /**
     * Get active sliders for a specific position
     */
    public function index(Request $request)
    {
        $position = $request->input('position', 'homepage');
        
        // Validate position
        $validPositions = ['homepage', 'shop', 'company_dashboard'];
        if (!in_array($position, $validPositions)) {
            return $this->respondError('Invalid position', 400);
        }

        // Cache sliders for 1 hour
        $cacheKey = "sliders:{$position}";
        $sliders = Cache::remember($cacheKey, 3600, function () use ($position) {
            return Banner::active()
                ->where(function ($query) use ($position) {
                    $query->where('position', $position)
                          ->orWhere('position', 'both');
                })
                ->orderBy('sort_order', 'asc')
                ->get();
        });

        return $this->respondSuccess([
            'sliders' => $sliders->map(function ($slider) {
                return [
                    'id' => $slider->id,
                    'title' => $slider->title,
                    'description' => $slider->description,
                    'image' => $slider->image_url,
                    'video_url' => $slider->video_url,
                    'button_text' => $slider->button_text,
                    'button_link' => $slider->button_link,
                    'background_color' => $slider->background_color,
                    'text_position' => $slider->text_position,
                    'display_duration' => $slider->display_duration,
                    'transition_effect' => $slider->transition_effect,
                    'auto_play' => $slider->auto_play,
                    'sort_order' => $slider->sort_order,
                ];
            }),
        ]);
    }

    /**
     * Get sliders for homepage
     */
    public function homepage()
    {
        return $this->getSlidersByPosition('homepage');
    }

    /**
     * Get sliders for shop page
     */
    public function shop()
    {
        return $this->getSlidersByPosition('shop');
    }

    /**
     * Get sliders for company dashboard
     */
    public function companyDashboard()
    {
        return $this->getSlidersByPosition('company_dashboard');
    }

    /**
     * Helper method to get sliders by position
     */
    private function getSlidersByPosition(string $position)
    {
        $cacheKey = "sliders:{$position}";
        
        $sliders = Cache::remember($cacheKey, 3600, function () use ($position) {
            return Banner::active()
                ->where(function ($query) use ($position) {
                    $query->where('position', $position)
                          ->orWhere('position', 'both');
                })
                ->orderBy('sort_order', 'asc')
                ->get();
        });

        return $this->respondSuccess([
            'position' => $position,
            'sliders' => $sliders->map(function ($slider) {
                return [
                    'id' => $slider->id,
                    'title' => $slider->title,
                    'description' => $slider->description,
                    'image' => $slider->image_url,
                    'video_url' => $slider->video_url,
                    'button_text' => $slider->button_text,
                    'button_link' => $slider->button_link,
                    'background_color' => $slider->background_color,
                    'text_position' => $slider->text_position,
                    'display_duration' => $slider->display_duration,
                    'transition_effect' => $slider->transition_effect,
                    'auto_play' => $slider->auto_play,
                    'sort_order' => $slider->sort_order,
                ];
            }),
        ]);
    }
}
