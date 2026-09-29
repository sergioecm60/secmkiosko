"use strict";

/*   14. MODALES
   ===================================================================== */
function abrirModal(sel) { $(sel).classList.add("on"); }
function cerrarModal(sel) { $(sel).classList.remove("on"); }

let alConfirmar = null;
function confirmar(titulo, texto) {
  return new Promise(resolve => {
    $("#mc-titulo").textContent = titulo;
    $("#mc-texto").textContent = texto;
    $("#m-confirmar").classList.add("on");
    alConfirmar = (si) => {
      $("#m-confirmar").classList.remove("on");
      alConfirmar = null;
      resolve(si);
    };
  });
}

/* Modal de captura rápida: texto, número o sí/no */
let alRapida = null;
function pedirDato(titulo, rotulo, ayuda, valorInicial, tipo) {
  return new Promise(resolve => {
    $("#mr-titulo").textContent = titulo;
    $("#mr-rotulo").textContent = rotulo;
    $("#mr-ayuda").textContent = ayuda || "";
    const inp = $("#mr-valor");
    inp.value = valorInicial != null ? valorInicial : "";
    inp.type = tipo || "text";
    inp.step = inp.type === "number" ? "0.01" : "";
    $("#m-rapida").classList.add("on");
    alRapida = (ok) => {
      const v = inp.value;
      $("#m-rapida").classList.remove("on");
      alRapida = null;
      resolve(ok ? v : null);
    };
    setTimeout(() => { inp.focus(); inp.select(); }, 120);
  });
}

const pedirTexto = (t, r, a, v) => pedirDato(t, r, a, v, "text");
const pedirClave = (t, r, a, v) => pedirDato(t, r, a, v, "password");
const pedirNumero = (t, r, a, v) => pedirDato(t, r, a, v, "number");

/** Devuelve true (sí), false (no) o null (cancelado). */
async function pedirSiNo(titulo, ayuda) {
  const r = await pedirDato(titulo, "Sí / No", ayuda, "si", "text");
  if (r === null) return null;
  return /^(si|sí|s|1|true|yes)$/i.test(String(r).trim());
}
