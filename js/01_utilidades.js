"use strict";

/*   1. UTILIDADES
   ===================================================================== */
const $  = (s, r) => (r || document).querySelector(s);
const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

/**
 * Escucha un elemento sólo si existe. Un id que falta (por ejemplo en un rol
 * que no ve esa pantalla) no debe dejar sin listeners a todo lo que viene
 * después, así que los enlaces de la UI nueva usan esto.
 */
function escuchar(sel, evento, fn) {
  const el = $(sel);
  if (el) el.addEventListener(evento, fn);
  return !!el;
}

const r2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

/**
 * Lee un importe escrito a la argentina: "1.234,56", "1234,56", "1234.56"
 * o "1234". Sirve para los campos donde el cajero teclea el efectivo contado.
 */
function parseNum(v) {
  if (typeof v === "number") return r2(v);
  let s = String(v == null ? "" : v).trim().replace(/[^\d,.\-]/g, "");
  if (!s) return 0;
  const ultimaComa = s.lastIndexOf(","), ultimoPunto = s.lastIndexOf(".");
  if (ultimaComa > ultimoPunto) {
    // La coma es el decimal: los puntos son separadores de miles.
    s = s.replace(/\./g, "").replace(",", ".");
  } else if (ultimaComa !== -1 && ultimoPunto === -1) {
    s = s.replace(",", ".");
  } else {
    s = s.replace(/,/g, "");
  }
  return r2(parseFloat(s) || 0);
}

/** Sólo la hora, para "abrió a las 14:05". */
function horaDe(iso) {
  if (!iso) return "—";
  return new Date(String(iso).replace(" ", "T"))
    .toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" });
}

const esc = (s) => String(s == null ? "" : s)
  .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
  .replace(/"/g, "&quot;").replace(/'/g, "&#39;");

/** Quita acentos y pasa a minúsculas para comparar sin sorpresas. */
const norm = (s) => String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

/**
 * Ultimo digito de un EAN-13: el que tiene que completar la decena.
 * Pesar los 12 primeros de 1,3,1,3... es el criterio de GS1.
 */
function digitoVerificador(codigo) {
  const c = String(codigo || "");
  if (!/^\d{13}$/.test(c)) return "";
  let s = 0;
  for (let i = 0; i < 12; i++) s += Number(c[i]) * (i % 2 === 0 ? 1 : 3);
  return String((10 - s % 10) % 10);
}

/**
 * Un EAN-13 de 13 digitos tiene que cerrar con su digito verificador.
 * Los codigos internos con letras o de otra longitud se dejan pasar.
 * El servidor aplica el mismo control (codigoAceptable en inc/config.php):
 * esto es solo para avisar antes de mandar.
 */
function codigoAceptable(codigo) {
  const c = String(codigo || "").trim();
  if (c === "") return true;
  if (!/^\d{13}$/.test(c)) return true;
  return c[12] === digitoVerificador(c);
}

/**
 * Imprime SOLO el ticket indicado.
 * Hay tres contenedores (#ticket, #ticket-caja, #ticket-comanda) y los tres
 * tienen su propio bloque @media print. Si se dejara activo el de los demas,
 * un ticket viejo quedaria impreso encima del nuevo, asi que se marca en
 * <body data-imprimir="..."> y el CSS muestra unicamente ese.
 * El ancho de pagina lo define la impresora: en una termica de 80mm el ticket
 * sale a lo ancho y en una A4/Carta normal queda centrado con las mismas
 * proporciones.
 */
function imprimirTicketAhora(clave) {
  document.body.dataset.imprimir = clave;
  const limpiar = () => {
    delete document.body.dataset.imprimir;
    window.removeEventListener("afterprint", limpiar);
  };
  window.addEventListener("afterprint", limpiar);
  // afterprint no dispara en todos los navegadores: se limpia al volver a la app
  if (!("onafterprint" in window)) setTimeout(limpiar, 1500);
  setTimeout(() => window.print(), 60);
}

function dinero(n, simbolo) {
  const v = r2(n);
  const txt = v.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (simbolo === false) return txt;
  return (estado.config.moneda || "$") + txt;
}

function hoyISO() {
  const d = new Date();
  return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
}

function diasAtras(n) {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
}

const dosDig = (n) => String(n).padStart(2, "0");

/**
 * Formato de fecha y hora de negocio: dd/mm/aaaa hh:mm en 24 horas.
 * Se arma a mano en vez de usar toLocaleString porque el locale es-AR
 * devuelve "25/09/26, 05:15 p. m." (12 horas y año de 2 cifras), que en
 * Argentina no se usa.
 */
function fechaHora(iso) {
  const d = new Date(String(iso).replace(" ", "T"));
  if (isNaN(d)) return "—";
  return dosDig(d.getDate()) + "/" + dosDig(d.getMonth() + 1) + "/" + d.getFullYear()
    + " " + dosDig(d.getHours()) + ":" + dosDig(d.getMinutes());
}

/** Solo la fecha: dd/mm/aaaa */
function fechaCorta(iso) {
  const d = new Date(String(iso).replace(" ", "T"));
  if (isNaN(d)) return "—";
  return dosDig(d.getDate()) + "/" + dosDig(d.getMonth() + 1) + "/" + d.getFullYear();
}

function numeroLocal(n, dec) {
  const v = Number(n) || 0;
  return v.toLocaleString("es-AR", {
    minimumFractionDigits: dec == null ? 0 : dec,
    maximumFractionDigits: dec == null ? 0 : dec
  });
}

const FRACCIONES = { 0.25: "¼", 0.5: "½", 0.75: "¾" };

function esPeso(u) {
  const s = String(u || "").trim().toLowerCase();
  return s === "kg" || s === "g" || s === "gramo" || s === "kilo";
}

function esVolumen(u) {
  const s = String(u || "").trim().toLowerCase();
  return s === "litro" || s === "l" || s === "lt" || s === "ml";
}

/**
 * Muestra una cantidad de la forma mas natural para el cliente.
 *   0.25 kg   -> "250 g"      0.5 litro -> "500 ml"
 *   2 kg      -> "2 kg"       2 litro   -> "2 L"
 *   1 unidad  -> "1"          0         -> "0"
 */
function cantidadTxt(n, unidad) {
  const v = Number(n) || 0;
  const u = String(unidad || "pieza").trim();
  const abs = Math.abs(v);
  const peso = esPeso(u), vol = esVolumen(u);

  if (peso || vol) {
    const chico = redondearTxt(abs * 1000);
    if (chico > 0 && enteroTxt(chico)) {
      if (abs >= 1) return numCorto(abs) + (peso ? " kg" : " L");
      return numeroLocal(chico, 0) + (peso ? " g" : " ml");
    }
  }
  return numCorto(v);
}

function redondearTxt(n) { return Math.round(n * 1000) / 1000; }
function enteroTxt(n) { return Math.abs(n - Math.round(n)) < 0.001; }

/** Entero sin decimales, fractionado con 2 y sin ceros de sobra: 4,5 / 0,25. */
function numCorto(v) {
  if (!Number.isInteger(v)) {
    return numeroLocal(v, 2).replace(/,00$/, "").replace(/,(\d)0$/, ",$1");
  }
  return numeroLocal(v, 0);
}

function aviso(msg, tipo) {
  const d = document.createElement("div");
  d.className = "aviso" + (tipo ? " " + tipo : "");
  d.textContent = msg;
  $("#avisos").appendChild(d);
  setTimeout(() => {
    d.style.transition = "opacity 250ms";
    d.style.opacity = "0";
    setTimeout(() => d.remove(), 260);
  }, tipo === "mal" ? 4500 : 2300);
}

/** Descarga texto como archivo (con BOM para que Excel respete acentos). */
function descargar(texto, nombre, tipo) {
  const blob = new Blob(["﻿" + texto], { type: (tipo || "text/csv") + ";charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url; a.download = nombre;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1500);
}

/** Convierte una matriz en CSV compatible con Excel en español. */
function aCSV(filas) {
  return filas.map(f => f.map(c => {
    const v = c == null ? "" : String(c);
    return /[";\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
  }).join(";")).join("\r\n");
}
