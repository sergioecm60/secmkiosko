"use strict";

/*   8. MOVIMIENTOS DE MERCANCERÍA
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
