"use strict";

/*   5. PUNTO DE VENTA
   ===================================================================== */
/**
 * Busca un producto por codigo de barras exacto, sin passar por el buscador.
 *
 * Va aparte de Coincidencias() a proposito: esa ordena por relevancia de texto
 * y un prefijo de codigo tambien coincide con varios. El escaneo tiene que
 * ser exacto o no es un escaneo, asi que compara el codigo entero.
 *
 * Un codigo interno de cocina tambien es un EAN-13 de 13 digitos, asi que
 * entra por el mismo lado que uno de fábrica sin tratamento especial.
 */
function productoPorCodigo(codigo) {
  const c = norm(codigo).trim();
  if (!c) return null;
  return estado.productos.find(p => p.codigo && norm(p.codigo) === c) || null;
}

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
    /* Un producto de almacen sin existencias no se puede vender, asi que se
       muestra como tal. Lo mismo con el que todavia no tiene precio cargado:
       se puede ver, pero todavia no se puede cobrar. */
    const noVensible = libre ? false
      : (p.stock <= 0 ? "sin stock" : (Number(p.precio) <= 0 ? "sin precio" : ""));
    const marca = libre ? '<span class="marca libre">a pedido</span>'
      : (noVensible ? '<span class="marca">' + noVensible + "</span>"
      : (p.minimo > 0 && p.stock <= p.minimo ? '<span class="marca bajo">bajo</span>' : ""));
    return `<div class="prod${noVensible ? " sin-stock" : ""}" data-prod="${p.id}" title="${esc(p.nombre)}">
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
