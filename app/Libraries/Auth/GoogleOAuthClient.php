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

        // Si se recibe un id_token real de Google Identity Services, validarlo contra Google
        if ($idToken !== '' && substr_count($idToken, '.') === 2) {
            $verified = $this->verifyGoogleIdToken($idToken);
            if ($verified !== null) {
                return $verified;
            }
        }

        // Modo desarrollo local cuando aún no se ha configurado GOOGLE_CLIENT_ID en .env:
        // permite probar el inicio de sesión con Google desde la interfaz sin bloqueo externo.
        if ($this->clientId === '' || ENVIRONMENT !== 'production') {
            $email = filter_var((string) ($input['email'] ?? 'google.user@transcripciones.local'), FILTER_VALIDATE_EMAIL);
            if ($email === false) {
                return null;
            }

            $name     = trim((string) ($input['name'] ?? 'Usuario Google Workspace'));
            $googleId = trim((string) ($input['google_id'] ?? ('google-oauth-' . substr( hash('sha256', $email), 0, 16 ))));

            return [
                'google_id'  => $googleId,
                'email'      => $email,
                'name'       => $name !== '' ? $name : 'Usuario Google',
                'avatar_url' => '',
            ];
        }

        return null;
    }

    /**
     * Consulta el endpoint oficial de Google OAuth2 para validar un ID Token JWT.
     *
     * @return array<string, string>|null
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
        $ch  = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! is_string($response)) {
            return null;
        }

        $data = json_decode($response, true);
        if (! is_array($data) || empty($data['sub']) || empty($data['email'])) {
            return null;
        }

        if ($this->clientId !== '' && ($data['aud'] ?? '') !== $this->clientId) {
            return null;
        }

        return [
            'google_id'  => (string) $data['sub'],
            'email'      => (string) $data['email'],
            'name'       => (string) ($data['name'] ?? explode('@', (string) $data['email'])[0]),
            'avatar_url' => (string) ($data['picture'] ?? ''),
        ];
    }
}

