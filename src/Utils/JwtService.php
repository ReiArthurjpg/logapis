<?php

declare(strict_types=1);

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use RuntimeException;

class JwtService
{
    /**
     * Codifica um payload em formato JWT token.
     * Utiliza Firebase\JWT se a dependência estiver instalada via Composer,
     * caso contrário recorre a implementação interna compatível com HMAC-SHA256.
     */
    public static function encode(array $payload, string $secret, string $algo = 'HS256'): string
    {
        if (class_exists(JWT::class)) {
            return JWT::encode($payload, $secret, $algo);
        }

        return self::nativeEncode($payload, $secret);
    }

    /**
     * Decodifica e valida um token JWT.
     * Retorna o payload em array ou lança exceção em caso de token inválido/expirado.
     */
    public static function decode(string $token, string $secret, string $algo = 'HS256'): array
    {
        if (class_exists(JWT::class)) {
            $decoded = JWT::decode($token, new Key($secret, $algo));
            return (array) $decoded;
        }

        return self::nativeDecode($token, $secret);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private static function nativeEncode(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $encodedHeader = self::base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $secret, true);
        $encodedSignature = self::base64UrlEncode($signature);

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    private static function nativeDecode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException("Formato de token inválido.");
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson = self::base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);
        if (!$header || ($header['alg'] ?? '') !== 'HS256') {
            throw new RuntimeException("Algoritmo de token não suportado.");
        }

        $expectedSig = self::base64UrlEncode(hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true));
        if (!hash_equals($expectedSig, $signatureB64)) {
            throw new RuntimeException("Assinatura do token inválida.");
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new RuntimeException("Payload do token corrompido.");
        }

        if (isset($payload['exp']) && time() >= $payload['exp']) {
            throw new RuntimeException("Token expirado.");
        }

        return $payload;
    }
}
