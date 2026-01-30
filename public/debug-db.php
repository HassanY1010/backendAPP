<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;

try {
    DB::connection()->getPdo();
    echo "Successfully connected to the database: " . DB::connection()->getDatabaseName();
} catch (\Exception $e) {
    echo "Could not connect to the database. Error: " . $e->getMessage();

    // Help diagnostic
    $host = env('DB_HOST');
    echo "\n\nDiagnostic Information:";
    echo "\nDB_HOST: " . $host;
    echo "\nDB_PORT: " . env('DB_PORT');
    echo "\nDB_DATABASE: " . env('DB_DATABASE');

    if (str_contains($e->getMessage(), 'getaddrinfo failed')) {
        echo "\n\nSUGGESTION: The hostname '{$host}' cannot be resolved. Please verify the hostname in your Aiven dashboard and ensure it's entered correctly in your environment variables.";
    }
}
