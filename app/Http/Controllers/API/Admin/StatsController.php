<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ad;
use App\Models\Report;
use App\Models\Category;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    public function index()
    {
        try {
            $stats = Cache::remember('admin_dashboard_stats', 300, function () {
                return [
                // Users Statistics
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'admin_users' => User::where('role', 'admin')->count(),
                'regular_users' => User::where('role', 'user')->count(),
                'guest_users' => User::where('role', 'guest')->count(),
                'new_users_today' => User::whereDate('created_at', today())->count(),
                'new_users_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                'new_users_month' => User::where('created_at', '>=', now()->subMonth())->count(),

                // Ads Statistics
                'total_ads' => Ad::count(),
                'active_ads' => Ad::where('status', 'active')->count(),
                'pending_ads' => Ad::where('status', 'pending')->count(),
                'rejected_ads' => Ad::where('status', 'rejected')->count(),
                'sold_ads' => Ad::where('status', 'sold')->count(),
                'expired_ads' => Ad::where('status', 'expired')->count(),
                'new_ads_today' => Ad::whereDate('created_at', today())->count(),
                'new_ads_week' => Ad::where('created_at', '>=', now()->subWeek())->count(),
                'new_ads_month' => Ad::where('created_at', '>=', now()->subMonth())->count(),

                // Reports Statistics
                'total_reports' => Report::count(),
                'pending_reports' => Report::where('status', 'pending')->count(),
                'resolved_reports' => Report::where('status', 'resolved')->count(),
                'new_reports_today' => Report::whereDate('created_at', today())->count(),

                // Categories Statistics
                'total_categories' => Category::count(),
                'active_categories' => Category::where('is_active', true)->count(),
                'parent_categories' => Category::whereNull('parent_id')->count(),

                // Sessions Statistics
                'active_sessions' => UserSession::count() > 0 ?UserSession::whereNull('logout_at')->count() : 0,
                'total_sessions' => UserSession::count(),
                ];
            });

            return response()->json($stats);
        }
        catch (\Exception $e) {
            // Return zeros if tables are missing to avoid crashing the frontend
            return response()->json([
                'total_users' => 0,
                'active_users' => 0,
                'inactive_users' => 0,
                'admin_users' => 0,
                'regular_users' => 0,
                'guest_users' => 0,
                'new_users_today' => 0,
                'new_users_week' => 0,
                'new_users_month' => 0,
                'total_ads' => 0,
                'active_ads' => 0,
                'pending_ads' => 0,
                'rejected_ads' => 0,
                'sold_ads' => 0,
                'expired_ads' => 0,
                'new_ads_today' => 0,
                'new_ads_week' => 0,
                'new_ads_month' => 0,
                'total_reports' => 0,
                'pending_reports' => 0,
                'resolved_reports' => 0,
                'new_reports_today' => 0,
                'total_categories' => 0,
                'active_categories' => 0,
                'parent_categories' => 0,
                'active_sessions' => 0,
                'total_sessions' => 0,
                'error' => 'Some tables are missing, please run migrations.'
            ]);
        }
    }
}
