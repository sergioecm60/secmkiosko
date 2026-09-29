"use strict";

/*   3. ESTADO
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
  esAdmin: document.body.getAttribute("data-rol") === "admin",
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
