<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Extraction;

/**
 * Modelo MVC para `decisiones_tecnicas` + Índice Virtual `decisiones_tecnicas_fts`
 * (Módulo 2: Buscador Semántico de Decisiones Técnicas).
 *
 * Implementa búsqueda híbrida ultrarrápida sobre SQLite en español:
 *  1. Sanea la consulta del usuario para evitar errores de sintaxis en FTS5 `MATCH`.
 *  2. Expande la consulta con el diccionario semántico (`Config\Extraction::$semanticSynonyms`).
 *  3. Calcula relevancia con el algoritmo `bm25()` nativo de SQLite FTS5.
 */
class DecisionModel extends Model
{
    protected $table            = 'decisiones_tecnicas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $afterFind        = ['aplicarMapeoLectura'];
    protected $allowedFields    = [
        'reunion_id',
        'intervencion_id',
        'categoria',
        'titulo',
        'justificacion',
        'orador',
        'etiqueta_tiempo',
        'etiquetas_semanticas',
    ];

    /**
     * Ejecuta una búsqueda semántica y filtrada sobre las decisiones técnicas.
     *
     * @return array{
     *   query: string,
     *   expanded_terms: array<int, string>,
     *   results: array<int, array<string, mixed>>
     * }
     */
    public function searchDecisions(
        string $consultaBruta = '',
        ?string $categoria = null,
        ?string $orador = null,
        ?int $reunionId = null
    ): array {
        $consultaLimpia     = trim($consultaBruta);
        $terminosExpandidos = $this->expandQueryTerms($consultaLimpia);

        $tablaDecisiones     = $this->db->prefixTable('decisiones_tecnicas');
        $tablaFts            = $this->db->prefixTable('decisiones_tecnicas_fts');
        $tablaReuniones      = $this->db->prefixTable('reuniones');
        $tablaIntervenciones = $this->db->prefixTable('intervenciones_transcripcion');

        $clausulasWhere = ['1 = 1'];
        $parametros     = [];

        if ($reunionId !== null && $reunionId > 0) {
            $clausulasWhere[] = 'd.reunion_id = ?';
            $parametros[]     = $reunionId;
        }

        if ($categoria !== null && in_array($categoria, ['decision', 'blocker', 'bloqueo', 'architecture', 'arquitectura'], true)) {
            $catNormalizada   = ($categoria === 'bloqueo') ? 'blocker' : (($categoria === 'arquitectura') ? 'architecture' : $categoria);
            $clausulasWhere[] = '(d.categoria = ? OR d.categoria = ?)';
            $parametros[]     = $categoria;
            $parametros[]     = $catNormalizada;
        }

        if ($orador !== null && trim($orador) !== '' && strtolower(trim($orador)) !== 'all' && strtolower(trim($orador)) !== 'todos') {
            $clausulasWhere[] = 'd.orador = ?';
            $parametros[]     = trim($orador);
        }

        // Caso A: Sin término de búsqueda -> listar decisiones filtradas
        if ($terminosExpandidos === []) {
            $whereSql = implode(' AND ', $clausulasWhere);
            $sql      = "SELECT d.*, m.titulo AS titulo_reunion, m.titulo AS meeting_title,
                                e.contenido AS contexto_intervencion, e.contenido AS entry_context,
                                0.0 AS rank_score
                         FROM {$tablaDecisiones} d
                         LEFT JOIN {$tablaReuniones} m ON m.id = d.reunion_id
                         LEFT JOIN {$tablaIntervenciones} e ON e.id = d.intervencion_id
                         WHERE {$whereSql}
                         ORDER BY d.id ASC
                         LIMIT 100";

            $filas = $this->db->query($sql, $parametros)->getResultArray();

            return [
                'query'          => $consultaLimpia,
                'expanded_terms' => [],
                'results'        => array_map([$this, 'mapearDecision'], $filas),
            ];
        }

        // Caso B: Búsqueda híbrida FTS5 (MATCH + bm25) con expansión semántica
        $expresionFts = $this->buildFtsMatchExpression($terminosExpandidos);
        $whereSql     = implode(' AND ', $clausulasWhere);

        $sql = "SELECT d.*, m.titulo AS titulo_reunion, m.titulo AS meeting_title,
                       e.contenido AS contexto_intervencion, e.contenido AS entry_context,
                       bm25({$tablaFts}, 3.0, 2.0, 2.5, 1.0) AS rank_score
                FROM {$tablaFts}
                INNER JOIN {$tablaDecisiones} d ON d.id = {$tablaFts}.rowid
                LEFT JOIN {$tablaReuniones} m ON m.id = d.reunion_id
                LEFT JOIN {$tablaIntervenciones} e ON e.id = d.intervencion_id
                WHERE {$tablaFts} MATCH ? AND {$whereSql}
                ORDER BY rank_score ASC, d.id ASC
                LIMIT 100";

        $parametrosFts = array_merge([$expresionFts], $parametros);
        $filas         = $this->db->query($sql, $parametrosFts)->getResultArray();

        // Fallback LIKE parametrizado si la consulta tenía acentos o caracteres específicos
        if ($filas === []) {
            $clausulasLike  = [];
            $parametrosLike = [];
            foreach (array_slice($terminosExpandidos, 0, 6) as $termino) {
                $clausulasLike[]  = '(d.titulo LIKE ? OR d.justificacion LIKE ? OR d.etiquetas_semanticas LIKE ?)';
                $comodin          = '%' . $termino . '%';
                $parametrosLike[] = $comodin;
                $parametrosLike[] = $comodin;
                $parametrosLike[] = $comodin;
            }

            $likeSql = '(' . implode(' OR ', $clausulasLike) . ')';
            $sql     = "SELECT d.*, m.titulo AS titulo_reunion, m.titulo AS meeting_title,
                               e.contenido AS contexto_intervencion, e.contenido AS entry_context,
                               0.0 AS rank_score
                        FROM {$tablaDecisiones} d
                        LEFT JOIN {$tablaReuniones} m ON m.id = d.reunion_id
                        LEFT JOIN {$tablaIntervenciones} e ON e.id = d.intervencion_id
                        WHERE {$whereSql} AND {$likeSql}
                        ORDER BY d.id ASC
                        LIMIT 100";

            $filas = $this->db->query($sql, array_merge($parametros, $parametrosLike))->getResultArray();
        }

        return [
            'query'          => $consultaLimpia,
            'expanded_terms' => $terminosExpandidos,
            'results'        => array_map([$this, 'mapearDecision'], $filas),
        ];
    }

    /**
     * Mapea y retrocompatibiliza los nombres de campos de una decisión técnica.
     *
     * @param array<string, mixed> $decision
     * @return array<string, mixed>
     */
    public function mapearDecision(array $decision): array
    {
        $decision['id']              = (int) $decision['id'];
        $decision['reunion_id']      = (int) ($decision['reunion_id'] ?? $decision['meeting_id'] ?? 0);
        $decision['intervencion_id'] = isset($decision['intervencion_id']) ? (int) $decision['intervencion_id'] : null;

        // Alias retrocompatibles para contratos MVC y frontend
        $decision['meeting_id']      = $decision['reunion_id'];
        $decision['entry_id']        = $decision['intervencion_id'];
        $decision['title']           = $decision['titulo'] ?? '';
        $decision['rationale']       = $decision['justificacion'] ?? '';
        $decision['speaker']         = $decision['orador'] ?? '';
        $decision['timestamp_label'] = $decision['etiqueta_tiempo'] ?? '00:00';
        $decision['semantic_tags']   = $decision['etiquetas_semanticas'] ?? '';
        $decision['category']        = $decision['categoria'] ?? 'decision';

        return $decision;
    }

    /**
     * Sanea la entrada del usuario y expande términos relacionados usando `Config\Extraction::$semanticSynonyms`.
     *
     * @return array<int, string>
     */
    public function expandQueryTerms(string $consultaBruta): array
    {
        // Eliminar cualquier carácter especial de FTS5 (" * ^ : ( ) - OR AND NOT)
        $saneado = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower($consultaBruta, 'UTF-8'));
        $tokens  = preg_split('/\s+/u', trim($saneado), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($tokens) || $tokens === []) {
            return [];
        }

        /** @var Extraction $extraccion */
        $extraccion = config('Extraction');
        $sinonimos  = $extraccion->semanticSynonyms;

        $expandidos = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            $expandidos[$token] = true;

            // Buscar coincidencias en las claves o valores del mapa semántico
            foreach ($sinonimos as $concepto => $listaRelacionada) {
                $conceptoNorm = $this->stripAccents($concepto);
                $tokenNorm    = $this->stripAccents($token);

                if (strpos($conceptoNorm, $tokenNorm) !== false || strpos($tokenNorm, $conceptoNorm) !== false) {
                    foreach ($listaRelacionada as $sin) {
                        $palabras = preg_split('/\s+/u', $sin, -1, PREG_SPLIT_NO_EMPTY);
                        if (is_array($palabras)) {
                            foreach ($palabras as $p) {
                                if (mb_strlen($p, 'UTF-8') >= 3) {
                                    $expandidos[mb_strtolower($p, 'UTF-8')] = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_slice(array_keys($expandidos), 0, 12);
    }

    /**
     * Infiere etiquetas semánticas cruzando el texto con `Config\Extraction::$semanticSynonyms`.
     */
    public function inferSemanticTags(string $texto): string
    {
        /** @var Extraction $extraccion */
        $extraccion = config('Extraction');
        $minusculas = mb_strtolower($texto, 'UTF-8');
        $etiquetas  = [];

        foreach ($extraccion->semanticSynonyms as $concepto => $terminos) {
            if (mb_strpos($minusculas, mb_strtolower((string) $concepto, 'UTF-8'), 0, 'UTF-8') !== false) {
                $etiquetas[$concepto] = true;
            }

            foreach ($terminos as $termino) {
                if (mb_strpos($minusculas, mb_strtolower($termino, 'UTF-8'), 0, 'UTF-8') !== false) {
                    $etiquetas[$concepto] = true;
                    foreach (array_slice($terminos, 0, 3) as $relacionado) {
                        $etiquetas[$relacionado] = true;
                    }
                    break;
                }
            }
        }

        if ($etiquetas === []) {
            return 'decision, acuerdo';
        }

        return implode(', ', array_keys($etiquetas));
    }

    /**
     * @param array<string, mixed> $eventData
     * @return array<string, mixed>
     */
    protected function aplicarMapeoLectura(array $eventData): array
    {
        if (! isset($eventData['data']) || ! is_array($eventData['data'])) {
            return $eventData;
        }

        if (! empty($eventData['singleton'])) {
            $eventData['data'] = $this->mapearDecision($eventData['data']);
        } else {
            $eventData['data'] = array_map([$this, 'mapearDecision'], $eventData['data']);
        }

        return $eventData;
    }

    protected function transformDataToArray($row, string $type): array
    {
        $datos = parent::transformDataToArray($row, $type);

        $aliasAColumna = [
            'meeting_id'      => 'reunion_id',
            'entry_id'        => 'intervencion_id',
            'category'        => 'categoria',
            'title'           => 'titulo',
            'rationale'       => 'justificacion',
            'speaker'         => 'orador',
            'timestamp_label' => 'etiqueta_tiempo',
            'semantic_tags'   => 'etiquetas_semanticas',
        ];

        foreach ($aliasAColumna as $alias => $columna) {
            if (array_key_exists($alias, $datos) && ! array_key_exists($columna, $datos)) {
                $datos[$columna] = $datos[$alias];
            }
        }

        return $datos;
    }

    /**
     * Construye una expresión `MATCH` segura para SQLite FTS5 usando comodines de prefijo.
     *
     * @param array<int, string> $terminos
     */
    private function buildFtsMatchExpression(array $terminos): string
    {
        $tokensSeguros = [];
        foreach ($terminos as $termino) {
            $limpio = (string) preg_replace('/[^\p{L}\p{N}]/u', '', $termino);
            if ($limpio !== '') {
                $tokensSeguros[] = '"' . $limpio . '"*';
            }
        }

        return implode(' OR ', $tokensSeguros);
    }

    private function stripAccents(string $texto): string
    {
        return strtr(mb_strtolower($texto, 'UTF-8'), [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);
    }
}
