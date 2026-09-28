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

/**
 * Escucha un elemento sólo si existe. Un id que falta (por ejemplo en un rol
 * que no ve esa pantalla) no debe dejar sin listeners a todo lo que viene
 * después, así que los enlaces de la UI nueva usan esto.
 */
function escuchar(sel, evento, fn) {
  const el = $(sel);
  if (el) el.addEventListener(evento, fn);
  return !!el;
}

const r2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

/**
 * Lee un importe escrito a la argentina: "1.234,56", "1234,56", "1234.56"
 * o "1234". Sirve para los campos donde el cajero teclea el efectivo contado.
 */
function parseNum(v) {
  if (typeof v === "number") return r2(v);
  let s = String(v == null ? "" : v).trim().replace(/[^\d,.\-]/g, "");
  if (!s) return 0;
  const ultimaComa = s.lastIndexOf(","), ultimoPunto = s.lastIndexOf(".");
  if (ultimaComa > ultimoPunto) {
    // La coma es el decimal: los puntos son separadores de miles.
    s = s.replace(/\./g, "").replace(",", ".");
  } else if (ultimaComa !== -1 && ultimoPunto === -1) {
    s = s.replace(",", ".");
  } else {
    s = s.replace(/,/g, "");
  }
  return r2(parseFloat(s) || 0);
}

/** Sólo la hora, para "abrió a las 14:05". */
function horaDe(iso) {
  if (!iso) return "—";
  return new Date(String(iso).replace(" ", "T"))
    .toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" });
}

const esc = (s) => String(s == null ? "" : s)
  .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
  .replace(/"/g, "&quot;").replace(/'/g, "&#39;");

/** Quita acentos y pasa a minúsculas para comparar sin sorpresas. */
const norm = (s) => String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

/**
 * Imprime SOLO el ticket indicado.
 * Hay tres contenedores (#ticket, #ticket-caja, #ticket-comanda) y los tres
 * tienen su propio bloque @media print. Si se dejara activo el de los demas,
 * un ticket viejo quedaria impreso encima del nuevo, asi que se marca en
 * <body data-imprimir="..."> y el CSS muestra unicamente ese.
 * El ancho de pagina lo define la impresora: en una termica de 80mm el ticket
 * sale a lo ancho y en una A4/Carta normal queda centrado con las mismas
 * proporciones.
 */
function imprimirTicketAhora(clave) {
  document.body.dataset.imprimir = clave;
  const limpiar = () => {
    delete document.body.dataset.imprimir;
    window.removeEventListener("afterprint", limpiar);
  };
  window.addEventListener("afterprint", limpiar);
  // afterprint no dispara en todos los navegadores: se limpia al volver a la app
  if (!("onafterprint" in window)) setTimeout(limpiar, 1500);
  setTimeout(() => window.print(), 60);
}

function dinero(n, simbolo) {
  const v = r2(n);
  const txt = v.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

const dosDig = (n) => String(n).padStart(2, "0");

/**
 * Formato de fecha y hora de negocio: dd/mm/aaaa hh:mm en 24 horas.
 * Se arma a mano en vez de usar toLocaleString porque el locale es-AR
 * devuelve "25/09/26, 05:15 p. m." (12 horas y año de 2 cifras), que en
 * Argentina no se usa.
 */
function fechaHora(iso) {
  const d = new Date(String(iso).replace(" ", "T"));
  if (isNaN(d)) return "—";
  return dosDig(d.getDate()) + "/" + dosDig(d.getMonth() + 1) + "/" + d.getFullYear()
    + " " + dosDig(d.getHours()) + ":" + dosDig(d.getMinutes());
}

/** Solo la fecha: dd/mm/aaaa */
function fechaCorta(iso) {
  const d = new Date(String(iso).replace(" ", "T"));
  if (isNaN(d)) return "—";
  return dosDig(d.getDate()) + "/" + dosDig(d.getMonth() + 1) + "/" + d.getFullYear();
}

function numeroLocal(n, dec) {
  const v = Number(n) || 0;
  return v.toLocaleString("es-AR", {
    minimumFractionDigits: dec == null ? 0 : dec,
    maximumFractionDigits: dec == null ? 0 : dec
  });
}

const FRACCIONES = { 0.25: "¼", 0.5: "½", 0.75: "¾" };

function esPeso(u) {
  const s = String(u || "").trim().toLowerCase();
  return s === "kg" || s === "g" || s === "gramo" || s === "kilo";
}

function esVolumen(u) {
  const s = String(u || "").trim().toLowerCase();
  return s === "litro" || s === "l" || s === "lt" || s === "ml";
}

/**
 * Muestra una cantidad de la forma mas natural para el cliente.
 *   0.25 kg   -> "250 g"      0.5 litro -> "500 ml"
 *   2 kg      -> "2 kg"       2 litro   -> "2 L"
 *   1 unidad  -> "1"          0         -> "0"
 */
function cantidadTxt(n, unidad) {
  const v = Number(n) || 0;
  const u = String(unidad || "pieza").trim();
  const abs = Math.abs(v);
  const peso = esPeso(u), vol = esVolumen(u);

  if (peso || vol) {
    const chico = redondearTxt(abs * 1000);
    if (chico > 0 && enteroTxt(chico)) {
      if (abs >= 1) return numCorto(abs) + (peso ? " kg" : " L");
      return numeroLocal(chico, 0) + (peso ? " g" : " ml");
    }
  }
  return numCorto(v);
}

function redondearTxt(n) { return Math.round(n * 1000) / 1000; }
function enteroTxt(n) { return Math.abs(n - Math.round(n)) < 0.001; }

/** Entero sin decimales, fractionado con 2 y sin ceros de sobra: 4,5 / 0,25. */
function numCorto(v) {
  if (!Number.isInteger(v)) {
    return numeroLocal(v, 2).replace(/,00$/, "").replace(/,(\d)0$/, ",$1");
  }
  return numeroLocal(v, 0);
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
    // Sesión vencida o clave sin cambiar: de vuelta al login.
    if (res.status === 401 || json.sesion || json.clave) {
      window.location.href = "login.php";
      throw new Error(json.error || "Sesión vencida.");
    }
    const err = new Error(json.error || ("Error " + res.status));
    err.permiso = res.status === 403;
    err.sinCaja = res.status === 409;
    throw err;
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
  editProvId: null,
  /* --- comandas de cocina --- */
  soyCocina: document.body.getAttribute("data-rol") === "cocina",
  miRol: document.body.getAttribute("data-rol") || "vendedor",
  comanda: null,        // la comanda armada en el modal de delivery
  atajos: [],
  zonas: [],
  cocFiltro: "pendiente",
  cocTimer: null,
  /* --- sesión, rol y caja --- */
  yo: null,
  soyAdmin: document.body.getAttribute("data-rol") === "admin",
  caja: null,          // la caja abierta ahora mismo, o null
  cajas: [],
  usuarios: []
};

/** ¿Puede este usuario administrar el catálogo? */
function esAdmin() { return !!estado.soyAdmin; }
function esCocina() { return !!estado.soyCocina; }
function puedeVender() { return esAdmin() || estado.miRol === "vendedor"; }

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
function agregar(id, cantidad, formatoId) {
  const p = prodPorId(id);
  if (!p) { aviso("Ese producto ya no existe.", "mal"); return; }
  cantidad = cantidad === undefined || cantidad === null ? 1 : cantidad;

  // El formato elegida define el precio y cuantas unidades base se descuentan.
  // Sin eleccion explicita se usa el predeterminado del producto.
  const formatos = p.formatos_venta || [];
  let fmt = null;
  if (formatoId !== undefined && formatoId !== null && Number(formatoId) > 0) {
    fmt = formatos.find(f => f.id === Number(formatoId));
    if (!fmt) { aviso("Ese formato ya no existe.", "mal"); return; }
  } else if (formatos.length) {
    fmt = formatos.find(f => f.predet) || formatos[0];
  }
  const factor = fmt ? (Number(fmt.factor) || 1) : 1;
  const precio = fmt && Number(fmt.precio_final) > 0 ? Number(fmt.precio_final) : p.precio;
  const baseCant = r2(cantidad * factor);
  const formatoIdFinal = fmt ? fmt.id : 0;

  // La venta libre no lleva control de existencias: no se agota y no avisa.
  if (!p.sin_stock) {
    if (p.stock <= 0) aviso("⚠️ " + p.nombre + " está agotado. Se registra igual y el stock queda en negativo.", "aviso-w");
    else if (p.minimo > 0 && p.stock - baseCant <= p.minimo) {
      aviso("Quedan solo " + cantidadTxt(p.stock, p.unidad) + " de " + p.nombre + ".", "aviso-w");
    }
  }

  // Una linea por producto + formato: "2 unidades" y "1 docena" van separadas.
  const linea = estado.carrito.find(l => l.id === p.id && (l.formato_id || 0) === formatoIdFinal);
  if (linea) linea.cantidad = r2(linea.cantidad + cantidad);
  else estado.carrito.push({
    id: p.id, formato_id: formatoIdFinal, nombre: p.nombre, precio,
    cantidad: r2(cantidad), factor,
    unidad: p.unidad || "pieza",
    formato: fmt ? fmt.unidad : null,
    // Se mandan tambien nombre y cantidad: si el producto se guarda y cambia
    // el id del formato, la venta lo reconoce igual.
    formato_unidad: fmt ? fmt.unidad : null,
    formato_factor: factor,
    stock: p.stock
  });

  renderCarrito();
  $("#txt-buscar").value = "";
  $("#txt-buscar").focus();
}

function cambiarCantidad(id, delta, formatoId) {
  const fId = Number(formatoId) || 0;
  const l = estado.carrito.find(x => x.id === Number(id) && (x.formato_id || 0) === fId);
  if (!l) return;
  l.cantidad = r2(l.cantidad + delta);
  if (l.cantidad <= 0) quitarLinea(l.id, fId);
  else renderCarrito();
}

/** Cambia el formato de una linea conservando la cantidad. */
function cambiarFormato(id, formatoId) {
  const fId = Number(formatoId) || 0;
  const linea = estado.carrito.find(x => x.id === Number(id) && (x.formato_id || 0) !== fId);
  if (!linea) return;
  const p = prodPorId(linea.id);
  if (!p) { quitarLinea(linea.id, linea.formato_id); return; }
  const fmt = (p.formatos_venta || []).find(f => f.id === fId);
  if (!fmt) return;

  // Si ya habia una linea con ese formato, se fusionan las cantidades.
  const destino = estado.carrito.find(x => x.id === linea.id && (x.formato_id || 0) === fId);
  if (destino) {
    destino.cantidad = r2(destino.cantidad + linea.cantidad);
    estado.carrito = estado.carrito.filter(x => x !== linea);
  } else {
    linea.formato_id = fId;
    linea.precio = Number(fmt.precio_final) > 0 ? Number(fmt.precio_final) : p.precio;
    linea.factor = Number(fmt.factor) || 1;
    linea.formato = fmt.unidad;
  }
  renderCarrito();
}

function quitarLinea(id, formatoId) {
  const fId = formatoId === undefined ? null : Number(formatoId);
  estado.carrito = estado.carrito.filter(x =>
    !(x.id === Number(id) && (fId === null || (x.formato_id || 0) === fId)));
  renderCarrito();
}

/** Los +/- van de a 1 formato (1 maple, 1 docena, 1 kg...). */
function pasoBase(l) { return 1; }

/** Chips con los formatos de venta del producto (docena, 2x1, 250 g...). */
function presetsHTML(l) {
  const p = prodPorId(l.id);
  const formatos = (p && p.formatos_venta) || [];
  if (formatos.length < 2) return "";
  return '<div class="chips">' + formatos.map(f => {
    const sel = (f.id === (l.formato_id || 0));
    const etq = f.unidad + (Number(f.factor) !== 1 ? " (" + cantidadTxt(Number(f.factor), p.unidad) + ")" : "");
    const pr = Number(f.precio_final) > 0 ? " · " + dinero(Number(f.precio_final)) : "";
    return `<button class="chip ${sel ? "on" : ""}" data-fmt-linea="${l.id}" data-fmt-nuevo="${f.id}" data-fmt-actual="${l.formato_id || 0}">${esc(etq + pr)}</button>`;
  }).join("") + "</div>";
}

function vaciarCarrito() {
  estado.carrito = [];
  estado.descuento = 0;
  renderCarrito();
}

const subtotalCarrito = () => r2(estado.carrito.reduce((s, l) => s + l.precio * l.cantidad, 0));
/** El costo de envío de la comanda se suma al total de la venta. */
const envioComanda    = () => r2(estado.comanda && estado.comanda.tipo === "delivery" ? (estado.comanda.envio || 0) : 0);
const totalCarrito    = () => r2(Math.max(0, subtotalCarrito() - estado.descuento) + envioComanda());
// Piezas en unidad base, que es como se descuenta del stock.
const piezasCarrito  = () => r2(estado.carrito.reduce((s, l) => s + l.cantidad * (l.factor || 1), 0));

function renderCarrito() {
  const cont = $("#carrito-items");

  if (!estado.carrito.length) {
    cont.innerHTML = '<div class="vacio" style="padding:36px 12px">'
      + '<div class="ico">🛒</div><h3>El ticket está vacío</h3>'
      + '<p>Toca un producto de la izquierda o escanea su código.</p></div>';
  } else {
    cont.innerHTML = estado.carrito.map(l => {
      const baseCant = r2(l.cantidad * (l.factor || 1));
      const equiv = (l.factor || 1) !== 1
        ? ` · ${esc(cantidadTxt(baseCant, l.unidad))}` : "";
      return `
      <div class="item" data-linea="${l.id}" data-fmt="${l.formato_id || 0}">
        <div class="info">
          <div class="n" title="${esc(l.nombre)}">${esc(l.nombre)}</div>
          <div class="p">${dinero(l.precio)}${l.formato ? " c/u " + esc(l.formato) : " c/u"}${equiv}</div>
          <div class="presets-linea">${presetsHTML(l)}</div>
        </div>
        <div class="cant">
          <button data-menos="${l.id}" data-menos-fmt="${l.formato_id || 0}" title="Quitar uno">−</button>
          <input type="number" step="0.01" min="0" value="${l.cantidad}" data-cant="${l.id}" data-cant-fmt="${l.formato_id || 0}" aria-label="Cantidad">
          <button data-mas="${l.id}" data-mas-fmt="${l.formato_id || 0}" title="Agregar uno">+</button>
        </div>
        <div class="imp">${dinero(l.precio * l.cantidad)}</div>
        <button class="quitar" data-quitar="${l.id}" data-quitar-fmt="${l.formato_id || 0}" title="Quitar la línea">✕</button>
      </div>`;
    }).join("");
  }

  const sub = subtotalCarrito();
  const tot = totalCarrito();
  const env = envioComanda();
  $("#c-count").textContent = estado.carrito.length;
  $("#c-sub").textContent = dinero(sub);
  $("#c-total").textContent = dinero(tot);
  $("#fila-desc").style.display = estado.descuento > 0 ? "" : "none";
  $("#c-desc").textContent = "−" + dinero(estado.descuento);
  const filaEnv = $("#c-envio");
  if (filaEnv) {
    filaEnv.hidden = env <= 0;
    if (env > 0) $("#c-envio-val").textContent = "+" + dinero(env);
  }
  renderBotonCobrar();
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
    // Venta libre: no hay existencias que valgan, asi que ni "agotado" ni
    // "sin stock": el producto se cobra siempre.
    const libre = !!p.sin_stock;
    const e = estadoStock(p);
    const marca = libre ? '<span class="marca libre">a pedido</span>'
      : (p.stock <= 0 ? '<span class="marca">agotado</span>'
      : (p.minimo > 0 && p.stock <= p.minimo ? '<span class="marca bajo">bajo</span>' : ""));
    return `<div class="prod${!libre && p.stock <= 0 ? " sin-stock" : ""}" data-prod="${p.id}" title="${esc(p.nombre)}">
      ${marca}
      ${fotoHTML(p, "foto")}
      <div class="nom">${esc(p.nombre)}</div>
      <div class="prez">${dinero(p.precio)}</div>
      <div class="stk">${libre ? "sin control de stock" : (p.stock > 0 ? esc(cantidadTxt(p.stock, p.unidad)) : "sin stock")}</div>
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
  const env = envioComanda();
  const filaEnv = $("#cob-envio-fila");
  if (filaEnv) {
    filaEnv.hidden = env <= 0;
    if (env > 0) {
      const z = estado.zonas.find(x => x.id === (estado.comanda.zona_id || 0));
      $("#cob-envio-detalle").textContent = dinero(env) + (z ? " · " + z.nombre : "");
    }
  }
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

  // La comanda viaja con la venta: se guardan juntas o no se guarda ninguna.
  let comanda = null;
  if (estado.comanda && estado.comanda.items.length) {
    const c = estado.comanda;
    comanda = {
      cliente: c.cliente, telefono: c.telefono, direccion: c.direccion,
      zona_id: c.zona_id || 0, tipo: c.tipo, lugar: c.lugar, notas: c.notas,
      items: c.items.map(it => ({
        texto: it.texto, detalle: it.detalle, producto_id: it.producto_id || 0, cantidad: it.cantidad
      }))
    };
  }

  try {
    const r = await api("venta_crear", {
      items: itemsParaVenta(),
      descuento: estado.descuento,
      metodo: estado.metodoCobro,
      medio_pago_id: metodo ? metodo.id : 0,
      recibido: efectivo ? recibido : -1,
      vuelto: efectivo ? r2(recibido - totalCarrito()) : 0,
      referencia: $("#cob-ref").value.trim(),
      comanda: comanda
    });

    cerrarModal("#m-cobro");
    vaciarCarrito();
    estado.comanda = null;
    renderBotonCobrar();
    estado.ultimaVenta = r.venta;

    if (r.sin_stock && r.sin_stock.length) {
      aviso("⚠️ Quedó en negativo: " + r.sin_stock.join(", "), "aviso-w");
    }
    if (r.comanda_id) {
      aviso("Comanda #" + r.comanda_id + " enviada a cocina.", "ok");
    }

    mostrarVenta(r.venta, true);
    await Promise.all([cargarProductos(), refrescarCabecera(), refrescarMiCaja()]);
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
   El pedido se cobra como una venta normal y ademas queda la comanda:
   lo que cocina tiene que preparar y a quien entregarselo.
   ===================================================================== */
const ETIQUETA_TIPO = { delivery: "🛵 Delivery", retiro: "🏠 Retiro", mesa: "🍽 Mesa" };
const ETIQUETA_ESTADO = {
  pendiente: "Nueva", preparando: "Preparando", listo: "Lista",
  entregado: "Entregada", cancelado: "Cancelada"
};

/* ---------- Modal de delivery ---------- */

function abrirDelivery() {
  if (!estado.comanda) {
    estado.comanda = {
      cliente: "", telefono: "", direccion: "", zona_id: 0, envio: 0,
      tipo: "delivery", lugar: "", notas: "", items: []
    };
  }
  const c = estado.comanda;
  $("#del-cliente").value = c.cliente || "";
  $("#del-telefono").value = c.telefono || "";
  $("#del-direccion").value = c.direccion || "";
  $("#del-lugar").value = c.lugar || "";
  $("#del-notas").value = c.notas || "";
  $("#del-texto").value = "";
  $("#del-detalle").value = "";
  $$("#del-tipo button").forEach(b => b.classList.toggle("on", b.dataset.tipo === c.tipo));
  actualizarCamposDelivery();
  renderZonas();
  renderAtajos();
  renderItemsDelivery();
  abrirModal("#m-delivery");
  setTimeout(() => $("#del-cliente").focus(), 120);
}

/** Muestra sólo los campos que aplican al tipo de pedido elegido. */
function actualizarCamposDelivery() {
  const t = estado.comanda.tipo;
  $("#del-zona-campos").style.display = t === "delivery" ? "" : "none";
  $("#del-dir-campo").style.display = t === "delivery" ? "" : "none";
  $("#del-lugar-campo").style.display = t === "mesa" ? "" : "none";
}

function renderZonas() {
  const sel = $("#del-zona");
  const id = String(estado.comanda.zona_id || 0);
  sel.innerHTML = '<option value="0">— sin zona —</option>'
    + estado.zonas.map(z => '<option value="' + z.id + '">' + esc(z.nombre) + " — " + dinero(z.costo) + "</option>").join("");
  sel.value = id;
  aplicarZona();
}

/** El costo sale de la zona configurada; el navegador no lo puede cambiar. */
function aplicarZona() {
  const c = estado.comanda;
  const id = Number($("#del-zona").value) || 0;
  c.zona_id = id;
  const z = estado.zonas.find(x => x.id === id);
  const costo = z ? Number(z.costo) || 0 : 0;
  c.envio = costo;
  $("#del-envio").value = dinero(z ? costo : 0);
  // El total del ticket incluye el envio: hay que repintarlo.
  renderCarrito();
}

/** Cambiar el tipo puede dejar el envio en cero (retiro y mesa no se reparten). */
function refrescarEnvio() {
  const c = estado.comanda;
  // Se relee el costo de la zona: si veniamos de retiro, el valor sigue ahi.
  const z = estado.zonas.find(x => x.id === (c.zona_id || 0));
  c.envio = c.tipo === "delivery" && z ? Number(z.costo) || 0 : 0;
  $("#del-envio").value = dinero(c.envio);
  renderCarrito();
}

/* ---------- Atajos ---------- */

function renderAtajos() {
  if (!estado.atajos.length) {
    $("#del-atajos").innerHTML = '<p class="parrafo" style="margin:0">No hay atajos cargados. Cargalos en Ajustes → Comandas de cocina.</p>';
    return;
  }
  // Se agrupan por sección, en el orden en que vinieron.
  const secciones = [];
  estado.atajos.forEach(a => {
    if (!secciones.some(s => s.nombre === a.seccion)) secciones.push({ nombre: a.seccion, lista: [] });
    secciones.find(s => s.nombre === a.seccion).lista.push(a);
  });
  $("#del-atajos").innerHTML = secciones.map(s => `
    <div class="atajo-sec"><span class="t">${esc(s.nombre)}</span><span class="ln"></span></div>
    <div class="atajo-botones">${s.lista.map(a => `
      <button class="atajo${a.producto_id ? " con-producto" : ""}" data-atajo="${a.id}"
              title="${esc(a.texto || "")}${a.producto_id ? " · se cobra" : " · sólo texto"}">
        ${esc(a.etiqueta)}${a.producto_id ? '<span class="pt">$</span>' : ""}
      </button>`).join("")}</div>`).join("");
}

/**
 * Un atajo con producto lo mete al carrito (se cobra y descuenta stock) y
 * además anota la línea para cocina. Uno sin producto es sólo texto.
 */
function usarAtajo(id) {
  const a = estado.atajos.find(x => x.id === Number(id));
  if (!a) return;
  let texto = a.texto;
  let detalle = a.detalle || null;

  if (a.producto_id) {
    const p = prodPorId(a.producto_id);
    if (!p) { aviso("El producto de ese atajo ya no existe.", "mal"); return; }
    // Formato puntual (por ejemplo "botella de 2 y cuarto"): si existe, se usa.
    let fmtId = 0;
    if (a.formato_unidad) {
      const f = (p.formatos_venta || []).find(x => norm(x.unidad) === norm(a.formato_unidad));
      if (f) fmtId = f.id;
    }
    agregar(a.producto_id, 1, fmtId);
    if (!texto) texto = p.nombre;
  }
  if (!texto) { aviso("Ese atajo no tiene texto para cocina.", "mal"); return; }

  agregarItemComanda({ texto, detalle, producto_id: a.producto_id || 0, cantidad: 1 });
  $("#del-texto").value = "";
  $("#del-detalle").value = "";
  $("#del-detalle-wrap").hidden = true;
}

function agregarItemComanda(item) {
  estado.comanda.items.push({
    texto: item.texto, detalle: item.detalle || null,
    producto_id: item.producto_id || 0, cantidad: Number(item.cantidad) || 1
  });
  renderItemsDelivery();
}

/* ---------- Lineas de la comanda ---------- */

function renderItemsDelivery() {
  const items = estado.comanda.items;
  $("#del-count").textContent = items.length;
  $("#del-guardar").disabled = items.length === 0;
  if (!items.length) {
    $("#del-items").innerHTML = '<div class="del-vacio">Todavía no hay nada que preparar.<br>Tocá un atajo de arriba o escribí una línea.</div>';
    return;
  }
  $("#del-items").innerHTML = items.map((it, i) => {
    // Si el atajo tira de un producto, se muestra cuanto suma al ticket.
    let precio = "";
    if (it.producto_id) {
      const linea = estado.carrito.find(l => l.id === it.producto_id);
      if (linea) precio = dinero(linea.precio * linea.cantidad);
    }
    return `<div class="del-item">
      <span class="n">${it.cantidad}</span>
      <span class="c"><b>${esc(it.texto)}</b>${it.detalle ? '<span class="d">' + esc(it.detalle) + "</span>" : ""}</span>
      <span class="precio">${precio}</span>
      <button class="quitar" data-quitar-com="${i}" title="Sacar de la comanda">✕</button>
    </div>`;
  }).join("");
}

/** El botón de cobrar se enciende con el carrito: la comanda viaja con la venta. */
function renderBotonCobrar() {
  const hayCarrito = estado.carrito.length > 0;
  const hayComanda = !!(estado.comanda && estado.comanda.items.length);
  const btn = $("#btn-cobrar");
  if (btn) btn.disabled = !hayCarrito;
  const av = $("#comanda-aviso");
  if (av) {
    av.hidden = !hayComanda;
    if (hayComanda) $("#comanda-cliente").textContent = estado.comanda.cliente || "(sin nombre)";
  }
}

function leerCamposDelivery() {
  const c = estado.comanda;
  c.cliente  = $("#del-cliente").value.trim();
  c.telefono = $("#del-telefono").value.trim();
  c.direccion = $("#del-direccion").value.trim();
  c.lugar    = $("#del-lugar").value.trim();
  c.notas    = $("#del-notas").value.trim();
}

/** El precio de la comanda de texto libre no se cobra: es sólo instrucción. */
function itemsParaVenta() {
  return estado.carrito.map(l => ({
    id: l.id,
    formato_id: l.formato_id || 0,
    formato_unidad: l.formato_unidad || null,
    formato_factor: l.formato_factor || 1,
    cantidad: l.cantidad,
    precio: l.precio
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
      <td class="num"><strong>${esc(cantidadTxt(p.stock, p.unidad))}</strong></td>
      <td>${p.minimo > 0 ? esc(cantidadTxt(p.minimo, p.unidad)) : "—"}</td>
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
    $("#mp-sin-stock").checked = !!p.sin_stock;
    $("#mp-unidad").value = p.unidad || "pieza";
    $("#mp-observaciones").value = p.observaciones || "";
    llenarProveedores(p.proveedor_id);
    estado.fotoTmp = p.foto || "";
    $("#mp-foto").innerHTML = estado.fotoTmp
      ? '<img src="' + esc(estado.fotoTmp) + '" alt="">'
      : emoji({ categoria: p.categoria });
    $("#mp-borrar").style.display = "";
    refrescarMargen();
    pintarFormatos("compra", p.formatos_compra || []);
    pintarFormatos("venta", p.formatos_venta || []);
    await cargarKardex(id);
  } else {
    $("#mp-titulo").textContent = "Nuevo producto";
    ["#mp-nombre", "#mp-codigo", "#mp-categoria", "#mp-precio", "#mp-costo",
     "#mp-minimo", "#mp-observaciones"].forEach(s => { $(s).value = ""; });
    $("#mp-stock").value = 0;
    $("#mp-sin-stock").checked = false;
    $("#mp-unidad").value = "pieza";
    llenarProveedores(0);
    estado.fotoTmp = "";
    $("#mp-foto").innerHTML = "📦";
    $("#mp-borrar").style.display = "none";
    $("#mp-kardex").style.display = "none";
    refrescarMargen();
    pintarFormatos("compra", []);
    pintarFormatos("venta", []);
  }
  abrirModal("#m-prod");
  refrescarUnidadesBase();
  setTimeout(() => $("#mp-nombre").focus(), 120);
}

/* ---------- Formatos de compra y de venta ---------- */

const CATALOGO_FORMATOS = {
  compra: [["unidad", 1], ["kg", 1], ["bolsa", null], ["caja", null], ["maple", null],
           ["cajon", null], ["decena", 10], ["docena", 12], ["centena", 100],
           ["bidon", null], ["paleta", null], ["atado", null], ["barra", null]],
  venta: [["unidad", 1], ["media", null], ["docena", 12], ["decena", 10], ["centena", 100],
          ["2x1", 2], ["3x2", 3], ["oferta", null], ["kg", 1], ["g", null],
          ["L", 1], ["mL", null], ["botella", null]]
};
const FACTOR_SUGERIDO = { decena: 10, docena: 12, centena: 100, "2x1": 2, "3x2": 3, g: 0.001, ml: 0.001 };

/** Dibuja una lista editable de formatos y recalcula costo y margenes. */
function pintarFormatos(ambito, lista) {
  const cont = $("#mp-fmt-" + ambito);
  if (!cont) return;
  estado["fmt_" + ambito] = (lista || []).map(f => ({
    unidad: f.unidad || "", factor: Number(f.factor) || 1,
    precio: f.precio === null || f.precio === undefined ? "" : Number(f.precio),
    margen: f.margen === null || f.margen === undefined ? "" : Number(f.margen),
    predet: !!f.predet
  }));
  dibujarFormatos(ambito);
}

/** Redibuja las filas desde el estado y engancha los eventos. */
function dibujarFormatos(ambito) {
  const cont = $("#mp-fmt-" + ambito);
  if (!cont) return;
  const filas = estado["fmt_" + ambito] || [];
  if (!filas.length) {
    cont.innerHTML = '<p class="parrafo fmt-vacio">Ninguno todavía. Agregá al menos uno.</p>';
    refrescarCostoYMargenes();
    return;
  }
  const esCompra = ambito === "compra";
  cont.innerHTML = filas.map((f, i) => `
    <div class="fmt-fila" data-i="${i}">
      <input class="fmt-unidad" list="cat-fmt-${ambito}" placeholder="${esCompra ? "maple" : "docena"}" maxlength="20" value="${esc(f.unidad)}">
      <input class="fmt-factor" type="number" step="0.0001" min="0.0001" placeholder="factor" value="${f.factor}">
      ${esCompra
        ? `<input class="fmt-precio" type="number" step="0.01" min="0" placeholder="precio" value="${f.precio}">`
        : `<input class="fmt-precio" type="number" step="0.01" min="0" placeholder="precio" value="${f.precio}">
           <input class="fmt-margen" type="number" step="0.1" placeholder="% costo" value="${f.margen}">`}
      <span class="fmt-acciones">
        <button type="button" class="btn sm fmt-predet ${f.predet ? " on" : ""}" data-predet="${ambito}:${i}" title="Usar este por defecto" aria-pressed="${f.predet ? "true" : "false"}">★</button>
        <button type="button" class="btn sm peligro fmt-quitar" title="Quitar este formato">✕</button>
      </span>
      <span class="fmt-calc"></span>
    </div>`).join("");

  const cont2 = $("#mp-fmt-" + ambito);
  cont2.querySelectorAll(".fmt-fila").forEach(fila => {
    const i = Number(fila.dataset.i);
    const sync = () => {
      const f = estado["fmt_" + ambito][i];
      f.unidad = fila.querySelector(".fmt-unidad").value.trim();
      f.factor = Number(fila.querySelector(".fmt-factor").value) || 1;
      f.precio = fila.querySelector(".fmt-precio").value === "" ? "" : Number(fila.querySelector(".fmt-precio").value);
      const mg = fila.querySelector(".fmt-margen");
      f.margen = mg ? (mg.value === "" ? "" : Number(mg.value)) : "";
      refrescarCostoYMargenes();
    };
    fila.querySelectorAll("input").forEach(inp => {
      inp.addEventListener("input", sync);
      // Si elige un nombre del catalogo, se le completa el factor solo.
      if (inp.classList.contains("fmt-unidad")) {
        inp.addEventListener("change", () => {
          const sug = FACTOR_SUGERIDO[(inp.value || "").toLowerCase()];
          if (sug) { fila.querySelector(".fmt-factor").value = sug; }
          sync();
        });
      }
    });
    fila.querySelector(".fmt-quitar").addEventListener("click", () => {
      estado["fmt_" + ambito].splice(i, 1);
      dibujarFormatos(ambito);
    });
    // Solo un formato por lista puede quedar como predeterminado.
    const btnPredet = fila.querySelector(".fmt-predet");
    if (btnPredet) {
      btnPredet.addEventListener("click", () => {
        const lista = estado["fmt_" + ambito];
        const yaEra = lista[i].predet;
        lista.forEach(f => { f.predet = false; });
        lista[i].predet = !yaEra;
        dibujarFormatos(ambito);
      });
    }
  });
  refrescarCostoYMargenes();
}

function agregarFilaFormato(ambito) {
  estado["fmt_" + ambito] = estado["fmt_" + ambito] || [];
  estado["fmt_" + ambito].push({ unidad: "", factor: 1, precio: "", margen: "", predet: false });
  dibujarFormatos(ambito);
  const filas = $("#mp-fmt-" + ambito).querySelectorAll(".fmt-unidad");
  if (filas.length) filas[filas.length - 1].focus();
}

/**
 * El costo por unidad sale del formato de compra marcado como predeterminado
 * (o del primero con precio). Con ese costo se arma el precio de los formatos
 * de venta que usan % en vez de precio fijo.
 */
function refrescarCostoYMargenes() {
  if (!$("#mp-fmt-compra")) return;
  const compra = estado.fmt_compra || [];
  const base = (() => {
    const c = compra.find(f => f.predet && Number(f.precio) > 0) || compra.find(f => Number(f.precio) > 0);
    if (!c) return 0;
    const fac = Number(c.factor) > 0 ? Number(c.factor) : 1;
    return Math.round((Number(c.precio) / fac) * 100) / 100;
  })();
  estado.costoCalculado = base;
  if ($("#mp-costo")) $("#mp-costo").value = base || "";

  // Muestra el costo estimado en cada fila de formato.
  (compra || []).forEach((f, i) => {
    const cel = $("#mp-fmt-compra .fmt-fila[data-i='" + i + "'] .fmt-calc");
    if (!cel) return;
    const fac = Number(f.factor) > 0 ? Number(f.factor) : 1;
    cel.textContent = f.unidad && Number(f.precio) > 0
      ? "= " + dinero(Number(f.precio) / fac) + " c/u"
      : "";
  });
  ((estado.fmt_venta) || []).forEach((f, i) => {
    const cel = $("#mp-fmt-venta .fmt-fila[data-i='" + i + "'] .fmt-calc");
    if (!cel) return;
    const fac = Number(f.factor) > 0 ? Number(f.factor) : 1;
    const porUnidad = f.precio !== "" && Number(f.precio) > 0
      ? Number(f.precio) / fac
      : (f.margen !== "" && base > 0 ? base * (1 + Number(f.margen) / 100) : 0);
    if (porUnidad <= 0) { cel.textContent = ""; return; }
    // El precio de la fila es el de un formato entero; el c/u es la division.
    cel.textContent = fac === 1
      ? ("= " + dinero(porUnidad) + " c/u")
      : ("= " + dinero(porUnidad * fac) + " el " + (f.unidad || "formato")
         + " \u00B7 " + dinero(porUnidad) + " c/u");
  });

  const av = $("#mp-costo-aviso");
  if (av) av.textContent = base > 0 ? "(del formato de compra)" : "";
  const av2 = $("#mp-precio-aviso");
  if (av2) av2.textContent = "";

  // Sugiere el precio del formato de venta predeterminado si esta vacio.
  const dflt = (estado.fmt_venta || []).find(f => f.predet) || (estado.fmt_venta || [])[0];
  if (dflt && (dflt.precio === "" || dflt.precio === null) && base > 0
      && dflt.margen !== "" && Number(dflt.margen) !== 0) {
    const fac = Number(dflt.factor) > 0 ? Number(dflt.factor) : 1;
    const sugerido = Math.round(base * (1 + Number(dflt.margen) / 100) * fac * 100) / 100;
    const inp = $("#mp-precio");
    if (inp && !Number(inp.value)) { inp.value = sugerido; }
  }
  refrescarMargen();
}

function refrescarUnidadesBase() {
  const u = $("#mp-unidad").value;
  const base = (u === "kg") ? "kilos" : (u === "litro" ? "litros" : "unidades");
  document.querySelectorAll(".mp-base").forEach(e => { e.textContent = base; });
  refrescarCostoYMargenes();
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
        <td class="num">${m.cantidad > 0 ? "+" : ""}${esc(cantidadTxt(m.cantidad, m.unidad))}</td>
        <td class="num">${esc(cantidadTxt(m.stock_anterior, m.unidad))} → <strong>${esc(cantidadTxt(m.stock_actual, m.unidad))}</strong></td>
        <td class="fuente">${esc(m.referencia || "")}${m.nota ? `<br><span class="fuente" style="font-size:11.5px">${esc(m.nota)}</span>` : ""}</td>
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
    sin_stock: $("#mp-sin-stock").checked ? 1 : 0,
    unidad: $("#mp-unidad").value,
    formatos_compra: (estado.fmt_compra || []).filter(f => f.unidad),
    formatos_venta: (estado.fmt_venta || []).filter(f => f.unidad),
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

/* =====================================================================
   PRODUCTO RAPIDO — venta espontanea
   ---------------------------------------------------------------------
   El pancho que pidio Pepe, una pizza armada en el momento: se carga el
   producto ahi, con el precio de hoy, y se cobra. No sale de un stock que
   se cuente, asi que va marcado con sin_stock y no toca existencias.

   El costo se deja en 0 a proposito. Los valores del proveedor cambian
   todos los dias y armar una receta por producto prepared no esta a la
   altura del negocio todavia: lo que manda es el precio que se cobra.

   Si el nombre ya existe no se duplica: se reutiliza el producto y, si el
   precio cambio, se pregunta antes de tocarlo.
   ===================================================================== */

const CATEGORIA_RAPIDA = "Venta libre";

/** Busca un producto por nombre ignorando mayusculas, acentos y espacios. */
function productoPorNombre(nombre) {
  const n = norm(nombre).replace(/\s+/g, " ").trim();
  if (!n) return null;
  return estado.productos.find(p => norm(p.nombre).replace(/\s+/g, " ").trim() === n) || null;
}

/** Un producto con formato de venta predeterminado toma el precio de ahi,
 *  no del campo "precio": tipearlo en la venta rapida no haria nada. */
function precioVieneDeFormato(p) {
  return !!(p && (p.formatos_venta || []).some(f => f.predet));
}

function abrirRapido() {
  $("#pr-nombre").value = "";
  $("#pr-precio").value = "";
  const cats = categorias();
  $("#pr-categoria").value = cats.includes(CATEGORIA_RAPIDA) ? CATEGORIA_RAPIDA : (cats[0] || CATEGORIA_RAPIDA);
  $("#pr-existe").style.display = "none";
  abrirModal("#m-rapido");
  setTimeout(() => $("#pr-nombre").focus(), 120);
}

/** Mientras se escribe el nombre, avisa si ya está en el catálogo. */
function avisarExisteRapido() {
  const p = productoPorNombre($("#pr-nombre").value.trim());
  const caja = $("#pr-existe");
  if (!p) { caja.style.display = "none"; return; }
  caja.style.display = "";
  let texto = p.nombre + " — " + dinero(p.precio);
  if (precioVieneDeFormato(p)) texto += " (el precio sale de su formato de venta)";
  else if (p.sin_stock) texto += " (venta libre)";
  $("#pr-existe-nombre").textContent = texto;
  if (!$("#pr-precio").value) $("#pr-precio").value = p.precio;
}

async function guardarRapido() {
  const btn = $("#pr-guardar");
  const nombre = $("#pr-nombre").value.trim();
  const precio = Number($("#pr-precio").value) || 0;
  const categoria = $("#pr-categoria").value.trim() || CATEGORIA_RAPIDA;

  if (!nombre) { aviso("Escribe qué es lo que se lleva el cliente.", "aviso-w"); $("#pr-nombre").focus(); return; }
  if (precio <= 0) { aviso("Poné el precio de venta.", "aviso-w"); $("#pr-precio").focus(); return; }

  const previo = productoPorNombre(nombre);
  const mismoPrecio = previo && Math.round(Number(previo.precio) * 100) === Math.round(precio * 100);
  const mandaElFormato = precioVieneDeFormato(previo);

  // El precio es el del producto (no se cobra distinto en cada venta), asi que
  // si cambió se avisa antes de sobrescribirlo.
  if (previo && !mismoPrecio) {
    if (mandaElFormato) {
      // Tipear el precio acá no serviría de nada: manda el formato. Mejor decirlo
      // que guardar en silencio y que el producto quede con el precio viejo.
      const ok = await confirmar("El precio sale del formato",
        '"' + previo.nombre + '" está en ' + dinero(previo.precio)
        + " y ese precio sale de su formato de venta, no del producto.\n\n"
        + "Para cambiarlo hay que editar el formato. ¿Lo agrego al ticket con el precio actual?");
      if (!ok) { $("#pr-precio").focus(); return; }
      cerrarModal("#m-rapido");
      agregar(previo.id, 1);
      aviso("Agregado: " + previo.nombre, "ok");
      $("#txt-buscar").focus();
      return;
    }
    const ok = await confirmar("Cambia el precio",
      '"' + previo.nombre + '" está en ' + dinero(previo.precio) + ' y lo estás cargando a ' + dinero(precio)
      + ".\n\n¿Querés actualizar el precio del producto?");
    if (!ok) { $("#pr-precio").focus(); return; }
  }

  btn.disabled = true;
  try {
    if (!previo) {
      // Nuevo: costo y stock en 0 a propósito, y marcado sin control de stock.
      await api("producto_guardar", {
        nombre, categoria, precio,
        costo: 0, stock: 0, minimo: 0, unidad: "pieza",
        sin_stock: 1, activo: 1
      });
    } else if (!mismoPrecio) {
      // Ya existe: se manda solo el precio. El resto no se toca, asi que un
      // producto que sí lleva stock no pierde su costo, su existencia ni su
      // categoría por pasar por la venta rápida.
      await api("producto_guardar", { id: previo.id, precio });
    }

    if (previo && mismoPrecio) {
      // No hay nada que guardar: estaba igual, solo va al ticket.
      cerrarModal("#m-rapido");
      agregar(previo.id, 1);
      aviso("Agregado: " + previo.nombre, "ok");
      $("#txt-buscar").focus();
      return;
    }

    await cargarProductos();
    renderFiltros();
    renderGrid();

    const guardado = productoPorNombre(previo ? previo.nombre : nombre);
    cerrarModal("#m-rapido");
    if (guardado) agregar(guardado.id, 1);
    aviso((previo ? "Precio actualizado" : "Producto cargado") + ": " + nombre, "ok");
    $("#txt-buscar").focus();
  } catch (e) {
    aviso(e.message, "mal");
  } finally {
    btn.disabled = false;
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

function abrirStock(tipo, productoId) {
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
  if (productoId) { elegirStock(productoId); return; }
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
        <div class="s">Stock actual: ${esc(cantidadTxt(p.stock, p.unidad))}</div>
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
    + '<span class="etq neutro">' + esc(cantidadTxt(stockSel.stock, stockSel.unidad)) + " en existencia</span> " + extra;

  const entrada = $("#ms-guardar").dataset.tipo !== "salida";
  const formatos = entrada ? (stockSel.formatos_compra || []) : [];

  $("#ms-campo-uc").style.display = formatos.length ? "" : "none";
  $("#ms-campo-pc").style.display = formatos.length ? "" : "none";
  $("#ms-campo-costo").style.display = formatos.length ? "none" : "";

  if (formatos.length) {
    // El predeterminado entra preseleccionado con su precio cargado.
    const sel = $("#ms-formato-compra");
    sel.innerHTML = formatos.map(f => {
      const etq = f.unidad + (Number(f.factor) !== 1
        ? " — " + cantidadTxt(Number(f.factor), stockSel.unidad || "pieza") : "");
      return `<option value="${f.id}">${esc(etq)}</option>`;
    }).join("");
    const predet = formatos.find(f => f.predet) || formatos[0];
    sel.value = String(predet.id);
    aplicarFormatoCompra();
    $("#ms-cant").value = 1;
  } else {
    // Sin formatos de compra: se resta en unidad base y se carga el costo.
    $("#ms-precio-compra").value = 0;
    $("#ms-cant-unidad").textContent = "(" + (stockSel.unidad || "pieza") + ")";
  }
  refrescarPreviewStock();
  $("#ms-guardar").disabled = false;
  $("#ms-cant").focus();
  $("#ms-cant").select();
}

/** Carga precio y factor del formato de compra elegido. */
function aplicarFormatoCompra() {
  const sel = $("#ms-formato-compra");
  if (!sel || !stockSel) return;
  const formatos = stockSel.formatos_compra || [];
  const f = formatos.find(x => x.id === Number(sel.value));
  if (!f) return;
  $("#ms-precio-compra").value = Number(f.precio) > 0 ? f.precio : "";
  $("#ms-pc-label").textContent = f.unidad;
  refrescarPreviewStock();
}

/** Muestra quantas unidades base entran y a cuanto queda el costo. */
function refrescarPreviewStock() {
  if (!stockSel) return;
  const p = $("#ms-preview");
  const cant = Number($("#ms-cant").value) || 0;
  const entrada = $("#ms-guardar").dataset.tipo !== "salida";
  const formatos = entrada ? (stockSel.formatos_compra || []) : [];
  const f = formatos.find(x => x.id === Number($("#ms-formato-compra") && $("#ms-formato-compra").value));
  const factor = f ? (Number(f.factor) || 1) : 1;
  const suma = r2(cant * factor);
  if (!suma) { p.style.display = "none"; return; }
  p.style.display = "";
  const partes = ["<b>" + esc(cantidadTxt(suma, stockSel.unidad)) + "</b> (" + cant + " "
    + (f ? esc(f.unidad) + " × " + cantidadTxt(factor, stockSel.unidad) : esc(stockSel.unidad || "pieza")) + ")",
    "quedan " + esc(cantidadTxt(r2(stockSel.stock + (entrada ? suma : -suma)), stockSel.unidad))];
  const pc = Number($("#ms-precio-compra").value) || 0;
  if (entrada && pc > 0 && factor > 0) {
    const nuevo = r2(pc / factor);
    partes.push("costo " + dinero(nuevo) + " por " + esc(stockSel.unidad || "pieza"));
    if (nuevo !== r2(stockSel.costo)) {
      const actual = stockSel.costo > 0
        ? " (antes " + dinero(stockSel.costo) + ")" : " (sin costo cargado)";
      partes.push("el costo del producto cambia" + actual);
    }
  }
  p.innerHTML = partes.join(" · ");
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
    const formatos = stockSel.formatos_compra || [];
    const f = formatos.find(x => x.id === Number($("#ms-formato-compra").value));
    if (f) {
      cuerpo.formato_id = f.id;
      cuerpo.formato_unidad = f.unidad;
      cuerpo.formato_factor = f.factor;
      const pc = Number($("#ms-precio-compra").value) || 0;
      if (pc > 0) cuerpo.precio_formato = pc;
    } else {
      const costo = Number($("#ms-costo").value) || 0;
      if (costo > 0) cuerpo.costo_unitario = costo;
    }
  }
  try {
    const r = await api("stock_mover", cuerpo);
    cerrarModal("#m-stock");
    let msg = "Movimiento registrado. Stock actual: " + cantidadTxt(r.stock, stockSel ? stockSel.unidad : "");
    if (r.costo > 0) msg += " · costo " + dinero(r.costo) + " por unidad";
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
      <td class="num fuente">${esc(cantidadTxt(i.cantidad, i.unidad))} × ${dinero(i.precio)}</td>
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
    + "<tr><td>&nbsp;&nbsp;" + esc(cantidadTxt(i.cantidad, i.unidad)) + " x " + dinero(i.precio, false) + "</td>"
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

  imprimirTicketAhora("venta");
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
        <div class="sub">${numeroLocal(s.unidades, 2)} unidades en almacén</div></div>`;

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
        <td class="num">${numeroLocal(t.unidades, 2)}</td>
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
<td class="num"><span class="etq ${f.stock <= 0 ? "mal" : "aviso"}">${esc(cantidadTxt(f.stock, f.unidad))}</span></td>
<td class="num fuente">${esc(cantidadTxt(f.minimo, f.unidad))}</td>
<td class="num">${esc(cantidadTxt(Math.round(sugerido * 100) / 100, f.unidad))}</td>
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
        filas.push([f.nombre, "", cantidadTxt(f.stock, f.unidad), cantidadTxt(f.minimo, f.unidad), sug, f.unidad || "", r2(sug * f.precio).toFixed(2)]);
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
        <td><strong>${esc(m.nombre)}</strong></td>
        <td>${m.efectivo ? '<span class="etq ok">sí</span>' : '<span class="fuente">no</span>'}</td>
        <td>${m.referencia ? '<span class="etq neutro">sí</span>' : '<span class="fuente">no</span>'}</td>
        <td>${m.activo ? '<span class="etq ok">Activo</span>' : '<span class="etq neutro">Inactivo</span>'}</td>
        <td class="acciones">
          <button class="btn sm" data-mp-editar="${m.id}">✎</button>
          <button class="btn sm peligro" data-mp-borrar="${m.id}">🗑</button>
        </td>
      </tr>`).join("")
      : '<tr><td colspan="6" style="padding:20px;text-align:center;color:var(--muted)">Sin medios de pago</td></tr>';
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

  await Promise.all([cargarMediosPago(), cargarProveedores(), cargarUsuarios()]);

  try {
    const r = await api("estado");
    const info = [
      ["Servidor web", "Apache + PHP " + r.php_version],
      ["Base de datos", "MySQL " + r.mysql_version],
      ["Productos en catálogo", numeroLocal(r.productos, 0)],
      ["Ventas de hoy", numeroLocal(r.hoy.ventas, 0) + " · " + dinero(r.hoy.total)],
      ["Valor del inventario", dinero(r.valor_inventario)],
      ["Ubicación", "C:\\laragon\\www\\secmkiosko"]
    ];
    $("#a-estado").innerHTML = info.map(i =>
      '<div class="fila" style="padding:6px 0"><span>' + esc(i[0]) + '</span><b>' + esc(i[1]) + "</b></div>"
    ).join("") + '<div class="parrafo" style="margin:14px 0 0;font-size:12.5px">'
      + "Para abrir este sistema en otra computadora: instala Laragon, copia la carpeta "
      + "<code>secmkiosko</code> dentro de <code>www</code>, abre <code>instalar.php</code> una vez "
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
const pedirClave = (t, r, a, v) => pedirDato(t, r, a, v, "password");
const pedirNumero = (t, r, a, v) => pedirDato(t, r, a, v, "number");

/** Devuelve true (sí), false (no) o null (cancelado). */
async function pedirSiNo(titulo, ayuda) {
  const r = await pedirDato(titulo, "Sí / No", ayuda, "si", "text");
  if (r === null) return null;
  return /^(si|sí|s|1|true|yes)$/i.test(String(r).trim());
}

/* =====================================================================
    15. CAJAS
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

/* =====================================================================
    16. USUARIOS
    ===================================================================== */
async function cargarUsuarios() {
  const tb = $("#usuarios-tb");
  if (!tb) return;
  try {
    const r = await api("usuarios");
    estado.usuarios = r.usuarios;
  } catch (e) {
    return;   // el vendedor no tiene acceso a esta lista
  }
  const yo = estado.yo;
  tb.innerHTML = estado.usuarios.map(u => {
    const esYo = yo && u.id === yo.id;
    return "<tr>"
      + '<td style="font-size:17px">' + (u.rol === "admin" ? "🔑" : "🧑") + "</td>"
      + "<td><b>" + esc(u.usuario) + "</b>" + (esYo ? ' <span class="etq ok">vos</span>' : "") + "</td>"
      + "<td>" + esc(u.nombre) + "</td>"
      + '<td><span class="etq ' + (u.rol === "admin" ? "pri" : "neutro") + '">'
      + (u.rol === "admin" ? "Administrador" : "Vendedor") + "</span></td>"
      + '<td class="num">' + u.cajas + "</td>"
      + '<td class="fuente" style="font-size:12px">'
      + (u.ultimo_ingreso ? esc(fechaHora(u.ultimo_ingreso)) : "nunca") + "</td>"
      + "<td>" + (u.activo ? '<span class="etq ok">Activo</span>' : '<span class="etq mal">Inactivo</span>') + "</td>"
      + '<td class="acciones">'
      + '<button class="btn sm" data-us-editar="' + u.id + '">✎</button>'
      + (esYo ? "" : '<button class="btn sm peligro" data-us-borrar="' + u.id + '">🗑</button>')
      + "</td></tr>";
  }).join("");

  $$("#usuarios-tb [data-us-editar]").forEach(b =>
    b.addEventListener("click", () => guardarUsuario(Number(b.dataset.usEditar))));
  $$("#usuarios-tb [data-us-borrar]").forEach(b =>
    b.addEventListener("click", () => borrarUsuario(Number(b.dataset.usBorrar))));
}

function guardarUsuario(id) {
  const u = id ? estado.usuarios.find(x => x.id === id) : null;
  $("#mu-titulo").textContent = u ? "Editar usuario" : "Nuevo usuario";
  $("#mu-usuario").value = u ? u.usuario : "";
  $("#mu-nombre").value = u ? u.nombre : "";
  $("#mu-rol").value = u ? u.rol : "vendedor";
  $("#mu-clave").value = "";
  $("#mu-clave-nota").textContent = u ? "(dejala vacía para no cambiarla)" : "(mínimo 4 caracteres)";
  $("#mu-activo").checked = u ? !!u.activo : true;
  $("#mu-ok").dataset.id = u ? u.id : 0;
  abrirModal("#m-usuario");
  setTimeout(() => $("#mu-usuario").focus(), 60);
}

async function confirmarUsuario() {
  const id = Number($("#mu-ok").dataset.id) || 0;
  const btn = $("#mu-ok");
  btn.disabled = true;
  try {
    await api("usuario_guardar", {
      id,
      usuario: $("#mu-usuario").value.trim().toLowerCase(),
      nombre: $("#mu-nombre").value.trim(),
      rol: $("#mu-rol").value,
      clave: $("#mu-clave").value,
      activo: $("#mu-activo").checked ? 1 : 0
    });
    cerrarModal("#m-usuario");
    aviso(id ? "Usuario actualizado." : "Usuario creado. Le pedí que entre y cambie la clave.", "ok");
    await cargarUsuarios();
  } catch (e) {
    aviso(e.message, "mal");
  } finally {
    btn.disabled = false;
  }
}

async function borrarUsuario(id) {
  const u = estado.usuarios.find(x => x.id === id);
  if (!u) return;
  const ok = await confirmar("Desactivar usuario",
    "«" + u.nombre + "» (usuario " + u.usuario + ") ya no va a poder entrar al kiosco. "
    + "Sus ventas y sus cajas quedan en el historial, así que no se borra nada.");
  if (!ok) return;
  try {
    await api("usuario_borrar", { id });
    aviso("Usuario desactivado.", "ok");
    await cargarUsuarios();
  } catch (e) { aviso(e.message, "mal"); }
}

async function cambiarMiClave() {
  const datos = await pedirDatosClave();
  if (!datos) return;
  try {
    await api("clave_cambiar", datos);
    aviso("Clave cambiada.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}

/* ---------- Ajustes: atajos y zonas ---------- */

async function cargarAjustesCocina() {
  if (!esAdmin()) return;
  const [a, z] = await Promise.all([
    api("comanda_atajos").catch(() => ({ atajos: [] })),
    api("zonas").catch(() => ({ zonas: [] }))
  ]);
  estado.atajos = a.atajos || [];
  estado.zonas = z.zonas || [];
  renderAjustesAtajos();
  renderAjustesZonas();
}

function renderAjustesAtajos() {
  const tb = $("#atajos-tb");
  if (!tb) return;
  if (!estado.atajos.length) {
    tb.innerHTML = '<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--muted)">Todavía no hay atajos.</td></tr>';
    return;
  }
  tb.innerHTML = estado.atajos.map(a => {
    const p = a.producto_id ? prodPorId(a.producto_id) : null;
    return `<tr>
      <td>${esc(a.seccion)}</td>
      <td><b>${esc(a.etiqueta)}</b></td>
      <td>${esc(a.texto || "—")}${a.detalle ? ' <span class="fuente">(' + esc(a.detalle) + ")</span>" : ""}</td>
      <td class="fuente">${p ? esc(p.nombre) + (a.formato_unidad ? " · " + esc(a.formato_unidad) : "") : '<span style="opacity:.6">sólo texto</span>'}</td>
      <td class="acciones">
        <button class="btn sm" data-edit-atajo="${a.id}" title="Editar">✎</button>
        <button class="btn sm peligro" data-borrar-atajo="${a.id}" title="Borrar">🗑</button>
      </td>
    </tr>`;
  }).join("");
}

function renderAjustesZonas() {
  const tb = $("#zonas-tb");
  if (!tb) return;
  if (!estado.zonas.length) {
    tb.innerHTML = '<tr><td colspan="3" style="padding:20px;text-align:center;color:var(--muted)">No hay zonas cargadas.</td></tr>';
    return;
  }
  tb.innerHTML = estado.zonas.map(z => `<tr>
    <td>${esc(z.nombre)}</td>
    <td class="num">${dinero(z.costo)}</td>
    <td class="acciones">
      <button class="btn sm" data-edit-zona="${z.id}" title="Editar">✎</button>
      <button class="btn sm peligro" data-borrar-zona="${z.id}" title="Borrar">🗑</button>
    </td>
  </tr>`).join("");
}

let editAtajo = { id: 0, seccion: "", etiqueta: "", texto: "", detalle: "", producto_id: 0, formato_unidad: "", orden: 1 };
let editZona = { id: 0, nombre: "", costo: 0 };

function abrirAtajo(id) {
  const a = id ? estado.atajos.find(x => x.id === id) : null;
  editAtajo = a
    ? { id: a.id, seccion: a.seccion, etiqueta: a.etiqueta, texto: a.texto || "", detalle: a.detalle || "", producto_id: a.producto_id || 0, formato_unidad: a.formato_unidad || "", orden: a.orden }
    : { id: 0, seccion: "Comidas", etiqueta: "", texto: "", detalle: "", producto_id: 0, formato_unidad: "", orden: estado.atajos.length + 1 };

  $("#atk-titulo").textContent = editAtajo.id ? "Editar atajo" : "Nuevo atajo";
  $("#atk-seccion").value = editAtajo.seccion;
  $("#atk-etiqueta").value = editAtajo.etiqueta;
  $("#atk-texto").value = editAtajo.texto;
  $("#atk-detalle").value = editAtajo.detalle;
  $("#atk-orden").value = editAtajo.orden;

  const sel = $("#atk-producto");
  sel.innerHTML = '<option value="">— sólo texto, no cobra —</option>'
    + estado.productos.filter(p => p.activo).map(p =>
      '<option value="' + p.id + '">' + esc(p.nombre) + "</option>").join("");
  sel.value = String(editAtajo.producto_id || 0);
  refrescarFormatosAtajo();
  abrirModal("#m-atajo");
  setTimeout(() => $("#atk-etiqueta").focus(), 120);
}

function refrescarFormatosAtajo() {
  const pid = Number($("#atk-producto").value) || 0;
  const sel = $("#atk-formato");
  const p = pid ? prodPorId(pid) : null;
  const formatos = p ? (p.formatos_venta || []) : [];
  sel.innerHTML = '<option value="">— el predeterminado —</option>'
    + formatos.map(f => '<option value="' + esc(f.unidad) + '">' + esc(f.unidad) + " × " + numeroLocal(f.factor, 0) + "</option>").join("");
  sel.value = editAtajo.formato_unidad || "";
  sel.disabled = formatos.length === 0;
}

async function guardarAtajo() {
  const datos = {
    id: editAtajo.id,
    seccion: $("#atk-seccion").value.trim() || "Comidas",
    etiqueta: $("#atk-etiqueta").value.trim(),
    texto: $("#atk-texto").value.trim(),
    detalle: $("#atk-detalle").value.trim(),
    producto_id: Number($("#atk-producto").value) || 0,
    formato_unidad: $("#atk-formato").value || "",
    orden: Number($("#atk-orden").value) || 0
  };
  try {
    await api("atajo_guardar", datos);
    cerrarModal("#m-atajo");
    await cargarAjustesCocina();
    if (estado.comanda) renderAtajos();
    aviso("Atajo guardado.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}

function abrirZona(id) {
  const z = id ? estado.zonas.find(x => x.id === id) : null;
  editZona = z ? { id: z.id, nombre: z.nombre, costo: z.costo } : { id: 0, nombre: "", costo: 0 };
  $("#zn-titulo").textContent = editZona.id ? "Editar zona" : "Nueva zona";
  $("#zn-nombre").value = editZona.nombre;
  $("#zn-costo").value = editZona.costo;
  abrirModal("#m-zona");
  setTimeout(() => $("#zn-nombre").focus(), 120);
}

async function guardarZona() {
  const datos = { id: editZona.id, nombre: $("#zn-nombre").value.trim(), costo: Number($("#zn-costo").value) || 0 };
  try {
    await api("zona_guardar", datos);
    cerrarModal("#m-zona");
    await cargarAjustesCocina();
    aviso("Zona guardada.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}

async function pedirDatosClave() {
  const actual = await pedirClave("Cambiar mi clave", "Clave actual", "La que usás hoy", "");
  if (actual === null) return null;
  const nueva = await pedirClave("Cambiar mi clave", "Clave nueva", "Mínimo 4 caracteres", "");
  if (nueva === null) return null;
  const rep = await pedirClave("Cambiar mi clave", "Repetí la clave nueva", "", "");
  if (rep === null) return null;
  return { actual, nueva, repetir: rep };
}

/* =====================================================================
    17. CARGA DE DATOS
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
    const t = e.target.closest("button");
    if (!t) return;
    if (t.dataset.fmtLinea) {
      // Cambiar de formato: si la linea destino ya existe, se le suma 1.
      const id = Number(t.dataset.fmtLinea);
      const nuevo = Number(t.dataset.fmtNuevo);
      const actual = Number(t.dataset.fmtActual) || 0;
      if (nuevo === actual) return;
      cambiarFormato(id, nuevo);
      return;
    }
    if (t.dataset.mas) cambiarCantidad(t.dataset.mas, 1, t.dataset.masFmt);
    else if (t.dataset.menos) cambiarCantidad(t.dataset.menos, -1, t.dataset.menosFmt);
    else if (t.dataset.quitar) quitarLinea(t.dataset.quitar, t.dataset.quitarFmt);
  });

  $("#carrito-items").addEventListener("change", e => {
    const inp = e.target;
    if (!inp.dataset.cant) return;
    const id = Number(inp.dataset.cant);
    const fId = Number(inp.dataset.cantFmt) || 0;
    const v = r2(Number(inp.value) || 0);
    if (v <= 0) { quitarLinea(id, fId); return; }
    const l = estado.carrito.find(x => x.id === id && (x.formato_id || 0) === fId);
    if (l) { l.cantidad = v; renderCarrito(); }
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

  /* --- delivery / comanda --- */
  escuchar("#btn-delivery", "click", abrirDelivery);

  escuchar("#del-tipo", "click", e => {
    const b = e.target.closest("button[data-tipo]");
    if (!b) return;
    estado.comanda.tipo = b.dataset.tipo;
    $$("#del-tipo button").forEach(x => x.classList.toggle("on", x === b));
    actualizarCamposDelivery();
    refrescarEnvio();
  });

  escuchar("#del-zona", "change", aplicarZona);

  escuchar("#del-atajos", "click", e => {
    const b = e.target.closest("[data-atajo]");
    if (!b) return;
    usarAtajo(b.dataset.atajo);
  });

  escuchar("#del-texto", "input", e => {
    // El detalle aparece sólo cuando hay algo que detallar.
    $("#del-detalle-wrap").hidden = !e.target.value.trim();
  });

  escuchar("#del-texto", "keydown", e => {
    if (e.key !== "Enter") return;
    e.preventDefault();
    agregarItemComanda({
      texto: e.target.value.trim(),
      detalle: $("#del-detalle").value.trim(),
      producto_id: 0, cantidad: 1
    });
    e.target.value = "";
    $("#del-detalle").value = "";
    $("#del-detalle-wrap").hidden = true;
  });

  escuchar("#del-items", "click", e => {
    const b = e.target.closest("[data-quitar-com]");
    if (!b) return;
    estado.comanda.items.splice(Number(b.dataset.quitarCom), 1);
    renderItemsDelivery();
  });

  escuchar("#del-guardar", "click", () => {
    leerCamposDelivery();
    if (!estado.comanda.cliente) {
      aviso("Poné el nombre del cliente.", "mal");
      $("#del-cliente").focus();
      return;
    }
    if (estado.comanda.tipo === "delivery" && !estado.comanda.direccion) {
      aviso("Falta la dirección de entrega.", "mal");
      $("#del-direccion").focus();
      return;
    }
    if (estado.comanda.tipo === "mesa" && !estado.comanda.lugar) {
      aviso("¿Qué mesa o lugar es?", "mal");
      $("#del-lugar").focus();
      return;
    }
    cerrarModal("#m-delivery");
    renderBotonCobrar();
    if (!estado.carrito.length) {
      aviso("Esa comanda es sólo texto: agregá algún producto al ticket para poder cobrarla.", "aviso-w");
      return;
    }
    abrirCobro();
  });

  /* --- delivery: ver o sacar la comanda armada --- */
  escuchar("#btn-ver-comanda", "click", abrirDelivery);
  escuchar("#btn-quitar-comanda", "click", () => {
    estado.comanda = null;
    renderCarrito();
    aviso("Comanda quitada del ticket.");
  });

  /* --- tablero de cocina --- */
  escuchar("#coc-filtros", "click", e => {
    const b = e.target.closest("button[data-coc]");
    if (!b) return;
    estado.cocFiltro = b.dataset.coc;
    $$("#coc-filtros button").forEach(x => x.classList.toggle("on", x === b));
    cargarComandas().catch(e => aviso(e.message, "mal"));
  });

  escuchar("#coc-tablero", "click", async e => {
    const btnEstado = e.target.closest("[data-estado-com]");
    const btnItem   = e.target.closest("[data-item-com]");
    const btnPrint  = e.target.closest("[data-print-com]");
    try {
      if (btnEstado) {
        const art = btnEstado.closest("[data-comanda]");
        btnEstado.disabled = true;
        await moverComanda(Number(art.dataset.comanda), btnEstado.dataset.estadoCom);
      } else if (btnItem) {
        btnItem.disabled = true;
        await marcarItem(Number(btnItem.dataset.itemCom));
      } else if (btnPrint) {
        imprimirComanda(Number(btnPrint.dataset.printCom));
      }
    } catch (err) {
      aviso(err.message, "mal");
    }
  });

  /* --- ajustes de atajos y zonas --- */
  escuchar("#btn-atajo-nuevo", "click", () => abrirAtajo(0));
  escuchar("#btn-zona-nueva", "click", () => abrirZona(0));
  escuchar("#atajos-tb", "click", async e => {
    const ed = e.target.closest("[data-edit-atajo]");
    const bo = e.target.closest("[data-borrar-atajo]");
    if (ed) abrirAtajo(Number(ed.dataset.editAtajo));
    if (bo) await confirmar("Borrar el atajo", "Se pierde el botón, no los productos.", async () => {
      await api("atajo_borrar", { id: Number(bo.dataset.borrarAtajo) });
      await cargarAjustesCocina();
    });
  });
  escuchar("#zonas-tb", "click", async e => {
    const ed = e.target.closest("[data-edit-zona]");
    const bo = e.target.closest("[data-borrar-zona]");
    if (ed) abrirZona(Number(ed.dataset.editZona));
    if (bo) await confirmar("Borrar la zona", "Las comandas viejas guardan el nombre.", async () => {
      await api("zona_borrar", { id: Number(bo.dataset.borrarZona) });
      await cargarAjustesCocina();
    });
  });
  escuchar("#atk-producto", "change", refrescarFormatosAtajo);
  escuchar("#atk-guardar", "click", guardarAtajo);
  escuchar("#zn-guardar", "click", guardarZona);

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
    else if (t.dataset.stock) abrirStock("entrada", t.dataset.stock);
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

  /* --- producto rápido (venta espontánea) --- */
  $("#btn-rapido").addEventListener("click", abrirRapido);
  $("#pr-guardar").addEventListener("click", guardarRapido);
  $("#pr-nombre").addEventListener("input", avisarExisteRapido);
  $("#pr-nombre").addEventListener("keydown", e => {
    if (e.key === "Enter") { e.preventDefault(); guardarRapido(); }
  });

  /* --- modal producto --- */
  $("#mp-guardar").addEventListener("click", guardarProducto);  $("#mp-btn-foto").addEventListener("click", () => $("#mp-file").click());
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

  /* --- formatos de compra y de venta --- */
  ["#ms-cant", "#ms-precio-compra"]
    .forEach(s => { const e = $(s); if (e) e.addEventListener("input", refrescarPreviewStock); });
  const selFmtCompra = $("#ms-formato-compra");
  if (selFmtCompra) selFmtCompra.addEventListener("change", aplicarFormatoCompra);
  const selUnidad = $("#mp-unidad");
  if (selUnidad) selUnidad.addEventListener("change", refrescarUnidadesBase);
  [["#mp-add-compra", "compra"], ["#mp-add-venta", "venta"]].forEach(([sel, amb]) => {
    const b = $(sel);
    if (b) b.addEventListener("click", () => agregarFilaFormato(amb));
  });

  // Datalists con el catalogo de formatos (sugeren el nombre, el factor lo
  // completa el JS cuando coincide con un multiplo conocido).
  const ancla = $("#m-prod");
  ["compra", "venta"].forEach(amb => {
    let dl = document.getElementById("cat-fmt-" + amb);
    if (!dl && ancla) {
      dl = document.createElement("datalist");
      dl.id = "cat-fmt-" + amb;
      ancla.appendChild(dl);
    }
    if (!dl) return;
    dl.innerHTML = CATALOGO_FORMATOS[amb]
      .map(([n]) => `<option value="${esc(n)}">`).join("");
  });

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
        fechaCorta(v.fecha), dosDig(d.getHours()) + ":" + dosDig(d.getMinutes()),
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
    if (estado.config.tema === "ocuro") document.body.classList.add("t.ocuro");
    aplicarMarca();
    renderPOS();
    $("#txt-buscar").focus();
    // Zonas y atajos se cargan siempre: el botón de delivery los necesita.
    api("comanda_atajos").then(r => { estado.atajos = r.atajos || []; }).catch(() => {});
    api("zonas").then(r => { estado.zonas = r.zonas || []; }).catch(() => {});
    if (esAdmin()) cargarAjustesCocina().catch(() => {});
  } catch (e) {
    document.body.innerHTML = '<div class="vacio" style="height:100vh">'
      + '<div class="ico">⚠️</div><h3>No se pudo conectar con el sistema</h3>'
      + "<p>" + esc(e.message) + "</p>"
      + '<p style="font-size:12.5px">Verifica que Laragon esté abierto y que MySQL esté encendido.</p>'
      + '<button class="btn pri" onclick="location.reload()">Reintentar</button></div>';
  }
}

document.addEventListener("DOMContentLoaded", iniciar);
