# Especificación y Referencia de la API REST · Unitrax

> **Base URL:** `/` o `http://localhost/App-transcripciones/public/`  
> **Formato de intercambio:** `application/json` (salvo subida de archivos `multipart/form-data` y exportación `text/markdown`)  
> **Autenticación:** Sesión nativa de CodeIgniter 4 o cabecera HTTP `Authorization: Bearer <TOKEN_JWT>` (o cookie `auth_token`).

---

## 1. Mecanismo de Autenticación y Seguridad

Todas las rutas bajo `/api/*` están protegidas por el filtro `App\Filters\AuthFilter`. Se admiten tres mecanismos equivalentes para autenticar solicitudes:

1. **Sesión Web:** Si la petición proviene de un navegador con sesión activa (`session()->get('user_id')`).
2. **Cabecera HTTP Authorization:**
   ```http
   Authorization: Bearer <token_jwt_hs256>
   ```
3. **Cookie HTTP:** Enviando la cookie `auth_token` con el JWT emitido en el login.

### Respuestas de Error Comunes

| Código HTTP | Significado | Causa |
|:---:|---|---|
| `401 Unauthorized` | No autenticado | Token ausente, firma inválida o expirado. |
| `404 Not Found` | Recurso no encontrado | ID de reunión, tarea o decisión inexistente. |
| `422 Unprocessable` | Datos de entrada inválidos | Faltan campos obligatorios o formato incorrecto. |
| `500 Server Error` | Error de servidor | Fallo inesperado en base de datos o almacenamiento. |

---

## 2. Endpoints de Autenticación

### POST /auth/login
Inicia sesión con correo electrónico y contraseña.

**Request:**
```http
POST /auth/login HTTP/1.1
Content-Type: application/json

{
  "email": "admin@transcripciones.local",
  "password": "TU_PASSWORD"
}
```

**Respuesta Exitosa (`200 OK`):**
```json
{
  "status": "ok",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "name": "Administrador Demo",
    "email": "admin@transcripciones.local",
    "avatar_url": null
  },
  "redirect": "http://localhost/App-transcripciones/public/"
}
```

---

### POST /auth/register
Registra una nueva cuenta de usuario en el sistema.

**Request:**
```http
POST /auth/register HTTP/1.1
Content-Type: application/json

{
  "name": "Carlos Mendoza",
  "email": "carlos@empresa.com",
  "password": "PasswordSeguro123*"
}
```

**Respuesta Exitosa (`201 Created`):**
```json
{
  "status": "ok",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 2,
    "name": "Carlos Mendoza",
    "email": "carlos@empresa.com",
    "avatar_url": null
  },
  "redirect": "http://localhost/App-transcripciones/public/"
}
```

---

### POST /auth/google
Autenticación mediante Google Identity Services (OAuth 2.0).

**Request:**
```http
POST /auth/google HTTP/1.1
Content-Type: application/json

{
  "credential": "<ID_TOKEN_EMITIDO_POR_GOOGLE>"
}
```

**Respuesta Exitosa (`200 OK`):**
```json
{
  "status": "ok",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 3,
    "name": "Vanesa Machado",
    "email": "vanesa@gmail.com",
    "avatar_url": "https://lh3.googleusercontent.com/a/..."
  },
  "redirect": "http://localhost/App-transcripciones/public/"
}
```

---

### POST /auth/logout
Cierra la sesión activa en el servidor y limpia cookies de autenticación.

**Request:**
```http
POST /auth/logout HTTP/1.1
```

**Respuesta Exitosa (`200 OK`):**
```json
{
  "status": "ok",
  "redirect": "http://localhost/App-transcripciones/public/login"
}
```

---

## 3. Endpoints de Reuniones (`/api/meetings`)

### GET /api/meetings
Obtiene la lista de reuniones procesadas para el usuario autenticado.

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "data": [
    {
      "id": 1,
      "titulo": "Sincronización Técnica: WhatsApp & Pasarela",
      "nombre_archivo_origen": "formato-real.txt",
      "iniciado_en": "23/10/2026, 8:48:01",
      "duracion_minutos": 7,
      "participantes": ["Alexander pertus", "Vanesa Machado"],
      "creado_en": "2026-11-06 09:00:00"
    }
  ]
}
```

---

### POST /api/meetings/upload
Sube y procesa una transcripción `.txt` en una sola pasada `O(n)`. Se admite tanto archivo en formulario multipart como contenido en texto crudo o activación del archivo de muestra real.

#### Opción A: Archivo `multipart/form-data`
**Campos del formulario:**
- `transcript_file` (File, requerido si no se envía texto directo): Archivo `.txt` de hasta 10 MB.
- `title` (String, opcional): Título personalizado de la reunión.

#### Opción B: Carga del archivo de muestra (`load_sample = true`)
Permite probar el sistema de inmediato con el archivo de prueba `.agents/resources/formato-transcripcion/formato-real.txt`.

**Request:**
```http
POST /api/meetings/upload HTTP/1.1
Content-Type: application/json

{
  "load_sample": true
}
```

**Respuesta Exitosa (`201 Created`):**
```json
{
  "status": "ok",
  "meeting_id": 1,
  "meeting": {
    "id": 1,
    "title": "Reunión de Coordinación: Verificación WhatsApp y Pasarela",
    "started_at": "23/10/2026, 8:48:01",
    "duration_minutes": 7,
    "participants": ["Alexander pertus", "Vanesa Machado"],
    "raw_hash": "c8b417c8cf4ba2a5cfa79cf66d3a863484f3e589be7...",
    "executive_summary": "Se acordó implementar la verificación de WhatsApp...",
    "ai_engine": "heuristic-local (Motor Semántico Determinista + FTS5)"
  },
  "message": "Transcripción procesada exitosamente."
}
```

> **Idempotencia:** Si se sube dos veces el mismo archivo (mismo hash SHA-256), el sistema no duplica los registros y devuelve la reunión ya existente con código `200 OK`.

---

### GET /api/meetings/:id
Devuelve la información completa de una reunión: metadata, resumen ejecutivo, lista de intervenciones cronológicas, tareas asociadas y decisiones técnicas.

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "data": {
    "meeting": {
      "id": 1,
      "title": "Sincronización Técnica",
      "started_at": "23/10/2026, 8:48:01",
      "duration_minutes": 7,
      "participants": ["Alexander pertus", "Vanesa Machado"],
      "executive_summary": "Acuerdos sobre plantilla de WhatsApp...",
      "ai_model_used": "heuristic-local"
    },
    "entries": [
      {
        "id": 1,
        "timestamp_sec": 0,
        "timestamp_label": "00:00",
        "speaker": "Vanesa Machado",
        "content": "Qué bueno"
      }
    ],
    "tasks": [
      {
        "id": 1,
        "entry_id": 25,
        "timestamp_label": "01:39",
        "summary": "Crear plantilla de WhatsApp para evitar bloqueo de Meta",
        "assignee": "Alexander pertus",
        "status": "pending",
        "confidence_score": 0.85
      }
    ],
    "decisions": [
      {
        "id": 1,
        "category": "decision",
        "title": "Aceptación de términos y condiciones antes del pago",
        "justification": "Se decide colocar los términos antes del paso de cobro",
        "speaker": "Alexander pertus",
        "timestamp_label": "02:50",
        "semantic_tags": ["terminos", "pago", "flujo"]
      }
    ]
  }
}
```

---

### PATCH /api/meetings/:id
Actualiza los campos editables de la reunión (título inline y/o resumen ejecutivo).

**Request:**
```http
PATCH /api/meetings/1 HTTP/1.1
Content-Type: application/json

{
  "title": "Nuevo Título de la Reunión",
  "executive_summary": "Resumen ejecutivo actualizado manualmente..."
}
```

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "meeting_id": 1,
  "message": "Reunión actualizada exitosamente."
}
```

---

### GET /api/meetings/:id/export
Genera y descarga un reporte completo de la minuta ejecutiva en formato Markdown puro (`.md`), listo para importar en Jira, Linear, Confluence o Notion.

**Headers de Respuesta:**
```http
Content-Type: text/markdown; charset=UTF-8
Content-Disposition: attachment; filename="minuta-reunion-1.md"
```

---

### DELETE /api/meetings/:id
Elimina de forma atómica una reunión y todos sus registros en cascada (intervenciones, tareas, decisiones e índices FTS5).

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "message": "Reunión eliminada correctamente."
}
```

---

## 4. Endpoints de Tareas (`/api/tasks` - Módulo 1)

### POST /api/tasks
Crea una nueva tarea o compromiso vinculado a una reunión.

**Request:**
```http
POST /api/tasks HTTP/1.1
Content-Type: application/json

{
  "meeting_id": 1,
  "summary": "Diseñar esquema de base de datos para pagos en Cele",
  "assignee": "José Daniel",
  "timestamp_label": "05:01",
  "entry_id": 35
}
```

**Response (`201 Created`):**
```json
{
  "status": "ok",
  "task": {
    "id": 12,
    "meeting_id": 1,
    "summary": "Diseñar esquema de base de datos para pagos en Cele",
    "assignee": "José Daniel",
    "timestamp_label": "05:01",
    "status": "pending",
    "confidence_score": 1.0
  }
}
```

---

### PATCH /api/tasks/:id
Actualiza una tarea existente (estado, responsable o texto).

**Valores admitidos para `status`:**
- `pending` / `pendiente`
- `in_progress` / `en_progreso`
- `done` / `completada`

**Request:**
```http
PATCH /api/tasks/12 HTTP/1.1
Content-Type: application/json

{
  "status": "done"
}
```

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "task_id": 12,
  "task": {
    "id": 12,
    "status": "done"
  }
}
```

---

### DELETE /api/tasks/:id
Elimina una tarea de la minuta.

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "message": "Tarea eliminada correctamente."
}
```

---

## 5. Endpoints de Decisiones Técnicas (`/api/decisions` - Módulo 2)

### GET /api/decisions/search
Motor de búsqueda semántica basado en SQLite FTS5 (`bm25()`) con expansión automática de sinónimos técnicos en español.

**Parámetros de consulta (Query Params):**

| Parámetro | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `q` | String | No | Término de búsqueda (ej. `whatsapp`, `pagos`, `limpieza`). |
| `category` | String | No | Filtro por categoría: `decision`, `blocker` o `architecture`. |
| `speaker` | String | No | Nombre del participante que emitió el acuerdo. |
| `meeting_id` | Int | No | ID de reunión específica (`0` o ausente = todas las reuniones). |

**Ejemplo de Request:**
```http
GET /api/decisions/search?q=whatsapp&category=decision HTTP/1.1
```

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "query": "whatsapp",
  "expanded_terms": ["whatsapp", "plantilla", "meta", "notificacion"],
  "total": 2,
  "results": [
    {
      "id": 4,
      "meeting_id": 1,
      "category": "decision",
      "title": "Envío de código de validación mediante plantilla de Meta",
      "justification": "Se descarta mensaje plano para evitar restricciones de spam.",
      "speaker": "Vanesa Machado",
      "timestamp_label": "01:39",
      "semantic_tags": ["whatsapp", "plantilla", "meta"],
      "relevance_rank": -4.21
    }
  ]
}
```

---

### POST /api/decisions
Registra manualmente un acuerdo, bloqueo técnico o nota de arquitectura.

**Request:**
```http
POST /api/decisions HTTP/1.1
Content-Type: application/json

{
  "meeting_id": 1,
  "category": "blocker",
  "title": "Falta de webhook de confirmación en la pasarela",
  "justification": "El servicio no notifica pagos en tiempo real si el usuario cierra el navegador",
  "speaker": "Alexander pertus",
  "timestamp_label": "06:40",
  "semantic_tags": "pasarela, pagos, webhook"
}
```

**Response (`201 Created`):**
```json
{
  "status": "ok",
  "decision": {
    "id": 15,
    "meeting_id": 1,
    "category": "blocker",
    "title": "Falta de webhook de confirmación en la pasarela",
    "speaker": "Alexander pertus",
    "timestamp_label": "06:40"
  }
}
```

---

### DELETE /api/decisions/:id
Elimina una decisión técnica de la base de datos y de la tabla virtual FTS5.

**Response (`200 OK`):**
```json
{
  "status": "ok",
  "message": "Decisión técnica eliminada correctamente."
}
```

