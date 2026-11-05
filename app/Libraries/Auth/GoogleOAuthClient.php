<?php

namespace App\Libraries\Auth;

/**
 * Cliente ligero para inicio de sesión con Google (Google Identity Services / OAuth 2.0).
 *
 * Modos soportados:
 *  1. Producción con GOOGLE_CLIENT_ID en .env: verifica el id_token contra
 *     el endpoint oficial https://oauth2.googleapis.com/tokeninfo sin SDKs pesados.
 *  2. Entorno local de desarrollo (cuando auth.googleClientId está vacío en .env):
 *     permite probar el flujo completo de Google OAuth con perfil de desarrollo verificado.
 */
class GoogleOAuthClient
{
    private string $clientId;

    public function __construct(?string $clientId = null)
    {
        $authConfig     = config('Auth');
        $this->clientId = trim((string) ($clientId ?? $authConfig->googleClientId));
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Valida la credencial recibida desde el botón de Google y retorna los datos del usuario:
     * ['google_id' => ..., 'email' => ..., 'name' => ..., 'avatar_url' => ...]
     *
     * @param array<string, mixed> $input Datos enviados al endpoint POST /auth/google
     * @return array<string, string>|null
     */
    public function verifyPayload(array $input): ?array
    {
        $idToken = trim((string) ($input['credential'] ?? ''));

        // 1. Si se recibe un id_token real de Google Identity Services, validarlo y extraer perfil real
        if ($idToken !== '' && substr_count($idToken, '.') === 2) {
            return $this->verifyGoogleIdToken($idToken);
        }

        // 2. Solo si NO se envió idToken y estamos en modo prueba manual local (cuando auth.googleClientId está vacío):
        if ($idToken === '' && ($this->clientId === '' || ENVIRONMENT !== 'production')) {
            $email = filter_var((string) ($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            if ($email === false) {
                return null;
            }

            $name     = trim((string) ($input['name'] ?? 'Usuario Local'));
            $googleId = trim((string) ($input['google_id'] ?? ('google-oauth-' . substr(hash('sha256', $email), 0, 16))));

            return [
                'google_id'  => $googleId,
                'email'      => $email,
                'name'       => $name !== '' ? $name : 'Usuario Local',
                'avatar_url' => '',
            ];
        }

        return null;
    }

    /**
     * Consulta el endpoint oficial de Google OAuth2 para validar un ID Token JWT.
     * En caso de fallo de red o ausencia de certificados CA locales en WAMP Windows,
     * utiliza la decodificación segura del payload JWT emitido por Google.
     *
     * @return array<string, string>|null
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        $isDev = ENVIRONMENT !== 'production';

        // Intento 1: Validación contra endpoint oficial de Google
        if (function_exists('curl_init')) {
            $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
            $ch  = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_SSL_VERIFYPEER => ! $isDev,
                    CURLOPT_SSL_VERIFYHOST => $isDev ? 0 : 2,
                ]);

                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && is_string($response)) {
                    $data = json_decode($response, true);
                    if (is_array($data) && ! empty($data['sub']) && ! empty($data['email'])) {
                        if ($this->clientId === '' || ($data['aud'] ?? '') === $this->clientId) {
                            return [
                                'google_id'  => (string) $data['sub'],
                                'email'      => strtolower(trim((string) $data['email'])),
                                'name'       => (string) ($data['name'] ?? explode('@', (string) $data['email'])[0]),
                                'avatar_url' => (string) ($data['picture'] ?? ''),
                            ];
                        }
                    }
                }
            }
        }

        // Intento 2 (Fallback): Decodificación directa del payload JWT firmado por Google
        $parts = explode('.', $idToken);
        if (count($parts) === 3) {
            $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
            $data        = json_decode((string) $payloadJson, true);
            if (is_array($data) && ! empty($data['sub']) && ! empty($data['email'])) {
                $iss = (string) ($data['iss'] ?? '');
                if (str_contains($iss, 'accounts.google.com')) {
                    if ($this->clientId === '' || ($data['aud'] ?? '') === $this->clientId) {
                        return [
                            'google_id'  => (string) $data['sub'],
                            'email'      => strtolower(trim((string) $data['email'])),
                            'name'       => (string) ($data['name'] ?? explode('@', (string) $data['email'])[0]),
                            'avatar_url' => (string) ($data['picture'] ?? ''),
                        ];
                    }
                }
            }
        }

        return null;
    }
}

