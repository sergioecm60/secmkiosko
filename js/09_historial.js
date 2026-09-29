"use strict";

/*   9. HISTORIAL
   ===================================================================== */
async function renderHistorial() {
  const tb = $("#h-tbody");
  tb.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:26px;color:var(--muted)">Cargando…</td></tr>';
  try {
    const r = await api("ventas", {
      desde: $("#h-desde").value || hoyISO(),
      hasta: $("#h-hasta").value || hoyISO()
    });
    $("#h-count").textContent = r.ventas.length;
    $("#h-total").textContent = dinero(r.importe);

    if (!r.ventas.length) {
      tb.innerHTML = '<tr><td colspan="6"><div class="vacio" style="padding:34px">'
        + '<div class="ico">🧾</div><h3>No hay ventas en este periodo</h3>'
        + "<p>Cambia las fechas o registra una venta.</p></div></td></tr>";
      return;
    }

    tb.innerHTML = r.ventas.map(v => {
      const art = v.anulada
        ? '<span class="etq mal">Anulada</span>'
        : (v.items
            ? v.items.reduce((s, i) => s + i.cantidad, 0) + " art."
            : verArticulos(v.id));
      return `<tr style="${v.anulada ? "opacity:.55" : ""}">
        <td><strong>#${v.folio}</strong></td>
        <td class="fuente">${fechaHora(v.fecha)}</td>
        <td>${art}</td>
        <td>${esc(v.metodo)}</td>
        <td class="num"><strong>${dinero(v.total)}</strong>${v.descuento > 0 ? '<br><span class="fuente" style="font-size:11px">desc. ' + dinero(v.descuento) + "</span>" : ""}</td>
        <td class="acciones">
          <button class="btn sm" data-ver="${v.id}">Ver</button>
          <button class="btn sm" data-reimp="${v.id}">🖨</button>
          ${v.anulada ? "" : `<button class="btn sm peligro" data-anular="${v.id}">Anular</button>`}
        </td>
      </tr>`;
    }).join("");
  } catch (e) {
    tb.innerHTML = '<tr><td colspan="6" style="padding:22px;color:var(--bad)">' + esc(e.message) + "</td></tr>";
  }
}

/* El listado no trae el detalle; se cuenta como texto simple */
function verArticulos(id) {
  return '<span class="fuente" title="Abre el detalle">—</span>';
}

async function verVenta(id) {
  try {
    const r = await api("venta", { id });
    mostrarVenta(r.venta, false);
  } catch (e) { aviso(e.message, "mal"); }
}

async function anularVenta(id) {
  const v = await api("venta", { id }).catch(() => null);
  if (!v) return;
  const ok = await confirmar(
    "Anular la venta #" + v.venta.folio,
    "Se devolverá el stock de los " + v.venta.items.length + " artículo(s) al inventario. " +
    "La venta queda marcada como anulada en el historial y los reportes, pero no se borra."
  );
  if (!ok) return;
  try {
    await api("venta_anular", { id, motivo: "Anulada desde el historial" });
    aviso("Venta anulada. El stock se devolvió al inventario.", "ok");
    await Promise.all([cargarProductos(), refrescarCabecera()]);
    renderHistorial();
    renderPOS();
  } catch (e) { aviso(e.message, "mal"); }
}
