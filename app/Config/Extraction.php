<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Diccionario lingüístico y heurísticas de extracción en español.
 *
 * Esta clase centraliza las frases literales (sin expresiones regulares complejas
 * que puedan causar catastrophic backtracking) para clasificar intervenciones
 * de una transcripción .txt en una sola pasada O(n):
 *  1. Ruido automático de herramientas de transcripción ($noise)
 *  2. Tareas y compromisos accionables ($taskMarkers)
 *  3. Decisiones técnicas y de arquitectura/flujo ($decisionMarkers)
 *  4. Bloqueos, limitaciones y riesgos operativos ($blockerMarkers)
 *  5. Sinónimos y conceptos relacionados para el Buscador Semántico ($semanticSynonyms)
 *
 * El backend (TranscriptAnalyzer / DecisionModel) compila estos arreglos usando
 * preg_quote() en una única expresión regular segura o búsqueda de subcadenas.
 */
class Extraction extends BaseConfig
{
    /**
     * Patrones de ruido automático generados por extensiones de grabación.
     * Justificación en formato-real.txt:
     * - Línea 23 (00:29): "Hola, estoy transcribiendo esta llamada con mi extensión Tactiq AI. https://tactiq.io/r/transcribing"
     *
     * @var array<int, string>
     */
    public array $noise = [
        'estoy transcribiendo esta llamada con mi extensión Tactiq AI',
        'https://tactiq.io/r/transcribing',
        'transcribed by tactiq',
    ];

    /**
     * Marcadores literales de tareas, compromisos y acciones pendientes.
     * Justificación directa en formato-real.txt:
     * - 00:32: "voy a exponer, este se va a decir que todavía la verificación..."
     * - 01:39: "tendrían que crear la plantilla y pasársela"
     * - 02:12: "hay que tener a necesitar la plantilla"
     * - 03:13: "lo que vamos a hacer hoy es a discutir esas limitaciones"
     * - 05:21: "habría que crear una cómo se llama... una inscripción que calculada de una de un bolívar"
     * - 05:33: "José Daniel presentaría lo que es esa verificación por WhatsApp"
     *
     * @var array<int, string>
     */
    public array $taskMarkers = [
        'tendrían que crear',
        'habría que crear',
        'voy a exponer',
        'lo que vamos a hacer',
        'presentaría',
        'hay que tener',
        'necesita la plantilla',
        'correr alguna rutina',
        'queda pendiente',
        'vamos a colocar',
        'nos tocaría a nosotros hacer',
        'pasársela',
        'explicárselo',
    ];

    /**
     * Marcadores literales de decisiones técnicas, diseño de flujo y arquitectura.
     * Justificación directa en formato-real.txt:
     * - 00:32: "dos opciones para implementar por High Level"
     * - 01:12: "bajo un flujo de trabajo que se hace desde el High Level"
     * - 02:15: "aceptar los términos y condiciones... en el formulario o entre las materias y el pago"
     * - 02:50: "lo más ideal es que sean cualquiera de los pasos, pero que esté antes del pago"
     * - 05:01: "presentamos la forma de pago por Cele"
     * - 05:28: "una inscripción que calculada de una de un bolívar"
     *
     * @var array<int, string>
     */
    public array $decisionMarkers = [
        'dos opciones para implementar',
        'flujo de trabajo',
        'lo más ideal es que',
        'antes del pago',
        'términos y condiciones',
        'forma de pago',
        'inscripción que calculada',
        'en el formulario o entre las materias',
        'manejar por',
        'verificación por WhatsApp',
    ];

    /**
     * Marcadores literales de bloqueos técnicos, limitantes y riesgos de datos.
     * Justificación directa en formato-real.txt:
     * - 00:32: "la verificación de este WhatsApp No se ha implementado"
     * - 01:12: "dependen de una plantilla para que metanobloquee el mensaje"
     * - 01:39: "los dos caminos conducen a lo mismo a una limitante"
     * - 03:13: "discutir esas limitaciones que se han tenido"
     * - 05:33: "van a quedar como que Hay registros ahí basuras"
     * - 06:40: "No va a tener un campo de Estados o como que verificado o rechazado"
     *
     * @var array<int, string>
     */
    public array $blockerMarkers = [
        'no se ha implementado',
        'dependen de una plantilla',
        'metanobloquee',
        'conducen a lo mismo a una limitante',
        'esas limitaciones',
        'registros ahí basuras',
        'no va a tener un campo de estados',
        'no dio chance',
        'estoy sin luz',
    ];

    /**
     * Mapa de expansión semántica del dominio técnico en español.
     * Permite que el Buscador Semántico de Decisiones Técnicas (SQLite FTS5) encuentre acuerdos
     * aunque el usuario consulte por concepto general y no por la palabra literal de la llamada.
     *
     * Justificación directa en formato-real.txt:
     * - whatsapp: verificación pendiente de número y bloqueo de mensajes por Meta (00:32, 01:12, 05:33)
     * - plantilla: requisito en High Level para habilitar envíos sin bloqueo (01:12, 01:39, 02:12)
     * - highlevel: alternativas por mini Coin / Point o flujo de trabajo (00:32, 01:12, 01:39)
     * - pago: cobro por Cele, inscripción calculada de un bolívar y descuento (02:50, 03:13, 05:15, 05:28)
     * - terminos: aceptación de condiciones en formulario o antes del pago (02:15, 02:50, 03:13)
     * - datos: ausencia de campo de estados y persistencia de contactos sin verificar (05:33, 06:40)
     * - limpieza: rutina para depurar registros basura o contactos no verificados (05:33, 06:40)
     * - bloqueo: limitantes del flujo, falta de implementación y corte eléctrico (00:32, 01:39, 03:13, 06:40)
     * - arquitectura: diseño del flujo de trabajo, orden de pasos y modelo de estados (00:32, 01:12, 02:50, 06:40)
     *
     * @var array<string, array<int, string>>
     */
    public array $semanticSynonyms = [
        'whatsapp'     => ['whatsapp', 'plantilla', 'meta', 'metanobloquee', 'high level', 'verificación', 'mensaje'],
        'plantilla'    => ['plantilla', 'whatsapp', 'high level', 'flujo de trabajo', 'limitante'],
        'highlevel'    => ['high level', 'flujo de trabajo', 'point', 'coin', 'plantilla', 'whatsapp'],
        'pago'         => ['pago', 'cele', 'bolívar', 'deuda', 'inscripción', 'descuento', 'antes del pago', 'términos'],
        'terminos'     => ['términos', 'condiciones', 'antes del pago', 'formulario', 'materias'],
        'datos'        => ['campo', 'estados', 'registros', 'basuras', 'verificado', 'rechazado', 'rutina', 'contactos'],
        'limpieza'     => ['rutina', 'borre', 'registros', 'basuras', 'verificado', 'rechazado', 'campo de estados'],
        'bloqueo'      => ['limitante', 'limitaciones', 'no se ha implementado', 'basuras', 'sin luz'],
        'arquitectura' => ['flujo de trabajo', 'high level', 'opciones para implementar', 'campo de estados', 'antes del pago'],
    ];
}
