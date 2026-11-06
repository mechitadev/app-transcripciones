<?php
/**
 * Vista Principal del Workspace SaaS Autenticado (`app/Views/dashboard/index.php`).
 *
 * Arquitectura de 3 zonas coordinadas con Tailwind CSS v4:
 *  1. Sidebar izquierdo colapsable: historial de reuniones, buscador rápido, subida drag & drop (<dialog>), muestra real, perfil y tema.
 *  2. Área central: cabecera con título editable inline, metadatos en Geist Mono, exportación Markdown (.md), chips de participantes con conteo real y pestañas Módulo 1 (Minuta + Tareas) / Módulo 2 (Buscador FTS5 de Decisiones).
 *  3. Panel derecho fijo: Visor Cronológico con filtro instantáneo y acciones en 1 clic (+ Tarea / + Decisión).
 */
$userName  = trim((string) ($currentUser['name'] ?? 'Admin'));
$userEmail = trim((string) ($currentUser['email'] ?? 'admin@transcripciones.local'));
$userAvatar = trim((string) ($currentUser['avatar_url'] ?? ''));
$parts     = preg_split('/\s+/u', $userName) ?: [];
$initials  = '';
foreach (array_slice($parts, 0, 2) as $p) {
    $initials .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
}
if ($initials === '') {
    $initials = 'AT';
}

$concepts = ! empty($semanticConcepts) && is_array($semanticConcepts)
    ? $semanticConcepts
    : ['whatsapp', 'plantilla', 'highlevel', 'pago', 'terminos', 'datos', 'limpieza', 'bloqueo', 'arquitectura'];
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrax · Minutas ejecutivas, tareas accionables y buscador semántico de decisiones técnicas en SQLite</title>
    <meta name="description" content="Convierte archivos .txt de reuniones en minutas editables, tareas con responsable y decisiones técnicas indexadas con SQLite FTS5.">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2310b981' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/tailwind.css') ?>">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('app_theme');
                var prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = saved ? saved : (prefersLight ? 'light' : 'dark');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="h-screen overflow-hidden bg-slate-50 text-slate-900 dark:bg-[#181818] dark:text-zinc-100 antialiased selection:bg-emerald-500/20 selection:text-emerald-400">

    <!-- Skip Link Accesible -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-emerald-600 focus:px-3 py-2 focus:text-sm focus:font-semibold focus:text-white">
        Saltar al contenido principal
    </a>

    <!-- Toast / Banner Flotante de Estado -->
    <div id="status-banner" role="status" aria-live="polite" hidden class="fixed bottom-4 right-4 z-50 max-w-sm rounded-xl border border-emerald-500/40 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-lg transition-opacity duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] dark:border-emerald-500/40 dark:bg-[#272727] dark:text-zinc-100"></div>

    <!-- Contenedor General del Workspace SaaS (3 Columnas Coordinadas) -->
    <div class="flex h-screen w-full overflow-hidden">

        <!-- ZONA 1: SIDEBAR IZQUIERDO COLAPSABLE -->
        <aside id="workspace-sidebar" aria-label="Historial de reuniones" class="fixed inset-y-0 left-0 z-30 flex w-80 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] lg:static lg:translate-x-0 dark:border-[#313131] dark:bg-[#1F1F1F]">
            <!-- Cabecera de Marca + Botón Colapsar -->
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3.5 dark:border-[#313131]">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="8" y1="13" x2="16" y2="13"/>
                            <line x1="8" y1="17" x2="13" y2="17"/>
                        </svg>
                    </span>
                    <div>
                        <span class="block text-sm font-bold tracking-tight text-slate-900 dark:text-white">
                            Unitrax
                        </span>
                        <span class="block font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                            Motor activo: <strong id="ai-engine-text" class="font-semibold text-emerald-600 dark:text-emerald-400"><?= esc($aiEngineName ?? 'heuristic local') ?></strong>
                        </span>
                    </div>
                </div>
                <button type="button" id="btn-sidebar-collapse" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-zinc-400 dark:hover:bg-[#272727] dark:hover:text-white" aria-label="Contraer u ocultar barra lateral" title="Contraer u ocultar barra lateral">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="18" height="18" x="3" y="3" rx="2"/>
                        <path d="M9 3v18"/>
                    </svg>
                </button>
            </div>

            <!-- Acciones de Ingesta (.txt) -->
            <div class="space-y-2 border-b border-slate-200 p-3 dark:border-[#313131]">
                <button type="button" id="btn-upload-txt" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-emerald-500">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <span>Subir archivo .txt</span>
                </button>
            </div>

            <!-- Filtro Rápido del Historial -->
            <div class="px-3 pt-3 pb-2">
                <div class="flex items-center justify-between pb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400">
                        Historial de reuniones
                    </span>
                    <span id="sidebar-meetings-count" class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-slate-700 dark:bg-[#272727] dark:text-zinc-300">
                        <?= count($meetings ?? []) ?>
                    </span>
                </div>
                <input type="search"
                       id="sidebar-meeting-filter"
                       placeholder="Filtrar reuniones..."
                       aria-label="Filtrar reuniones..."
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100 dark:placeholder:text-zinc-500">
            </div>

            <!-- Lista de Reuniones en Historial (#meetings-list) -->
            <div class="flex-1 overflow-y-auto px-3 py-1">
                <ul id="meetings-list" class="space-y-1.5" role="listbox" aria-label="Historial de reuniones"></ul>

                <!-- Selector oculto sincronizado por compatibilidad -->
                <select id="meeting-select" class="sr-only" aria-label="Selecciona una reunión procesada">
                    <?php if (! empty($meetings)): ?>
                        <?php foreach ($meetings as $m): ?>
                            <option value="<?= (int) $m['id'] ?>">
                                <?= esc($m['titulo'] ?? $m['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Pie de Usuario, Tema y Cerrar Sesión -->
            <div class="border-t border-slate-200 p-3 dark:border-[#313131]">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 flex-1 items-center gap-2.5" title="<?= esc($userName) ?> &lt;<?= esc($userEmail) ?>&gt;">
                        <?php if ($userAvatar !== ''): ?>
                            <img src="<?= esc($userAvatar) ?>" alt="<?= esc($userName) ?>" class="h-8 w-8 shrink-0 rounded-lg object-cover border border-slate-200 dark:border-[#313131]" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600/15 font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                <?= esc($initials) ?>
                            </span>
                        <?php endif; ?>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-slate-900 dark:text-white" title="<?= esc($userName) ?>">
                                <?= esc($userName) ?>
                            </p>
                            <p class="truncate font-mono text-[11px] text-slate-500 dark:text-zinc-400" title="<?= esc($userEmail) ?>">
                                <?= esc($userEmail) ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" id="btn-theme-toggle" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-slate-200 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200 dark:hover:bg-[#313131]" aria-label="Cambiar a tema claro" title="Cambiar a tema claro">
                            <svg class="hidden h-4 w-4 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="4"/>
                                <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                            </svg>
                            <svg class="block h-4 w-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </button>

                        <form action="<?= site_url('auth/logout') ?>" method="post" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" id="btn-logout" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 hover:bg-red-500/10 hover:text-red-600 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200 dark:hover:bg-red-500/20 dark:hover:text-red-400" aria-label="Cerrar sesión" title="Cerrar sesión">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/>
                                    <line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ZONA 2: ÁREA CENTRAL DE TRABAJO (Cabecera + Pestañas Módulo 1 y Módulo 2) -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

            <!-- Cabecera de Reunión Activa (#meeting-header) -->
            <header id="meeting-header" class="border-b border-slate-200 bg-white px-4 py-3 sm:px-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                        <!-- Botón Mostrar Sidebar (cuando está colapsado o en móvil) -->
                        <button type="button" id="btn-sidebar-expand" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 lg:hidden dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-label="Contraer u ocultar barra lateral" title="Contraer u ocultar barra lateral">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="4" y1="7" x2="20" y2="7"/>
                                <line x1="4" y1="12" x2="20" y2="12"/>
                                <line x1="4" y1="17" x2="20" y2="17"/>
                            </svg>
                        </button>

                        <!-- Título Editable Inline -->
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <h1 id="meeting-title-display" class="truncate text-lg font-bold text-slate-900 sm:text-xl dark:text-white" title="Editar título de la reunión">
                                —
                            </h1>
                            <input type="text"
                                   id="meeting-title-input"
                                   hidden
                                   aria-label="Editar título de la reunión"
                                   class="min-w-0 flex-1 rounded-lg border border-emerald-500 bg-slate-50 px-2.5 py-1 text-base font-bold text-slate-900 focus:outline-none dark:bg-[#272727] dark:text-white">
                            <button type="button" id="btn-edit-meeting-title" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:text-zinc-400 dark:hover:bg-[#272727] dark:hover:text-white" aria-label="Editar título de la reunión" title="Editar título de la reunión">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 20h9"/>
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Acciones Rápidas de Reunión (Exportar .md, Eliminar y Toggle Visor Móvil) -->
                    <div class="flex items-center gap-2">
                        <a id="btn-export-markdown"
                           href="<?= site_url('/') ?>"
                           download
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-800 transition-colors duration-300 hover:bg-slate-200 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100 dark:hover:bg-[#313131]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            <span>Exportar minuta (.md)</span>
                        </a>

                        <button type="button" id="btn-delete-meeting" class="inline-flex items-center gap-1.5 rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 py-1.5 text-xs font-semibold text-red-700 transition-colors duration-300 hover:bg-red-500/20 dark:text-red-300" title="Eliminar reunión">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                            <span class="hidden sm:inline">Eliminar reunión</span>
                        </button>

                        <button type="button" id="btn-toggle-viewer-mobile" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 lg:hidden dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <span>Transcripción cronológica</span>
                        </button>
                    </div>
                </div>

                <!-- Fila de Metadatos en Geist Mono -->
                <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-600 dark:text-zinc-400">
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium text-slate-500 dark:text-zinc-500">Inicio:</span>
                        <span id="meta-started-at" class="font-mono font-medium text-slate-800 dark:text-zinc-200">—</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium text-slate-500 dark:text-zinc-500">Duración:</span>
                        <span class="font-mono font-medium text-slate-800 dark:text-zinc-200">
                            <span id="meta-duration">0</span> min
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium text-slate-500 dark:text-zinc-500">Archivo:</span>
                        <span id="meta-source-file" class="font-mono font-medium text-slate-800 dark:text-zinc-200">—</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium text-slate-500 dark:text-zinc-500">Analizador:</span>
                        <span id="meta-ai-model" class="font-mono font-medium text-emerald-600 dark:text-emerald-400">—</span>
                    </div>
                    <div id="meta-url-wrap" hidden class="flex items-center gap-1">
                        <a id="meta-original-url" href="<?= site_url('/') ?>" target="_blank" rel="noopener noreferrer" class="font-medium text-emerald-600 underline hover:text-emerald-500 dark:text-emerald-400">
                            Abrir fuente original
                        </a>
                    </div>
                </div>

                <!-- Chips Interactivos de Participantes con Conteo Real -->
                <div class="mt-3 flex items-center gap-2 overflow-x-auto pb-1">
                    <span class="shrink-0 text-xs font-medium text-slate-500 dark:text-zinc-500">
                        Participantes:
                    </span>
                    <div id="speaker-chips" role="group" aria-label="Participantes" class="flex items-center gap-1.5"></div>
                </div>

                <!-- Conmutador de Pestañas Módulo 1 vs Módulo 2 -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-2.5 dark:border-[#313131]" role="tablist">
                    <div class="flex items-center gap-2">
                        <button type="button"
                                id="tab-btn-minutes"
                                role="tab"
                                aria-selected="true"
                                aria-controls="panel-module-minutes"
                                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition-colors duration-300 dark:bg-[#272727] dark:text-white">
                            <span>Minuta y tareas</span>
                            <span id="tab-count-tasks" class="rounded bg-white/20 px-1.5 py-0.5 font-mono text-[11px]">0</span>
                        </button>

                        <button type="button"
                                id="tab-btn-decisions"
                                role="tab"
                                aria-selected="false"
                                aria-controls="panel-module-decisions"
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors duration-300 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-[#272727] dark:hover:text-white">
                            <span>Decisiones técnicas</span>
                            <span id="tab-count-decisions" class="rounded bg-slate-200 px-1.5 py-0.5 font-mono text-[11px] text-slate-700 dark:bg-[#313131] dark:text-zinc-300">0</span>
                        </button>
                    </div>

                    <button type="button" id="btn-shortcut-ctrlk" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 font-mono text-xs text-slate-500 hover:border-emerald-500/50 hover:text-slate-800 dark:border-[#313131] dark:bg-[#181818] dark:text-zinc-400 dark:hover:text-white">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                        </svg>
                        <span>Ctrl + K</span>
                    </button>
                </div>
            </header>

            <!-- Cuerpo Central + Visor Derecho -->
            <div class="flex min-h-0 flex-1 overflow-hidden">

                <!-- COLUMNA CENTRAL: MÓDULO 1 Y MÓDULO 2 -->
                <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6">

                    <!-- MÓDULO 1: GESTOR DE MINUTAS Y EXTRACCIÓN AUTOMÁTICA DE TAREAS -->
                    <section id="panel-module-minutes" role="tabpanel" aria-labelledby="tab-btn-minutes" class="space-y-6">

                        <!-- Tarjeta de Minuta Ejecutiva Editable -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                    Minuta ejecutiva
                                </h2>
                                <button type="button" id="btn-edit-summary" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200 dark:hover:bg-[#313131]">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                    </svg>
                                    <span>Editar minuta</span>
                                </button>
                            </div>

                            <p id="executive-summary-text" class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-zinc-300">—</p>

                            <div id="summary-editor-wrap" hidden class="mt-3 space-y-3">
                                <textarea id="executive-summary-input"
                                          rows="4"
                                          placeholder="Escribe el resumen ejecutivo de los acuerdos y puntos tratados..."
                                          class="w-full rounded-xl border border-slate-300 bg-slate-50 p-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white"></textarea>
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" id="btn-cancel-summary" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300">
                                        Cancelar
                                    </button>
                                    <button type="button" id="btn-save-summary" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                                        Guardar cambios
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de Tareas y Compromisos -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                            <!-- Barra de Progreso Real (scaleX) -->
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                    Tareas y compromisos
                                </h2>
                                <span id="tasks-progress-text" class="font-mono text-xs font-medium text-slate-600 dark:text-zinc-400">
                                    0 / 0 completadas · 0%
                                </span>
                            </div>

                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-[#272727]">
                                <div id="tasks-progress-bar" class="h-full w-full origin-left scale-x-0 bg-emerald-500 transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"></div>
                            </div>

                            <!-- Formulario Compacto de Creación Rápida de Tarea -->
                            <form id="form-create-task" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-12">
                                <input type="text"
                                       id="new-task-summary"
                                       placeholder="Registrar nuevo compromiso o tarea de la reunión..."
                                       required
                                       class="sm:col-span-6 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white dark:placeholder:text-zinc-500">

                                <select id="new-task-assignee"
                                        aria-label="Responsable"
                                        class="app-select sm:col-span-3 rounded-xl border border-slate-300 bg-slate-50 px-2.5 py-2 text-xs font-medium text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white"></select>

                                <input type="text"
                                       id="new-task-timestamp"
                                       value="00:00"
                                       aria-label="Minuto"
                                       class="sm:col-span-1 rounded-xl border border-slate-300 bg-slate-50 px-2 py-2 text-center font-mono text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white">

                                <button type="submit" class="sm:col-span-2 inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition-colors duration-300 hover:bg-emerald-500">
                                    Agregar tarea
                                </button>
                            </form>

                            <!-- Filtros por Estado de Tarea -->
                            <div id="task-status-filters" role="group" aria-label="Tareas y compromisos" class="mt-4 flex flex-wrap items-center gap-1.5">
                                <button type="button" data-status-filter="all" aria-pressed="true" class="rounded-lg border border-slate-200 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white dark:border-[#313131] dark:bg-[#313131] dark:text-white">
                                    Todas <span data-count-status="all" class="ml-1 font-mono text-[11px]">0</span>
                                </button>
                                <button type="button" data-status-filter="pending" aria-pressed="false" class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300">
                                    Pendientes <span data-count-status="pending" class="ml-1 font-mono text-[11px]">0</span>
                                </button>
                                <button type="button" data-status-filter="in_progress" aria-pressed="false" class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300">
                                    En progreso <span data-count-status="in_progress" class="ml-1 font-mono text-[11px]">0</span>
                                </button>
                                <button type="button" data-status-filter="done" aria-pressed="false" class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300">
                                    Completadas <span data-count-status="done" class="ml-1 font-mono text-[11px]">0</span>
                                </button>
                            </div>

                            <!-- Lista Interactiva de Tareas (#tasks-list) -->
                            <ul id="tasks-list" aria-live="polite" class="mt-4 space-y-2.5"></ul>
                        </div>
                    </section>

                    <!-- MÓDULO 2: BUSCADOR SEMÁNTICO DE DECISIONES TÉCNICAS (SQLite FTS5) -->
                    <section id="panel-module-decisions" role="tabpanel" aria-labelledby="tab-btn-decisions" hidden class="space-y-6">

                        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                        Buscador semántico de decisiones técnicas
                                    </h2>
                                    <p class="mt-0.5 text-xs text-slate-600 dark:text-zinc-400">
                                        Consulta acuerdos de arquitectura, reglas de negocio y bloqueos con SQLite FTS5 y expansión semántica.
                                    </p>
                                </div>

                                <!-- Conmutador de Alcance: Esta reunión vs Todas las reuniones -->
                                <div id="decision-scope-toggle" class="inline-flex rounded-xl border border-slate-200 bg-slate-100 p-1 dark:border-[#313131] dark:bg-[#181818]" role="group">
                                    <button type="button" data-scope="current" aria-pressed="true" class="rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-900 dark:bg-[#272727] dark:text-white">
                                        Esta reunión
                                    </button>
                                    <button type="button" data-scope="all" aria-pressed="false" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-zinc-400">
                                        Todas las reuniones
                                    </button>
                                </div>
                            </div>

                            <!-- Input Buscador FTS5 con Debounce(250ms) + AbortController -->
                            <div class="relative mt-4">
                                <input type="search"
                                       id="decision-search-input"
                                       placeholder="Buscar por concepto técnico (whatsapp, plantilla, pago, datos, bloqueo)..."
                                       aria-label="Buscador semántico de decisiones técnicas"
                                       class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2.5 pl-10 pr-16 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white dark:placeholder:text-zinc-500">
                                <svg class="pointer-events-none absolute left-3.5 top-3 h-4 w-4 text-slate-400 dark:text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                                </svg>
                                <kbd class="pointer-events-none absolute right-3 top-2.5 rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px] text-slate-500 dark:border-[#313131] dark:bg-[#1F1F1F] dark:text-zinc-400">
                                    Ctrl + K
                                </kbd>
                            </div>

                            <!-- Chips de Conceptos Semánticos Frecuentes -->
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">
                                    Conceptos frecuentes:
                                </span>
                                <div id="semantic-concepts-chips" class="flex flex-wrap items-center gap-1.5">
                                    <?php foreach ($concepts as $concept): ?>
                                        <button type="button" data-semantic-chip="<?= esc($concept) ?>" class="rounded-lg border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700 transition-colors duration-300 hover:border-emerald-500/50 hover:text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300 dark:hover:text-emerald-400">
                                            <?= esc($concept) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Filtros por Categoría -->
                            <div id="decision-category-filters" role="group" aria-label="Categoría" class="mt-3 flex flex-wrap items-center gap-1.5">
                                <button type="button" data-cat-filter="" aria-pressed="true" class="rounded-lg border border-slate-200 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white dark:border-[#313131] dark:bg-[#313131] dark:text-white">
                                    Todas
                                </button>
                                <button type="button" data-cat-filter="decision" aria-pressed="false" class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300">
                                    Decisiones
                                </button>
                                <button type="button" data-cat-filter="blocker" aria-pressed="false" class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300">
                                    Bloqueos y limitantes
                                </button>
                                <button type="button" data-cat-filter="architecture" aria-pressed="false" class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300">
                                    Flujo y arquitectura
                                </button>
                            </div>

                            <!-- Cinta de Términos Expandidos por el Motor Semántico -->
                            <div id="expanded-terms-bar" hidden class="mt-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 font-mono text-xs text-emerald-700 dark:text-emerald-300"></div>

                            <!-- Bloque Desplegable <details> para Alta Manual de Decisión Técnica -->
                            <details id="details-new-decision" class="group mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#313131] dark:bg-[#181818]">
                                <summary class="flex cursor-pointer list-none items-center justify-between text-xs font-semibold text-slate-800 dark:text-zinc-200">
                                    <span>Registrar decisión manual</span>
                                    <span class="font-mono text-sm transition-transform duration-300 group-open:rotate-45">+</span>
                                </summary>

                                <form id="form-create-decision" class="mt-3 space-y-3">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                                        <div class="sm:col-span-6">
                                            <label for="new-decision-title" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                                Título del acuerdo o hallazgo
                                            </label>
                                            <input type="text"
                                                   id="new-decision-title"
                                                   required
                                                   placeholder="Ej. Ubicar aceptación de condiciones antes de la pasarela de pago"
                                                   class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white">
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="new-decision-category" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                                Categoría
                                            </label>
                                            <select id="new-decision-category" class="app-select mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white">
                                                <option value="decision">Decisión técnica</option>
                                                <option value="blocker">Bloqueante o limitación</option>
                                                <option value="architecture">Flujo o arquitectura</option>
                                            </select>
                                        </div>

                                        <div class="sm:col-span-3">
                                            <label for="new-decision-speaker" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                                Autor
                                            </label>
                                            <select id="new-decision-speaker" class="app-select mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white"></select>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="new-decision-rationale" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                            Justificación y contexto técnico
                                        </label>
                                        <textarea id="new-decision-rationale"
                                                  rows="2"
                                                  required
                                                  placeholder="Describe por qué se tomó la decisión o qué restricción técnica aplica..."
                                                  class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white"></textarea>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 items-end">
                                        <div class="sm:col-span-3">
                                            <label for="new-decision-timestamp" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                                Minuto
                                            </label>
                                            <input type="text"
                                                   id="new-decision-timestamp"
                                                   value="00:00"
                                                   class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 font-mono text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white">
                                        </div>

                                        <div class="sm:col-span-6">
                                            <label for="new-decision-tags" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                                                Etiquetas semánticas (opcional)
                                            </label>
                                            <input type="text"
                                                   id="new-decision-tags"
                                                   placeholder="pago, formulario, arquitectura"
                                                   class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white">
                                        </div>

                                        <div class="sm:col-span-3">
                                            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                                                Guardar decisión
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </details>

                            <!-- Lista de Resultados (#decisions-list) -->
                            <ul id="decisions-list" aria-live="polite" class="mt-4 space-y-3"></ul>
                        </div>
                    </section>
                </main>

                <!-- ZONA 3: PANEL DERECHO FIJO EN ESCRITORIO · VISOR CRONOLÓGICO DE TRANSCRIPCIÓN -->
                <aside id="transcript-viewer-panel" aria-labelledby="panel-viewer-title" class="fixed inset-y-0 right-0 z-30 hidden w-full flex-col border-l border-slate-200 bg-white sm:w-96 lg:static lg:flex lg:w-96 xl:w-[440px] dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <!-- Cabecera del Visor -->
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-[#313131]">
                        <div>
                            <h2 id="panel-viewer-title" class="text-sm font-bold text-slate-900 dark:text-white">
                                Transcripción cronológica
                            </h2>
                            <p class="font-mono text-xs text-slate-500 dark:text-zinc-400">
                                <span id="viewer-count-num">0</span> intervenciones
                            </p>
                        </div>
                        <button type="button" id="btn-close-viewer-mobile" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden dark:text-zinc-400 dark:hover:bg-[#272727]" aria-label="Cerrar ventana">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Buscador Instantáneo y Banner de Filtro de Hablante -->
                    <div class="space-y-2 border-b border-slate-200 p-3 dark:border-[#313131]">
                        <input type="search"
                               id="transcript-filter-input"
                               placeholder="Filtrar intervenciones del diálogo..."
                               aria-label="Filtrar intervenciones del diálogo..."
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100 dark:placeholder:text-zinc-500">

                        <div id="speaker-filter-banner" hidden class="flex items-center justify-between rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-700 dark:text-emerald-300">
                            <span class="truncate">
                                Filtrando intervenciones de: <strong id="speaker-filter-name"></strong>
                            </span>
                            <button type="button" id="btn-clear-speaker-filter" class="ml-2 shrink-0 font-semibold underline">
                                Mostrar todos
                            </button>
                        </div>
                    </div>

                    <!-- Contenedor Scrollable de Intervenciones (#transcript-list) -->
                    <div id="transcript-scroll-container" class="flex-1 overflow-y-auto p-3">
                        <ol id="transcript-list" class="space-y-2"></ol>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <!-- MODAL <dialog> DRAG AND DROP DE SUBIDA DE ARCHIVO .TXT -->
    <dialog id="upload-dialog" class="m-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-bold">Subir transcripción de reunión</h3>
                <p class="mt-1 text-xs text-slate-600 dark:text-zinc-400">
                    Selecciona o suelta un archivo .txt con marcas de tiempo para extraer su minuta, tareas y decisiones.
                </p>
            </div>
            <button type="button" data-close-dialog="upload-dialog" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-[#313131]" aria-label="Cerrar ventana">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <form id="form-upload-modal" class="mt-4 space-y-4">
            <input type="file" id="file-upload-input" accept=".txt,text/plain" hidden>

            <div id="upload-dropzone" tabindex="0" role="button" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition-colors duration-300 hover:border-emerald-500 dark:border-[#313131] dark:bg-[#1F1F1F] dark:hover:border-emerald-500">
                <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <p id="upload-selected-filename" class="mt-2 text-xs font-semibold text-slate-800 dark:text-zinc-200">
                    Arrastra aquí tu archivo .txt o haz clic para seleccionarlo
                </p>
                <p class="mt-1 font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                    Texto plano UTF8 hasta 5 MB
                </p>
            </div>

            <div>
                <label for="upload-custom-title" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                    Título personalizado (opcional)
                </label>
                <input type="text"
                       id="upload-custom-title"
                       placeholder="Ej. Revisión de integración de pagos y mensajería"
                       class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#1F1F1F] dark:text-white">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" data-close-dialog="upload-dialog" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#1F1F1F] dark:text-zinc-300">
                    Cancelar
                </button>
                <button type="submit" id="btn-upload-submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                    Procesar transcripción
                </button>
            </div>
        </form>
    </dialog>

    <!-- MODAL <dialog> DE CONFIRMACIÓN DE ELIMINACIÓN DE REUNIÓN -->
    <dialog id="confirm-delete-dialog" class="m-auto w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">
            Eliminar reunión del repositorio
        </h3>
        <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-zinc-300">
            Se borrarán la transcripción, las tareas y las decisiones indexadas de esta sesión en SQLite.
        </p>
        <div class="mt-5 flex items-center justify-end gap-2">
            <button type="button" data-close-dialog="confirm-delete-dialog" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#1F1F1F] dark:text-zinc-300">
                Cancelar
            </button>
            <button type="button" id="btn-confirm-delete-meeting" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500">
                Eliminar definitivamente
            </button>
        </div>
    </dialog>

    <!-- Estado Inicial Hidratado (§ 5.3) -->
    <script id="initial-app-state" type="application/json">
        <?= json_encode([
            'baseUrl'           => rtrim(site_url(), '/'),
            'csrfTokenName'     => csrf_token(),
            'csrfHash'          => csrf_hash(),
            'meetings'          => $meetings ?? [],
            'activeMeetingData' => $activeMeetingData ?? null,
            'semanticConcepts'  => $concepts,
            'i18n'              => [
                'metaAllParticipants'      => "Todos",
                'statusPending'            => "Pendiente",
                'statusInProgress'         => "En progreso",
                'statusDone'               => "Completada",
                'tasksCompletedOf'         => "completadas",
                'taskDefaultAssignee'      => "Equipo",
                'taskJumpToMinute'         => "Ir al minuto",
                'taskEditBtn'              => "Editar texto de la tarea",
                'taskToggleDoneLabel'      => "Alternar tarea completada",
                'taskDeleteBtn'            => "Eliminar tarea",
                'taskCreatedMsg'           => "Tarea agregada a la minuta.",
                'taskUpdatedMsg'           => "Tarea actualizada.",
                'taskDeletedMsg'           => "Tarea eliminada.",
                'emptyTasks'               => "No hay tareas registradas para el filtro actual. Agrega una arriba o créala con un clic desde cualquier línea de la transcripción.",
                'catDecision'              => "Decisión técnica",
                'catBlocker'               => "Bloqueante o limitación",
                'catArchitecture'          => "Flujo o arquitectura",
                'expandedTermsPrefix'      => "Búsqueda expandida con",
                'decisionViewInContext'    => "Ver en transcripción",
                'decisionSwitchAndJump'    => "Abrir reunión e ir al minuto",
                'decisionSpeakerLabel'     => "Autor",
                'decisionTagsLabel'        => "Conceptos",
                'decisionMeetingOriginLabel'=> "Sesión",
                'decisionDeleteBtn'        => "Eliminar decisión",
                'decisionCreatedMsg'       => "Decisión técnica registrada e indexada en FTS5.",
                'decisionDeletedMsg'       => "Decisión técnica eliminada del índice.",
                'emptyDecisions'           => "No se encontraron decisiones técnicas con estos criterios. Prueba otro concepto semántico o cambia el alcance a todas las reuniones.",
                'viewerQuickTaskBtn'       => "+ Tarea",
                'viewerQuickTaskTitle'     => "Convertir esta intervención en tarea de la minuta",
                'viewerQuickDecisionBtn'   => "+ Decisión",
                'viewerQuickDecisionTitle' => "Convertir esta intervención en decisión técnica indexada",
                'viewerEmptyFilter'        => "Ninguna línea del diálogo coincide con el filtro aplicado.",
                'emptyMeetingState'        => "Aún no tienes reuniones en tu espacio de trabajo. Sube tu primer archivo .txt para comenzar.",
                'sidebarEmptySearch'       => "Ninguna reunión coincide con tu búsqueda.",
                'meetingUpdatedMsg'        => "Cambios guardados en la reunión.",
                'uploadSuccessMsg'         => "Transcripción procesada e indexada correctamente.",
                'uploadErrorInvalidFile'   => "Archivo no válido. Selecciona un archivo de texto plano .txt menor a 5 MB.",
                'uploadDropzonePrompt'     => "Arrastra aquí tu archivo .txt o haz clic para seleccionarlo",
                'navDeleteMeeting'         => "Eliminar reunión",
            ],
        ], JSON_UNESCAPED_UNICODE) ?>
    </script>
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</body>
</html>
