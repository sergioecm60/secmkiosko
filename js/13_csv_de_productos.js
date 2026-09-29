"use strict";

/*   13. CSV DE PRODUCTOS
   ===================================================================== */
function exportarProductos() {
  if (!estado.productos.length) { aviso("No hay productos que exportar.", "aviso-w"); return; }
  const filas = [["Nombre", "Codigo", "Categoria", "Precio", "Stock", "Minimo", "Unidad"]];
  estado.productos.forEach(p => filas.push([
    p.nombre, p.codigo || "", p.categoria || "",
    p.precio.toFixed(2), numeroLocal(p.stock, 2), numeroLocal(p.minimo, 2), p.unidad || "pieza"
  ]));
  descargar(aCSV(filas), "productos-" + hoyISO() + ".csv");
  aviso("Archivo con " + estado.productos.length + " producto(s) descargado.", "ok");
}

/** Divide una línea CSV respetando comillas. */
function partirCSV(linea) {
  const out = [];
  let cur = "", enComilla = false;
  for (let i = 0; i < linea.length; i++) {
    const ch = linea[i];
    if (ch === '"') {
      if (enComilla && linea[i + 1] === '"') { cur += '"'; i++; }
      else enComilla = !enComilla;
    } else if ((ch === ";" || ch === ",") && !enComilla) {
      out.push(cur); cur = "";
    } else if (ch !== "\r") {
      cur += ch;
    }
  }
  out.push(cur);
  return out.map(s => s.trim());
}

async function importarCSV(archivo) {
  const texto = await archivo.text();
  const lineas = texto.replace(/^﻿/, "").split(/\r?\n/).filter(l => l.trim());
  if (lineas.length < 2) { aviso("El archivo no tiene datos.", "mal"); return; }

  // Detecta el separador probando con ;
  const cabecera = partirCSV(lineas[0]);
  const hayCabecera = /nombre/i.test(cabecera[0]) && !/^\d/.test(cabecera[1] || "");
  const cuerpo = hayCabecera ? lineas.slice(1) : lineas;

  let creados = 0, actualizados = 0, fallidos = 0;
  for (const linea of cuerpo) {
    const c = partirCSV(linea);
    if (c.length < 3) { fallidos++; continue; }
    const nombre = c[0];
    if (!nombre) { fallidos++; continue; }
    try {
      const codigo = c[1] || "";
      const existente = codigo ? estado.productos.find(p => norm(p.codigo) === norm(codigo)) : null;
      const datos = {
        id: existente ? existente.id : 0,
        nombre: nombre,
        codigo: codigo,
        categoria: c[2] || "",
        precio: Number(String(c[3] || "0").replace(/[^\d.-]/g, "")) || 0,
        stock: Number(String(c[4] || "0").replace(/[^\d.-]/g, "")) || 0,
        minimo: Number(String(c[5] || "0").replace(/[^\d.-]/g, "")) || 0,
        unidad: c[6] || "pieza",
        activo: 1
      };
      await api("producto_guardar", datos);
      if (existente) actualizados++; else creados++;
      // Refleja el cambio en memoria para no duplicar códigos dentro del mismo archivo
      if (existente) existente.precio = datos.precio;
    } catch (e) { fallidos++; }
  }

  await cargarProductos();
  renderPOS();
  if (estado.vista === "productos") renderProductos();
  aviso("Importación: " + creados + " nuevos, " + actualizados + " actualizados" + (fallidos ? ", " + fallidos + " con error" : "") + ".",
    fallidos ? "aviso-w" : "ok");
}
