<?php

declare(strict_types=1);

namespace App\Utils;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message, ?array $data = null, int $statusCode = 200): void
    {
        $response = [
            'status'  => 'success',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::json($response, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, ?array $errors = null): void
    {
        $response = [
            'status'  => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }
}
