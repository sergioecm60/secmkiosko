"use strict";

/*   7. PRODUCTOS
   ===================================================================== */

// El <datalist> que ofrece el campo Categoría del formulario. Va aparte porque
// hay que repintarlo también cuando el administrador agrega o renombra una
// desde Ajustes, sin tener que volver a renderizar toda la grilla.
function pintarListaCategorias() {
  const listaCats = $("#lista-cat");
  if (listaCats) listaCats.innerHTML = categorias().map(c => '<option value="' + esc(c) + '">').join("");
}

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

  pintarListaCategorias();

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
    // El codigo se cargo por codigo, no lo escribio nadie: sin esto el aviso
    // de repetido no apareceria al editar.
    revisarCodigoBarras();
    await cargarKardex(id);
  } else {
    $("#mp-titulo").textContent = "Nuevo producto";
    ["#mp-nombre", "#mp-codigo", "#mp-categoria", "#mp-precio", "#mp-costo",
     "#mp-minimo", "#mp-observaciones"].forEach(s => { $(s).value = ""; });
    $("#mp-codigo-aviso").textContent = "";
    $("#mp-codigo").classList.remove("mal-codigo");
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

/**
 * Marca el campo de codigo segun tenga o no un EAN-13 coherente.
 * Solo avisa: no bloquea, porque un codigo interno con letras es valido.
 */
function revisarCodigoBarras() {
  const campo = $("#mp-codigo");
  const nota = $("#mp-codigo-aviso");
  if (!campo || !nota) return;
  const c = campo.value.trim();
  if (c === "") {
    campo.classList.remove("mal-codigo");
    nota.textContent = "";
    return;
  }
  // Primero el digito verificador: un codigo mal formado no sirve ni para
  // buscar duplicados, asi que se corta ahi.
  if (!codigoAceptable(c)) {
    campo.classList.add("mal-codigo");
    nota.textContent = "debería terminar en " + digitoVerificador(c);
    return;
  }
  campo.classList.remove("mal-codigo");

  // Ahora el repetido. El servidor lo rechaza al guardar, pero ahi el cajero
  // ya lleno todo el formulario: enterlate el aviso mientras escanea, que es
  // como se da de alta un producto. El propio producto se ignora cuando se
  // esta editando, si no siempre seria "repetido" consigo mismo.
  const choque = estado.productos.find(p =>
    p.codigo === c && p.id !== (estado.editId || 0));
  if (choque) {
    campo.classList.add("mal-codigo");
    nota.textContent = "ya lo tiene " + choque.nombre;
    return;
  }
  nota.textContent = "";
}

async function guardarProducto() {
  // El control del EAN-13 va tambien del lado del navegador: si el codigo esta
  // mal, mejor decirlo al toque en vez de mandar y que lo rechace el servidor.
  const codigo = $("#mp-codigo").value.trim();
  if (!codigoAceptable(codigo)) {
    $("#mp-codigo").focus();
    aviso("El código " + codigo + " no es un EAN-13 válido. Debería terminar en "
          + digitoVerificador(codigo) + ".", "mal");
    return;
  }

  const datos = {
    id: estado.editId || 0,
    nombre: $("#mp-nombre").value.trim(),
    codigo: codigo,
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
