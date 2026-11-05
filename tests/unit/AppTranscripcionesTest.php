<?php

use App\Libraries\AI\TranscriptAnalyzer;
use App\Libraries\Auth\GoogleOAuthClient;
use App\Libraries\Auth\JwtManager;
use App\Libraries\TranscriptParser;
use App\Models\DecisionModel;
use App\Models\MeetingModel;
use App\Models\TaskModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Suite de pruebas automatizadas para App-Transcripciones (MVC + SQLite FTS5).
 *
 * @internal
 */
final class AppTranscripcionesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testParserExtractsRealFormatInSinglePassAndFiltersTactiqNoise(): void
    {
        $samplePath = ROOTPATH . '.agents/resources/formato-transcripcion/formato-real.txt';
        $this->assertFileExists($samplePath);

        $parser = new TranscriptParser();
        $result = $parser->parseFile($samplePath);

        $this->assertSame(64, strlen($result['raw_hash']));
        $this->assertSame('23/10/2026, 8:48:01', $result['metadata']['started_at']);
        $this->assertSame(7, $result['metadata']['duration_minutes']);
        $this->assertSame(['Alexander pertus', 'Vanesa Machado'], $result['metadata']['participants']);
        $this->assertSame(
            'https://app.tactiq.io/api/2/u/m/r/QWoAhHc91MTNgDaFbWx3?o=txt',
            $result['metadata']['original_url']
        );

        $this->assertNotEmpty($result['entries']);

        // Verificar que el mensaje promocional de Tactiq AI (00:29) fue filtrado
        foreach ($result['entries'] as $entry) {
            $this->assertStringNotContainsString('Tactiq AI', $entry['content']);
        }
    }

    public function testAnalyzerAndBatchPersistenceWithSemanticSearchFts5(): void
    {
        $samplePath = ROOTPATH . '.agents/resources/formato-transcripcion/formato-real.txt';
        $parser     = new TranscriptParser();
        $analyzer   = new TranscriptAnalyzer();

        $parsed   = $parser->parseFile($samplePath);
        $analysis = $analyzer->analyze($parsed['metadata'], $parsed['entries']);

        $this->assertNotEmpty($analysis['executive_summary']);
        $this->assertNotEmpty($analysis['tasks']);
        $this->assertNotEmpty($analysis['decisions']);

        $meetingModel = new MeetingModel();
        $meetingId    = $meetingModel->saveFullMeeting($parsed, $analysis, 1, 'formato-real.txt');
        $this->assertGreaterThan(0, $meetingId);

        // Verificar idempotencia por hash SHA-256
        $duplicateCheck = $meetingModel->findByHash($parsed['raw_hash']);
        $this->assertNotNull($duplicateCheck);
        $this->assertSame($meetingId, (int) $duplicateCheck['id']);

        // Verificar Módulo 1: Tareas extraídas y actualización de estado
        $taskModel = new TaskModel();
        $tasks     = $taskModel->getByMeeting($meetingId);
        $this->assertNotEmpty($tasks);

        $firstTaskId = (int) $tasks[0]['id'];
        $taskModel->update($firstTaskId, ['status' => 'done']);
        $updatedTask = $taskModel->find($firstTaskId);
        $this->assertSame('done', $updatedTask['status']);

        // Verificar Módulo 2: Buscador Semántico de Decisiones Técnicas con SQLite FTS5 + Sinónimos
        $decisionModel = new DecisionModel();

        // 1) Búsqueda directa ("whatsapp")
        $resWhatsapp = $decisionModel->searchDecisions('whatsapp', null, null, $meetingId);
        $this->assertNotEmpty($resWhatsapp['results']);

        // 2) Búsqueda por concepto expandido ("limpieza" -> expande a rutina, registros, basuras, campo de estados)
        $resSemantic = $decisionModel->searchDecisions('limpieza', null, null, $meetingId);
        $this->assertNotEmpty($resSemantic['expanded_terms']);
        $this->assertNotEmpty($resSemantic['results']);

        // 3) Filtro por categoría 'blocker'
        $resBlockers = $decisionModel->searchDecisions('', 'blocker', null, $meetingId);
        $this->assertNotEmpty($resBlockers['results']);
        foreach ($resBlockers['results'] as $row) {
            $this->assertSame('blocker', $row['category']);
        }
    }

    public function testJwtAndGoogleAndLocalAuthentication(): void
    {
        $jwt   = new JwtManager('test-secret-key-256-bits', 3600);
        $token = $jwt->issueToken([
            'id'    => 99,
            'name'  => 'Tester JWT',
            'email' => 'tester@transcripciones.local',
        ]);

        $payload = $jwt->verifyToken($token);
        $this->assertNotNull($payload);
        $this->assertSame(99, $payload['sub']);
        $this->assertSame('tester@transcripciones.local', $payload['email']);

        // Token adulterado debe ser rechazado
        $this->assertNull($jwt->verifyToken($token . 'tampered'));

        // Verificar usuario semilla y Google OAuth en UserModel
        $userModel = new UserModel();
        $admin     = $userModel->verifyCredentials('admin@transcripciones.local', 'Admin123*');
        $this->assertNotNull($admin);

        $googleClient  = new GoogleOAuthClient('');
        $googleProfile = $googleClient->verifyPayload([
            'email' => 'google.dev@transcripciones.local',
            'name'  => 'Dev Google',
        ]);
        $this->assertNotNull($googleProfile);

        $googleUser = $userModel->findOrCreateGoogleUser($googleProfile);
        $this->assertSame('google.dev@transcripciones.local', $googleUser['email']);
    }

    public function testPortalStatsMeetingUpdateAndMarkdownExportEndpoints(): void
    {
        $meetingModel = new MeetingModel();

        // 1. Base vacía devuelve conteos enteros en cero mediante 1 consulta escalar
        $emptyStats = $meetingModel->getPortalStats();
        $this->assertSame([
            'meetings_count'  => 0,
            'tasks_count'     => 0,
            'decisions_count' => 0,
            'total_minutes'   => 0,
        ], $emptyStats);

        // 2. Ingestar muestra real y verificar métricas escalares reales
        $samplePath = ROOTPATH . '.agents/resources/formato-transcripcion/formato-real.txt';
        $parser     = new TranscriptParser();
        $analyzer   = new TranscriptAnalyzer();
        $parsed     = $parser->parseFile($samplePath);
        $analysis   = $analyzer->analyze($parsed['metadata'], $parsed['entries']);
        $meetingId  = $meetingModel->saveFullMeeting($parsed, $analysis, 1, 'formato-real.txt');

        $stats = $meetingModel->getPortalStats();
        $this->assertSame(1, $stats['meetings_count']);
        $this->assertSame(count($analysis['tasks']), $stats['tasks_count']);
        $this->assertSame(count($analysis['decisions']), $stats['decisions_count']);
        $this->assertSame(7, $stats['total_minutes']);

        $authSession = [
            'user_id'    => 1,
            'user_name'  => 'Administrador Demo',
            'user_email' => 'admin@transcripciones.local',
        ];

        // 3. Sin sesión devuelve 401 en PATCH /api/meetings/(:num)
        $unauth = $this->withHeaders(['Accept' => 'application/json'])
            ->withBodyFormat('json')
            ->patch("api/meetings/{$meetingId}", [
                'title' => 'Intento sin sesión',
            ]);
        $unauth->assertStatus(401);

        // 4. Payload vacío devuelve 422 con field = title
        $invalid = $this->withSession($authSession)
            ->withBodyFormat('json')
            ->patch("api/meetings/{$meetingId}", ['title' => '   ']);
        $invalid->assertStatus(422);
        $invalidJson = json_decode($invalid->getJSON(), true);
        $this->assertSame('error', $invalidJson['status']);
        $this->assertSame('title', $invalidJson['field']);

        // 5. Reunión inexistente devuelve 404
        $notFound = $this->withSession($authSession)
            ->withBodyFormat('json')
            ->patch('api/meetings/99999', ['title' => 'No existe']);
        $notFound->assertStatus(404);

        // 6. Actualización válida de título y minuta ejecutiva hidrata la reunión
        $updateRes = $this->withSession($authSession)
            ->withBodyFormat('json')
            ->patch("api/meetings/{$meetingId}", [
                'title'             => 'Revisión de pasarela Zelle y WhatsApp',
                'executive_summary' => 'Minuta ejecutiva editada y validada por ingeniería.',
            ]);
        $updateRes->assertStatus(200);
        $updatePayload = json_decode($updateRes->getJSON(), true);
        $this->assertSame('ok', $updatePayload['status']);
        $this->assertSame('Revisión de pasarela Zelle y WhatsApp', $updatePayload['meeting']['title']);
        $this->assertSame('Minuta ejecutiva editada y validada por ingeniería.', $updatePayload['meeting']['executive_summary']);
        $this->assertSame(['Alexander pertus', 'Vanesa Machado'], $updatePayload['meeting']['participants']);

        // 7. Marcar la primera tarea como completada y verificar exportación Markdown UTF-8
        $taskModel = new TaskModel();
        $tasks     = $taskModel->getByMeeting($meetingId);
        $taskModel->update((int) $tasks[0]['id'], ['status' => 'done']);

        $exportNotFound = $this->withSession($authSession)->get('api/meetings/99999/export');
        $exportNotFound->assertStatus(404);

        $exportRes = $this->withSession($authSession)->get("api/meetings/{$meetingId}/export");
        $exportRes->assertStatus(200);
        $this->assertStringContainsString('text/markdown', $exportRes->response()->getHeaderLine('Content-Type'));
        $this->assertSame(
            "attachment; filename=\"minuta-{$meetingId}-revision-de-pasarela-zelle-y-whatsapp.md\"",
            $exportRes->response()->getHeaderLine('Content-Disposition')
        );

        $markdown = (string) $exportRes->response()->getBody();
        $this->assertStringContainsString('# Revisión de pasarela Zelle y WhatsApp', $markdown);
        $this->assertStringContainsString('- **Inicio:** 23/10/2026, 8:48:01', $markdown);
        $this->assertStringContainsString('- **Duración:** 7 min', $markdown);
        $this->assertStringContainsString('## Minuta ejecutiva', $markdown);
        $this->assertStringContainsString('Minuta ejecutiva editada y validada por ingeniería.', $markdown);
        $this->assertStringContainsString('## Tareas y compromisos', $markdown);
        $this->assertStringContainsString('- [x] [', $markdown);
        $this->assertStringContainsString('- [ ] [', $markdown);
        $this->assertStringContainsString('## Decisiones técnicas', $markdown);
    }

    public function testManualDecisionCreateAndDeleteSyncsFts5Immediately(): void
    {
        $samplePath   = ROOTPATH . '.agents/resources/formato-transcripcion/formato-real.txt';
        $parser       = new TranscriptParser();
        $analyzer     = new TranscriptAnalyzer();
        $meetingModel = new MeetingModel();

        $parsed    = $parser->parseFile($samplePath);
        $analysis  = $analyzer->analyze($parsed['metadata'], $parsed['entries']);
        $meetingId = $meetingModel->saveFullMeeting($parsed, $analysis, 1, 'formato-real.txt');

        $authSession = [
            'user_id'    => 1,
            'user_name'  => 'Administrador Demo',
            'user_email' => 'admin@transcripciones.local',
        ];

        // 1. Validación 422 cuando falta título o justificación y 404 si la reunión no existe
        $missingTitle = $this->withSession($authSession)
            ->withBodyFormat('json')
            ->post('api/decisions', [
                'meeting_id' => $meetingId,
                'title'      => '',
                'rationale'  => 'Contexto válido',
            ]);
        $missingTitle->assertStatus(422);

        $missingMeeting = $this->withSession($authSession)
            ->withBodyFormat('json')
            ->post('api/decisions', [
                'meeting_id' => 99999,
                'title'      => 'Acuerdo huérfano',
                'rationale'  => 'No existe la reunión',
            ]);
        $missingMeeting->assertStatus(404);

        // 2. Alta manual sin semantic_tags infiere etiquetas y sincroniza FTS5 por trigger
        $createRes = $this->withSession($authSession)
            ->withBodyFormat('json')
            ->post('api/decisions', [
                'meeting_id'      => $meetingId,
                'category'        => 'architecture',
                'title'           => 'Adoptar protocolo KyberCheckout antes del pago',
                'rationale'       => 'Asegura validación criptográfica del formulario antes del pago de la inscripción.',
                'speaker'         => 'Vanesa Machado',
                'timestamp_label' => '04:15',
            ]);
        $createRes->assertStatus(201);
        $createdPayload = json_decode($createRes->getJSON(), true);
        $this->assertSame('ok', $createdPayload['status']);

        $decisionId = (int) $createdPayload['decision']['id'];
        $this->assertGreaterThan(0, $decisionId);
        $this->assertSame('architecture', $createdPayload['decision']['category']);
        $this->assertStringContainsString('pago', $createdPayload['decision']['semantic_tags']);

        // Verificar presencia inmediata en el índice virtual SQLite FTS5
        $decisionModel = new DecisionModel();
        $ftsAfterInsert = $decisionModel->searchDecisions('KyberCheckout', null, null, $meetingId);
        $this->assertCount(1, $ftsAfterInsert['results']);
        $this->assertSame($decisionId, (int) $ftsAfterInsert['results'][0]['id']);

        // 3. Eliminación sincroniza la baja inmediata en technical_decisions_fts
        $deleteRes = $this->withSession($authSession)->delete("api/decisions/{$decisionId}");
        $deleteRes->assertStatus(200);
        $deletePayload = json_decode($deleteRes->getJSON(), true);
        $this->assertSame('ok', $deletePayload['status']);
        $this->assertSame($decisionId, $deletePayload['deleted_id']);

        $ftsAfterDelete = $decisionModel->searchDecisions('KyberCheckout', null, null, $meetingId);
        $this->assertSame([], $ftsAfterDelete['results']);

        // Segundo borrado devuelve 404
        $deleteAgain = $this->withSession($authSession)->delete("api/decisions/{$decisionId}");
        $deleteAgain->assertStatus(404);
    }

    public function testSanitizedFixturesParseAndExtractTasksAndDecisions(): void
    {
        $fixturesDir = ROOTPATH . 'tests/fixtures/transcripts/';
        $this->assertDirectoryExists($fixturesDir);

        $parser   = new TranscriptParser();
        $analyzer = new TranscriptAnalyzer();

        $files = glob($fixturesDir . '*.txt');
        $this->assertCount(4, $files);

        foreach ($files as $filePath) {
            $parsed = $parser->parseFile($filePath);

            $this->assertNotEmpty($parsed['metadata']['title']);
            $this->assertNotEmpty($parsed['metadata']['started_at']);
            $this->assertGreaterThan(0, $parsed['metadata']['duration_minutes']);
            $this->assertNotEmpty($parsed['metadata']['participants']);
            $this->assertNotEmpty($parsed['entries']);

            // Validar que no hay ruido de Tactiq AI en las intervenciones parseadas
            foreach ($parsed['entries'] as $entry) {
                $this->assertStringNotContainsString('Tactiq AI', $entry['content']);
            }

            $analysis = $analyzer->analyze($parsed['metadata'], $parsed['entries']);
            $this->assertNotEmpty($analysis['tasks'], 'Debe extraer al menos una tarea de ' . basename($filePath));
            $this->assertNotEmpty($analysis['decisions'], 'Debe extraer al menos una decisión de ' . basename($filePath));
            $this->assertNotEmpty($analysis['executive_summary']);
        }
    }
}

