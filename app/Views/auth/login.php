<?php
/**
 * Vista de Landing + Acceso Integrado (`GET /` sin sesión y `GET /login`).
 *
 * Implementa el sistema visual Tailwind CSS v4 (Manrope + Geist Mono),
 * superficies oscuras planas (#181818, #1F1F1F, #272727, #313131),
 * degradado exclusivo en el titular del Hero (B5),
 * Tagline Reveal palabra por palabra con IntersectionObserver (B11),
 * métricas reales de SQLite ($portalStats) y cero enlaces muertos.
 */
$stats = $portalStats ?? [
    'meetings_count'  => 0,
    'tasks_count'     => 0,
    'decisions_count' => 0,
    'total_minutes'   => 0,
];

$taglineLines = [
    "Ningún acuerdo técnico vuelve a perderse en un chat.",
    "Cada tarea conserva su autor, su contexto real",
    "y el minuto exacto donde el equipo tomó la decisión.",
];
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Transcripciones · Minutas ejecutivas, tareas accionables y buscador semántico de decisiones técnicas en SQLite</title>
    <meta name="description" content="Convierte archivos .txt de reuniones en minutas editables, tareas con responsable y decisiones técnicas indexadas con SQLite FTS5.">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="App Transcripciones · Minutas ejecutivas, tareas accionables y buscador semántico de decisiones técnicas en SQLite">
    <meta property="og:description" content="Convierte archivos .txt de reuniones en minutas editables, tareas con responsable y decisiones técnicas indexadas con SQLite FTS5.">
    <meta property="og:type" content="website">
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
    <?php if (! empty($googleClientId)): ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-[#181818] dark:text-zinc-100 antialiased selection:bg-emerald-500/20 selection:text-emerald-400">

    <!-- Skip Link Accesible -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-emerald-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white focus:outline-none">
        Saltar al contenido principal
    </a>

    <!-- Barra de Navegación Flotante Superior -->
    <header class="sticky top-4 z-40 mx-auto max-w-6xl px-4 sm:px-6">
        <nav aria-label="App Transcripciones" class="flex items-center justify-between rounded-2xl border border-slate-200/90 bg-white/85 px-4 py-3 backdrop-blur-md transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] dark:border-[#313131] dark:bg-[#1F1F1F]/85">
            <!-- Marca -->
            <a href="#main-content" class="flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="8" y1="13" x2="16" y2="13"/>
                        <line x1="8" y1="17" x2="13" y2="17"/>
                    </svg>
                </span>
                <span class="text-base font-bold tracking-tight text-slate-900 dark:text-white">
                    App Transcripciones
                </span>
            </a>

            <!-- Enlaces Escritorio -->
            <div class="hidden md:flex md:items-center md:gap-6">
                <a href="#funcionalidades" class="text-sm font-medium text-slate-600 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white">
                    Beneficios
                </a>
                <a href="#flujo" class="text-sm font-medium text-slate-600 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white">
                    Cómo funciona
                </a>
                <a href="#arquitectura" class="text-sm font-medium text-slate-600 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white">
                    Ejecución local verificable
                </a>
                <a href="#preguntas" class="text-sm font-medium text-slate-600 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white">
                    Preguntas frecuentes
                </a>
            </div>

            <!-- Acciones Derecha -->
            <div class="flex items-center gap-2">
                <button type="button" id="btn-theme-toggle" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200 dark:hover:bg-[#313131]" aria-label="Cambiar a tema claro" title="Cambiar a tema claro">
                    <!-- Icono Sol (visible en oscuro) -->
                    <svg class="hidden h-4 w-4 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                    <!-- Icono Luna (visible en claro) -->
                    <svg class="block h-4 w-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>

                <a href="#auth-card" class="hidden sm:inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-slate-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200">
                    Entrar al espacio
                </a>

                <!-- Botón Menú Móvil -->
                <button type="button" id="btn-mobile-menu" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 md:hidden dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-expanded="false" aria-controls="mobile-nav-panel" aria-label="Abrir menú de navegación">
                    <svg id="icon-menu-open" class="h-5 w-5 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="4" y1="7" x2="20" y2="7"/>
                        <line x1="4" y1="12" x2="20" y2="12"/>
                        <line x1="4" y1="17" x2="20" y2="17"/>
                    </svg>
                    <svg id="icon-menu-close" class="hidden h-5 w-5 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </nav>

        <!-- Panel de Navegación Móvil Escalonado -->
        <div id="mobile-nav-panel" hidden class="mt-2 rounded-2xl border border-slate-200 bg-white p-6 md:hidden dark:border-[#313131] dark:bg-[#1F1F1F]">
            <div class="flex flex-col gap-4">
                <a href="#funcionalidades" class="mobile-nav-item text-base font-semibold text-slate-800 dark:text-zinc-100" style="transition-delay: 60ms;">
                    Beneficios
                </a>
                <a href="#flujo" class="mobile-nav-item text-base font-semibold text-slate-800 dark:text-zinc-100" style="transition-delay: 120ms;">
                    Cómo funciona
                </a>
                <a href="#arquitectura" class="mobile-nav-item text-base font-semibold text-slate-800 dark:text-zinc-100" style="transition-delay: 180ms;">
                    Ejecución local verificable
                </a>
                <a href="#preguntas" class="mobile-nav-item text-base font-semibold text-slate-800 dark:text-zinc-100" style="transition-delay: 240ms;">
                    Preguntas frecuentes
                </a>
                <a href="#auth-card" class="mobile-nav-item mt-2 inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-base font-semibold text-white hover:bg-emerald-500" style="transition-delay: 300ms;">
                    Entrar al espacio de trabajo
                </a>
            </div>
        </div>
    </header>

    <main id="main-content" class="mx-auto max-w-6xl px-4 pt-10 pb-20 sm:px-6 lg:pt-16">

        <!-- SECCIÓN 1: HERO & AUTH GATEWAY (Asimétrico 7 / 5 en lg:grid-cols-12) -->
        <section id="acceso" aria-labelledby="hero-heading" class="grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-8">
            <!-- Columna Izquierda (7 columnas): Argumento Principal + Métricas Reales SQLite -->
            <div class="flex flex-col gap-6 lg:col-span-7 lg:pt-2">
                <div class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#1F1F1F] dark:text-zinc-300">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    <span>Minutas ejecutivas y memoria técnica sobre SQLite FTS5</span>
                </div>

                <h1 id="hero-heading" class="hero-title-gradient max-w-[680px] text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl leading-[1.12]">
                    <span class="block">Convierte tus transcripciones .txt</span>
                    <span class="block">en tareas claras y decisiones técnicas</span>
                    <span class="block">listas para consultar en segundos</span>
                </h1>

                <p class="max-w-[680px] text-base font-normal leading-relaxed text-slate-600 sm:text-lg dark:text-zinc-400">
                    Sube el archivo de tu reunión y obtén en una sola lectura la minuta editable, los responsables asignados y un buscador semántico conectado al minuto exacto de cada acuerdo.
                </p>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <a href="#auth-card" id="btn-hero-focus-login" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-base font-semibold text-white transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                        <span>Entrar al espacio de trabajo</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14m-6-6 6 6-6 6"/>
                        </svg>
                    </a>
                    <button type="button" id="btn-hero-demo" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-base font-semibold text-slate-800 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-slate-100 dark:border-[#313131] dark:bg-[#1F1F1F] dark:text-zinc-200 dark:hover:bg-[#272727]">
                        <span>Probar con cuenta de demostración</span>
                    </button>
                </div>

                <!-- Tira de Métricas Reales en Vivo desde SQLite ($portalStats) -->
                <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#313131] dark:bg-[#1F1F1F]">
                        <div class="font-mono text-2xl font-bold text-slate-900 dark:text-white" data-counter-target="<?= (int) $stats['meetings_count'] ?>">
                            <?= (int) $stats['meetings_count'] ?>
                        </div>
                        <div class="mt-1 text-xs font-medium text-slate-600 dark:text-zinc-400">
                            Reuniones procesadas
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#313131] dark:bg-[#1F1F1F]">
                        <div class="font-mono text-2xl font-bold text-slate-900 dark:text-white" data-counter-target="<?= (int) $stats['tasks_count'] ?>">
                            <?= (int) $stats['tasks_count'] ?>
                        </div>
                        <div class="mt-1 text-xs font-medium text-slate-600 dark:text-zinc-400">
                            Tareas extraídas
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#313131] dark:bg-[#1F1F1F]">
                        <div class="font-mono text-2xl font-bold text-slate-900 dark:text-white" data-counter-target="<?= (int) $stats['decisions_count'] ?>">
                            <?= (int) $stats['decisions_count'] ?>
                        </div>
                        <div class="mt-1 text-xs font-medium text-slate-600 dark:text-zinc-400">
                            Decisiones en FTS5
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#313131] dark:bg-[#1F1F1F]">
                        <div class="font-mono text-2xl font-bold text-slate-900 dark:text-white" data-counter-target="<?= (int) $stats['total_minutes'] ?>">
                            <?= (int) $stats['total_minutes'] ?>
                        </div>
                        <div class="mt-1 text-xs font-medium text-slate-600 dark:text-zinc-400">
                            Minutos analizados
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha (5 columnas): Tarjeta de Autenticación Real (#auth-card) -->
            <div id="auth-card" class="lg:col-span-5 rounded-3xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                <div class="mb-4">
                    <h2 id="portal-card-title" class="text-xl font-bold text-slate-900 dark:text-white">
                        Accede a tu espacio de trabajo
                    </h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-400">
                        Ingresa con tus credenciales locales, continúa con Google o usa la cuenta semilla para recorrer las reuniones procesadas.
                    </p>
                </div>

                <?php if (session()->getFlashdata('auth_error')): ?>
                    <div class="mb-4 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-700 dark:text-red-300" role="alert">
                        <?= esc((string) session()->getFlashdata('auth_error')) ?>
                    </div>
                <?php endif; ?>

                <div id="auth-alert" class="mb-4 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-700 dark:text-red-300" role="alert" hidden></div>

                <!-- Pestañas Accesibles Iniciar sesión / Crear cuenta -->
                <div class="mb-5 grid grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1 dark:border-[#313131] dark:bg-[#181818]" role="tablist">
                    <button type="button" id="tab-btn-login" role="tab" aria-selected="true" aria-controls="form-login" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] dark:bg-[#272727] dark:text-white">
                        Iniciar sesión
                    </button>
                    <button type="button" id="tab-btn-register" role="tab" aria-selected="false" aria-controls="form-register" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white">
                        Crear cuenta
                    </button>
                </div>

                <!-- Formulario de Inicio de Sesión -->
                <form id="form-login" action="<?= site_url('auth/login') ?>" method="post" class="space-y-4">
                    <?= csrf_field() ?>

                    <div>
                        <label for="login-email" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                            Correo electrónico
                        </label>
                        <input type="text"
                               id="login-email"
                               name="email"
                               value="admin@transcripciones.local"
                               placeholder="nombre@equipo.local"
                               required
                               autocomplete="username"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white dark:placeholder:text-zinc-500">
                    </div>

                    <div>
                        <label for="login-password" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                            Contraseña
                        </label>
                        <div class="relative mt-1.5">
                            <input type="password"
                                   id="login-password"
                                   name="password"
                                   value="Admin123*"
                                   placeholder="Mínimo 6 caracteres"
                                   required
                                   autocomplete="current-password"
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2.5 pl-3.5 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white dark:placeholder:text-zinc-500">
                            <button type="button"
                                    id="btn-toggle-pwd"
                                    aria-label="Mostrar contraseña"
                                    title="Mostrar contraseña"
                                    class="absolute inset-y-0 right-2 inline-flex items-center justify-center rounded-lg px-2 text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white">
                                <svg id="icon-eye-open" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg id="icon-eye-closed" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                    <line x1="2" y1="2" x2="22" y2="22"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="btn-submit-login" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-base font-semibold text-white transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                        <span>Entrar al espacio de trabajo</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14m-6-6 6 6-6 6"/>
                        </svg>
                    </button>
                </form>

                <!-- Formulario de Registro -->
                <form id="form-register" action="<?= site_url('auth/register') ?>" method="post" hidden class="space-y-4">
                    <?= csrf_field() ?>

                    <div>
                        <label for="reg-name" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                            Nombre completo
                        </label>
                        <input type="text"
                               id="reg-name"
                               name="name"
                               placeholder="Ej. Vanesa Machado"
                               required
                               autocomplete="name"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white dark:placeholder:text-zinc-500">
                    </div>

                    <div>
                        <label for="reg-email" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                            Correo electrónico
                        </label>
                        <input type="email"
                               id="reg-email"
                               name="email"
                               placeholder="nombre@equipo.local"
                               required
                               autocomplete="email"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white dark:placeholder:text-zinc-500">
                    </div>

                    <div>
                        <label for="reg-password" class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">
                            Contraseña
                        </label>
                        <input type="password"
                               id="reg-password"
                               name="password"
                               placeholder="Mínimo 6 caracteres"
                               minlength="6"
                               required
                               autocomplete="new-password"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-[#313131] dark:bg-[#272727] dark:text-white dark:placeholder:text-zinc-500">
                    </div>

                    <button type="submit" id="btn-submit-register" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-base font-semibold text-white transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-emerald-500">
                        <span>Crear cuenta y entrar</span>
                    </button>
                </form>

                <!-- Divisor OAuth -->
                <div class="my-5 flex items-center gap-3">
                    <div class="h-px flex-1 bg-slate-200 dark:bg-[#313131]"></div>
                    <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">o continúa con</span>
                    <div class="h-px flex-1 bg-slate-200 dark:bg-[#313131]"></div>
                </div>

                <!-- Botón Continuar con Google -->
                <?php if (! empty($googleClientId)): ?>
                    <div id="g_id_onload"
                         data-client_id="<?= esc($googleClientId) ?>"
                         data-callback="handleGoogleCredentialResponse"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="continue_with" data-shape="rectangular" data-width="100%"></div>
                <?php else: ?>
                    <button type="button" id="btn-google-oauth" class="w-full inline-flex items-center justify-center gap-2.5 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-800 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-slate-100 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100 dark:hover:bg-[#313131]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.13-1.57.38-2.29V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.98-3.09z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/>
                        </svg>
                        <span>Continuar con Google</span>
                    </button>
                    <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">
                        Modo local activo: el inicio con Google funciona en modo de prueba local mientras defines tu variable GOOGLE_CLIENT_ID en .env.
                    </p>
                <?php endif; ?>

                <!-- Bloque de Autocompletar Cuenta Semilla en 1 Clic -->
                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-3.5 dark:border-[#313131] dark:bg-[#181818]">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-slate-700 dark:text-zinc-300">
                            Acceso rápido de evaluación
                        </span>
                        <button type="button" id="btn-fill-demo" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-emerald-500/20 dark:text-emerald-300">
                            Usar cuenta semilla
                        </button>
                    </div>
                    <p class="mt-1.5 font-mono text-xs text-slate-600 dark:text-zinc-400">
                        Cuenta disponible: admin@transcripciones.local · Clave: Admin123*
                    </p>
                </div>
            </div>
        </section>

        <!-- SECCIÓN 2: CONTRASTE PROBLEMA VS SOLUCIÓN (#funcionalidades) -->
        <section id="funcionalidades" aria-labelledby="problem-heading" class="mt-24">
            <div class="max-w-3xl">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                    El problema habitual
                </span>
                <h2 id="problem-heading" class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">
                    Del archivo .txt olvidado a una memoria técnica consultable
                </h2>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Antes -->
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Acuerdos dispersos en archivos sueltos
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Las transcripciones quedan guardadas en carpetas sin índice. Nadie recuerda en qué minuto se aprobó una condición de pago, quién asumió cada entrega ni qué bloqueo técnico detuvo el avance.
                    </p>
                </article>

                <!-- Después -->
                <article class="rounded-2xl border border-emerald-500/40 bg-white p-6 dark:border-emerald-500/40 dark:bg-[#1F1F1F]">
                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Minutas vivas e indexadas con App Transcripciones
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Una sola pasada lineal procesa la sesión completa, vincula cada tarea con su autor real e indexa las decisiones de arquitectura en SQLite FTS5 conectadas a su marca MM:SS.
                    </p>
                </article>
            </div>
        </section>

        <!-- SECCIÓN 3: 4 BENEFICIOS MEDIBLES (grid-cols-1 md:grid-cols-2) -->
        <section id="beneficios" aria-labelledby="benefits-heading" class="mt-24">
            <div class="max-w-3xl">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                    Resultados medibles
                </span>
                <h2 id="benefits-heading" class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">
                    Todo lo que tu equipo necesita después de cada reunión técnica
                </h2>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Beneficio 1 -->
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 11l3 3L22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Acuerdos accionables desde la primera lectura
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Extrae compromisos, responsables y marcas de tiempo MM:SS en un único recorrido lineal del archivo .txt sin releer ni duplicar sesiones gracias a la verificación SHA256.
                    </p>
                </article>

                <!-- Beneficio 2 -->
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.3-4.3"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Decisiones de arquitectura localizables en milisegundos
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Encuentra por qué se eligió un flujo de pago, una plantilla de mensajería o una regla de datos combinando el índice nativo SQLite FTS5 y el ranking bm25() con expansión semántica en español.
                    </p>
                </article>

                <!-- Beneficio 3 -->
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Trazabilidad directa al diálogo original
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Salta desde cualquier tarea o bloqueo técnico hasta la línea exacta donde se dijo en la llamada, o crea acuerdos nuevos desde cualquier intervención con un clic.
                    </p>
                </article>

                <!-- Beneficio 4 -->
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Privacidad total en tu propio servidor
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Tus transcripciones, sesiones y minutas viven en una base SQLite local bajo tu control, funcionando al instante con heurísticas deterministas sin costo obligatorio o con modelos gratuitos opcionales.
                    </p>
                </article>
            </div>
        </section>

        <!-- SECCIÓN 4: B11 TAGLINE REVEAL PALABRA POR PALABRA -->
        <section id="manifiesto" aria-label="App Transcripciones" class="my-28 py-12">
            <div id="tagline-reveal-container" class="mx-auto max-w-4xl text-center">
                <p class="max-w-[680px] mx-auto text-3xl font-semibold leading-tight tracking-tight sm:text-5xl">
                    <?php foreach ($taglineLines as $lineIdx => $lineText): ?>
                        <span class="block <?= $lineIdx > 0 ? 'mt-2' : '' ?>">
                            <?php
                            $words = preg_split('/\s+/u', trim($lineText)) ?: [];
                            foreach ($words as $word):
                            ?>
                                <span class="tagline-word mr-2"><?= esc($word) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endforeach; ?>
                </p>
            </div>
        </section>

        <!-- SECCIÓN 5: CÓMO FUNCIONA EN 3 PASOS (#flujo) -->
        <section id="flujo" aria-labelledby="how-heading" class="mt-24">
            <div class="max-w-3xl">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                    Flujo de trabajo
                </span>
                <h2 id="how-heading" class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">
                    De archivo plano a base de conocimiento en tres pasos
                </h2>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <span class="inline-flex rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 font-mono text-xs font-semibold text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        01
                    </span>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Sube tu archivo .txt o carga la muestra real
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Arrastra una transcripción exportada con marcas MM:SS Autor: Texto; el lector valida el archivo en una sola pasada, filtra avisos automáticos y extrae fecha, duración y participantes.
                    </p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <span class="inline-flex rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 font-mono text-xs font-semibold text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        02
                    </span>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Revisa la minuta y gestiona las tareas del equipo
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Edita el resumen ejecutivo en línea, marca avances con un clic, reasigna responsables y descarga el acta completa en formato Markdown .md lista para compartir.
                    </p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <span class="inline-flex rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 font-mono text-xs font-semibold text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
                        03
                    </span>
                    <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                        Consulta decisiones técnicas en todas tus sesiones
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Abre el buscador con Ctrl + K, filtra por categoría o participante y localiza al instante acuerdos de flujo, arquitectura o bloqueos operativos.
                    </p>
                </article>
            </div>
        </section>

        <!-- SECCIÓN 6: PRUEBA TÉCNICA Y GARANTÍA LOCAL (#arquitectura) -->
        <section id="arquitectura" aria-labelledby="proof-heading" class="mt-24">
            <div class="rounded-3xl border border-slate-200 bg-white p-8 dark:border-[#313131] dark:bg-[#1F1F1F]">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                    Ejecución local verificable
                </span>
                <h2 id="proof-heading" class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">
                    Control completo sobre tus actas y datos técnicos sin costos ocultos
                </h2>
                <p class="mt-3 max-w-3xl text-base leading-relaxed text-slate-600 dark:text-zinc-400">
                    Sin tarjeta de crédito ni suscripciones obligatorias. Tus sesiones permanecen en SQLite local dentro de tu infraestructura, con cuenta semilla lista al instante y exportación libre en Markdown .md.
                </p>

                <div class="mt-6 flex flex-wrap gap-2">
                    <span class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 font-mono text-xs font-medium text-slate-800 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200">
                        SQLite FTS5 bm25()
                    </span>
                    <span class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 font-mono text-xs font-medium text-slate-800 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200">
                        SHA256
                    </span>
                    <span class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 font-mono text-xs font-medium text-slate-800 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200">
                        O(n) MAX_LINES 50000
                    </span>
                    <span class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 font-mono text-xs font-medium text-slate-800 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200">
                        JWT HMAC SHA256
                    </span>
                    <span class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 font-mono text-xs font-medium text-slate-800 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200">
                        Markdown .md
                    </span>
                </div>
            </div>
        </section>

        <!-- SECCIÓN 7: 6 PREGUNTAS FRECUENTES TÉCNICAS (#preguntas) -->
        <section id="preguntas" aria-labelledby="faq-heading" class="mt-24">
            <div class="max-w-3xl">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                    Preguntas técnicas frecuentes
                </span>
                <h2 id="faq-heading" class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">
                    Todo sobre el motor local, la privacidad y los formatos soportados
                </h2>
            </div>

            <div class="mt-8 space-y-3">
                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-slate-900 focus:outline-none dark:text-white">
                        <span>¿Qué formato deben tener los archivos .txt que subo a la plataforma?</span>
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-open:rotate-45 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Acepta archivos de texto plano .txt de hasta 5 MB codificados en UTF8 o convertidos automáticamente al ingresar. Reconoce cabeceras como Meeting started, Duration, Participants, enlaces de transcripción original y líneas cronológicas con formato MM:SS Autor: Intervención, filtrando avisos automáticos de extensiones de grabación.
                    </p>
                </details>

                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-slate-900 focus:outline-none dark:text-white">
                        <span>¿Dónde se almacenan las transcripciones y qué privacidad ofrece SQLite?</span>
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-open:rotate-45 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Toda la información se guarda localmente en el archivo writable/database/transcripciones.db dentro de tu propio entorno CodeIgniter 4. Ninguna transcripción sale hacia terceros a menos que actives voluntariamente una clave de API para un proveedor externo en tu configuración .env.
                    </p>
                </details>

                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-slate-900 focus:outline-none dark:text-white">
                        <span>¿Cómo funciona el buscador semántico con SQLite FTS5?</span>
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-open:rotate-45 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Cada decisión técnica, acuerdo de arquitectura o bloqueo se indexa mediante triggers automáticos en una tabla virtual FTS5 que ordena resultados por relevancia con el algoritmo bm25(). Además, el motor expande tu consulta con sinónimos técnicos del dominio en español para hallar conceptos relacionados aunque no recuerdes la palabra literal.
                    </p>
                </details>

                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-slate-900 focus:outline-none dark:text-white">
                        <span>¿Cómo protege mis sesiones la autenticación híbrida con JWT y Google?</span>
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-open:rotate-45 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Al iniciar sesión con tus credenciales locales o mediante Google OAuth, el servidor sincroniza una sesión nativa de CodeIgniter 4 y emite en paralelo un token firmado con HMAC SHA256 en cookie HttpOnly y encabezado Bearer, protegiendo tanto las vistas web como cada endpoint JSON de la API.
                    </p>
                </details>

                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-slate-900 focus:outline-none dark:text-white">
                        <span>¿Necesito pagar una suscripción de inteligencia artificial para extraer minutas y tareas?</span>
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-open:rotate-45 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        No. La aplicación incluye por defecto un analizador heurístico determinista en español que funciona sin conexión y sin costo en milisegundos. Si deseas síntesis generativa adicional, puedes conectar modelos gratuitos compatibles en app/Config/AI.php conservando siempre el respaldo local automático si no hay red.
                    </p>
                </details>

                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#313131] dark:bg-[#1F1F1F]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-slate-900 focus:outline-none dark:text-white">
                        <span>¿Qué ocurre si subo dos veces el mismo archivo .txt o quiero llevarme la minuta a otra herramienta?</span>
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-700 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-open:rotate-45 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
                        Antes de procesar cualquier subida se calcula la huella criptográfica SHA256 del archivo para evitar duplicados y abrir de inmediato la reunión existente. En cualquier momento puedes exportar la minuta completa, las tareas con su estado y las decisiones técnicas en un archivo .md estándar.
                    </p>
                </details>
            </div>
        </section>

        <!-- SECCIÓN 8: CTA FINAL -->
        <section aria-labelledby="final-cta-heading" class="mt-24 rounded-3xl border border-slate-200 bg-white p-8 text-center sm:p-12 dark:border-[#313131] dark:bg-[#1F1F1F]">
            <h2 id="final-cta-heading" class="mx-auto max-w-2xl text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">
                Empieza a indexar las decisiones técnicas de tu equipo hoy
            </h2>
            <p class="mx-auto mt-3 max-w-xl text-base text-slate-600 dark:text-zinc-400">
                Entra con la cuenta semilla o sube tu primer archivo .txt para obtener su minuta editable, tareas con responsable y búsqueda FTS5 en segundos.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="#auth-card" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-base font-semibold text-white transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-emerald-500">
                    <span>Entrar al espacio de trabajo</span>
                </a>
            </div>
        </section>
    </main>

    <!-- PIE DE PÁGINA SEMÁNTICO CON MODALES <dialog> REALES -->
    <footer class="border-t border-slate-200 bg-white py-8 dark:border-[#313131] dark:bg-[#1F1F1F]">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6">
            <div class="text-sm text-slate-600 dark:text-zinc-400">
                <span class="font-semibold text-slate-900 dark:text-white">App Transcripciones</span>
                · <span class="font-mono"><?= date('Y') ?></span>
            </div>
            <div class="flex flex-wrap items-center gap-6">
                <button type="button" id="btn-open-privacy" class="text-sm font-medium text-slate-600 transition-colors duration-300 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white">
                    Privacidad y datos locales
                </button>
                <button type="button" id="btn-open-terms" class="text-sm font-medium text-slate-600 transition-colors duration-300 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white">
                    Condiciones de uso
                </button>
            </div>
        </div>
    </footer>

    <!-- Modal <dialog> de Privacidad SQLite Local -->
    <dialog id="privacy-dialog" class="m-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100">
        <div class="flex items-start justify-between gap-4">
            <h3 class="text-lg font-bold">Privacidad y almacenamiento SQLite local</h3>
            <button type="button" data-close-dialog="privacy-dialog" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-[#313131]" aria-label="Cerrar ventana">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-300">
            App Transcripciones guarda todas las reuniones, intervenciones cronológicas, tareas y decisiones técnicas en el archivo local writable/database/transcripciones.db de tu servidor. Las credenciales se almacenan con hash seguro y las sesiones operan mediante cookies HttpOnly y tokens firmados con HMAC SHA256. Ningún dato se transmite a servicios externos salvo que configures de forma expresa una clave opcional en tu archivo .env.
        </p>
        <div class="mt-6 flex justify-end">
            <button type="button" data-close-dialog="privacy-dialog" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">
                Cerrar ventana
            </button>
        </div>
    </dialog>

    <!-- Modal <dialog> de Condiciones de Uso -->
    <dialog id="terms-dialog" class="m-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-100">
        <div class="flex items-start justify-between gap-4">
            <h3 class="text-lg font-bold">Condiciones de uso del repositorio</h3>
            <button type="button" data-close-dialog="terms-dialog" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-[#313131]" aria-label="Cerrar ventana">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-300">
            Este sistema está destinado a equipos de ingeniería, producto y gestión que documentan acuerdos de reuniones con autorización de sus participantes. Conservas la propiedad total sobre tus archivos .txt originales y las minutas exportadas en formato Markdown .md, siendo responsable del respaldo periódico de tu base de datos SQLite local.
        </p>
        <div class="mt-6 flex justify-end">
            <button type="button" data-close-dialog="terms-dialog" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">
                Cerrar ventana
            </button>
        </div>
    </dialog>

    <script>
        (function () {
            'use strict';

            // 1. Conmutador de Tema Claro / Oscuro con rotación suave
            const btnTheme = document.getElementById('btn-theme-toggle');
            let themeRotation = 0;
            if (btnTheme) {
                btnTheme.addEventListener('click', function () {
                    const isDark = document.documentElement.classList.toggle('dark');
                    try {
                        localStorage.setItem('app_theme', isDark ? 'dark' : 'light');
                    } catch (e) {}
                    themeRotation += 180;
                    btnTheme.style.transform = 'rotate(' + themeRotation + 'deg)';
                });
            }

            // 2. Menú Móvil con animación escalonada
            const btnMobileMenu = document.getElementById('btn-mobile-menu');
            const mobilePanel = document.getElementById('mobile-nav-panel');
            const iconMenuOpen = document.getElementById('icon-menu-open');
            const iconMenuClose = document.getElementById('icon-menu-close');

            function toggleMobileMenu(forceClose) {
                if (!mobilePanel || !btnMobileMenu) return;
                const isOpen = forceClose ? false : mobilePanel.hidden;
                mobilePanel.hidden = !isOpen;
                btnMobileMenu.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                iconMenuOpen.classList.toggle('hidden', isOpen);
                iconMenuClose.classList.toggle('hidden', !isOpen);
                requestAnimationFrame(function () {
                    mobilePanel.classList.toggle('mobile-menu-open', isOpen);
                });
            }

            if (btnMobileMenu && mobilePanel) {
                btnMobileMenu.addEventListener('click', function () {
                    toggleMobileMenu(false);
                });
                mobilePanel.addEventListener('click', function (e) {
                    if (e.target.closest('a')) {
                        toggleMobileMenu(true);
                    }
                });
            }

            // 3. Pestañas Iniciar sesión / Crear cuenta
            const tabLogin = document.getElementById('tab-btn-login');
            const tabRegister = document.getElementById('tab-btn-register');
            const formLogin = document.getElementById('form-login');
            const formRegister = document.getElementById('form-register');
            const alertBox = document.getElementById('auth-alert');
            const inputEmail = document.getElementById('login-email');
            const inputPwd = document.getElementById('login-password');
            const btnTogglePwd = document.getElementById('btn-toggle-pwd');
            const iconEyeOpen = document.getElementById('icon-eye-open');
            const iconEyeClosed = document.getElementById('icon-eye-closed');

            function switchTab(isLogin) {
                tabLogin.setAttribute('aria-selected', isLogin ? 'true' : 'false');
                tabRegister.setAttribute('aria-selected', !isLogin ? 'true' : 'false');

                tabLogin.className = isLogin
                    ? 'rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] dark:bg-[#272727] dark:text-white'
                    : 'rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white';

                tabRegister.className = !isLogin
                    ? 'rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] dark:bg-[#272727] dark:text-white'
                    : 'rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white';

                formLogin.hidden = !isLogin;
                formRegister.hidden = isLogin;
                alertBox.hidden = true;
            }

            tabLogin.addEventListener('click', function () { switchTab(true); });
            tabRegister.addEventListener('click', function () { switchTab(false); });

            // 4. Mostrar / Ocultar contraseña
            if (btnTogglePwd && inputPwd) {
                btnTogglePwd.addEventListener('click', function () {
                    const isPwd = inputPwd.type === 'password';
                    inputPwd.type = isPwd ? 'text' : 'password';
                    iconEyeOpen.classList.toggle('hidden', isPwd);
                    iconEyeClosed.classList.toggle('hidden', !isPwd);
                });
            }

            // 5. Autocompletar credenciales demo en 1 clic
            function fillAndFocusDemo(autoSubmit) {
                switchTab(true);
                inputEmail.value = 'admin@transcripciones.local';
                inputPwd.value = 'Admin123*';
                inputEmail.focus();
                if (autoSubmit) {
                    formLogin.requestSubmit();
                }
            }

            const btnFillDemo = document.getElementById('btn-fill-demo');
            if (btnFillDemo) {
                btnFillDemo.addEventListener('click', function () {
                    fillAndFocusDemo(false);
                });
            }

            const btnHeroDemo = document.getElementById('btn-hero-demo');
            if (btnHeroDemo) {
                btnHeroDemo.addEventListener('click', function () {
                    fillAndFocusDemo(true);
                });
            }

            // 6. Envío de Autenticación (Login / Registro / Google)
            async function submitAuth(url, payload, btnEl) {
                if (btnEl) btnEl.disabled = true;
                alertBox.hidden = true;
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await response.json();
                    if (!response.ok || data.status !== 'ok') {
                        alertBox.textContent = data.message || '<?= esc("Las credenciales ingresadas no coinciden con ninguna cuenta activa.", 'js') ?>';
                        alertBox.hidden = false;
                        return;
                    }
                    if (data.token) {
                        localStorage.setItem('jwt_token', data.token);
                    }
                    window.location.href = '<?= site_url('/') ?>';
                } catch (err) {
                    alertBox.textContent = '<?= esc("No fue posible conectar con el servidor local. Verifica tu servicio PHP y vuelve a intentarlo.", 'js') ?>';
                    alertBox.hidden = false;
                } finally {
                    if (btnEl) btnEl.disabled = false;
                }
            }

            formLogin.addEventListener('submit', function (e) {
                e.preventDefault();
                let emailVal = inputEmail.value.trim();
                if (emailVal.toLowerCase() === 'profesor' || emailVal.toLowerCase() === 'admin') {
                    emailVal = 'admin@transcripciones.local';
                }
                submitAuth(formLogin.action, {
                    email: emailVal,
                    password: inputPwd.value
                }, document.getElementById('btn-submit-login'));
            });

            formRegister.addEventListener('submit', function (e) {
                e.preventDefault();
                submitAuth(formRegister.action, {
                    name: document.getElementById('reg-name').value.trim(),
                    email: document.getElementById('reg-email').value.trim(),
                    password: document.getElementById('reg-password').value
                }, document.getElementById('btn-submit-register'));
            });

            const btnGoogle = document.getElementById('btn-google-oauth');
            if (btnGoogle) {
                btnGoogle.addEventListener('click', function () {
                    submitAuth('<?= site_url('auth/google') ?>', {
                        email: 'admin@transcripciones.local',
                        name: 'Administrador Local'
                    }, btnGoogle);
                });
            }

            window.handleGoogleCredentialResponse = function (response) {
                submitAuth('<?= site_url('auth/google') ?>', {
                    credential: response.credential
                }, null);
            };

            // 7. Tagline Reveal Palabra por Palabra (B11) con IntersectionObserver
            const taglineContainer = document.getElementById('tagline-reveal-container');
            const taglineWords = taglineContainer ? Array.from(taglineContainer.querySelectorAll('.tagline-word')) : [];
            const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (taglineWords.length > 0) {
                if (reducedMotion || !('IntersectionObserver' in window)) {
                    taglineWords.forEach(function (w) { w.classList.add('is-active'); });
                } else {
                    let isIntersecting = false;
                    let rafPending = false;

                    function updateTaglineWordsOnScroll() {
                        rafPending = false;
                        if (!isIntersecting || !taglineContainer) return;

                        const rect = taglineContainer.getBoundingClientRect();
                        const viewH = window.innerHeight || document.documentElement.clientHeight;
                        const startY = viewH * 0.85;
                        const endY = viewH * 0.25;
                        const progress = Math.max(0, Math.min(1, (startY - rect.top) / Math.max(1, startY - endY)));
                        const activeCount = Math.ceil(progress * taglineWords.length);

                        taglineWords.forEach(function (word, idx) {
                            word.classList.toggle('is-active', idx < activeCount);
                        });
                    }

                    function onWindowScroll() {
                        if (!isIntersecting || rafPending) return;
                        rafPending = true;
                        requestAnimationFrame(updateTaglineWordsOnScroll);
                    }

                    const observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            isIntersecting = entry.isIntersecting;
                            if (isIntersecting) {
                                updateTaglineWordsOnScroll();
                                window.addEventListener('scroll', onWindowScroll, { passive: true });
                            } else {
                                window.removeEventListener('scroll', onWindowScroll);
                            }
                        });
                    }, { threshold: [0, 0.15, 0.5, 1] });

                    observer.observe(taglineContainer);
                }
            }

            // 8. Modales <dialog> de Privacidad y Condiciones
            const privacyDialog = document.getElementById('privacy-dialog');
            const termsDialog = document.getElementById('terms-dialog');
            const btnOpenPrivacy = document.getElementById('btn-open-privacy');
            const btnOpenTerms = document.getElementById('btn-open-terms');

            if (btnOpenPrivacy && privacyDialog && typeof privacyDialog.showModal === 'function') {
                btnOpenPrivacy.addEventListener('click', function () {
                    privacyDialog.showModal();
                });
            }
            if (btnOpenTerms && termsDialog && typeof termsDialog.showModal === 'function') {
                btnOpenTerms.addEventListener('click', function () {
                    termsDialog.showModal();
                });
            }

            document.querySelectorAll('[data-close-dialog]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = btn.getAttribute('data-close-dialog');
                    const dlg = id ? document.getElementById(id) : null;
                    if (dlg && typeof dlg.close === 'function') {
                        dlg.close();
                    }
                });
            });
        })();
    </script>
</body>
</html>
