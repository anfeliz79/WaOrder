<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = SurveyResponse::with([
            'order:id,order_number,total,created_at',
            'customer:id,name,phone',
        ])
        ->where('completed', true)
        ->latest();

        // Filters
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('food_quality')) {
            $query->where('food_quality', $request->food_quality);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        $reviews = $query->paginate(20);

        // Stats
        $stats = SurveyResponse::where('completed', true)->select([
            DB::raw('COUNT(*) as total_reviews'),
            DB::raw('ROUND(AVG(rating), 1) as avg_rating'),
            DB::raw('SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_count'),
            DB::raw('SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative_count'),
            DB::raw('SUM(CASE WHEN comment IS NOT NULL AND comment != "" THEN 1 ELSE 0 END) as with_comments'),
        ])->first();

        // Rating distribution
        $distribution = SurveyResponse::where('completed', true)
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Food quality distribution
        $qualityDistribution = SurveyResponse::where('completed', true)
            ->whereNotNull('food_quality')
            ->select('food_quality', DB::raw('COUNT(*) as count'))
            ->groupBy('food_quality')
            ->pluck('count', 'food_quality')
            ->toArray();

        if ($request->wantsJson()) {
            return response()->json(compact('reviews', 'stats', 'distribution', 'qualityDistribution'));
        }

        return Inertia::render('Reviews/Index', [
            'reviews' => $reviews,
            'stats' => $stats,
            'distribution' => $distribution,
            'qualityDistribution' => $qualityDistribution,
            'filters' => $request->only(['rating', 'food_quality', 'search']),
        ]);
    }
}
