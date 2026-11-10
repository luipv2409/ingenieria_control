<?php
/**
 * Historial de lecturas de sensores con filtros y gráfica
 */
$sensores = q("
    SELECT s.id_sensor, s.numero_serie, t.nombre AS tipo, d.codigo, i.nombre AS institucion
    FROM sensor s
    JOIN tipo_sensor t ON t.id_tipo_sensor = s.id_tipo_sensor
    JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    ORDER BY i.nombre, t.nombre, s.numero_serie")->fetchAll();

$fSensor = (int)($_GET['sensor'] ?? 0);
$fHoras  = (int)($_GET['horas'] ?? 24);
if (!in_array($fHoras, [1, 6, 12, 24, 72, 168], true)) $fHoras = 24;

$where = "l.fecha_hora >= NOW() - INTERVAL {$fHoras} HOUR";
$params = [];
if ($fSensor > 0) {
    $where .= " AND l.id_sensor = ?";
    $params[] = $fSensor;
}

$filas = q("
    SELECT l.fecha_hora, l.valor, l.calidad_dato, s.numero_serie, t.nombre AS tipo,
           d.codigo AS nodo, i.nombre AS institucion
    FROM lectura_sensor l
    JOIN sensor s ON s.id_sensor = l.id_sensor
    JOIN tipo_sensor t ON t.id_tipo_sensor = s.id_tipo_sensor
    JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    WHERE {$where}
    ORDER BY l.fecha_hora DESC
    LIMIT 400", $params)->fetchAll();

$total = (int)valor("SELECT COUNT(*) FROM lectura_sensor l WHERE {$where}", $params);

$min = $filas ? min(array_column($filas, 'valor')) : null;
$max = $filas ? max(array_column($filas, 'valor')) : null;

echo '<div class="panel">
  <form method="get" action="index.php">
    <input type="hidden" name="p" value="lecturas">
    <div class="fila">
      <div class="campo"><label>Sensor</label>
        <select name="sensor">
          <option value="0">Todos los sensores</option>';
foreach ($sensores as $s) {
    $sel = $fSensor === (int)$s['id_sensor'] ? ' selected' : '';
    $et = $s['tipo'] . ' · ' . $s['institucion'] . ' · ' . $s['codigo'];
    echo '<option value="' . (int)$s['id_sensor'] . '"' . $sel . '>' . e($et) . '</option>';
}
echo '  </select></div>
      <div class="campo"><label>Rango</label>
        <select name="horas">';
foreach ([1 => 'Última hora', 6 => '6 horas', 12 => '12 horas', 24 => '24 horas', 72 => '3 días', 168 => '7 días'] as $h => $t) {
    echo '<option value="' . $h . '"' . ($fHoras === $h ? ' selected' : '') . '>' . $t . '</option>';
}
echo '</select></div>
      <div class="campo" style="justify-content:flex-end"><label>&nbsp;</label>
        <button class="boton" type="submit">Aplicar filtros</button></div>
    </div>
  </form>
</div>';

// Gráfica: serie promedio por institución cada 15 min (o por sensor si se filtra)
$serieFilas = q("
    SELECT DATE_FORMAT(l.fecha_hora, '%d/%m %H:%i') etiqueta, i.nombre AS serie, ROUND(AVG(l.valor),2) v
    FROM lectura_sensor l
    JOIN sensor s ON s.id_sensor = l.id_sensor AND s.id_tipo_sensor = 1
    JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    WHERE {$where} AND s.id_tipo_sensor = 1
    GROUP BY DATE_FORMAT(l.fecha_hora, '%d/%m %H:%i'), i.nombre, i.id_institucion
    ORDER BY MIN(l.fecha_hora)", $params)->fetchAll();

$porFecha = [];
$etiquetas = [];
foreach ($serieFilas as $r) {
    $etiquetas[$r['etiqueta']] = 1;
    $porFecha[$r['serie']][$r['etiqueta']] = (float)$r['v'];
}
$etiquetas = array_keys($etiquetas);
// si hay demasiados puntos, reducir
if (count($etiquetas) > 120) {
    $etiquetas = array_values(array_filter($etiquetas, fn($k, $i) => $i % (int)ceil(count($etiquetas) / 120) === 0, ARRAY_FILTER_USE_BOTH));
}
$colores = ['#2e75b6', '#d64545', '#2e9e5b', '#e8a13a'];
$datosSeries = [];
$ci = 0;
foreach ($porFecha as $nombre => $vals) {
    $datosSeries[] = [
        'nombre' => $nombre,
        'color'  => $colores[$ci % count($colores)],
        'data'   => array_map(fn($k) => $vals[$k] ?? null, $etiquetas),
    ];
    $ci++;
}

echo '<div class="panel"><h2>Temperatura registrada (' . $fHoras . ' h) · ' . number_format($total) . ' lecturas</h2>
        <div class="canvas-wrap"><canvas id="grafLec" class="grafico"></canvas></div>
        <div class="leyenda" id="leyendaLec"></div>';
if ($min !== null) {
    echo '<div class="pie-mapa">Mínimo observado: <strong>' . number_format((float)$min, 2)
        . ' °C</strong> · Máximo: <strong>' . number_format((float)$max, 2)
        . ' °C</strong> · Banda verde: 2 °C a 8 °C.</div>';
}
echo '</div>';

echo '<div class="panel"><h2>Últimas lecturas</h2>';
if (!$filas) {
    echo '<p>No hay lecturas registradas para el periodo seleccionado. Encienda el nodo ESP32 para recibir telemetría en tiempo real.</p>';
} else {
    echo '<table class="tabla"><tr><th>Fecha y hora</th><th>Sensor</th><th>Nodo</th><th>Institución</th>'
       . '<th class="num">Valor (°C)</th><th>Estado</th></tr>';
    foreach (array_slice($filas, 0, 150) as $r) {
        $v = (float)$r['valor'];
        $cls = ($v < 2 || $v > 8) ? 'st-rojo' : (($v < 3 || $v > 7) ? 'st-ambar' : 'st-verde');
        echo '<tr><td>' . e($r['fecha_hora']) . '</td><td>' . e($r['numero_serie']) . '</td>
              <td>' . e($r['nodo']) . '</td><td>' . e($r['institucion']) . '</td>
              <td class="num"><span class="etiqueta-estado ' . $cls . '">' . number_format($v, 2) . '</span></td>
              <td>' . e($r['calidad_dato']) . '</td></tr>';
    }
    echo '</table><p class="pie-mapa">Mostrando hasta 150 de ' . number_format($total) . ' lecturas del periodo.</p>';
}
echo '</div>';

$seriesJson = json_encode($datosSeries);
$labelsJson = json_encode($etiquetas);
$scripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function(){
  var series = {$seriesJson}, labels = {$labelsJson};
  dibujarGrafico('grafLec', {etiquetas: labels, series: series, yMin: 0, yMax: 12, unidad: '°C', zonaSegura: [2,8]});
  pintarLeyenda('leyendaLec', series);
});
window.addEventListener('resize', function(){
  var series = {$seriesJson}, labels = {$labelsJson};
  dibujarGrafico('grafLec', {etiquetas: labels, series: series, yMin: 0, yMax: 12, unidad: '°C', zonaSegura: [2,8]});
});
</script>
HTML;
