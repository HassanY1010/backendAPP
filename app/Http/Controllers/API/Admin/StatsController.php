<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ad;
use App\Models\Report;
use App\Models\Category;
use App\Models\UserSession;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index()
    {
        return response()->json([
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
            'active_sessions' => UserSession::whereNull('logout_at')->count(),
            'total_sessions' => UserSession::count(),
            'sessions_today' => UserSession::whereDate('login_at', today())->count(),
        ]);
    }
}
