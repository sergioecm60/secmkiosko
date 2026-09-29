"use strict";

/*   4. CARRITO
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
const totalCarrito    = () => r2(Math.max(0, subtotalCarrito() - estado.descuento));
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
  $("#c-count").textContent = estado.carrito.length;
  $("#c-sub").textContent = dinero(sub);
  $("#c-total").textContent = dinero(tot);
  $("#fila-desc").style.display = estado.descuento > 0 ? "" : "none";
  $("#c-desc").textContent = "-" + dinero(estado.descuento);
  renderBotonCobrar();
}

/** El botón de cobrar se enciende con el carrito. Nada más lo controla:
 *  la comanda ya no viaja con la venta. */
function renderBotonCobrar() {
  const btn = $("#btn-cobrar");
  if (btn) btn.disabled = estado.carrito.length === 0;
}
