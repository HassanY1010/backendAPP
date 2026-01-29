<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login with phone number (creates user if doesn't exist)
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        // Revoke all tokens...
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        // Update login stats
        $user->last_login_at = now();
        $user->login_count = $user->login_count + 1;
        $user->save();

        // Log session
        \App\Models\UserSession::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'last_activity' => now(),
        ]);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function adminLogin(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if ($user->role !== 'admin' && $user->role !== 'moderator') {
            return response()->json(['message' => 'غير مصرح لك بالدخول كمدير'], 403);
        }

        $token = $user->createToken('admin_token', ['*'], now()->addYear())->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل دخول المدير بنجاح',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Guest login (creates temporary guest user)
     */
    public function guestLogin(Request $request)
    {
        // Create a unique guest user
        $guestPhone = 'guest_' . Str::uuid();

        $user = User::create([
            'phone' => $guestPhone,
            'name' => 'Guest User',
            'role' => 'guest',
        ]);

        // Create session record for guest
        UserSession::create([
            'user_id' => $user->id,
            'login_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $user->createToken('guest_token')->plainTextToken;

        return response()->json([
            'message' => 'Guest login successful',
            'data' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        // Update the active session with logout time
        $activeSession = UserSession::where('user_id', $request->user()->id)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first();

        if ($activeSession) {
            $activeSession->update([
                'logout_at' => now(),
            ]);
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function user(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * Search for users by name
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $users = User::where('name', 'like', '%' . $request->query('query') . '%')
            ->where('role', '!=', 'guest')
            ->select('id', 'name', 'avatar', 'is_active')
            ->take(10)
            ->get();

        return response()->json($users);
    }

    /**
     * Get user public profile with ads
     */
    public function publicProfile($id)
    {
        $user = User::withCount(['followers', 'following', 'ads'])
            ->findOrFail($id);

        return response()->json([
            'user' => new UserResource($user),
            'ads' => \App\Http\Resources\AdResource::collection(
                $user->ads()->with(['category', 'mainImage'])->latest()->take(20)->get()
            )
        ]);
    }
    /**
     * Send OTP Verification Code
     */
    public function sendOtp(Request $request)
    {
        // 1. Normalize phone (strip +, 00, spaces, then prepend +)
        $phone = $request->phone;
        $phone = str_replace(['+', ' ', '-'], '', $phone);
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }
        $phone = '+' . $phone; // Alawaeltec strictly requires the + sign

        // 2. Validate normalized phone (+ sign then 12 digits for Yemen/Saudi)
        $validator = \Illuminate\Support\Facades\Validator::make(['phone' => $phone], [
            'phone' => ['required', 'string', 'regex:/^\+(967[0-9]{9}|966[0-9]{9})$/'],
        ], [
            'phone.regex' => 'رقم الهاتف غير صحيح. يجب أن يبدأ بـ +967 (اليمن) أو +966 (السعودية) ويتكون من 13 خانة.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $code = (string) rand(100000, 999999);

        // Find or create user
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'User ' . substr($phone, -4)] // Placeholder name
        );

        // Update OTP
        $user->update([
            'otp' => $code,
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        // API Credentials from config
        $gatewayUrl = config('services.sms.gateway_url');
        $org = config('services.sms.org_name');
        $apiUser = config('services.sms.user_name');
        $pass = config('services.sms.password');
        $message = "رمز التحقق الخاص بك هو: $code";

        // Send SMS
        try {
            if (!$gatewayUrl) {
                \Log::warning("SMS Gateway URL is null. Skipping SMS send for $phone.");
                return response()->json([
                    'message' => 'OTP generated but SMS service is not configured',
                    'status' => 'generated_no_sms',
                ]);
            }

            $response = \Illuminate\Support\Facades\Http::get($gatewayUrl, [
                'orgName' => $org,
                'userName' => $apiUser,
                'password' => $pass,
                'mobileNo' => $phone,
                'text' => $message,
                'coding' => '2',
            ]);

            if ($response->successful()) {
                \Log::info("SMS Success for $phone: " . $response->body());
            } else {
                \Log::error("SMS Gateway Error for $phone (Status {$response->status()}): " . $response->body());
                return response()->json([
                    'message' => 'SMS gateway returned an error. Please contact support.',
                    'status' => 'gateway_error',
                    'detail' => $response->status()
                ], 502);
            }

        } catch (\Exception $e) {
            \Log::error("SMS Exception for $phone: " . $e->getMessage());
            return response()->json([
                'message' => 'Unable to connect to SMS service.',
                'status' => 'connection_failed'
            ], 503);
        }

        return response()->json([
            'message' => 'OTP sent successfully',
            'status' => 'sent',
            // 'debug_code' => $code // REMOVED FOR SECURITY
        ]);
    }

    /**
     * Verify OTP and Login
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'valid' => false
            ], 404);
        }

        if ($user->otp !== $request->code) {
            return response()->json([
                'message' => 'Invalid verification code',
                'valid' => false
            ], 400);
        }

        if ($user->otp_expires_at && now()->gt($user->otp_expires_at)) {
            return response()->json([
                'message' => 'Verification code expired',
                'valid' => false
            ], 400);
        }

        // Clear OTP
        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
            'is_active' => true,
            'phone_verified_at' => now(),
            'last_login_at' => now(),
            'login_count' => $user->login_count + 1,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'valid' => true,
            'data' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Get user's session history
     */
    public function sessions(Request $request)
    {
        $sessions = UserSession::where('user_id', $request->user()->id)
            ->orderBy('login_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'login_at' => $session->login_at->format('Y-m-d H:i:s'),
                    'logout_at' => $session->logout_at?->format('Y-m-d H:i:s'),
                    'ip_address' => $session->ip_address,
                    'device_type' => $session->device_type,
                    'duration' => $session->duration,
                    'is_active' => $session->isActive(),
                ];
            });

        return response()->json([
            'data' => $sessions
        ]);
    }
    /**
     * Get user dashboard stats and recent activity
     */
    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        $cacheKey = "user_dashboard_stats_{$user->id}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($user) {
            // 1. Calculate Stats efficiently
            $stats = [
                'total_ads' => $user->ads()->count(),
                'total_sold' => $user->ads()->where('status', 'sold')->count(),
                'total_buyers' => $user->receivedMessages()->distinct('sender_id')->count('sender_id'),
                'rating' => round(\App\Models\Review::where('reviewed_id', $user->id)->avg('rating') ?? 0, 1),
            ];

            // 2. Calculate Achievements
            $achievements = [
                [
                    'id' => 'active_seller',
                    'label' => 'بائع نشط',
                    'icon' => 'star_rounded',
                    'achieved' => $stats['total_ads'] > 0,
                ],
                [
                    'id' => 'top_rated',
                    'label' => 'تقييم 5 نجوم',
                    'icon' => 'thumb_up_rounded',
                    'achieved' => $stats['rating'] >= 4.5,
                ],
                [
                    'id' => 'fast_seller',
                    'label' => 'سرعة البيع',
                    'icon' => 'bolt_rounded',
                    'achieved' => $stats['total_sold'] > 0,
                ],
                [
                    'id' => 'featured_seller',
                    'label' => 'بائع متميز',
                    'icon' => 'diamond_rounded',
                    'achieved' => $stats['total_sold'] >= 5 && $stats['rating'] >= 4.0,
                ],
            ];

            // 3. Build Recent Activity
            $recentAds = $user->ads()->latest()->take(5)->get();
            $recentReviews = \App\Models\Review::where('reviewed_id', $user->id)
                ->with('reviewer:id,name')
                ->latest()
                ->take(5)
                ->get();

            $activities = $recentAds->flatMap(function ($ad) {
                $acts = [
                    [
                        'type' => 'ad_created',
                        'title' => 'إعلان جديد',
                        'description' => 'تم إضافة إعلان: ' . \Illuminate\Support\Str::limit($ad->title, 30),
                        'time' => $ad->created_at->diffForHumans(),
                        'timestamp' => $ad->created_at,
                        'icon_code' => 57415,
                        'color_hex' => '#10B981',
                    ]
                ];

                if ($ad->status === 'sold') {
                    $acts[] = [
                        'type' => 'ad_sold',
                        'title' => 'بيع ناجح',
                        'description' => 'تم بيع: ' . \Illuminate\Support\Str::limit($ad->title, 30),
                        'time' => $ad->updated_at->diffForHumans(),
                        'timestamp' => $ad->updated_at,
                        'icon_code' => 58784,
                        'color_hex' => '#8B5CF6',
                    ];
                }
                return $acts;
            })->concat($recentReviews->map(function ($review) {
                return [
                    'type' => 'review_received',
                    'title' => 'تقييم جديد',
                    'description' => 'حصلت على تقييم ' . $review->rating . ' نجوم من ' . ($review->reviewer->name ?? 'مستخدم'),
                    'time' => $review->created_at->diffForHumans(),
                    'timestamp' => $review->created_at,
                    'icon_code' => 57921,
                    'color_hex' => '#F59E0B',
                ];
            }));

            if ($activities->count() < 2) {
                $activities->push([
                    'type' => 'joined',
                    'title' => 'بداية الرحلة',
                    'description' => 'انضممت إلى التطبيق',
                    'time' => $user->created_at->diffForHumans(),
                    'timestamp' => $user->created_at,
                    'icon_code' => 58360,
                    'color_hex' => '#4A6DFF',
                ]);
            }

            $sortedActivities = $activities->sortByDesc('timestamp')->values()->take(5);

            return [
                'stats' => $stats,
                'achievements' => $achievements,
                'activities' => $sortedActivities
            ];
        });
    }
}
