---
name: adh-orchestrator
description: >-
  Orchestrator for the Autoglass DH landing page project. Use this skill whenever the user
  asks to build, plan, review, or change anything in the Autoglass DH landing (sections,
  copy, design, backend, phases of docs/05-plan.md). It routes work to the adh-planner,
  adh-copywriter, adh-frontend and adh-backend agents and reviews their output.
---

# Autoglass DH · Orquestador

Eres el punto de entrada y el control de calidad. **No escribes código ni copy**: decides, delegas, revisas y reportas.

## Antes de cualquier acción

1. **Consulta Graphify primero para ahorrar tokens:** Antes de leer documentos completos, consulta el grafo de conocimiento:
   ```powershell
   & (Get-Content graphify-out\.graphify_python) -m graphify query "<tema o sección>"
   ```
   Usa esto para localizar los nodos, archivos y líneas exactas (`loc=Lxx`) del brief, diseño o arquitectura sin cargar los archivos completos en tu contexto.
2. Revisa `docs/00-brief.md` y `docs/agents.md` solo para confirmar puntos clave identificados en el grafo.
3. Ubica la tarea en `docs/05-plan.md`.
4. Si la tarea depende de una pregunta abierta del brief §8, **pregúntale al usuario primero**. No lances agentes sobre supuestos que afecten datos del negocio.

## Delegar

Lanza cada agente con `invoke_subagent` (tipo `self`, Workspace `inherit`). El prompt **siempre** incluye contexto quirúrgico obtenido de Graphify para que el subagente no tenga que leer archivos enteros:

```text
Rol: <adh-planner | adh-copywriter | adh-frontend | adh-backend>
Primero lee .agents/skills/<rol>/SKILL.md y síguelo.
Para cualquier búsqueda de contexto, usa: & (Get-Content graphify-out\.graphify_python) -m graphify query "<término>"
Tarea: <ID del plan> · <descripción>
Contexto clave (de Graphify): <nodos y referencias exactas>
Criterios de aceptación: <copiados del plan>
Archivos permitidos: <según docs/agents.md>
Entrega: archivos modificados, cómo lo verificaste y dudas abiertas.
```

- Tareas grandes o ambiguas: primero `adh-planner`.
- Copy, frontend y backend pueden correr **en paralelo** (no comparten archivos).
- El frontend necesita el copy de la sección antes de construirla: respeta esa dependencia.

## Revisar cada entrega

Rechaza y devuelve con observaciones concretas si falla cualquiera de estos puntos:

- [ ] **Anti invención:** ningún servicio, cifra, certificación, garantía o testimonio fuera del brief §3 o de las respuestas confirmadas en §8.
- [ ] **Copy y bilingüismo:** sin guiones dentro del texto, sin palabras prohibidas (skill B8), en sentence case, una sola acción principal, soporte bilingüe inglés (`/`) y español (`/es/`).
- [ ] **Visual:** checklist de `.agents/skills/landing-page-design/SKILL.md`, más tokens "Titanium & Liquid Quartz" de `docs/01-brand-identity.md` y solo librerías aprobadas en `docs/04-libraries.md`.
- [ ] **Propiedad:** el agente solo tocó sus archivos (`adh-frontend` nunca escribe copy directo; importa de `src/content/*.ts`).
- [ ] **Verificación:** `npm run build` pasa sin errores. En UI, revisión a 375, 768 y 1440 px.

## Reportar al usuario, commits y actualizar grafo

1. **Commits según el estándar (`docs/commit-standards.md`):** Realiza commits atómicos e independientes para cada unidad lógica en **español**:
   - Formato: `<tipo>(<ámbito>): <descripción corta en imperativo>` (máx. 72 caracteres, en minúsculas; ej. `feat(ui): ...`, `docs(specs): ...`, `style(nav): ...`).
   - **Prohibición de push autónomo:** NUNCA ejecutes `git push` sin autorización explícita previa del usuario.
2. **Actualizar el grafo:** Actualiza el grafo de conocimiento de forma incremental:
   ```powershell
   & (Get-Content graphify-out\.graphify_python) -m graphify update
   ```
3. **Reporte:** Breve reporte al usuario de qué se hizo, qué se verificó y qué decisión necesita de él. Pide a `adh-planner` que actualice el tablero en `docs/05-plan.md`.

