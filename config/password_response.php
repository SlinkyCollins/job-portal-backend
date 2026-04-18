<?php
require_once __DIR__ . '/api_response.php';

function passwordResponse(bool $status, string $message, int $statusCode = 200, array $errors = []): void
{
    apiResponse($status, $message, $statusCode, [], $errors);
}
