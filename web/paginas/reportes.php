<?php
/**
 * Reportes e indicadores del piloto
 */

echo '<div class="tarjetas">';
// 1. Cobertura de registro (24h)
$lecturas24 = (int)valor("SELECT COUNT(*) FROM lectura_sensor WHERE fecha_hora >= NOW() - INTERVAL 24 HOUR");
$sensoresT = (int)valor("SELECT COUNT(*) FROM sensor WHERE id_tipo_sensor = 1 AND estado = 'ACTIVO'");
$esperadas = $sensoresT * 24 * 12;
$cobertura = $esperadas ? round($lecturas24 * 100 / $esperadas, 1) : 0;
echo '<div class="tarjeta ' . ($cobertura >= 95 ? 'verde' : 'ambar') . '">
        <div class="etiqueta">Cobertura de registro 24 h</div>
        <div class="valor">' . $cobertura . '%</div>
        <div class="detalle">' . number_format($lecturas24) . ' / ' . number_format($esperadas) . ' lecturas · meta ≥ 95%</div>
      </div>';

// 2. Disponibilidad
$disp = $esperadas ? round($lecturas24 * 100 / $esperadas, 1) : 0;
echo '<div class="tarjeta ' . ($disp >= 95 ? 'verde' : 'ambar') . '">
        <div class="etiqueta">Disponibilidad del sistema</div>
        <div class="valor">' . $disp . '%</div>
        <div class="detalle">Aproximación por lecturas recibidas · meta ≥ 95%</div>
      </div>';

// 3. Tiempo de notificación promedio
$tNotif = q("
    SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, a.fecha_hora, n.fecha_envio)),1) min,
           COUNT(*) n
    FROM notificacion n JOIN alarma a ON a.id_alarma = n.id_alarma")->fetch();
$tNotifMin = $tNotif['min'];
echo '<div class="tarjeta ' . (($tNotifMin !== null && $tNotifMin <= 2) ? 'verde' : 'ambar') . '">
        <div class="etiqueta">Tiempo de notificación</div>
        <div class="valor">' . ($tNotifMin !== null ? number_format((float)$tNotifMin, 1) : '—') . ' min</div>
        <div class="detalle">Promedio de ' . (int)$tNotif['n'] . ' notificaciones · meta ≤ 2 min</div>
      </div>';

// 4. Falsas alarmas
$totAlarmas = (int)valor("SELECT COUNT(*) FROM alarma");
$falsas = (int)valor("
    SELECT COUNT(*) FROM alarma a
    JOIN estado_alarma e ON e.id_estado_alarma = a.id_estado_alarma
    WHERE e.nombre = 'FALSA_ALARMA'");
$pctFalsas = $totAlarmas ? round($falsas * 100 / $totAlarmas, 1) : 0;
echo '<div class="tarjeta ' . ($pctFalsas <= 10 ? 'verde' : 'rojo') . '">
        <div class="etiqueta">Falsas alarmas</div>
        <div class="valor">' . $pctFalsas . '%</div>
        <div class="detalle">' . $falsas . ' de ' . $totAlarmas . ' · meta ≤ 10%</div>
      </div>';
echo '</div>';

// Trazabilidad
$conAccion = (int)valor("SELECT COUNT(DISTINCT id_alarma) FROM accion_respuesta");
$traz = $totAlarmas ? round($conAccion * 100 / $totAlarmas, 1) : 0;
echo '<div class="panel"><h2>Indicadores de trazabilidad y detección</h2>
      <div class="tarjetas" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">';

$tResp = q("
    SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, a.fecha_hora, ar.fecha_hora)),1) min
    FROM accion_respuesta ar JOIN alarma a ON a.id_alarma = ar.id_alarma WHERE ar.fecha_hora >= a.fecha_hora")->fetch();
echo '<div class="tarjeta verde"><div class="etiqueta">Trazabilidad de eventos</div>
        <div class="valor">' . $traz . '%</div>
        <div class="detalle">Alarmas con acción registrada (' . $conAccion . '/' . $totAlarmas . ') · meta ≥ 90%</div></div>';

echo '<div class="tarjeta ambar"><div class="etiqueta">Tiempo de respuesta personal</div>
        <div class="valor">' . ($tResp['min'] !== null ? number_format((float)$tResp['min'], 1) : '—') . ' min</div>
        <div class="detalle">Desde generación de alarma hasta registro de acción</div></div>';

$noValidas = (int)valor("SELECT COUNT(*) FROM lectura_sensor WHERE calidad_dato != 'VALIDO'");
echo '<div class="tarjeta ambar"><div class="etiqueta">Lecturas no válidas</div>
        <div class="valor">' . number_format($noValidas) . '</div>
        <div class="detalle">Datos marcados para revisión / simulación</div></div>';

echo '<div class="tarjeta"><div class="etiqueta">Eventos de puerta</div>
        <div class="valor">' . (int)valor("SELECT COUNT(*) FROM evento_puerta") . '</div>
        <div class="detalle">Registrados por los sensores reed switch</div></div>';
echo '</div></div>';

// Alarmas por tipo
echo '<div class="panel"><h2>Distribución de alarmas por tipo</h2>
      <table class="tabla"><tr><th>Tipo de alarma</th><th class="num">Cantidad</th><th class="num">%</th></tr>';
foreach (q("
    SELECT t.nombre, COUNT(a.id_alarma) c
    FROM tipo_alarma t LEFT JOIN alarma a ON a.id_tipo_alarma = t.id_tipo_alarma
    GROUP BY t.id_tipo_alarma, t.nombre ORDER BY c DESC")->fetchAll() as $r) {
    $p = $totAlarmas ? round($r['c'] * 100 / $totAlarmas, 1) : 0;
    echo '<tr><td>' . e(str_replace('_', ' ', $r['nombre'])) . '</td><td class="num">' . (int)$r['c'] . '</td>'
       . '<td class="num">' . $p . '%</td></tr>';
}
echo '</table></div>';

// Exportar
$cs = csrf_token();
echo '<div class="panel"><h2>Exportar datos (CSV)</h2>
      <div class="botones">
        <a class="boton verde" href="index.php?p=reportes&amp;exp=csv&amp;tipo=lecturas&amp;cs=' . $cs . '">⬇ Lecturas (72 h)</a>
        <a class="boton verde" href="index.php?p=reportes&amp;exp=csv&amp;tipo=alarmas&amp;cs=' . $cs . '">⬇ Alarmas</a>
        <a class="boton verde" href="index.php?p=reportes&amp;exp=csv&amp;tipo=inventario&amp;cs=' . $cs . '">⬇ Inventario de vacunas</a>
        <a class="boton verde" href="index.php?p=reportes&amp;exp=csv&amp;tipo=puertas&amp;cs=' . $cs . '">⬇ Eventos de puerta</a>
        <a class="boton verde" href="index.php?p=reportes&amp;exp=csv&amp;tipo=energia&amp;cs=' . $cs . '">⬇ Estado de energía</a>
      </div>
      <p class="pie-mapa">Los archivos CSV incluyen BOM UTF-8 para su apertura directa en Excel. '
      . 'Las metas son objetivos de diseño de la propuesta (primer informe, sección f.3). Solo podrán '
      . 'declararse como resultados alcanzados después de ejecutar las pruebas de validación con el simulador.</p>
      </div>';