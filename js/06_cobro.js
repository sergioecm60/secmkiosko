"use strict";

/*   6. COBRO
   ===================================================================== */
function abrirCobro() {
  if (!estado.carrito.length) { aviso("El ticket está vacío.", "aviso-w"); return; }
  // Sin caja abierta no hay cobro: cada venta tiene que pertenecer a un turno
  // para que al cerrar la caja se pueda conciliar.
  if (!estado.caja) {
    aviso("No tenés una caja abierta. Abrila antes de cobrar.", "aviso-w");
    ir("cajas");
    setTimeout(abrirModalAbrirCaja, 120);
    return;
  }
  const tot = totalCarrito();
  const metodos = estado.mediosPago.length
    ? estado.mediosPago
    : [{ id: 0, nombre: "Efectivo", icono: "💵", referencia: false }];
  if (!metodos.some(m => m.nombre === estado.metodoCobro)) estado.metodoCobro = metodos[0].nombre;
  estado.recibido = "";

  // Los botones de metodo se generan desde la tabla medios_pago
  $("#cob-metodos").style.gridTemplateColumns = "repeat(" + Math.min(metodos.length, 3) + ",1fr)";
  $("#cob-metodos").innerHTML = metodos.map(m =>
    '<button class="metodo' + (m.nombre === estado.metodoCobro ? " on" : "") + '" data-m="' + esc(m.nombre) + '">'
    + '<span class="ic">' + esc(m.icono) + "</span>" + esc(m.nombre) + "</button>").join("");

  $("#cob-total").textContent = dinero(tot);
  $("#cob-ref").value = "";
  refrescarCobro();

  // Sugerencias de dinero: exacto y billetes redondo hacia arriba
  const sug = new Set([Math.ceil(tot)]);
  [20, 50, 100, 200, 500, 1000].forEach(b => { if (b >= tot) sug.add(b); });
  $("#cob-billetes").innerHTML = Array.from(sug).filter(v => v > 0)
    .sort((a, b) => a - b)
    .map(v => '<button class="billete" data-billete="' + v + '">' + (v === Math.ceil(tot) && v === tot ? "Exacto " : "") + dinero(v, false) + "</button>")
    .join("");

  abrirModal("#m-cobro");
}

function refrescarCobro() {
  const tot = totalCarrito();
  const metodo = estado.mediosPago.find(m => m.nombre === estado.metodoCobro);
  const efectivo = metodo ? !!metodo.efectivo : true;
  $("#cob-efectivo").style.display = efectivo ? "" : "none";
  $("#cob-otro").style.display = efectivo ? "none" : "";
  if (!efectivo) { $("#cob-confirmar").disabled = false; return; }

  const recibido = estado.recibido === "" ? 0 : Number(estado.recibido);
  const dif = r2(recibido - tot);
  const caja = $("#cob-vuelto");
  caja.className = "vuelto" + (dif < 0 ? " falta" : "");

  if (estado.recibido === "") {
    caja.innerHTML = "<span>Falta entregar</span><b>" + dinero(tot) + "</b>";
  } else if (dif < 0) {
    caja.innerHTML = "<span>Faltan</span><b>" + dinero(-dif) + "</b>";
  } else {
    caja.innerHTML = "<span>Vuelto</span><b>" + dinero(dif) + "</b>";
  }
  $("#cob-confirmar").disabled = dif < 0;
}

function teclaCobro(t) {
  if (t === "C") { estado.recibido = ""; }
  else if (t === "B") { estado.recibido = estado.recibido.slice(0, -1); }
  else {
    if (estado.recibido === "0") estado.recibido = "";
    if (estado.recibido.length < 9) estado.recibido += t;
  }
  refrescarCobro();
}

async function confirmarVenta() {
  const btn = $("#cob-confirmar");
  btn.disabled = true;
  btn.textContent = "Guardando…";

  const metodo = estado.mediosPago.find(m => m.nombre === estado.metodoCobro);
  const efectivo = metodo ? !!metodo.efectivo : true;
  const recibido = estado.recibido === "" ? 0 : Number(estado.recibido);

  // La comanda NO viaja con la venta: ya se guardó sola, con su propio
  // botón. Acá sólo se cobra el carrito.
  // Si hay algo para cocina, la comanda sale sola con el cobro: se arma en el
  // servidor con las lineas marcadas y queda atada a este folio.
  const hayCocina = lineasDeCocina().length > 0;
  try {
    const r = await api("venta_crear", {
      items: itemsParaVenta(),
      descuento: estado.descuento,
      metodo: estado.metodoCobro,
      medio_pago_id: metodo ? metodo.id : 0,
      recibido: efectivo ? recibido : -1,
      vuelto: efectivo ? r2(recibido - totalCarrito()) : 0,
      referencia: $("#cob-ref").value.trim()
    });

    cerrarModal("#m-cobro");
    vaciarCarrito();
    renderBotonCobrar();
    estado.ultimaVenta = r.venta;

    if (r.sin_stock && r.sin_stock.length) {
      aviso("⚠️ Quedó en negativo: " + r.sin_stock.join(", "), "aviso-w");
    }

    mostrarVenta(r.venta, true);
    await Promise.all([cargarProductos(), refrescarCabecera(), refrescarMiCaja()]);

    // El papel de la cocina sale apenas se cobra, sin que el cajero tenga que
    // pedirlo. Si el navegador no puede imprimir, igual queda en el tablero.
    if (r.comanda_id) {
      ultimaComanda = { id: r.comanda_id };
      try { imprimirComanda(r.comanda_id); }
      catch (e) { aviso("Cobrado. La comanda quedó en el tablero de cocina.", "aviso-w"); }
    }
    $("#txt-buscar").focus();
  } catch (e) {
    aviso("No se pudo guardar: " + e.message, "mal");
  } finally {
    btn.disabled = false;
    btn.textContent = "✓ Confirmar venta";
    refrescarCobro();
  }
}

/* =====================================================================
   6b. COMANDAS DE COCINA
   La comanda es el papel para la cocina y va SEPARADA del cobro: el cajero
   carga los productos y cobra en el carrito (de ahi sale el remito), y aparte
   saca la comanda con lo que hay que preparar. Para consumo en el local no
   hay que completar nada: el tipo arranca en "En el local" y los datos de
   entrega son opcionales.
   ===================================================================== */
const ETIQUETA_TIPO = { delivery: "🛵 Delivery", retiro: "🏠 Para llevar", mesa: "🍽 En el local" };
const ETIQUETA_ESTADO = {
  pendiente: "Nueva", preparando: "Preparando", listo: "Lista",
  entregado: "Entregada", cancelado: "Cancelada"
};

/* ---------- Comanda de cocina (el papel, no la venta) ---------- */

/** La comanda que se está armando. Vive en memoria hasta que se guarda. */
let comandaEnCurso = null;

/** La última comanda guardada, para poder sacarle otra copia de inmediato. */
let ultimaComanda = null;

function abrirComanda() {
  if (!comandaEnCurso) {
    comandaEnCurso = {
      cliente: "", direccion: "", zona_id: 0, envio: 0,
      tipo: "mesa", lugar: "", notas: "", items: []
    };
  }
  const c = comandaEnCurso;
  $("#com-cliente").value = c.cliente || "";
  $("#com-lugar").value = c.lugar || "";
  $("#com-direccion").value = c.direccion || "";
  $("#com-notas").value = c.notas || "";
  $("#com-texto").value = "";
  $("#com-detalle").value = "";
  $("#com-detalle-wrap").hidden = true;
  $$("#com-tipo button").forEach(b => b.classList.toggle("on", b.dataset.tipo === c.tipo));
  actualizarCamposComanda();
  renderZonas();
  renderAtajos();
  renderItemsComanda();
  // Si ya se guardó alguna comanda en esta sesión, se puede sacar otra copia
  // sin volver a armarla.
  $("#com-imprimir").disabled = !ultimaComanda;
  abrirModal("#m-comanda");
}

/** Muestra sólo los campos que aplican al tipo de pedido elegido. */
function actualizarCamposComanda() {
  const t = comandaEnCurso.tipo;
  $("#com-zona-campos").hidden = t !== "delivery";
  $("#com-dir-campo").hidden = t !== "delivery";
}

function renderZonas() {
  const sel = $("#com-zona");
  const id = String(comandaEnCurso.zona_id || 0);
  sel.innerHTML = '<option value="0">— sin zona —</option>'
    + estado.zonas.map(z => '<option value="' + z.id + '">' + esc(z.nombre) + " — " + dinero(z.costo) + "</option>").join("");
  sel.value = id;
  aplicarZona();
}

/** El costo sale de la zona configurada; el navegador no lo puede cambiar. */
function aplicarZona() {
  const c = comandaEnCurso;
  const id = Number($("#com-zona").value) || 0;
  c.zona_id = id;
  const z = estado.zonas.find(x => x.id === id);
  // El envío de la comanda es sólo informativo: el cobro va aparte.
  c.envio = c.tipo === "delivery" && z ? Number(z.costo) || 0 : 0;
  $("#com-envio").value = dinero(c.envio);
}

/* ---------- Atajos ---------- */

function renderAtajos() {
  // Crear y editar atajos es del administrador; el cajero sólo los usa.
  const btnNuevo = $("#com-btn-nuevo-atajo");
  if (btnNuevo) btnNuevo.hidden = !estado.esAdmin;
  if (!estado.atajos.length) {
    $("#com-atajos").innerHTML = estado.esAdmin
      ? '<p class="parrafo" style="margin:0">No hay atajos cargados. Usá "+ Nuevo atajo".</p>'
      : '<p class="parrafo" style="margin:0">No hay atajos cargados.</p>';
    return;
  }
  // Se agrupan por sección, en el orden en que vinieron.
  const secciones = [];
  estado.atajos.forEach(a => {
    if (!secciones.some(s => s.nombre === a.seccion)) secciones.push({ nombre: a.seccion, lista: [] });
    secciones.find(s => s.nombre === a.seccion).lista.push(a);
  });
  $("#com-atajos").innerHTML = secciones.map(s => `
    <div class="atajo-sec"><span class="t">${esc(s.nombre)}</span><span class="ln"></span></div>
    <div class="atajo-botones">${s.lista.map(a => `
      <span class="atajo-grupo">
        <button class="atajo" data-atajo="${a.id}"
                title="${esc(a.texto || "")}${a.detalle ? " · " + esc(a.detalle) : ""}">
          ${esc(a.etiqueta)}
        </button>
        ${estado.esAdmin ? `<button class="atajo-ed" data-editar-atajo="${a.id}"
                title="Editar este atajo">✎</button>
              <button class="atajo-borrar" data-borrar-atajo-comanda="${a.id}"
                title="Borrar este atajo">🗑</button>` : ""}
      </span>`).join("")}</div>`).join("");
}

/**
 * Un atajo agrega una línea a la comanda y nada más. No toca el carrito: el
 * cobro es otro paso, así que meter un producto acá cobraría de más.
 */
function usarAtajo(id) {
  const a = estado.atajos.find(x => x.id === Number(id));
  if (!a) return;
  let texto = a.texto;
  if (!texto && a.producto_id) {
    const p = prodPorId(a.producto_id);
    texto = p ? p.nombre : "";
  }
  if (!texto) { aviso("Ese atajo no tiene texto para la comanda.", "mal"); return; }
  agregarItemComanda({
    texto, detalle: a.detalle || null,
    producto_id: a.producto_id || 0, cantidad: 1
  });
}

function agregarItemComanda(item) {
  if (!comandaEnCurso) return;
  // Si la línea ya está anotada se acumula en vez de repetirla: dos "6
  // huevos" son una línea con cantidad 2, no dos renglones en el papel.
  // norm() pasa a minúsculas pero NO saca los espacios de los bordes, así
  // que acá se limpian: "6 HUEVOS " y " 6 huevos" son la misma línea.
  const clave = s => norm(s == null ? "" : String(s)).trim().replace(/\s+/g, " ");
  const igual = comandaEnCurso.items.find(it =>
    (it.producto_id || 0) === (item.producto_id || 0)
    && clave(it.texto) === clave(item.texto)
    && clave(it.detalle || "") === clave(item.detalle || ""));
  if (igual) {
    igual.cantidad = (Number(igual.cantidad) || 0) + (Number(item.cantidad) || 1);
  } else {
    comandaEnCurso.items.push({
      texto: item.texto, detalle: item.detalle || null,
      producto_id: item.producto_id || 0, cantidad: Number(item.cantidad) || 1
    });
  }
  renderItemsComanda();
}

/* ---------- Lineas de la comanda ---------- */

function renderItemsComanda() {
  const items = comandaEnCurso ? comandaEnCurso.items : [];
  $("#com-count").textContent = items.length;
  $("#com-guardar").disabled = items.length === 0;
  if (!items.length) {
    $("#com-items").innerHTML = '<div class="del-vacio">Todavía no hay nada que preparar.<br>'
      + 'Traé el pedido del carrito, tocá un atajo o escribí una línea.</div>';
    return;
  }
  $("#com-items").innerHTML = items.map((it, i) => `
    <div class="del-item">
      <span class="n">${it.cantidad}</span>
      <span class="c"><b>${esc(it.texto)}</b>${it.detalle ? '<span class="d">' + esc(it.detalle) + "</span>" : ""}</span>
      <button class="quitar" data-quitar-com="${i}" title="Sacar de la comanda">✕</button>
    </div>`).join("");
}

/** Escribe una línea a mano para la comanda. */
function agregarTextoComanda() {
  const texto = $("#com-texto").value.trim();
  if (!texto) return;
  agregarItemComanda({
    texto, detalle: $("#com-detalle").value.trim(),
    producto_id: 0, cantidad: 1
  });
  $("#com-texto").value = "";
  $("#com-detalle").value = "";
  $("#com-detalle-wrap").hidden = true;
  $("#com-texto").focus();
}

/** Trae el carrito a la comanda: es lo que el cajero ya está por cobrar. */
function traerCarritoAComanda() {
  if (!comandaEnCurso) return;
  if (!estado.carrito.length) {
    aviso("El carrito está vacío: cargá algo antes de armar la comanda.", "aviso-w");
    return;
  }
  estado.carrito.forEach(l => {
    agregarItemComanda({
      texto: l.formato_unidad ? l.nombre + " (" + l.formato_unidad + ")" : l.nombre,
      detalle: null,
      producto_id: l.id,
      cantidad: Number(l.cantidad) || 1
    });
  });
  aviso("Se pasó el carrito a la comanda.", "ok");
}

/* ---------- Guardar e imprimir ---------- */

function leerCamposComanda() {
  const c = comandaEnCurso;
  c.cliente   = $("#com-cliente").value.trim();
  c.lugar     = $("#com-lugar").value.trim();
  c.direccion = $("#com-direccion").value.trim();
  c.notas     = $("#com-notas").value.trim();
}

/** Guarda la comanda sola: no cobra nada. El remito sale del carrito. */
function guardarComanda() {
  if (!comandaEnCurso) { aviso("No hay ninguna comanda armada.", "mal"); return; }
  leerCamposComanda();
  const c = comandaEnCurso;
  if (!c.items.length) {
    aviso("Anotá al menos una cosa para preparar.", "mal");
    return;
  }
  api("comanda_crear", {
    tipo: c.tipo, cliente: c.cliente, lugar: c.lugar,
    direccion: c.direccion, zona_id: c.zona_id || 0, notas: c.notas,
    items: c.items.map(it => ({
      texto: it.texto, detalle: it.detalle,
      producto_id: it.producto_id || 0, cantidad: it.cantidad
    }))
  }).then(r => {
    ultimaComanda = r.comanda;
    // Ya quedó en la base: si el cajero sigue cargando, arranca otra.
    comandaEnCurso = null;
    cerrarModal("#m-comanda");
    $("#com-imprimir").disabled = false;
    // El cajero se la lleva a la cocina: lo natural es printable ahí mismo.
    if (ultimaComanda) {
      confirmar(
        "Comanda #" + r.comanda.id + " guardada",
        "¿La imprimís ahora para llevársela a la cocina? Si no, la sacás "
        + "de nuevo desde el botón Imprimir."
      ).then(ok => { if (ok) imprimirComanda(r.comanda.id); });
    }
  }).catch(e => aviso(e.message, "mal"));
}

/** Imprime el papel de la última comanda guardada, sin volver a armarlo. */
function imprimirUltimaComanda() {
  if (!ultimaComanda) { aviso("Todavía no guardaste ninguna comanda.", "mal"); return; }
  imprimirComanda(ultimaComanda.id);
}
/** El precio de la comanda de texto libre no se cobra: es sólo instrucción. */
function itemsParaVenta() {
  return estado.carrito.map(l => ({
    id: l.id,
    formato_id: l.formato_id || 0,
    formato_unidad: l.formato_unidad || null,
    formato_factor: l.formato_factor || 1,
    cantidad: l.cantidad,
    precio: l.precio,
    // El destino lo decide el cajero linea por linea. Viaja con la venta para
    // que el servidor arme el papel de la cocina sin que el navegador le
    // mande una segunda lista de items.
    cocina: l.cocina ? 1 : 0
  }));
}

/* ---------- Tablero de cocina ---------- */

async function cargarComandas() {
  const cerrado = estado.cocFiltro === "cerradas";
  const r = await api("comandas", cerrado ? { incluir: "cerradas" } : undefined);
  const todas = r.comandas || [];
  const cuenta = { pendiente: 0, preparando: 0, listo: 0, entregado: 0, cancelado: 0 };
  todas.forEach(c => { cuenta[c.estado] = (cuenta[c.estado] || 0) + 1; });
  ["pendiente", "preparando", "listo"].forEach(e => {
    const el = $("#coc-n-" + e);
    if (el) el.textContent = cuenta[e] || 0;
  });
  const pend = cuenta.pendiente || 0;
  const badge = $("#coc-pend");
  badge.hidden = pend === 0;
  badge.textContent = pend;

  renderTablero(todas, cerrado);
}

/**
 * La cocina no está mirando la pantalla todo el rato, así que el tablero se
 * refresca solo. Sólo corre mientras la vista está abierta.
 */
function arrancarPollingCocina() {
  clearInterval(estado.cocTimer);
  estado.cocTimer = setInterval(() => {
    if (estado.vista === "cocina" && !document.hidden) {
      cargarComandas().catch(() => {});
    }
  }, 12000);
}

function renderTablero(todas, cerrado) {
  const cont = $("#coc-tablero");
  if (cerrado) {
    const f = todas.filter(c => c.estado === "entregado" || c.estado === "cancelado");
    if (!f.length) {
      cont.innerHTML = vacioCocina("📭", "Todavía no hay pedidos entregados.");
      return;
    }
    cont.innerHTML = f.map(c => tarjetaComanda(c, true)).join("");
    return;
  }
  const lista = todas.filter(c => c.estado === estado.cocFiltro);
  if (!lista.length) {
    const msj = {
      pendiente: ["👌", "No hay pedidos nuevos. La cocina está al día."],
      preparando: ["🔥", "No hay nada preparándose."],
      listo: ["📣", "No hay pedidos listos para entregar."]
    }[estado.cocFiltro] || ["🍳", "Nada por acá."];
    cont.innerHTML = vacioCocina(msj[0], msj[1]);
    return;
  }
  cont.innerHTML = lista.map(c => tarjetaComanda(c, false)).join("");
}

function vacioCocina(ic, txt) {
  return '<div class="coc-vacia"><span class="ic">' + ic + "</span>" + esc(txt) + "</div>";
}

function minutosDe(iso) {
  const t = new Date(String(iso).replace(" ", "T"));
  if (isNaN(t)) return 0;
  return Math.max(0, Math.floor((Date.now() - t.getTime()) / 60000));
}

function tarjetaComanda(c, historico) {
  const min = minutosDe(c.creado);
  // El color de la cabecera avisa de urgencia sin que nadie tenga que leer la hora.
  const urgente = !historico && (c.estado === "pendiente" ? min >= 12 : c.estado === "preparando" ? min >= 25 : false);
  const claseEstado = c.estado === "entregado" ? "entregado" : c.estado === "cancelado" ? "cancelado" : c.estado;
  const reloj = c.estado === "pendiente" && min >= 12 ? '<span class="etq mal">' + min + " min</span>"
    : c.estado === "preparando" && min >= 25 ? '<span class="etq mal">' + min + " min</span>"
    : '<span class="etq neutro">' + min + " min</span>";

  const datos = [];
  if (c.telefono) datos.push(["📞", esc(c.telefono)]);
  if (c.direccion) datos.push(["📍", esc(c.direccion)]);
  if (c.zona_nombre) datos.push(["🗺", esc(c.zona_nombre) + (Number(c.zona_id) > 0 && Number(c.envio) > 0 ? " · envío " + dinero(Number(c.envio)) : "")]);
  if (c.lugar) datos.push(["🍽", esc(c.lugar)]);

  const items = c.items.map(it => `
    <li class="com-item${it.estado === "listo" ? " listo" : ""}">
      <button class="chk" data-item-com="${it.id}" title="Marcar como listo">✓</button>
      <span class="txt">
        <b>${numeroLocal(it.cantidad, 0)}</b> ${esc(it.texto)}
        ${it.detalle ? '<span class="det">' + esc(it.detalle) + "</span>" : ""}
        ${it.es_producto ? '<span class="cobra">· cobrado</span>' : ""}
      </span>
    </li>`).join("");

  let pie = "";
  if (!historico && c.estado !== "cancelado") {
    if (c.estado === "pendiente") {
      pie = '<button class="btn pri" data-estado-com="preparando">🔥 Empezar</button>';
    } else if (c.estado === "preparando") {
      pie = '<button class="btn ok" data-estado-com="listo">✅ Listo</button>'
          + '<button class="btn" data-estado-com="pendiente">↩ Volver a nueva</button>';
    } else if (c.estado === "listo") {
      pie = '<button class="btn ok" data-estado-com="entregado">🛵 Entregado</button>'
          + '<button class="btn" data-estado-com="preparando">↩ Volver</button>';
    }
    pie += '<button class="btn sm" data-print-com="' + c.id + '" title="Imprimir la comanda">🖨</button>'
        + (esAdmin() ? '<button class="btn sm peligro" data-estado-com="cancelado" title="Cancelar">✕</button>' : "");
  }

  return `<article class="comanda ${claseEstado}${urgente ? " urgente" : ""}" data-comanda="${c.id}">
    <div class="com-cab">
      <div class="com-quien">
        <div class="com-cliente">${esc(c.cliente)}</div>
        <div class="com-meta">
          <span class="com-tipo ${c.tipo}">${ETIQUETA_TIPO[c.tipo] || c.tipo}</span>
          <span>${ETIQUETA_ESTADO[c.estado] || c.estado}</span>
          ${reloj}
        </div>
      </div>
      <span class="com-id">#${c.id}${c.folio ? " · f" + c.folio : ""}</span>
    </div>
    ${datos.length ? '<div class="com-datos">' + datos.map(d =>
      '<div class="d"><span class="ic">' + d[0] + "</span><span>" + d[1] + "</span></div>").join("") + "</div>" : ""}
    <ul class="com-items">${items}</ul>
    ${c.notas ? '<div class="com-notas">⚠️ ' + esc(c.notas) + "</div>" : ""}
    ${historico ? "" : '<div class="com-total">Total <b>' + dinero(c.total) + "</b></div>"}
    ${pie ? '<div class="com-pie">' + pie + "</div>" : ""}
  </article>`;
}

async function moverComanda(id, nuevo) {
  const r = await api("comanda_estado", { id, estado: nuevo });
  aplicarComanda(r.comanda);
  aviso("Comanda #" + id + " → " + (ETIQUETA_ESTADO[nuevo] || nuevo), "ok");
}

async function marcarItem(idItem) {
  const el = document.querySelector('[data-item-com="' + idItem + '"]');
  const marcar = !el || !el.closest(".com-item").classList.contains("listo");
  const r = await api("comanda_item", { id_item: idItem, estado: marcar ? "listo" : "pendiente" });
  aplicarComanda(r.comanda);
}

/** Reemplaza la tarjeta en el tablero sin volver a pedir todo. */
function aplicarComanda(c) {
  if (!c) return;
  const article = document.querySelector('[data-comanda="' + c.id + '"]');
  if (!article) { cargarComandas(); return; }
  const tmp = document.createElement("div");
  tmp.innerHTML = tarjetaComanda(c, estado.cocFiltro === "cerradas");
  const nuevo = tmp.firstElementChild;
  nuevo.className += " recien";
  article.replaceWith(nuevo);
  if (c.estado !== estado.cocFiltro && estado.cocFiltro !== "cerradas") {
    nuevo.style.opacity = "0";
    nuevo.style.transform = "scale(.96)";
    setTimeout(() => nuevo.remove(), 220);
  }
  // Los conteos de las pestañas hay que refrescarlos igual.
  const r = api("comandas", { incluir: "cerradas" }).then(x => {
    (x.comandas || []).forEach(k => {
      const el = $("#coc-n-" + k.estado);
      if (el) el.textContent = 0;   // se recalcula abajo
    });
    const cuenta = {};
    (x.comandas || []).forEach(k => { cuenta[k.estado] = (cuenta[k.estado] || 0) + 1; });
    ["pendiente", "preparando", "listo"].forEach(e => {
      const el = $("#coc-n-" + e);
      if (el) el.textContent = cuenta[e] || 0;
    });
  }).catch(() => {});
}

/* ---------- Ticket de cocina ---------- */

function imprimirComanda(id) {
  const cont = $("#ticket-comanda");
  api("comanda", { id }).then(r => {
    const c = r.comanda;
    const min = minutosDe(c.creado);
    const filas = c.items.map(it =>
      "<tr><td class='n'>" + numeroLocal(it.cantidad, 0) + "</td><td>"
      + esc(it.texto) + (it.detalle ? "<br><span class='d'>" + esc(it.detalle) + "</span>" : "")
      + "</td></tr>").join("");
    const datos = [];
    if (c.telefono) datos.push("Tel: " + esc(c.telefono));
    if (c.direccion) datos.push("Dom: " + esc(c.direccion));
    if (c.zona_nombre) datos.push("Zona: " + esc(c.zona_nombre));
    if (c.lugar) datos.push("Mesa: " + esc(c.lugar));
    cont.innerHTML = `
      <div class="tc-cab">
        <div class="tc-neg">COMANDA #${c.id}</div>
        <div class="tc-sub">${ETIQUETA_TIPO[c.tipo] || c.tipo} · ${esc(ETIQUETA_ESTADO[c.estado] || c.estado)} · ${min} min</div>
      </div>
      <div class="tc-cliente">${esc(c.cliente)}</div>
      ${datos.length ? '<div class="tc-datos">' + datos.join("<br>") + "</div>" : ""}
      ${c.notas ? '<div class="tc-obs">⚠ ' + esc(c.notas) + "</div>" : ""}
      <table class="tc-l">${filas}</table>
      <div class="tc-pie">Pedido folio ${c.folio || "—"} · pagado<br>${esc(estado.config.negocio || "")}</div>`;
    imprimirTicketAhora("comanda");
  }).catch(e => aviso("No se pudo imprimir: " + e.message, "mal"));
}
