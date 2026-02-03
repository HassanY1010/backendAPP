<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\AdController;
use App\Http\Controllers\API\FavoriteController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\AppReviewController;
use App\Http\Controllers\API\ProfileController;

// App Reviews
Route::post('/app-reviews', [AppReviewController::class, 'store']);

// Test Image URL Generation (Diagnostic)
Route::get('/test-image-url', function () {
    $image = \App\Models\AdImage::first();
    if (!$image) {
        return response()->json(['error' => 'No images found in database']);
    }
    return response()->json([
        'image_path' => $image->image_path,
        'thumbnail_path' => $image->thumbnail_path,
        'generated_image_url' => $image->image_url,
        'generated_thumbnail_url' => $image->thumbnail_url,
        'expected_format' => env('SUPABASE_URL') . '/storage/v1/object/public/uploads/' . $image->image_path,
        'config_url' => config('filesystems.disks.supabase.url'),
    ]);
});


// Auth Routes with Rate Limiting
Route::prefix('v1')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/guest-login', [AuthController::class, 'guestLogin']);
        Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    });


    // Protected Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::get('/auth/verify', function (Request $request) {
            return response()->json(['user_id' => $request->user()->id]);
        });
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::get('/user/dashboard-stats', [AuthController::class, 'dashboardStats']);

        // Profile
        Route::post('/profile/update', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
        Route::get('/profile/export', [ProfileController::class, 'export']);

        // Ads
        Route::post('/ads', [AdController::class, 'store']);
        Route::post('/ads/{id}/update', [AdController::class, 'update']);
        Route::delete('/ads/{id}', [AdController::class, 'destroy']);
        Route::get('/user/ads', [AdController::class, 'userAds']);
        Route::post('/ads/upload-image', [AdController::class, 'uploadImage']);

        // Social & Comments
        Route::post('/ads/{id}/comments', [\App\Http\Controllers\CommentController::class, 'store']);

        Route::post('/users/{id}/follow', [\App\Http\Controllers\FollowController::class, 'follow']);
        Route::post('/users/{id}/unfollow', [\App\Http\Controllers\FollowController::class, 'unfollow']);
        Route::get('/users/{id}/followers', [\App\Http\Controllers\FollowController::class, 'followers']);
        Route::get('/users/{id}/following', [\App\Http\Controllers\FollowController::class, 'following']);


        // Favorites
        Route::post('/favorite/{ad_id}', [FavoriteController::class, 'toggle']);
        Route::post('/ads/{id}/like', [FavoriteController::class, 'toggle']);
        Route::post('/ads/{id}/unlike', [FavoriteController::class, 'toggle']);
        Route::get('/favorites', [FavoriteController::class, 'index']);

        // Messages
        Route::post('/messages/send', [MessageController::class, 'send']);
        Route::get('/messages/fetch/{userId}/{otherUserId}', [MessageController::class, 'fetch']);
        Route::get('/messages/conversations', [MessageController::class, 'conversations']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // Reports
        Route::post('/report', [ReportController::class, 'store']);

        // User Search & Profile
        Route::get('/users/search', [\App\Http\Controllers\API\AuthController::class, 'search']); // Need to implement these in AuthController or separate

        // Admin Routes
        Route::middleware(\App\Http\Middleware\AdminMiddleware::class)->prefix('admin')->group(function () {

            // Users
            Route::get('/users', [\App\Http\Controllers\API\Admin\UserController::class, 'index']);
            Route::patch('/user/{id}', [\App\Http\Controllers\API\Admin\UserController::class, 'update']);
            Route::patch('/user/{id}/status', [\App\Http\Controllers\API\Admin\UserController::class, 'updateStatus']);
            Route::patch('/user/{id}/role', [\App\Http\Controllers\API\Admin\UserController::class, 'updateRole']);
            Route::delete('/user/{id}', [\App\Http\Controllers\API\Admin\UserController::class, 'destroy']);

            // Ads
            Route::get('/ads', [\App\Http\Controllers\API\Admin\AdController::class, 'index']);
            Route::get('/ad/{id}', [\App\Http\Controllers\API\Admin\AdController::class, 'show']);
            Route::post('/ad/{id}/update-status', [\App\Http\Controllers\API\Admin\AdController::class, 'updateStatus']);
            Route::post('/ad/{id}/activate-featured', [\App\Http\Controllers\API\Admin\AdController::class, 'activateFeatured']);
            Route::post('/ad/{id}/deactivate-featured', [\App\Http\Controllers\API\Admin\AdController::class, 'deactivateFeatured']);
            Route::delete('/ad/{id}', [\App\Http\Controllers\API\Admin\AdController::class, 'destroy']);

            // Categories
            Route::get('/categories', [\App\Http\Controllers\API\Admin\CategoryController::class, 'index']);
            Route::post('/category', [\App\Http\Controllers\API\Admin\CategoryController::class, 'store']);
            Route::post('/category/{id}/update', [\App\Http\Controllers\API\Admin\CategoryController::class, 'update']);
            Route::delete('/category/{id}', [\App\Http\Controllers\API\Admin\CategoryController::class, 'destroy']);

            // Reports
            Route::get('/reports', [\App\Http\Controllers\API\Admin\ReportController::class, 'index']);
            Route::post('/report/{id}/resolve', [\App\Http\Controllers\API\Admin\ReportController::class, 'resolve']);
            Route::delete('/report/{id}', [\App\Http\Controllers\API\Admin\ReportController::class, 'destroy']);

            // Stats
            Route::get('/stats', [\App\Http\Controllers\API\Admin\StatsController::class, 'index']);
        });
    });
    // Public Routes
    Route::get('/ads/recent', [AdController::class, 'recent']); // Top 4 recent ads
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/ads', [AdController::class, 'index']);
    Route::get('/ads/{id}', [AdController::class, 'show']);
    Route::get('/ads/{id}/comments', [\App\Http\Controllers\CommentController::class, 'index']);
    Route::get('/users/{id}/profile', [\App\Http\Controllers\API\AuthController::class, 'publicProfile']);
});
