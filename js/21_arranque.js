"use strict";

/* 21. ARRANQUE
    ===================================================================== */
function conectarSesion() {
  // Menú de la cuenta
  const btn = $("#btn-usuario"), lista = $("#menu-usuario-lista");
  if (btn && lista) {
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      lista.classList.toggle("on");
    });
    document.addEventListener("click", () => lista.classList.remove("on"));
    lista.addEventListener("click", (e) => e.stopPropagation());
    $$("#menu-usuario-lista [data-ir]").forEach(b =>
      b.addEventListener("click", () => {
        lista.classList.remove("on");
        // "Usuarios" no es una vista propia: vive dentro de Ajustes.
        if (b.dataset.ir === "usuarios") {
          ir("ajustes");
          const ancla = $("#usuarios");
          if (ancla) ancla.scrollIntoView({ behavior: "smooth", block: "start" });
        } else {
          ir(b.dataset.ir);
        }
      }));
    $$("#menu-usuario-lista [data-accion]").forEach(b =>
      b.addEventListener("click", () => { lista.classList.remove("on"); cambiarMiClave(); }));
  }
  // Atajo a la caja desde la franja de arriba
  const mc = $("#mi-caja");
  if (mc) mc.addEventListener("click", () => ir("cajas"));

  // Modales de caja
  const on = (sel, ev, fn) => { const e = $(sel); if (e) e.addEventListener(ev, fn); };
  on("#ac-ok", "click", confirmarAbrirCaja);
  on("#cc-ok", "click", confirmarCerrarCaja);
  on("#cd-imprimir", "click", imprimirCaja);
  on("#mu-ok", "click", confirmarUsuario);
  on("#cj-buscar", "click", cargarListaCajas);
  on("#btn-us-nuevo", "click", () => guardarUsuario(0));

  // Enter para confirmar en los modales de caja
  ["#ac-monto", "#cc-motivo", "#mu-clave"].forEach(sel => {
    const e = $(sel);
    if (!e) return;
    e.addEventListener("keydown", (ev) => {
      if (ev.key !== "Enter") return;
      ev.preventDefault();
      const v = $(sel).closest(".velo");
      const ok = { "#ac-monto": "#ac-ok", "#cc-motivo": "#cc-ok", "#mu-clave": "#mu-ok" }[sel];
      if (v && ok) $(ok).click();
    });
  });
}

async function iniciar() {
  conectar();
  conectarSesion();
  try {
    if (esCocina()) {
      // La cocina entra directo a su tablero: no toca caja, ventas ni stock.
      $$("#tabs button").forEach(b => {
        if (b.dataset.v !== "cocina") b.hidden = true;
      });
      await cargarProductos();
      ir("cocina");
      return;
    }
    await Promise.all([cargarProductos(), refrescarCabecera(), cargarProveedores()]);
    if (estado.config.tema === "ocuro") document.body.classList.add("ocuro");
    aplicarMarca();
    renderPOS();
    $("#txt-buscar").focus();
    /* Zonas y categorias se resuelven antes de seguir, y no por convenience:
       Ajustes dibuja la tabla de categorias apenas las recibe, asi que si
       estas dos promesas quedan flotando, renderAjustesCategorias() corre
       contra un estado vacio y la tabla sale en blanco sin que nada falle.
       El orden de respuesta de dos peticiones no esta garantizado, y antes
       esto funcionaba solo porque el await de zonas.delayaba lo suficiente.
       Si alguna vez hay que volver a soltar una de estas, que sea porque ya
       no la necesita nadie al pintar, no por forgetting el await. */
    await Promise.all([
        api("zonas").then(r => { estado.zonas = r.zonas || []; }).catch(() => {}),
        api("categorias").then(r => { estado.categorias = r.categorias || []; }).catch(() => {}),
    ]);
    if (esAdmin()) await cargarAjustesCocina();
  } catch (e) {
    document.body.innerHTML = '<div class="vacio" style="height:100vh">'
      + '<div class="ico">⚠️</div><h3>No se pudo conectar con el sistema</h3>'
      + "<p>" + esc(e.message) + "</p>"
      + '<p style="font-size:12.5px">Verifica que Laragon esté abierto y que MySQL esté encendido.</p>'
      + '<button class="btn pri" onclick="location.reload()">Reintentar</button></div>';
  }
}

document.addEventListener("DOMContentLoaded", iniciar);
