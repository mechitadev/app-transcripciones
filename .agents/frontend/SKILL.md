---
name: frontend
description: >-
  Ingeniero Frontend para App-Transcripciones. Úsalo para construir e integrar la interfaz del
  lector de transcripciones .txt con metadata, el Gestor de Minutas y Tareas, y el Buscador
  Semántico de Decisiones Técnicas.
---

# App-Transcripciones · Frontend

Construyes la interfaz de usuario para el análisis de transcripciones, gestión de minutas/tareas y búsqueda semántica de decisiones técnicas.
**Ámbito de escritura:** `app/Views/**`, `public/assets/**` (CSS/JS). **Nunca escribas texto visible fijo**: usa `lang('App.clave')` (del copywriter); si falta una clave, pídela. Consume solo las rutas/JSON definidos en `docs/plan.md` § Contratos. Escapa todo dato dinámico (`esc()` en vistas, `textContent` en JS; nunca `innerHTML` con datos de transcripción). Incluye el token CSRF en los POST.

## Recursos y Skills Obligatorias (Leer antes de programar)

1. **Skill Ponytail (Optimización de código):**
   - Lee y aplica `C:\Users\Administrador\.gemini\config\plugins\ponytail\skills\ponytail\SKILL.md` (modo `full`).
   - Usa capacidades nativas de HTML5, CSS moderno y Vanilla JS (ES6+) antes de añadir dependencias pesadas. `<dialog>` para modales, `<details>/<summary>` para bloques colapsables, `fetch` con `AbortController` para búsqueda en vivo.
2. **Regla Anti-Redundancia y Anti-Bucles Infinitos:**
   - Lee `.agents/rules/optimization-and-loops.md`.
3. **Referencia Visual y de Arquitectura UI:**
   - **Diseño profesional (obligatorio, ambas):** lee y aplica `C:\Users\Administrador\.gemini\config\plugins\myskills\skills\ui-ux-pro-max\SKILL.md` (paleta, tipografía, accesibilidad, estados de UI) y `C:\Users\Administrador\.gemini\config\plugins\myskills\skills\impeccable\SKILL.md` (jerarquía visual, polish, estados vacíos/error, responsive, anti-patrones). Antes de entregar una pantalla, pásale el criterio *critique/polish* de `impeccable`. Opcional para navegación: `.agents/site-architecture/SKILL.md`.
   - **Idioma:** toda la interfaz en **español** (vía `lang()`).

## Reglas Técnicas No Negociables

### 1. Prevención de Bucles Infinitos y Redundancia en el Cliente
- **Prohibido el polling infinito:** Nunca uses `setInterval` sin condición de parada clara ni llamadas recursivas a `fetch` sin control.
- **Buscador con Debounce y Cancelación:** El input del **Buscador Semántico de Decisiones Técnicas** debe usar un `debounce` de `250ms` y cancelar cualquier petición anterior en vuelo mediante `AbortController` para evitar condiciones de carrera y saturación del backend SQLite.
- **Renderizado eficiente del DOM:** Al mostrar transcripciones largas o resultados de búsqueda, inserta mediante un único `DocumentFragment` o plantilla renderizada por el servidor, evitando manipular el DOM repetidamente dentro de un bucle línea por línea.
- **Delegación de eventos:** Usa un único `addEventListener` delegado en el contenedor de tareas o transcripciones en lugar de registrar cientos de listeners individuales por cada fila.

### 2. Interfaces Clave a Construir
1. **Visor e Ingesta de Transcripciones (`.txt`):**
   - Subida drag-and-drop o selección de archivos `.txt`.
   - Cabecera clara con la **metadata** extraída: Fecha de inicio, Duración, Participantes (chips/filtros) y enlace al transcript original.
   - Línea de tiempo interactiva (`MM:SS`) que permite saltar del resultado de una tarea o decisión técnica directamente a la línea exacta del diálogo y resaltarla.
2. **Gestor de Minutas y Extracción Automática de Tareas:**
   - Resumen ejecutivo de la minuta (temas tratados, bloqueos y acuerdos).
   - Tablero/lista de tareas extraídas automáticamente con responsable (`Participants`), cita original con timestamp (`02:15`), cambio de estado rápido (`Pendiente`, `En progreso`, `Completada`) sin recargar toda la página, y opción de agregar/editar tareas manualmente.
3. **Buscador Semántico de Decisiones Técnicas:**
   - Barra de búsqueda semántica/técnica con filtros por participante, reunión y tipo de hallazgo (`Decisión`, `Bloqueante/Limitación`, `Flujo/Arquitectura`).
   - Tarjetas de resultados que muestran el fragmento relevante resaltado, el motivo por el cual se clasificó como decisión técnica, quién la tomó, en qué minuto (`MM:SS`) y botón de *"Ver en contexto"*.

## Verificación Antes de Entregar

1. Comprueba en consola del navegador que no existan errores JS, peticiones duplicadas ni bucles de eventos.
2. Verifica diseño responsivo (móvil, tablet y escritorio) y navegación por teclado accesible.
