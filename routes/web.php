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
    if (! Storage::disk('public')->exists($path)) {
        // If it's an avatar request and file doesn't exist, return a default avatar
        if (str_starts_with($path, 'avatars/')) {
            // You can create a default avatar or return a placeholder
            // For now, return 404 but log the missing file
            \Log::warning("Missing avatar file: {$path}");
            abort(404, 'Avatar not found');
        }
        abort(404);
    }
    
    $filePath = Storage::disk('public')->path($path);
    $mimeType = mime_content_type($filePath);
    
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
    ]);
})->where('path', '.*');
