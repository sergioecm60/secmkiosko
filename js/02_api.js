"use strict";

/*   2. API
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
