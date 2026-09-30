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
  zonas: [],
  categorias: [],
  categoriasUsadas: {},
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

// Que categoria va a cocina por omision. El administrador lo define en
// Ajustes -> Mercaderia; el cajero puede dar la vuelta linea por linea.
function esCategoriaCocina(nombre) {
  const c = (estado.categorias || []).find(x => x.nombre === nombre);
  return !!(c && c.cocina);
}

// Las fichas de Vender salen de la lista maestra que maneja el administrador,
// no de lo que haya escrito cada producto. Si la lista todavia no llego (el
// arranque es asincrono) se arma con los productos para no mostrar la pantalla
// vacia; las mayusculas no parten nada porque el UNIQUE no las distingue.
function categorias() {
  if (estado.categorias && estado.categorias.length) {
    return estado.categorias.map(c => c.nombre);
  }
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
