<?php
require_once __DIR__ . '/../vendor/autoload.php';  // Use absolute path for reliability
use Cloudinary\Cloudinary;

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Check for required env vars
if (!isset($_ENV['CLOUDINARY_CLOUD_NAME'], $_ENV['CLOUDINARY_API_KEY'], $_ENV['CLOUDINARY_API_SECRET'])) {
    die(json_encode(['status' => false, 'message' => 'Cloudinary config missing in .env']));
}

$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
        'api_key'    => $_ENV['CLOUDINARY_API_KEY'],
        'api_secret' => $_ENV['CLOUDINARY_API_SECRET'],
    ],
    'url' => ['secure' => true]
]);