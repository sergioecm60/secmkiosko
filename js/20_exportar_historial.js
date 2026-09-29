"use strict";

/* 20. EXPORTAR HISTORIAL
   ===================================================================== */
async function exportarVentas() {
  try {
    const r = await api("ventas", { desde: $("#h-desde").value || hoyISO(), hasta: $("#h-hasta").value || hoyISO() });
    if (!r.ventas.length) { aviso("No hay ventas en el periodo.", "aviso-w"); return; }
    const filas = [["Folio", "Fecha", "Hora", "Artículos", "Método", "Subtotal", "Descuento", "Total", "Estado", "Referencia"]];
    for (const v of r.ventas) {
      const d = new Date(String(v.fecha).replace(" ", "T"));
      let arts = 0;
      try { arts = (await api("venta", { id: v.id })).venta.items.reduce((s, i) => s + i.cantidad, 0); } catch (e) { /* ok */ }
      filas.push([
        v.folio,
        fechaCorta(v.fecha), dosDig(d.getHours()) + ":" + dosDig(d.getMinutes()),
        numeroLocal(arts, 0), v.metodo, v.subtotal.toFixed(2), v.descuento.toFixed(2), v.total.toFixed(2),
        v.anulada ? "Anulada" : "Válida", v.referencia || ""
      ]);
    }
    descargar(aCSV(filas), "ventas-" + $("#h-desde").value + "-a-" + $("#h-hasta").value + ".csv");
    aviso("Historial exportado.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}
