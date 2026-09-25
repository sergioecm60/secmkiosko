<?php
/**
 * Kiosco — interfaz del punto de venta.
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

if (!instalado()) {
    header('Location: instalar.php');
    exit;
}
$cfg = leerConfig();
?><!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<title><?= htmlspecialchars($cfg['negocio'], ENT_QUOTES, 'UTF-8') ?> — Kiosco</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128722;</text></svg>">
<link rel="stylesheet" href="estilos.css?v=<?= @filemtime(__DIR__ . '/estilos.css') ?: '1' ?>">
</head>
<body class="<?= ($cfg['tema'] ?? 'claro') === 'ocuro' ? 'ocuro' : '' ?>">

<header class="topbar">
  <div class="brand">
    <div class="logo" id="lbl-logo"><?= htmlspecialchars(mb_substr($cfg['logo'] ?: 'K', 0, 2), ENT_QUOTES, 'UTF-8') ?></div>
    <div>
      <div class="negocio" id="lbl-negocio"><?= htmlspecialchars($cfg['negocio'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="sub" id="lbl-hoy">—</div>
    </div>
  </div>
  <nav class="tabs" id="tabs">
    <button data-v="vender" class="on">Vender</button>
    <button data-v="productos">Productos</button>
    <button data-v="historial">Historial</button>
    <button data-v="reportes">Reportes</button>
    <button data-v="ajustes">Ajustes</button>
  </nav>
  <div class="caja-dia">
    <div class="cap">Ventas de hoy</div>
    <div class="val" id="lbl-hoy-total">$0.00</div>
  </div>
  <button class="icono" id="btn-tema" title="Cambiar tema claro / oscuro">🌗</button>
</header>

<main>
  <!-- ================= VENDER ================= -->
  <section class="vista on pad0" id="v-vender">
    <div class="pos">
      <div class="pos-izq">
        <div class="buscador">
          <span class="lupa">🔍</span>
          <input id="txt-buscar" placeholder="Escanea el código o escribe el nombre del producto…" autocomplete="off" spellcheck="false">
          <button class="x" id="btn-limpiar-busca" title="Limpiar búsqueda (Esc)">✕</button>
        </div>
        <div class="chips" id="filtros"></div>
        <div class="grid" id="grid-productos"></div>
      </div>
      <aside class="pos-der">
        <div class="carrito-cab">
          <h2>🧾 Ticket <span class="pill" id="c-count">0</span></h2>
          <button class="mini peligro" id="btn-vaciar">Vaciar</button>
        </div>
        <div class="carrito-items" id="carrito-items"></div>
        <div class="carrito-pie">
          <div class="fila"><span>Subtotal</span><b id="c-sub">$0.00</b></div>
          <div class="fila" id="fila-desc" style="display:none">
            <span>Descuento <span class="lapiz" title="Cambiar descuento">✎</span></span>
            <b id="c-desc">-$0.00</b>
          </div>
          <div class="fila total"><span>Total</span><b id="c-total">$0.00</b></div>
          <button class="btn-cobrar" id="btn-cobrar" disabled>Cobrar</button>
        </div>
      </aside>
    </div>
  </section>

  <!-- ================= PRODUCTOS ================= -->
  <section class="vista" id="v-productos">
    <div class="card">
      <div class="card-cab">
        <h2>📦 Productos <span class="pill" id="p-count">0</span></h2>
        <div class="filetools">
          <div class="buscador" style="min-width:220px">
            <span class="lupa">🔍</span>
            <input id="txt-buscar-p" placeholder="Buscar producto…" autocomplete="off" spellcheck="false">
          </div>
          <button class="btn sm" id="btn-p-desc">⬇ Importar CSV</button>
          <button class="btn sm" id="btn-p-csv">⬆ Exportar CSV</button>
          <button class="btn sm pri" id="btn-p-nuevo">+ Nuevo producto</button>
        </div>
      </div>
      <div class="envoltura">
        <div style="max-height:calc(100vh - 190px); overflow:auto">
          <table class="tabla">
            <thead>
              <tr>
                <th style="width:58px"></th>
                <th>Producto</th>
                <th style="width:130px">Código</th>
                <th style="width:120px">Categoría</th>
                <th class="num" style="width:100px">Precio</th>
                <th class="num" style="width:95px">Stock</th>
                <th class="num" style="width:90px">Mínimo</th>
                <th style="width:110px">Estado</th>
                <th class="acciones" style="width:180px">Acciones</th>
              </tr>
            </thead>
            <tbody id="p-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>
    <input type="file" id="file-csv" accept=".csv,text/csv" style="display:none">
  </section>

  <!-- ================= HISTORIAL ================= -->
  <section class="vista" id="v-historial">
    <div class="card">
      <div class="card-cab">
        <h2>🧾 Historial de ventas <span class="pill" id="h-count">0</span></h2>
        <div class="filetools">
          <input type="date" id="h-desde" class="inline-date">
          <span style="color:var(--muted)">→</span>
          <input type="date" id="h-hasta" class="inline-date">
          <button class="btn sm" id="btn-h-hoy">Hoy</button>
          <button class="btn sm" id="btn-h-mes">Este mes</button>
          <button class="btn sm" id="btn-h-csv">⬆ Exportar</button>
        </div>
      </div>
      <div class="envoltura">
        <div style="max-height:calc(100vh - 190px); overflow:auto">
          <table class="tabla">
            <thead>
              <tr>
                <th style="width:90px">Folio</th>
                <th style="width:150px">Fecha</th>
                <th>Artículos</th>
                <th style="width:110px">Pago</th>
                <th class="num" style="width:110px">Total</th>
                <th class="acciones" style="width:170px">Acciones</th>
              </tr>
            </thead>
            <tbody id="h-tbody"></tbody>
          </table>
        </div>
      </div>
      <div class="card-cue" style="border-top:1px solid var(--line)">
        <div class="fila"><span>Total del periodo (ventas válidas)</span><b id="h-total">$0.00</b></div>
      </div>
    </div>
  </section>

  <!-- ================= REPORTES ================= -->
  <section class="vista" id="v-reportes">
    <div class="card" style="margin-bottom:16px">
      <div class="card-cab">
        <h2>📊 Reporte de ventas</h2>
        <div class="filetools">
          <input type="date" id="r-desde" class="inline-date">
          <span style="color:var(--muted)">→</span>
          <input type="date" id="r-hasta" class="inline-date">
          <button class="btn sm" id="btn-r-hoy">Hoy</button>
          <button class="btn sm" id="btn-r-7">7 días</button>
          <button class="btn sm" id="btn-r-30">30 días</button>
          <button class="btn sm" id="btn-r-mes">Mes actual</button>
        </div>
      </div>
    </div>

    <div class="stats" id="r-stats"></div>

    <div class="rejilla2">
      <div class="card">
        <div class="card-cab"><h2>⏰ Ventas por hora</h2></div>
        <div class="card-cue"><div class="barras" id="r-horas"></div></div>
      </div>
      <div class="card">
        <div class="card-cab"><h2>💳 Por forma de pago</h2></div>
        <div class="envoltura" style="box-shadow:none;border:0;border-radius:0">
          <table class="tabla">
            <thead><tr><th>Método</th><th class="num" style="width:80px">Ventas</th><th class="num" style="width:130px">Importe</th><th class="num" style="width:80px">%</th></tr></thead>
            <tbody id="r-pago"></tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-cab"><h2>🏆 Productos más vendidos</h2></div>
        <div class="envoltura" style="box-shadow:none;border:0;border-radius:0; max-height:300px; overflow:auto">
          <table class="tabla">
            <thead><tr><th style="width:44px"></th><th>Producto</th><th class="num" style="width:80px">Uds.</th><th class="num" style="width:120px">Vendido</th></tr></thead>
            <tbody id="r-top"></tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-cab">
          <h2>⚠️ Productos por reponer</h2>
          <button class="btn sm" id="btn-r-compra">Generar lista de compra</button>
        </div>
        <div class="envoltura" style="box-shadow:none;border:0;border-radius:0; max-height:300px; overflow:auto">
          <table class="tabla">
            <thead><tr><th>Producto</th><th class="num" style="width:80px">Stock</th><th class="num" style="width:80px">Mín.</th><th class="num" style="width:90px">Sugerido</th><th class="num" style="width:110px">Costo</th></tr></thead>
            <tbody id="r-faltantes"></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= AJUSTES ================= -->
  <section class="vista" id="v-ajustes">
    <div class="rejilla2">
      <div class="card">
        <div class="card-cab"><h2>🏪 Datos del negocio</h2></div>
        <div class="card-cue">
          <div class="rejilla">
            <div class="campo"><label>Nombre</label><input id="c-negocio" maxlength="40"></div>
            <div class="campo"><label>Símbolo de moneda</label><input id="c-moneda" maxlength="4"></div>
            <div class="campo"><label>Dirección</label><input id="c-direccion" maxlength="60"></div>
            <div class="campo"><label>Teléfono</label><input id="c-telefono" maxlength="30"></div>
          </div>
          <div class="campo" style="margin-top:13px"><label>Pie del ticket</label><input id="c-pie" maxlength="80"></div>
          <div class="rejilla" style="margin-top:13px">
            <div class="campo"><label>Iniciales del logo</label><input id="c-logo" maxlength="2" style="text-transform:uppercase"></div>
            <div class="campo"><label>Siguiente folio</label><input id="c-folio" type="number" min="1"></div>
          </div>
          <button class="btn pri" id="btn-c-guardar" style="margin-top:15px">💾 Guardar cambios</button>
        </div>
      </div>

      <div class="card">
        <div class="card-cab"><h2>💾 Respaldo de datos</h2></div>
        <div class="card-cue">
          <p class="parrafo">
            Tus datos viven en la base de datos MySQL de esta computadora. Descarga un respaldo
            <strong>cada día</strong> y guárdalo en un USB o en la nube: con ese archivo se
            reconstruye el sistema completo en otra máquina.
          </p>
          <div style="display:flex; gap:9px; flex-wrap:wrap">
            <a class="btn ok" href="respaldo.php?accion=descargar">⬇ Descargar respaldo (.sql)</a>
            <a class="btn" href="respaldo.php?accion=csv">⬆ Exportar productos (CSV)</a>
            <a class="btn" href="respaldo.php?accion=ventas_csv">⬆ Exportar ventas (CSV)</a>
            <a class="btn" href="respaldo.php">📂 Restaurar / phpMyAdmin</a>
          </div>
          <p class="parrafo" style="margin-top:14px; margin-bottom:0">
            💡 Además, en <strong>Laragon</strong> activá el respaldo automático:
            <em>Menú → MySQL → Backup automático</em>. Eso te salva sin que tengas que acordarte.
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-cab"><h2>📈 Estado del sistema</h2></div>
        <div class="card-cue" id="a-estado">Cargando…</div>
      </div>

      <div class="card">
        <div class="card-cab"><h2>📦 Entradas y salidas de mercancía</h2></div>
        <div class="card-cue">
          <p class="parrafo">Registra compras, mermas o conteos físicos. Cada cambio queda anotado en el kardex del producto.</p>
          <div style="display:flex; gap:9px; flex-wrap:wrap">
            <button class="btn" id="btn-rapida-entrada">📥 Registrar entrada</button>
            <button class="btn" id="btn-rapida-salida">📤 Registrar salida</button>
          </div>
        </div>
      </div>

      <div class="card" style="border-color:var(--bad)">
        <div class="card-cab" style="border-color:var(--bad)"><h2 style="color:var(--bad)">⚠️ Zona de peligro</h2></div>
        <div class="card-cue">
          <p class="parrafo" style="margin-top:0">Antes de borrar se descarga un respaldo automático a la carpeta <code>datos/</code>.</p>
          <div style="display:flex; gap:9px; flex-wrap:wrap">
            <button class="btn peligro" data-limpiar="ventas">🗑 Borrar historial de ventas</button>
            <button class="btn peligro" data-limpiar="productos">🗑 Borrar catálogo de productos</button>
            <button class="btn peligro" data-limpiar="kardex">🗑 Borrar kardex</button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-cab"><h2>💡 Atajos de teclado</h2></div>
        <div class="card-cue">
          <ul class="atajos">
            <li><kbd>F2</kbd> Ir a la búsqueda / enfocar para escanear</li>
            <li><kbd>Enter</kbd> Agregar el producto buscado al ticket</li>
            <li><kbd>F4</kbd> Cobrar la venta actual</li>
            <li><kbd>Esc</kbd> Cerrar ventana / limpiar búsqueda</li>
            <li><kbd>Ctrl</kbd>+<kbd>P</kbd> Imprimir el ticket abierto</li>
            <li><kbd>Esc</kbd> en la pantalla de cobro = anular / vaciar</li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- ================= MODAL COBRO ================= -->
<div class="velo" id="m-cobro">
  <div class="modal">
    <div class="modal-cab"><h2>💳 Cobrar</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <div class="cobrar-total">
        <div class="cap">Total a pagar</div>
        <div class="val" id="cob-total">$0.00</div>
      </div>
      <div class="metodos" id="cob-metodos">
        <button class="metodo on" data-m="Efectivo"><span class="ic">💵</span>Efectivo</button>
        <button class="metodo" data-m="Tarjeta"><span class="ic">💳</span>Tarjeta</button>
        <button class="metodo" data-m="Transferencia"><span class="ic">📱</span>Transfer.</button>
      </div>
      <div id="cob-efectivo">
        <label class="rotulo" id="cob-rotulo">Efectivo recibido</label>
        <div class="billetes" id="cob-billetes"></div>
        <div class="teclado" id="cob-teclado"></div>
        <div class="vuelto" id="cob-vuelto"><span>Entregado exacto</span><b>$0.00</b></div>
      </div>
      <div id="cob-otro" style="display:none">
        <div class="campo"><label>Referencia (opcional)</label><input id="cob-ref" placeholder="Últimos 4 dígitos, autorización, etc."></div>
        <p class="parrafo" style="margin-top:12px; margin-bottom:0">
          Se registra la venta por el total exacto. El sistema no maneja el dinero del cajero:
          eso lo llevas en tu cuaderno o corte de caja.
        </p>
      </div>
    </div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn ok" id="cob-confirmar" style="height:48px;padding:0 28px;font-size:16px">✓ Confirmar venta</button>
    </div>
  </div>
</div>

<!-- ================= MODAL PRODUCTO ================= -->
<div class="velo" id="m-prod">
  <div class="modal ancho">
    <div class="modal-cab"><h2 id="mp-titulo">Nuevo producto</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <div class="con-foto">
        <div class="col-foto">
          <div class="avatar grande" id="mp-foto">📦</div>
          <button class="btn sm" id="mp-btn-foto">Cambiar foto</button>
          <button class="btn sm peligro" id="mp-btn-sinfoto">Quitar</button>
          <input type="file" id="mp-file" accept="image/*" style="display:none">
        </div>
        <div class="rejilla" style="flex:1;min-width:0;align-content:start">
          <div class="campo" style="grid-column:1/-1"><label>Nombre *</label><input id="mp-nombre" maxlength="60"></div>
          <div class="campo"><label>Código / código de barras</label><input id="mp-codigo" maxlength="30" spellcheck="false"></div>
          <div class="campo"><label>Categoría</label><input id="mp-categoria" maxlength="25" list="lista-cat"></div>
          <datalist id="lista-cat"></datalist>
          <div class="campo"><label>Precio de venta *</label><input id="mp-precio" type="number" step="0.01" min="0"></div>
          <div class="campo"><label>Stock actual</label><input id="mp-stock" type="number" step="0.01"></div>
          <div class="campo"><label>Stock mínimo (alerta)</label><input id="mp-minimo" type="number" step="0.01" min="0"></div>
          <div class="campo"><label>Unidad</label>
            <select id="mp-unidad">
              <option>pieza</option><option>paquete</option><option>lata</option>
              <option>botella</option><option>caja</option><option>kg</option><option>litro</option>
            </select>
          </div>
        </div>
      </div>
      <div id="mp-kardex" style="display:none; margin-top:18px">
        <h3 class="subt">Últimos movimientos</h3>
        <div class="envoltura" style="box-shadow:none; margin-top:8px; max-height:180px; overflow:auto">
          <table class="tabla">
            <thead><tr><th style="width:130px">Fecha</th><th style="width:90px">Tipo</th><th class="num">Cant.</th><th class="num">Stock</th><th>Referencia</th></tr></thead>
            <tbody id="mp-kardex-tb"></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="modal-pie">
      <button class="btn peligro" id="mp-borrar" style="display:none">Eliminar</button>
      <button class="btn" data-cerrar style="margin-left:auto">Cancelar</button>
      <button class="btn pri" id="mp-guardar">Guardar producto</button>
    </div>
  </div>
</div>

<!-- ================= MODAL STOCK ================= -->
<div class="velo" id="m-stock">
  <div class="modal">
    <div class="modal-cab"><h2 id="ms-titulo">Movimiento de mercancía</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <div class="campo" style="margin-bottom:13px"><label>Producto</label><input id="ms-buscar" placeholder="Escribe o escanea el nombre / código…" autocomplete="off" spellcheck="false"></div>
      <div class="lista-suger" id="ms-lista"></div>
      <div id="ms-form" style="display:none; margin-top:15px">
        <div class="aviso-linea" id="ms-info">—</div>
        <div class="rejilla" style="margin-top:13px">
          <div class="campo"><label>Cantidad</label><input id="ms-cant" type="number" step="0.01" min="0"></div>
          <div class="campo"><label>Motivo</label><input id="ms-motivo" maxlength="60" placeholder="Compra, merma, conteo…"></div>
        </div>
      </div>
    </div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn pri" id="ms-guardar" disabled>Registrar movimiento</button>
    </div>
  </div>
</div>

<!-- ================= MODAL VENTA ================= -->
<div class="velo" id="m-venta">
  <div class="modal">
    <div class="modal-cab"><h2 id="mv-titulo">Venta</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue" id="mv-cue"></div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cerrar</button>
      <button class="btn" id="mv-imprimir">🖨 Imprimir ticket</button>
    </div>
  </div>
</div>

<!-- ================= MODAL ENTRADA RAPIDA ================= -->
<div class="velo" id="m-rapida">
  <div class="modal" style="max-width:400px">
    <div class="modal-cab"><h2 id="mr-titulo">—</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <div class="campo"><label id="mr-rotulo">Valor</label><input id="mr-valor" autocomplete="off"></div>
      <p class="parrafo" id="mr-ayuda" style="margin:12px 0 0"></p>
    </div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn pri" id="mr-ok">Aceptar</button>
    </div>
  </div>
</div>

<!-- ================= MODAL CONFIRMAR ================= -->
<div class="velo" id="m-confirmar">
  <div class="modal" style="max-width:430px">
    <div class="modal-cab"><h2 id="mc-titulo">¿Confirmar?</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue"><p class="parrafo" style="margin:0" id="mc-texto"></p></div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn peligro" id="mc-ok">Sí, continuar</button>
    </div>
  </div>
</div>

<div id="avisos"></div>
<div id="ticket"></div>

<script src="app.js?v=<?= @filemtime(__DIR__ . '/app.js') ?: '1' ?>"></script>
</body>
</html>
