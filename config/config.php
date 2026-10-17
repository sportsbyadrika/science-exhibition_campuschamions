<?php
/**
 * Application configuration. Values come from the .env file in the project root
 * (copy .env.example to .env); never put real credentials in this file.
 */
return [
    'app' => [
        'name'     => env('APP_NAME', 'Sahodaya Science Fair Portal'),
        // Public base URL WITHOUT trailing slash
        'url'      => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
        'env'      => env('APP_ENV', 'production'),   // 'development' shows PHP errors
        'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
    ],
    'db' => [
        'host'     => env('DB_HOST', 'localhost'),
        'port'     => (int) env('DB_PORT', 3306),
        'name'     => env('DB_DATABASE', 'science_fair'),
        'user'     => env('DB_USERNAME', 'root'),
        'pass'     => (string) env('DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
    ],
    'uploads' => [
        'max_photo_kb'   => (int) env('UPLOAD_MAX_PHOTO_KB', 1024),
        'max_receipt_kb' => (int) env('UPLOAD_MAX_RECEIPT_KB', 5120),
    ],
];
