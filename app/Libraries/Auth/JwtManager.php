<?php

namespace App\Libraries\Auth;

/**
 * Gestor ligero y nativo de JSON Web Tokens (JWT - Algoritmo HS256).
 *
 * Siguiendo la regla Ponytail (cero dependencias externas redundantes),
 * implementa el estándar RFC 7519 usando funciones criptográficas nativas de PHP:
 *  - hash_hmac('sha256', ...) para firmado
 *  - hash_equals() para validación resistente a ataques de tiempo (timing attacks)
 */
class JwtManager
{
    /**
     * Clave secreta de firmado (configurable en .env como auth.jwtSecret).
     */
    private string $secret;

    /**
     * Tiempo de vida del token en segundos (por defecto 8 horas = 28800 s).
     */
    private int $ttlSeconds;

    public function __construct(?string $secret = null, ?int $ttlSeconds = null)
    {
        $authConfig       = config('Auth');
        $this->secret     = $secret ?? ($authConfig->jwtSecret ?: 'app-transcripciones-jwt-secret-key-wamp-2026');
        $this->ttlSeconds = $ttlSeconds ?? ($authConfig->jwtTtlSeconds ?: 28800);
    }

    /**
     * Genera un token JWT firmado (HS256) para el usuario autenticado.
     *
     * @param array<string, mixed> $user Datos básicos del usuario (id, name, email)
     */
    public function issueToken(array $user): string
    {
        $now     = time();
        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss'    => 'app-transcripciones',
            'sub'    => (int) ($user['id'] ?? 0),
            'name'   => (string) ($user['nombre'] ?? $user['name'] ?? ''),
            'nombre' => (string) ($user['nombre'] ?? $user['name'] ?? ''),
            'email'  => (string) ($user['correo'] ?? $user['email'] ?? ''),
            'correo' => (string) ($user['correo'] ?? $user['email'] ?? ''),
            'iat'    => $now,
            'exp'    => $now + $this->ttlSeconds,
        ];

        $segments   = [];
        $segments[] = $this->base64UrlEncode((string) json_encode($header));
        $segments[] = $this->base64UrlEncode((string) json_encode($payload));

        $signingInput = implode('.', $segments);
        $signature    = hash_hmac('sha256', $signingInput, $this->secret, true);
        $segments[]   = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Verifica la firma y expiración de un token JWT.
     * Devuelve el payload como array asociativo si es válido, o null si es inválido/expirado.
     *
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $jwt): ?array
    {
        $parts = explode('.', trim($jwt));
        if (count($parts) !== 3) {
            return null;
        }

        [$headB64, $bodyB64, $sigB64] = $parts;

        $header  = json_decode($this->base64UrlDecode($headB64), true);
        $payload = json_decode($this->base64UrlDecode($bodyB64), true);
        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', $headB64 . '.' . $bodyB64, $this->secret, true)
        );

        if (! hash_equals($expectedSig, $sigB64)) {
            return null;
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}

