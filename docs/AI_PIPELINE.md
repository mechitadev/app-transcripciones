# Pipeline de Inteligencia Artificial · Unitrax

Guía de arquitectura y configuración del motor de IA híbrido de dos niveles integrado en **Unitrax**.

---

## 1. Visión General: Arquitectura en Dos Niveles

Uno de los mayores problemas al procesar transcripciones extensas con IA es la dependencia de modelos comerciales costosos, la latencia elevada y el riesgo de límites de velocidad (*Rate Limits - HTTP 429*).

Unitrax resuelve este desafío dividiendo el procesamiento en dos capas complementarias:

| Característica | Nivel 1: Motor Heurístico Local | Nivel 2: LLM Gratuito (Enriquecedor) |
|---|---|---|
| **Costo** | $0.00 USD para siempre | $0.00 USD (Tiers gratuitos) |
| **Dependencia de red** | Cero (100% offline) | Petición HTTP a la API |
| **Tiempo de respuesta** | $< 10\text{ ms}$ | $1.5\text{ a }4\text{ segundos}$ |
| **Alucinaciones** | Cero ($0\%$) | Controladas con `temperature: 0.1` |
| **Mecanismo de acción** | Diccionario lingüístico `Config\Extraction` | Generación de JSON estructurado |
| **Disponibilidad** | Siempre activo | Opcional (degradación automática) |

---

## 2. Proveedores Gratuitos Soportados

El archivo de configuración `app/Config/AI.php` y las variables de entorno `.env` permiten alternar entre cuatro proveedores sin tocar una sola línea de código:

### 1. Google Gemini Free Tier (`provider = 'gemini'`)
- **Modelo por defecto:** `gemini-2.5-flash`
- **Costo:** Gratuito (hasta 15 peticiones por minuto en AI Studio).
- **Cómo obtener la clave:**
  1. Visita [Google AI Studio](https://aistudio.google.com/apikey).
  2. Inicia sesión con tu cuenta de Google y genera una API Key gratuita.
  3. Agrega en tu `.env`:
     ```env
     ai.provider = 'gemini'
     ai.apiKey = 'TU_GEMINI_API_KEY'
     ai.model = 'gemini-2.5-flash'
     ```

### 2. Groq Cloud Free Tier (`provider = 'groq'`)
- **Modelo por defecto:** `llama-3.3-70b-versatile`
- **Velocidad:** Inferencia ultra rápida gracias a chips LPU de Groq.
- **Cómo obtener la clave:**
  1. Ingresa en [Groq Console](https://console.groq.com/keys).
  2. Crea tu API Key gratuita.
  3. Configura en tu `.env`:
     ```env
     ai.provider = 'groq'
     ai.apiKey = 'TU_GROQ_API_KEY'
     ai.model = 'llama-3.3-70b-versatile'
     ```

### 3. Ollama en Local (`provider = 'ollama'`)
- **Privacidad total:** Todo el procesamiento ocurre en tu máquina, ideal para transcripciones con información confidencial o sin acceso a internet.
- **Modelos recomendados:** `llama3.2` o `qwen2.5:7b`.
- **Configuración:**
  1. Descarga e instala [Ollama](https://ollama.com/).
  2. Descarga el modelo en tu terminal:
     ```bash
     ollama run llama3.2
     ```
  3. Configura en tu `.env`:
     ```env
     ai.provider = 'ollama'
     ai.ollamaBaseUrl = 'http://localhost:11434'
     ai.model = 'llama3.2'
     ```

### 4. Modo Local Heurístico Puro (`provider = 'local'`)
- Si no deseas realizar llamadas HTTP de ningún tipo, define:
  ```env
  ai.provider = 'local'
  ```
- El sistema operará al 100% de sus capacidades extrayendo tareas, bloqueos y decisiones con el motor heurístico nativo.

---

## 3. Prevención de Alucinaciones y Optimización de Tokens

`App\Libraries\AI\FreeModelClient` incorpora salvaguardas para garantizar respuestas confiables:

1. **Ventana de Tokens Acotada (Budgeting):**  
   Antes del envío, el cliente compacta las intervenciones a un máximo de **12,000 caracteres**, priorizando las intervenciones con mayor densidad de diálogo. Esto evita exceder el límite gratuito de tokens de entrada y reduce el tiempo de cómputo.
2. **Temperatura Determinista (`temperature = 0.1`):**  
   Se fuerza al modelo a apegarse estrictamente a lo que los participantes dijeron en la reunión, eliminando interpretaciones creativas o invención de datos.
3. **Esquema JSON Estricto:**  
   El prompt del sistema exige devolver un objeto JSON válido con las claves `executive_summary`, `tasks` y `decisions`. Si el modelo agrega texto conversacional fuera del JSON, el método `extractJsonPayload()` aísla automáticamente el bloque JSON.
4. **Respaldo Automático (*Fallback* Transparente):**  
   Si la petición al proveedor externo falla por tiempo de espera (`timeoutSeconds = 8`), caída de conexión o límite de cuota (HTTP 429), `TranscriptAnalyzer` toma automáticamente el resultado del analizador heurístico local. El usuario nunca recibe una pantalla de error ni pierde su archivo.

