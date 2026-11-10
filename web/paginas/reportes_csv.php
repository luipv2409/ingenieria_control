<?php
/**
 * Exportación CSV (respuesta limpia, sin HTML del panel).
 * index.php?p=reportes&exp=csv&tipo=<lecturas|alarmas|inventario|puertas|energia>
 */

$tipo = $_GET['tipo'] ?? 'lecturas';

$definiciones = [
    'lecturas' => [
        'archivo'  => 'lecturas_punata_vacuna_segura.csv',
        'columnas' => ['fecha_hora', 'sensor', 'nodo', 'institucion', 'valor_c', 'calidad_dato'],
        'sql' => "SELECT l.fecha_hora, s.numero_serie AS sensor, d.codigo AS nodo,
                         i.nombre AS institucion, l.valor, l.calidad_dato
                  FROM lectura_sensor l
                  JOIN sensor s ON s.id_sensor = l.id_sensor
                  JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
                  JOIN institucion_salud i ON i.id_institucion = d.id_institucion
                  WHERE l.fecha_hora >= NOW() - INTERVAL 72 HOUR AND s.id_tipo_sensor = 1
                  ORDER BY l.fecha_hora DESC",
    ],
    'alarmas' => [
        'archivo'  => 'alarmas_punata_vacuna_segura.csv',
        'columnas' => ['id_alarma', 'fecha_hora', 'tipo', 'nivel', 'estado', 'dispositivo',
                       'institucion', 'descripcion', 'acciones_registradas'],
        'sql' => "SELECT a.id_alarma, a.fecha_hora, t.nombre AS tipo, a.nivel, e.nombre AS estado,
                         d.codigo AS dispositivo, i.nombre AS institucion, a.descripcion,
                         (SELECT COUNT(*) FROM accion_respuesta ar WHERE ar.id_alarma = a.id_alarma) AS acciones_registradas
                  FROM alarma a
                  JOIN tipo_alarma t ON t.id_tipo_alarma = a.id_tipo_alarma
                  JOIN estado_alarma e ON e.id_estado_alarma = a.id_estado_alarma
                  JOIN dispositivo d ON d.id_dispositivo = a.id_dispositivo
                  JOIN institucion_salud i ON i.id_institucion = d.id_institucion
                  ORDER BY a.fecha_hora DESC",
    ],
    'inventario' => [
        'archivo'  => 'inventario_vacunas_punata_vacuna_segura.csv',
        'columnas' => ['codigo_lote', 'vacuna', 'fabricante', 'cantidad_inicial', 'stock', 'fecha_vencimiento', 'estado'],
        'sql' => "SELECT lv.codigo_lote, v.nombre AS vacuna, v.fabricante, lv.cantidad_inicial,
                         lv.cantidad_inicial + COALESCE((
                           SELECT SUM(CASE WHEN m.tipo_movimiento IN ('INGRESO','TRASLADO_ENTRADA','DEVOLUCION')
                                           THEN m.cantidad ELSE -m.cantidad END)
                           FROM movimiento_vacuna m WHERE m.id_lote = lv.id_lote), 0) AS stock,
                         lv.fecha_vencimiento, lv.estado
                  FROM lote_vacuna lv JOIN vacuna v ON v.id_vacuna = lv.id_vacuna
                  ORDER BY lv.fecha_vencimiento",
    ],
    'puertas' => [
        'archivo'  => 'eventos_puerta_punata_vacuna_segura.csv',
        'columnas' => ['id_evento_puerta', 'sensor', 'nodo', 'institucion', 'fecha_inicio', 'fecha_fin',
                       'duracion_segundos', 'estado'],
        'sql' => "SELECT ep.id_evento_puerta, s.numero_serie AS sensor, d.codigo AS nodo,
                         i.nombre AS institucion, ep.fecha_inicio, ep.fecha_fin, ep.duracion_segundos, ep.estado
                  FROM evento_puerta ep
                  JOIN sensor s ON s.id_sensor = ep.id_sensor
                  JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
                  JOIN institucion_salud i ON i.id_institucion = d.id_institucion
                  ORDER BY ep.fecha_inicio DESC",
    ],
    'energia' => [
        'archivo'  => 'estado_energia_punata_vacuna_segura.csv',
        'columnas' => ['id_estado_energia', 'nodo', 'institucion', 'fecha_hora', 'estado', 'voltaje'],
        'sql' => "SELECT ee.id_estado_energia, d.codigo AS nodo, i.nombre AS institucion,
                         ee.fecha_hora, ee.estado, ee.voltaje
                  FROM estado_energia ee
                  JOIN dispositivo d ON d.id_dispositivo = ee.id_dispositivo
                  JOIN institucion_salud i ON i.id_institucion = d.id_institucion
                  ORDER BY ee.fecha_hora DESC",
    ],
];

if (!isset($definiciones[$tipo])) {
    http_response_code(400);
    echo 'Tipo de exportación no válido.';
    exit;
}

$def = $definiciones[$tipo];
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $def['archivo'] . '"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8
fputcsv($out, $def['columnas']);
foreach (q($def['sql'])->fetchAll() as $r) {
    fputcsv($out, array_values($r));
}
fclose($out);
exit;
