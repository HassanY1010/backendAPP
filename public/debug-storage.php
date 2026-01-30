<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

// Manual bootstrap for a standalone-like experience if needed, 
// but we'll assume it's hit via the web server with Laravel fully loaded.
// If Laravel is NOT loaded, this script will fail, but usually public/*.php 
// doesn't have Laravel loaded unless it's the index.php entry point.

// To be safe, we'll try to load the Laravel app.
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

header('Content-Type: text/plain');

echo "--- Storage Diagnostic ---\n";

$disk = 'public';
echo "Checking disk: $disk\n";

try {
    $exists = Storage::disk($disk)->exists('.');
    echo "Disk exists: " . ($exists ? "YES" : "NO") . "\n";

    $root = config("filesystems.disks.$disk.root");
    echo "Disk root: $root\n";

    if (File::exists($root)) {
        echo "Root directory exists on filesystem: YES\n";
        echo "Root directory readable: " . (is_readable($root) ? "YES" : "NO") . "\n";
        echo "Root directory writable: " . (is_writable($root) ? "YES" : "NO") . "\n";
    } else {
        echo "Root directory exists on filesystem: NO (Try running php artisan storage:link?)\n";
    }

    $testFile = 'test_write.txt';
    Storage::disk($disk)->put($testFile, 'Test at ' . date('Y-m-d H:i:s'));
    if (Storage::disk($disk)->exists($testFile)) {
        echo "Can write to disk: YES\n";
        Storage::disk($disk)->delete($testFile);
        echo "Can delete from disk: YES\n";
    } else {
        echo "Can write to disk: NO\n";
    }

    echo "Listing directory 'ads':\n";
    if (Storage::disk($disk)->exists('ads')) {
        $files = Storage::disk($disk)->files('ads');
        echo "Found " . count($files) . " files in 'ads'.\n";
        foreach (array_slice($files, 0, 10) as $file) {
            echo " - $file (" . Storage::disk($disk)->size($file) . " bytes)\n";
        }
    } else {
        echo "Directory 'ads' does not exist.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "--- End ---\n";
