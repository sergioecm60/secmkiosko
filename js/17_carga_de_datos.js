"use strict";

/*    17. CARGA DE DATOS
    ===================================================================== */
async function cargarProductos() {
  const r = await api("productos");
  estado.productos = r.productos;
}

async function refrescarCabecera() {
  const r = await api("estado");
  estado.config = r.config;
  if (r.medios_pago && r.medios_pago.length) estado.mediosPago = r.medios_pago;
  if (r.usuario) { estado.yo = r.usuario; estado.soyAdmin = !!r.usuario.es_admin; }
  if (r.caja !== undefined) estado.caja = r.caja;
  aplicarMarca();
  $("#lbl-hoy-total").textContent = dinero(r.hoy.total);
  const d = new Date();
  $("#lbl-hoy").textContent = d.toLocaleDateString("es-AR", { weekday: "long", day: "numeric", month: "long" });
  pintarEstadoCaja();
}

/** Franja de la barra superior: si tengo caja abierta y cuánto entró. */
function pintarEstadoCaja() {
  const caja = estado.caja;
  const el = $("#mi-caja");
  if (!el) return;
  el.className = "sesion-caja " + (caja ? "abierto" : "cerrado");
  const v = $("#mi-caja-val");
  if (caja) {
    v.textContent = "Abierta · " + dinero(caja.monto_inicial);
    el.title = "Caja abierta desde las " + new Date(caja.abierta_en).toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" });
  } else {
    v.textContent = "Cerrada";
    el.title = "No tenés una caja abierta";
  }
}
