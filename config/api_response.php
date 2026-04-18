<?php

function apiResponse(bool $status, string $message, int $statusCode = 200, array $data = [], array $errors = []): void
{
    http_response_code($statusCode);

    $payload = [
        'status' => $status,
        'message' => $message,
    ];

    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }

    echo json_encode(array_merge($payload, $data));
}
