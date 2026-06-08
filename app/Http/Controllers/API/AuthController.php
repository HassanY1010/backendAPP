<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdResource;
use App\Http\Resources\UserResource;
use App\Models\Review;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const USER_TOKEN_TTL_DAYS = 30;

    private const ADMIN_TOKEN_TTL_HOURS = 8;

    private const OTP_MAX_ATTEMPTS = 5;

    private const OTP_LOCK_MINUTES = 15;

    private const SMS_GATEWAY_TIMEOUT_SECONDS = 8;

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '9670')) {
            $phone = '967'.substr($phone, 4);
        } elseif (str_starts_with($phone, '9660')) {
            $phone = '966'.substr($phone, 4);
        }

        return '+'.$phone;
    }

    private function phoneForSmsGateway(string $normalizedPhone): string
    {
        return ltrim($normalizedPhone, '+');
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) <= 4) {
            return '****';
        }

        return '+'.substr($digits, 0, 3)
            .str_repeat('*', max(strlen($digits) - 7, 0))
            .substr($digits, -4);
    }

    private function parseSmsGatewayResponse(string $body): array
    {
        $body = trim($body);

        if (preg_match('/(?:^|\s)(\d+)\s*:\s*([A-Z_]+)(?::\s*([^\s:]+))?/i', $body, $matches)) {
            $code = $matches[1];
            $message = strtoupper($matches[2]);

            return [
                'accepted' => $code === '0' && $message === 'SUCCESS',
                'code' => $code,
                'message' => $message,
                'message_id' => $matches[3] ?? null,
                'raw' => $body,
            ];
        }

        return [
            'accepted' => false,
            'code' => null,
            'message' => 'UNPARSEABLE_RESPONSE',
            'message_id' => null,
            'raw' => $body,
        ];
    }

    private function redactSmsLogText(string $text): string
    {
        return preg_replace(
            '/([?&](?:orgName|userName|password|mobileNo|text)=)[^&\s]+/i',
            '$1[redacted]',
            $text
        ) ?? 'redacted';
    }

    private function smsFailureStatus(array $gatewayResult): int
    {
        return match ($gatewayResult['code'] ?? null) {
            '2', '3' => 422,
            default => 503,
        };
    }

    private function smsFailureMessage(array $gatewayResult): string
    {
        return match ($gatewayResult['code'] ?? null) {
            '2' => 'رقم الهاتف غير صحيح. يرجى التأكد من عدد الأرقام ثم المحاولة مرة أخرى.',
            '3' => 'رقم الهاتف غير مدعوم من مزود الرسائل الحالي.',
            default => 'تعذر إرسال رمز التحقق حالياً، يرجى المحاولة لاحقاً.',
        };
    }

    private function clearPendingOtp(User $user): void
    {
        $user->forceFill([
            'otp' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_locked_until' => null,
        ])->save();
    }

    private function recordSession(User $user, Request $request): void
    {
        UserSession::create([
            'user_id' => $user->id,
            'login_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function loginPayload(User $user, string $token, string $message, bool $includeData = false): array
    {
        $payload = [
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ];

        if ($includeData) {
            $payload['data'] = new UserResource($user);
        }

        return $payload;
    }

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

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is disabled'], 403);
        }

        $token = $user
            ->createToken('auth_token', ['user'], now()->addDays(self::USER_TOKEN_TTL_DAYS))
            ->plainTextToken;

        // Update login stats
        $user->last_login_at = now();
        $user->login_count = ($user->login_count ?? 0) + 1;
        $user->save();

        // Log session
        $this->recordSession($user, $request);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'data' => new UserResource($user),
        ]);
    }

    public function adminLogin(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح لك بالدخول كمدير'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is disabled'], 403);
        }

        $token = $user
            ->createToken('admin_token', ['admin'], now()->addHours((int) env('ADMIN_TOKEN_TTL_HOURS', self::ADMIN_TOKEN_TTL_HOURS)))
            ->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'login_count' => ($user->login_count ?? 0) + 1,
        ])->save();

        $this->recordSession($user, $request);

        return response()->json([
            'message' => 'تم تسجيل دخول المدير بنجاح',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Guest login (creates temporary guest user)
     */
    public function guestLogin(Request $request)
    {
        // Create a unique guest user
        $guestPhone = 'guest_'.Str::uuid();

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

        $token = $user
            ->createToken('guest_token', ['guest'], now()->addDay())
            ->plainTextToken;

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
            'message' => 'Logged out successfully',
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

        $users = User::where('name', 'like', '%'.$request->query('query').'%')
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
        $user = User::withTrustMetrics()
            ->findOrFail($id);

        return response()->json([
            'user' => new UserResource($user),
            'ads' => AdResource::collection(
                $user->ads()->with(['category', 'mainImage'])->latest()->take(20)->get()
            ),
        ]);
    }

    /**
     * Send OTP Verification Code
     */
    public function sendOtp(Request $request)
    {
        $phone = $this->normalizePhone((string) $request->phone);

        $validator = Validator::make(['phone' => $phone], [
            'phone' => ['required', 'string', 'regex:/^\+967(70|71|73|77)[0-9]{7}$/'],
        ], [
            'phone.regex' => 'رقم الهاتف غير مدعوم. بوابة الرسائل الحالية تدعم أرقام اليمن فقط بالبادئات 70 أو 71 أو 73 أو 77.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $gatewayUrl = config('services.sms.gateway_url');
        $org = config('services.sms.org_name');
        $apiUser = config('services.sms.user_name');
        $pass = config('services.sms.password');

        if (! $gatewayUrl || ! $org || ! $apiUser || ! $pass) {
            Log::error('SMS gateway configuration is incomplete', [
                'missing' => array_keys(array_filter([
                    'gateway_url' => ! $gatewayUrl,
                    'org_name' => ! $org,
                    'user_name' => ! $apiUser,
                    'password' => ! $pass,
                ])),
            ]);

            return response()->json([
                'message' => 'خدمة الرسائل غير مهيأة حالياً. يرجى التواصل مع الدعم.',
                'status' => 'sms_not_configured',
            ], 503);
        }

        $code = (string) random_int(100000, 999999);
        $message = "رمز التحقق الخاص بك هو: $code";
        $gatewayPhone = $this->phoneForSmsGateway($phone);

        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'User '.substr($phone, -4)]
        );

        $user->forceFill([
            'otp' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(5),
            'otp_attempts' => 0,
            'otp_locked_until' => null,
        ])->save();

        try {
            $response = Http::timeout(self::SMS_GATEWAY_TIMEOUT_SECONDS)
                ->retry(1, 400, null, false)
                ->get($gatewayUrl, [
                    'orgName' => $org,
                    'userName' => $apiUser,
                    'password' => $pass,
                    'mobileNo' => $gatewayPhone,
                    'text' => $message,
                    'coding' => '2',
                ]);

            $responseBody = $response->body();

            if (! $response->successful()) {
                $this->clearPendingOtp($user);

                Log::error('SMS gateway returned HTTP error', [
                    'phone' => $this->maskPhone($phone),
                    'http_status' => $response->status(),
                    'gateway_body' => mb_substr($responseBody, 0, 200),
                ]);

                return response()->json([
                    'message' => 'بوابة الرسائل غير متوفرة حالياً، يرجى المحاولة مرة أخرى.',
                    'status' => 'sms_gateway_http_error',
                ], 503);
            }

            $gatewayResult = $this->parseSmsGatewayResponse($responseBody);

            if (! $gatewayResult['accepted']) {
                $this->clearPendingOtp($user);

                Log::error('SMS gateway rejected OTP', [
                    'phone' => $this->maskPhone($phone),
                    'gateway_code' => $gatewayResult['code'],
                    'gateway_message' => $gatewayResult['message'],
                    'gateway_body' => mb_substr($gatewayResult['raw'], 0, 200),
                ]);

                return response()->json([
                    'message' => $this->smsFailureMessage($gatewayResult),
                    'status' => 'sms_gateway_rejected',
                    'gateway_code' => $gatewayResult['code'],
                    'gateway_message' => $gatewayResult['message'],
                ], $this->smsFailureStatus($gatewayResult));
            }

            Log::info('SMS OTP accepted by gateway', [
                'phone' => $this->maskPhone($phone),
                'message_id' => $gatewayResult['message_id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('SMS gateway request failed', [
                'phone' => $this->maskPhone($phone),
                'exception' => get_class($e),
                'error' => $this->redactSmsLogText($e->getMessage()),
            ]);

            return response()->json([
                'status' => 'sent_pending_delivery',
                'message' => 'تم طلب إرسال رمز التحقق. قد تتأخر الرسالة قليلًا، ويمكنك إعادة الإرسال من صفحة التحقق إذا لم تصل.',
            ], 202);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق بنجاح.',
            'status' => 'sent',
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

        $phone = $this->normalizePhone((string) $request->phone);

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            \Log::warning("Verify OTP: User not found for normalized phone: $phone (Original: {$request->phone})");

            return response()->json([
                'message' => 'المستخدم غير موجود. تأكد من إدخال نفس الرقم الصحيح.',
                'valid' => false,
            ], 404);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Account is disabled',
                'valid' => false,
            ], 403);
        }

        if ($user->otp_locked_until && now()->lt($user->otp_locked_until)) {
            return response()->json([
                'message' => 'Too many OTP attempts. Try again later.',
                'valid' => false,
            ], 429);
        }

        if (! $user->otp || ! Hash::check($request->code, $user->otp)) {
            $attempts = ((int) $user->otp_attempts) + 1;
            $user->forceFill([
                'otp_attempts' => $attempts,
                'otp_locked_until' => $attempts >= self::OTP_MAX_ATTEMPTS
                    ? now()->addMinutes(self::OTP_LOCK_MINUTES)
                    : null,
            ])->save();

            Log::warning('Verify OTP failed: code mismatch', ['phone' => $phone, 'attempts' => $attempts]);

            return response()->json([
                'message' => 'كود التحقق غير صحيح.',
                'valid' => false,
            ], 400);
        }

        if (! $user->otp_expires_at || now()->gt($user->otp_expires_at)) {
            \Log::warning("Verify OTP: Code expired for $phone. Expires: {$user->otp_expires_at}, Now: ".now());

            return response()->json([
                'message' => 'كود التحقق انتهت صلاحيته.',
                'valid' => false,
            ], 400);
        }

        // Clear OTP
        $user->forceFill([
            'otp' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_locked_until' => null,
            'phone_verified_at' => now(),
            'last_login_at' => now(),
            'login_count' => ($user->login_count ?? 0) + 1,
        ])->save();

        $token = $user
            ->createToken('auth_token', ['user'], now()->addDays(self::USER_TOKEN_TTL_DAYS))
            ->plainTextToken;

        $this->recordSession($user, $request);

        return response()->json([
            'message' => 'Login successful',
            'valid' => true,
            'data' => new UserResource($user),
            'user' => new UserResource($user),
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
            'data' => $sessions,
        ]);
    }

    /**
     * Get user dashboard stats and recent activity
     */
    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        $cacheKey = "user_dashboard_stats_{$user->id}";

        return Cache::remember($cacheKey, 600, function () use ($user) {
            // 1. Calculate Stats efficiently
            $stats = [
                'total_ads' => $user->ads()->count(),
                'total_sold' => $user->ads()->where('status', 'sold')->count(),
                'total_buyers' => $user->receivedMessages()->distinct('sender_id')->count('sender_id'),
                'rating' => round(Review::where('reviewed_id', $user->id)->avg('rating') ?? 0, 1),
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
            $recentReviews = Review::where('reviewed_id', $user->id)
                ->with('reviewer:id,name')
                ->latest()
                ->take(5)
                ->get();

            $activities = $recentAds->flatMap(function ($ad) {
                $acts = [
                    [
                        'type' => 'ad_created',
                        'title' => 'إعلان جديد',
                        'description' => 'تم إضافة إعلان: '.Str::limit($ad->title, 30),
                        'time' => $ad->created_at->diffForHumans(),
                        'timestamp' => $ad->created_at,
                        'icon_code' => 57415,
                        'color_hex' => '#10B981',
                    ],
                ];

                if ($ad->status === 'sold') {
                    $acts[] = [
                        'type' => 'ad_sold',
                        'title' => 'بيع ناجح',
                        'description' => 'تم بيع: '.Str::limit($ad->title, 30),
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
                    'description' => 'حصلت على تقييم '.$review->rating.' نجوم من '.($review->reviewer->name ?? 'مستخدم'),
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
                'activities' => $sortedActivities,
            ];
        });
    }
}
