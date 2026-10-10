<?php
require_once __DIR__ . '/../vendor/autoload.php';  // Use absolute path for reliability
use Cloudinary\Cloudinary;

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$isMock = (isset($_ENV['MOCK_CLOUDINARY']) && $_ENV['MOCK_CLOUDINARY'] === 'true')
    || (isset($_SERVER['HTTP_X_MOCK_CLOUDINARY']) && $_SERVER['HTTP_X_MOCK_CLOUDINARY'] === 'true');

if ($isMock) {
    $cloudinary = new class {
        public function uploadApi() {
            return new class {
                public function upload($file, array $options = []) {
                    $publicId = $options['public_id'] ?? ('mock_' . uniqid());
                    if (!empty($options['folder'])) {
                        $fullPublicId = rtrim($options['folder'], '/') . '/' . $publicId;
                    } else {
                        $fullPublicId = $publicId;
                    }
                    return [
                        'secure_url' => 'https://res.cloudinary.com/jobnet-mock/raw/upload/' . $fullPublicId,
                        'public_id' => $fullPublicId,
                    ];
                }
                public function destroy($publicId, array $options = []) {
                    return ['result' => 'ok'];
                }
            };
        }
    };
} else {
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
}