<?php

namespace App\Controllers;

use App\Models\DecisionModel;
use App\Models\MeetingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controlador MVC del Buscador Semántico de Decisiones Técnicas (`SearchController` - Módulo 2).
 *
 * Atiende peticiones de búsqueda (`GET /api/decisions/search`), registro manual o rápido
 * (`POST /api/decisions`) y eliminación (`DELETE /api/decisions/(:num)`) combinando:
 *  - Búsqueda de texto completo SQLite FTS5 (`bm25()`) sincronizada por triggers
 *  - Expansión semántica de términos técnicos (`Config\Extraction::$semanticSynonyms`)
 *  - Filtros combinables por categoría (`decision`, `blocker`, `architecture`),
 *    participante (`speaker`) y reunión (`meeting_id`).
 */
class SearchController extends BaseController
{
    private DecisionModel $decisionModel;
    private MeetingModel $meetingModel;

    public function __construct()
    {
        $this->decisionModel = new DecisionModel();
        $this->meetingModel  = new MeetingModel();
    }

    /**
     * Ejecuta la búsqueda semántica de decisiones técnicas (`GET /api/decisions/search`).
     */
    public function decisions(): ResponseInterface
    {
        $q         = (string) ($this->request->getGet('q') ?? '');
        $category  = (string) ($this->request->getGet('category') ?? '');
        $speaker   = (string) ($this->request->getGet('speaker') ?? '');
        $meetingId = (int) ($this->request->getGet('meeting_id') ?? 0);

        $searchData = $this->decisionModel->searchDecisions(
            $q,
            $category !== '' ? $category : null,
            $speaker !== '' ? $speaker : null,
            $meetingId > 0 ? $meetingId : null
        );

        return $this->response->setJSON([
            'status'         => 'ok',
            'query'          => $searchData['query'],
            'expanded_terms' => $searchData['expanded_terms'],
            'results'        => $searchData['results'],
        ]);
    }

    /**
     * Registra una nueva decisión técnica y la indexa vía trigger en SQLite FTS5 (`POST /api/decisions`).
     */
    public function create(): ResponseInterface
    {
        $payload      = $this->getRequestPayload();
        $meetingId    = (int) ($payload['meeting_id'] ?? $payload['reunion_id'] ?? 0);
        $title        = trim((string) ($payload['title'] ?? $payload['titulo'] ?? ''));
        $rationale    = trim((string) ($payload['rationale'] ?? $payload['justificacion'] ?? ''));
        $rawCategory  = trim((string) ($payload['category'] ?? $payload['categoria'] ?? 'decision'));
        $category     = in_array($rawCategory, ['decision', 'blocker', 'architecture'], true)
            ? $rawCategory
            : 'decision';
        $speaker      = trim((string) ($payload['speaker'] ?? $payload['orador'] ?? 'Equipo'));
        $timestamp    = trim((string) ($payload['timestamp_label'] ?? $payload['etiqueta_tiempo'] ?? '00:00'));
        $rawEntryId   = $payload['entry_id'] ?? $payload['intervencion_id'] ?? null;
        $entryId      = ! empty($rawEntryId) ? (int) $rawEntryId : null;
        $semanticTags = trim((string) ($payload['semantic_tags'] ?? $payload['etiquetas_semanticas'] ?? ''));

        if ($meetingId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'field'   => 'meeting_id',
                'message' => 'Completa todos los campos requeridos con un formato válido.',
            ]);
        }

        if ($title === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'field'   => 'title',
                'message' => 'Completa todos los campos requeridos con un formato válido.',
            ]);
        }

        if ($rationale === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'field'   => 'rationale',
                'message' => 'Completa todos los campos requeridos con un formato válido.',
            ]);
        }

        $meeting = $this->meetingModel->find($meetingId);
        if (! is_array($meeting)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'La reunión solicitada ya no existe.',
            ]);
        }

        if ($semanticTags === '') {
            $semanticTags = $this->decisionModel->inferSemanticTags($title . ' ' . $rationale);
        }

        $fila = [
            'reunion_id'           => $meetingId,
            'intervencion_id'      => $entryId,
            'categoria'            => $category,
            'titulo'               => mb_substr($title, 0, 255, 'UTF-8'),
            'justificacion'        => $rationale,
            'orador'               => $speaker !== '' ? mb_substr($speaker, 0, 120, 'UTF-8') : 'Equipo',
            'etiqueta_tiempo'      => $timestamp !== '' ? mb_substr($timestamp, 0, 16, 'UTF-8') : '00:00',
            'etiquetas_semanticas' => $semanticTags,
        ];

        $id         = (int) $this->decisionModel->insert($fila, true);
        $fila['id'] = $id;

        return $this->response->setStatusCode(201)->setJSON([
            'status'   => 'ok',
            'message'  => 'Decisión técnica registrada e indexada en FTS5.',
            'decision' => $this->decisionModel->mapearDecision($fila),
        ]);
    }

    /**
     * Elimina una decisión técnica y sincroniza la baja en `decisiones_tecnicas_fts` por trigger (`DELETE /api/decisions/(:num)`).
     */
    public function delete(int $id): ResponseInterface
    {
        $existing = $this->decisionModel->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Decisión técnica no encontrada.',
            ]);
        }

        $this->decisionModel->delete($id);

        return $this->response->setStatusCode(200)->setJSON([
            'status'     => 'ok',
            'message'    => 'Decisión técnica eliminada del índice.',
            'deleted_id' => $id,
        ]);
    }
}
