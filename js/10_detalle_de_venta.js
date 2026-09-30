"use strict";

/*   10. DETALLE DE VENTA / TICKET
   ===================================================================== */
function mostrarVenta(v, recienHecha) {
  estado.ultimaVenta = v;
  $("#mv-titulo").textContent = (recienHecha ? "✅ Venta registrada · #" : "Venta #") + v.folio;

  const filas = (v.items || []).map(i => `
    <tr>
      <td>${esc(i.nombre)}</td>
      <td class="num fuente">${esc(cantidadTxt(i.cantidad, i.unidad))} × ${dinero(i.precio)}</td>
      <td class="num">${dinero(i.importe)}</td>
    </tr>`).join("");

  let extra = "";
  if (v.metodo === "Efectivo" && v.recibido != null) {
    extra = '<div class="aviso-linea" style="margin-top:12px"><span>Efectivo recibido</span><strong>'
      + dinero(v.recibido) + "</strong></div>"
      + '<div class="aviso-linea" style="margin-top:6px; background:var(--okbg); color:var(--ok)">'
      + "<span>Vuelto</span><strong>" + dinero(v.vuelto) + "</strong></div>";
  }
  if (v.descuento > 0) {
    extra = '<div class="aviso-linea" style="margin-top:12px"><span>Descuento aplicado</span><strong style="color:var(--ok)">−'
      + dinero(v.descuento) + "</strong></div>" + extra;
  }
  if (v.referencia) {
    extra += '<div class="aviso-linea" style="margin-top:6px"><span>Referencia</span><strong>' + esc(v.referencia) + "</strong></div>";
  }
  if (v.anulada) {
    extra += '<div class="aviso-linea" style="margin-top:6px;background:var(--badbg);color:var(--bad)">'
      + "<span>⚠ Esta venta fue anulada</span></div>";
  }

  $("#mv-cue").innerHTML = `
    <div class="rejilla" style="margin-bottom:14px">
      <div class="campo"><label>Folio</label><strong style="font-size:17px">#${v.folio}</strong></div>
      <div class="campo"><label>Fecha</label><strong>${fechaHora(v.fecha)}</strong></div>
      <div class="campo"><label>Método de pago</label><strong>${esc(v.metodo)}</strong></div>
    </div>
    <div class="envoltura"><table class="tabla tabla-detalle">
      <thead><tr><th>Artículo</th><th class="num">Cant. × precio</th><th class="num">Importe</th></tr></thead>
      <tbody>${filas}</tbody>
    </table></div>
    <div class="aviso-linea" style="margin-top:12px; background:var(--panel2)">
      <span>Subtotal ${v.descuento > 0 ? "− descuento " + dinero(v.descuento) : ""}</span>
      <strong style="font-size:19px">${dinero(v.total)}</strong>
    </div>
    ${extra}`;

  $("#mv-imprimir").onclick = () => imprimirTicket(v);
  abrirModal("#m-venta");
}

function imprimirTicket(v) {
  const c = estado.config;
  const lineas = (v.items || []).map(i =>
    "<tr><td colspan='2'>" + esc(i.nombre) + "</td></tr>"
    + "<tr><td>&nbsp;&nbsp;" + esc(cantidadTxt(i.cantidad, i.unidad)) + " x " + dinero(i.precio, false) + "</td>"
    + "<td class='d'>" + dinero(i.importe, false) + "</td></tr>"
  ).join("");

  let pago = "";
  if (v.metodo === "Efectivo" && v.recibido != null) {
    pago = "<div class='t-l' style='margin-top:5px'>"
      + "<tr><td>Recibido</td><td class='d'>" + dinero(v.recibido, false) + "</td></tr>"
      + "<tr><td>VUELTO</td><td class='d'>" + dinero(v.vuelto, false) + "</td></tr></div>";
  } else {
    pago = "<div class='t-l' style='margin-top:5px'><tr><td>Pago</td><td class='d'>" + esc(v.metodo) + "</td></tr></div>";
  }

  $("#ticket").innerHTML = `
    <div class="t-cab">
      <div class="t-neg">${esc(c.negocio || "Kiosco")}</div>
      ${c.direccion ? "<div class='t-sub'>" + esc(c.direccion) + "</div>" : ""}
      ${c.telefono ? "<div class='t-sub'>Tel. " + esc(c.telefono) + "</div>" : ""}
    </div>
    <div class="t-l">
      <tr><td>Folio</td><td class="d">#${v.folio}</td></tr>
      <tr><td>Fecha</td><td class="d">${fechaHora(v.fecha)}</td></tr>
      ${v.cliente ? "<tr><td>Cliente</td><td class='d'>" + esc(v.cliente) + "</td></tr>" : ""}
    </div>
    <div class="t-l" style="margin-top:6px">${lineas}</div>
    <div class="t-tot t-l">
      <tr><td>TOTAL</td><td class="d">${dinero(v.total, false)}</td></tr>
    </div>
    ${pago}
    ${v.descuento > 0 ? "<div class='t-l'><tr><td>Descuento</td><td class='d'>-" + dinero(v.descuento, false) + "</td></tr></div>" : ""}
    <div class="t-pie">
      ${esc(c.pie || "")}
      <div style="margin-top:5px">*** Gracias ***</div>
    </div>`;

  imprimirTicketAhora("venta");
}
