<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Handle CORS preflight requests
Route::options('/local-cdn/{path}', function () {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
    ]);
})->where('path', '.*');

Route::get('/local-cdn/{path}', function ($path) {
    // Check if file exists
    if (!Storage::disk('public')->exists($path)) {
        // If it's an avatar request and file doesn't exist, return a default avatar placeholder
        if (str_starts_with($path, 'avatars/')) {
            return response()->file(public_path('images/default-avatar.png'), [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=3600', // Cache placeholders for an hour
                'Access-Control-Allow-Origin' => '*',
            ]);
        }
        abort(404);
    }

    $filePath = Storage::disk('public')->path($path);
    $mimeType = mime_content_type($filePath);

    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000', // Cache assets for a year
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
    ]);
})->where('path', '.*');
