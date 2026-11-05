<?php

namespace App\Libraries;

use Config\Extraction;
use InvalidArgumentException;
use SplFileObject;

/**
 * Parser determinista de archivos `.txt` de transcripción en una sola pasada O(n).
 *
 * Cumple con `.agents/rules/optimization-and-loops.md`:
 *  - Lectura línea por línea mediante `SplFileObject` (bajo consumo de memoria).
 *  - Cota superior estricta `MAX_LINES = 50000` (cero riesgo de bucles infinitos).
 *  - Extrae metadata de cabecera y diálogo cronológico en el mismo recorrido.
 *  - Filtra el ruido promocional automático definido en `Config\Extraction::$noise`.
 */
class TranscriptParser
{
    /**
     * Límite máximo de líneas permitidas por archivo para prevenir bucles infinitos.
     */
    public const MAX_LINES = 50000;

    /**
     * Expresión regular centralizada (DRY) para líneas de intervención:
     * Soporta `MM:SS Autor: Texto` y `HH:MM:SS Autor: Texto` con o sin indentación.
     */
    private const ENTRY_REGEX = '/^\s*(\d{2}:\d{2}(?::\d{2})?)\s+([^:]{1,120}):\s*(.+)$/u';

    private Extraction $extractionConfig;

    /**
     * Regex compilada una sola vez para filtrar líneas de ruido (ej. Tactiq AI).
     */
    private string $noisePattern;

    public function __construct(?Extraction $extractionConfig = null)
    {
        $this->extractionConfig = $extractionConfig ?? config('Extraction');
        $quotedNoise            = array_map(
            static fn (string $phrase): string => preg_quote($phrase, '/'),
            $this->extractionConfig->noise
        );
        $this->noisePattern = $quotedNoise !== []
            ? '/(' . implode('|', $quotedNoise) . ')/iu'
            : '';
    }

    /**
     * Procesa un archivo `.txt` en una única pasada O(n) y retorna su metadata,
     * hash SHA-256 de idempotencia y lista de intervenciones limpias.
     *
     * @return array{
     *   raw_hash: string,
     *   metadata: array{
     *     title: string,
     *     started_at: string,
     *     duration_minutes: int,
     *     participants: array<int, string>,
     *     original_url: string|null
     *   },
     *   entries: array<int, array{
     *     timestamp_sec: int,
     *     timestamp_label: string,
     *     speaker: string,
     *     content: string
     *   }>
     * }
     */
    public function parseFile(string $filePath, ?string $customTitle = null): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException('El archivo de transcripción no existe o no se puede leer.');
        }

        $rawHash = (string) hash_file('sha256', $filePath);

        $title        = $customTitle ? trim($customTitle) : '';
        $startedAt    = '';
        $durationMin  = 0;
        $participants = [];
        $originalUrl  = null;
        $entries      = [];
        $speakersSeen = [];

        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $lineCount = 0;
        while (! $file->eof() && $lineCount < self::MAX_LINES) {
            $rawLine = (string) $file->fgets();
            $lineCount++;

            if ($rawLine === '') {
                continue;
            }

            // Garantizar codificación UTF-8 limpia
            if (! mb_check_encoding($rawLine, 'UTF-8')) {
                $rawLine = mb_convert_encoding($rawLine, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
            }

            $trimmed = trim($rawLine);
            if ($trimmed === '') {
                continue;
            }

            // 1. Intentar parsear como línea de diálogo (MM:SS Autor: Texto)
            if (preg_match(self::ENTRY_REGEX, $rawLine, $matches) === 1) {
                $timestampLabel = trim($matches[1]);
                $speaker        = trim($matches[2]);
                $content        = trim($matches[3]);

                // Filtrar ruido automático de extensiones (ej. Tactiq AI)
                if ($this->noisePattern !== '' && preg_match($this->noisePattern, $content) === 1) {
                    continue;
                }

                if ($content === '') {
                    continue;
                }

                $entries[] = [
                    'timestamp_sec'   => $this->timestampToSeconds($timestampLabel),
                    'timestamp_label' => $timestampLabel,
                    'speaker'         => $speaker,
                    'content'         => $content,
                ];

                $speakersSeen[$speaker] = true;
                continue;
            }

            // 2. Extraer metadata de la cabecera en la misma pasada
            if ($title === '' && strpos($trimmed, '# ') === 0) {
                $headerCandidate = trim(substr($trimmed, 2));
                if (strtolower($headerCandidate) !== 'meeting transcription') {
                    $title = $headerCandidate;
                }
                continue;
            }

            if (stripos($trimmed, 'Meeting started:') === 0) {
                $startedAt = trim(substr($trimmed, strlen('Meeting started:')));
                continue;
            }

            if (stripos($trimmed, 'Duration:') === 0) {
                if (preg_match('/(\d+)/', $trimmed, $durMatch) === 1) {
                    $durationMin = (int) $durMatch[1];
                }
                continue;
            }

            if (stripos($trimmed, 'Participants:') === 0) {
                $rawParticipants = trim(substr($trimmed, strlen('Participants:')));
                foreach (explode(',', $rawParticipants) as $participant) {
                    $cleanName = trim($participant);
                    if ($cleanName !== '' && ! in_array($cleanName, $participants, true)) {
                        $participants[] = $cleanName;
                    }
                }
                continue;
            }

            if ($originalUrl === null && preg_match('/\[View original transcript\]\((https?:\/\/[^\s\)]+)\)/i', $trimmed, $urlMatch) === 1) {
                $originalUrl = trim($urlMatch[1]);
                continue;
            }
        }

        // Si la cabecera no listó participantes, inferirlos de los hablantes detectados
        if ($participants === [] && $speakersSeen !== []) {
            $participants = array_keys($speakersSeen);
        }

        if ($title === '') {
            $title = $startedAt !== ''
                ? 'Reunión técnica · ' . $startedAt
                : 'Reunión · ' . pathinfo($filePath, PATHINFO_FILENAME);
        }

        return [
            'raw_hash' => $rawHash,
            'metadata' => [
                'title'            => $title,
                'started_at'       => $startedAt,
                'duration_minutes' => $durationMin,
                'participants'     => $participants,
                'original_url'     => $originalUrl,
            ],
            'entries' => $entries,
        ];
    }

    /**
     * Convierte una marca de tiempo `MM:SS` o `HH:MM:SS` a segundos enteros.
     */
    public function timestampToSeconds(string $label): int
    {
        $parts = array_map('intval', explode(':', trim($label)));
        if (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }
        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        }

        return 0;
    }
}

