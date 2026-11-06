<?php

namespace App\Controllers;

use App\Libraries\AI\FreeModelClient;
use App\Libraries\AI\TranscriptAnalyzer;
use App\Libraries\TranscriptParser;
use App\Models\MeetingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controlador MVC de Reuniones y Transcripciones (`MeetingController`).
 *
 * Coordina:
 *  - Renderizado del panel principal (`GET /`)
 *  - Subida segura e idempotente de archivos `.txt` (`POST /api/meetings/upload`)
 *  - Carga con un clic del archivo real de prueba `.agents/resources/formato-transcripcion/formato-real.txt`
 *  - Consulta detallada, edición inline, exportación Markdown y eliminación de reuniones
 *    (`GET/PATCH/DELETE /api/meetings/(:num)` y `GET /api/meetings/(:num)/export`)
 */
class MeetingController extends BaseController
{
    private MeetingModel $meetingModel;
    private TranscriptParser $parser;
    private TranscriptAnalyzer $analyzer;

    public function __construct()
    {
        $this->meetingModel = new MeetingModel();
        $this->parser       = new TranscriptParser();
        $this->analyzer     = new TranscriptAnalyzer();
    }

    /**
     * Renderiza la vista principal del dashboard (`GET /`).
     * Si el usuario no ha iniciado sesión, presenta directamente la interfaz de acceso
     * en la misma URL sin redirecciones ni requerir un servidor aparte.
     */
    public function index()
    {
        if (! $this->isAuthenticated()) {
            $googleClient = new \App\Libraries\Auth\GoogleOAuthClient();

            return view('auth/login', [
                'googleClientId' => $googleClient->getClientId(),
                'portalStats'    => $this->meetingModel->getPortalStats(),
            ]);
        }

        $userId   = (int) session()->get('user_id');
        $meetings = $this->meetingModel->listAllMeetings($userId);

        $activeMeetingData = null;
        if ($meetings !== []) {
            $activeMeetingData = $this->meetingModel->getMeetingFullDetails((int) $meetings[0]['id']);
        }

        $freeModelClient = new FreeModelClient();
        $userRow         = (new \App\Models\UserModel())->find($userId);

        return view('dashboard/index', [
            'meetings'          => $meetings,
            'activeMeetingData' => $activeMeetingData,
            'aiEngineName'      => $freeModelClient->getActiveEngineName(),
            'semanticConcepts'  => array_keys(config('Extraction')->semanticSynonyms),
            'currentUser'       => [
                'id'         => $userId,
                'name'       => (string) ($userRow['name'] ?? session()->get('user_name')),
                'email'      => (string) ($userRow['email'] ?? session()->get('user_email')),
                'avatar_url' => (string) ($userRow['avatar_url'] ?? session()->get('user_avatar') ?? ''),
            ],
        ]);
    }

    /**
     * Comprueba si existe una sesión activa o un token JWT válido.
     */
    private function isAuthenticated(): bool
    {
        $session = session();
        if ($session->has('user_id') && (int) $session->get('user_id') > 0) {
            return true;
        }

        $token      = '';
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (stripos($authHeader, 'Bearer ') === 0) {
            $token = trim(substr($authHeader, 7));
        } elseif (isset($_COOKIE['auth_token']) && is_string($_COOKIE['auth_token'])) {
            $token = trim($_COOKIE['auth_token']);
        }

        if ($token !== '') {
            $jwt     = new \App\Libraries\Auth\JwtManager();
            $payload = $jwt->verifyToken($token);
            if ($payload !== null && ! empty($payload['sub'])) {
                $session->set([
                    'user_id'    => (int) $payload['sub'],
                    'user_name'  => (string) ($payload['name'] ?? 'Usuario'),
                    'user_email' => (string) ($payload['email'] ?? ''),
                    'jwt_token'  => $token,
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve el listado de reuniones procesadas (`GET /api/meetings`).
     */
    public function list(): ResponseInterface
    {
        $userId = session()->has('user_id') ? (int) session()->get('user_id') : null;

        return $this->response->setJSON([
            'status'   => 'ok',
            'meetings' => $this->meetingModel->listAllMeetings($userId),
        ]);
    }

    /**
     * Procesa un archivo `.txt` subido por el usuario o carga el archivo de muestra (`POST /api/meetings/upload`).
     */
    public function upload(): ResponseInterface
    {
        $payload   = $this->getRequestPayload();
        $useSample = $this->request->getPost('use_sample') ?? ($payload['use_sample'] ?? null);

        if (! empty($useSample)) {
            $samplePath = ROOTPATH . '.agents/resources/formato-transcripcion/formato-real.txt';
            if (! is_file($samplePath)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'No se encontró el archivo de muestra formato-real.txt.',
                ]);
            }

            $result = $this->ingestFile($samplePath, 'formato-real.txt');

            return $this->response->setJSON([
                'status'       => 'ok',
                'deduplicated' => $result['deduplicated'],
                'message'      => $result['deduplicated'] ? 'Este archivo ya estaba registrado por su huella SHA256. Se abrió la reunión existente.' : 'Transcripción procesada e indexada correctamente.',
                'meeting_id'   => $result['meeting_id'],
                'data'         => $this->meetingModel->getMeetingFullDetails($result['meeting_id']),
            ]);
        }

        $file = $this->request->getFile('transcript_file');
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'field'   => 'transcript_file',
                'message' => 'Archivo no válido. Selecciona un archivo de texto plano .txt menor a 5 MB.',
            ]);
        }

        // Validación estricta de seguridad: extensión .txt, MIME text/plain y tamaño <= 5MB
        $ext      = strtolower($file->getClientExtension());
        $mimeType = strtolower($file->getMimeType());
        $size     = $file->getSize();

        if ($ext !== 'txt' || $size > 5 * 1024 * 1024 || (strpos($mimeType, 'text/') !== 0 && $mimeType !== 'application/octet-stream')) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'field'   => 'transcript_file',
                'message' => 'Archivo no válido. Selecciona un archivo de texto plano .txt menor a 5 MB.',
            ]);
        }

        $originalClientName = $file->getClientName();
        $randomName         = $file->getRandomName();
        $uploadDir          = WRITEPATH . 'uploads';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $file->move($uploadDir, $randomName);
        $savedPath = $uploadDir . DIRECTORY_SEPARATOR . $randomName;

        $customTitle = trim((string) $this->request->getPost('title'));
        $result      = $this->ingestFile($savedPath, $originalClientName, $customTitle !== '' ? $customTitle : null);

        return $this->response->setJSON([
            'status'       => 'ok',
            'deduplicated' => $result['deduplicated'],
            'message'      => $result['deduplicated'] ? 'Este archivo ya estaba registrado por su huella SHA256. Se abrió la reunión existente.' : 'Transcripción procesada e indexada correctamente.',
            'meeting_id'   => $result['meeting_id'],
            'data'         => $this->meetingModel->getMeetingFullDetails($result['meeting_id']),
        ]);
    }

    /**
     * Obtiene el detalle completo de una reunión (`GET /api/meetings/(:num)`).
     */
    public function show(int $id): ResponseInterface
    {
        $details = $this->meetingModel->getMeetingFullDetails($id);
        if ($details === null || ! $this->belongsToCurrentUser($details['meeting'])) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Reunión no encontrada.',
            ]);
        }

        return $this->response->setJSON([
            'status'    => 'ok',
            'meeting'   => $details['meeting'],
            'entries'   => $details['entries'],
            'tasks'     => $details['tasks'],
            'decisions' => $details['decisions'],
        ]);
    }

    /**
     * Actualiza el título o la minuta ejecutiva de una reunión (`PATCH/POST /api/meetings/(:num)`).
     */
    public function update(int $id): ResponseInterface
    {
        $existing = $this->meetingModel->getMeetingByIdHydrated($id);
        if ($existing === null || ! $this->belongsToCurrentUser($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'La reunión solicitada ya no existe.',
            ]);
        }

        $payload = $this->getRequestPayload();
        $updates = [];

        if (array_key_exists('title', $payload) || array_key_exists('titulo', $payload)) {
            $title = trim((string) ($payload['titulo'] ?? $payload['title']));
            if ($title !== '') {
                $updates['titulo'] = mb_substr($title, 0, 255, 'UTF-8');
            }
        }

        if (array_key_exists('executive_summary', $payload) || array_key_exists('resumen_ejecutivo', $payload)) {
            $summary = trim((string) ($payload['resumen_ejecutivo'] ?? $payload['executive_summary']));
            if ($summary !== '') {
                $updates['resumen_ejecutivo'] = $summary;
            }
        }

        if ($updates === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'field'   => 'title',
                'message' => 'Completa todos los campos requeridos con un formato válido.',
            ]);
        }

        $this->meetingModel->update($id, $updates);
        $hydrated = $this->meetingModel->getMeetingByIdHydrated($id);

        return $this->response->setStatusCode(200)->setJSON([
            'status'  => 'ok',
            'message' => 'Cambios guardados en la reunión.',
            'meeting' => $hydrated,
        ]);
    }

    /**
     * Exporta la minuta ejecutiva, tareas y decisiones técnicas en Markdown UTF-8 (`GET /api/meetings/(:num)/export`).
     */
    public function export(int $id): ResponseInterface
    {
        $existing = $this->meetingModel->find($id);
        if (! is_array($existing) || ! $this->belongsToCurrentUser($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'La reunión solicitada ya no existe.',
            ]);
        }

        $export = $this->meetingModel->exportMeetingMarkdown($id);
        if ($export === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'La reunión solicitada ya no existe.',
            ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setContentType('text/markdown', 'UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"')
            ->setBody($export['markdown']);
    }

    /**
     * Elimina una reunión y todos sus registros hijos (`DELETE /api/meetings/(:num)`).
     */
    public function delete(int $id): ResponseInterface
    {
        $existing = $this->meetingModel->find($id);
        if (! is_array($existing) || ! $this->belongsToCurrentUser($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Reunión no encontrada.',
            ]);
        }

        $this->meetingModel->deleteMeetingCascade($id);

        return $this->response->setJSON([
            'status'     => 'ok',
            'deleted_id' => $id,
        ]);
    }

    /**
     * Verifica que una reunión pertenezca al usuario autenticado actual.
     *
     * @param array<string, mixed> $meeting
     */
    private function belongsToCurrentUser(array $meeting): bool
    {
        $userId = session()->has('user_id') ? (int) session()->get('user_id') : 0;
        $owner  = isset($meeting['usuario_id']) ? (int) $meeting['usuario_id'] : 0;

        if ($userId > 0 && $owner > 0) {
            return $owner === $userId;
        }

        return true;
    }

    /**
     * Ejecuta el flujo completo de ingesta (Parser O(n) -> Verificación SHA-256 -> Analyzer -> SQLite Batch).
     *
     * @return array{meeting_id: int, deduplicated: bool}
     */
    private function ingestFile(string $filePath, string $sourceFilename, ?string $customTitle = null): array
    {
        $parsed = $this->parser->parseFile($filePath, $customTitle);
        $userId = session()->has('user_id') ? (int) session()->get('user_id') : null;

        if ($userId !== null && $userId > 0) {
            $parsed['raw_hash'] = hash('sha256', $userId . ':' . $parsed['raw_hash']);
        }

        // Idempotencia por hash SHA-256 del archivo en el espacio del usuario
        $existing = $this->meetingModel->findByHash($parsed['raw_hash'], $userId);
        if ($existing !== null) {
            return [
                'meeting_id'   => (int) $existing['id'],
                'deduplicated' => true,
            ];
        }

        $analysis  = $this->analyzer->analyze($parsed['metadata'], $parsed['entries']);
        $meetingId = $this->meetingModel->saveFullMeeting($parsed, $analysis, $userId, $sourceFilename);

        return [
            'meeting_id'   => $meetingId,
            'deduplicated' => false,
        ];
    }
}
