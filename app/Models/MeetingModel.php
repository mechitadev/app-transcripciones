<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo MVC para las tablas `reuniones` e `intervenciones_transcripcion`.
 *
 * Cumple con `.agents/rules/optimization-and-loops.md`:
 *  - Cero consultas SQLite dentro de bucles (`N+1` prohibido).
 *  - Inserción masiva con `insertBatch(..., null, 500)` dentro de una única
 *    transacción atómica (`$db->transStart()` / `$db->transComplete()`).
 */
class MeetingModel extends Model
{
    protected $table            = 'reuniones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'usuario_id',
        'titulo',
        'nombre_archivo_origen',
        'iniciado_en',
        'duracion_minutos',
        'participantes_json',
        'url_original',
        'resumen_ejecutivo',
        'hash_crudo',
        'modelo_ia_usado',
        'creado_en',
    ];

    /**
     * Verifica por hash SHA-256 si el archivo .txt ya fue procesado previamente (idempotencia).
     *
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hashCrudo): ?array
    {
        $fila = $this->where('hash_crudo', $hashCrudo)->first();

        return is_array($fila) ? $this->hydrateMeetingRow($fila) : null;
    }

    /**
     * Lista todas las reuniones ordenadas de la más reciente a la más antigua.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllMeetings(): array
    {
        $filas = $this->orderBy('id', 'DESC')->findAll(100);

        return array_map([$this, 'hydrateMeetingRow'], $filas);
    }

    /**
     * Obtiene una reunión por su ID con `participantes` decodificado y tipos normalizados.
     *
     * @return array<string, mixed>|null
     */
    public function getMeetingByIdHydrated(int $id): ?array
    {
        $fila = $this->find($id);

        return is_array($fila) ? $this->hydrateMeetingRow($fila) : null;
    }

    /**
     * Obtiene las métricas reales del repositorio en una única consulta escalar SQLite (cero N+1).
     *
     * @return array{meetings_count: int, tasks_count: int, decisions_count: int, total_minutes: int}
     */
    public function getPortalStats(): array
    {
        $tablaReuniones  = $this->db->prefixTable('reuniones');
        $tablaTareas     = $this->db->prefixTable('minutas_tareas');
        $tablaDecisiones = $this->db->prefixTable('decisiones_tecnicas');

        $fila = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM {$tablaReuniones}) AS meetings_count,
                (SELECT COUNT(*) FROM {$tablaTareas}) AS tasks_count,
                (SELECT COUNT(*) FROM {$tablaDecisiones}) AS decisions_count,
                (SELECT COALESCE(SUM(duracion_minutos), 0) FROM {$tablaReuniones}) AS total_minutes"
        )->getRowArray();

        return [
            'meetings_count'  => (int) ($fila['meetings_count'] ?? 0),
            'tasks_count'     => (int) ($fila['tasks_count'] ?? 0),
            'decisions_count' => (int) ($fila['decisions_count'] ?? 0),
            'total_minutes'   => (int) ($fila['total_minutes'] ?? 0),
        ];
    }

    /**
     * Guarda una reunión completa, sus intervenciones, tareas y decisiones técnicas
     * en una única transacción SQLite usando `insertBatch` (cero N+1).
     *
     * @param array<string, mixed> $parsedData     Salida de TranscriptParser::parseFile()
     * @param array<string, mixed> $analysisResult Salida de TranscriptAnalyzer::analyze()
     */
    public function saveFullMeeting(
        array $parsedData,
        array $analysisResult,
        ?int $usuarioId,
        string $nombreArchivoOrigen
    ): int {
        $metadata       = $parsedData['metadata'];
        $intervenciones = $parsedData['entries'];

        $this->db->transStart();

        // 1. Insertar cabecera de la reunión
        $datosReunion = [
            'usuario_id'            => $usuarioId,
            'titulo'                => (string) ($metadata['title'] ?? 'Reunión'),
            'nombre_archivo_origen' => $nombreArchivoOrigen,
            'iniciado_en'           => (string) ($metadata['started_at'] ?? ''),
            'duracion_minutos'      => (int) ($metadata['duration_minutes'] ?? 0),
            'participantes_json'    => (string) json_encode($metadata['participants'] ?? [], JSON_UNESCAPED_UNICODE),
            'url_original'          => $metadata['original_url'] ?? null,
            'resumen_ejecutivo'     => (string) ($analysisResult['executive_summary'] ?? ''),
            'hash_crudo'            => (string) $parsedData['raw_hash'],
            'modelo_ia_usado'       => (string) ($analysisResult['ai_model_used'] ?? 'heuristic-local'),
            'creado_en'             => date('Y-m-d H:i:s'),
        ];

        $reunionId = (int) $this->insert($datosReunion, true);

        // 2. Insertar intervenciones en lote (`insertBatch` de hasta 500 filas por bloque)
        $indicesIntervenciones = [];
        if ($intervenciones !== []) {
            $filasIntervencion = [];
            foreach ($intervenciones as $int) {
                $filasIntervencion[] = [
                    'reunion_id'      => $reunionId,
                    'segundo_tiempo'  => (int) $int['timestamp_sec'],
                    'etiqueta_tiempo' => (string) $int['timestamp_label'],
                    'orador'          => (string) $int['speaker'],
                    'contenido'       => (string) $int['content'],
                ];
            }

            $this->db->table('intervenciones_transcripcion')->insertBatch($filasIntervencion, null, 500);

            // Recuperar los IDs generados en una sola consulta para vincular tareas y decisiones
            $intervencionesInsertadas = $this->db->table('intervenciones_transcripcion')
                ->select('id')
                ->where('reunion_id', $reunionId)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($intervencionesInsertadas as $indice => $fila) {
                $indicesIntervenciones[$indice] = (int) $fila['id'];
            }
        }

        // 3. Insertar tareas extraídas en lote (`minutas_tareas`)
        $tareas = $analysisResult['tasks'] ?? [];
        if ($tareas !== []) {
            $filasTarea = [];
            foreach ($tareas as $tarea) {
                $indiceInt    = (int) ($tarea['entry_index'] ?? 0);
                $filasTarea[] = [
                    'reunion_id'        => $reunionId,
                    'intervencion_id'   => $indicesIntervenciones[$indiceInt] ?? null,
                    'etiqueta_tiempo'   => (string) ($tarea['timestamp_label'] ?? '00:00'),
                    'resumen'           => (string) $tarea['summary'],
                    'asignado'          => (string) ($tarea['assignee'] ?? 'Equipo'),
                    'estado'            => (string) ($tarea['status'] ?? 'pending'),
                    'puntaje_confianza' => (float) ($tarea['confidence_score'] ?? 0.85),
                ];
            }

            $this->db->table('minutas_tareas')->insertBatch($filasTarea, null, 500);
        }

        // 4. Insertar decisiones técnicas en lote (`decisiones_tecnicas` -> sincroniza FTS5 por trigger)
        $decisiones = $analysisResult['decisions'] ?? [];
        if ($decisiones !== []) {
            $filasDecision = [];
            foreach ($decisiones as $dec) {
                $indiceInt       = (int) ($dec['entry_index'] ?? 0);
                $filasDecision[] = [
                    'reunion_id'           => $reunionId,
                    'intervencion_id'      => $indicesIntervenciones[$indiceInt] ?? null,
                    'categoria'            => (string) ($dec['category'] ?? 'decision'),
                    'titulo'               => (string) $dec['title'],
                    'justificacion'        => (string) $dec['rationale'],
                    'orador'               => (string) ($dec['speaker'] ?? 'Equipo'),
                    'etiqueta_tiempo'      => (string) ($dec['timestamp_label'] ?? '00:00'),
                    'etiquetas_semanticas' => (string) ($dec['semantic_tags'] ?? ''),
                ];
            }

            $this->db->table('decisiones_tecnicas')->insertBatch($filasDecision, null, 500);
        }

        $this->db->transComplete();

        return $reunionId;
    }

    /**
     * Obtiene el detalle completo de una reunión junto con sus intervenciones,
     * tareas y decisiones técnicas en consultas directas indexadas.
     *
     * @return array<string, mixed>|null
     */
    public function getMeetingFullDetails(int $reunionId): ?array
    {
        $reunion = $this->find($reunionId);
        if (! is_array($reunion)) {
            return null;
        }

        $reunion = $this->hydrateMeetingRow($reunion);

        $intervenciones = $this->db->table('intervenciones_transcripcion')
            ->where('reunion_id', $reunionId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $intervencionesMapeadas = array_map(static function (array $fila): array {
            $fila['id']              = (int) $fila['id'];
            $fila['reunion_id']      = (int) $fila['reunion_id'];
            $fila['segundo_tiempo']  = (int) ($fila['segundo_tiempo'] ?? 0);
            $fila['etiqueta_tiempo'] = $fila['etiqueta_tiempo'] ?? '00:00';
            $fila['orador']          = $fila['orador'] ?? '';
            $fila['contenido']       = $fila['contenido'] ?? '';

            // Alias retrocompatibles
            $fila['meeting_id']      = $fila['reunion_id'];
            $fila['timestamp_sec']   = $fila['segundo_tiempo'];
            $fila['timestamp_label'] = $fila['etiqueta_tiempo'];
            $fila['speaker']         = $fila['orador'];
            $fila['content']         = $fila['contenido'];

            return $fila;
        }, $intervenciones);

        $taskModel = new TaskModel();
        $tareas    = $taskModel->getByMeeting($reunionId);

        $decisionModel = new DecisionModel();
        $decisiones    = $this->db->table('decisiones_tecnicas')
            ->where('reunion_id', $reunionId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $decisionesMapeadas = array_map([$decisionModel, 'mapearDecision'], $decisiones);

        return [
            'meeting'        => $reunion,
            'entries'        => $intervencionesMapeadas,
            'tasks'          => $tareas,
            'decisions'      => $decisionesMapeadas,
            // Claves nativas en español
            'reunion'        => $reunion,
            'intervenciones' => $intervencionesMapeadas,
            'minutas_tareas' => $tareas,
            'decisiones'     => $decisionesMapeadas,
        ];
    }

    /**
     * Genera el documento Markdown UTF-8 de la minuta completa y su nombre de archivo seguro.
     *
     * @return array{filename: string, markdown: string}|null
     */
    public function exportMeetingMarkdown(int $reunionId): ?array
    {
        $detalles = $this->getMeetingFullDetails($reunionId);
        if ($detalles === null) {
            return null;
        }

        $reunion       = $detalles['meeting'];
        $tareas        = $detalles['tasks'];
        $decisiones    = $detalles['decisions'];
        $titulo        = trim((string) ($reunion['title'] ?? 'Reunión'));
        $participantes = ! empty($reunion['participants']) && is_array($reunion['participants'])
            ? implode(', ', $reunion['participants'])
            : 'Sin participantes registrados';
        $inicio        = trim((string) ($reunion['started_at'] ?? '')) !== ''
            ? (string) $reunion['started_at']
            : 'No especificado';
        $duracion      = (int) ($reunion['duration_minutes'] ?? 0);
        $archivoFuente = trim((string) ($reunion['source_filename'] ?? '')) !== ''
            ? (string) $reunion['source_filename']
            : 'N/A';
        $motorIa       = trim((string) ($reunion['ai_model_used'] ?? '')) !== ''
            ? (string) $reunion['ai_model_used']
            : 'heuristic-local';
        $urlOriginal   = trim((string) ($reunion['original_url'] ?? ''));
        $resumen       = trim((string) ($reunion['executive_summary'] ?? '')) !== ''
            ? trim((string) $reunion['executive_summary'])
            : 'Sin minuta ejecutiva registrada.';

        $lineas   = [];
        $lineas[] = '# ' . ($titulo !== '' ? $titulo : 'Reunión');
        $lineas[] = '';
        $lineas[] = '- **Inicio:** ' . $inicio;
        $lineas[] = '- **Duración:** ' . $duracion . ' min';
        $lineas[] = '- **Participantes:** ' . $participantes;
        $lineas[] = '- **Archivo fuente:** ' . $archivoFuente;
        $lineas[] = '- **Motor IA:** ' . $motorIa;
        if ($urlOriginal !== '') {
            $lineas[] = '- **URL original:** ' . $urlOriginal;
        }

        $lineas[] = '';
        $lineas[] = '## Minuta ejecutiva';
        $lineas[] = '';
        $lineas[] = $resumen;

        $lineas[] = '';
        $lineas[] = '## Tareas y compromisos';
        $lineas[] = '';
        if ($tareas === []) {
            $lineas[] = 'Sin tareas registradas.';
        } else {
            foreach ($tareas as $tarea) {
                $completada = in_array($tarea['status'] ?? '', ['done', 'completada'], true);
                $marca      = $completada ? '[x]' : '[ ]';
                $minuto     = trim((string) ($tarea['timestamp_label'] ?? '00:00'));
                $asignado   = trim((string) ($tarea['assignee'] ?? 'Equipo'));
                $textoTarea = trim((string) ($tarea['summary'] ?? ''));
                $lineas[]   = sprintf('- %s [%s] **%s:** %s', $marca, $minuto, $asignado, $textoTarea);
            }
        }

        $lineas[] = '';
        $lineas[] = '## Decisiones técnicas';
        $lineas[] = '';
        if ($decisiones === []) {
            $lineas[] = 'Sin decisiones técnicas registradas.';
        } else {
            $etiquetasCategoria = [
                'decision'     => 'Decisión técnica',
                'blocker'      => 'Bloqueo / Limitante',
                'bloqueo'      => 'Bloqueo / Limitante',
                'architecture' => 'Flujo / Arquitectura',
                'arquitectura' => 'Flujo / Arquitectura',
            ];
            foreach ($decisiones as $dec) {
                $catClave      = (string) ($dec['category'] ?? 'decision');
                $catEtiqueta   = $etiquetasCategoria[$catClave] ?? 'Decisión técnica';
                $minuto        = trim((string) ($dec['timestamp_label'] ?? '00:00'));
                $tituloDec     = trim((string) ($dec['title'] ?? ''));
                $orador        = trim((string) ($dec['speaker'] ?? 'Equipo'));
                $justificacion = trim((string) ($dec['rationale'] ?? ''));
                $etiquetas     = trim((string) ($dec['semantic_tags'] ?? ''));

                $lineas[] = sprintf('### [%s] %s (%s)', $minuto, $tituloDec, $catEtiqueta);
                $lineas[] = '- **Categoría:** ' . $catEtiqueta;
                $lineas[] = '- **Autor:** ' . $orador;
                $lineas[] = '- **Justificación:** ' . $justificacion;
                if ($etiquetas !== '') {
                    $lineas[] = '- **Etiquetas:** ' . $etiquetas;
                }
                $lineas[] = '';
            }
        }

        $slug = $this->slugifyFilename($titulo);

        return [
            'filename' => sprintf('minuta-%d-%s.md', $reunionId, $slug),
            'markdown' => rtrim(implode("\n", $lineas)) . "\n",
        ];
    }

    /**
     * Elimina una reunión y todos sus registros hijos dentro de una transacción.
     */
    public function deleteMeetingCascade(int $reunionId): bool
    {
        $this->db->transStart();
        $this->db->table('decisiones_tecnicas')->where('reunion_id', $reunionId)->delete();
        $this->db->table('minutas_tareas')->where('reunion_id', $reunionId)->delete();
        $this->db->table('intervenciones_transcripcion')->where('reunion_id', $reunionId)->delete();
        $this->delete($reunionId);
        $this->db->transComplete();

        return $this->db->transStatus();
    }

    protected function transformDataToArray($row, string $type): array
    {
        $datos = parent::transformDataToArray($row, $type);

        $aliasAColumna = [
            'user_id'           => 'usuario_id',
            'title'             => 'titulo',
            'source_filename'   => 'nombre_archivo_origen',
            'started_at'        => 'iniciado_en',
            'duration_minutes'  => 'duracion_minutos',
            'original_url'      => 'url_original',
            'executive_summary' => 'resumen_ejecutivo',
            'raw_hash'          => 'hash_crudo',
            'ai_model_used'     => 'modelo_ia_usado',
            'created_at'        => 'creado_en',
        ];

        foreach ($aliasAColumna as $alias => $columna) {
            if (array_key_exists($alias, $datos) && ! array_key_exists($columna, $datos)) {
                $datos[$columna] = $datos[$alias];
            }
        }

        return $datos;
    }

    /**
     * Decodifica `participantes_json` a un array limpio para consumo de controladores y vistas.
     *
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function hydrateMeetingRow(array $fila): array
    {
        $participantes            = json_decode((string) ($fila['participantes_json'] ?? '[]'), true);
        $fila['participantes']    = is_array($participantes) ? $participantes : [];
        $fila['id']               = (int) $fila['id'];
        $fila['duracion_minutos'] = (int) ($fila['duracion_minutos'] ?? 0);

        // Alias retrocompatibles para contratos MVC y frontend
        $fila['title']             = $fila['titulo'] ?? '';
        $fila['source_filename']   = $fila['nombre_archivo_origen'] ?? '';
        $fila['started_at']        = $fila['iniciado_en'] ?? '';
        $fila['duration_minutes']  = $fila['duracion_minutos'];
        $fila['participants']      = $fila['participantes'];
        $fila['original_url']      = $fila['url_original'] ?? null;
        $fila['executive_summary'] = $fila['resumen_ejecutivo'] ?? '';
        $fila['raw_hash']          = $fila['hash_crudo'] ?? '';
        $fila['ai_model_used']     = $fila['modelo_ia_usado'] ?? 'heuristic-local';
        $fila['created_at']        = $fila['creado_en'] ?? '';

        return $fila;
    }

    private function slugifyFilename(string $texto): string
    {
        $normalizado = strtr(mb_strtolower($texto, 'UTF-8'), [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $normalizado);
        $slug = trim(substr($slug, 0, 50), '-');

        return $slug !== '' ? $slug : 'reunion';
    }
}
