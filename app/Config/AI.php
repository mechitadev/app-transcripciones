<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * ============================================================================
 * CONFIGURACIÓN DEL MODELO DE INTELIGENCIA ARTIFICIAL GRATUITO (`Config\AI`)
 * ============================================================================
 *
 * PROPÓSITO DE ESTE ARCHIVO:
 * Centralizar toda la configuración del modelo gratuito utilizado por la
 * aplicación para:
 *   1. Redactar la Minuta Ejecutiva de cada reunión.
 *   2. Extraer tareas y compromisos con su responsable y minuto exacto.
 *   3. Detectar decisiones técnicas, bloqueos y conceptos de arquitectura.
 *
 * ¿CÓMO FUNCIONA LA ARQUITECTURA DE IA EN ESTA APP?
 * - Nivel 1 (Base Determinista Local - Siempre Activa y Gratuita):
 *   Analiza las líneas de la transcripción usando el diccionario lingüístico
 *   de `app/Config/Extraction.php` en una sola pasada O(n). No requiere internet
 *   ni API Key y garantiza respuesta en < 10 milisegundos.
 *
 * - Nivel 2 (Enriquecimiento con Modelo LLM Gratuito):
 *   Si configuras una API Key gratuita en el archivo `.env` (por ejemplo de
 *   Google AI Studio - Gemini Free Tier o Groq Cloud Free Tier) o ejecutas
 *   Ollama localmente, `App\Libraries\AI\FreeModelClient` consulta el modelo
 *   para enriquecer la minuta, las tareas y las etiquetas semánticas.
 *
 * PROVEEDORES GRATUITOS SOPORTADOS:
 * - 'gemini' : Google Gemini Free Tier (https://aistudio.google.com/apikey)
 *              Modelo recomendado sin costo: 'gemini-2.5-flash'
 * - 'groq'   : Groq Cloud Free Tier (https://console.groq.com/keys)
 *              Modelo recomendado sin costo: 'llama-3.3-70b-versatile'
 * - 'ollama' : Ejecución 100% local y gratuita (http://localhost:11434)
 *              Modelo recomendado: 'llama3.2' o 'qwen2.5:7b'
 * - 'local'  : Usa únicamente el motor heurístico determinista sin llamadas HTTP.
 */
class AI extends BaseConfig
{
    /**
     * Proveedor activo del modelo gratuito.
     * Valores válidos: 'gemini' | 'groq' | 'ollama' | 'local'
     * Se puede sobreescribir en `.env` con: ai.provider = 'gemini'
     */
    public string $provider = 'gemini';

    /**
     * Clave de API gratuita del proveedor seleccionado (`gemini` o `groq`).
     * Si está vacía (''), el sistema no falla: degrada automáticamente al
     * motor heurístico local (`fallbackToHeuristics = true`).
     * Se configura en `.env` con: ai.apiKey = 'TU_API_KEY_GRATUITA'
     */
    public string $apiKey = '';

    /**
     * Identificador del modelo gratuito a utilizar según el proveedor:
     * - Para Gemini Free Tier: 'gemini-2.5-flash'
     * - Para Groq Free Tier:   'llama-3.3-70b-versatile'
     * - Para Ollama Local:     'llama3.2'
     */
    public string $model = 'gemini-2.5-flash';

    /**
     * URL base cuando se usa un servidor local de Ollama (`provider = 'ollama'`).
     */
    public string $ollamaBaseUrl = 'http://localhost:11434';

    /**
     * Temperatura de generación (0.0 a 1.0).
     * Se fija en 0.1 para obtener respuestas técnicas precisas, deterministas
     * y fieles a lo que realmente se dijo en el archivo .txt (cero alucinaciones).
     */
    public float $temperature = 0.1;

    /**
     * Tiempo máximo de espera (en segundos) para la petición HTTP al modelo gratuito.
     * Evita que una caída de red o límite de cuota (Rate Limit 429) bloquee al usuario.
     */
    public int $timeoutSeconds = 8;

    /**
     * Si es true (recomendado), cuando no hay API Key o el proveedor gratuito
     * devuelve error/timeout, el sistema usa automáticamente el analizador
     * heurístico local basado en `Config\Extraction`.
     */
    public bool $fallbackToHeuristics = true;

    public function __construct()
    {
        parent::__construct();

        // Cargar valores desde variables de entorno (.env) si están definidas
        $this->provider       = (string) env('ai.provider', $this->provider);
        $this->apiKey         = (string) env('ai.apiKey', $this->apiKey);
        $this->model          = (string) env('ai.model', $this->model);
        $this->ollamaBaseUrl  = (string) env('ai.ollamaBaseUrl', $this->ollamaBaseUrl);
        $this->timeoutSeconds = (int) env('ai.timeoutSeconds', $this->timeoutSeconds);
    }

    /**
     * Indica si el conector remoto de IA está listo para realizar llamadas HTTP.
     */
    public function isRemoteEnabled(): bool
    {
        if ($this->provider === 'local') {
            return false;
        }

        if ($this->provider === 'ollama') {
            return $this->ollamaBaseUrl !== '';
        }

        return trim($this->apiKey) !== '';
    }
}

