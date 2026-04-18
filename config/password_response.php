<?php

function passwordResponse(bool $status, string $message, int $statusCode = 200, array $errors = []): void
{
    http_response_code($statusCode);

    $payload = [
        'status' => $status,
        'message' => $message,
    ];

    if (!$status && !empty($errors)) {
        $payload['errors'] = $errors;
    }

    echo json_encode($payload);
}
