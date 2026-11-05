# AGENTS.md · Sistema de Agentes de App-Transcripciones

Aplicación Fullstack (**CodeIgniter 4 + SQLite + Frontend ligero**) diseñada para leer archivos `.txt` de transcripciones con su metadata y proveer dos módulos principales:
1. **Gestor de Minutas y Extracción Automática de Tareas.**
2. **Buscador Semántico de Decisiones Técnicas.**

---

## Reglas Globales Inquebrantables

1. **Optimización con `ponytail` (Obligatorio):**
   Todos los agentes deben seguir la skill `ponytail` (`C:\Users\Administrador\.gemini\config\plugins\ponytail\skills\ponytail\SKILL.md` en modo `full`). Primero usar funciones nativas de PHP, CodeIgniter 4, SQLite (`FTS5`, `json_extract`, transacciones en lote) y APIs nativas del navegador antes de crear abstracciones o instalar librerías.
2. **Prohibición de Código Repetitivo, Redundante y Bucles Infinitos:**
   Todos los agentes están sujetos a `.agents/rules/optimization-and-loops.md`:
   - Prohibido cualquier bucle sin cota superior (`MAX_LINES`) o condición de salida garantizada.
   - Prohibido leer el mismo archivo `.txt` múltiples veces; el parser debe procesar metadata e intervenciones en una sola pasada (`O(n)`).
   - Prohibido ejecutar consultas SQLite dentro de bucles (`N+1`); usar siempre `insertBatch` dentro de transacciones.
   - Prohibido el polling infinito en el frontend; usar `debounce` y `AbortController` en el buscador.

---

## Estructura de `.agents/`

- [`.agents/skills.json`](file:///c:/wamp64/www/App-transcripciones/.agents/skills.json): Registro de skills locales del proyecto.
- [`.agents/rules/optimization-and-loops.md`](file:///c:/wamp64/www/App-transcripciones/.agents/rules/optimization-and-loops.md): Reglas estrictas de rendimiento, anti-redundancia y prevención de bucles infinitos.
- [`.agents/orchestrator/SKILL.md`](file:///c:/wamp64/www/App-transcripciones/.agents/orchestrator/SKILL.md): Agente coordinador y control de calidad.
- [`.agents/planner/SKILL.md`](file:///c:/wamp64/www/App-transcripciones/.agents/planner/SKILL.md): Planificador de tareas atómicas sobre SQLite, Backend y Frontend.
- [`.agents/backend/SKILL.md`](file:///c:/wamp64/www/App-transcripciones/.agents/backend/SKILL.md): Ingeniero Backend (CodeIgniter 4, SQLite, Parser `.txt`, Extractor de Minutas/Tareas y Motor Semántico/FTS5).
- [`.agents/frontend/SKILL.md`](file:///c:/wamp64/www/App-transcripciones/.agents/frontend/SKILL.md): Ingeniero Frontend (Visor de transcripciones con metadata, Gestor de Minutas/Tareas y Buscador Semántico interactivo).
- [`.agents/copywriter/SKILL.md`](file:///c:/wamp64/www/App-transcripciones/.agents/copywriter/SKILL.md): Especialista en UX Writing y patrones lingüísticos de extracción en español (sin regex con backtracking infinito).
- [`.agents/resources/formato-transcripcion/formato-real.txt`](file:///c:/wamp64/www/App-transcripciones/.agents/resources/formato-transcripcion/formato-real.txt): Ejemplo real del formato `.txt` con metadata y diálogo cronológico.
