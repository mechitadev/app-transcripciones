<?php

namespace App\Controllers;

use App\Libraries\Auth\GoogleOAuthClient;
use App\Libraries\Auth\JwtManager;
use App\Models\MeetingModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controlador MVC de Autenticación (`AuthController`).
 *
 * Centraliza:
 *  - Inicio de sesión tradicional con correo/contraseña
 *  - Registro de cuenta nueva
 *  - Inicio de sesión con Google (OAuth 2.0 / Google Identity Services)
 *  - Emisión de JWT (HS256) + persistencia en sesión de CodeIgniter 4
 */
class AuthController extends BaseController
{
    private UserModel $userModel;
    private JwtManager $jwtManager;
    private GoogleOAuthClient $googleClient;

    public function __construct()
    {
        $this->userModel    = new UserModel();
        $this->jwtManager   = new JwtManager();
        $this->googleClient = new GoogleOAuthClient();
    }

    /**
     * Renderiza la vista de inicio de sesión (`GET /login`).
     */
    public function loginView()
    {
        if (session()->has('user_id') && (int) session()->get('user_id') > 0) {
            return redirect()->to('/');
        }

        return view('auth/login', [
            'googleClientId' => $this->googleClient->getClientId(),
            'portalStats'    => (new MeetingModel())->getPortalStats(),
        ]);
    }

    /**
     * Autentica al usuario con correo y contraseña (`POST /auth/login`).
     */
    public function login(): ResponseInterface
    {
        $payload  = $this->getRequestPayload();
        $email    = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->respondAuthError('Completa todos los campos requeridos con un formato válido.', 422);
        }

        $user = $this->userModel->verifyCredentials($email, $password);
        if ($user === null) {
            return $this->respondAuthError('Las credenciales ingresadas no coinciden con ninguna cuenta activa.', 401);
        }

        return $this->establishAuthSession($user);
    }

    /**
     * Registra un usuario nuevo y abre su sesión + JWT (`POST /auth/register`).
     */
    public function register(): ResponseInterface
    {
        $payload  = $this->getRequestPayload();
        $name     = trim((string) ($payload['name'] ?? ''));
        $email    = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ($name === '' || $email === '' || strlen($password) < 6 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respondAuthError('Completa todos los campos requeridos con un formato válido.', 422);
        }

        if ($this->userModel->findByEmail($email) !== null) {
            return $this->respondAuthError('El correo electrónico ya está registrado.', 409);
        }

        $userData = [
            'nombre'          => $name,
            'correo'          => $email,
            'clave_hash'      => password_hash($password, PASSWORD_DEFAULT),
            'google_id'       => null,
            'foto_perfil_url' => null,
            'creado_en'       => date('Y-m-d H:i:s'),
            'name'            => $name,
            'email'           => $email,
        ];

        $id             = (int) $this->userModel->insert($userData, true);
        $userData['id'] = $id;

        return $this->establishAuthSession($userData);
    }

    /**
     * Autentica o registra un usuario mediante Google OAuth (`POST /auth/google`).
     */
    public function google(): ResponseInterface
    {
        $payload       = $this->getRequestPayload();
        $googleProfile = $this->googleClient->verifyPayload($payload);

        if ($googleProfile === null) {
            return $this->respondAuthError('No se pudo verificar la credencial de Google.', 401);
        }

        $user = $this->userModel->findOrCreateGoogleUser($googleProfile);

        return $this->establishAuthSession($user);
    }

    /**
     * Cierra la sesión activa y limpia la cookie del token JWT (`POST /auth/logout`).
     */
    public function logout(): ResponseInterface
    {
        session()->destroy();
        setcookie('auth_token', '', time() - 3600, '/', '', false, true);

        if ($this->wantsJson()) {
            return $this->response->setJSON(['status' => 'ok']);
        }

        return redirect()->to(site_url('/'));
    }

    /**
     * Inicia la sesión de CodeIgniter 4, emite el token JWT HS256 y responde en JSON o redirección.
     *
     * @param array<string, mixed> $user
     */
    private function establishAuthSession(array $user): ResponseInterface
    {
        $token = $this->jwtManager->issueToken($user);
        $avatar = (string) ($user['foto_perfil_url'] ?? $user['avatar_url'] ?? '');

        session()->set([
            'user_id'     => (int) $user['id'],
            'user_name'   => (string) $user['name'],
            'user_email'  => (string) $user['email'],
            'user_avatar' => $avatar,
            'jwt_token'   => $token,
        ]);

        setcookie('auth_token', $token, [
            'expires'  => time() + 28800,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if ($this->wantsJson()) {
            return $this->response->setJSON([
                'status' => 'ok',
                'token'  => $token,
                'user'   => [
                    'id'    => (int) $user['id'],
                    'name'  => (string) $user['name'],
                    'email' => (string) $user['email'],
                ],
            ]);
        }

        return redirect()->to('/');
    }

    private function wantsJson(): bool
    {
        $accept      = $this->request->getHeaderLine('Accept');
        $contentType = $this->request->getHeaderLine('Content-Type');

        return stripos($accept, 'application/json') !== false
            || stripos($contentType, 'application/json') !== false
            || $this->request->isAJAX();
    }

    private function respondAuthError(string $message, int $statusCode): ResponseInterface
    {
        if ($this->wantsJson()) {
            return $this->response->setStatusCode($statusCode)->setJSON([
                'status'  => 'error',
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('auth_error', $message);
    }
}
