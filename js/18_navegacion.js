"use strict";

/* 18. NAVEGACIÓN
   ===================================================================== */
function ir(vista) {
  estado.vista = vista;
  $$("#tabs button").forEach(b => b.classList.toggle("on", b.dataset.v === vista));
  $$(".vista").forEach(s => s.classList.toggle("on", s.id === "v-" + vista));

  if (vista === "vender") { renderPOS(); $("#txt-buscar").focus(); }
  if (vista === "productos") { if (!$("#h-desde").value) {} renderProductos(); $("#txt-buscar-p").focus(); }
  if (vista === "historial") {
    if (!$("#h-desde").value) { $("#h-desde").value = hoyISO(); $("#h-hasta").value = hoyISO(); }
    renderHistorial();
  }
  if (vista === "cajas") {
    if (!$("#cj-desde").value) { $("#cj-desde").value = hoyISO().slice(0, 8) + "01"; $("#cj-hasta").value = hoyISO(); }
    renderCajas();
  }
  if (vista === "reportes") {
    if (!$("#r-desde").value) { $("#r-desde").value = hoyISO(); $("#r-hasta").value = hoyISO(); }
    renderReportes();
  }
  if (vista === "ajustes") renderAjustes();
  if (vista === "cocina") {
    arrancarPollingCocina();
    cargarComandas().catch(e => aviso(e.message, "mal"));
  } else if (estado.vista === "cocina") {
    clearInterval(estado.cocTimer);
  }
}

window.ir = ir;
window.abrirProducto = abrirProducto;
