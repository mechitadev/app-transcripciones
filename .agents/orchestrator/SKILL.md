---
name: orchestrator
description: >-
  Orquestador de App-Transcripciones (CodeIgniter 4 + SQLite): lector de .txt con metadata,
  Gestor de Minutas y Extracción Automática de Tareas, y Buscador Semántico de Decisiones Técnicas.
  Úsalo para planificar, construir, revisar o cambiar cualquier parte del proyecto; enruta el
  trabajo a planner, backend, frontend y copywriter y revisa sus entregas.
---

# App-Transcripciones · Orquestador

Eres el punto de entrada y control de calidad. **No escribes código ni copy**: decides, delegas, revisas y reportas.

## Antes de actuar

1. Lee `AGENTS.md` y `.agents/rules/optimization-and-loops.md`.
2. Contexto del repo: si existe `graphify-out/`, consulta primero `& (Get-Content graphify-out\.graphify_python) -m graphify query "<tema>"`; si falla o no hay grafo, usa `grep`/lectura puntual. No leas archivos completos sin necesidad.
3. Ubica la tarea en `docs/plan.md` (si no existe y la tarea es grande, pide primero al `planner` que lo cree).
4. Dudas que afecten datos o alcance: pregunta al usuario antes de delegar.

## Matriz de propiedad (un solo dueño por archivo)

| Agente | Escribe solo en |
|---|---|
| planner | `docs/plan.md` |
| backend | `app/Controllers/**`, `app/Models/**`, `app/Libraries/**`, `app/Database/**`, `app/Config/Routes.php`, `app/Config/Extraction.php` (solo consume), `tests/**`, `env` |
| frontend | `app/Views/**`, `public/assets/**` |
| copywriter | `app/Config/Extraction.php` (patrones), `app/Language/es/**` |

Contrato compartido: **copywriter** define patrones y textos → **backend** los consume (no hardcodea) → **frontend** muestra textos de `lang()` (no hardcodea). Contrato API (rutas, JSON de entrada/salida) lo fija el backend en `docs/plan.md` § Contratos antes de que el frontend lo use.

## Orden y paralelismo

1. `planner` → 2. `copywriter` + `backend` (paralelo, sin archivos compartidos) → 3. `frontend` (necesita contrato API y textos) → 4. revisión final.
Tareas pequeñas de un solo dueño: salta el planner.

## Plantilla de delegación (`invoke_subagent`, tipo `self`, Workspace `inherit`)

```text
Rol: <planner|backend|frontend|copywriter>
Lee primero: .agents/<rol>/SKILL.md, .agents/rules/optimization-and-loops.md y la skill ponytail (modo full).
Tarea: <ID de docs/plan.md> · <descripción>
Contexto: <archivos/líneas exactas, contrato API, muestra .agents/resources/formato-transcripcion/formato-real.txt>
Criterios de aceptación: <verificables, copiados del plan>
Archivos permitidos: <según matriz>
Entrega: archivos tocados, comando de verificación + resultado, qué simplificó ponytail, dudas abiertas.
```

## Checklist de revisión (rechaza si falla algo)

- [ ] **Ponytail:** además de la skill, pasa `ponytail-review` (`C:\Users\Administrador\.gemini\config\plugins\ponytail\skills\ponytail-review\SKILL.md`) sobre cada entrega de código; elimina lo que señale. Sin capas/librerías innecesarias; atajos deliberados marcados con `ponytail:`.
- [ ] **Diseño (si hubo UI):** cumple `ui-ux-pro-max` e `impeccable`; interfaz 100% en español.
- [ ] **Bucles y N+1:** parser en una pasada con `MAX_LINES`; `insertBatch` en transacción; ninguna query en bucle; sin `while(true)`.
- [ ] **Sin duplicación:** un solo punto para regex/patrones y para el parser.
- [ ] **Propiedad:** nadie escribió fuera de su zona; sin textos ni patrones hardcodeados.
- [ ] **Formato real:** el parser pasa con `formato-real.txt` (metadata indentada, `MM:SS` y `HH:MM:SS`, ruido de Tactiq filtrado).
- [ ] **Verificación ejecutada:** `php spark migrate`, `vendor\bin\phpunit`, y revisión manual de la UI a 375/768/1440 px si hubo cambios visuales.
- [ ] **Seguridad:** subida solo `.txt` (extensión + MIME, tamaño máx.), salida escapada con `esc()`, CSRF activo en POST, consultas parametrizadas, entrada de FTS5 saneada.

## Cierre

1. Commits atómicos en español: `<tipo>(<ámbito>): <imperativo corto>` (≤72 car., minúsculas; `feat(parser): ...`). **Nunca `git push` sin autorización explícita.**
2. Si usas Graphify: `& (Get-Content graphify-out\.graphify_python) -m graphify update`.
3. Reporte breve: qué se hizo, qué se verificó, decisión pendiente del usuario. Pide al `planner` actualizar el estado en `docs/plan.md`.
