<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración inicial para App-Transcripciones (SQLite3 + FTS5 en Español).
 *
 * Crea las tablas del patrón MVC completamente en español:
 *  - usuarios: Autenticación por sesión CI4, JWT y Google OAuth 2.0
 *  - reuniones: Cabecera, metadata y resumen ejecutivo de cada transcripción .txt
 *  - intervenciones_transcripcion: Intervenciones cronológicas (MM:SS Orador: Contenido)
 *  - minutas_tareas: Módulo 1 - Gestor de Minutas y Extracción Automática de Tareas
 *  - decisiones_tecnicas: Módulo 2 - Decisiones técnicas, bloqueos y arquitectura
 *  - decisiones_tecnicas_fts: Índice virtual SQLite FTS5 con triggers automáticos
 */
class CreateInitialSchema extends Migration
{
    public function up()
    {
        // 1. Tabla de usuarios (Sesiones, JWT, Google Login)
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'correo' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
            ],
            'clave_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'google_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
                'null'       => true,
            ],
            'foto_perfil_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'creado_en' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('correo');
        $this->forge->createTable('usuarios', true);

        // 2. Tabla de reuniones (Metadata extraída del .txt + Minuta ejecutiva)
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usuario_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'nombre_archivo_origen' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'iniciado_en' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'duracion_minutos' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'default'    => 0,
            ],
            'participantes_json' => [
                'type' => 'TEXT',
            ],
            'url_original' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'resumen_ejecutivo' => [
                'type' => 'TEXT',
            ],
            'hash_crudo' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'modelo_ia_usado' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'default'    => 'heuristic-local',
            ],
            'creado_en' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('hash_crudo');
        $this->forge->createTable('reuniones', true);

        // 3. Tabla de intervenciones cronológicas de la transcripción
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'reunion_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
            ],
            'segundo_tiempo' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'default'    => 0,
            ],
            'etiqueta_tiempo' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
            ],
            'orador' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'contenido' => [
                'type' => 'TEXT',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reunion_id');
        $this->forge->createTable('intervenciones_transcripcion', true);

        // 4. Módulo 1: Tabla de tareas y compromisos de la minuta
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'reunion_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
            ],
            'intervencion_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'etiqueta_tiempo' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => '00:00',
            ],
            'resumen' => [
                'type' => 'TEXT',
            ],
            'asignado' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pendiente',
            ],
            'puntaje_confianza' => [
                'type'    => 'FLOAT',
                'default' => 0.85,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reunion_id');
        $this->forge->createTable('minutas_tareas', true);

        // 5. Módulo 2: Tabla de decisiones técnicas, bloqueos y arquitectura
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'reunion_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
            ],
            'intervencion_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'categoria' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'decision',
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'justificacion' => [
                'type' => 'TEXT',
            ],
            'orador' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'etiqueta_tiempo' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
            ],
            'etiquetas_semanticas' => [
                'type' => 'TEXT',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reunion_id');
        $this->forge->createTable('decisiones_tecnicas', true);

        // 6. Índice virtual SQLite FTS5 sincronizado por triggers
        $decisionesTable = $this->db->prefixTable('decisiones_tecnicas');
        $ftsTable        = $this->db->prefixTable('decisiones_tecnicas_fts');
        $trigInsert      = $this->db->prefixTable('trg_decisiones_ai');
        $trigDelete      = $this->db->prefixTable('trg_decisiones_ad');
        $trigUpdate      = $this->db->prefixTable('trg_decisiones_au');

        $this->db->query("CREATE VIRTUAL TABLE IF NOT EXISTS {$ftsTable} USING fts5(
            titulo,
            justificacion,
            etiquetas_semanticas,
            orador,
            content='{$decisionesTable}',
            content_rowid='id'
        )");

        $this->db->query("CREATE TRIGGER IF NOT EXISTS {$trigInsert} AFTER INSERT ON {$decisionesTable} BEGIN
            INSERT INTO {$ftsTable}(rowid, titulo, justificacion, etiquetas_semanticas, orador)
            VALUES (new.id, new.titulo, new.justificacion, new.etiquetas_semanticas, new.orador);
        END");

        $this->db->query("CREATE TRIGGER IF NOT EXISTS {$trigDelete} AFTER DELETE ON {$decisionesTable} BEGIN
            INSERT INTO {$ftsTable}({$ftsTable}, rowid, titulo, justificacion, etiquetas_semanticas, orador)
            VALUES ('delete', old.id, old.titulo, old.justificacion, old.etiquetas_semanticas, old.orador);
        END");

        $this->db->query("CREATE TRIGGER IF NOT EXISTS {$trigUpdate} AFTER UPDATE ON {$decisionesTable} BEGIN
            INSERT INTO {$ftsTable}({$ftsTable}, rowid, titulo, justificacion, etiquetas_semanticas, orador)
            VALUES ('delete', old.id, old.titulo, old.justificacion, old.etiquetas_semanticas, old.orador);
            INSERT INTO {$ftsTable}(rowid, titulo, justificacion, etiquetas_semanticas, orador)
            VALUES (new.id, new.titulo, new.justificacion, new.etiquetas_semanticas, new.orador);
        END");

        // 7. Usuario administrador semilla para acceso inmediato
        $exists = $this->db->table('usuarios')->where('correo', 'admin@transcripciones.local')->countAllResults();
        if ($exists === 0) {
            $this->db->table('usuarios')->insert([
                'nombre'          => 'Administrador Demo',
                'correo'          => 'admin@transcripciones.local',
                'clave_hash'      => password_hash('Admin123*', PASSWORD_DEFAULT),
                'google_id'       => null,
                'foto_perfil_url' => null,
                'creado_en'       => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $trigInsert = $this->db->prefixTable('trg_decisiones_ai');
        $trigDelete = $this->db->prefixTable('trg_decisiones_ad');
        $trigUpdate = $this->db->prefixTable('trg_decisiones_au');
        $ftsTable   = $this->db->prefixTable('decisiones_tecnicas_fts');

        $this->db->query("DROP TRIGGER IF EXISTS {$trigInsert}");
        $this->db->query("DROP TRIGGER IF EXISTS {$trigDelete}");
        $this->db->query("DROP TRIGGER IF EXISTS {$trigUpdate}");
        $this->db->query("DROP TABLE IF EXISTS {$ftsTable}");

        $this->forge->dropTable('decisiones_tecnicas', true);
        $this->forge->dropTable('minutas_tareas', true);
        $this->forge->dropTable('intervenciones_transcripcion', true);
        $this->forge->dropTable('reuniones', true);
        $this->forge->dropTable('usuarios', true);
    }
}
