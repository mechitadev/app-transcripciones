<?php

namespace App\Filters;

use App\Libraries\Auth\JwtManager;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro de Autenticación Híbrida (Sesiones CI4 + JWT HS256).
 *
 * Permite el acceso si:
 *  1. Existe una sesión activa en el servidor (`session('user_id')`), O
 *  2. La petición incluye un token JWT válido en la cabecera `Authorization: Bearer <token>`
 *     o en la cookie `auth_token` (en cuyo caso reconstruye la sesión automáticamente).
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // 1. Verificar si ya hay sesión activa
        if ($session->has('user_id') && (int) $session->get('user_id') > 0) {
            return null;
        }

        // 2. Buscar token JWT en cabecera Authorization: Bearer o en cookie auth_token
        $token      = '';
        $authHeader = $request->getHeaderLine('Authorization');
        if (stripos($authHeader, 'Bearer ') === 0) {
            $token = trim(substr($authHeader, 7));
        } elseif (isset($_COOKIE['auth_token']) && is_string($_COOKIE['auth_token'])) {
            $token = trim($_COOKIE['auth_token']);
        }

        if ($token !== '') {
            $jwt     = new JwtManager();
            $payload = $jwt->verifyToken($token);
            if ($payload !== null && ! empty($payload['sub'])) {
                // Sincronizar con la sesión de CodeIgniter 4
                $session->set([
                    'user_id'    => (int) $payload['sub'],
                    'user_name'  => (string) ($payload['name'] ?? 'Usuario'),
                    'user_email' => (string) ($payload['email'] ?? ''),
                    'jwt_token'  => $token,
                ]);

                return null;
            }
        }

        // 3. Si es una llamada a la API (/api/* o JSON), devolver 401 JSON
        $uriPath = trim($request->getUri()->getPath(), '/');
        $accept  = $request->getHeaderLine('Accept');
        if (strpos($uriPath, 'api/') === 0 || stripos($accept, 'application/json') !== false) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'No autenticado. Inicia sesión o envía un token JWT válido.',
                ]);
        }

        // 4. Si es navegación web normal, redirigir al portal principal /
        return redirect()->to(site_url('/'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}

