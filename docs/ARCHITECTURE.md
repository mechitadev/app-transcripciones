# Arquitectura del Sistema · Unitrax (App-Transcripciones)

Documento de referencia técnica sobre las decisiones de diseño, flujo de datos, esquema de base de datos y patrones de software implementados en **Unitrax**.

---

## 1. Principios de Arquitectura

El proyecto sigue una filosofía rigurosa orientada a rendimiento predecible, simplicidad operativa y bajo consumo de recursos:

1. **Regla Ponytail (Full Mode):**
   - Máximo aprovechamiento de las funciones nativas de PHP 8 (`hash_hmac`, `hash_file`, `password_hash`, `SplFileObject`, `cURL`).
   - Cero dependencias externas innecesarias: no se instalan SDKs pesados para JWT ni para clientes de IA.
   - Todo el frontend corre sobre APIs web nativas del navegador (`<dialog>`, `<details>`, `IntersectionObserver`, `AbortController`, `DocumentFragment`, `localStorage`).

2. **Cotas Superiores y Protección Anti-Bucles:**
   - Lectura de archivos acotada a `MAX_LINES = 50000` para garantizar que ningún archivo mal formado cause bloqueos de memoria o bucles infinitos.
   - Procesamiento en una sola pasada $O(n)$: metadata de cabecera, sanitización de codificación UTF-8, filtrado de ruido y extracción de intervenciones se realizan en un único ciclo lineal.
   - Consultas por lotes con `insertBatch(..., null, 500)` dentro de transacciones SQLite; prohibidas las consultas individuales dentro de bucles ($N+1$).

3. **Arquitectura Híbrida de Inteligencia Artificial:**
   - **Nivel 1 (Local Determinista):** Extracción instantánea ($<10\text{ ms}$) mediante el catálogo heurístico `Config\Extraction`. Cero costo, $100\%$ disponible sin conexión a internet y cero alucinaciones.
   - **Nivel 2 (LLM Gratuito Opcional):** Enriquecimiento contextual mediante APIs gratuitas (Google Gemini Free Tier, Groq Cloud Free Tier u Ollama local) con degradación elegante (*graceful fallback*) automática ante límites de cuota (HTTP 429) o fallos de red.

---

## 2. Diagrama General de Arquitectura

```mermaid
flowchart TD
    subgraph Cliente["Navegador Web (Frontend)"]
        UI["Interfaz Tailwind CSS v4"]
        JS["app.js (Vanilla ES6+)"]
        Dialog["Modales Nativos <dialog>"]
        Theme["Theme Manager (Dark/Light)"]
    end

    subgraph Servidor["Servidor PHP (CodeIgniter 4)"]
        Router["CI4 Router (Routes.php)"]
        Filter["AuthFilter (Sesión + JWT HS256)"]
        
        subgraph Controladores["Controladores MVC"]
            AuthCtrl["AuthController"]
            MeetCtrl["MeetingController"]
            TaskCtrl["TaskController"]
            SearchCtrl["SearchController"]
        end

        subgraph Librerias["Servicios y Librerías"]
            Parser["TranscriptParser O(n)"]
            Analyzer["TranscriptAnalyzer"]
            FreeAI["FreeModelClient (cURL)"]
            JwtLib["JwtManager (HS256)"]
            GoogleLib["GoogleOAuthClient"]
        end

        subgraph Modelos["Capa de Datos"]
            UserM["UserModel"]
            MeetM["MeetingModel"]
            TaskM["TaskModel"]
            DecM["DecisionModel"]
        end
    end

    subgraph BaseDatos["Almacenamiento Local (SQLite3)"]
        SQL[(transcripciones.db)]
        FTS[(Virtual Table FTS5 bm25)]
        Triggers["Triggers DB (Insert/Update/Delete)"]
    end

    subgraph ProveedoresIA["Proveedores de IA Externos (Opcional)"]
        Gemini["Google Gemini Free Tier"]
        Groq["Groq Cloud Free Tier"]
        Ollama["Ollama Local (11434)"]
    end

    UI --> JS
    JS -->|Fetch API + AbortController| Router
    Router --> Filter
    Filter --> Controladores
    MeetCtrl --> Parser
    MeetCtrl --> Analyzer
    Analyzer --> FreeAI
    FreeAI -.->|cURL HTTP| ProveedoresIA
    Controladores --> Modelos
    Modelos --> SQL
    SQL --> Triggers
    Triggers --> FTS
```

---

## 3. Esquema de Base de Datos y Motor FTS5

El sistema utiliza **SQLite3** con la extensión **FTS5** habilitada de forma nativa. La base de datos se almacena en el archivo persistente `writable/database/transcripciones.db`.

### Diagrama Entidad-Relación (ERD)

```mermaid
erDiagram
    usuarios ||--o{ reuniones : "crea"
    reuniones ||--o{ intervenciones_transcripcion : "contiene"
    reuniones ||--o{ minutas_tareas : "genera"
    reuniones ||--o{ decisiones_tecnicas : "registra"
    intervenciones_transcripcion ||--o{ minutas_tareas : "vincula minuto"
    intervenciones_transcripcion ||--o{ decisiones_tecnicas : "vincula minuto"
    decisiones_tecnicas ||--|| decisiones_tecnicas_fts : "sincroniza via triggers"

    usuarios {
        INTEGER id PK
        VARCHAR nombre
        VARCHAR correo UK
        VARCHAR clave_hash
        VARCHAR google_id
        VARCHAR foto_perfil_url
        DATETIME creado_en
    }

    reuniones {
        INTEGER id PK
        INTEGER usuario_id FK
        VARCHAR titulo
        VARCHAR nombre_archivo_origen
        VARCHAR iniciado_en
        INTEGER duracion_minutos
        TEXT participantes_json
        VARCHAR url_original
        TEXT resumen_ejecutivo
        VARCHAR hash_crudo UK
        VARCHAR modelo_ia_usado
        DATETIME creado_en
    }

    intervenciones_transcripcion {
        INTEGER id PK
        INTEGER reunion_id FK
        INTEGER segundo_tiempo
        VARCHAR etiqueta_tiempo
        VARCHAR orador
        TEXT contenido
    }

    minutas_tareas {
        INTEGER id PK
        INTEGER reunion_id FK
        INTEGER intervencion_id FK
        VARCHAR etiqueta_tiempo
        TEXT resumen
        VARCHAR asignado
        VARCHAR estado
        FLOAT puntaje_confianza
    }

    decisiones_tecnicas {
        INTEGER id PK
        INTEGER reunion_id FK
        INTEGER intervencion_id FK
        VARCHAR categoria
        VARCHAR titulo
        TEXT justificacion
        VARCHAR orador
        VARCHAR etiqueta_tiempo
        TEXT etiquetas_semanticas
    }

    decisiones_tecnicas_fts {
        VARCHAR titulo
        TEXT justificacion
        TEXT etiquetas_semanticas
        VARCHAR orador
    }
```

### Sincronización Automática con FTS5

Para garantizar que el índice de texto completo nunca quede desactualizado respecto a la tabla principal, la migración `CreateInitialSchema` registra tres disparadores (*triggers*) en SQLite:

```sql
-- Trigger de Inserción
CREATE TRIGGER trg_decisiones_ai AFTER INSERT ON decisiones_tecnicas BEGIN
    INSERT INTO decisiones_tecnicas_fts(rowid, titulo, justificacion, etiquetas_semanticas, orador)
    VALUES (new.id, new.titulo, new.justificacion, new.etiquetas_semanticas, new.orador);
END;

-- Trigger de Borrado
CREATE TRIGGER trg_decisiones_ad AFTER DELETE ON decisiones_tecnicas BEGIN
    INSERT INTO decisiones_tecnicas_fts(decisiones_tecnicas_fts, rowid, titulo, justificacion, etiquetas_semanticas, orador)
    VALUES ('delete', old.id, old.titulo, old.justificacion, old.etiquetas_semanticas, old.orador);
END;

-- Trigger de Actualización
CREATE TRIGGER trg_decisiones_au AFTER UPDATE ON decisiones_tecnicas BEGIN
    INSERT INTO decisiones_tecnicas_fts(decisiones_tecnicas_fts, rowid, titulo, justificacion, etiquetas_semanticas, orador)
    VALUES ('delete', old.id, old.titulo, old.justificacion, old.etiquetas_semanticas, old.orador);
    INSERT INTO decisiones_tecnicas_fts(rowid, titulo, justificacion, etiquetas_semanticas, orador)
    VALUES (new.id, new.titulo, new.justificacion, new.etiquetas_semanticas, new.orador);
END;
```

---

## 4. Pipeline de Ingesta y Procesamiento

```mermaid
sequenceDiagram
    autonumber
    actor Usuario
    participant Frontend as JS (app.js)
    participant MeetingCtrl as MeetingController
    participant Parser as TranscriptParser
    participant Analyzer as TranscriptAnalyzer
    participant AIClient as FreeModelClient
    participant DB as SQLite3 (MeetingModel)

    Usuario->>Frontend: Selecciona archivo .txt o hace clic en muestra real
    Frontend->>MeetingCtrl: POST /api/meetings/upload
    MeetingCtrl->>Parser: parseFile($filePath)
    Note over Parser: 1. Calcula hash SHA-256<br/>2. Lee línea a línea con SplFileObject<br/>3. Extrae metadata y participantes<br/>4. Filtra ruido (Tactiq AI, etc.)<br/>5. Cota máx. 50,000 líneas
    Parser-->>MeetingCtrl: Devuelve array estructurado

    MeetingCtrl->>DB: findByHash($rawHash)
    alt Hash ya existe en SQLite (Idempotencia)
        DB-->>MeetingCtrl: Registro existente
        MeetingCtrl-->>Frontend: 200 OK (Reunión previa recuperada)
    else Archivo nuevo
        MeetingCtrl->>Analyzer: analyze($metadata, $entries)
        Analyzer->>AIClient: analyzeTranscript($metadata, $entries)
        alt LLM Habilitado (Gemini / Groq / Ollama)
            AIClient-->>Analyzer: Minuta, tareas y decisiones estructuradas
        else Sin API Key o Error 429
            Note over Analyzer: Fallback automático al analizador<br/>heurístico local determinista
        end
        Analyzer-->>MeetingCtrl: Análisis final listo
        MeetingCtrl->>DB: saveFullMeeting() dentro de db->transBegin()
        Note over DB: Inserción por lotes en reuniones,<br/>intervenciones, minutas_tareas y<br/>decisiones_tecnicas (triggers FTS5)
        DB-->>MeetingCtrl: ID de la nueva reunión
        MeetingCtrl-->>Frontend: 201 Created
    end
    Frontend->>Usuario: Actualiza Workspace y renderiza minuta
```

---

## 5. Arquitectura del Frontend

El cliente web no utiliza dependencias pesadas tipo React, Vue o Webpack. Está construido con JavaScript moderno (ES6+) estructurado en un único ciclo de vida de aplicación:

- **Componentes modales nativos:** Uso de la etiqueta `<dialog>` de HTML5 con métodos nativos `.showModal()` y `.close()`, garantizando accesibilidad de teclado (`Escape`) y bloqueo de foco.
- **Cancelación de peticiones redundantes:** El buscador semántico y los filtros de transcripción implementan un patrón `debounce(250ms)` combinado con `AbortController`. Si el usuario sigue escribiendo, la petición HTTP anterior en tránsito se cancela inmediatamente.
- **Renderizado por lotes:** Las intervenciones largas se insertan en el DOM mediante `DocumentFragment`, reduciendo al mínimo los reflujos (*reflows*) y repintados (*repaints*) de la página.
- **Cero FOUC (Flash of Unstyled Content):** Un script minificado y bloqueante en la cabecera `<head>` lee `localStorage.getItem('app_theme')` y la preferencia del sistema operativo (`prefers-color-scheme`) antes de que el navegador pinte el primer píxel del DOM.
- **Sistema Visual:** Tailwind CSS v4 con variables CSS nativas, fuentes `Manrope` (400 a 700) para lectura general y `Geist Mono` para códigos, métricas y marcas de tiempo.

