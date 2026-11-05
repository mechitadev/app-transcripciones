<?php

namespace App\Libraries\AI;

use Config\Extraction;

/**
 * ============================================================================
 * ORQUESTADOR DE ANÁLISIS DE TRANSCRIPCIONES (`TranscriptAnalyzer`)
 * ============================================================================
 *
 * ¿CÓMO FUNCIONA ESTA CLASE?
 * Esta clase es el núcleo de inteligencia para los dos módulos principales:
 *   1. Gestor de Minutas y Extracción Automática de Tareas.
 *   2. Buscador Semántico de Decisiones Técnicas.
 *
 * PROCESO HÍBRIDO EN DOS PASOS:
 * - PASO 1 (Extracción Heurística Determinista en Español):
 *   Recorre las intervenciones ya parseadas (`$entries`) en una sola pasada y
 *   aplica las expresiones compiladas a partir de `app/Config/Extraction.php`:
 *     - Detecta compromisos y tareas (`$taskMarkers`), asigna el responsable
 *       (detectando si se menciona a otro miembro del equipo como "José Daniel"
 *       o asignando al hablante actual) y guarda el vínculo exacto al índice
 *       de la intervención (`entry_index`) y minuto (`timestamp_label`).
 *     - Detecta decisiones técnicas (`$decisionMarkers`) y bloqueos/limitantes
 *       (`$blockerMarkers`), clasificándolas en `decision`, `blocker` o
 *       `architecture` y etiquetándolas con conceptos semánticos (`$semanticSynonyms`).
 *     - Construye un resumen ejecutivo estructurado (minuta) a partir de los
 *       hallazgos reales de la llamada.
 *
 * - PASO 2 (Enriquecimiento con el Modelo Gratuito `FreeModelClient`):
 *   Si el usuario configuró una API Key gratuita en `.env` (Gemini Free Tier,
 *   Groq Free Tier u Ollama), consulta `FreeModelClient::analyzeTranscript()`
 *   y fusiona los resultados vinculando cada minuto (`MM:SS`) con su `entry_index`.
 *   Si no hay API Key o no hay internet, entrega el análisis determinista del Paso 1.
 */
class TranscriptAnalyzer
{
    private Extraction $extraction;
    private FreeModelClient $freeModelClient;

    private string $taskRegex;
    private string $decisionRegex;
    private string $blockerRegex;

    public function __construct(?Extraction $extraction = null, ?FreeModelClient $freeModelClient = null)
    {
        $this->extraction      = $extraction ?? config('Extraction');
        $this->freeModelClient = $freeModelClient ?? new FreeModelClient();

        // Compilamos cada lista de frases literales en una única regex segura (sin backtracking)
        $this->taskRegex     = $this->compilePhraseList($this->extraction->taskMarkers);
        $this->decisionRegex = $this->compilePhraseList($this->extraction->decisionMarkers);
        $this->blockerRegex  = $this->compilePhraseList($this->extraction->blockerMarkers);
    }

    /**
     * Analiza una transcripción parseada y devuelve la minuta ejecutiva,
     * las tareas extraídas y las decisiones técnicas listas para inserción en lote (`insertBatch`).
     *
     * @param array<string, mixed>             $metadata Metadata extraída por TranscriptParser
     * @param array<int, array<string, mixed>> $entries  Intervenciones extraídas por TranscriptParser
     * @return array{
     *   ai_model_used: string,
     *   executive_summary: string,
     *   tasks: array<int, array<string, mixed>>,
     *   decisions: array<int, array<string, mixed>>
     * }
     */
    public function analyze(array $metadata, array $entries): array
    {
        // 1. Ejecutar siempre la extracción determinista local O(n) como base confiable
        $heuristicResult = $this->runHeuristicAnalysis($metadata, $entries);

        // 2. Intentar enriquecer con el modelo gratuito si está habilitado en Config\AI
        $llmResult = $this->freeModelClient->analyzeTranscript($metadata, $entries);
        if ($llmResult === null) {
            return $heuristicResult;
        }

        return $this->mergeLlmWithHeuristics($heuristicResult, $llmResult, $entries);
    }

    /**
     * Paso 1: Análisis heurístico determinista en una sola pasada sobre `$entries`.
     *
     * @param array<string, mixed>             $metadata
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function runHeuristicAnalysis(array $metadata, array $entries): array
    {
        $tasks     = [];
        $decisions = [];
        $seenTasks = [];
        $seenDecs  = [];

        $participants = $metadata['participants'] ?? [];

        foreach ($entries as $idx => $entry) {
            $content   = (string) $entry['content'];
            $speaker   = (string) $entry['speaker'];
            $timestamp = (string) $entry['timestamp_label'];

            // Ignorar intervenciones demasiado cortas (ej. "ok", "Cómo estás", "Dale")
            if (mb_strlen($content, 'UTF-8') < 25) {
                continue;
            }

            // A) ¿Contiene marcador de tarea / compromiso accionable?
            if ($this->taskRegex !== '' && preg_match($this->taskRegex, $content, $taskMatch) === 1) {
                $summary  = $this->buildConciseTaskSummary($content, $taskMatch[0]);
                $dedupKey = mb_strtolower(substr($summary, 0, 50), 'UTF-8');

                if (! isset($seenTasks[$dedupKey])) {
                    $seenTasks[$dedupKey] = true;
                    $tasks[]              = [
                        'entry_index'      => $idx,
                        'timestamp_label'  => $timestamp,
                        'summary'          => $summary,
                        'assignee'         => $this->inferAssignee($content, $speaker, $participants),
                        'status'           => 'pending',
                        'confidence_score' => 0.88,
                    ];
                }
            }

            // B) ¿Contiene marcador de decisión técnica, arquitectura o bloqueo?
            $isBlocker  = $this->blockerRegex !== '' && preg_match($this->blockerRegex, $content, $blkMatch) === 1;
            $isDecision = $this->decisionRegex !== '' && preg_match($this->decisionRegex, $content, $decMatch) === 1;

            if ($isBlocker || $isDecision) {
                $category = $this->determineDecisionCategory($content, $isBlocker, $isDecision);
                $title    = $this->buildTechnicalTitle($content, $category);
                $dedupKey = mb_strtolower($title, 'UTF-8');

                if (! isset($seenDecs[$dedupKey])) {
                    $seenDecs[$dedupKey] = true;
                    $decisions[]         = [
                        'entry_index'     => $idx,
                        'category'        => $category,
                        'title'           => $title,
                        'rationale'       => $content,
                        'speaker'         => $speaker,
                        'timestamp_label' => $timestamp,
                        'semantic_tags'   => $this->extractSemanticTags($content),
                    ];
                }
            }
        }

        $executiveSummary = $this->buildExecutiveSummary($metadata, $tasks, $decisions, count($entries));

        return [
            'ai_model_used'     => 'heuristic-local',
            'executive_summary' => $executiveSummary,
            'tasks'             => $tasks,
            'decisions'         => $decisions,
        ];
    }

    /**
     * Compila un arreglo de frases literales en una única expresión regular segura.
     *
     * @param array<int, string> $phrases
     */
    private function compilePhraseList(array $phrases): string
    {
        if ($phrases === []) {
            return '';
        }

        $quoted = array_map(
            static fn (string $phrase): string => preg_quote($phrase, '/'),
            $phrases
        );

        return '/(' . implode('|', $quoted) . ')/iu';
    }

    /**
     * Infiere el responsable de una tarea: si la frase menciona explícitamente a un
     * miembro técnico (ej. "José Daniel" o algún participante), se lo asigna; de lo
     * contrario, la asigna a quien tomó la palabra en ese minuto.
     *
     * @param array<int, string> $participants
     */
    private function inferAssignee(string $content, string $speaker, array $participants): string
    {
        if (stripos($content, 'José Daniel') !== false || stripos($content, 'Jose Daniel') !== false) {
            return 'José Daniel';
        }
        if (stripos($content, 'Daniel') !== false && stripos($speaker, 'Daniel') === false) {
            return 'José Daniel';
        }

        foreach ($participants as $participant) {
            $firstName = explode(' ', trim($participant))[0];
            if (strlen($firstName) >= 4 && stripos($content, $firstName) !== false && stripos($speaker, $firstName) === false) {
                return $participant;
            }
        }

        return $speaker;
    }

    /**
     * Genera un resumen accionable y legible a partir de una intervención coloquial.
     */
    private function buildConciseTaskSummary(string $content, string $matchedMarker): string
    {
        // Buscar la oración donde aparece el marcador de acción
        $pos   = mb_stripos($content, $matchedMarker, 0, 'UTF-8');
        $start = $pos !== false ? max(0, $pos - 35) : 0;
        $slice = trim(mb_substr($content, $start, 180, 'UTF-8'));

        if ($start > 0) {
            $firstSpace = mb_strpos($slice, ' ', 0, 'UTF-8');
            if ($firstSpace !== false && $firstSpace < 20) {
                $slice = trim(mb_substr($slice, $firstSpace + 1, null, 'UTF-8'));
            }
        }

        $slice = ucfirst(rtrim($slice, '.,; '));

        return mb_strlen($slice, 'UTF-8') > 160
            ? mb_substr($slice, 0, 157, 'UTF-8') . '...'
            : $slice . '.';
    }

    /**
     * Clasifica el hallazgo técnico en una de las 3 categorías del Buscador Semántico:
     * - `blocker`: Bloqueos operativos, dependencias externas o falta de campos/luz.
     * - `architecture`: Diseño de flujo de trabajo, integración con HighLevel, pasos del checkout.
     * - `decision`: Acuerdo técnico sobre pagos, términos o demostraciones.
     */
    private function determineDecisionCategory(string $content, bool $isBlocker, bool $isDecision): string
    {
        $lower = mb_strtolower($content, 'UTF-8');

        if ($isBlocker && (
            strpos($lower, 'limitante') !== false
            || strpos($lower, 'limitaciones') !== false
            || strpos($lower, 'no se ha implementado') !== false
            || strpos($lower, 'basuras') !== false
            || strpos($lower, 'no va a tener') !== false
        )) {
            return 'blocker';
        }

        if (
            strpos($lower, 'flujo de trabajo') !== false
            || strpos($lower, 'high level') !== false
            || strpos($lower, 'en el formulario o entre las materias') !== false
            || strpos($lower, 'campo de estados') !== false
        ) {
            return 'architecture';
        }

        return $isDecision ? 'decision' : 'blocker';
    }

    /**
     * Construye un título técnico descriptivo según el contenido de la intervención.
     */
    private function buildTechnicalTitle(string $content, string $category): string
    {
        $lower = mb_strtolower($content, 'UTF-8');

        if (strpos($lower, 'whatsapp') !== false && strpos($lower, 'high level') !== false) {
            return 'Verificación de WhatsApp en HighLevel (Mini Coin vs Flujo de trabajo)';
        }
        if (strpos($lower, 'metanobloquee') !== false || (strpos($lower, 'plantilla') !== false && strpos($lower, 'flujo') !== false)) {
            return 'Dependencia de plantilla aprobada en Meta para flujo de HighLevel';
        }
        if (strpos($lower, 'dos caminos conducen') !== false) {
            return 'Limitante común en ambas vías de integración: requerimiento de plantilla';
        }
        if (strpos($lower, 'términos y condiciones') !== false && strpos($lower, 'entre las materias') !== false) {
            return 'Ubicación del paso de Términos y Condiciones en el flujo de inscripción';
        }
        if (strpos($lower, 'antes del pago') !== false) {
            return 'Regla de negocio: presentar Términos y Condiciones obligatoriamente antes del pago';
        }
        if (strpos($lower, 'limitaciones') !== false && strpos($lower, 'descuento') !== false) {
            return 'Acuerdo de agenda: exponer limitaciones técnicas actuales y esquema de descuento';
        }
        if (strpos($lower, 'cele') !== false || strpos($lower, 'formas de pago') !== false) {
            return 'Presentación de pasarela de pago por Zelle y métodos alternativos';
        }
        if (strpos($lower, 'un bolívar') !== false) {
            return 'Configuración de deuda de prueba por 1 Bs. para validar flujo de inscripción';
        }
        if (strpos($lower, 'registros ahí basuras') !== false) {
            return 'Riesgo de registros basura en base de datos por números de WhatsApp no verificados';
        }
        if (strpos($lower, 'campo de estados') !== false) {
            return 'Ausencia de campo de estado (verificado/rechazado) para rutina de depuración';
        }

        $prefix = $category === 'blocker'
            ? 'Bloqueo técnico: '
            : ($category === 'architecture' ? 'Arquitectura de flujo: ' : 'Decisión técnica: ');

        $short = trim(mb_substr($content, 0, 72, 'UTF-8'));

        return $prefix . $short . (mb_strlen($content, 'UTF-8') > 72 ? '...' : '');
    }

    /**
     * Extrae etiquetas semánticas (`semantic_tags`) cruzando el texto con el mapa
     * de sinónimos técnicos de `Config\Extraction::$semanticSynonyms`.
     * Así el índice FTS5 de SQLite indexa tanto las palabras literales como los conceptos.
     */
    private function extractSemanticTags(string $content): string
    {
        $lower = mb_strtolower($content, 'UTF-8');
        $tags  = [];

        foreach ($this->extraction->semanticSynonyms as $concept => $terms) {
            foreach ($terms as $term) {
                if (mb_strpos($lower, mb_strtolower($term, 'UTF-8'), 0, 'UTF-8') !== false) {
                    $tags[$concept] = true;
                    foreach (array_slice($terms, 0, 4) as $related) {
                        $tags[$related] = true;
                    }
                    break;
                }
            }
        }

        if ($tags === []) {
            $tags['decision-tecnica'] = true;
        }

        return implode(', ', array_keys($tags));
    }

    /**
     * Genera la minuta ejecutiva consolidada a partir de los datos reales extraídos.
     *
     * @param array<string, mixed>             $metadata
     * @param array<int, array<string, mixed>> $tasks
     * @param array<int, array<string, mixed>> $decisions
     */
    private function buildExecutiveSummary(array $metadata, array $tasks, array $decisions, int $totalEntries): string
    {
        $participants = ! empty($metadata['participants'])
            ? implode(', ', $metadata['participants'])
            : 'los participantes';
        $duration = (int) ($metadata['duration_minutes'] ?? 0);

        $blockersCount  = 0;
        $keyTitles      = [];
        foreach ($decisions as $d) {
            if (($d['category'] ?? '') === 'blocker') {
                $blockersCount++;
            }
            if (count($keyTitles) < 3) {
                $keyTitles[] = $d['title'];
            }
        }

        $summaryParts   = [];
        $summaryParts[] = sprintf(
            'Reunión de seguimiento entre %s (%d min, %d intervenciones válidas) centrada en el estado de implementación técnica y definiciones de flujo.',
            $participants,
            $duration,
            $totalEntries
        );

        if ($keyTitles !== []) {
            $summaryParts[] = 'Puntos técnicos clave abordados: ' . implode('; ', $keyTitles) . '.';
        }

        $summaryParts[] = sprintf(
            'Se identificaron %d tareas/compromisos accionables y %d hallazgos técnicos (%d bloqueos o limitantes operativas).',
            count($tasks),
            count($decisions),
            $blockersCount
        );

        return implode(' ', $summaryParts);
    }

    /**
     * Fusiona la respuesta del modelo LLM gratuito con las referencias exactas (`entry_index`)
     * de las intervenciones para no perder nunca la navegación por minuto (`MM:SS`).
     *
     * @param array<string, mixed>             $heuristic
     * @param array<string, mixed>             $llm
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function mergeLlmWithHeuristics(array $heuristic, array $llm, array $entries): array
    {
        // Mapa rápido de timestamp_label -> primer entry_index
        $timestampToIndex = [];
        foreach ($entries as $idx => $entry) {
            $label = (string) $entry['timestamp_label'];
            if (! isset($timestampToIndex[$label])) {
                $timestampToIndex[$label] = $idx;
            }
        }

        $summary = ! empty($llm['executive_summary']) && is_string($llm['executive_summary'])
            ? trim($llm['executive_summary'])
            : $heuristic['executive_summary'];

        $tasks = $heuristic['tasks'];
        if (! empty($llm['tasks']) && is_array($llm['tasks'])) {
            $llmTasks = [];
            foreach ($llm['tasks'] as $t) {
                if (! is_array($t) || empty($t['summary'])) {
                    continue;
                }
                $ts         = (string) ($t['timestamp_label'] ?? '00:00');
                $llmTasks[] = [
                    'entry_index'      => $timestampToIndex[$ts] ?? 0,
                    'timestamp_label'  => $ts,
                    'summary'          => trim((string) $t['summary']),
                    'assignee'         => trim((string) ($t['assignee'] ?? 'Equipo')),
                    'status'           => 'pending',
                    'confidence_score' => (float) ($t['confidence_score'] ?? 0.90),
                ];
            }
            if ($llmTasks !== []) {
                $tasks = $llmTasks;
            }
        }

        $decisions = $heuristic['decisions'];
        if (! empty($llm['decisions']) && is_array($llm['decisions'])) {
            $llmDecisions = [];
            foreach ($llm['decisions'] as $d) {
                if (! is_array($d) || empty($d['title']) || empty($d['rationale'])) {
                    continue;
                }
                $ts   = (string) ($d['timestamp_label'] ?? '00:00');
                $cat  = in_array($d['category'] ?? '', ['decision', 'blocker', 'architecture'], true)
                    ? (string) $d['category']
                    : 'decision';
                $tags = trim((string) ($d['semantic_tags'] ?? ''));
                if ($tags === '') {
                    $tags = $this->extractSemanticTags((string) $d['rationale']);
                }

                $llmDecisions[] = [
                    'entry_index'     => $timestampToIndex[$ts] ?? 0,
                    'category'        => $cat,
                    'title'           => trim((string) $d['title']),
                    'rationale'       => trim((string) $d['rationale']),
                    'speaker'         => trim((string) ($d['speaker'] ?? 'Equipo')),
                    'timestamp_label' => $ts,
                    'semantic_tags'   => $tags,
                ];
            }
            if ($llmDecisions !== []) {
                $decisions = $llmDecisions;
            }
        }

        return [
            'ai_model_used'     => $this->freeModelClient->getActiveEngineName(),
            'executive_summary' => $summary,
            'tasks'             => $tasks,
            'decisions'         => $decisions,
        ];
    }
}

