# Reglas de Optimización, Anti-Bloat y Prevención de Bucles (App-Transcripciones)

Estas reglas aplican de forma obligatoria a todos los agentes del sistema (`orchestrator`, `planner`, `backend`, `frontend`, `copywriter`).

## 1. Uso Obligatorio de Ponytail (Modo: `full`)
Antes de escribir, refactorizar o diseñar código, activa y aplica la skill `ponytail`:
`C:\Users\Administrador\.gemini\config\plugins\ponytail\skills\ponytail\SKILL.md`

Recorre siempre la escalera de decisiones antes de implementar:
1. **¿Necesita existir?** (YAGNI). Si nadie lo pidió y no resuelve las 2 features principales, no lo escribas.
2. **¿Ya existe en el repositorio?** Reutiliza helpers, modelos o vistas existentes antes de crear archivos nuevos.
3. **¿La librería estándar de PHP / SQLite / navegador lo resuelve?** Usa funciones nativas (`SplFileObject`, `preg_match`, `PDO` / Query Builder de CI4, SQLite `FTS5`, `<dialog>`, `<input type="search">`) antes de instalar paquetes.
4. **¿Puede resolverse en una línea clara?** Hazlo en una línea.

## 2. Prohibición de Código Repetitivo, Redundante y Bucles Infinitos
Queda estrictamente prohibido generar código repetitivo, redundante o propenso a bucles infinitos que degraden el rendimiento de la aplicación:

- **Cero bucles sin cota superior (`while(true)` / recursión sin freno):** Todo ciclo de lectura o procesamiento debe tener una condición de salida determinista y un límite máximo de iteraciones o líneas (`MAX_LINES = 50000`).
- **Lectura por streaming de archivos `.txt`:** Usa `fgets()` o `SplFileObject` línea por línea o en un único pase (`O(n)`). Nunca re-leas el mismo archivo múltiples veces para extraer distintos metadatos.
- **Prohibido el problema `N+1` en SQLite:**
  - Al guardar segmentos de transcripción, tareas extraídas o decisiones técnicas, usa **inserciones por lotes (`insertBatch`)** dentro de una única transacción (`$db->transStart()` / `$db->transComplete()`).
  - Nunca ejecutes `INSERT`, `UPDATE` o `SELECT` dentro de un bucle `foreach` o `while` que recorra líneas de una transcripción.
- **Prohibido el polling infinito y re-renderizado en bucle en el Frontend:**
  - Nunca uses `setInterval` sin limpieza ni llamadas `fetch` encadenadas sin condición de parada.
  - En el buscador semántico/técnico, aplica un `debounce` simple (250–300 ms) y cancela peticiones previas con `AbortController`.
- **Principio DRY pragmático:** Si una expresión regular de parseo de cabecera o línea de diálogo (`MM:SS Autor: Texto`) se usa en más de un lugar, centralízala en un único método del parser en lugar de duplicarla en controladores o vistas.
