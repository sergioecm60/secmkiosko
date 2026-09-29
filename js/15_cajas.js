"use strict";

/*    15. CAJAS
    ===================================================================== */

/** Pide el efectivo inicial y abre la caja. */
function abrirModalAbrirCaja() {
  const inp = $("#ac-monto");
  inp.value = "";
  $("#ac-aviso").innerHTML = "Si no ponés nada, la caja arranca en <b>" + dinero(0) + "</b>.";
  abrirModal("#m-abrir-caja");
  setTimeout(() => inp.focus(), 60);
}

async function confirmarAbrirCaja() {
  const btn = $("#ac-ok");
  btn.disabled = true;
  try {
    const r = await api("caja_abrir", { monto_inicial: $("#ac-monto").value });
    estado.caja = r.caja;
    cerrarModal("#m-abrir-caja");
    aviso("Caja abierta. Ya podés cobrar.", "ok");
    pintarEstadoCaja();
    await renderCajas();
  } catch (e) {
    aviso(e.message, "mal");
  } finally {
    btn.disabled = false;
  }
}

/** Mi caja: estado, números del turno y botones. */
async function refrescarMiCaja() {
  const r = await api("cajas_mias");
  estado.caja = r.caja;
  pintarEstadoCaja();
  return r;
}

async function renderCajas() {
  await pintarMiCaja();
  await cargarListaCajas();
}

/** Panel de arriba: abrir/cerrar y el resumen del turno. */
async function pintarMiCaja() {
  const r = await api("cajas_mias");
  estado.caja = r.caja;
  pintarEstadoCaja();

  const est = $("#mi-caja-estado");
  const cue = $("#mi-caja-cue");
  if (!cue) return;

  if (!r.caja) {
    est.innerHTML = '<span class="etq neutro">Cerrada</span>';
    cue.innerHTML =
      '<div class="caja-abrir">'
      + '<div class="txt"><b>No tenés una caja abierta</b>'
      + '<span>No se puede cobrar sin caja. Poné el efectivo con el que arrancás el turno y '
      + 'después vendé con normalidad.</span></div>'
      + '<button class="btn ok" id="mi-caja-abrir">🏧 Abrir mi caja</button>'
      + "</div>";
    $("#mi-caja-abrir").addEventListener("click", abrirModalAbrirCaja);
    return;
  }

  const c = r.caja;
  const s = r.resumen;
  const esperado = r2(c.monto_inicial + s.metodos.filter(m => m.es_efectivo).reduce((a, m) => a + m.esperado, 0));

  est.innerHTML = '<span class="etq ok">Abierta #' + c.id + '</span>';
  cue.innerHTML =
    '<div class="caja-nums" style="margin-bottom:14px">'
    + '<div class="n"><div class="cap">Fondo inicial</div><div class="val">' + dinero(c.monto_inicial) + "</div></div>"
    + '<div class="n"><div class="cap">Ventas del turno</div><div class="val">' + s.ventas + "</div></div>"
    + '<div class="n"><div class="cap">Vendido</div><div class="val">' + dinero(s.total) + "</div></div>"
    + '<div class="n ok"><div class="cap">Efectivo esperado</div><div class="val">' + dinero(esperado) + "</div></div>"
    + "</div>"
    + '<div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">'
    + '<button class="btn pri" id="mi-caja-cerrar">🔒 Contar y cerrar caja</button>'
    + '<span class="fuente" style="font-size:12.5px">Abierta ' + horaDe(c.abierta_en) + "</span>"
    + "</div>";

  $("#mi-caja-cerrar").addEventListener("click", () => abrirModalCerrarCaja(c, s));
}

/**
 * Modal de cierre: una fila por medio de pago con lo esperado y lo contado.
 * `cajaId` se pasa sólo cuando el administrador cierra la caja de otro.
 */
function abrirModalCerrarCaja(c, s, cajaId) {
  const filas = s.metodos.filter(m => m.ventas > 0);
  const inicial = c.monto_inicial;
  $("#cc-ok").dataset.caja = cajaId || 0;

  $("#cc-tabla").innerHTML =
    '<table class="tabla-mp"><thead><tr>'
    + '<th style="width:30px"></th><th>Método</th><th class="num">Esperado</th>'
    + '<th class="num" style="width:130px">Contado</th><th class="num" style="width:100px">Diferencia</th>'
    + "</tr></thead><tbody>"
    + filas.map(m => {
      // El efectivo esperado ya incluye el fondo con el que se abrió la caja.
      const esp = m.es_efectivo ? r2(m.esperado + inicial) : m.esperado;
      return '<tr data-mp="' + m.id + '" data-esp="' + esp + '" data-efe="' + (m.es_efectivo ? 1 : 0) + '">'
        + '<td class="mp-ico">' + esc(m.icono) + "</td>"
        + "<td><b>" + esc(m.nombre) + "</b><br><span class=\"fuente\" style=\"font-size:11.5px\">"
        + m.ventas + (m.ventas === 1 ? " venta" : " ventas") + "</span></td>"
        + '<td class="num">' + dinero(esp) + "</td>"
        + '<td class="declarado"><input type="text" inputmode="decimal" data-decl="' + m.id + '" value="'
        + numCorto(esp) + '" autocomplete="off"></td>'
        + '<td class="num dif cero" data-dif="' + m.id + '">—</td>'
        + "</tr>";
    }).join("")
    + "</tbody></table>"
    + (filas.length === 0
      ? '<p class="parrafo" style="margin:12px 0 0">No vendiste nada en este turno, así que no hay nada que conciliar.</p>'
      : "");

  $$("#cc-tabla input[data-decl]").forEach(inp => {
    inp.addEventListener("input", () => {
      const fila = inp.closest("tr");
      const dif = r2(parseNum(inp.value) - parseNum(fila.dataset.esp));
      const cel = fila.querySelector("[data-dif]");
      cel.textContent = dif === 0 ? "—" : (dif > 0 ? "+" : "−") + dinero(Math.abs(dif));
      cel.className = "num dif " + (dif === 0 ? "cero" : (dif > 0 ? "sobra" : "falta"));
      inp.classList.toggle("cuadra", dif === 0);
      inp.classList.toggle("nocuadra", dif !== 0);
      actualizarMotivoCierre();
    });
  });

  actualizarMotivoCierre();
  $("#cc-motivo").value = "";
  abrirModal("#m-cerrar-caja");
}

/** Pide el motivo sólo si algo no da exacto. */
function actualizarMotivoCierre() {
  const hayDescuadre = $$("#cc-tabla input[data-decl]").some(inp => !inp.classList.contains("cuadra"));
  $("#cc-motivo-campo").style.display = hayDescuadre ? "" : "none";
  if (hayDescuadre) {
    const f = $$("#cc-tabla tr").find(tr => {
      const i = tr.querySelector("input[data-decl]");
      return i && !i.classList.contains("cuadra");
    });
    const d = f ? f.querySelector("[data-dif]").textContent : "";
    $("#cc-motivo-label").innerHTML = 'Motivo del descuadre <span class="etq mal" style="margin-left:5px">'
      + esc(d) + '</span> — anotá qué pasó para que el administrador lo revise';
  }
}

async function confirmarCerrarCaja() {
  const btn = $("#cc-ok");
  btn.disabled = true;
  const declarados = {};
  $$("#cc-tabla input[data-decl]").forEach(inp => {
    declarados[inp.dataset.decl] = parseNum(inp.value);
  });
  try {
    const cajaId = Number(btn.dataset.caja) || 0;
    await api("caja_cerra", { declarados, motivo: $("#cc-motivo").value.trim(), caja_id: cajaId });
    if (!cajaId) estado.caja = null;
    cerrarModal("#m-cerrar-caja");
    aviso("Caja cerrada. Quedó registrada en el historial.", "ok");
    await refrescarCabecera();
    await renderCajas();
  } catch (e) {
    aviso(e.message, "mal");
  } finally {
    btn.disabled = false;
  }
}

/** Historial de cajas. El vendedor sólo ve las suyas. */
async function cargarListaCajas() {
  const tb = $("#cajas-tb");
  if (!tb) return;
  let r;
  try {
    r = await api("cajas_todas", { desde: $("#cj-desde").value, hasta: $("#cj-hasta").value });
    estado.cajas = r.cajas || [];
  } catch (e) {
    tb.innerHTML = '<tr><td colspan="10" style="padding:20px;text-align:center;color:var(--bad)">'
      + esc(e.message) + "</td></tr>";
    return;
  }

  $("#cajas-titulo").innerHTML = (r.solo_mias ? "Mis cajas" : "Historial de cajas")
    + (r.descuadres ? ' · <span class="etq mal">' + r.descuadres + " con descuadre</span>" : "");

  const cs = estado.cajas;
  tb.innerHTML = cs.length
    ? cs.map(filaCajaTabla).join("")
    : '<tr><td colspan="10" style="padding:20px;text-align:center;color:var(--muted)">No hay cajas en esas fechas</td></tr>';

  $$("#cajas-tb [data-ver-caja]").forEach(b =>
    b.addEventListener("click", () => verCaja(Number(b.dataset.verCaja))));
}

function filaCajaTabla(c) {
  let estado;
  if (c.abierta) {
    estado = '<span class="etq ok">Abierta</span>';
  } else if (c.diferencia === null) {
    estado = '<span class="etq neutro">—</span>';
  } else if (Math.abs(c.diferencia) < 0.01) {
    estado = '<span class="etq ok">Cuadrada</span>';
  } else {
    estado = '<span class="etq mal">' + (c.diferencia > 0 ? "Sobró " : "Faltó ")
      + dinero(Math.abs(c.diferencia)) + "</span>";
  }
  const nota = !c.abierta && c.motivo
    ? '<br><span class="fuente" style="font-size:11px">' + esc(c.motivo) + "</span>" : "";
  return "<tr>"
    + "<td><b>#" + c.id + "</b></td>"
    + (esAdmin() ? "<td>" + esc(c.cajero_usuario || "") + nota + "</td>" : "")
    + "<td>" + esc(fechaHora(c.abierta_en)) + "</td>"
    + "<td>" + (c.cerrada_en ? esc(fechaHora(c.cerrada_en)) : "—") + "</td>"
    + '<td class="num">' + (c.ventas === null ? "—" : c.ventas) + "</td>"
    + '<td class="num">' + dinero(c.monto_inicial) + "</td>"
    + '<td class="num">' + dinero(c.total_esperado) + "</td>"
    + '<td class="num">' + dinero(c.efectivo_declarado) + "</td>"
    + "<td>" + estado + "</td>"
    + '<td class="acciones"><button class="btn sm" data-ver-caja="' + c.id + '">👁</button></td>'
    + "</tr>";
}

/** Detalle de una caja + ticket imprimible. */
async function verCaja(id) {
  try {
    const r = await api("caja_detalle", { id });
    const c = r.caja, s = r.resumen;
    $("#cd-titulo").textContent = "Caja #" + c.id + " · " + (c.cajero_usuario || "");

    const cuadra = !c.abierta && c.diferencia !== null && Math.abs(c.diferencia) < 0.01;
    const difTxt = c.abierta
      ? '<span class="etq ok">Abierta</span>'
      : (cuadra
        ? '<span class="etq ok">Cuadrada</span>'
        : '<span class="etq mal">' + (c.diferencia > 0 ? "Sobró " : "Faltó ") + dinero(Math.abs(c.diferencia)) + "</span>");

    $("#cd-cue").innerHTML =
      '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">'
      + '<div style="font-size:13px">' + difTxt + "</div>"
      + '<div class="caja-nums" style="flex:1;min-width:260px">'
      + '<div class="n"><div class="cap">Fondo</div><div class="val">' + dinero(c.monto_inicial) + "</div></div>"
      + '<div class="n"><div class="cap">Vendido</div><div class="val">' + dinero(s.total) + "</div></div>"
      + '<div class="n"><div class="cap">Efectivo esperado</div><div class="val">' + dinero(c.efectivo_esperado) + "</div></div>"
      + '<div class="n"><div class="cap">Efectivo contado</div><div class="val">' + dinero(c.efectivo_declarado) + "</div></div>"
      + "</div></div>"
      + (c.motivo ? '<p class="parrafo" style="margin:0 0 12px"><b>Motivo:</b> ' + esc(c.motivo) + "</p>" : "")
      + '<table class="tabla-mp"><thead><tr><th style="width:30px"></th><th>Método</th>'
      + '<th class="num">Esperado</th><th class="num">Contado</th><th class="num">Diferencia</th></tr></thead><tbody>'
      + s.metodos.filter(m => m.ventas > 0).map(m => {
        const esp = m.es_efectivo ? r2(m.esperado + c.monto_inicial) : m.esperado;
        const d = r2(m.declarado - m.esperado);
        return "<tr><td class=\"mp-ico\">" + esc(m.icono) + "</td><td><b>" + esc(m.nombre) + "</b></td>"
          + '<td class="num">' + dinero(esp) + "</td>"
          + '<td class="num">' + dinero(m.es_efectivo ? m.declarado : m.esperado) + "</td>"
          + '<td class="num dif ' + (d === 0 ? "cero" : (d > 0 ? "sobra" : "falta")) + '">'
          + (d === 0 ? "—" : (d > 0 ? "+" : "−") + dinero(Math.abs(d))) + "</td></tr>";
      }).join("")
      + "</tbody></table>"
      + '<h3 style="font-size:13px;margin:16px 0 7px">Ventas del turno (' + r.ventas.length + ")</h3>"
      + '<div style="max-height:220px;overflow:auto"><table class="tabla-mp"><tbody>'
      + r.ventas.map(v =>
        "<tr><td>#" + v.folio + "</td>"
        + '<td class="fuente" style="font-size:12px">' + esc(fechaHora(v.fecha)) + "</td>"
        + "<td>" + esc(v.metodo) + (v.referencia ? ' <span class="fuente">(' + esc(v.referencia) + ")</span>" : "") + "</td>"
        + (v.anulada ? '<td><span class="etq mal">anulada</span></td>' : "")
        + '<td class="num"><b>' + dinero(v.total) + "</b></td></tr>").join("")
      + "</tbody></table></div>";

    $("#cd-caja-datos").value = JSON.stringify(c);
    // El administrador puede cerrar una caja que quedó abierta (el cajero se
    // fue sin cerrar). El vendedor sólo cierra la suya desde "Mi caja".
    const btnCerrar = $("#cd-cerrar-caja");
    if (btnCerrar) {
      const puede = c.abierta && (esAdmin() || (estado.caja && estado.caja.id === c.id));
      btnCerrar.style.display = puede ? "" : "none";
      btnCerrar.onclick = async () => {
        if (!await confirmar("Cerrar la caja #" + c.id,
          "Vas a contar el efectivo y cerrar la caja de " + (c.cajero || "") + ". "
          + "Queda registrada con tu nombre. Esta acción no se puede deshacer.")) return;
        cerrarModal("#m-caja-detalle");
        await cargarListaCajas();
        abrirModalCerrarCajaAjena(c);
      };
    }
    abrirModal("#m-caja-detalle");
  } catch (e) {
    aviso(e.message, "mal");
  }
}

/** Cierre de una caja ajena: reutiliza la tabla de conciliación. */
function abrirModalCerrarCajaAjena(c) {
  api("caja_detalle", { id: c.id }).then(r => {
    // efectivoEsperadoCaja vive en el servidor; la conciliación por método
    // que arma el modal ya trae el esperado de cada medio de pago.
    abrirModalCerrarCaja(
      { id: c.id, monto_inicial: c.monto_inicial },
      r.resumen,
      c.id
    );
  }).catch(e => aviso(e.message, "mal"));
}

/** Ticket de la caja, para imprimir y archivar en el mostrador. */
function imprimirCaja() {
  const btn = $("#cd-imprimir");
  btn.disabled = true;
  (async () => {
    try {
      const c = JSON.parse($("#cd-caja-datos").value);
      const r = await api("caja_detalle", { id: c.id });
      const s = r.resumen;
      const cuadra = !r.caja.abierta && r.caja.diferencia !== null && Math.abs(r.caja.diferencia) < 0.01;

      $("#ticket-caja").innerHTML = '<div class="papel">'
        + "<h1>" + esc(estado.config.negocio || "Kiosco") + "</h1>"
        + '<div class="cen">Cierre de caja #' + r.caja.id + "<br>"
        + fechaHora(r.caja.abierta_en) + " → " + (r.caja.cerrada_en ? fechaHora(r.caja.cerrada_en) : "sin cerrar")
        + "<br>Cajero: " + esc(r.caja.cajero || "") + "</div><hr>"
        + '<div class="lin"><span>Fondo inicial</span><b>' + dinero(r.caja.monto_inicial) + "</b></div><hr>"
        + s.metodos.filter(m => m.ventas > 0).map(m => {
          const esp = m.es_efectivo ? r2(m.esperado + r.caja.monto_inicial) : m.esperado;
          const dec = m.es_efectivo ? m.declarado : m.esperado;
          return '<div class="mp"><span>' + esc(m.icono) + " " + esc(m.nombre) + " (" + m.ventas + ")</span>"
            + "<b>" + dinero(esp) + "</b>"
            + '<div class="lin" style="font-size:11px"><span>contado</span><span>' + dinero(dec) + "</span></div></div>";
        }).join("") + "<hr>"
        + '<div class="lin"><span>TOTAL VENDIDO</span><b>' + dinero(s.total) + "</b></div>"
        + '<div class="lin tot"><span>Efectivo esperado</span><b>' + dinero(r.caja.efectivo_esperado) + "</b></div>"
        + '<div class="lin tot"><span>Efectivo contado</span><b>' + dinero(r.caja.efectivo_declarado) + "</b></div>"
        + '<div class="lin tot"><span>' + (cuadra ? "CIERRE" : (r.caja.diferencia > 0 ? "SOBRÓ" : "FALTÓ")) + "</span>"
        + "<b>" + dinero(r.caja.diferencia) + "</b></div>"
        + (r.caja.motivo ? '<hr><div style="font-size:11px">Motivo: ' + esc(r.caja.motivo) + "</div>" : "")
        + '<div class="firma">____________________________<br>Conformidad del cajero</div>'
        + "</div>";
      imprimirTicketAhora("caja");
    } catch (e) {
      aviso(e.message, "mal");
    } finally {
      btn.disabled = false;
    }
  })();
}
