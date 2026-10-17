<?php
/**
 * Copy this file to config/config.php and update the values.
 * config/config.php is git-ignored so credentials never reach the repository.
 */
return [
    'app' => [
        'name'     => 'Sahodaya Science Fair Portal',
        // Public base URL WITHOUT trailing slash, e.g. https://sciencefair.example.org or http://localhost/sciencefair
        'url'      => 'http://localhost:8000',
        'env'      => 'production',          // 'development' shows PHP errors
        'timezone' => 'Asia/Kolkata',
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'science_fair',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    'uploads' => [
        'max_photo_kb'   => 1024,  // cropped student photo
        'max_receipt_kb' => 5120,  // payment proof
    ],
];
