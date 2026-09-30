"use strict";

/*    16. USUARIOS
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

/* ---------- Ajustes: zonas ---------- */

async function cargarAjustesCocina() {
  if (!esAdmin()) return;
  /* Los atajos de comanda se fueron: lo que va a la cocina son productos del
     catalogo de la categoria "Comidas y Tragos", y se editan desde Productos.
     Ya no hace falta traerlos ni tener una tabla propia en Ajustes. */
  const z = await api("zonas").catch(() => ({ zonas: [] }));
  estado.zonas = z.zonas || [];
  renderAjustesZonas();
  renderAjustesCategorias();
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

// --- Categorias -------------------------------------------------------------
// La tabla de arriba. Muestra cuantos productos tiene cada una, porque no se
// puede borrar una que este en uso y conviene verlo antes de intentarlo.
let editCategoria = { id: 0, nombre: "", cocina: 0 };

function renderAjustesCategorias() {
  const tb = $("#a-cat-tb");
  if (!tb) return;
  if (!estado.categorias.length) {
    tb.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:var(--muted)">No hay categorías.</td></tr>';
    return;
  }
  const usados = estado.categoriasUsadas || {};
  tb.innerHTML = estado.categorias.map(c => {
    const n = usados[c.nombre] || 0;
    return `<tr>
      <td>${esc(c.nombre)}${c.nombre === "Venta libre" ? ' <span class="marca bajo">del sistema</span>' : ""}</td>
      <td>${c.cocina ? '<span class="marca ok">🍳 cocina</span>' : '<span style="color:var(--muted)">mostrador</span>'}</td>
      <td class="num">${n}</td>
      <td class="acciones">
        <button class="btn sm" data-edit-cat="${c.id}" title="Editar">✎</button>
        <button class="btn sm peligro" data-borrar-cat="${c.id}" title="Borrar">🗑</button>
      </td>
    </tr>`;
  }).join("");
}

async function cargarCategorias() {
  const r = await api("categorias");
  estado.categorias = r.categorias || [];
  const u = await api("categorias_usadas");
  estado.categoriasUsadas = u.usadas || {};
  renderAjustesCategorias();
  renderFiltros();
  pintarListaCategorias();
}

function abrirCategoria(id) {
  const c = id ? estado.categorias.find(x => x.id === id) : null;
  editCategoria = c ? { id: c.id, nombre: c.nombre, cocina: c.cocina || 0 } : { id: 0, nombre: "", cocina: 0 };
  $("#ct-titulo").textContent = editCategoria.id ? "Editar categoría" : "Nueva categoría";
  $("#ct-nombre").value = editCategoria.nombre;
  $("#ct-cocina").checked = !!editCategoria.cocina;
  abrirModal("#m-categoria");
  setTimeout(() => $("#ct-nombre").focus(), 120);
}

async function guardarCategoria() {
  const nombre = $("#ct-nombre").value.trim();
  if (!nombre) { aviso("La categoría necesita un nombre.", "mal"); return; }
  const datos = { id: editCategoria.id, nombre, cocina: $("#ct-cocina").checked ? 1 : 0 };
  try {
    await api("categoria_guardar", datos);
    cerrarModal("#m-categoria");
    await cargarCategorias();
    aviso("Categoría guardada.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}

async function borrarCategoria(id) {
  const c = estado.categorias.find(x => x.id === id);
  if (!c) return;
  const n = (estado.categoriasUsadas || {})[c.nombre] || 0;
  // Se avisa acá y no sólo en el servidor para no gastar un viaje, pero la
  // cuenta va primero: si hay productos, el borrado no va a pasar igual.
  if (n > 0) {
    aviso(`"${c.nombre}" tiene ${n} producto(s). Reubicá esos productos primero.`, "mal");
    return;
  }
  if (!await confirmar("Borrar la categoría", `"${c.nombre}" no tiene ningún producto. ¿Borrarla?`)) return;
  try {
    await api("categoria_borrar", { id });
    await cargarCategorias();
    aviso("Categoría borrada.", "ok");
  } catch (e) { aviso(e.message, "mal"); }
}

let editZona = { id: 0, nombre: "", costo: 0 };

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
