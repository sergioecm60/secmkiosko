"use strict";

/*   11. REPORTES
   ===================================================================== */
async function renderReportes() {
  const d = $("#r-desde").value || hoyISO();
  const h = $("#r-hasta").value || hoyISO();
  $("#r-stats").innerHTML = '<div class="stat"><div class="cap">Cargando…</div></div>';

  try {
    const r = await api("reportes", { desde: d, hasta: h });
    const s = r.resumen;

    $("#r-stats").innerHTML = `
      <div class="stat acento"><div class="cap">Ventas</div><div class="val">${numeroLocal(s.ventas, 0)}</div>
        <div class="sub">${d === h ? "hoy" : "en el periodo"}</div></div>
      <div class="stat"><div class="cap">Importe total</div><div class="val">${dinero(s.total)}</div>
        <div class="sub">${s.descuentos > 0 ? "descuentos: " + dinero(s.descuentos) : "sin descuentos"}</div></div>
      <div class="stat"><div class="cap">Ticket promedio</div><div class="val">${dinero(s.promedio)}</div>
        <div class="sub">mayor: ${dinero(s.mayor)}</div></div>
      <div class="stat"><div class="cap" style="color:var(--ok)">Ganancia bruta</div>
        <div class="val" style="color:var(--ok)">${dinero(s.utilidad)}</div>
        <div class="sub">costo de mercancía: ${dinero(s.costo)}</div>
        <div class="sub">margen ${numeroLocal(s.margen, 1)}%</div></div>
      <div class="stat"><div class="cap">Inventario a costo</div><div class="val">${dinero(s.costo_inventario)}</div>
        <div class="sub">a venta: ${dinero(s.inventario)}</div>
        <div class="sub">en anaquel: ${dinero(s.ganancia_potencial)}</div></div>
      <div class="stat"><div class="cap">Anuladas</div><div class="val" style="${s.anuladas ? "color:var(--bad)" : ""}">${numeroLocal(s.anuladas, 0)}</div>
        <div class="sub">${numeroLocal(s.unidades, 2)} unidades en almacén</div></div>`;

    // Gráfica por hora
    const horas = r.por_hora;
    const maxH = horas.length ? Math.max.apply(null, horas.map(h2 => h2.total)) : 0;
    let barras = "";
    for (let h2 = 0; h2 < 24; h2++) {
      const d2 = horas.find(x => x.h === h2);
      const val = d2 ? d2.total : 0;
      const alt = maxH > 0 ? Math.max(2, (val / maxH) * 100) : 0;
      barras += '<div class="b" title="' + (d2 ? numeroLocal(d2.ventas, 0) + " ventas · " + dinero(val) : "sin ventas") + '">'
        + (val > 0 ? "<span class='vl'>" + dinero(val, false) + "</span>" : "")
        + "<div class='barra' style='height:" + alt + "%'></div>"
        + "<span class='lb'>" + (h2 % 3 === 0 ? h2 + "h" : "") + "</span></div>";
    }
    $("#r-horas").innerHTML = barras;

    // Top productos
    $("#r-top").innerHTML = r.top.length ? r.top.map((t, i) => `
      <tr>
        <td class="fuente">${i < 3 ? ["🥇", "🥈", "🥉"][i] : (i + 1)}</td>
        <td>${esc(t.nombre)}</td>
        <td class="num">${numeroLocal(t.unidades, 2)}</td>
        <td class="num">${dinero(t.vendido)}
          <br><span class="fuente" style="font-size:11px">margen ${t.costo > 0 ? numeroLocal(t.margen, 1) + "%" : "—"}</span></td>
      </tr>`).join("")
      : '<tr><td colspan="4" style="padding:22px;text-align:center;color:var(--muted)">Sin ventas en el periodo</td></tr>';

    // Formas de pago
    $("#r-pago").innerHTML = r.por_pago.length ? r.por_pago.map(m => `
      <tr>
        <td>${esc(m.metodo)}</td>
        <td class="num">${numeroLocal(m.ventas, 0)}</td>
        <td class="num">${dinero(m.total)}</td>
        <td class="num fuente">${s.total > 0 ? Math.round(m.total / s.total * 100) : 0}%</td>
      </tr>`).join("")
      : '<tr><td colspan="4" style="padding:22px;text-align:center;color:var(--muted)">Sin datos</td></tr>';

    // Por reponer
    $("#r-faltantes").innerHTML = r.faltantes.length ? r.faltantes.map(f => {
      const sugerido = Math.max(f.minimo * 2 - f.stock, f.minimo > 0 ? 1 : 0, 1);
      return `<tr>
        <td>${esc(f.nombre)}</td>
<td class="num"><span class="etq ${f.stock <= 0 ? "mal" : "aviso"}">${esc(cantidadTxt(f.stock, f.unidad))}</span></td>
<td class="num fuente">${esc(cantidadTxt(f.minimo, f.unidad))}</td>
<td class="num">${esc(cantidadTxt(Math.round(sugerido * 100) / 100, f.unidad))}</td>
        <td class="num">${dinero(sugerido * f.precio)}</td>
      </tr>`;
    }).join("")
      : '<tr><td colspan="5" style="padding:22px;text-align:center;color:var(--ok)">✓ Todo el inventario está por encima del mínimo</td></tr>';

    // Botón lista de compra
    $("#btn-r-compra").onclick = () => {
      if (!r.faltantes.length) { aviso("No hay productos por reponer.", "ok"); return; }
      const filas = [["Producto", "Categoría", "Stock actual", "Mínimo", "Sugerido", "Unidad", "Costo estimado"]];
      r.faltantes.forEach(f => {
        const sug = Math.round(Math.max(f.minimo * 2 - f.stock, 1));
        filas.push([f.nombre, "", cantidadTxt(f.stock, f.unidad), cantidadTxt(f.minimo, f.unidad), sug, f.unidad || "", r2(sug * f.precio).toFixed(2)]);
      });
      descargar(aCSV(filas), "lista-de-compra-" + hoyISO() + ".csv");
      aviso("Lista de compra descargada.", "ok");
    };
  } catch (e) {
    $("#r-stats").innerHTML = '<div class="stat"><div class="cap">Error</div><div class="val" style="font-size:16px;color:var(--bad)">'
      + esc(e.message) + "</div></div>";
  }
}
