/* =====================================================================
   KIOSCO — lógica de la interfaz
   Point of sale · control de existencias · reportes
   ===================================================================== */
"use strict";

/* =====================================================================
   1. UTILIDADES
   ===================================================================== */
const $  = (s, r) => (r || document).querySelector(s);
const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

const r2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

const esc = (s) => String(s == null ? "" : s)
  .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
  .replace(/"/g, "&quot;").replace(/'/g, "&#39;");

/** Quita acentos y pasa a minúsculas para comparar sin sorpresas. */
const norm = (s) => String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

function dinero(n, simbolo) {
  const v = r2(n);
  const txt = v.toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (simbolo === false) return txt;
  return (estado.config.moneda || "$") + txt;
}

function hoyISO() {
  const d = new Date();
  return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
}

function diasAtras(n) {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
}

function fechaHora(iso) {
  const d = new Date(String(iso).replace(" ", "T"));
  if (isNaN(d)) return "—";
  return d.toLocaleString("es-MX", {
    day: "2-digit", month: "2-digit", year: "2-digit", hour: "2-digit", minute: "2-digit"
  });
}

function numeroLocal(n, dec) {
  const v = Number(n) || 0;
  return v.toLocaleString("es-MX", {
    minimumFractionDigits: dec == null ? 0 : dec,
    maximumFractionDigits: dec == null ? 0 : dec
  });
}

function aviso(msg, tipo) {
  const d = document.createElement("div");
  d.className = "aviso" + (tipo ? " " + tipo : "");
  d.textContent = msg;
  $("#avisos").appendChild(d);
  setTimeout(() => {
    d.style.transition = "opacity 250ms";
    d.style.opacity = "0";
    setTimeout(() => d.remove(), 260);
  }, tipo === "mal" ? 4500 : 2300);
}

/** Descarga texto como archivo (con BOM para que Excel respete acentos). */
function descargar(texto, nombre, tipo) {
  const blob = new Blob(["﻿" + texto], { type: (tipo || "text/csv") + ";charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url; a.download = nombre;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1500);
}

/** Convierte una matriz en CSV compatible con Excel en español. */
function aCSV(filas) {
  return filas.map(f => f.map(c => {
    const v = c == null ? "" : String(c);
    return /[";\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
  }).join(";")).join("\r\n");
}

/* =====================================================================
   2. API
   ===================================================================== */
async function api(accion, datos) {
  const opciones = { headers: { "X-Requested-With": "kiosco" } };
  if (datos !== undefined) {
    opciones.method = "POST";
    opciones.headers["Content-Type"] = "application/json";
    opciones.body = JSON.stringify(datos);
  }
  let res, json;
  try {
    res = await fetch("api.php?accion=" + encodeURIComponent(accion), opciones);
  } catch (e) {
    throw new Error("No hay conexión con el servidor. ¿Sigue abierto Laragon?");
  }
  try {
    json = await res.json();
  } catch (e) {
    throw new Error("El servidor devolvió una respuesta inesperada (código " + res.status + ").");
  }
  if (!res.ok || !json.ok) {
    if (json.instalar) { window.location.href = "instalar.php"; }
    throw new Error(json.error || ("Error " + res.status));
  }
  return json;
}

/* =====================================================================
   3. ESTADO
   ===================================================================== */
const estado = {
  config: {},
  productos: [],
  carrito: [],
  descuento: 0,
  vista: "vender",
  filtroCat: "",
  busqueda: "",
  editId: null,
  fotoTmp: "",
  metodoCobro: "Efectivo",
  recibido: "",
  ultimaVenta: null,
  mediosPago: [{ id: 1, nombre: "Efectivo", icono: "💵", referencia: false }],
  proveedores: [],
  editMedioId: null,
  editProvId: null
};

const prodPorId = (id) => estado.productos.find(p => p.id === Number(id)) || null;

function categorias() {
  const set = new Set();
  estado.productos.forEach(p => { if (p.categoria) set.add(p.categoria); });
  return Array.from(set).sort((a, b) => a.localeCompare(b, "es"));
}

function estadoStock(p) {
  if (p.stock <= 0) return { clase: "mal", texto: "Agotado" };
  if (p.minimo > 0 && p.stock <= p.minimo) return { clase: "aviso", texto: "Bajo" };
  return { clase: "ok", texto: "Disponible" };
}

function emoji(p) {
  const c = norm(p.categoria || "");
  if (/bebida|agua|refresco|jugo|cerveza/.test(c)) return "🥤";
  if (/pan|dulce|galleta|botana|chocolate/.test(c)) return "🍪";
  if (/leche|queso|lacteo|yogur/.test(c)) return "🥛";
  if (/carne|pollo|jamon|embutido/.test(c)) return "🥩";
  if (/fruta|verdura/.test(c)) return "🍎";
  if (/limpieza|jabon|detergente|cloro/.test(c)) return "🧼";
  if (/papel|higiene|servilleta|bolsa/.test(c)) return "🧻";
  if (/cafe|te\b/.test(c)) return "☕";
  if (/cereal|avena|grano/.test(c)) return "🥣";
  if (/aceite|aceituna/.test(c)) return "🫒";
  if (/medicina|pastilla|vitamin/.test(c)) return "💊";
  if (/pila/.test(c)) return "🔋";
  return "📦";
}

function fotoHTML(p, clase) {
  if (p.foto) return '<div class="' + clase + '"><img src="' + esc(p.foto) + '" alt=""></div>';
  return '<div class="' + clase + '">' + emoji(p) + "</div>";
}

/* =====================================================================
   4. CARRITO
   ===================================================================== */
function agregar(id, cantidad) {
  const p = prodPorId(id);
  if (!p) { aviso("Ese producto ya no existe.", "mal"); return; }
  if (p.stock <= 0) aviso("⚠️ " + p.nombre + " está agotado. Se registra igual y el stock queda en negativo.", "aviso-w");
  else if (p.minimo > 0 && p.stock - (cantidad || 1) <= p.minimo) {
    aviso("Quedan solo " + numeroLocal(p.stock, 0) + " de " + p.nombre + ".", "aviso-w");
  }

  const linea = estado.carrito.find(l => l.id === p.id);
  if (linea) linea.cantidad = r2(linea.cantidad + (cantidad || 1));
  else estado.carrito.push({ id: p.id, nombre: p.nombre, precio: p.precio, cantidad: r2(cantidad || 1) });

  renderCarrito();
  $("#txt-buscar").value = "";
  $("#txt-buscar").focus();
}

function cambiarCantidad(id, delta) {
  const l = estado.carrito.find(x => x.id === Number(id));
  if (!l) return;
  l.cantidad = r2(l.cantidad + delta);
  if (l.cantidad <= 0) estado.carrito = estado.carrito.filter(x => x.id !== l.id);
  renderCarrito();
}

function quitarLinea(id) {
  estado.carrito = estado.carrito.filter(x => x.id !== Number(id));
  renderCarrito();
}

function vaciarCarrito() {
  estado.carrito = [];
  estado.descuento = 0;
  renderCarrito();
}

const subtotalCarrito = () => r2(estado.carrito.reduce((s, l) => s + l.precio * l.cantidad, 0));
const totalCarrito   = () => r2(Math.max(0, subtotalCarrito() - estado.descuento));
const piezasCarrito  = () => estado.carrito.reduce((s, l) => s + l.cantidad, 0);

function renderCarrito() {
  const cont = $("#carrito-items");

  if (!estado.carrito.length) {
    cont.innerHTML = '<div class="vacio" style="padding:36px 12px">'
      + '<div class="ico">🛒</div><h3>El ticket está vacío</h3>'
      + '<p>Toca un producto de la izquierda o escanea su código.</p></div>';
  } else {
    cont.innerHTML = estado.carrito.map(l => `
      <div class="item">
        <div class="info">
          <div class="n" title="${esc(l.nombre)}">${esc(l.nombre)}</div>
          <div class="p">${dinero(l.precio)} c/u</div>
        </div>
        <div class="cant">
          <button data-menos="${l.id}" title="Quitar uno">−</button>
          <input type="number" step="1" min="0" value="${l.cantidad}" data-cant="${l.id}" aria-label="Cantidad">
          <button data-mas="${l.id}" title="Agregar uno">+</button>
        </div>
        <div class="imp">${dinero(l.precio * l.cantidad)}</div>
        <button class="quitar" data-quitar="${l.id}" title="Quitar la línea">✕</button>
      </div>`).join("");
  }

  const sub = subtotalCarrito();
  const tot = totalCarrito();
  $("#c-count").textContent = piezasCarrito();
  $("#c-sub").textContent = dinero(sub);
  $("#c-total").textContent = dinero(tot);
  $("#fila-desc").style.display = estado.descuento > 0 ? "" : "none";
  $("#c-desc").textContent = "−" + dinero(estado.descuento);
  $("#btn-cobrar").disabled = estado.carrito.length === 0;
}

/* =====================================================================
   5. PUNTO DE VENTA
   ===================================================================== */
function Coincidencias(texto) {
  const q = norm(texto).trim();
  const base = estado.productos.filter(p => p.activo !== false);
  if (!q) return base;
  return base.map(p => {
    const n = norm(p.nombre), c = norm(p.codigo), cat = norm(p.categoria);
    if (c === q) return { p, r: 0 };
    if (n.startsWith(q)) return { p, r: 1 };
    if (c && c.startsWith(q)) return { p, r: 2 };
    if (n.includes(q)) return { p, r: 3 };
    if (cat.includes(q)) return { p, r: 4 };
    return null;
  }).filter(Boolean).sort((a, b) => a.r - b.r).map(x => x.p);
}

function renderFiltros() {
  const cats = categorias();
  const cont = $("#filtros");
  if (!cats.length) { cont.innerHTML = ""; return; }

  const cuenta = (c) => estado.productos.filter(p => p.categoria === c && p.activo !== false).length;
  cont.innerHTML = '<button class="chip' + (estado.filtroCat === "" ? " on" : "") + '" data-cat="">'
    + 'Todos <b>' + estado.productos.filter(p => p.activo !== false).length + '</b></button>'
    + cats.map(c => '<button class="chip' + (estado.filtroCat === c ? " on" : "") + '" data-cat="' + esc(c) + '">'
      + esc(c) + ' <b>' + cuenta(c) + '</b></button>').join("");
}

function renderGrid() {
  const cont = $("#grid-productos");
  let lista = Coincidencias(estado.busqueda);
  if (estado.filtroCat) lista = lista.filter(p => p.categoria === estado.filtroCat);

  if (!estado.productos.length) {
    cont.innerHTML = '<div class="vacio" style="grid-column:1/-1">'
      + '<div class="ico">📦</div><h3>No hay productos en el catálogo</h3>'
      + '<p>Agrega tu primer producto para empezar a vender.</p>'
      + '<button class="btn pri" onclick="ir(\'productos\');abrirProducto()">+ Crear producto</button></div>';
    return;
  }
  if (!lista.length) {
    cont.innerHTML = '<div class="vacio" style="grid-column:1/-1">'
      + '<div class="ico">🔍</div><h3>Nada coincide con «' + esc(estado.busqueda) + '»</h3>'
      + '<p>Prueba con menos palabras o revisa el código de barras.</p></div>';
    return;
  }

  cont.innerHTML = lista.map(p => {
    const e = estadoStock(p);
    const marca = p.stock <= 0 ? '<span class="marca">agotado</span>'
      : (p.minimo > 0 && p.stock <= p.minimo ? '<span class="marca bajo">bajo</span>' : "");
    return `<div class="prod${p.stock <= 0 ? " sin-stock" : ""}" data-prod="${p.id}" title="${esc(p.nombre)}">
      ${marca}
      ${fotoHTML(p, "foto")}
      <div class="nom">${esc(p.nombre)}</div>
      <div class="prez">${dinero(p.precio)}</div>
      <div class="stk">${p.stock > 0 ? numeroLocal(p.stock, 0) + " " + esc(p.unidad || "pieza") : "sin stock"}</div>
    </div>`;
  }).join("");
}

function renderPOS() {
  renderFiltros();
  renderGrid();
  renderCarrito();
}

/* =====================================================================
   6. COBRO
   ===================================================================== */
function abrirCobro() {
  if (!estado.carrito.length) { aviso("El ticket está vacío.", "aviso-w"); return; }
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

  try {
    const r = await api("venta_crear", {
      items: estado.carrito.map(l => ({ id: l.id, cantidad: l.cantidad, precio: l.precio })),
      descuento: estado.descuento,
      metodo: estado.metodoCobro,
      recibido: efectivo ? recibido : -1,
      vuelto: efectivo ? r2(recibido - totalCarrito()) : 0,
      referencia: $("#cob-ref").value.trim()
    });

    cerrarModal("#m-cobro");
    vaciarCarrito();
    estado.ultimaVenta = r.venta;

    if (r.sin_stock && r.sin_stock.length) {
      aviso("⚠️ Quedó en negativo: " + r.sin_stock.join(", "), "aviso-w");
    }

    mostrarVenta(r.venta, true);
    await Promise.all([cargarProductos(), refrescarCabecera()]);
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
   7. PRODUCTOS
   ===================================================================== */
function renderProductos() {
  const tb = $("#p-tbody");
  const q = norm(estado.busquedaP);
  let lista = estado.productos;
  if (q) {
    lista = lista.filter(p => norm(p.nombre).includes(q) || norm(p.codigo).includes(q) || norm(p.categoria).includes(q));
  }

  $("#p-count").textContent = estado.productos.length;

  if (!lista.length) {
    tb.innerHTML = '<tr><td colspan="11"><div class="vacio" style="padding:34px">'
      + '<div class="ico">' + (estado.productos.length ? "🔍" : "📦") + "</div>"
      + "<h3>" + (estado.productos.length ? "Ningún producto coincide" : "El catálogo está vacío") + "</h3>"
      + "<p>" + (estado.productos.length ? "Prueba con otro texto." : "Crea tu primer producto o importa un CSV.") + "</p>"
      + (estado.productos.length ? "" : '<button class="btn pri" onclick="abrirProducto()">+ Nuevo producto</button>')
      + "</div></td></tr>";
    return;
  }

  const cats = categorias();
  const listaCats = $("#lista-cat");
  if (listaCats) listaCats.innerHTML = cats.map(c => '<option value="' + esc(c) + '">').join("");

  tb.innerHTML = lista.map(p => {
    const e = estadoStock(p);
    const margen = p.margen;
    const etqMargen = p.costo > 0
      ? (margen < 0 ? "mal" : (margen < 15 ? "aviso" : "ok"))
      : "neutro";
    return `<tr>
      <td>${fotoHTML(p, "avatar")}</td>
      <td><strong>${esc(p.nombre)}</strong>${p.activo ? "" : ' <span class="etq neutro">inactivo</span>'}
        ${p.proveedor ? '<br><span class="fuente" style="font-size:11.5px">🚚 ' + esc(p.proveedor) + "</span>" : ""}
        ${p.observaciones ? '<br><span class="fuente" style="font-size:11.5px" title="' + esc(p.observaciones) + '">📝 ' + esc(p.observaciones.slice(0, 40)) + (p.observaciones.length > 40 ? "…" : "") + "</span>" : ""}</td>
      <td class="fuente">${p.codigo ? esc(p.codigo) : "—"}</td>
      <td>${p.categoria ? esc(p.categoria) : "—"}</td>
      <td class="num">${dinero(p.precio)}</td>
      <td class="num fuente">${p.costo > 0 ? dinero(p.costo) : "—"}</td>
      <td class="num"><span class="etq ${etqMargen}">${p.costo > 0 ? numeroLocal(margen, 1) + "%" : "—"}</span></td>
      <td class="num"><strong>${numeroLocal(p.stock, 0)}</strong> <span class="fuente">${esc(p.unidad || "")}</span></td>
      <td>${p.minimo > 0 ? numeroLocal(p.minimo, 0) : "—"}</td>
      <td><span class="etq ${e.clase}">${e.texto}</span></td>
      <td class="acciones">
        <button class="btn sm" data-editar="${p.id}" title="Editar">✎</button>
        <button class="btn sm" data-stock="${p.id}" title="Entrada / salida de mercancía">📥</button>
        <button class="btn sm peligro" data-borrar="${p.id}" title="Eliminar">🗑</button>
      </td>
    </tr>`;
  }).join("");
}

/** Rellena el selector de proveedores del modal de producto. */
function llenarProveedores(seleccionado) {
  const sel = $("#mp-proveedor");
  if (!sel) return;
  sel.innerHTML = '<option value="0">— sin proveedor —</option>'
    + estado.proveedores.filter(p => p.activo).map(p =>
        '<option value="' + p.id + '"' + (String(p.id) === String(seleccionado || 0) ? " selected" : "") + ">"
        + esc(p.nombre) + "</option>").join("");
  sel.value = String(seleccionado || 0);
}

/** Calcula y muestra el margen mientras se escribe precio y costo. */
function refrescarMargen() {
  const precio = Number($("#mp-precio").value) || 0;
  const costo = Number($("#mp-costo").value) || 0;
  const caja = $("#mp-margen");
  if (!caja) return;
  if (precio <= 0) { caja.style.display = "none"; return; }
  caja.style.display = "";
  const utilidad = r2(precio - costo);
  const pct = r2((utilidad / precio) * 100);
  let color = "var(--ok)", texto = "";
  if (costo <= 0) {
    color = "var(--muted)";
    texto = "<b>" + dinero(precio) + "</b> <span class='fuente'>(sin costo cargado)</span>";
  } else if (pct < 0) {
    color = "var(--bad)";
    texto = "<b style='color:" + color + "'>" + dinero(utilidad) + " · " + numeroLocal(pct, 1) + "%</b> <span class='fuente'>— vendés a pérdida</span>";
  } else {
    if (pct < 15) color = "var(--warn)";
    texto = "<b style='color:" + color + "'>" + dinero(utilidad) + " · " + numeroLocal(pct, 1) + "%</b>"
      + " <span class='fuente'>por unidad</span>";
  }
  $("#mp-margen-valor").innerHTML = texto;
}

async function abrirProducto(id) {
  estado.editId = id || null;
  estado.fotoTmp = "";

  if (id) {
    const p = prodPorId(id);
    if (!p) { aviso("Producto no encontrado.", "mal"); return; }
    $("#mp-titulo").textContent = "Editar producto";
    $("#mp-nombre").value = p.nombre;
    $("#mp-codigo").value = p.codigo || "";
    $("#mp-categoria").value = p.categoria || "";
    $("#mp-precio").value = p.precio;
    $("#mp-costo").value = p.costo || 0;
    $("#mp-stock").value = p.stock;
    $("#mp-minimo").value = p.minimo;
    $("#mp-unidad").value = p.unidad || "pieza";
    $("#mp-observaciones").value = p.observaciones || "";
    llenarProveedores(p.proveedor_id);
    estado.fotoTmp = p.foto || "";
    $("#mp-foto").innerHTML = estado.fotoTmp
      ? '<img src="' + esc(estado.fotoTmp) + '" alt="">'
      : emoji({ categoria: p.categoria });
    $("#mp-borrar").style.display = "";
    refrescarMargen();
    await cargarKardex(id);
  } else {
    $("#mp-titulo").textContent = "Nuevo producto";
    ["#mp-nombre", "#mp-codigo", "#mp-categoria", "#mp-precio", "#mp-costo",
     "#mp-minimo", "#mp-observaciones"].forEach(s => { $(s).value = ""; });
    $("#mp-stock").value = 0;
    $("#mp-unidad").value = "pieza";
    llenarProveedores(0);
    estado.fotoTmp = "";
    $("#mp-foto").innerHTML = "📦";
    $("#mp-borrar").style.display = "none";
    $("#mp-kardex").style.display = "none";
    refrescarMargen();
  }
  abrirModal("#m-prod");
  setTimeout(() => $("#mp-nombre").focus(), 120);
}

async function cargarKardex(id) {
  try {
    const r = await api("producto", { id });
    const k = r.kardex || [];
    $("#mp-kardex").style.display = k.length ? "" : "none";
    $("#mp-kardex-tb").innerHTML = k.map(m => {
      const etq = m.cantidad > 0 ? "ok" : (m.tipo === "venta" ? "neutro" : "aviso");
      return `<tr>
        <td class="fuente">${fechaHora(m.fecha)}</td>
        <td><span class="etq ${etq}">${esc(m.tipo)}</span></td>
        <td class="num">${m.cantidad > 0 ? "+" : ""}${numeroLocal(m.cantidad, 0)}</td>
        <td class="num">${numeroLocal(m.stock_anterior, 0)} → <strong>${numeroLocal(m.stock_actual, 0)}</strong></td>
        <td class="fuente">${esc(m.referencia || "")}</td>
      </tr>`;
    }).join("");
  } catch (e) { /* sin kardex */ }
}

async function guardarProducto() {
  const datos = {
    id: estado.editId || 0,
    nombre: $("#mp-nombre").value.trim(),
    codigo: $("#mp-codigo").value.trim(),
    categoria: $("#mp-categoria").value.trim(),
    precio: Number($("#mp-precio").value) || 0,
    costo: Number($("#mp-costo").value) || 0,
    stock: Number($("#mp-stock").value) || 0,
    minimo: Number($("#mp-minimo").value) || 0,
    unidad: $("#mp-unidad").value,
    foto: estado.fotoTmp,
    observaciones: $("#mp-observaciones").value.trim(),
    proveedor_id: Number($("#mp-proveedor").value) || 0,
    activo: 1
  };
  if (!datos.nombre) { aviso("Escribe el nombre del producto.", "aviso-w"); $("#mp-nombre").focus(); return; }
  if (datos.precio <= 0) { aviso("El precio de venta debe ser mayor que cero.", "aviso-w"); $("#mp-precio").focus(); return; }
  if (datos.costo > datos.precio) {
    const ok = await confirmar("Costo mayor que el precio",
      "El costo (" + dinero(datos.costo) + ") es mayor que el precio de venta (" + dinero(datos.precio) +
      "). Cada venta de este producto te daría pérdida. ¿Guardar igual?");
    if (!ok) { $("#mp-costo").focus(); return; }
  }

  try {
    await api("producto_guardar", datos);
    cerrarModal("#m-prod");
    aviso(estado.editId ? "Producto actualizado." : "Producto creado.", "ok");
    await cargarProductos();
    if (estado.vista === "productos") renderProductos();
    renderFiltros();
    renderGrid();
  } catch (e) {
    aviso(e.message, "mal");
  }
}

async function borrarProducto(id) {
  const p = prodPorId(id);
  if (!p) return;
  const ok = await confirmar(
    "Eliminar «" + p.nombre + "»",
    "Se quitará del catálogo. Las ventas ya registradas se conservan con su nombre y su importe, "
    + "pero las existencias dejarán de descontarse. Esta acción no se puede deshacer."
  );
  if (!ok) return;
  try {
    await api("producto_borrar", { id });
    aviso("Producto eliminado.", "ok");
    estado.carrito = estado.carrito.filter(l => l.id !== Number(id));
    await cargarProductos();
    renderPOS();
    if (estado.vista === "productos") renderProductos();
  } catch (e) {
    aviso(e.message, "mal");
  }
}

/* --- Carga de imagen del producto (reducida a 128 px para que no pese mucho) --- */
function leerImagen(archivo) {
  const lector = new FileReader();
  lector.onload = () => {
    const img = new Image();
    img.onload = () => {
      const lado = 128;
      const lienzo = document.createElement("canvas");
      lienzo.width = lado; lienzo.height = lado;
      const ctx = lienzo.getContext("2d");
      const corte = Math.min(img.width, img.height);
      lienzo.drawImage(img, (img.width - corte) / 2, (img.height - corte) / 2, corte, corte, 0, 0, lado, lado);
      estado.fotoTmp = lienzo.toDataURL("image/jpeg", 0.6);
      $("#mp-foto").innerHTML = '<img src="' + estado.fotoTmp + '" alt="">';
    };
    img.onerror = () => aviso("No se pudo leer la imagen.", "mal");
    img.src = lector.result;
  };
  lector.readAsDataURL(archivo);
}

/* =====================================================================
   8. MOVIMIENTOS DE MERCANCERÍA
   ===================================================================== */
let stockSel = null;

function abrirStock(tipo) {
  stockSel = null;
  const entrada = tipo !== "salida";
  $("#ms-titulo").textContent = entrada ? "📥 Entrada de mercancía" : "📤 Salida de mercancía";
  $("#ms-buscar").value = "";
  $("#ms-lista").innerHTML = "";
  $("#ms-form").style.display = "none";
  $("#ms-guardar").disabled = true;
  $("#ms-cant").value = 1;
  $("#ms-motivo").value = entrada ? "Compra a proveedor" : "Merma";
  $("#ms-documento").value = "";
  $("#ms-costo").value = 0;
  $("#ms-guardar").dataset.tipo = tipo;
  $("#ms-compra").style.display = entrada ? "" : "none";
  $("#ms-ayuda").textContent = entrada
    ? "Registrá el proveedor y el número de remito para saber de dónde salió cada mercadería. Si cargás un costo unitario nuevo, se actualiza el margen del producto."
    : "Las salidas no cambian el costo del producto: solo descuentan existencias.";
  llenarProveedoresStock(0);
  abrirModal("#m-stock");
  setTimeout(() => $("#ms-buscar").focus(), 120);
}

function llenarProveedoresStock(seleccionado) {
  const sel = $("#ms-proveedor");
  if (!sel) return;
  sel.innerHTML = '<option value="0">— sin proveedor —</option>'
    + estado.proveedores.filter(p => p.activo).map(p =>
        '<option value="' + p.id + '"' + (String(p.id) === String(seleccionado || 0) ? " selected" : "") + ">"
        + esc(p.nombre) + "</option>").join("");
  sel.value = String(seleccionado || 0);
}

function filtrarStock() {
  const q = norm($("#ms-buscar").value);
  const lista = Coincidencias($("#ms-buscar").value).slice(0, 30);
  $("#ms-lista").innerHTML = lista.map(p => `
    <div class="suger" data-sel="${p.id}">
      ${fotoHTML(p, "avatar")}
      <div class="info">
        <div class="n">${esc(p.nombre)}</div>
        <div class="s">Stock actual: ${numeroLocal(p.stock, 0)} ${esc(p.unidad || "")}</div>
      </div>
      <span class="etq ${estadoStock(p).clase}">${dinero(p.precio)}</span>
    </div>`).join("") || (q ? '<div style="padding:14px;text-align:center;color:var(--muted)">Sin resultados</div>' : "");
}

function elegirStock(id) {
  stockSel = prodPorId(id);
  if (!stockSel) return;
  $("#ms-lista").innerHTML = "";
  $("#ms-buscar").value = stockSel.nombre;
  $("#ms-form").style.display = "";
  const extra = stockSel.costo > 0
    ? '<span class="etq neutro">costo ' + dinero(stockSel.costo) + " · margen " + numeroLocal(stockSel.margen, 1) + "%</span>"
    : '<span class="etq aviso">sin costo cargado</span>';
  $("#ms-info").innerHTML = "<span>" + esc(stockSel.nombre) + "</span>"
    + '<span class="etq neutro">' + numeroLocal(stockSel.stock, 0) + " " + esc(stockSel.unidad || "") + " en existencia</span> " + extra;
  $("#ms-guardar").disabled = false;
  $("#ms-cant").focus();
  $("#ms-cant").select();
}

async function guardarStock() {
  if (!stockSel) return;
  const cant = Number($("#ms-cant").value) || 0;
  if (cant <= 0) { aviso("La cantidad debe ser mayor que cero.", "aviso-w"); return; }
  const tipo = $("#ms-guardar").dataset.tipo;
  const cuerpo = {
    id: stockSel.id, tipo: tipo,
    cantidad: cant, referencia: $("#ms-motivo").value.trim()
  };
  if (tipo !== "salida") {
    cuerpo.proveedor_id = Number($("#ms-proveedor").value) || 0;
    cuerpo.documento = $("#ms-documento").value.trim();
    const costo = Number($("#ms-costo").value) || 0;
    if (costo > 0) cuerpo.costo_unitario = costo;
  }
  try {
    const r = await api("stock_mover", cuerpo);
    cerrarModal("#m-stock");
    let msg = "Movimiento registrado. Stock actual: " + numeroLocal(r.stock, 0);
    if (r.costo > 0 && Number($("#ms-costo").value) > 0) {
      msg += " · costo actualizado a " + dinero(r.costo);
    }
    aviso(msg, "ok");
    await cargarProductos();
    renderPOS();
    if (estado.vista === "productos") renderProductos();
  } catch (e) { aviso(e.message, "mal"); }
}

/* =====================================================================
   9. HISTORIAL
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

/* =====================================================================
   10. DETALLE DE VENTA / TICKET
   ===================================================================== */
function mostrarVenta(v, recienHecha) {
  estado.ultimaVenta = v;
  $("#mv-titulo").textContent = (recienHecha ? "✅ Venta registrada · #" : "Venta #") + v.folio;

  const filas = (v.items || []).map(i => `
    <tr>
      <td>${esc(i.nombre)}</td>
      <td class="num fuente">${numeroLocal(i.cantidad, 0)} × ${dinero(i.precio)}</td>
      <td class="num">${dinero(i.importe)}</td>
    </tr>`).join("");

  let extra = "";
  if (v.metodo === "Efectivo" && v.recibido != null) {
    extra = '<div class="aviso-linea" style="margin-top:12px"><span>Efectivo recibido</span><strong>'
      + dinero(v.recibido) + "</strong></div>"
      + '<div class="aviso-linea" style="margin-top:6px; background:var(--okbg); color:var(--ok)">'
      + "<span>Vuelto</span><strong>" + dinero(v.vuelto) + "</strong></div>";
  }
  if (v.descuento > 0) {
    extra = '<div class="aviso-linea" style="margin-top:12px"><span>Descuento aplicado</span><strong style="color:var(--ok)">−'
      + dinero(v.descuento) + "</strong></div>" + extra;
  }
  if (v.referencia) {
    extra += '<div class="aviso-linea" style="margin-top:6px"><span>Referencia</span><strong>' + esc(v.referencia) + "</strong></div>";
  }
  if (v.anulada) {
    extra += '<div class="aviso-linea" style="margin-top:6px;background:var(--badbg);color:var(--bad)">'
      + "<span>⚠ Esta venta fue anulada</span></div>";
  }

  $("#mv-cue").innerHTML = `
    <div class="rejilla" style="margin-bottom:14px">
      <div class="campo"><label>Folio</label><strong style="font-size:17px">#${v.folio}</strong></div>
      <div class="campo"><label>Fecha</label><strong>${fechaHora(v.fecha)}</strong></div>
      <div class="campo"><label>Método de pago</label><strong>${esc(v.metodo)}</strong></div>
    </div>
    <div class="envoltura"><table class="tabla">
      <thead><tr><th>Artículo</th><th class="num" style="width:130px">Cant. × precio</th><th class="num" style="width:110px">Importe</th></tr></thead>
      <tbody>${filas}</tbody>
    </table></div>
    <div class="aviso-linea" style="margin-top:12px; background:var(--panel2)">
      <span>Subtotal ${v.descuento > 0 ? "− descuento " + dinero(v.descuento) : ""}</span>
      <strong style="font-size:19px">${dinero(v.total)}</strong>
    </div>
    ${extra}`;

  $("#mv-imprimir").onclick = () => imprimirTicket(v);
  abrirModal("#m-venta");
}

function imprimirTicket(v) {
  const c = estado.config;
  const lineas = (v.items || []).map(i =>
    "<tr><td colspan='2'>" + esc(i.nombre) + "</td></tr>"
    + "<tr><td>&nbsp;&nbsp;" + numeroLocal(i.cantidad, 0) + " x " + dinero(i.precio, false) + "</td>"
    + "<td class='d'>" + dinero(i.importe, false) + "</td></tr>"
  ).join("");

  let pago = "";
  if (v.metodo === "Efectivo" && v.recibido != null) {
    pago = "<div class='t-l' style='margin-top:5px'>"
      + "<tr><td>Recibido</td><td class='d'>" + dinero(v.recibido, false) + "</td></tr>"
      + "<tr><td>VUELTO</td><td class='d'>" + dinero(v.vuelto, false) + "</td></tr></div>";
  } else {
    pago = "<div class='t-l' style='margin-top:5px'><tr><td>Pago</td><td class='d'>" + esc(v.metodo) + "</td></tr></div>";
  }

  $("#ticket").innerHTML = `
    <div class="t-cab">
      <div class="t-neg">${esc(c.negocio || "Kiosco")}</div>
      ${c.direccion ? "<div class='t-sub'>" + esc(c.direccion) + "</div>" : ""}
      ${c.telefono ? "<div class='t-sub'>Tel. " + esc(c.telefono) + "</div>" : ""}
    </div>
    <div class="t-l">
      <tr><td>Folio</td><td class="d">#${v.folio}</td></tr>
      <tr><td>Fecha</td><td class="d">${fechaHora(v.fecha)}</td></tr>
    </div>
    <div class="t-l" style="margin-top:6px">${lineas}</div>
    <div class="t-tot t-l">
      <tr><td>TOTAL</td><td class="d">${dinero(v.total, false)}</td></tr>
    </div>
    ${pago}
    ${v.descuento > 0 ? "<div class='t-l'><tr><td>Descuento</td><td class='d'>-" + dinero(v.descuento, false) + "</td></tr></div>" : ""}
    <div class="t-pie">
      ${esc(c.pie || "")}
      <div style="margin-top:5px">*** Gracias ***</div>
    </div>`;

  window.print();
}

/* =====================================================================
   11. REPORTES
   ===================================================================== */
async function renderReportes() {
  const d = $("#r-desde").value || hoyISO();
  const h = $("#r-hasta").value || hoyISO();
  $("#r-stats").innerHTML = '<div class="stat"><div class="cap">Cargando…</div></div>';

  try {
    const r = await api("reportes", { desde: d, hasta: h });
    const s = r.resumen;

    $("#r-stats").innerHTML = `
      <div class="stat acento"><div class="cap">Ventas</div><div class="val">${numeroLocal(s.ventas, 0)}</div>
        <div class="sub">${d === h ? "hoy" : "en el periodo"}</div></div>
      <div class="stat"><div class="cap">Importe total</div><div class="val">${dinero(s.total)}</div>
        <div class="sub">${s.descuentos > 0 ? "descuentos: " + dinero(s.descuentos) : "sin descuentos"}</div></div>
      <div class="stat"><div class="cap">Ticket promedio</div><div class="val">${dinero(s.promedio)}</div>
        <div class="sub">mayor: ${dinero(s.mayor)}</div></div>
      <div class="stat"><div class="cap" style="color:var(--ok)">Ganancia bruta</div>
        <div class="val" style="color:var(--ok)">${dinero(s.utilidad)}</div>
        <div class="sub">costo de mercancía: ${dinero(s.costo)} · margen ${numeroLocal(s.margen, 1)}%</div></div>
      <div class="stat"><div class="cap">Inventario a costo</div><div class="val">${dinero(s.costo_inventario)}</div>
        <div class="sub">a venta: ${dinero(s.inventario)} · en anaquel: ${dinero(s.ganancia_potencial)}</div></div>
      <div class="stat"><div class="cap">Anuladas</div><div class="val" style="${s.anuladas ? "color:var(--bad)" : ""}">${numeroLocal(s.anuladas, 0)}</div>
        <div class="sub">${numeroLocal(s.unidades, 0)} piezas en almacén</div></div>`;

    // Gráfica por hora
    const horas = r.por_hora;
    const maxH = horas.length ? Math.max.apply(null, horas.map(h2 => h2.total)) : 0;
    let barras = "";
    for (let h2 = 0; h2 < 24; h2++) {
      const d2 = horas.find(x => x.h === h2);
      const val = d2 ? d2.total : 0;
      const alt = maxH > 0 ? Math.max(2, (val / maxH) * 100) : 0;
      barras += '<div class="b" title="' + (d2 ? numeroLocal(d2.ventas, 0) + " ventas · " + dinero(val) : "sin ventas") + '">'
        + (val > 0 ? "<span class='vl'>" + dinero(val, false) + "</span>" : "")
        + "<div class='barra' style='height:" + alt + "%'></div>"
        + "<span class='lb'>" + (h2 % 3 === 0 ? h2 + "h" : "") + "</span></div>";
    }
    $("#r-horas").innerHTML = barras;

    // Top productos
    $("#r-top").innerHTML = r.top.length ? r.top.map((t, i) => `
      <tr>
        <td class="fuente">${i < 3 ? ["🥇", "🥈", "🥉"][i] : (i + 1)}</td>
        <td>${esc(t.nombre)}</td>
        <td class="num">${numeroLocal(t.unidades, 0)}</td>
        <td class="num">${dinero(t.vendido)}
          <br><span class="fuente" style="font-size:11px">margen ${t.costo > 0 ? numeroLocal(t.margen, 1) + "%" : "—"}</span></td>
      </tr>`).join("")
      : '<tr><td colspan="4" style="padding:22px;text-align:center;color:var(--muted)">Sin ventas en el periodo</td></tr>';

    // Formas de pago
    $("#r-pago").innerHTML = r.por_pago.length ? r.por_pago.map(m => `
      <tr>
        <td>${esc(m.metodo)}</td>
        <td class="num">${numeroLocal(m.ventas, 0)}</td>
        <td class="num">${dinero(m.total)}</td>
        <td class="num fuente">${s.total > 0 ? Math.round(m.total / s.total * 100) : 0}%</td>
      </tr>`).join("")
      : '<tr><td colspan="4" style="padding:22px;text-align:center;color:var(--muted)">Sin datos</td></tr>';

    // Por reponer
    $("#r-faltantes").innerHTML = r.faltantes.length ? r.faltantes.map(f => {
      const sugerido = Math.max(f.minimo * 2 - f.stock, f.minimo > 0 ? 1 : 0, 1);
      return `<tr>
        <td>${esc(f.nombre)}</td>
        <td class="num"><span class="etq ${f.stock <= 0 ? "mal" : "aviso"}">${numeroLocal(f.stock, 0)}</span></td>
        <td class="num fuente">${numeroLocal(f.minimo, 0)}</td>
        <td class="num">${numeroLocal(Math.round(sugerido), 0)} ${esc(f.unidad || "")}</td>
        <td class="num">${dinero(sugerido * f.precio)}</td>
      </tr>`;
    }).join("")
      : '<tr><td colspan="5" style="padding:22px;text-align:center;color:var(--ok)">✓ Todo el inventario está por encima del mínimo</td></tr>';

    // Botón lista de compra
    $("#btn-r-compra").onclick = () => {
      if (!r.faltantes.length) { aviso("No hay productos por reponer.", "ok"); return; }
      const filas = [["Producto", "Categoría", "Stock actual", "Mínimo", "Sugerido", "Unidad", "Costo estimado"]];
      r.faltantes.forEach(f => {
        const sug = Math.round(Math.max(f.minimo * 2 - f.stock, 1));
        filas.push([f.nombre, "", numeroLocal(f.stock, 0), numeroLocal(f.minimo, 0), sug, f.unidad || "", r2(sug * f.precio).toFixed(2)]);
      });
      descargar(aCSV(filas), "lista-de-compra-" + hoyISO() + ".csv");
      aviso("Lista de compra descargada.", "ok");
    };
  } catch (e) {
    $("#r-stats").innerHTML = '<div class="stat"><div class="cap">Error</div><div class="val" style="font-size:16px;color:var(--bad)">'
      + esc(e.message) + "</div></div>";
  }
}

/* =====================================================================
   12. AJUSTES
   ===================================================================== */

/* --- Medios de pago --- */
async function cargarMediosPago() {
  try {
    const r = await api("medios_pago", { todos: 1 });
    estado.mediosTodos = r.medios;
    const tb = $("#a-mp-tb");
    if (!tb) return;
    tb.innerHTML = r.medios.length ? r.medios.map(m => `
      <tr>
        <td style="font-size:19px">${esc(m.icono)}</td>
        <td><strong>${esc(m.nombre)}</strong>${m.efectivo ? ' <span class="etq ok">recibe vuelto</span>' : ""}</td>
        <td>${m.referencia ? '<span class="etq neutro">sí</span>' : '<span class="fuente">no</span>'}</td>
        <td>${m.activo ? '<span class="etq ok">Activo</span>' : '<span class="etq neutro">Inactivo</span>'}</td>
        <td class="acciones">
          <button class="btn sm" data-mp-editar="${m.id}">✎</button>
          <button class="btn sm peligro" data-mp-borrar="${m.id}">🗑</button>
        </td>
      </tr>`).join("")
      : '<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--muted)">Sin medios de pago</td></tr>';
  } catch (e) { /* sin tabla */ }
}

async function guardarMedioPago(id) {
  const nombre = id
    ? (estado.mediosTodos.find(m => m.id === Number(id)) || {}).nombre
    : await pedirTexto("Nuevo medio de pago", "Nombre", "Por ejemplo: Mercado Pago, Débito, Cobro móvil…", "", "text");
  if (nombre === null) return;

  if (id) {
    const m = estado.mediosTodos.find(x => x.id === Number(id));
    if (!m) return;
    try {
      await api("medio_pago_guardar", {
        id: m.id, nombre: m.nombre, icono: m.icono,
        referencia: m.referencia ? 1 : 0, efectivo: m.efectivo ? 1 : 0,
        activo: m.activo ? 1 : 0, orden: 99
      });
      aviso("Medio de pago actualizado.", "ok");
    } catch (e) { aviso(e.message, "mal"); return; }
  } else {
    if (!String(nombre).trim()) { aviso("Escribe el nombre.", "aviso-w"); return; }
    const esEfec = await pedirSiNo("¿Recibe dinero en efectivo?", "Si dice sí, el sistema va a pedir el importe entregado y calculará el vuelto.");
    if (esEfec === null) return;
    const conRef = esEfec ? 0 : 1;
    try {
      await api("medio_pago_guardar", {
        nombre: String(nombre).trim(), icono: esEfec ? "💵" : "💳",
        referencia: conRef, efectivo: esEfec ? 1 : 0, activo: 1, orden: 99
      });
      aviso("Medio de pago agregado.", "ok");
    } catch (e) { aviso(e.message, "mal"); return; }
  }
  await cargarMediosPago();
  await refrescarCabecera();
}

/* --- Proveedores --- */
async function cargarProveedores() {
  try {
    const r = await api("proveedores");
    estado.proveedores = r.proveedores;
    const tb = $("#a-prov-tb");
    if (!tb) return;
    tb.innerHTML = r.proveedores.length ? r.proveedores.map(p => `
      <tr>
        <td><strong>${esc(p.nombre)}</strong>${p.activo ? "" : ' <span class="etq neutro">inactivo</span>'}
          ${p.observaciones ? '<br><span class="fuente" style="font-size:11.5px">' + esc(p.observaciones.slice(0, 40)) + "</span>" : ""}</td>
        <td class="fuente">${p.telefono ? esc(p.telefono) : "—"}</td>
        <td class="num">${p.articulos}</td>
        <td class="acciones">
          <button class="btn sm" data-prov-editar="${p.id}">✎</button>
          <button class="btn sm peligro" data-prov-borrar="${p.id}">🗑</button>
        </td>
      </tr>`).join("")
      : '<tr><td colspan="4" style="padding:20px;text-align:center;color:var(--muted)">Sin proveedores cargados</td></tr>';
  } catch (e) { /* sin tabla */ }
}

async function guardarProveedor(id) {
  if (id) {
    const p = estado.proveedores.find(x => x.id === Number(id));
    if (!p) return;
    const nombre = await pedirTexto("Editar proveedor", "Nombre", "", p.nombre, "text");
    if (nombre === null) return;
    try {
      await api("proveedor_guardar", {
        id: p.id, nombre: String(nombre).trim() || p.nombre,
        telefono: p.telefono, email: p.email, observaciones: p.observaciones,
        activo: p.activo ? 1 : 0
      });
      aviso("Proveedor actualizado.", "ok");
    } catch (e) { aviso(e.message, "mal"); return; }
  } else {
    const nombre = await pedirTexto("Nuevo proveedor", "Nombre del proveedor", "Ej: Distribuidora del Sur", "", "text");
    if (nombre === null) return;
    if (!String(nombre).trim()) { aviso("Escribe el nombre.", "aviso-w"); return; }
    const tel = await pedirTexto("Teléfono (opcional)", "Teléfono", "Podés dejarlo vacío", "", "text");
    if (tel === null) return;
    try {
      await api("proveedor_guardar", { nombre: String(nombre).trim(), telefono: String(tel || "").trim() });
      aviso("Proveedor agregado.", "ok");
    } catch (e) { aviso(e.message, "mal"); return; }
  }
  await cargarProveedores();
}

async function borrarProveedor(id) {
  const p = estado.proveedores.find(x => x.id === Number(id));
  if (!p) return;
  const ok = await confirmar("Eliminar proveedor",
    "Se quitará «" + p.nombre + "» de la lista. Los " + p.articulos +
    " producto(s) que tiene asignados quedarán sin proveedor, pero no se borra nada del catálogo.");
  if (!ok) return;
  try {
    const r = await api("proveedor_borrar", { id });
    aviso("Proveedor eliminado. " + r.desasignados + " producto(s) quedaron sin proveedor.", "ok");
    await cargarProveedores();
    await cargarProductos();
  } catch (e) { aviso(e.message, "mal"); }
}

async function renderAjustes() {
  const c = estado.config;
  $("#c-negocio").value = c.negocio || "";
  $("#c-moneda").value = c.moneda || "$";
  $("#c-direccion").value = c.direccion || "";
  $("#c-telefono").value = c.telefono || "";
  $("#c-pie").value = c.pie || "";
  $("#c-logo").value = c.logo || "K";
  $("#c-folio").value = c.folio || 1;

  await Promise.all([cargarMediosPago(), cargarProveedores()]);

  try {
    const r = await api("estado");
    const info = [
      ["Servidor web", "Apache + PHP " + r.php_version],
      ["Base de datos", "MySQL " + r.mysql_version],
      ["Productos en catálogo", numeroLocal(r.productos, 0)],
      ["Ventas de hoy", numeroLocal(r.hoy.ventas, 0) + " · " + dinero(r.hoy.total)],
      ["Valor del inventario", dinero(r.valor_inventario)],
      ["Ubicación", "C:\\laragon\\www\\kiosco"]
    ];
    $("#a-estado").innerHTML = info.map(i =>
      '<div class="fila" style="padding:6px 0"><span>' + esc(i[0]) + '</span><b>' + esc(i[1]) + "</b></div>"
    ).join("") + '<div class="parrafo" style="margin:14px 0 0;font-size:12.5px">'
      + "Para abrir este sistema en otra computadora: instala Laragon, copia la carpeta "
      + "<code>kiosco</code> dentro de <code>www</code>, abre <code>instalar.php</code> una vez "
      + "y restaura el archivo de respaldo.</div>";
  } catch (e) {
    $("#a-estado").innerHTML = '<div class="parrafo" style="margin:0">No se pudo leer el estado: ' + esc(e.message) + "</div>";
  }
}

async function guardarConfig() {
  const datos = {
    negocio: $("#c-negocio").value.trim(),
    moneda: $("#c-moneda").value.trim() || "$",
    direccion: $("#c-direccion").value.trim(),
    telefono: $("#c-telefono").value.trim(),
    pie: $("#c-pie").value.trim(),
    logo: $("#c-logo").value.trim().toUpperCase() || "K",
    folio: Number($("#c-folio").value) || 1
  };
  try {
    const r = await api("config_guardar", datos);
    estado.config = r.config;
    aplicarMarca();
    aviso("Configuración guardada.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}

function aplicarMarca() {
  const c = estado.config;
  document.title = (c.negocio || "Kiosco") + " — Kiosco";
  $("#lbl-negocio").textContent = c.negocio || "Kiosco";
  $("#lbl-logo").textContent = (c.logo || "K").slice(0, 2);
}

/* =====================================================================
   13. CSV DE PRODUCTOS
   ===================================================================== */
function exportarProductos() {
  if (!estado.productos.length) { aviso("No hay productos que exportar.", "aviso-w"); return; }
  const filas = [["Nombre", "Codigo", "Categoria", "Precio", "Stock", "Minimo", "Unidad"]];
  estado.productos.forEach(p => filas.push([
    p.nombre, p.codigo || "", p.categoria || "",
    p.precio.toFixed(2), numeroLocal(p.stock, 2), numeroLocal(p.minimo, 2), p.unidad || "pieza"
  ]));
  descargar(aCSV(filas), "productos-" + hoyISO() + ".csv");
  aviso("Archivo con " + estado.productos.length + " producto(s) descargado.", "ok");
}

/** Divide una línea CSV respetando comillas. */
function partirCSV(linea) {
  const out = [];
  let cur = "", enComilla = false;
  for (let i = 0; i < linea.length; i++) {
    const ch = linea[i];
    if (ch === '"') {
      if (enComilla && linea[i + 1] === '"') { cur += '"'; i++; }
      else enComilla = !enComilla;
    } else if ((ch === ";" || ch === ",") && !enComilla) {
      out.push(cur); cur = "";
    } else if (ch !== "\r") {
      cur += ch;
    }
  }
  out.push(cur);
  return out.map(s => s.trim());
}

async function importarCSV(archivo) {
  const texto = await archivo.text();
  const lineas = texto.replace(/^﻿/, "").split(/\r?\n/).filter(l => l.trim());
  if (lineas.length < 2) { aviso("El archivo no tiene datos.", "mal"); return; }

  // Detecta el separador probando con ;
  const cabecera = partirCSV(lineas[0]);
  const hayCabecera = /nombre/i.test(cabecera[0]) && !/^\d/.test(cabecera[1] || "");
  const cuerpo = hayCabecera ? lineas.slice(1) : lineas;

  let creados = 0, actualizados = 0, fallidos = 0;
  for (const linea of cuerpo) {
    const c = partirCSV(linea);
    if (c.length < 3) { fallidos++; continue; }
    const nombre = c[0];
    if (!nombre) { fallidos++; continue; }
    try {
      const codigo = c[1] || "";
      const existente = codigo ? estado.productos.find(p => norm(p.codigo) === norm(codigo)) : null;
      const datos = {
        id: existente ? existente.id : 0,
        nombre: nombre,
        codigo: codigo,
        categoria: c[2] || "",
        precio: Number(String(c[3] || "0").replace(/[^\d.-]/g, "")) || 0,
        stock: Number(String(c[4] || "0").replace(/[^\d.-]/g, "")) || 0,
        minimo: Number(String(c[5] || "0").replace(/[^\d.-]/g, "")) || 0,
        unidad: c[6] || "pieza",
        activo: 1
      };
      await api("producto_guardar", datos);
      if (existente) actualizados++; else creados++;
      // Refleja el cambio en memoria para no duplicar códigos dentro del mismo archivo
      if (existente) existente.precio = datos.precio;
    } catch (e) { fallidos++; }
  }

  await cargarProductos();
  renderPOS();
  if (estado.vista === "productos") renderProductos();
  aviso("Importación: " + creados + " nuevos, " + actualizados + " actualizados" + (fallidos ? ", " + fallidos + " con error" : "") + ".",
    fallidos ? "aviso-w" : "ok");
}

/* =====================================================================
   14. MODALES
   ===================================================================== */
function abrirModal(sel) { $(sel).classList.add("on"); }
function cerrarModal(sel) { $(sel).classList.remove("on"); }

let alConfirmar = null;
function confirmar(titulo, texto) {
  return new Promise(resolve => {
    $("#mc-titulo").textContent = titulo;
    $("#mc-texto").textContent = texto;
    $("#m-confirmar").classList.add("on");
    alConfirmar = (si) => {
      $("#m-confirmar").classList.remove("on");
      alConfirmar = null;
      resolve(si);
    };
  });
}

/* Modal de captura rápida: texto, número o sí/no */
let alRapida = null;
function pedirDato(titulo, rotulo, ayuda, valorInicial, tipo) {
  return new Promise(resolve => {
    $("#mr-titulo").textContent = titulo;
    $("#mr-rotulo").textContent = rotulo;
    $("#mr-ayuda").textContent = ayuda || "";
    const inp = $("#mr-valor");
    inp.value = valorInicial != null ? valorInicial : "";
    inp.type = tipo || "text";
    inp.step = inp.type === "number" ? "0.01" : "";
    $("#m-rapida").classList.add("on");
    alRapida = (ok) => {
      const v = inp.value;
      $("#m-rapida").classList.remove("on");
      alRapida = null;
      resolve(ok ? v : null);
    };
    setTimeout(() => { inp.focus(); inp.select(); }, 120);
  });
}

const pedirTexto = (t, r, a, v) => pedirDato(t, r, a, v, "text");
const pedirNumero = (t, r, a, v) => pedirDato(t, r, a, v, "number");

/** Devuelve true (sí), false (no) o null (cancelado). */
async function pedirSiNo(titulo, ayuda) {
  const r = await pedirDato(titulo, "Sí / No", ayuda, "si", "text");
  if (r === null) return null;
  return /^(si|sí|s|1|true|yes)$/i.test(String(r).trim());
}

/* =====================================================================
   15. CARGA DE DATOS
   ===================================================================== */
async function cargarProductos() {
  const r = await api("productos");
  estado.productos = r.productos;
}

async function refrescarCabecera() {
  const r = await api("estado");
  estado.config = r.config;
  if (r.medios_pago && r.medios_pago.length) estado.mediosPago = r.medios_pago;
  aplicarMarca();
  $("#lbl-hoy-total").textContent = dinero(r.hoy.total);
  const d = new Date();
  $("#lbl-hoy").textContent = d.toLocaleDateString("es-MX", { weekday: "long", day: "numeric", month: "long" });
}

/* =====================================================================
   16. NAVEGACIÓN
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
  if (vista === "reportes") {
    if (!$("#r-desde").value) { $("#r-desde").value = hoyISO(); $("#r-hasta").value = hoyISO(); }
    renderReportes();
  }
  if (vista === "ajustes") renderAjustes();
}

window.ir = ir;
window.abrirProducto = abrirProducto;

/* =====================================================================
   17. EVENTOS
   ===================================================================== */
function conectar() {

  /* --- pestañas --- */
  $("#tabs").addEventListener("click", e => {
    const b = e.target.closest("button[data-v]");
    if (b) ir(b.dataset.v);
  });

  /* --- tema --- */
  $("#btn-tema").addEventListener("click", async () => {
    const oscuro = document.body.classList.toggle("t.ocuro");
    const tema = oscuro ? "ocuro" : "claro";
    try { await api("config_guardar", { tema: tema }); estado.config.tema = tema; } catch (e) { /* visual only */ }
  });

  /* --- POS: búsqueda --- */
  const buscar = $("#txt-buscar");
  buscar.addEventListener("input", () => { estado.busqueda = buscar.value; renderGrid(); });

  buscar.addEventListener("keydown", e => {
    if (e.key === "Enter") {
      e.preventDefault();
      const lista = Coincidencias(buscar.value).filter(p => !estado.filtroCat || p.categoria === estado.filtroCat);
      if (!lista.length) { aviso("Ningún producto coincide con «" + buscar.value.trim() + "».", "aviso-w"); return; }
      if (lista.length === 1 || norm(lista[0].codigo) === norm(buscar.value.trim())) {
        agregar(lista[0].id, 1);
      } else {
        aviso(lista.length + " productos coinciden. Toca el correcto en la rejilla.", "aviso-w");
      }
    }
  });

  $("#btn-limpiar-busca").addEventListener("click", () => {
    buscar.value = ""; estado.busqueda = ""; renderGrid(); buscar.focus();
  });

  /* --- POS: clics --- */
  $("#filtros").addEventListener("click", e => {
    const c = e.target.closest(".chip");
    if (!c) return;
    estado.filtroCat = c.dataset.cat;
    renderFiltros(); renderGrid();
  });

  $("#grid-productos").addEventListener("click", e => {
    const p = e.target.closest("[data-prod]");
    if (p) agregar(p.dataset.prod, 1);
  });

  $("#carrito-items").addEventListener("click", e => {
    const t = e.target;
    if (t.dataset.mas) cambiarCantidad(t.dataset.mas, 1);
    else if (t.dataset.menos) cambiarCantidad(t.dataset.menos, -1);
    else if (t.dataset.quitar) quitarLinea(t.dataset.quitar);
  });

  $("#carrito-items").addEventListener("change", e => {
    const inp = e.target;
    if (!inp.dataset.cant) return;
    const v = Math.round(Number(inp.value) || 0);
    if (v <= 0) quitarLinea(inp.dataset.cant);
    else {
      const l = estado.carrito.find(x => x.id === Number(inp.dataset.cant));
      if (l) { l.cantidad = v; renderCarrito(); }
    }
  });

  $("#btn-vaciar").addEventListener("click", async () => {
    if (!estado.carrito.length) return;
    if (await confirmar("Vaciar el ticket", "Se quitarán " + estado.carrito.length + " línea(s). No se registrará ninguna venta.")) {
      vaciarCarrito();
    }
  });

  $("#fila-desc").addEventListener("click", async () => {
    const actual = estado.descuento;
    const v = await pedirNumero("Descuento", "Monto del descuento (0 para quitar)",
      "Se aplica al subtotal completo de la venta.", actual || "", "number");
    if (v === null) return;
    const n = Math.max(0, Number(v) || 0);
    estado.descuento = r2(Math.min(n, subtotalCarrito()));
    renderCarrito();
  });

  $("#btn-cobrar").addEventListener("click", abrirCobro);

  /* --- cobro --- */
  $$("#cob-metodos .metodo").forEach(b => b.addEventListener("click", () => {
    estado.metodoCobro = b.dataset.m;
    $$("#cob-metodos .metodo").forEach(x => x.classList.toggle("on", x === b));
    refrescarCobro();
  }));

  $("#cob-billetes").addEventListener("click", e => {
    const b = e.target.closest("[data-billete]");
    if (!b) return;
    estado.recibido = String(Number(b.dataset.billete));
    refrescarCobro();
  });

  $("#cob-teclado").innerHTML = ["1", "2", "3", "4", "5", "6", "7", "8", "9", "C", "0", "B"]
    .map(k => '<button data-tecla="' + k + '" class="' + (k === "C" || k === "B" ? "fn" : "") + '">'
      + (k === "C" ? "C" : (k === "B" ? "⌫" : k)) + "</button>").join("");

  $("#cob-teclado").addEventListener("click", e => {
    const b = e.target.closest("[data-tecla]");
    if (b) teclaCobro(b.dataset.tecla);
  });

  $("#cob-confirmar").addEventListener("click", confirmarVenta);

  /* --- productos --- */
  $("#p-tbody").addEventListener("click", e => {
    const t = e.target;
    if (t.dataset.editar) abrirProducto(t.dataset.editar);
    else if (t.dataset.borrar) borrarProducto(t.dataset.borrar);
    else if (t.dataset.stock) abrirStock("entrada");
  });

  $("#txt-buscar-p").addEventListener("input", e => { estado.busquedaP = e.target.value; renderProductos(); });
  $("#btn-p-nuevo").addEventListener("click", () => abrirProducto());
  $("#btn-p-csv").addEventListener("click", exportarProductos);
  $("#btn-p-desc").addEventListener("click", () => $("#file-csv").click());
  $("#file-csv").addEventListener("change", e => {
    const f = e.target.files[0];
    if (f) importarCSV(f);
    e.target.value = "";
  });

  /* --- modal producto --- */
  $("#mp-guardar").addEventListener("click", guardarProducto);
  $("#mp-btn-foto").addEventListener("click", () => $("#mp-file").click());
  $("#mp-file").addEventListener("change", e => {
    const f = e.target.files[0];
    if (f) leerImagen(f);
    e.target.value = "";
  });
  $("#mp-btn-sinfoto").addEventListener("click", () => {
    estado.fotoTmp = "";
    const cat = $("#mp-categoria").value;
    $("#mp-foto").innerHTML = emoji({ categoria: cat });
  });
  $("#mp-categoria").addEventListener("input", () => {
    if (!estado.fotoTmp) $("#mp-foto").innerHTML = emoji({ categoria: $("#mp-categoria").value });
  });
  $("#mp-borrar").addEventListener("click", () => { const id = estado.editId; cerrarModal("#m-prod"); borrarProducto(id); });

  /* --- movimientos --- */
  $("#btn-rapida-entrada").addEventListener("click", () => abrirStock("entrada"));
  $("#btn-rapida-salida").addEventListener("click", () => abrirStock("salida"));
  $("#ms-buscar").addEventListener("input", filtrarStock);
  $("#ms-lista").addEventListener("click", e => {
    const s = e.target.closest("[data-sel]");
    if (s) elegirStock(s.dataset.sel);
  });
  $("#ms-guardar").addEventListener("click", guardarStock);

  /* --- historial --- */
  $("#btn-h-hoy").addEventListener("click", () => {
    $("#h-desde").value = hoyISO(); $("#h-hasta").value = hoyISO(); renderHistorial();
  });
  $("#btn-h-mes").addEventListener("click", () => {
    const d = new Date();
    $("#h-desde").value = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-01";
    $("#h-hasta").value = hoyISO();
    renderHistorial();
  });
  ["#h-desde", "#h-hasta"].forEach(s => $(s).addEventListener("change", renderHistorial));

  $("#h-tbody").addEventListener("click", e => {
    const t = e.target;
    if (t.dataset.ver) verVenta(t.dataset.ver);
    else if (t.dataset.reimp) verVenta(t.dataset.reimp);
    else if (t.dataset.anular) anularVenta(t.dataset.anular);
  });

  $("#btn-h-csv").addEventListener("click", exportarVentas);

  /* --- reportes --- */
  $("#btn-r-hoy").addEventListener("click", () => { $("#r-desde").value = hoyISO(); $("#r-hasta").value = hoyISO(); renderReportes(); });
  $("#btn-r-7").addEventListener("click", () => { $("#r-desde").value = diasAtras(6); $("#r-hasta").value = hoyISO(); renderReportes(); });
  $("#btn-r-30").addEventListener("click", () => { $("#r-desde").value = diasAtras(29); $("#r-hasta").value = hoyISO(); renderReportes(); });
  $("#btn-r-mes").addEventListener("click", () => {
    const d = new Date();
    $("#r-desde").value = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-01";
    $("#r-hasta").value = hoyISO();
    renderReportes();
  });
  ["#r-desde", "#r-hasta"].forEach(s => $(s).addEventListener("change", renderReportes));

  /* --- ajustes --- */
  $("#btn-c-guardar").addEventListener("click", guardarConfig);

  /* --- medios de pago --- */
  $("#btn-mp-nuevo").addEventListener("click", () => guardarMedioPago(0));
  $("#a-mp-tb").addEventListener("click", e => {
    const t = e.target;
    if (t.dataset.mpEditar) guardarMedioPago(t.dataset.mpEditar);
    else if (t.dataset.mpBorrar) {
      const m = (estado.mediosTodos || []).find(x => x.id === Number(t.dataset.mpBorrar));
      confirmar("Eliminar medio de pago",
        "Se quitará «" + (m ? m.nombre : "") + "» de las opciones de cobro. Las ventas ya registradas lo conservan."
      ).then(ok => {
        if (!ok) return;
        api("medio_pago_borrar", { id: t.dataset.mpBorrar })
          .then(r => {
            aviso(r.desactivado ? "Ese medio ya se usó en ventas: se desactivó en vez de borrarse." : "Medio de pago eliminado.",
              r.desactivado ? "aviso-w" : "ok");
            return Promise.all([cargarMediosPago(), refrescarCabecera()]);
          })
          .catch(err => aviso(err.message, "mal"));
      });
    }
  });

  /* --- proveedores --- */
  $("#btn-prov-nuevo").addEventListener("click", () => guardarProveedor(0));
  $("#a-prov-tb").addEventListener("click", e => {
    const t = e.target;
    if (t.dataset.provEditar) guardarProveedor(t.dataset.provEditar);
    else if (t.dataset.provBorrar) borrarProveedor(t.dataset.provBorrar);
  });

  /* --- margen en vivo --- */
  ["#mp-precio", "#mp-costo"].forEach(s => $(s).addEventListener("input", refrescarMargen));

  $$("[data-limpiar]").forEach(b => b.addEventListener("click", async () => {
    const que = b.dataset.limpiar;
    const textos = {
      ventas: ["Borrar el historial de ventas", "Se borrarán TODAS las ventas y sus artículos. Los productos y el stock no cambian, pero el kardex perderá los movimientos de venta. Se descargará un respaldo antes de continuar."],
      productos: ["Borrar todo el catálogo", "Se eliminarán TODOS los productos y sus existencias. Las ventas ya registradas se conservan con su nombre y su importe."],
      kardex: ["Borrar el kardex", "Se borrará el historial de movimientos de inventario. Las ventas y los productos no cambian."]
    };
    const [t, m] = textos[que];
    if (!await confirmar(t, m)) return;
    const ok = await confirmar("Última confirmación", "¿Seguro? Esta acción no se puede deshacer. Se guardará un respaldo automático en la carpeta datos/ de esta computadora.");
    if (!ok) return;
    try {
      await api("kiosco_limpiar", { que: que });
      aviso("Listo. Recargando…", "ok");
      setTimeout(() => location.reload(), 900);
    } catch (e) { aviso(e.message, "mal"); }
  }));

  /* --- modales: cerrar --- */
  $$(".velo").forEach(v => {
    v.addEventListener("click", e => { if (e.target === v) v.classList.remove("on"); });
  });
  $$("[data-cerrar]").forEach(b => b.addEventListener("click", e => {
    const velo = b.closest(".velo");
    if (velo) velo.classList.remove("on");
  }));
  $("#mc-ok").addEventListener("click", () => alConfirmar && alConfirmar(true));
  $("#mr-ok").addEventListener("click", () => alRapida && alRapida(true));

  $("#mr-valor").addEventListener("keydown", e => {
    if (e.key === "Enter") { e.preventDefault(); alRapida && alRapida(true); }
  });

  /* --- atajos de teclado --- */
  document.addEventListener("keydown", e => {
    if (e.key === "F2") { e.preventDefault(); ir("vender"); return; }
    if (e.key === "F4") { e.preventDefault(); if (!$("#m-cobro").classList.contains("on")) abrirCobro(); return; }

    if (e.key === "Escape") {
      const abiertas = $$(".velo.on");
      if (abiertas.length) {
        const ult = abiertas[abiertas.length - 1];
        if (ult.id === "m-cobro" && estado.recibido !== "") { estado.recibido = ""; refrescarCobro(); return; }
        ult.classList.remove("on");
        return;
      }
      if (estado.vista === "vender") { $("#txt-buscar").value = ""; estado.busqueda = ""; renderGrid(); }
    }
  });
}

/* =====================================================================
   18. EXPORTAR HISTORIAL
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
        d.toLocaleDateString("es-MX"), d.toLocaleTimeString("es-MX", { hour: "2-digit", minute: "2-digit" }),
        numeroLocal(arts, 0), v.metodo, v.subtotal.toFixed(2), v.descuento.toFixed(2), v.total.toFixed(2),
        v.anulada ? "Anulada" : "Válida", v.referencia || ""
      ]);
    }
    descargar(aCSV(filas), "ventas-" + $("#h-desde").value + "-a-" + $("#h-hasta").value + ".csv");
    aviso("Historial exportado.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}

/* =====================================================================
   19. ARRANQUE
   ===================================================================== */
async function iniciar() {
  conectar();
  try {
    await Promise.all([cargarProductos(), refrescarCabecera(), cargarProveedores()]);
    if (estado.config.tema === "ocuro") document.body.classList.add("t.ocuro");
    aplicarMarca();
    renderPOS();
    $("#txt-buscar").focus();
  } catch (e) {
    document.body.innerHTML = '<div class="vacio" style="height:100vh">'
      + '<div class="ico">⚠️</div><h3>No se pudo conectar con el sistema</h3>'
      + "<p>" + esc(e.message) + "</p>"
      + '<p style="font-size:12.5px">Verifica que Laragon esté abierto y que MySQL esté encendido.</p>'
      + '<button class="btn pri" onclick="location.reload()">Reintentar</button></div>';
  }
}

document.addEventListener("DOMContentLoaded", iniciar);
