# Plan Técnico, Diseño y Contratos MVC · App Transcripciones (Rediseño Tailwind CSS v4)

Arquitectura **MVC (Modelo · Vista · Controlador)** sobre **CodeIgniter 4 + SQLite3 (FTS5 `bm25()`) + Tailwind CSS v4 + Vanilla JS (ES6+)**.

**Reglas globales activas en todas las tareas:**
- **`ponytail` (`full`):** soluciones nativas de PHP, SQLite FTS5 y APIs estándar del navegador (`<dialog>`, `<details>`, `IntersectionObserver`, `AbortController`, `DocumentFragment`) sin librerías innecesarias.
- **`.agents/rules/optimization-and-loops.md`:** parser `O(n)` en 1 sola pasada con `MAX_LINES = 50000`, inserciones por lote `insertBatch(..., null, 500)` dentro de transacción SQLite (cero `N+1`), buscador con `debounce(250ms)` + `AbortController` y cero `setInterval` sin parada.
- **`landing-page-design` + `ui-ux-pro-max`:** tipografía `Manrope` (400–700) + `Geist Mono` numérico/técnico, cero cursivas, cero peso `900`, cero guiones (`-`, `–`, `—`) dentro del texto visible en español, superficies oscuras planas (`#181818`, `#1F1F1F`, `#272727`, `#313131`), único degradado permitido en el texto del titular del Hero, curva `cubic-bezier(0.22, 1, 0.36, 1)`, respeto estricto a `prefers-reduced-motion`, iconos SVG estilo Phosphor/Solar y cero enlaces muertos (`href="#"` prohibido).

---

## 1. Tabla de Tareas Atómicas

### 1.1 Cimiento Funcional Existente (Completado)

| ID | Fase | Agente | Tarea | Criterio de Verificación | Estado |
|---|---|---|---|---|---|
| T1 | Base | `planner` | Arquitectura inicial MVC, esquema SQLite + FTS5 y contratos base | `docs/plan.md` inicial creado | ✅ |
| T2 | Base | `copywriter` | Diccionario heurístico `app/Config/Extraction.php` y textos base `app/Language/es/App.php` | `php -l app/Config/Extraction.php` sin errores | ✅ |
| T3 | Base | `backend` | Migración `CreateInitialSchema` (5 tablas + índice virtual `technical_decisions_fts` + triggers + semilla) | `php spark migrate` crea `writable/database/transcripciones.db` | ✅ |
| T4 | Base | `backend` | Autenticación híbrida `JwtManager` (HS256), `GoogleOAuthClient`, `UserModel`, `AuthFilter` y `AuthController` | Sesión CI4 + cookie/Bearer JWT protegen rutas web y API | ✅ |
| T5 | Base | `backend` | `TranscriptParser` (`O(n)`) + `FreeModelClient` + `TranscriptAnalyzer` (heurístico + LLM gratuito opcional) | Ingesta idempotente SHA256 de `formato-real.txt` en 1 pasada | ✅ |
| T6 | Base | `backend` | Modelos y Controladores MVC iniciales + suite PHPUnit | `vendor\bin\phpunit` en verde | ✅ |
| T7 | Base | `frontend` | Primera versión funcional de vistas y cliente JS | Acciones conectadas a SQLite | ✅ |
| T8 | Base | `orchestrator` | Verificación inicial de extremo a extremo | Flujo base validado | ✅ |

### 1.2 Plan de Rediseño Completo con Tailwind CSS v4 (Ejecución Actual)

> **Paralelismo explícito:** `R2 (copywriter) ∥ R3 (backend)` pueden ejecutarse simultáneamente porque no comparten archivos y ambos obedecen § 5 Contratos. `R4 -> R5 -> R6` (`frontend`) se ejecutan en orden secuencial tras completarse `R2` y `R3`.

| ID | Fase | Agente | Tarea | Criterio de Verificación | Estado |
|---|---|---|---|---|---|
| R1 | 1. Plan | `planner` | Actualizar `docs/plan.md` con esquema `landing-page-design` completo, sistema visual Tailwind CSS v4, arquitectura SaaS del Workspace y § 5 Contratos (`PATCH /api/meetings/(:num)`, `GET /api/meetings/(:num)/export`, `POST /api/decisions`, `DELETE /api/decisions/(:num)`, `portalStats` y claves `App.php`) | `docs/plan.md` actualizado con las 4 secciones obligatorias y contratos sin ambigüedades | ✅ |
| R2 | 2. Copy (`∥ R3`) | `copywriter` | Actualizar `app/Language/es/App.php` con el 100% de las claves de § 5.4 (Landing + Acceso, FAQ de 6 preguntas, modales legales, Workspace SaaS, exportación `.md`, alta manual de decisiones, acciones rápidas `+ Tarea` / `+ Decisión` y vista 404) cumpliendo B1/B8: sentence case, cero guiones (`-`, `–`, `—`) en textos visibles, cero palabras prohibidas y cero signos de exclamación en mensajes de sistema | `php -l app/Language/es/App.php` sin errores y verificación de presencia exacta de todas las claves listadas en § 5.4 | ✅ |
| R3 | 2. Backend (`∥ R2`) | `backend` | Implementar en `app/Models/MeetingModel.php`, `app/Controllers/MeetingController.php`, `app/Controllers/AuthController.php`, `app/Controllers/SearchController.php`, `app/Config/Routes.php` y `tests/`: (1) `MeetingModel::getPortalStats()` (1 consulta escalar SQLite) inyectado en `GET /` no autenticado y `GET /login`; (2) `PATCH /api/meetings/(:num)` (`MeetingController::update/$1`); (3) `GET /api/meetings/(:num)/export` (`MeetingController::export/$1`, `text/markdown`); (4) `POST /api/decisions` (`SearchController::create`) y `DELETE /api/decisions/(:num)` (`SearchController::delete/$1`); (5) tests PHPUnit para los 4 nuevos endpoints y métricas reales | `php -l` limpio en controladores/modelos/rutas y `vendor\bin\phpunit` pasando al 100% | ✅ |
| R4 | 3. Sistema CSS | `frontend` | Reescribir `resources/css/app.css` para Tailwind CSS v4 (`@import "tailwindcss";`, `@custom-variant dark (&:where(.dark, .dark *));`, `@theme` con `Manrope` 400–700 + `Geist Mono`, superficies oscuras planas `#181818`/`#1F1F1F`/`#272727`/`#313131` según B4, curva `--ease-weighted: cubic-bezier(0.22, 1, 0.36, 1)` según B7, `@media (prefers-reduced-motion: reduce)`, utilidades `text-balance`/`text-pretty` y animaciones exclusivas en `transform`/`opacity`), vaciar o retirar la hoja heredada `public/assets/css/app.css` y compilar `public/assets/css/tailwind.css` | `npm run build` compila `public/assets/css/tailwind.css` sin errores; cero referencias a `Inter`, `Roboto`, `italic` o `font-black`/`900` | ✅ |
| R5 | 4. UI Landing + Acceso + 404 | `frontend` | Rediseñar `app/Views/auth/login.php` (servida en `GET /` sin sesión y `GET /login`) aplicando el esquema completo de § 2 (Nav flotante B7 + menú móvil escalonado, Hero Tipo A con degradado de texto B5 + métricas SQLite reales `$portalStats` + Panel de Acceso real Login/Registro/Google/Demo 1 clic sin reCAPTCHA falso ni banners universitarios viejos, Problema a solución, 4 Beneficios, Tagline reveal palabra por palabra con `IntersectionObserver` B11, Cómo funciona en 3 pasos, FAQ técnica de 6 preguntas con `<details>/<summary>`, CTA final + modales `<dialog>` legales) y crear 404 de marca en `app/Views/errors/html/error_404.php` | `php -l app/Views/auth/login.php`, `php -l app/Views/errors/html/error_404.php`, `npm run build` exitoso, cero `href="#"` y conteos reales renderizados desde `$portalStats` | ✅ |
| R6 | 5. UI Workspace SaaS | `frontend` | Rediseñar `app/Views/dashboard/index.php` y `public/assets/js/app.js` con layout SaaS estilo Linear/Granola: Sidebar izquierdo colapsable (historial, filtro rápido, modal `<dialog>` drag and drop `.txt`, muestra `formato-real.txt`, perfil, tema oscuro/claro sin FOUC y logout); Cabecera con título editable inline (`PATCH /api/meetings/(:num)`), metadatos reales en `Geist Mono`, exportación `.md` (`GET /api/meetings/(:num)/export`) y chips de participantes con conteo real; Área central con pestañas ágiles para **Módulo 1** (minuta editable, progreso `scaleX`, alta rápida, checkbox 1 clic, cambio de estado/responsable, edición inline y salto a minuto) y **Módulo 2** (`Ctrl + K`, alcance *Esta reunión* vs *Todas las reuniones*, chips semánticos, alta manual `POST /api/decisions`, borrado `DELETE /api/decisions/(:num)` y salto directo a reunión + minuto); Panel derecho fijo `Visor Cronológico` (`DocumentFragment`, filtro en vivo, resaltado suave y botones 1 clic `+ Tarea` / `+ Decisión` por intervención) | `php -l app/Views/dashboard/index.php`, `npm run build` exitoso, cero `innerHTML` con contenido de transcripción, `debounce(250ms)` + `AbortController` activo y todas las rutas de § 5.2 integradas | ✅ |
| R7 | 6. QA Final | `orchestrator` | Auditoría integral `ponytail`, `landing-page-design` (checklists A, B1–B11) y `ui-ux-pro-max` (contraste claro/oscuro, accesibilidad teclado, foco visible, responsive `375px`/`768px`/`1024px`/`1440px`) + ejecución completa de `vendor\bin\phpunit` | Suite PHPUnit 100% verde, build CSS limpio y verificación funcional de todos los flujos sin errores | ✅ |

---

## 2. Esquema de Landing + Acceso (`GET /` no autenticado y `GET /login`) · `landing-page-design`

> **Una oferta -> una audiencia -> una acción primaria:** convertir transcripciones `.txt` de reuniones de ingeniería y producto en minutas editables, tareas accionables y decisiones técnicas indexadas en SQLite local, entrando directamente al espacio de trabajo.
> **Eliminación total de elementos heredados/falsos:** prohibido incluir el reCAPTCHA simulado, enlaces rotos `href="#"` (`#btn-support`, `#btn-assistant`, `#link-forgot-pwd`), textos en mayúsculas sostenidas o banners institucionales ajenos al producto (`UNIR CON Venezuela`).

### 2.1 Page Outline (Orden de Secciones y Construcción A6)

1. **Enlace de accesibilidad `Saltar al contenido principal`** (`href="#contenido-principal"`, visible al recibir foco por teclado) + **Barra de navegación flotante superior (`<header>` / `<nav>`)**:
   - Separada del borde superior (`top-4`), centrada (`max-w-6xl`), superficie translúcida sobre `backdrop-blur-md` con borde perimetral completo (B4/B7).
   - Marca `App Transcripciones` con isotipo SVG limpio (trazo Solar/Phosphor), enlaces internos reales (`#beneficios`, `#como-funciona`, `#preguntas`), botón conmutador de tema oscuro/claro y CTA compacto (`text-sm font-semibold`) hacia `#acceso`.
   - En móvil: botón de menú con icono transformable animado en curva B7 que abre un panel de altura completa (`min-h-dvh`) donde los enlaces entran escalonados cada `60ms` (tope `400ms`) subiendo `24px` (`Spacing-300`).
2. **Hero + Panel de Acceso Integrado (`#acceso`, sobre el pliegue)**:
   - **Columna izquierda (`max-w-[680px]`):** insignia técnica sobria, titular principal con degradado exclusivo en texto (B5) y saltos de línea semánticos, subtítulo específico, CTA primario que enfoca el formulario o activa el acceso rápido de demostración, y **tira de prueba real conectada a SQLite** (`$portalStats`: reuniones procesadas, tareas extraídas, decisiones indexadas en FTS5 y minutos analizados).
   - **Columna derecha (`#panel-acceso`):** tarjeta de autenticación real con borde completo (`rounded-2xl p-6`, elementos internos `rounded-lg` respetando B3 `16px - 8px = 8px`), pestañas accesibles (`role="tablist"`) **Iniciar sesión** / **Crear cuenta**, campos con `<label for="...">` visibles, botón mostrar/ocultar contraseña, botón real **Continuar con Google** (`POST /auth/google`) y botón/píldora funcional **Autocompletar cuenta demo** (`admin@transcripciones.local` / `Admin123*`) que rellena los campos o permite probar en 1 clic sin enlaces muertos.
3. **Del problema a la solución (`#problema`)**:
   - Contraste claro en dos tarjetas comparativas: lo que ocurre cuando los acuerdos quedan enterrados en archivos `.txt` sueltos frente a una lectura lineal `O(n)` que vincula cada tarea y decisión técnica a su `MM:SS` exacto.
4. **Beneficios orientados a resultados reales (`#beneficios`)**:
   - Cuatro tarjetas de resultados medibles con iconografía SVG Phosphor/Solar (`24x24`), borde perimetral completo y cero relleno verbal.
5. **Sección obligatoria Tagline Reveal palabra por palabra (`#manifiesto`, regla B11)**:
   - Momento tipográfico independiente (`text-4xl md:text-5xl font-semibold`, `max-w-[680px]`, mínimo 2 líneas) donde cada palabra se ilumina individualmente de `opacity-30` a `opacity-100` en orden de lectura mediante `IntersectionObserver` y curva B7.
6. **Cómo funciona en 3 pasos (`#como-funciona`)**:
   - Secuencia numerada en `Geist Mono` (`01`, `02`, `03`) desde la subida del `.txt` hasta la búsqueda semántica `FTS5` y la exportación en Markdown.
7. **Prueba técnica real y garantía operativa (`#prueba`)**:
   - Muestra verificable basada en datos reales del repositorio (estructura de metadatos, acuerdos detectados y motor híbrido local + IA gratuita) junto con reversión de riesgo: ejecución 100% local en SQLite, sin tarjeta de crédito y con cuenta semilla lista al instante.
8. **Preguntas frecuentes técnicas (`#preguntas`, 6 preguntas reales para AEO)**:
   - Implementadas con `<details>` y `<summary>` nativos accesibles, sin JavaScript pesado, respondiendo dudas técnicas reales de equipos de desarrollo.
9. **CTA final + Pie de página semántico (`<footer>`)**:
   - Repite la promesa y el CTA primario hacia `#acceso`, e incluye enlaces funcionales que abren modales `<dialog>` nativos para **Privacidad y almacenamiento SQLite local** y **Condiciones de uso del repositorio** (cero `href="#"`).

### 2.2 Hero Copy en Español (Cero Guiones en el Texto · Reglas A5, B1 y B5)

- **Etiqueta superior:** `Minutas ejecutivas y memoria técnica sobre SQLite FTS5`
- **Titular (`h1`, `max-w-[680px]`, saltos donde quiebra la idea):**
  `Convierte tus transcripciones .txt`
  `en tareas claras y decisiones técnicas`
  `listas para consultar en segundos`
- **Subtítulo (`max-w-[680px]`):**
  `Sube el archivo de tu reunión y obtén en una sola lectura la minuta editable, los responsables asignados y un buscador semántico conectado al minuto exacto de cada acuerdo.`
- **CTA primario (verbo + lo que obtiene):**
  `Entrar al espacio de trabajo`
- **Acción secundaria de prueba sin fricción (dentro del panel de acceso):**
  `Cargar credenciales de demostración`
- **Línea de prueba real (alimentada en vivo desde SQLite `$portalStats`):**
  `Repositorio activo con {meetings_count} reuniones analizadas, {tasks_count} tareas registradas y {decisions_count} decisiones técnicas indexadas en SQLite.`

### 2.3 Beneficios Orientados a Resultados Reales (4 Bloques · Cero Guiones)

1. **Acuerdos accionables desde la primera lectura.** Extrae compromisos, responsables y marcas de tiempo `MM:SS` en un único recorrido lineal del archivo `.txt` sin releer ni duplicar sesiones gracias a la verificación `SHA256`.
2. **Decisiones de arquitectura localizables en milisegundos.** Encuentra por qué se eligió un flujo de pago, una plantilla de mensajería o una regla de datos combinando el índice nativo `SQLite FTS5` con expansión semántica en español.
3. **Trazabilidad directa al diálogo original.** Salta desde cualquier tarea o bloqueo técnico hasta la línea exacta donde se dijo en la llamada, o crea acuerdos nuevos desde cualquier intervención con un clic.
4. **Privacidad total en tu propio servidor.** Tus transcripciones, sesiones y minutas viven en una base `SQLite` local bajo tu control, funcionando al instante con heurísticas deterministas o con modelos gratuitos opcionales.

### 2.4 Cómo Funciona en 3 Pasos (Cero Guiones)

1. **Paso `01` · Sube tu archivo `.txt` o carga la muestra real.** Arrastra una transcripción exportada con marcas `MM:SS Autor: Texto`; el parser valida el archivo, filtra ruido automático y extrae fecha, duración y participantes en una sola pasada.
2. **Paso `02` · Revisa la minuta y gestiona las tareas del equipo.** Edita el resumen ejecutivo en línea, marca avances con un clic, reasigna responsables y descarga el acta completa en formato Markdown lista para compartir.
3. **Paso `03` · Consulta decisiones técnicas en todas tus sesiones.** Abre el buscador con `Ctrl + K`, filtra por categoría o participante y localiza al instante acuerdos de flujo, arquitectura o bloqueos operativos.

### 2.5 Tagline Reveal Palabra por Palabra (Obligatorio · Regla B11)

- **Texto exacto (3 líneas semánticas, cero guiones, `max-w-[680px]`, `text-4xl sm:text-5xl font-semibold`):**
  `Ningún acuerdo técnico vuelve a perderse en un chat.`
  `Cada tarea conserva su autor, su contexto real`
  `y el minuto exacto donde el equipo tomó la decisión.`
- **Especificación técnica de animación:**
  - En el renderizado PHP/JS, cada palabra se envuelve en `<span class="tagline-word">` iniciando con opacidad tenue (`opacity: 0.28`).
  - Se utiliza `IntersectionObserver` observando el contenedor/líneas de umbral (o cálculo acotado en `requestAnimationFrame` disparado únicamente mientras la sección intersecta el viewport; **prohibido** `window.addEventListener('scroll')` sin control).
  - Cada palabra transiciona individualmente en orden de lectura a `opacity: 1` usando `transition: opacity 500ms cubic-bezier(0.22, 1, 0.36, 1)`.
  - Si `window.matchMedia('(prefers-reduced-motion: reduce)').matches` es verdadero, todas las palabras se muestran directamente con `opacity: 1` sin animación escalonada.

### 2.6 FAQ Real de 6 Preguntas Técnicas (Cero Guiones en Prosa)

1. **¿Qué formato deben tener los archivos `.txt` que subo a la plataforma?**
   Acepta archivos de texto plano `.txt` de hasta 5 MB codificados en UTF8 (o convertidos automáticamente al ingresar). Reconoce cabeceras como `Meeting started`, `Duration`, `Participants`, enlaces de transcripción original y líneas cronológicas con formato `MM:SS Autor: Intervención`, filtrando avisos automáticos de extensiones de grabación.
2. **¿Dónde se almacenan las transcripciones y qué privacidad ofrece SQLite?**
   Toda la información se guarda localmente en el archivo `writable/database/transcripciones.db` dentro de tu propio entorno CodeIgniter 4. Ninguna transcripción sale hacia terceros a menos que actives voluntariamente una clave de API para un proveedor externo en tu configuración `.env`.
3. **¿Cómo funciona el buscador semántico con SQLite FTS5?**
   Cada decisión técnica, acuerdo de arquitectura o bloqueo se indexa mediante triggers automáticos en una tabla virtual `FTS5` que ordena resultados por relevancia con el algoritmo `bm25()`. Además, el motor expande tu consulta con sinónimos técnicos del dominio en español para hallar conceptos relacionados aunque no recuerdes la palabra literal.
4. **¿Cómo protege mis sesiones la autenticación híbrida con JWT y Google?**
   Al iniciar sesión con tus credenciales locales o mediante Google OAuth, el servidor sincroniza una sesión nativa de CodeIgniter 4 y emite en paralelo un token firmado con `HMAC SHA256` en cookie `HttpOnly` y encabezado `Bearer`, protegiendo tanto las vistas web como cada endpoint JSON de la API.
5. **¿Necesito pagar una suscripción de inteligencia artificial para extraer minutas y tareas?**
   No. La aplicación incluye por defecto un analizador heurístico determinista en español que funciona sin conexión y sin costo en milisegundos. Si deseas síntesis generativa adicional, puedes conectar modelos gratuitos compatibles en `app/Config/AI.php` conservando siempre el respaldo local automático si no hay red.
6. **¿Qué ocurre si subo dos veces el mismo archivo `.txt` o quiero llevarme la minuta a otra herramienta?**
   Antes de procesar cualquier subida se calcula la huella criptográfica `SHA256` del archivo para evitar duplicados y abrir de inmediato la reunión existente. En cualquier momento puedes exportar la minuta completa, las tareas con su estado y las decisiones técnicas en un archivo `.md` estándar.

### 2.7 SEO / AEO y Elección Deliberada de Layout

- **Recomendación SEO / AEO:** `index, follow` en la portada pública (`GET /` y `GET /login`), con HTML5 semántico (`<header>`, `<nav>`, `<main>`, `<section>`, `<details>/<summary>`, `<footer>`), metadatos completos:
  - `<title>App Transcripciones · Minutas ejecutivas y buscador técnico en SQLite</title>`
  - `<meta name="description" content="Convierte archivos .txt de reuniones en minutas editables, tareas con responsable y decisiones técnicas indexadas con SQLite FTS5.">`
  - Etiquetas `og:title`, `og:description`, `og:type="website"` y favicon SVG propio embebido/local.
- **Layout seleccionado (`landing-page-design` A3):** **Tipo A (Classic hero plus sections) con Panel de Acceso integrado en el Hero**.
  - **Justificación:** En una herramienta de productividad técnica donde `GET /` (cuando no hay sesión) y `GET /login` son la puerta única al sistema, combinar el argumento claro de Tipo A con el formulario real de acceso en la primera pantalla permite conversión inmediata en 0 clics extra para usuarios recurrentes o evaluadores con la cuenta demo, mientras ofrece debajo toda la evidencia arquitectónica (beneficios, flujo de 3 pasos, métricas reales SQLite y FAQ técnica) para quienes aterrizan por primera vez.

---

## 3. Sistema Visual Tailwind CSS v4 (`resources/css/app.css` -> `public/assets/css/tailwind.css`)

1. **Compilación única:**
   - Entrada: `resources/css/app.css` con `@import "tailwindcss";`, `@source "../../app/Views/**/*.php";` y `@source "../../public/assets/js/**/*.js";`.
   - Salida minificada: `public/assets/css/tailwind.css` vía `npm run build`. Las vistas cargan únicamente `assets/css/tailwind.css` (se elimina el enlace a `assets/css/app.css` heredado para garantizar cero colisiones).
2. **Tipografía (`landing-page-design` B1):**
   - Fuente primaria única de interfaz: **`Manrope`** en pesos `400` (`font-normal`), `500` (`font-medium`), `600` (`font-semibold`) y `700` (`font-bold`).
   - Fuente monoespaciada funcional: **`Geist Mono`** (`400`, `500`, `600`) **exclusivamente** para marcas de tiempo `MM:SS`, contadores numéricos, atajos `Ctrl + K`, duraciones y metadatos técnicos.
   - **Prohibiciones estrictas:** prohibido `Inter`, `Roboto`, `Arial`, `Helvetica`; prohibido `italic` en toda la interfaz; prohibido `font-extrabold`/`font-black` (`800`/`900`); prohibidos tamaños arbitrarios como `text-[19px]` (usar exclusivamente pasos de la escala oficial de Tailwind `text-xs` a `text-6xl`).
   - **Control de líneas:** `text-wrap: balance` en titulares (`h1`–`h4`) y `text-wrap: pretty` en cuerpo de texto (`p`, `li`, `dd`) para impedir palabras huérfanas.
3. **Modo Oscuro y Claro Real (`landing-page-design` B4/B5 + `ui-ux-pro-max`):**
   - Variante de clase en `resources/css/app.css`: `@custom-variant dark (&:where(.dark, .dark *));`.
   - Inicialización sin parpadeo (FOUC) en `<head>` leyendo `localStorage.getItem('app_theme')` con respaldo a `window.matchMedia('(prefers-color-scheme: dark)')`.
   - **Superficies en Modo Oscuro (exclusivamente paleta plana B4, cero degradados de fondo):**
     - Fondo base lienzo: `#181818`
     - Paneles laterales / cabeceras / contenedores base: `#1F1F1F`
     - Tarjetas / modales `<dialog>` / campos de entrada: `#272727`
     - Estados hover / píldoras activas / bordes divisorios: `#313131`
     - Contraste profundo puntual: `#000000`
   - **Superficies en Modo Claro (contraste mínimo 4.5:1 garantizado):**
     - Fondo base lienzo: `#F8FAFC` (`slate-50`), tarjetas y paneles `#FFFFFF`, superficies sutiles `#F1F5F9` (`slate-100`).
     - Texto primario `#0F172A` (`slate-900`), texto secundario `#475569` (`slate-600`), bordes visibles `border-slate-200` (`#E2E8F0`).
   - **Bordes y Degradados (B4/B5):**
     - **Prohibido** aplicar borde a un solo lado de una tarjeta (`border-l-4` en cards prohibido); las tarjetas llevan borde perimetral completo (`border`) o separación limpia de superficie.
     - **Prohibido** cualquier degradado en fondos, botones o tarjetas. El **único degradado permitido** vive en el texto del `<h1>` del Hero (`bg-gradient-to-r bg-clip-text text-transparent`): en modo oscuro `#FFFFFF` -> `#9B9B9B`; en modo claro `#000000` -> `#666666`.
4. **Espaciado (B2), Radios Anidados (B3), Movimiento (B7) e Iconos (B6):**
   - Espaciado restringido a los pasos equivalentes de B2 (`0, 2px, 4px, 8px, 12px, 16px, 24px, 32px, 40px, 48px, 64px, 80px, 96px`). Botones principales con `py-2 px-3` (`8px` vertical, `12px` horizontal) y `text-base font-semibold`; botones compactos de cabecera/barra con `text-sm font-semibold`.
   - Radios anidados calculados con `inner = outer - gap` cuando `gap < 32px` (ej. tarjeta `rounded-2xl` [16px] con `p-2` [8px] -> elemento hijo `rounded-lg` [8px]).
   - Curva base obligatoria: `ease-[cubic-bezier(0.22,1,0.36,1)]` con `duration-500` para transiciones de posición/panel y `duration-300` para cambios de color/opacidad en hover (nunca `< 200ms`). Animar solo `transform` y `opacity`. Respetar `@media (prefers-reduced-motion: reduce)`.
   - Iconos SVG inline estilo **Phosphor** o **Solar** (`viewBox="0 0 24 24"`, trazo uniforme `stroke-width="1.75"` o `"2"`, `aria-hidden="true"`). Cero emojis usados como iconos y cero Material Icons.

---

## 4. Arquitectura UX del Workspace Autenticado (`GET /` con sesión activa)

Diseño tipo aplicación SaaS de alta densidad informativa y cero ruido visual (inspirado en Linear / Granola), estructurado en **3 columnas coordinadas en escritorio** (`aside` colapsable + `main` central por módulos + `aside` derecho fijo para el visor cronológico) y **navegación adaptativa por paneles/drawers en móvil y tablet**:

1. **Sidebar Izquierdo Colapsable (`<aside id="workspace-sidebar">`):**
   - Botón para colapsar/expandir el sidebar (animado con `transform` / estado limpio, recordado en `localStorage`).
   - **Acciones de ingesta arriba:**
     - Botón primario **Subir archivo `.txt`** que abre un modal nativo `<dialog id="upload-dialog">` con zona **arrastrar y soltar** (*drag and drop*), selector de archivo `.txt` (`<= 5 MB`), campo opcional de título personalizado y estados skeleton durante el análisis.
     - Botón secundario **Cargar muestra (`formato-real.txt`)** que invoca `POST /api/meetings/upload` con `{use_sample: 1}`.
   - **Buscador rápido de reuniones** (`<input type="search">`) que filtra instantáneamente en cliente la lista del historial por título o nombre de archivo.
   - **Historial real de reuniones (`meetings`):** cada ítem muestra título, fecha corta, badge de duración en `Geist Mono` e indicador visual de reunión activa, además de botón de eliminar reunión que abre un `<dialog id="confirm-delete-dialog">` accesible (prohibido `window.confirm` / `window.alert`).
   - **Pie de usuario y preferencias:** avatar con iniciales generadas del nombre, nombre y correo real (`currentUser`), insignia discreta de sesión activa, botón alternador de tema oscuro/claro y formulario/botón de **Cerrar sesión** (`POST /auth/logout`).

2. **Cabecera de Reunión Activa (`<header id="meeting-header">`):**
   - **Título editable inline:** muestra el título de la reunión activa; al hacer clic o activar el botón editar revela un `<input>` inline que guarda al pulsar `Enter` o perder foco mediante `PATCH /api/meetings/(:num)` (`{title}`), sincronizando el título en la cabecera y en el sidebar sin recargar.
   - **Fila de metadatos reales (`Geist Mono` para valores numéricos/temporales):** inicio (`started_at`), duración (`duration_minutes`), motor utilizado (`ai_model_used`), archivo fuente (`source_filename`) y enlace externo a transcripción original (`original_url`, renderizado solo cuando existe).
   - **Botón Exportar minuta en Markdown (`.md`):** enlace/botón directo a `GET /api/meetings/(:num)/export` que descarga el acta completa formateada en Markdown.
   - **Chips interactivos de participantes con conteo real:** píldora `Todos ({total})` seguida de un chip por cada hablante con el número exacto de intervenciones en la reunión activa (ej. `Vanesa Machado (21)`), filtrando con 1 clic el visor cronológico y resaltando el filtro activo.

3. **Área Central de Trabajo con Conmutador Ágil de Vistas (`Módulo 1` vs `Módulo 2`):**
   - Barra de pestañas de primer nivel con contadores reales en `Geist Mono` (`Minuta y tareas ({tasks.length})` · `Decisiones técnicas ({decisions.length})`) y píldora visual del atajo `Ctrl + K`.
   - **Vista Módulo 1 · Gestor de Minutas y Extracción Automática de Tareas:**
     - **Minuta ejecutiva editable:** tarjeta con el `executive_summary` y botón `Editar minuta` que alterna a `<textarea>` con botones `Guardar cambios` / `Cancelar`, persistiendo vía `PATCH /api/meetings/(:num)` (`{executive_summary}`).
     - **Barra de progreso real:** muestra proporción exacta de tareas completadas (`{done} de {total} completadas · {pct}%` en `Geist Mono`) y barra de avance animada exclusivamente con `transform: scaleX(...)` (`origin-left`, curva B7).
     - **Filtros por estado** (`Todas`, `Pendientes`, `En progreso`, `Completadas`) con conteo en cada filtro.
     - **Formulario compacto de creación rápida** (`POST /api/tasks`): resumen de tarea, selector de responsable poblado con los participantes reales + `Equipo`, y minuto `MM:SS`.
     - **Lista interactiva de tareas** (delegación de eventos en un único listener):
       - **Checkbox de 1 clic** para marcar como `done` o reabrir a `pending` (`PATCH /api/tasks/(:num)`).
       - **Edición inline del texto de la tarea** (`summary`) con guardado por `Enter` o botón confirmar (`PATCH /api/tasks/(:num)`).
       - Selectores compactos en línea para cambiar estado (`pending` | `in_progress` | `done`) y responsable (`assignee`) sin recargar.
       - Botón **`MM:SS` Ir al minuto** que desplaza y resalta la intervención exacta en el visor derecho + botón eliminar (`DELETE /api/tasks/(:num)`).
   - **Vista Módulo 2 · Buscador Semántico de Decisiones Técnicas (`SQLite FTS5`):**
     - **Buscador con atajo global `Ctrl + K` / `Cmd + K`:** al pulsarlo desde cualquier punto del workspace cambia a la vista del Módulo 2 y enfoca `#decision-search-input`; ejecuta consultas con `debounce(250ms)` y cancelación previa con `AbortController` sobre `GET /api/decisions/search`.
     - **Selector de alcance explícito:** conmutador de dos estados **Esta reunión** (`meeting_id = activa`) vs **Todas las reuniones** (`meeting_id` vacío, consulta global en SQLite).
     - **Filtros combinables** por categoría (`Todas`, `Decisiones`, `Bloqueos y limitantes`, `Flujo y arquitectura`) y por hablante.
     - **Chips de conceptos semánticos sugeridos** (`whatsapp`, `plantilla`, `pago`, `terminos`, `datos`, `bloqueo`, `arquitectura`) que disparan la búsqueda con 1 clic + barra informativa de términos expandidos (`expanded_terms`) devueltos por `DecisionModel`.
     - **Alta manual de decisión técnica** (bloque desplegable `<details>` o modal ligero conectado a `POST /api/decisions` con categoría, título, justificación, autor, minuto `MM:SS` y etiquetas opcionales).
     - **Tarjetas de resultados:** muestran insignia de categoría, título, justificación (`rationale`), cita contextual si existe (`entry_context`), nombre de la reunión (especialmente útil en alcance *Todas las reuniones*), autor, etiquetas semánticas, botón **Eliminar decisión** (`DELETE /api/decisions/(:num)`) y botón **Ir a reunión y minuto (`MM:SS`)** (si el resultado pertenece a otra reunión, carga primero `GET /api/meetings/(:num)` y luego desplaza el visor a la intervención exacta).

4. **Panel Derecho Fijo en Escritorio (`<aside id="transcript-viewer-panel">` · Visor Cronológico):**
   - Cabecera fija con contador en `Geist Mono` (`{visibles} / {total} intervenciones`), indicador si hay filtro de participante activo (con botón para limpiar) y campo `<input type="search">` de filtrado instantáneo en cliente.
   - Lista cronológica renderizada en una sola operación DOM mediante `DocumentFragment` y `textContent` (cero riesgo XSS).
   - Resaltado suave temporal (anillo y superficie destacada durante `2.2s` con transición de opacidad B7) al saltar desde una tarea o decisión técnica.
   - **Conversión en 1 clic desde cualquier línea del diálogo:** cada intervención incluye dos botones de acción rápida (`+ Tarea` y `+ Decisión`):
     - **`+ Tarea`:** llama a `POST /api/tasks` con `{meeting_id, entry_id: entry.id, timestamp_label: entry.timestamp_label, assignee: entry.speaker, summary: entry.content}` y actualiza en vivo la lista y contadores del Módulo 1.
     - **`+ Decisión`:** llama a `POST /api/decisions` con `{meeting_id, entry_id: entry.id, category: "decision", title: <primeros 90 caracteres limpios de entry.content>, rationale: entry.content, speaker: entry.speaker, timestamp_label: entry.timestamp_label}` e indexa al instante el acuerdo en `SQLite FTS5`, actualizando en vivo el Módulo 2.

---

## 5. § Contratos (Fuente Única de Verdad entre `copywriter`, `backend` y `frontend`)

### 5.1 Esquema SQLite (`writable/database/transcripciones.db`) y Consultas Agregadas

No se requieren nuevas migraciones destructivas; se reutilizan íntegramente las 5 tablas y el índice virtual FTS5 existentes:
1. **`users`**: `id`, `name`, `email` (UNIQUE), `password_hash`, `google_id`, `avatar_url`, `created_at`.
2. **`meetings`**: `id`, `user_id`, `title`, `source_filename`, `started_at`, `duration_minutes`, `participants_json`, `original_url`, `executive_summary`, `raw_hash` (CHAR 64 UNIQUE), `ai_model_used`, `created_at`.
3. **`transcript_entries`**: `id`, `meeting_id`, `timestamp_sec`, `timestamp_label`, `speaker`, `content`.
4. **`minutes_tasks`**: `id`, `meeting_id`, `entry_id`, `timestamp_label`, `summary`, `assignee`, `status` (`pending` | `in_progress` | `done`), `confidence_score`.
5. **`technical_decisions`** + **`technical_decisions_fts`** (`USING fts5(title, rationale, semantic_tags, speaker, content='technical_decisions', content_rowid='id')` con triggers `AFTER INSERT`, `AFTER UPDATE`, `AFTER DELETE`): `id`, `meeting_id`, `entry_id`, `category` (`decision` | `blocker` | `architecture`), `title`, `rationale`, `speaker`, `timestamp_label`, `semantic_tags`.

**Nuevo método requerido en `App\Models\MeetingModel`:**
- `getPortalStats(): array{meetings_count: int, tasks_count: int, decisions_count: int, total_minutes: int}`
  Ejecuta **una única consulta escalar SQLite** (cero `N+1`):
  ```sql
  SELECT
    (SELECT COUNT(*) FROM meetings) AS meetings_count,
    (SELECT COUNT(*) FROM minutes_tasks) AS tasks_count,
    (SELECT COUNT(*) FROM technical_decisions) AS decisions_count,
    (SELECT COALESCE(SUM(duration_minutes), 0) FROM meetings) AS total_minutes
  ```
  *(Nota: si `meetings_count === 0` y existe `.agents/resources/formato-transcripcion/formato-real.txt`, `MeetingController` / `AuthController` puede mantener el conteo real en `0` o sembrar idempotentemente la muestra; en todos los casos los números renderizados en `auth/login.php` provienen siempre de `getPortalStats()` en SQLite, jamás hardcodeados).*

---

### 5.2 Rutas HTTP y Contratos Exactos de Entrada / Salida

| Método | Ruta | Controlador::Método | Filtro | Payload de Entrada | Respuesta Exitosa / Formato Exacto | Errores HTTP |
|---|---|---|---|---|---|---|
| `GET` | `/` | `MeetingController::index` | Público / Híbrido | — | **Sin sesión:** vista `auth/login` con `['googleClientId' => str, 'portalStats' => {...}]`. **Con sesión:** vista `dashboard/index` con `['meetings' => [...], 'activeMeetingData' => ?array, 'aiEngineName' => str, 'currentUser' => {id, name, email}]` | — |
| `GET` | `/login` | `AuthController::loginView` | Público | — | Si hay sesión redirige a `/`. Si no, vista `auth/login` con `['googleClientId' => str, 'portalStats' => {...}]` | — |
| `POST` | `/auth/login` | `AuthController::login` | Público | JSON o Form `{email: str, password: str}` | `200` `{status: "ok", token: "<jwt>", user: {id: int, name: str, email: str}}` + Cookie `auth_token` + Sesión | `401`, `422` `{status: "error", message: str}` |
| `POST` | `/auth/register` | `AuthController::register` | Público | JSON o Form `{name: str, email: str, password: str}` | `200` `{status: "ok", token: "<jwt>", user: {id: int, name: str, email: str}}` + Cookie `auth_token` + Sesión | `409`, `422` `{status: "error", message: str}` |
| `POST` | `/auth/google` | `AuthController::google` | Público | JSON `{credential: str}` o `{email: str, name: str, google_id?: str}` | `200` `{status: "ok", token: "<jwt>", user: {id: int, name: str, email: str}}` | `401` `{status: "error", message: str}` |
| `POST` | `/auth/logout` | `AuthController::logout` | Público | — | JSON `{status: "ok"}` (si AJAX) o `302` a `/` | — |
| `GET` | `/api/meetings` | `MeetingController::list` | `auth` | — | `200` `{status: "ok", meetings: [{id, title, source_filename, started_at, duration_minutes, participants: str[], original_url, executive_summary, ai_model_used, created_at}, ...]}` | `401` |
| `POST` | `/api/meetings/upload` | `MeetingController::upload` | `auth` | `multipart/form-data` (`transcript_file`, `title?`) o JSON `{use_sample: 1}` | `200` `{status: "ok", deduplicated: bool, message: str, meeting_id: int, data: {meeting: {...}, entries: [...], tasks: [...], decisions: [...]}}` | `401`, `404`, `422` `{status: "error", field?: str, message: str}` |
| `GET` | `/api/meetings/(:num)` | `MeetingController::show/$1` | `auth` | — | `200` `{status: "ok", meeting: {...}, entries: [...], tasks: [...], decisions: [...]}` | `401`, `404` |
| `PATCH` *(+ `POST`)* | `/api/meetings/(:num)` | `MeetingController::update/$1` | `auth` | JSON `{title?: str, executive_summary?: str}` (al menos un campo válido no vacío) | `200` `{status: "ok", message: str, meeting: {id, title, source_filename, started_at, duration_minutes, participants: str[], original_url, executive_summary, ai_model_used, created_at}}` | `401`, `404`, `422` `{status: "error", field?: str, message: str}` |
| `GET` | `/api/meetings/(:num)/export` | `MeetingController::export/$1` | `auth` | — | `200` cuerpo `text/markdown; charset=UTF-8` con `Content-Disposition: attachment; filename="minuta-{id}-{slug}.md"` (contiene `# {title}`, metadatos, `## Minuta ejecutiva`, `## Tareas y compromisos` con `- [x]`/`- [ ]` y `## Decisiones técnicas`) | `401`, `404` |
| `DELETE` | `/api/meetings/(:num)` | `MeetingController::delete/$1` | `auth` | — | `200` `{status: "ok", deleted_id: int}` | `401`, `404` |
| `POST` | `/api/tasks` | `TaskController::create` | `auth` | JSON `{meeting_id: int, entry_id?: int\|null, summary: str, assignee?: str, timestamp_label?: str}` | `201` `{status: "ok", task: {id, meeting_id, entry_id, timestamp_label, summary, assignee, status: "pending", confidence_score: 1.0}}` | `401`, `422` `{status: "error", field: "summary", message: str}` |
| `PATCH` *(+ `POST`)* | `/api/tasks/(:num)` | `TaskController::update/$1` | `auth` | JSON `{status?: "pending"\|"in_progress"\|"done", assignee?: str, summary?: str}` | `200` `{status: "ok", task: {id, meeting_id, entry_id, timestamp_label, summary, assignee, status, confidence_score}}` | `401`, `404` |
| `DELETE` | `/api/tasks/(:num)` | `TaskController::delete/$1` | `auth` | — | `200` `{status: "ok", deleted_id: int}` | `401`, `404` |
| `GET` | `/api/decisions/search` | `SearchController::decisions` | `auth` | Query `?q=&category=&speaker=&meeting_id=` (`meeting_id=0` o vacío => todas las reuniones) | `200` `{status: "ok", query: str, expanded_terms: str[], results: [{id, meeting_id, meeting_title, entry_id, entry_context, category, title, rationale, speaker, timestamp_label, semantic_tags, rank_score}, ...]}` | `401` |
| `POST` | `/api/decisions` | `SearchController::create` | `auth` | JSON `{meeting_id: int, entry_id?: int\|null, category: "decision"\|"blocker"\|"architecture", title: str, rationale: str, speaker: str, timestamp_label: str, semantic_tags?: str}` | `201` `{status: "ok", decision: {id: int, meeting_id: int, entry_id: int\|null, category: str, title: str, rationale: str, speaker: str, timestamp_label: str, semantic_tags: str}}` (sincroniza `technical_decisions_fts` por trigger) | `401`, `404`, `422` `{status: "error", field: str, message: str}` |
| `DELETE` | `/api/decisions/(:num)` | `SearchController::delete/$1` | `auth` | — | `200` `{status: "ok", deleted_id: int}` (limpia `technical_decisions_fts` por trigger) | `401`, `404` |

---

### 5.3 Contrato del Estado Inicial Hidratado en `app/Views/dashboard/index.php` (`#initial-app-state`)

El servidor inyecta en `<script id="initial-app-state" type="application/json">` el objeto inicial para renderizado inmediato sin llamada extra de arranque:
```json
{
  "baseUrl": "http://localhost/App-transcripciones/public",
  "csrfTokenName": "csrf_test_name",
  "csrfHash": "<hash>",
  "meetings": [],
  "activeMeetingData": {
    "meeting": {},
    "entries": [],
    "tasks": [],
    "decisions": []
  },
  "semanticConcepts": ["whatsapp", "plantilla", "pago", "terminos", "datos", "limpieza", "bloqueo", "arquitectura"],
  "i18n": {}
}
```

---

### 5.4 Lista Exacta de Claves Requeridas en `app/Language/es/App.php`

**Reglas obligatorias para `copywriter` al definir estas claves:**
1. **Cero guiones (`-`, `–`, `—`) en textos visibles** de la Landing, encabezados, etiquetas y mensajes (regla `landing-page-design` B1). Escribir `App Transcripciones`, `SHA256`, `UTF8`, `Ctrl + K`, `arrastrar y soltar`, `un clic`.
2. **Sentence case** en todos los títulos, pestañas y botones (solo mayúscula inicial y nombres propios como `SQLite`, `FTS5`, `Google`, `Markdown`, `CodeIgniter`).
3. **Cero signos de exclamación (`!` / `¡`)** en mensajes de confirmación o error, y **cero palabras de relleno prohibidas** (B8).

#### A. Identidad global, accesibilidad y tema
- `appTitle` — Nombre limpio sin guion (`App Transcripciones`)
- `appSubtitle` — Descripción breve del sistema
- `metaDescription` — Meta description SEO para `<head>`
- `skipToContent` — `Saltar al contenido principal`
- `themeToggleDark` — `Cambiar a tema oscuro`
- `themeToggleLight` — `Cambiar a tema claro`
- `modalCloseBtn` — `Cerrar ventana`
- `btnCancel` — `Cancelar`
- `btnSave` — `Guardar cambios`
- `btnConfirmDelete` — `Eliminar definitivamente`

#### B. Navegación y Hero de Landing (`app/Views/auth/login.php`)
- `landingNavBenefits` — `Beneficios`
- `landingNavHow` — `Cómo funciona`
- `landingNavFaq` — `Preguntas frecuentes`
- `landingNavCta` — `Entrar al espacio`
- `landingMenuOpen` — `Abrir menú de navegación`
- `landingMenuClose` — `Cerrar menú de navegación`
- `heroBadge` — Etiqueta contextual superior del Hero
- `heroHeadlineLine1` — Línea 1 del titular (`Convierte tus transcripciones .txt`)
- `heroHeadlineLine2` — Línea 2 del titular (`en tareas claras y decisiones técnicas`)
- `heroHeadlineLine3` — Línea 3 del titular (`listas para consultar en segundos`)
- `heroSubheadline` — Subtítulo específico del Hero (`max-w-[680px]`)
- `heroPrimaryCta` — `Entrar al espacio de trabajo`
- `heroSecondaryDemoCta` — `Probar con cuenta de demostración`
- `statMeetingsLabel` — `Reuniones procesadas`
- `statTasksLabel` — `Tareas extraídas`
- `statDecisionsLabel` — `Decisiones en FTS5`
- `statMinutesLabel` — `Minutos analizados`

#### C. Panel de Acceso Real (`login`, `register`, `google`, `demo`)
- `loginTitle` — Título de la pestaña/página de acceso
- `loginSubtitle` — Subtítulo breve de la tarjeta de acceso
- `tabLogin` — `Iniciar sesión`
- `tabRegister` — `Crear cuenta`
- `fieldName` — `Nombre completo`
- `fieldNamePlaceholder` — `Ej. Vanesa Machado`
- `fieldEmail` — `Correo electrónico`
- `fieldEmailPlaceholder` — `nombre@equipo.local`
- `fieldPassword` — `Contraseña`
- `fieldPasswordPlaceholder` — `Mínimo 6 caracteres`
- `togglePasswordShow` — `Mostrar contraseña`
- `togglePasswordHide` — `Ocultar contraseña`
- `btnLogin` — `Entrar al espacio de trabajo`
- `btnRegister` — `Crear cuenta y entrar`
- `authDividerOr` — `o continúa con`
- `btnGoogleLogin` — `Continuar con Google`
- `googleNoClientNote` — Nota clara sobre modo local de Google cuando `GOOGLE_CLIENT_ID` no está definido
- `demoCredentialsLabel` — `Acceso rápido de evaluación`
- `demoCredentialsFillBtn` — `Usar cuenta semilla`
- `demoCredentialsHint` — Indicación de cuenta `admin@transcripciones.local`
- `authErrorInvalid` — `Las credenciales ingresadas no coinciden con ninguna cuenta activa.`
- `authErrorRequired` — `Completa todos los campos requeridos con un formato válido.`
- `authErrorNetwork` — `No fue posible conectar con el servidor local. Verifica tu servicio PHP y vuelve a intentarlo.`

#### D. Secciones Argumentales de la Landing (Problema, Beneficios, Tagline B11, Pasos, Prueba, FAQ y Legal)
- `problemSectionLabel` — `El problema habitual`
- `problemSectionTitle` — Título de contraste entre `.txt` crudo y memoria indexada
- `problemBeforeTitle` — Título tarjeta situación sin sistema
- `problemBeforeBody` — Descripción concreta de pérdida de acuerdos en archivos sueltos
- `problemAfterTitle` — Título tarjeta con `App Transcripciones`
- `problemAfterBody` — Descripción concreta de lectura `O(n)` e indexación en SQLite
- `benefitsSectionLabel` — `Resultados medibles`
- `benefitsSectionTitle` — Título de la sección de beneficios
- `benefit1Title`, `benefit1Body` — Beneficio 1 (extracción en 1 pasada y deduplicación `SHA256`)
- `benefit2Title`, `benefit2Body` — Beneficio 2 (búsqueda semántica `SQLite FTS5` + `bm25()`)
- `benefit3Title`, `benefit3Body` — Beneficio 3 (trazabilidad al minuto `MM:SS` y acciones en un clic)
- `benefit4Title`, `benefit4Body` — Beneficio 4 (privacidad local en SQLite y costo cero obligatorio)
- `taglineLine1` — `Ningún acuerdo técnico vuelve a perderse en un chat.`
- `taglineLine2` — `Cada tarea conserva su autor, su contexto real`
- `taglineLine3` — `y el minuto exacto donde el equipo tomó la decisión.`
- `howSectionLabel` — `Flujo de trabajo`
- `howSectionTitle` — `De archivo plano a base de conocimiento en tres pasos`
- `howStep1Title`, `howStep1Body` — Paso 1 (subida `.txt` y parseo `O(n)`)
- `howStep2Title`, `howStep2Body` — Paso 2 (edición de minuta, tareas y exportación `.md`)
- `howStep3Title`, `howStep3Body` — Paso 3 (consulta semántica con `Ctrl + K` en todas las sesiones)
- `proofSectionLabel` — `Ejecución local verificable`
- `proofSectionTitle` — Título de garantía operativa y riesgo cero
- `proofRiskFreeNote` — Texto de reversión de riesgo (sin tarjeta, SQLite local, exportación libre en Markdown)
- `faqSectionLabel` — `Preguntas técnicas frecuentes`
- `faqSectionTitle` — `Todo sobre el motor local, la privacidad y los formatos soportados`
- `faq1Q`, `faq1A` — Pregunta y respuesta 1 (formato `.txt` y tamaño máximo 5 MB)
- `faq2Q`, `faq2A` — Pregunta y respuesta 2 (almacenamiento local y privacidad SQLite)
- `faq3Q`, `faq3A` — Pregunta y respuesta 3 (motor `FTS5`, `bm25()` y expansión semántica)
- `faq4Q`, `faq4A` — Pregunta y respuesta 4 (sesión híbrida CodeIgniter + `JWT` y `Google`)
- `faq5Q`, `faq5A` — Pregunta y respuesta 5 (analizador heurístico incluido + modelos gratuitos opcionales)
- `faq6Q`, `faq6A` — Pregunta y respuesta 6 (deduplicación `SHA256` y exportación en Markdown `.md`)
- `finalCtaTitle` — Título de cierre orientado a acción
- `finalCtaBody` — Subtítulo breve de cierre
- `finalCtaBtn` — `Entrar al espacio de trabajo`
- `footerPrivacyLink` — `Privacidad y datos locales`
- `footerTermsLink` — `Condiciones de uso`
- `legalPrivacyTitle`, `legalPrivacyBody` — Contenido real del modal `<dialog>` de privacidad SQLite local
- `legalTermsTitle`, `legalTermsBody` — Contenido real del modal `<dialog>` de condiciones de uso

#### E. Workspace Autenticado: Sidebar, Modal Drag and Drop y Cabecera de Reunión
- `sidebarToggleCollapse` — `Contraer u ocultar barra lateral`
- `sidebarMeetingsHeader` — `Historial de reuniones`
- `sidebarSearchPlaceholder` — `Filtrar reuniones...`
- `sidebarEmptySearch` — `Ninguna reunión coincide con tu búsqueda.`
- `navUploadTxt` — `Subir archivo .txt`
- `navLoadSample` — `Cargar muestra formato real`
- `navDeleteMeeting` — `Eliminar reunión`
- `navExportMarkdown` — `Exportar minuta (.md)`
- `navLogout` — `Cerrar sesión`
- `aiModeLabel` — `Motor activo`
- `jwtActiveBadge` — `Sesión verificada`
- `selectMeetingPlaceholder` — `Selecciona una reunión procesada`
- `uploadModalTitle` — `Subir transcripción de reunión`
- `uploadModalDesc` — `Selecciona o suelta un archivo .txt con marcas de tiempo para extraer su minuta, tareas y decisiones.`
- `uploadDropzonePrompt` — `Arrastra aquí tu archivo .txt o haz clic para seleccionarlo`
- `uploadDropzoneHint` — `Texto plano UTF8 hasta 5 MB`
- `uploadCustomTitleLabel` — `Título personalizado (opcional)`
- `uploadCustomTitlePlaceholder` — `Ej. Revisión de integración de pagos y mensajería`
- `uploadSubmitBtn` — `Procesar transcripción`
- `deleteModalTitle` — `Eliminar reunión del repositorio`
- `deleteModalBody` — `Se borrarán la transcripción, las tareas y las decisiones indexadas de esta sesión en SQLite.`
- `meetingTitleEditHint` — `Editar título de la reunión`
- `meetingUpdatedMsg` — `Cambios guardados en la reunión.`
- `meetingNotFound` — `La reunión solicitada ya no existe.`
- `metaStartedAt` — `Inicio`
- `metaDuration` — `Duración`
- `metaDurationUnit` — `min`
- `metaParticipants` — `Participantes`
- `metaAllParticipants` — `Todos`
- `metaOriginalUrl` — `Abrir fuente original`
- `metaAiEngine` — `Analizador`
- `metaSourceFile` — `Archivo`
- `metaFilterBySpeaker` — `Filtrando intervenciones de`
- `metaClearSpeakerFilter` — `Mostrar todos`

#### F. Módulo 1: Gestor de Minutas y Extracción Automática de Tareas
- `tabModuleMinutes` — `Minuta y tareas`
- `moduleMinutesTitle` — `Gestor de minutas y tareas`
- `moduleMinutesDesc` — `Síntesis editable y compromisos accionables vinculados al minuto exacto de la llamada.`
- `executiveSummaryTitle` — `Minuta ejecutiva`
- `btnEditSummary` — `Editar minuta`
- `summaryPlaceholder` — `Escribe el resumen ejecutivo de los acuerdos y puntos tratados...`
- `tasksProgressLabel` — `Avance de acuerdos`
- `tasksCompletedOf` — `completadas`
- `tasksHeader` — `Tareas y compromisos`
- `filterTasksAll` — `Todas`
- `filterTasksPending` — `Pendientes`
- `filterTasksInProgress` — `En progreso`
- `filterTasksDone` — `Completadas`
- `statusPending` — `Pendiente`
- `statusInProgress` — `En progreso`
- `statusDone` — `Completada`
- `taskAssigneeLabel` — `Responsable`
- `taskDefaultAssignee` — `Equipo`
- `taskMinuteLabel` — `Minuto`
- `taskJumpToMinute` — `Ir al minuto`
- `taskAddPlaceholder` — `Registrar nuevo compromiso o tarea de la reunión...`
- `taskAddBtn` — `Agregar tarea`
- `taskEditBtn` — `Editar texto de la tarea`
- `taskToggleDoneLabel` — `Alternar tarea completada`
- `taskDeleteBtn` — `Eliminar tarea`
- `taskCreatedMsg` — `Tarea agregada a la minuta.`
- `taskUpdatedMsg` — `Tarea actualizada.`
- `taskDeletedMsg` — `Tarea eliminada.`
- `emptyTasks` — `No hay tareas registradas para el filtro actual. Agrega una arriba o créala con un clic desde cualquier línea de la transcripción.`

#### G. Módulo 2: Buscador Semántico de Decisiones Técnicas (`SQLite FTS5`)
- `tabModuleDecisions` — `Decisiones técnicas`
- `moduleDecisionsTitle` — `Buscador semántico de decisiones técnicas`
- `moduleDecisionsDesc` — `Consulta acuerdos de arquitectura, reglas de negocio y bloqueos con SQLite FTS5 y expansión semántica.`
- `searchDecisionsPlaceholder` — `Buscar por concepto técnico (whatsapp, plantilla, pago, datos, bloqueo)...`
- `searchShortcutHint` — `Ctrl + K`
- `scopeThisMeeting` — `Esta reunión`
- `scopeAllMeetings` — `Todas las reuniones`
- `semanticChipsLabel` — `Conceptos frecuentes`
- `expandedTermsPrefix` — `Búsqueda expandida con`
- `filterCatAll` — `Todas`
- `filterCatDecision` — `Decisiones`
- `filterCatBlocker` — `Bloqueos y limitantes`
- `filterCatArchitecture` — `Flujo y arquitectura`
- `catDecision` — `Decisión técnica`
- `catBlocker` — `Bloqueante o limitación`
- `catArchitecture` — `Flujo o arquitectura`
- `btnNewDecisionToggle` — `Registrar decisión manual`
- `decisionFormTitleLabel` — `Título del acuerdo o hallazgo`
- `decisionFormTitlePlaceholder` — `Ej. Ubicar aceptación de condiciones antes de la pasarela de pago`
- `decisionFormRationaleLabel` — `Justificación y contexto técnico`
- `decisionFormRationalePlaceholder` — `Describe por qué se tomó la decisión o qué restricción técnica aplica...`
- `decisionFormCategoryLabel` — `Categoría`
- `decisionFormTagsLabel` — `Etiquetas semánticas (opcional)`
- `decisionFormTagsPlaceholder` — `pago, formulario, arquitectura`
- `decisionAddBtn` — `Guardar decisión`
- `decisionDeleteBtn` — `Eliminar decisión`
- `decisionViewInContext` — `Ver en transcripción`
- `decisionSwitchAndJump` — `Abrir reunión e ir al minuto`
- `decisionSpeakerLabel` — `Autor`
- `decisionTagsLabel` — `Conceptos`
- `decisionMeetingOriginLabel` — `Sesión`
- `decisionCreatedMsg` — `Decisión técnica registrada e indexada en FTS5.`
- `decisionDeletedMsg` — `Decisión técnica eliminada del índice.`
- `emptyDecisions` — `No se encontraron decisiones técnicas con estos criterios. Prueba otro concepto semántico o cambia el alcance a todas las reuniones.`

#### H. Visor Cronológico de Transcripción y Vista 404
- `viewerTitle` — `Transcripción cronológica`
- `viewerFilterPlaceholder` — `Filtrar intervenciones del diálogo...`
- `viewerLinesCount` — `intervenciones`
- `viewerQuickTaskBtn` — `+ Tarea`
- `viewerQuickTaskTitle` — `Convertir esta intervención en tarea de la minuta`
- `viewerQuickDecisionBtn` — `+ Decisión`
- `viewerQuickDecisionTitle` — `Convertir esta intervención en decisión técnica indexada`
- `viewerEmptyFilter` — `Ninguna línea del diálogo coincide con el filtro aplicado.`
- `emptyMeetingState` — `Aún no hay reuniones cargadas en el espacio de trabajo. Sube un archivo .txt o carga la muestra real para comenzar.`
- `uploadSuccessMsg` — `Transcripción procesada e indexada correctamente.`
- `uploadDuplicateMsg` — `Este archivo ya estaba registrado por su huella SHA256. Se abrió la reunión existente.`
- `uploadErrorInvalidFile` — `Archivo no válido. Selecciona un archivo de texto plano .txt menor a 5 MB.`
- `error404Badge` — `Error 404`
- `error404Title` — `No encontramos la página que buscas`
- `error404Body` — `La ruta solicitada no existe o fue movida dentro de App Transcripciones.`
- `error404BackBtn` — `Volver al espacio principal`
