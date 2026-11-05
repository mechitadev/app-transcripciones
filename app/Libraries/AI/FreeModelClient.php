<?php

namespace App\Libraries\AI;

use Config\AI as AIConfig;

/**
 * ============================================================================
 * CLIENTE HTTP LIGERO PARA MODELOS DE IA GRATUITOS (`FreeModelClient`)
 * ============================================================================
 *
 * ¿QUÉ HACE ESTA CLASE?
 * Se encarga de enviar los fragmentos relevantes de una transcripción al
 * modelo gratuito configurado en `app/Config/AI.php` (Google Gemini Free Tier,
 * Groq Cloud Free Tier u Ollama Local) usando únicamente la extensión nativa
 * `cURL` de PHP (regla Ponytail: cero SDKs pesados de terceros).
 *
 * FLUJO DE LECTURA DEL CÓDIGO:
 * 1. `analyzeTranscript()`: Verifica si el proveedor gratuito está activo,
 *    construye el prompt estructurado en español y despacha la petición.
 * 2. `buildSystemPrompt()`: Define las instrucciones exactas para que el modelo
 *    devuelva un JSON estricto con tres claves:
 *      - `executive_summary`: Resumen de la minuta.
 *      - `tasks`: Lista de tareas accionables con minuto (`timestamp_label`) y responsable.
 *      - `decisions`: Lista de decisiones técnicas, bloqueos o acuerdos de arquitectura.
 * 3. `callGemini()` / `callGroq()` / `callOllama()`: Adaptadores HTTP específicos
 *    para cada API gratuita.
 * 4. `extractJsonPayload()`: Limpia posibles bloques Markdown (```json ... ```)
 *    y valida que la estructura devuelta sea segura antes de pasarla al controlador.
 */
class FreeModelClient
{
    private AIConfig $config;

    public function __construct(?AIConfig $config = null)
    {
        $this->config = $config ?? config('AI');
    }

    /**
     * Devuelve una etiqueta legible del motor de IA configurado actualmente.
     */
    public function getActiveEngineName(): string
    {
        if (! $this->config->isRemoteEnabled()) {
            return 'heuristic-local (Motor Semántico Determinista + FTS5)';
        }

        return sprintf('%s (%s)', strtoupper($this->config->provider), $this->config->model);
    }

    /**
     * Envía las intervenciones de la reunión al modelo gratuito y devuelve el análisis estructurado.
     * Si el proveedor no tiene API Key o falla por límite de cuota gratuita, devuelve `null`
     * para que `TranscriptAnalyzer` utilice el resultado heurístico local sin interrumpir el flujo.
     *
     * @param array<string, mixed>             $metadata Metadata de la reunión (started_at, participants, etc.)
     * @param array<int, array<string, mixed>> $entries  Intervenciones cronológicas parseadas
     * @return array<string, mixed>|null
     */
    public function analyzeTranscript(array $metadata, array $entries): ?array
    {
        // Si no hay API key configurada o estamos en modo 'local', no hacemos llamadas de red
        if (! $this->config->isRemoteEnabled() || ! function_exists('curl_init') || $entries === []) {
            return null;
        }

        // Construimos una representación compacta del diálogo (acotada a 12,000 caracteres
        // para respetar la ventana de tokens gratuitos y mantener latencia baja)
        $dialogueLines = [];
        $charCount     = 0;
        foreach ($entries as $entry) {
            $line      = sprintf('[%s] %s: %s', $entry['timestamp_label'], $entry['speaker'], $entry['content']);
            $charCount += strlen($line);
            if ($charCount > 12000) {
                break;
            }
            $dialogueLines[] = $line;
        }

        $prompt = $this->buildSystemPrompt($metadata, implode("\n", $dialogueLines));

        $rawText = null;
        if ($this->config->provider === 'gemini') {
            $rawText = $this->callGemini($prompt);
        } elseif ($this->config->provider === 'groq') {
            $rawText = $this->callGroq($prompt);
        } elseif ($this->config->provider === 'ollama') {
            $rawText = $this->callOllama($prompt);
        }

        if ($rawText === null || trim($rawText) === '') {
            return null;
        }

        return $this->extractJsonPayload($rawText);
    }

    /**
     * Construye el prompt de instrucciones para el modelo gratuito.
     * Obliga a responder exclusivamente en formato JSON válido y prohíbe inventar datos
     * que no aparezcan respaldados por un minuto (`MM:SS`) real de la transcripción.
     *
     * @param array<string, mixed> $metadata
     */
    private function buildSystemPrompt(array $metadata, string $dialogueText): string
    {
        $participants = implode(', ', $metadata['participants'] ?? []);
        $startedAt    = (string) ($metadata['started_at'] ?? 'No especificada');

        return <<<PROMPT
Eres un analista de ingeniería de software. Analiza la siguiente transcripción de reunión en español y devuelve ÚNICAMENTE un objeto JSON válido (sin texto adicional) con la siguiente estructura exacta:
{
  "executive_summary": "Resumen ejecutivo claro de 2 a 4 oraciones sobre los temas tratados, acuerdos y bloqueos.",
  "tasks": [
    {
      "timestamp_label": "MM:SS exacto donde se menciona",
      "assignee": "Nombre del participante responsable",
      "summary": "Descripción accionable y concisa de la tarea",
      "confidence_score": 0.92
    }
  ],
  "decisions": [
    {
      "timestamp_label": "MM:SS exacto",
      "speaker": "Nombre de quien expone la decisión o bloqueo",
      "category": "decision | blocker | architecture",
      "title": "Título técnico corto (máx 90 caracteres)",
      "rationale": "Explicación técnica de la decisión, flujo o limitante",
      "semantic_tags": "palabras, clave, tecnicas, separadas, por, coma"
    }
  ]
}

Metadata de la reunión:
- Fecha: {$startedAt}
- Participantes: {$participants}

Transcripción:
{$dialogueText}
PROMPT;
    }

    /**
     * Conector HTTP para Google Gemini API (Free Tier).
     * Endpoint oficial: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
     */
    private function callGemini(string $prompt): ?string
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($this->config->model),
            rawurlencode($this->config->apiKey)
        );

        $body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'      => $this->config->temperature,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = $this->postJson($url, $body);
        if (! is_array($response)) {
            return null;
        }

        return $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    /**
     * Conector HTTP para Groq Cloud (Free Tier - Compatible con formato OpenAI Chat Completions).
     * Endpoint oficial: https://api.groq.com/openai/v1/chat/completions
     */
    private function callGroq(string $prompt): ?string
    {
        $url  = 'https://api.groq.com/openai/v1/chat/completions';
        $body = [
            'model'           => $this->config->model,
            'temperature'     => $this->config->temperature,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $headers = [
            'Authorization: Bearer ' . $this->config->apiKey,
        ];

        $response = $this->postJson($url, $body, $headers);
        if (! is_array($response)) {
            return null;
        }

        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Conector HTTP para Ollama ejecutándose localmente (`http://localhost:11434/api/generate`).
     */
    private function callOllama(string $prompt): ?string
    {
        $url  = rtrim($this->config->ollamaBaseUrl, '/') . '/api/generate';
        $body = [
            'model'  => $this->config->model,
            'prompt' => $prompt,
            'format' => 'json',
            'stream' => false,
        ];

        $response = $this->postJson($url, $body);
        if (! is_array($response)) {
            return null;
        }

        return $response['response'] ?? null;
    }

    /**
     * Ejecuta una petición HTTP POST con JSON mediante cURL nativo y timeout estricto.
     *
     * @param array<string, mixed> $payload
     * @param array<int, string>   $extraHeaders
     * @return array<string, mixed>|null
     */
    private function postJson(string $url, array $payload, array $extraHeaders = []): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $headers = array_merge(['Content-Type: application/json'], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => (string) json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || ! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Limpia bloques de código Markdown si el LLM los incluyó y valida el esquema JSON.
     *
     * @return array<string, mixed>|null
     */
    private function extractJsonPayload(string $rawText): ?array
    {
        $cleaned = trim($rawText);
        if (strpos($cleaned, '```') !== false) {
            $cleaned = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $cleaned);
        }

        $data = json_decode(trim($cleaned), true);
        if (! is_array($data)) {
            return null;
        }

        if (! isset($data['executive_summary']) && ! isset($data['tasks']) && ! isset($data['decisions'])) {
            return null;
        }

        return $data;
    }
}

