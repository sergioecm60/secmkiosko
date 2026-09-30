"use strict";

/* 19. EVENTOS
   ===================================================================== */
function conectar() {

  /* --- pestañas --- */
  $("#tabs").addEventListener("click", e => {
    const b = e.target.closest("button[data-v]");
    if (b) ir(b.dataset.v);
  });

  /* --- tema --- */
  $("#btn-tema").addEventListener("click", async () => {
    const oscuro = document.body.classList.toggle("ocuro");
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
    if (t.dataset.cocina) { alternarCocina(t.dataset.cocina, t.dataset.cocinaFmt); return; }
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

  /* Los eventos de zonas se fueron con el alta de Ajustes. El reparto esta
     deshabilitado; ver la nota en cargarAjustesCocina (js/16_usuarios.js). */

  /* --- categorias --- */
  escuchar("#btn-cat-nuevo", "click", () => abrirCategoria(0));
  escuchar("#ct-guardar", "click", guardarCategoria);
  escuchar("#a-cat-tb", "click", e => {
    const ed = e.target.closest("[data-edit-cat]");
    const bo = e.target.closest("[data-borrar-cat]");
    if (ed) abrirCategoria(Number(ed.dataset.editCat));
    if (bo) borrarCategoria(Number(bo.dataset.borrarCat));
  });

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

  /* --- el codigo de barras se chequea mientras se tipea ---
     Un EAN-13 mal tipeado no lo encuentra el lector, asi que conviene
     avisar en el momento y no cuando el cliente esta esperando. */
  const campoCodigo = $("#mp-codigo");
  if (campoCodigo) {
    campoCodigo.addEventListener("input", revisarCodigoBarras);
    campoCodigo.addEventListener("blur", revisarCodigoBarras);
  }

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

  /* --- ayuda de teclas: la lamparita de Vender ---
     Va antes que el resto de este bloque a proposito. El manejador general de
     Escape, unas lineas mas abajo, agarra Escape para cerrar modales; si este
     lo recibiera primero ya lo habria gastado en cerrar el cartelito. Este solo
     mira si el cartelito esta fijado y, si no, no se ocupa del Escape.
     El hover ya lo abre por CSS. El clic la deja pegada abierta, que es lo
     que hace falta en una pantalla tactil, donde no existe pasar el mouse. */
  const ayuda = $("#ayuda-teclas"), btnAyuda = $("#btn-ayuda-teclas");
  if (ayuda && btnAyuda) {
    btnAyuda.addEventListener("click", () => {
      const fija = ayuda.classList.toggle("fija");
      btnAyuda.setAttribute("aria-expanded", fija ? "true" : "false");
    });
    // Escape cierra la ayuda, como cualquier otra ventana.
    document.addEventListener("keydown", e => {
      if (e.key !== "Escape") return;
      if (ayuda.classList.contains("fija")) {
        ayuda.classList.remove("fija");
        btnAyuda.setAttribute("aria-expanded", "false");
      }
    });
    // La ayuda se cierra sola al salir de Vender: si el cajero la fijo abierta
    // y despues va a cobrar, un cartelito flotando sobre el cobro estorba. Se
    // escucha en la etapa de captura para no depender de si el click cae en un
    // hijo que tenga su propio manejador y frene la propagacion.
    const salirDeVender = () => {
      if (estado.vista !== "vender") {
        ayuda.classList.remove("fija");
        btnAyuda.setAttribute("aria-expanded", "false");
      }
    };
    // Las pestanas de arriba. No hace falta capturar el click: ir() ya cambia
    // estado.vista, asi que con leerlo despues alcanza y no se pisa ningun
    // manejador que ya exista.
    $$("#tabs button").forEach(b => b.addEventListener("click", salirDeVender));
  }

  /* --- atajos de teclado --- */
  document.addEventListener("keydown", e => {
    if (e.key === "F2") { e.preventDefault(); ir("vender"); return; }
    if (e.key === "F4") { e.preventDefault(); if (!$("#m-cobro").classList.contains("on")) abrirCobro(); return; }

    /* Ctrl+P reimprime el ticket abierto. Antes la lista de atajos de Ajustes
       lo prometia pero no existia en ningun lado: el shortcut hacia que el
       navegador abriera su propio dialogo de impresion, que imprime la pagina
       entera en vez del ticket. Se intercepta y se usa el mismo camino que el
       boton Imprimir del ticket. */
    if ((e.ctrlKey || e.metaKey) && (e.key === "p" || e.key === "P")) {
      // Se corta siempre, haya ticket o no: si no hay nada que reimprimir, el
      // dialogo del navegador abria igual e imprimiria la pagina entera.
      e.preventDefault();
      if (!estado.ultimaVenta) { aviso("No hay ningun ticket abierto para reimprimir."); return; }
      e.preventDefault();
      mostrarVenta(estado.ultimaVenta, false);
      imprimirTicket(estado.ultimaVenta);
      return;
    }

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
