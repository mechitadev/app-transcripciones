# Registro de Cambios (Changelog) · Unitrax

Todos los cambios notables de este proyecto están documentados en este archivo siguiendo el formato de [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y respetando [SemVer](https://semver.org/lang/es/).

---

## [1.0.0] - 2026-11-06

### Añadido
- **Ingesta de Transcripciones en $O(n)$:**
  - Lector de archivos `.txt` en una sola pasada con cota de seguridad de `MAX_LINES = 50000` (`App\Libraries\TranscriptParser`).
  - Extracción automática de metadata de cabecera: fecha de inicio, duración en minutos, participantes y enlace al registro original.
  - Idempotencia garantizada mediante cálculo de hash SHA-256 del contenido crudo (`hash_crudo`).
  - Filtrado de ruido promocional automático (notificaciones de Tactiq AI y extensiones similares).
- **Módulo 1: Gestor de Minutas y Extracción Automática de Tareas:**
  - Redacción automática del resumen ejecutivo de la reunión.
  - Detección y extracción de tareas con asignado, minuto exacto (`MM:SS`) y puntaje de confianza.
  - Gestión interactiva de estados de tareas: `pendiente`, `en_progreso` y `completada`.
  - Creación manual y edición inline de tareas vinculadas a las intervenciones.
- **Módulo 2: Buscador Semántico de Decisiones Técnicas:**
  - Indexación de decisiones técnicas, acuerdos de arquitectura y bloqueos operativos.
  - Tabla virtual SQLite FTS5 (`decisiones_tecnicas_fts`) con ranking por relevancia `bm25()`.
  - Disparadores (*triggers*) en base de datos para sincronización en tiempo real de altas, bajas y modificaciones.
  - Expansión semántica de sinónimos técnicos en español (`Config\Extraction::$semanticSynonyms`).
  - Búsqueda combinada por texto libre, participante, categoría y alcance (*Esta reunión* vs *Todas las reuniones*).
- **Motor de Inteligencia Artificial Híbrido:**
  - Nivel 1 local determinista basado en heurísticas lingüísticas sin costo ni consumo de red.
  - Nivel 2 opcional con soporte para Google Gemini Free Tier (`gemini-2.5-flash`), Groq Cloud Free Tier (`llama-3.3-70b-versatile`) y Ollama local.
  - Degradación elegante (*graceful fallback*) automática en caso de error o límite de cuota.
- **Autenticación y Seguridad:**
  - Autenticación híbrida: sesiones nativas de CodeIgniter 4 y tokens JWT (HS256) nativos sin SDKs de terceros.
  - Soporte para Google Identity Services (OAuth 2.0 / `POST /auth/google`).
  - Cuenta de demostración preconfigurada para pruebas rápidas de evaluación.
- **Interfaz y Experiencia de Usuario (UI/UX):**
  - Rediseño con Tailwind CSS v4, tipografías `Manrope` y `Geist Mono`.
  - Soporte para tema claro y tema oscuro con persistencia en `localStorage` y prevención de FOUC.
  - Visor cronológico lateral con búsqueda instantánea y botones de acción rápida en 1 clic.
  - Exportación de minutas en formato Markdown (`.md`) para Jira, Linear o Confluence.
- **Documentación y Calidad:**
  - Suite de pruebas unitarias automatizadas con PHPUnit ($100\%$ de pruebas en verde).
  - Documentación de arquitectura, API REST y especificación `llms.txt`.

