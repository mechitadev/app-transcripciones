<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * ============================================================================
 * CONFIGURACIÓN CENTRALIZADA DE AUTENTICACIÓN (`Config\Auth`)
 * ============================================================================
 *
 * Todas las claves y secretos se configuran exclusivamente a través de las
 * variables de entorno en el archivo `.env`.
 */
class Auth extends BaseConfig
{
    /**
     * Clave secreta para la firma HMAC-SHA256 de tokens JWT.
     * Se define en .env: auth.jwtSecret
     */
    public string $jwtSecret = 'app-transcripciones-jwt-secret-key-wamp-2026';

    /**
     * Tiempo de expiración del token JWT en segundos (por defecto 28800 = 8 horas).
     * Se define en .env: auth.jwtTtlSeconds
     */
    public int $jwtTtlSeconds = 28800;

    /**
     * ID de cliente de Google OAuth 2.0 (Google Identity Services).
     * Se define en .env: auth.googleClientId
     * Si se deja vacío (''), la interfaz opera en modo local con autenticación tradicional.
     */
    public string $googleClientId = '';

    /**
     * Secreto de cliente de Google OAuth 2.0.
     * Se define en .env: auth.googleClientSecret
     */
    public string $googleClientSecret = '';

    public function __construct()
    {
        parent::__construct();

        $this->jwtSecret          = (string) env('auth.jwtSecret', $this->jwtSecret);
        $this->jwtTtlSeconds      = (int) env('auth.jwtTtlSeconds', $this->jwtTtlSeconds);
        $this->googleClientId     = (string) env('auth.googleClientId', $this->googleClientId);
        $this->googleClientSecret = (string) env('auth.googleClientSecret', $this->googleClientSecret);
    }
}

