<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo MVC para la tabla `usuarios`.
 *
 * Gestiona la autenticación con credenciales locales (clave_hash / password_verify)
 * y la vinculación/creación automática de cuentas provenientes de Google OAuth 2.0.
 */
class UserModel extends Model
{
    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $afterFind        = ['aplicarMapeoDespuesDeBuscar'];
    protected $allowedFields    = [
        'nombre',
        'correo',
        'clave_hash',
        'google_id',
        'foto_perfil_url',
        'creado_en',
    ];

    /**
     * Busca un usuario por su correo electrónico (normalizado a minúsculas).
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $correo): ?array
    {
        $correoNormalizado = strtolower(trim($correo));
        if ($correoNormalizado === 'profesor' || $correoNormalizado === 'admin') {
            $correoNormalizado = 'admin@transcripciones.local';
        }

        $usuario = $this->where('correo', $correoNormalizado)->first();

        return is_array($usuario) ? $this->mapearUsuario($usuario) : null;
    }

    /**
     * Autentica un usuario con correo y contraseña.
     *
     * @return array<string, mixed>|null
     */
    public function verifyCredentials(string $correo, string $clave): ?array
    {
        $usuario = $this->findByEmail($correo);
        if ($usuario === null || empty($usuario['clave_hash'])) {
            return null;
        }

        if (! password_verify($clave, (string) $usuario['clave_hash'])) {
            return null;
        }

        return $usuario;
    }

    /**
     * Crea o actualiza un usuario autenticado mediante Google OAuth 2.0.
     *
     * @param array<string, string> $perfilGoogle ['google_id', 'email', 'name', 'avatar_url']
     * @return array<string, mixed>
     */
    public function findOrCreateGoogleUser(array $perfilGoogle): array
    {
        $correo    = strtolower(trim($perfilGoogle['email'] ?? $perfilGoogle['correo'] ?? ''));
        $existente = $this->findByEmail($correo);

        if ($existente !== null) {
            $actualizaciones = [];
            $googleId        = $perfilGoogle['google_id'] ?? null;
            $fotoUrl         = $perfilGoogle['avatar_url'] ?? $perfilGoogle['foto_perfil_url'] ?? null;
            $nombre          = $perfilGoogle['name'] ?? $perfilGoogle['nombre'] ?? null;

            if (! empty($googleId)) {
                $actualizaciones['google_id'] = $googleId;
            }
            if (! empty($fotoUrl)) {
                $actualizaciones['foto_perfil_url'] = $fotoUrl;
            }
            if (! empty($nombre)) {
                $actualizaciones['nombre'] = $nombre;
            }
            if ($actualizaciones !== []) {
                $this->update($existente['id'], $actualizaciones);
                $existente = array_merge($existente, $actualizaciones);
            }

            return $this->mapearUsuario($existente);
        }

        $nombre = $perfilGoogle['name'] ?? $perfilGoogle['nombre'] ?? 'Usuario Google';
        $foto   = $perfilGoogle['avatar_url'] ?? $perfilGoogle['foto_perfil_url'] ?? null;

        $datosNuevo = [
            'nombre'          => $nombre ?: 'Usuario Google',
            'correo'          => $correo,
            'clave_hash'      => null,
            'google_id'       => $perfilGoogle['google_id'] ?? null,
            'foto_perfil_url' => $foto,
            'creado_en'       => date('Y-m-d H:i:s'),
        ];

        $id               = (int) $this->insert($datosNuevo, true);
        $datosNuevo['id'] = $id;

        return $this->mapearUsuario($datosNuevo);
    }

    protected function transformDataToArray($row, string $type): array
    {
        $datos = parent::transformDataToArray($row, $type);

        $aliasAColumna = [
            'name'          => 'nombre',
            'email'         => 'correo',
            'password_hash' => 'clave_hash',
            'avatar_url'    => 'foto_perfil_url',
            'created_at'    => 'creado_en',
        ];

        foreach ($aliasAColumna as $alias => $columna) {
            if (array_key_exists($alias, $datos) && ! array_key_exists($columna, $datos)) {
                $datos[$columna] = $datos[$alias];
            }
        }

        return $datos;
    }

    /**
     * @param array<string, mixed> $eventData
     * @return array<string, mixed>
     */
    protected function aplicarMapeoDespuesDeBuscar(array $eventData): array
    {
        if (! isset($eventData['data']) || ! is_array($eventData['data'])) {
            return $eventData;
        }

        if (! empty($eventData['singleton'])) {
            $eventData['data'] = $this->mapearUsuario($eventData['data']);
        } else {
            $eventData['data'] = array_map([$this, 'mapearUsuario'], $eventData['data']);
        }

        return $eventData;
    }

    /**
     * Mapea y enriquece el registro garantizando variables y claves en español y alias estándar.
     *
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function mapearUsuario(array $usuario): array
    {
        $usuario['id']              = (int) ($usuario['id'] ?? 0);
        $usuario['name']            = $usuario['nombre'] ?? $usuario['name'] ?? '';
        $usuario['email']           = $usuario['correo'] ?? $usuario['email'] ?? '';
        $usuario['avatar_url']      = $usuario['foto_perfil_url'] ?? $usuario['avatar_url'] ?? '';
        $usuario['foto_perfil_url'] = $usuario['avatar_url'];

        return $usuario;
    }
}
