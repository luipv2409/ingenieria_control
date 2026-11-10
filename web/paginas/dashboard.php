<?php
/**
 * Panel general — KPIs, gráfica de temperatura y alarmas recientes
 */

// Última lectura de temperatura por institución
$actuales = q("
    SELECT i.id_institucion, i.nombre, ROUND(AVG(l.valor),2) AS temp, MAX(l.fecha_hora) AS fecha
    FROM lectura_sensor l
    JOIN sensor s       ON s.id_sensor = l.id_sensor AND s.id_tipo_sensor = 1
    JOIN dispositivo d  ON d.id_dispositivo = s.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    WHERE l.id_lectura IN (SELECT MAX(id_lectura) FROM lectura_sensor GROUP BY id_sensor)
    GROUP BY i.id_institucion, i.nombre
    ORDER BY i.nombre")->fetchAll();

$alarmasActivas = (int)valor("
    SELECT COUNT(*) FROM alarma a
    WHERE a.id_estado_alarma IN (SELECT id_estado_alarma FROM estado_alarma WHERE nombre IN ('ACTIVA','NOTIFICADA'))");

$dispositivosActivos = (int)valor("SELECT COUNT(*) FROM dispositivo WHERE estado = 'ACTIVO'");
$totalDispositivos = (int)valor("SELECT COUNT(*) FROM dispositivo");

$lecturas24 = (int)valor("SELECT COUNT(*) FROM lectura_sensor WHERE fecha_hora >= NOW() - INTERVAL 24 HOUR");

$esperadas24 = (int)valor("
    SELECT COUNT(*) * 24 * 12
    FROM sensor s WHERE s.id_tipo_sensor = 1 AND s.estado = 'ACTIVO'");
$cobertura = $esperadas24 > 0 ? round($lecturas24 * 100 / $esperadas24, 1) : 0;

// Alarmas recientes
$alarmas = q("
    SELECT a.id_alarma, a.fecha_hora, a.nivel, a.descripcion,
           t.nombre AS tipo, e.nombre AS estado, d.codigo AS dispositivo
    FROM alarma a
    JOIN tipo_alarma t    ON t.id_tipo_alarma = a.id_tipo_alarma
    JOIN estado_alarma e  ON e.id_estado_alarma = a.id_estado_alarma
    JOIN dispositivo d    ON d.id_dispositivo = a.id_dispositivo
    ORDER BY a.fecha_hora DESC LIMIT 8")->fetchAll();

// Último estado de energía por dispositivo
$energia = q("
    SELECT d.codigo, e.estado, e.voltaje, e.fecha_hora
    FROM estado_energia e
    JOIN dispositivo d ON d.id_dispositivo = e.id_dispositivo
    WHERE e.id_estado_energia IN (SELECT MAX(id_estado_energia) FROM estado_energia GROUP BY id_dispositivo)
    ORDER BY d.codigo")->fetchAll();

// Puertas: eventos abiertos hoy
$puertasHoy = (int)valor("
    SELECT COUNT(*) FROM evento_puerta WHERE fecha_inicio >= CURDATE() AND estado = 'ABIERTA'");

// ---- Tarjetas ----
echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <div style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#2e7d32; font-weight:600;">
          <span style="width:10px; height:10px; border-radius:50%; background:#2e7d32; display:inline-block; box-shadow:0 0 6px #2e7d32;"></span>
          Monitoreo en Tiempo Real (Actualización automática)
        </div>
        <small id="txtUltimaSync" style="color:#6b7a8c;">Última sinc: ' . date('H:i:s') . '</small>
      </div>';
echo '<div class="tarjetas" id="contenedorTarjetas">';
foreach ($actuales as $a) {
    $t = (float)$a['temp'];
    $cls = ($t < 2.0 || $t > 8.0) ? 'rojo' : (($t < 3.0 || $t > 7.0) ? 'ambar' : 'verde');
    $corto = stripos($a['nombre'], 'La Capilla') !== false ? 'La Capilla (remoto)' : 'Hospital Punata (coordinador)';
    $slug = 'inst_' . (int)$a['id_institucion'];
    $segInactivo = !empty($a['fecha']) ? (time() - strtotime($a['fecha'])) : 999999;
    $msgConn = ($segInactivo <= 20) ? '● En línea' : (($segInactivo <= 60) ? '⚠ Demora de red' : '✖ Sin comunicación');
    $colorConn = ($segInactivo <= 20) ? '#16a34a' : (($segInactivo <= 60) ? '#d97706' : '#dc2626');
    echo '<div class="tarjeta ' . $cls . '" id="card_' . $slug . '">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span class="etiqueta">' . e($corto) . '</span>
              <span id="badge_conn_' . $slug . '" style="font-size:11px; font-weight:bold; color:' . $colorConn . ';">' . $msgConn . '</span>
            </div>
            <div class="valor" id="val_' . $slug . '">' . number_format($t, 2) . ' °C</div>
            <div class="detalle" id="det_' . $slug . '">Última lectura: ' . e($a['fecha']) . '</div>
          </div>';
}

$stP = q("SELECT estado, fecha_inicio FROM evento_puerta ORDER BY id_evento_puerta DESC LIMIT 1")->fetch();
$puertaAbiertaInicial = $stP && $stP['estado'] === 'ABIERTA';
echo '<div class="tarjeta ' . ($puertaAbiertaInicial ? 'rojo' : 'verde') . '" id="cardPuerta">
        <div class="etiqueta">Puerta Refrigerador (MC-38)</div>
        <div class="valor" id="valPuerta">' . ($puertaAbiertaInicial ? 'ABIERTA' : 'CERRADA') . '</div>
        <div class="detalle" id="detPuerta">Sensor magnético Pin 5 · ' . ($stP ? e(substr($stP['fecha_inicio'], 11)) : 'Normal') . '</div>
      </div>';

echo '<div class="tarjeta ' . ($alarmasActivas > 0 ? 'rojo' : 'verde') . '" id="cardAlarmas">
        <div class="etiqueta">Alarmas activas</div>
        <div class="valor" id="valAlarmas">' . $alarmasActivas . '</div>
        <div class="detalle">Requieren atención del personal</div>
      </div>';
echo '<div class="tarjeta verde">
        <div class="etiqueta">Dispositivos</div>
        <div class="valor" id="valDispositivos">' . $dispositivosActivos . '/' . $totalDispositivos . '</div>
        <div class="detalle">Nodos ESP32 operativos</div>
      </div>';
echo '<div class="tarjeta ' . ($cobertura >= 95 ? 'verde' : 'ambar') . '">
        <div class="etiqueta">Cobertura 24 h</div>
        <div class="valor">' . $cobertura . '%</div>
        <div class="detalle">Meta ≥ 95 % · ' . number_format($lecturas24) . ' lecturas recibidas</div>
      </div>';
echo '</div>';

// ---- Gráfica de temperatura ----
$filas = q("
    SELECT DATE(fecha_hora) f, HOUR(fecha_hora) h, i.id_institucion, i.nombre,
           ROUND(AVG(l.valor),2) v
    FROM lectura_sensor l
    JOIN sensor s ON s.id_sensor = l.id_sensor AND s.id_tipo_sensor = 1
    JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    WHERE l.fecha_hora >= NOW() - INTERVAL 24 HOUR
    GROUP BY f, h, i.id_institucion, i.nombre
    ORDER BY f, h, i.id_institucion")->fetchAll();

$etiquetas = [];
$series = [];
foreach ($filas as $r) {
    $k = $r['f'] . ' ' . str_pad($r['h'], 2, '0', STR_PAD_LEFT) . ':00';
    $etiquetas[$k] = substr($k, 11);
    $series[$r['nombre']][$k] = (float)$r['v'];
}
$etiquetas = array_keys($etiquetas);
$colores = ['#2e75b6', '#d64545'];
$datosSeries = [];
$i = 0;
foreach ($series as $nombre => $vals) {
    $datosSeries[] = [
        'nombre' => $nombre,
        'color'  => $colores[$i % count($colores)],
        'data'   => array_map(fn($k) => $vals[$k] ?? null, $etiquetas),
    ];
    $i++;
}

echo '<div class="dos-columnas">';
echo '<div class="panel"><h2>Temperatura promedio horaria — últimas 24 horas</h2>
        <div class="canvas-wrap"><canvas id="grafTemp" class="grafico"></canvas></div>
        <div class="leyenda" id="leyendaTemp"></div>
        <div class="pie-mapa">Banda verde: rango autorizado 2 °C a 8 °C (OPS/PAI).</div>
      </div>';

echo '<div class="panel"><h2>Estado de energía (último reporte)</h2>
      <table class="tabla"><tr><th>Nodo</th><th>Estado</th><th class="num">Voltaje</th></tr>';
foreach ($energia as $en) {
    $ok = stripos($en['estado'], 'PRESENTE') !== false;
    echo '<tr><td>' . e($en['codigo']) . '</td>
          <td><span class="etiqueta-estado ' . ($ok ? 'st-verde' : 'st-rojo') . '">' . e($en['estado']) . '</span></td>
          <td class="num">' . ($en['voltaje'] !== null ? number_format((float)$en['voltaje'], 1) . ' V' : '—') . '</td></tr>';
}
echo '<tr><td>Puertas abiertas hoy</td><td colspan="2"><strong>' . $puertasHoy . '</strong></td></tr>';
echo '</table></div></div>';

// ---- Alarmas recientes ----
echo '<div class="panel"><h2>Alarmas recientes</h2>';
if (!$alarmas) {
    echo '<p>No hay alarmas registradas.</p>';
} else {
    echo '<table class="tabla"><tr><th>Fecha y hora</th><th>Tipo</th><th>Nivel</th><th>Dispositivo</th><th>Descripción</th><th>Estado</th></tr>';
    foreach ($alarmas as $a) {
        $nivelCls = $a['nivel'] === 'CRITICA' ? 'st-rojo' : 'st-ambar';
        $estCls = $a['estado'] === 'RESUELTA' ? 'st-verde' : ($a['estado'] === 'FALSA_ALARMA' ? 'st-gris' : 'st-ambar');
        echo '<tr>
                <td>' . e($a['fecha_hora']) . '</td>
                <td>' . e(str_replace('_', ' ', $a['tipo'])) . '</td>
                <td><span class="etiqueta-estado ' . $nivelCls . '">' . e($a['nivel']) . '</span></td>
                <td>' . e($a['dispositivo']) . '</td>
                <td>' . e($a['descripcion']) . '</td>
                <td><span class="etiqueta-estado ' . $estCls . '">' . e($a['estado']) . '</span></td>
              </tr>';
    }
    echo '</table>';
}
echo '<div class="botones"><a class="boton" href="index.php?p=alarmas">Gestionar alarmas</a></div></div>';

$seriesJson = json_encode($datosSeries);
$labelsJson = json_encode($etiquetas);
$scripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function(){
  var series = {$seriesJson};
  var labels = {$labelsJson};
  dibujarGrafico('grafTemp', {etiquetas: labels, series: series, yMin: 0, yMax: 12, unidad: '°C', zonaSegura: [2,8]});
  pintarLeyenda('leyendaTemp', series);

  // Auto-actualización en vivo cada 2 segundos
  setInterval(function(){
    fetch('api/en_vivo.php')
      .then(function(res){ return res.json(); })
      .then(function(data){
        if (!data || !data.ok) return;
        var syncEl = document.getElementById('txtUltimaSync');
        if (syncEl) syncEl.textContent = 'Última sinc: ' + (data.timestamp ? data.timestamp.substr(11) : '');

        if (Array.isArray(data.actuales)) {
          data.actuales.forEach(function(a){
            var id = a.id_institucion;
            var valEl = document.getElementById('val_inst_' + id);
            var detEl = document.getElementById('det_inst_' + id);
            var cardEl = document.getElementById('card_inst_' + id);
            var badgeEl = document.getElementById('badge_conn_inst_' + id);

            if (valEl) valEl.textContent = Number(a.temp).toFixed(2) + ' °C';
            if (detEl) detEl.textContent = 'Última lectura: ' + a.fecha;
            if (badgeEl && a.mensaje_conexion) {
              badgeEl.textContent = a.mensaje_conexion;
              badgeEl.style.color = a.color_conexion || '#16a34a';
            }
            if (cardEl) {
              var t = Number(a.temp);
              cardEl.className = 'tarjeta ' + ((t < 2.0 || t > 8.0) ? 'rojo' : ((t < 3.0 || t > 7.0) ? 'ambar' : 'verde'));
            }
          });
        }

        if (data.puertaActual) {
          var valP = document.getElementById('valPuerta');
          var cardP = document.getElementById('cardPuerta');
          var detP = document.getElementById('detPuerta');
          if (valP) valP.textContent = data.puertaActual.estado;
          if (cardP) cardP.className = 'tarjeta ' + (data.puertaActual.abierta ? 'rojo' : 'verde');
          if (detP) detP.textContent = 'Sensor magnético Pin 5 · ' + (data.puertaActual.fecha ? data.puertaActual.fecha.substr(11) : '');
        }

        var valAlarmas = document.getElementById('valAlarmas');
        var cardAlarmas = document.getElementById('cardAlarmas');
        if (valAlarmas && data.alarmasActivas !== undefined) {
          valAlarmas.textContent = data.alarmasActivas;
          if (cardAlarmas) {
            cardAlarmas.className = 'tarjeta ' + (data.alarmasActivas > 0 ? 'rojo' : 'verde');
          }
        }
      })
      .catch(function(err){ /* silencioso */ });
  }, 2000);
});
window.addEventListener('resize', function(){
  var series = {$seriesJson};
  var labels = {$labelsJson};
  dibujarGrafico('grafTemp', {etiquetas: labels, series: series, yMin: 0, yMax: 12, unidad: '°C', zonaSegura: [2,8]});
});
</script>
HTML;
