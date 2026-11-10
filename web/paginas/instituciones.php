<?php
/**
 * Instituciones de salud y configuración de control (límites de temperatura)
 */
$error = '';
$exito = '';
$puedeConfigurar = tiene_permiso('configurar_sistema');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'config') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    elseif (!$puedeConfigurar) { $error = 'No tiene permiso para configurar el sistema.'; }
    else {
        $idConf  = (int)$_POST['id_configuracion'];
        $tmin    = (float)$_POST['temperatura_min'];
        $tmax    = (float)$_POST['temperatura_max'];
        $tiempo  = (int)$_POST['tiempo_alerta_minutos'];
        $estado  = (int)$_POST['estado'];
        if ($tmin >= $tmax) { $error = 'La temperatura mínima debe ser menor a la máxima.'; }
        else {
            q("UPDATE configuracion_control
               SET temperatura_min = ?, temperatura_max = ?, tiempo_alerta_minutos = ?, estado = ?
               WHERE id_configuracion = ?", [$tmin, $tmax, $tiempo, $estado, $idConf]);
            auditoria('CONFIG_ACTUALIZADA', 'Instituciones');
            $_SESSION['mensaje'] = 'Configuración de control actualizada.';
            header('Location: index.php?p=instituciones');
            exit;
        }
    }
}

$instituciones = q("
    SELECT i.*, l.nombre AS localidad, m.nombre AS municipio, c.id_configuracion,
           c.temperatura_min, c.temperatura_max, c.tiempo_alerta_minutos, c.estado AS config_estado
    FROM institucion_salud i
    JOIN localidad l ON l.id_localidad = i.id_localidad
    JOIN municipio m ON m.id_municipio = l.id_municipio
    LEFT JOIN configuracion_control c ON c.id_institucion = i.id_institucion AND c.fecha_fin IS NULL
    ORDER BY i.nombre")->fetchAll();

$dispositivosPorIns = q("
    SELECT id_institucion, COUNT(*) total,
           SUM(estado = 'ACTIVO') activos
    FROM dispositivo GROUP BY id_institucion")->fetchAll();
$porIns = [];
foreach ($dispositivosPorIns as $r) {
    $porIns[$r['id_institucion']] = $r;
}

echo (!empty($exito) ? '<div class="alerta ok">' . e($exito) . '</div>' : '');
echo (!empty($error) ? '<div class="alerta error">' . e($error) . '</div>' : '');

echo '<div class="panel"><h2>Establecimientos de salud del piloto</h2>';
echo '<table class="tabla"><tr><th>Institución</th><th>Localidad</th><th>Tipo</th><th>Nivel</th>'
   . '<th>Nodos</th><th>Límites °C</th><th>Alerta (min)</th><th>Configuración</th></tr>';
foreach ($instituciones as $i) {
    $conf = $i['id_configuracion'] ? $i : null;
    echo '<tr>
            <td><strong>' . e($i['nombre']) . '</strong></td>
            <td>' . e($i['localidad']) . '</td>
            <td>' . e($i['tipo_institucion']) . '</td>
            <td>' . e($i['nivel_atencion']) . '</td>';
    $nd = $porIns[$i['id_institucion']] ?? null;
    echo '<td>' . ($nd ? (int)$nd['activos'] . '/' . (int)$nd['total'] : '0/0') . '</td>';
    if ($conf) {
        echo '<td><span class="badge">' . number_format((float)$conf['temperatura_min'], 2)
           . ' – ' . number_format((float)$conf['temperatura_max'], 2) . '</span></td>
              <td>' . (int)$conf['tiempo_alerta_minutos'] . '</td>';
        echo '<td>';
        if ($puedeConfigurar) {
            echo '<button class="boton mini" onclick="document.getElementById(\'cfg-' . (int)$conf['id_configuracion'] . '\').style.display=\'block\'">Editar</button>
                  <div id="cfg-' . (int)$conf['id_configuracion'] . '" style="display:none;margin-top:8px;background:#f7fafd;padding:10px;border-radius:8px">
                    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                      <input type="hidden" name="acc" value="config">
                      <input type="hidden" name="csrf" value="' . csrf_token() . '">
                      <input type="hidden" name="id_configuracion" value="' . (int)$conf['id_configuracion'] . '">
                      <div class="campo"><label>Temperatura mínima</label>
                        <input type="number" step="0.01" name="temperatura_min" value="' . number_format((float)$conf['temperatura_min'], 2, '.', '') . '"></div>
                      <div class="campo"><label>Temperatura máxima</label>
                        <input type="number" step="0.01" name="temperatura_max" value="' . number_format((float)$conf['temperatura_max'], 2, '.', '') . '"></div>
                      <div class="campo"><label>Tiempo de alerta (min)</label>
                        <input type="number" min="1" name="tiempo_alerta_minutos" value="' . (int)$conf['tiempo_alerta_minutos'] . '"></div>
                      <div class="campo"><label>Estado</label>
                        <select name="estado">
                          <option value="1" ' . ($conf['config_estado'] == 1 ? 'selected' : '') . '>Activo</option>
                          <option value="0" ' . ($conf['config_estado'] == 0 ? 'selected' : '') . '>Inactivo</option>
                        </select></div>
                      <button class="boton mini verde" type="submit">Guardar</button>
                    </form></div>';
        } else {
            echo '—';
        }
        echo '</td>';
    } else {
        echo '<td colspan="3">Sin configuración activa</td>';
    }
    echo '</tr>';
}
echo '</table>';
echo '<p class="pie-mapa">Límites por defecto: 2,00 °C a 8,00 °C (OPS/PAI). La configuración aplica solo al '
    . 'sistema de monitoreo; no modifica el compresor del refrigerador sanitario.</p></div>';