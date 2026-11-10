/**
 * Gráfico de líneas minimalista (sin dependencias externas)
 * Uso: dibujarGrafico(idCanvas, {etiquetas:[], series:[{nombre,color,data:[],relleno}], yMin, yMax, unidad})
 */
function dibujarGrafico(id, cfg) {
  const cv = document.getElementById(id);
  if (!cv) return;
  const dpr = window.devicePixelRatio || 1;
  const W = cv.clientWidth, H = cv.clientHeight;
  cv.width = W * dpr; cv.height = H * dpr;
  const ctx = cv.getContext('2d');
  ctx.scale(dpr, dpr);
  ctx.clearRect(0, 0, W, H);

  const pad = {izq: 44, der: 12, arr: 12, abj: 34};
  const ancho = W - pad.izq - pad.der, alto = H - pad.arr - pad.abj;
  const series = cfg.series || [];
  const labels = cfg.etiquetas || [];
  if (!series.length || !labels.length) return;

  let min = (cfg.yMin !== undefined) ? cfg.yMin : Infinity;
  let max = (cfg.yMax !== undefined) ? cfg.yMax : -Infinity;
  if (cfg.yMin === undefined || cfg.yMax === undefined) {
    series.forEach(s => s.data.forEach(v => {
      if (v === null || v === undefined) return;
      if (v < min) min = v;
      if (v > max) max = v;
    }));
    if (min === Infinity) { min = 0; max = 1; }
    const margen = (max - min) * 0.15 || 1;
    if (cfg.yMin === undefined) min -= margen;
    if (cfg.yMax === undefined) max += margen;
  }

  const x = i => pad.izq + (labels.length === 1 ? ancho / 2 : (i * ancho) / (labels.length - 1));
  const y = v => pad.arr + alto - ((v - min) / (max - min)) * alto;

  // zona segura (si hay limites 2-8)
  if (cfg.zonaSegura) {
    const yTop = Math.max(pad.arr, y(cfg.zonaSegura[1]));
    const yBot = Math.min(pad.arr + alto, y(cfg.zonaSegura[0]));
    ctx.fillStyle = 'rgba(46,158,91,.10)';
    ctx.fillRect(pad.izq, yTop, ancho, yBot - yTop);
    ctx.strokeStyle = 'rgba(46,158,91,.55)';
    ctx.setLineDash([5, 4]);
    ctx.beginPath(); ctx.moveTo(pad.izq, yTop); ctx.lineTo(pad.izq + ancho, yTop); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(pad.izq, yBot); ctx.lineTo(pad.izq + ancho, yBot); ctx.stroke();
    ctx.setLineDash([]);
  }

  // ejes
  ctx.strokeStyle = '#dde5ee'; ctx.fillStyle = '#6b7a8c'; ctx.font = '11px Segoe UI, Arial';
  const pasos = 5;
  for (let i = 0; i <= pasos; i++) {
    const v = min + ((max - min) * i) / pasos;
    const yy = y(v);
    ctx.beginPath(); ctx.moveTo(pad.izq, yy); ctx.lineTo(pad.izq + ancho, yy); ctx.stroke();
    ctx.textAlign = 'right'; ctx.fillText(v.toFixed(1), pad.izq - 6, yy + 4);
  }
  const saltoLab = Math.max(1, Math.ceil(labels.length / 7));
  ctx.textAlign = 'center';
  for (let i = 0; i < labels.length; i += saltoLab) {
    ctx.fillText(labels[i], x(i), H - pad.abj + 16);
  }
  if (cfg.unidad) {
    ctx.textAlign = 'left';
    ctx.fillText(cfg.unidad, 4, pad.arr + 4);
  }

  // series
  series.forEach(s => {
    ctx.strokeStyle = s.color; ctx.lineWidth = 2;
    if (s.relleno) {
      ctx.beginPath();
      let inicio = true;
      s.data.forEach((v, i) => {
        if (v === null || v === undefined) return;
        if (inicio) { ctx.moveTo(x(i), pad.arr + alto); ctx.lineTo(x(i), y(v)); inicio = false; }
        else ctx.lineTo(x(i), y(v));
      });
      ctx.lineTo(x(s.data.length - 1), pad.arr + alto);
      ctx.closePath();
      ctx.fillStyle = s.color + '26';
      ctx.fill();
      ctx.strokeStyle = s.color;
    }
    ctx.beginPath();
    let arrancado = false;
    s.data.forEach((v, i) => {
      if (v === null || v === undefined) { arrancado = false; return; }
      if (!arrancado) { ctx.moveTo(x(i), y(v)); arrancado = true; }
      else ctx.lineTo(x(i), y(v));
    });
    ctx.stroke();
    ctx.fillStyle = s.color;
    s.data.forEach((v, i) => {
      if (v === null || v === undefined) return;
      ctx.beginPath(); ctx.arc(x(i), y(v), 2.2, 0, Math.PI * 2); ctx.fill();
    });
  });
}

function pintarLeyenda(id, series) {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = series.map(s => '<span><i style="background:' + s.color + '"></i>' + s.nombre + '</span>').join('');
}
