"use strict";

/*   12. AJUSTES
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
      ["Servidor web", (r.servidor || "desconocido") + " · PHP " + r.php_version],
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
