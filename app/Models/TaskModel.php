<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo MVC para la tabla `minutas_tareas` (Módulo 1: Gestor de Minutas y Tareas).
 */
class TaskModel extends Model
{
    protected $table            = 'minutas_tareas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $afterFind        = ['aplicarMapeoLectura'];
    protected $allowedFields    = [
        'reunion_id',
        'intervencion_id',
        'etiqueta_tiempo',
        'resumen',
        'asignado',
        'estado',
        'puntaje_confianza',
    ];

    /**
     * Lista todas las tareas de una reunión específica mapeadas en español y alias compatibles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByMeeting(int $reunionId): array
    {
        return $this->where('reunion_id', $reunionId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Normaliza los campos de una tarea para que estén disponibles tanto en español
     * como con alias compatibles para el visor y la interfaz.
     *
     * @param array<string, mixed> $tarea
     * @return array<string, mixed>
     */
    public function mapearTarea(array $tarea): array
    {
        $estadoCrudo = (string) ($tarea['estado'] ?? $tarea['status'] ?? 'pending');
        $mapaEstado  = [
            'pendiente'   => 'pending',
            'en_progreso' => 'in_progress',
            'completada'  => 'done',
        ];
        $statusNormalizado = $mapaEstado[$estadoCrudo] ?? $estadoCrudo;

        $tarea['id']                = (int) $tarea['id'];
        $tarea['reunion_id']        = (int) ($tarea['reunion_id'] ?? $tarea['meeting_id'] ?? 0);
        $tarea['intervencion_id']   = isset($tarea['intervencion_id']) ? (int) $tarea['intervencion_id'] : null;
        $tarea['puntaje_confianza'] = (float) ($tarea['puntaje_confianza'] ?? $tarea['confidence_score'] ?? 0.85);

        // Alias retrocompatibles para contratos MVC y frontend
        $tarea['meeting_id']       = $tarea['reunion_id'];
        $tarea['entry_id']         = $tarea['intervencion_id'];
        $tarea['timestamp_label']  = $tarea['etiqueta_tiempo'] ?? '00:00';
        $tarea['summary']          = $tarea['resumen'] ?? '';
        $tarea['assignee']         = $tarea['asignado'] ?? '';
        $tarea['status']           = $statusNormalizado;
        $tarea['confidence_score'] = $tarea['puntaje_confianza'];

        return $tarea;
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
            $eventData['data'] = $this->mapearTarea($eventData['data']);
        } else {
            $eventData['data'] = array_map([$this, 'mapearTarea'], $eventData['data']);
        }

        return $eventData;
    }

    protected function transformDataToArray($row, string $type): array
    {
        $datos = parent::transformDataToArray($row, $type);

        $aliasAColumna = [
            'meeting_id'       => 'reunion_id',
            'entry_id'         => 'intervencion_id',
            'timestamp_label'  => 'etiqueta_tiempo',
            'summary'          => 'resumen',
            'assignee'         => 'asignado',
            'status'           => 'estado',
            'confidence_score' => 'puntaje_confianza',
        ];

        foreach ($aliasAColumna as $alias => $columna) {
            if (array_key_exists($alias, $datos) && ! array_key_exists($columna, $datos)) {
                $datos[$columna] = $datos[$alias];
            }
        }

        return $datos;
    }
}
