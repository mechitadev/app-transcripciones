<?php

namespace App\Controllers;

use App\Models\TaskModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controlador MVC de Tareas de la Minuta (`TaskController` - Módulo 1).
 *
 * Permite:
 *  - Crear tareas adicionales manualmente vinculadas a una reunión y minuto (`POST /api/tasks`)
 *  - Actualizar estado (`pending`, `in_progress`, `done`), responsable o resumen (`PATCH /api/tasks/(:num)`)
 *  - Eliminar tareas (`DELETE /api/tasks/(:num)`)
 */
class TaskController extends BaseController
{
    private TaskModel $taskModel;

    public function __construct()
    {
        $this->taskModel = new TaskModel();
    }

    /**
     * Crea una nueva tarea en la minuta (`POST /api/tasks`).
     */
    public function create(): ResponseInterface
    {
        $payload    = $this->getRequestPayload();
        $meetingId  = (int) ($payload['meeting_id'] ?? $payload['reunion_id'] ?? 0);
        $summary    = trim((string) ($payload['summary'] ?? $payload['resumen'] ?? ''));
        $assignee   = trim((string) ($payload['assignee'] ?? $payload['asignado'] ?? 'Equipo'));
        $timestamp  = trim((string) ($payload['timestamp_label'] ?? $payload['etiqueta_tiempo'] ?? '00:00'));
        $rawEntryId = $payload['entry_id'] ?? $payload['intervencion_id'] ?? null;

        if ($meetingId <= 0 || $summary === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'field'   => 'summary',
                'message' => 'Completa todos los campos requeridos con un formato válido.',
            ]);
        }

        $fila = [
            'reunion_id'        => $meetingId,
            'intervencion_id'   => ! empty($rawEntryId) ? (int) $rawEntryId : null,
            'etiqueta_tiempo'   => $timestamp !== '' ? $timestamp : '00:00',
            'resumen'           => $summary,
            'asignado'          => $assignee !== '' ? $assignee : 'Equipo',
            'estado'            => 'pending',
            'puntaje_confianza' => 1.0,
        ];

        $id         = (int) $this->taskModel->insert($fila, true);
        $fila['id'] = $id;

        return $this->response->setStatusCode(201)->setJSON([
            'status' => 'ok',
            'task'   => $this->taskModel->mapearTarea($fila),
        ]);
    }

    /**
     * Actualiza el estado, responsable o texto de una tarea (`PATCH /api/tasks/(:num)`).
     */
    public function update(int $id): ResponseInterface
    {
        $existing = $this->taskModel->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Tarea no encontrada.',
            ]);
        }

        $payload = $this->getRequestPayload();
        $updates = [];

        if (isset($payload['status']) && in_array($payload['status'], ['pending', 'in_progress', 'done'], true)) {
            $updates['estado'] = (string) $payload['status'];
        }

        if (isset($payload['assignee']) && trim((string) $payload['assignee']) !== '') {
            $updates['asignado'] = trim((string) $payload['assignee']);
        }

        if (isset($payload['summary']) && trim((string) $payload['summary']) !== '') {
            $updates['resumen'] = trim((string) $payload['summary']);
        }

        if ($updates !== []) {
            $this->taskModel->update($id, $updates);
            $existing = $this->taskModel->find($id);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'task'   => $existing,
        ]);
    }

    /**
     * Elimina una tarea de la minuta (`DELETE /api/tasks/(:num)`).
     */
    public function delete(int $id): ResponseInterface
    {
        $existing = $this->taskModel->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Tarea no encontrada.',
            ]);
        }

        $this->taskModel->delete($id);

        return $this->response->setJSON([
            'status'     => 'ok',
            'deleted_id' => $id,
        ]);
    }
}
