<?php

namespace App\Services;

use RuntimeException;

/**
 * Verifies HS256 JWTs issued by CAPS.
 *
 * CAPS signs its tokens with `Jwts.builder().signWith(hmacShaKeyFor(BASE64.decode(secret)), HS256)`
 * (see com.casey.supportal.service.JwtService in the CAPS repo). The Tracker
 * therefore needs the same base64-encoded shared secret in order to validate
 * incoming tokens.
 *
 * Implemented from scratch (no third-party dependency) because we only need
 * HS256 and the surface area is small.
 */
class CaseyJwtService
{
    /**
     * Verify a JWT and return its claims. Throws RuntimeException on any
     * validation failure (malformed token, bad signature, expired, etc.).
     *
     * @return array<string,mixed>
     */
    public function verify(string $jwt): array
    {
        $secret = (string) config('services.casey.jwt_shared_secret', '');
        if ($secret === '') {
            throw new RuntimeException('CASEY_JWT_SHARED_SECRET is not configured.');
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT.');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        if (! is_array($header)) {
            throw new RuntimeException('JWT header is not valid JSON.');
        }

        $alg = strtoupper((string) ($header['alg'] ?? ''));
        if ($alg !== 'HS256') {
            throw new RuntimeException("Unsupported JWT algorithm: $alg.");
        }

        $signature = $this->base64UrlDecode($encodedSignature);
        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $key = base64_decode($secret, true);
        if ($key === false || $key === '') {
            throw new RuntimeException('CASEY_JWT_SHARED_SECRET is not valid base64.');
        }
        $expected = hash_hmac('sha256', $signingInput, $key, true);
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('JWT signature mismatch.');
        }

        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (! is_array($claims)) {
            throw new RuntimeException('JWT payload is not valid JSON.');
        }

        $leeway = (int) config('services.casey.jwt_leeway_seconds', 30);
        $now = time();

        if (isset($claims['exp']) && is_numeric($claims['exp']) && ($now - $leeway) >= (int) $claims['exp']) {
            throw new RuntimeException('JWT has expired.');
        }
        if (isset($claims['nbf']) && is_numeric($claims['nbf']) && ($now + $leeway) < (int) $claims['nbf']) {
            throw new RuntimeException('JWT is not yet valid.');
        }
        if (isset($claims['iat']) && is_numeric($claims['iat']) && ($now + $leeway) < (int) $claims['iat']) {
            throw new RuntimeException('JWT issued-at is in the future.');
        }
        if (! isset($claims['sub']) || trim((string) $claims['sub']) === '') {
            throw new RuntimeException('JWT is missing the subject claim.');
        }

        return $claims;
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder !== 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('JWT segment is not valid base64url.');
        }
        return $decoded;
    }
}
