<?php
/**
 * Vista 404 personalizada de marca · App Transcripciones (`app/Views/errors/html/error_404.php`).
 */
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error 404 · App Transcripciones</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/tailwind.css') ?>">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('app_theme');
                if (saved === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12 text-slate-900 dark:bg-[#181818] dark:text-zinc-100 antialiased">
    <main class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 text-center dark:border-[#313131] dark:bg-[#1F1F1F]">
        <span class="inline-flex rounded-lg border border-slate-200 bg-slate-100 px-3 py-1 font-mono text-xs font-semibold text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-emerald-400">
            Error 404
        </span>
        <h1 class="mt-4 text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">
            No encontramos la página que buscas
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-zinc-400">
            La ruta solicitada no existe o fue movida dentro de App Transcripciones.
        </p>
        <div class="mt-6">
            <a href="<?= site_url('/') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-base font-semibold text-white transition-colors duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:bg-emerald-500">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                <span>Volver al espacio principal</span>
            </a>
        </div>
    </main>
</body>
</html>
