<?php
/**
 * Kiosco — interfaz del punto de venta.
 */
declare(strict_types=1);
require __DIR__ . '/inc/sesion.php';

if (!instalado()) {
    header('Location: instalar.php');
    exit;
}

// Sin sesión no se entra al punto de venta.
$yo = usuarioActual();
if (!$yo) {
    header('Location: login.php');
    exit;
}
// Con la clave de fábrica todavía no se opera: primero hay que cambiarla.
if ((int) $yo['debe_cambiar_clave'] === 1) {
    header('Location: login.php');
    exit;
}

$cfg = leerConfig();
$soyAdmin = ($yo['rol'] === 'admin');
$soyCocina = ($yo['rol'] === 'cocina');
$miRol = $yo['rol'] ?: 'vendedor';
$rolTexto = ['admin' => 'Administrador', 'vendedor' => 'Vendedor', 'cocina' => 'Cocina'][$miRol] ?? 'Vendedor';
?><!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<title><?= htmlspecialchars($cfg['negocio'], ENT_QUOTES, 'UTF-8') ?> — Kiosco</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128722;</text></svg>">
<link rel="stylesheet" href="css/estilos.css?v=<?= @filemtime(__DIR__ . '/css/estilos.css') ?: '1' ?>">
</head>
<body class="<?= ($cfg['tema'] ?? 'claro') === 'ocuro' ? 'ocuro' : '' ?>"
      data-rol="<?= htmlspecialchars($miRol, ENT_QUOTES, 'UTF-8') ?>">

<header class="topbar">
  <div class="brand">
    <div class="logo" id="lbl-logo"><?= htmlspecialchars(mb_substr($cfg['logo'] ?: 'K', 0, 2), ENT_QUOTES, 'UTF-8') ?></div>
    <div>
      <div class="negocio" id="lbl-negocio"><?= htmlspecialchars($cfg['negocio'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="sub" id="lbl-hoy">—</div>
    </div>
  </div>
  <nav class="tabs" id="tabs">
    <button data-v="vender" class="on" title="Vender">Vender</button>
    <button data-v="productos" title="Productos">Productos</button>
    <button data-v="historial" title="Historial">Historial</button>
    <button data-v="cajas" title="<?= $soyAdmin ? 'Cajas' : 'Mi caja' ?>"><?= $soyAdmin ? 'Cajas' : 'Mi caja' ?></button>
    <button data-v="reportes" title="Reportes">Reportes</button>
    <button data-v="cocina" class="tab-cocina" title="Cocina">🍳 Cocina <span class="pill" id="coc-pend" hidden>0</span></button>
    <button data-v="ajustes" title="Ajustes">Ajustes</button>
  </nav>
  <div class="caja-dia">
    <div class="cap">Ventas de hoy</div>
    <div class="val" id="lbl-hoy-total">$0.00</div>
  </div>
  <button class="icono" id="btn-tema" title="Cambiar tema claro / oscuro">🌗</button>
  <div class="sesion-caja" id="mi-caja" title="Estado de tu caja">
    <span class="pt">Mi caja</span>
    <span class="pv" id="mi-caja-val">—</span>
  </div>
  <div class="menu-usuario">
    <button class="usuario-btn" id="btn-usuario" title="Tu cuenta">
      <span class="avatar" id="lbl-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($yo['nombre'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="udatos">
        <span class="unombre" id="lbl-usuario"><?= htmlspecialchars($yo['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="urole"><?= htmlspecialchars($rolTexto, ENT_QUOTES, 'UTF-8') ?></span>
      </span>
      <span class="flecha">▾</span>
    </button>
    <div class="menu-lista" id="menu-usuario-lista">
      <button data-ir="cajas">🏧 <span>Mi caja</span></button>
      <?php if ($soyAdmin): ?>
      <button data-ir="usuarios">👥 <span>Usuarios</span></button>
      <?php endif; ?>
      <button data-accion="clave">🔑 <span>Cambiar mi clave</span></button>
      <div class="menu-sep"></div>
      <a href="salir.php" class="peligro">🚪 <span>Cerrar sesión</span></a>
    </div>
  </div>
</header>

<?php if (!$soyAdmin): ?>
<div class="aviso-rol">
  Estás como <b>Vendedor</b>: podés abrir y cerrar tu caja y vender.
  Los productos, precios y el stock los carga el administrador.
</div>
<?php endif; ?>

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
        <div class="barra-rapida">
          <button class="btn sm" id="btn-rapido" title="Cargar al vuelo algo que no está en el catálogo. Se cobra con su precio y no descuenta stock.">+ Producto rápido</button>
          <span class="fuente">Venta espontánea: se cobra, no se controla stock</span>

          <!-- La ayuda de teclas va acá y no en Ajustes: el que la necesita es
               el cajero, y el cajero no entra nunca a Ajustes. Se despliega al
               pasar el mouse; el clic la deja abierta para las pantallas
               táctiles, donde no hay hover. -->
          <div class="ayuda-teclas" id="ayuda-teclas">
            <button class="btn sm lampara" id="btn-ayuda-teclas"
              aria-expanded="false" aria-controls="lista-teclas"
              title="Ver los atajos de teclado">💡</button>
            <div class="panel-teclas" id="lista-teclas" role="tooltip">
              <ul class="teclas-lista">
                <li><kbd>F2</kbd> Ir a la búsqueda y enfocar para escanear</li>
                <li><kbd>Enter</kbd> Agregar el producto buscado al ticket</li>
                <li><kbd>F4</kbd> Cobrar la venta actual</li>
                <li><kbd>Ctrl</kbd>+<kbd>P</kbd> Reimprimir el ticket abierto</li>
                <li><kbd>Esc</kbd> Cerrar ventana / limpiar búsqueda</li>
                <li><kbd>Esc</kbd> en el cobro = vaciar lo recibido</li>
                <li class="f"><kbd>🍳</kbd> En cada línea del ticket: mandarla a cocina</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="chips" id="filtros"></div>
        <div class="grid" id="grid-productos"></div>
      </div>
        <aside class="pos-der">
        <div class="carrito-cab">
          <h2>🧾 Ticket <span class="pill" id="c-count">0</span></h2>
          <div class="filetools">
            <button class="mini peligro" id="btn-vaciar">Vaciar</button>
          </div>
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
                <th style="width:130px">Categoría</th>
                <th class="num" style="width:100px">Precio</th>
                <th class="num" style="width:95px">Costo</th>
                <th class="num" style="width:80px">Margen</th>
                <th class="num" style="width:90px">Stock</th>
                <th class="num" style="width:80px">Mínimo</th>
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

  <!-- ================= CAJAS ================= -->
  <section class="vista" id="v-cajas">
    <div class="cajas-wrap">

      <!-- Estado de mi caja: abrir, ver y cerrar -->
      <div class="card" id="mi-caja-card">
        <div class="card-cab">
          <h2>🏧 Mi caja</h2>
          <span id="mi-caja-estado"></span>
        </div>
        <div class="card-cue" id="mi-caja-cue">
          <p class="parrafo">Cargando…</p>
        </div>
      </div>

      <!-- Historial: el administrador ve todas, el vendedor las suyas -->
      <div class="card">
        <div class="card-cab">
          <h2 id="cajas-titulo">Historial de cajas</h2>
          <div class="filetools">
            <input type="date" id="cj-desde" class="inline-date">
            <span class="hasta">a</span>
            <input type="date" id="cj-hasta" class="inline-date">
            <button class="btn sm" id="cj-buscar">🔍</button>
          </div>
        </div>
        <div class="envoltura">
          <table class="tabla">
            <thead>
              <tr>
                <th style="width:60px">Caja</th>
                <?php if ($soyAdmin): ?><th style="width:150px">Cajero</th><?php endif; ?>
                <th style="width:150px">Abrió</th>
                <th style="width:150px">Cerró</th>
                <th class="num" style="width:80px">Ventas</th>
                <th class="num" style="width:110px">Fondo</th>
                <th class="num" style="width:120px">Vendido</th>
                <th class="num" style="width:120px">Efectivo</th>
                <th style="width:150px">Estado</th>
                <th class="acciones" style="width:70px"></th>
              </tr>
            </thead>
            <tbody id="cajas-tb"></tbody>
          </table>
        </div>
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

  <!-- ================= COCINA ================= -->
  <section class="vista" id="v-cocina">
    <div class="cocina-barra">
      <div class="cocina-filtros" id="coc-filtros">
        <button class="on" data-coc="pendiente">Nuevas <span class="pill" id="coc-n-pendiente">0</span></button>
        <button data-coc="preparando">Preparando <span class="pill" id="coc-n-preparando">0</span></button>
        <button data-coc="listo">Listos <span class="pill" id="coc-n-listo">0</span></button>
        <button data-coc="cerradas">Historial</button>
      </div>
      <div class="filetools">
        <label class="switch"><input type="checkbox" id="coc-ticket" checked> Imprimir al recibir</label>
        <button class="btn sm" id="btn-coc-refrescar" title="Recargar">⟳ Actualizar</button>
      </div>
    </div>
    <div class="cocina-tablero" id="coc-tablero"></div>
  </section>

  <!-- ================= AJUSTES ================= -->
  <section class="vista" id="v-ajustes">
    <div class="ajustes">

      <div class="solo-admin">
      <h3 class="ajustes-tit">🏪 Negocio</h3>
      <div class="rejilla2">
        <div class="card">
          <div class="card-cab"><h2>Datos del negocio</h2></div>
          <div class="card-cue">
            <div class="rejilla">
              <div class="campo"><label>Nombre</label><input id="c-negocio" maxlength="40"></div>
              <div class="campo"><label>Símbolo de moneda</label><input id="c-moneda" maxlength="4"></div>
              <div class="campo" style="grid-column:1/-1"><label>Dirección</label><input id="c-direccion" maxlength="60"></div>
              <div class="campo"><label>Teléfono</label><input id="c-telefono" maxlength="30"></div>
              <div class="campo"><label>Siguiente folio</label><input id="c-folio" type="number" min="1"></div>
            </div>
            <div class="campo" style="margin-top:13px"><label>Pie del ticket</label><input id="c-pie" maxlength="80"></div>
            <div class="rejilla" style="margin-top:13px">
              <div class="campo"><label>Iniciales del logo</label><input id="c-logo" maxlength="2" style="text-transform:uppercase"></div>
            </div>
            <button class="btn pri" id="btn-c-guardar" style="margin-top:15px">💾 Guardar cambios</button>
          </div>
        </div>

        <div class="card">
          <div class="card-cab"><h2>📈 Estado del sistema</h2></div>
          <div class="card-cue" id="a-estado">Cargando…</div>
        </div>
      </div>

      <h3 class="ajustes-tit">💳 Cobros</h3>
      <div class="rejilla1">
        <div class="card">
          <div class="card-cab">
            <h2>Medios de pago</h2>
            <button class="btn sm pri" id="btn-mp-nuevo">+ Agregar</button>
          </div>
          <p class="parrafo" style="margin:12px 16px 0">
            Cada venta queda registrada con su medio de pago. Al cerrar la caja se compara
            el efectivo contado contra el esperado y se listan tarjetas, transferencias y
            Mercado Pago para que nada quede sin verificar.
          </p>
          <div class="envoltura" style="box-shadow:none;border:0;border-radius:0">
            <table class="tabla">
              <thead><tr><th style="width:44px"></th><th>Método</th><th style="width:120px">Recibe vuelto</th><th style="width:120px">Pide referencia</th><th style="width:100px">Estado</th><th class="acciones" style="width:120px"></th></tr></thead>
              <tbody id="a-mp-tb"></tbody>
            </table>
          </div>
        </div>
      </div>

      <h3 class="ajustes-tit">📦 Mercadería</h3>
      <div class="rejilla1">
        <div class="card">
          <div class="card-cab">
            <h2>Proveedores</h2>
            <button class="btn sm pri" id="btn-prov-nuevo">+ Agregar</button>
          </div>
          <div class="envoltura" style="box-shadow:none;border:0;border-radius:0; max-height:300px; overflow:auto">
            <table class="tabla">
              <thead><tr><th>Proveedor</th><th style="width:150px">Teléfono</th><th class="num" style="width:100px">Artículos</th><th class="acciones" style="width:120px"></th></tr></thead>
              <tbody id="a-prov-tb"></tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-cab">
            <h2>Categorías</h2>
            <button class="btn sm pri" id="btn-cat-nuevo">+ Agregar</button>
          </div>
          <div class="card-cue">
            <p class="parrafo" style="margin-top:0">
              Son las fichas que se ven arriba en <b>Vender</b>. Sólo el administrador las
              crea y las cambia. Al renombrar una, los productos que la tenían la siguen.
            </p>
          </div>
          <div class="envoltura" style="box-shadow:none;border:0;border-radius:0; max-height:240px; overflow:auto">
            <table class="tabla">
              <thead><tr><th>Categoría</th><th style="width:130px">Va a</th><th class="num" style="width:110px">Productos</th><th class="acciones" style="width:120px"></th></tr></thead>
              <tbody id="a-cat-tb"></tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-cab"><h2>Entradas y salidas de mercancía</h2></div>
          <div class="card-cue">
            <p class="parrafo" style="margin-top:0">
              Registra compras, mermas o conteos físicos. Cada cambio queda anotado en el
              kardex del producto, y podés cargar la compra por maple, cajón, bolsa o bidón.
            </p>
            <div style="display:flex; gap:9px; flex-wrap:wrap">
              <button class="btn" id="btn-rapida-entrada">📥 Registrar entrada</button>
              <button class="btn" id="btn-rapida-salida">📤 Registrar salida</button>
            </div>
          </div>
        </div>
      </div>

      <h3 class="ajustes-tit">💾 Datos y respaldo</h3>
      <div class="rejilla1">
        <div class="card">
          <div class="card-cab"><h2>Respaldo de datos</h2></div>
          <div class="card-cue">
            <p class="parrafo" style="margin-top:0">
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
      </div>

      <div class="solo-admin">
      <h3 class="ajustes-tit" id="cocina">🍳 Comandas de cocina</h3>
      <div class="rejilla2">

        <div class="card">
          <div class="card-cab">
            <h2>Comidas y Tragos</h2>
          </div>
          <p class="parrafo" style="margin:12px 16px 0">
            Lo que come y toma la gente en el local o se lleva ya preparado no son
            atajos sueltos: son <b>productos del catálogo</b>, en la categoría
            <b>Comidas y Tragos</b>, con su precio. Se cargan y se cambian los
            precios desde <b>Productos</b>, filtrando por esa categoría.
            <br><br>
            Todos van con <b>sin stock</b>, porque lo que se prepara no se descuenta:
            se cobra y nada más. Como la categoría está marcada <i>va a cocina</i>,
            toda línea que salga de ahí llega sola al papel de la cocina y el cajero
            no tiene que marcar nada.
          </p>
          <p class="parrafo" style="margin:12px 16px">
            Lo mismo vale para cualquier otro producto: si el cajero marca la 🍳
            de una línea, esa línea va a la comanda, sea o no de esta categoría.
          </p>
        </div>

        <!-- El reparto por zonas queda deshabilitado a proposito. Antes el
             selector de zona vivia en el modal de comanda que se borro, asi
             que las zonas se podian cargar desde Ajustes pero ningun pedido
             podia elegir una: el costo se configuraba y nunca se cobraba.
             La tabla zonas y el calculo del envio en el backend siguen
             enteros, asi que revived este bloque y poné el <select> en el
             modal de cobro para volver a activar el reparto. -->

      </div>
      </div>

      <h3 class="ajustes-tit" id="usuarios">👥 Usuarios</h3>
      <div class="rejilla1">
        <div class="card">
          <div class="card-cab">
            <h2>Usuarios del sistema</h2>
            <button class="btn sm pri" id="btn-us-nuevo">+ Agregar</button>
          </div>
          <p class="parrafo" style="margin:12px 16px 0">
            El <b>administrador</b> carga productos, proveedores y precios, ajusta el stock,
            ve e imprime todas las cajas y también puede vender. El <b>vendedor</b> abre y
            cierra su propia caja y vende; el catálogo no lo puede tocar. El de <b>cocina</b>
            sólo ve el tablero de comandas y marca los pedidos: no cobra ni abre caja.
          </p>
          <div class="envoltura" style="box-shadow:none;border:0;border-radius:0">
            <table class="tabla tabla-usuarios">
              <thead>
                <tr>
                  <th></th>
                  <th>Usuario</th>
                  <th>Nombre</th>
                  <th>Rol</th>
                  <th class="num">Cajas</th>
                  <th>Último ingreso</th>
                  <th>Estado</th>
                  <th class="acciones"></th>
                </tr>
              </thead>
              <tbody id="usuarios-tb"></tbody>
            </table>
          </div>
        </div>
      </div>

      </div>

      <h3 class="ajustes-tit">💡 Ayuda</h3>
      <div class="rejilla2">
        <div class="card">
          <div class="card-cab"><h2>Atajos de teclado</h2></div>
          <div class="card-cue">
            <p class="parrafo" style="margin-top:0">
              La lista completa de atajos no está acá: vive en <b>Vender</b>, en el botón
              💡 de la barra de búsqueda, porque el que la necesita es el cajero y nunca
              entra a Ajustes.
            </p>
          </div>
        </div>
      </div>

      <div class="solo-admin">
      <h3 class="ajustes-tit">⚠️ Zona de peligro</h3>
      <div class="rejilla1">
        <div class="card" style="border-color:var(--bad)">
          <div class="card-cab" style="border-color:var(--bad)"><h2 style="color:var(--bad)">Borrar datos</h2></div>
          <div class="card-cue">
            <p class="parrafo" style="margin-top:0">Antes de borrar se descarga un respaldo automático a la carpeta <code>datos/</code>.</p>
            <div style="display:flex; gap:9px; flex-wrap:wrap">
              <button class="btn peligro" data-limpiar="ventas">🗑 Borrar historial de ventas</button>
              <button class="btn peligro" data-limpiar="productos">🗑 Borrar catálogo de productos</button>
              <button class="btn peligro" data-limpiar="kardex">🗑 Borrar kardex</button>
            </div>
          </div>
        </div>
      </div>
      </div>

    </div>
  </section>
</main>

<!-- ================= MODAL COMANDA (cocina) ================= -->
<!--
  La comanda es el papel para la cocina, no la venta. El cobro vive en el
  carrito: acá sólo se anota qué hay que preparar y a quién se lo llevan. Por
  eso el tipo arranca en "mesa" (consumo en el local) y los datos de
  entrega son opcionales.
-->
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
      <!-- Solo aparece si hay alguna linea marcada para comanda. Sin lineas
           marcadas no se imprime ninguna comanda, asi que no hay por que
           preguntarle el nombre a nadie. -->
      <div class="campo" id="cob-comanda" hidden>
        <label>Nombre de quien retira</label>
        <input id="cob-retiro" placeholder="Si no, va como Mostrador" autocomplete="off">
        <p class="parrafo" style="margin:6px 0 0;font-size:12.5px">
          Sale impreso en el papel que se lleva la cocina y el pedido.
        </p>
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

<!-- El modal de zona se fue con el alta de Ajustes: sin selector de zona en
     el cobro no hay a que cargar costo, y un formulario que guarda algo que
     no se usa es peor que no tenerlo. La API de zonas sigue viva. -->

<div class="velo" id="m-categoria">
  <div class="modal" style="max-width:400px">
    <div class="modal-cab"><h2 id="ct-titulo">Nueva categoría</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
          <div class="campo"><label>Nombre de la categoría</label>
        <input id="ct-nombre" placeholder="Bebidas, Botanas…" maxlength="60" autocomplete="off"></div>
      <label class="check" style="display:flex;align-items:center;gap:9px;margin-top:12px;cursor:pointer">
        <input type="checkbox" id="ct-cocina" style="width:auto;margin:0">
        <span>Los productos de esta categoría van a <b>cocina</b></span>
      </label>
      <p class="parrafo" style="margin:9px 0 0;font-size:12px">
        Marca acá la rotisería, las bebidas y los tragos: esas líneas salen
        solas en la comanda cuando el cajero cobra. El cajero puede dar la
        vuelta línea por línea, así que esto es el comportamiento por omisión.
      </p>
    </div>

    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn pri" id="ct-guardar">Guardar categoría</button>
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
        <div class="rejilla mp-campos" style="flex:1;min-width:0;align-content:start">
          <div class="campo" style="grid-column:1/-1"><label>Nombre *</label><input id="mp-nombre" maxlength="60"></div>
          <div class="campo"><label>Código / código de barras <span class="fuente" id="mp-codigo-aviso"></span></label><input id="mp-codigo" maxlength="30" spellcheck="false"></div>
          <div class="campo"><label>Categoría</label><input id="mp-categoria" maxlength="25" list="lista-cat"></div>
          <datalist id="lista-cat"></datalist>
          <div class="campo"><label>Precio de venta * <span class="fuente" id="mp-precio-aviso"></span></label><input id="mp-precio" type="number" step="0.01" min="0"></div>
          <div class="campo"><label>Costo por <span class="mp-base">unidad</span> <span class="fuente" id="mp-costo-aviso"></span></label><input id="mp-costo" type="number" step="0.01" min="0" placeholder="0.00" readonly></div>
          <div class="campo"><label>Stock actual</label><input id="mp-stock" type="number" step="0.01"></div>
          <div class="campo"><label>Stock mínimo (alerta)</label><input id="mp-minimo" type="number" step="0.01" min="0"></div>
          <div class="campo mp-bloque">
            <label class="check" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600">
              <input type="checkbox" id="mp-sin-stock" style="width:auto;height:auto">
              Se vende sin control de stock
            </label>
            <p class="parrafo" style="margin:2px 0 0;font-size:12px">
              Para lo que se prepara al momento y no sale de un stock que se cuente.
              No se descuenta existencias ni entra en los avisos y la valuación de inventario.
            </p>
          </div>
          <div class="campo"><label>Unidad (cómo se cuenta el stock)</label>
            <select id="mp-unidad">
              <option>pieza</option><option>unidad</option><option>paquete</option><option>lata</option>
              <option>botella</option><option>caja</option><option>kg</option><option>litro</option>
            </select>
          </div>
          <div class="campo"><label>Proveedor</label>
            <select id="mp-proveedor"><option value="0">— sin proveedor —</option></select>
          </div>

          <h3 class="subt mp-bloque">📥 Formatos de compra <span class="fuente">— lo que te trae el proveedor</span></h3>
          <p class="parrafo mp-bloque" style="margin:-6px 0 0;font-size:12.5px">
            Maple, cajón, bolsa, caja… El precio es el de <b>una</b> presentación, y el sistema
            calcula el costo dividiéndolo por la cantidad que trae. El stock siempre se cuenta
            en <b><span class="mp-base">unidades</span></b>.
          </p>
          <div id="mp-fmt-compra" class="fmt-lista mp-bloque"></div>
          <button class="btn sm mp-bloque" type="button" id="mp-add-compra" style="justify-self:start">+ Agregar formato de compra</button>

          <h3 class="subt mp-bloque">🏷️ Formatos de venta <span class="fuente">— lo que le vendés al cliente</span></h3>
          <p class="parrafo mp-bloque" style="margin:-6px 0 0;font-size:12.5px">
            Docena, 2x1, media kg… Todos descuentan del stock. Ponele <b>precio fijo</b>
            o <b>% sobre el costo</b> y el sistema te arma el precio. Si no ponés ninguno,
            vale el precio del producto multiplicado por lo que trae el formato.
          </p>
          <div id="mp-fmt-venta" class="fmt-lista mp-bloque"></div>
          <button class="btn sm mp-bloque" type="button" id="mp-add-venta" style="justify-self:start">+ Agregar formato de venta</button>

          <div class="campo mp-bloque">
            <label>Observaciones</label>
            <input id="mp-observaciones" maxlength="200" placeholder="Notas internas: cambios de envase, etc.">
          </div>
          <div class="aviso-linea mp-bloque" id="mp-margen" style="display:none">
            <span>Margen por unidad</span><span id="mp-margen-valor"><b>—</b></span>
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
          <div class="campo"><label>Cantidad <span id="ms-cant-unidad"></span></label><input id="ms-cant" type="number" step="0.01" min="0"></div>
          <div class="campo"><label>Motivo</label><input id="ms-motivo" maxlength="60" placeholder="Compra, merma, conteo…"></div>
        </div>
        <div class="rejilla" style="margin-top:13px" id="ms-compra">
          <div class="campo" id="ms-campo-uc" style="display:none">
            <label>Comprar en formato</label>
            <select id="ms-formato-compra"></select>
          </div>
          <div class="campo" id="ms-campo-pc" style="display:none">
            <label>Precio del <span id="ms-pc-label">formato</span></label>
            <input id="ms-precio-compra" type="number" step="0.01" min="0" placeholder="0.00">
          </div>
          <div class="campo"><label>Proveedor</label>
            <select id="ms-proveedor"><option value="0">— sin proveedor —</option></select>
          </div>
          <div class="campo"><label>N.º de remito / factura</label><input id="ms-documento" maxlength="40" placeholder="REM-0000-0000"></div>
          <div class="campo" id="ms-campo-costo"><label>Costo unitario nuevo</label><input id="ms-costo" type="number" step="0.01" min="0" placeholder="dejar 0 = no cambiar"></div>
        </div>
        <div class="aviso-linea" id="ms-preview" style="display:none;margin-top:12px"></div>
        <p class="parrafo" id="ms-ayuda" style="margin:12px 0 0;font-size:12.5px"></p>
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

<!-- ================= MODAL PRODUCTO RAPIDO ================= -->
<!-- Venta espontánea: el pancho que pidió Pepe, una pizza armada en el momento.
     El producto se carga con su precio y queda en el catálogo, pero marcado
     como "sin stock": no se le descuenta existencia ni entra en los avisos
     de stock. El costo se deja en 0 a propósito, los valores del proveedor
     cambian todos los días y acá no se arma una receta. -->
<div class="velo" id="m-rapido">
  <div class="modal" style="max-width:420px">
    <div class="modal-cab"><h2>Producto rápido</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <div class="campo"><label>Nombre *</label>
        <input id="pr-nombre" maxlength="60" placeholder="Pancho, pizza especial,(fill)…" autocomplete="off" spellcheck="false">
      </div>
      <div class="rejilla" style="margin-top:13px">
        <div class="campo"><label>Precio de venta *</label>
          <input id="pr-precio" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
        </div>
        <div class="campo"><label>Categoría</label>
          <input id="pr-categoria" maxlength="25" list="lista-cat" placeholder="Venta libre">
          <datalist id="lista-cat"></datalist>
        </div>
      </div>
      <div class="aviso-linea" id="pr-existe" style="display:none;margin-top:13px">
        <span>Ya existe en el catálogo</span><b id="pr-existe-nombre">—</b>
      </div>
      <p class="parrafo" style="margin:13px 0 0">
        Se guarda en el catálogo con este precio y queda listo para mañana.
        No descuenta stock: si el mismo nombre ya existe, se actualiza el precio
        en vez de duplicarlo.
      </p>
    </div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn pri" id="pr-guardar">Agregar al ticket</button>
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

<!-- ================= MODAL ABRIR CAJA ================= -->
<div class="velo" id="m-abrir-caja">
  <div class="modal" style="max-width:440px">
    <div class="modal-cab"><h2>🏧 Abrir caja</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <p class="parrafo" style="margin-top:0">
        Contá el efectivo que dejás en la gaveta para empezar el turno.
        Todas las ventas que cobres van a quedar sumadas a esta caja
        y se van a conciliar al cerrarla.
      </p>
      <div class="campo">
        <label>Efectivo inicial en la gaveta</label>
        <input id="ac-monto" type="text" inputmode="decimal" placeholder="0,00" autocomplete="off">
      </div>
      <p class="parrafo" id="ac-aviso" style="margin:10px 0 0;font-size:12.5px"></p>
    </div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn ok" id="ac-ok">Abrir caja</button>
    </div>
  </div>
</div>

<!-- ================= MODAL CERRAR CAJA ================= -->
<div class="velo" id="m-cerrar-caja">
  <div class="modal" style="max-width:560px">
    <div class="modal-cab"><h2>🔒 Cerrar caja</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <p class="parrafo" style="margin-top:0">
        Contá el efectivo de la gaveta y anotá cuánto hay en cada medio de pago.
        El sistema te dice cuánto esperaba encontrar: si no coincide, escribí el motivo
        del descuadre para que el administrador lo vea.
      </p>
      <div id="cc-tabla"></div>
      <div class="campo" id="cc-motivo-campo" style="margin-top:14px; display:none">
        <label id="cc-motivo-label">Motivo del descuadre</label>
        <input id="cc-motivo" maxlength="200" placeholder="Ej: me quedé sin cambio para un cliente">
      </div>
    </div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn ok" id="cc-ok">Cerrar caja</button>
    </div>
  </div>
</div>

<!-- ================= MODAL DETALLE DE CAJA ================= -->
<div class="velo" id="m-caja-detalle">
  <div class="modal" style="max-width:680px">
    <div class="modal-cab"><h2 id="cd-titulo">Caja</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue" id="cd-cue"></div>
    <input type="hidden" id="cd-caja-datos" value="">
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cerrar</button>
      <button class="btn" id="cd-cerrar-caja" style="display:none">🔒 Cerrar esta caja</button>
      <button class="btn pri" id="cd-imprimir">🖨 Imprimir</button>
    </div>
  </div>
</div>

<!-- ================= MODAL USUARIO ================= -->
<div class="velo" id="m-usuario">
  <div class="modal" style="max-width:520px">
    <div class="modal-cab"><h2 id="mu-titulo">Usuario</h2><button class="cerrar" data-cerrar>✕</button></div>
    <div class="modal-cue">
      <div class="rejilla">
        <div class="campo">
          <label>Usuario (para entrar)</label>
          <input id="mu-usuario" autocapitalize="none" spellcheck="false" autocomplete="off">
        </div>
        <div class="campo">
          <label>Nombre y apellido</label>
          <input id="mu-nombre" autocomplete="off">
        </div>
        <div class="campo">
          <label>Rol</label>
          <select id="mu-rol">
            <option value="vendedor">Vendedor — abre y cierra su caja, vende</option>
            <option value="cocina">Cocina — sólo prepara comandas, no cobra</option>
            <option value="admin">Administrador — control total</option>
          </select>
        </div>
        <div class="campo">
          <label>Clave <span class="fuente" id="mu-clave-nota">(dejala vacía para no cambiarla)</span></label>
          <input id="mu-clave" type="text" autocapitalize="none" spellcheck="false" autocomplete="new-password">
        </div>
      </div>
      <label class="check" style="margin-top:12px">
        <input type="checkbox" id="mu-activo" checked>
        <span>Puede entrar al sistema</span>
      </label>
      <p class="parrafo" style="margin:12px 0 0;font-size:12.5px">
        La clave se guarda cifrada. Cuando le pongas una clave nueva, el usuario
        va a tener que cambiarla la primera vez que entre.
      </p>
    </div>
    <div class="modal-pie">
      <button class="btn" data-cerrar>Cancelar</button>
      <button class="btn pri" id="mu-ok">Guardar</button>
    </div>
  </div>
</div>

<div id="avisos"></div>
<div id="ticket"></div>
<div id="ticket-caja"></div>
<div id="ticket-comanda"></div>

<?php
// Los modulos de js/ se cargan en orden y como scripts clasicos: comparten
// el ambito global, asi que el prefijo numerico define el orden de carga.
// El numero del archivo es la seccion original de app.js.
$modulos = [
    '01_utilidades',
    '02_api',
    '03_estado',
    '04_carrito',
    '05_punto_de_venta',
    '06_cobro',
    '07_productos',
    '08_movimientos_de_mercancer_a',
    '09_historial',
    '10_detalle_de_venta',
    '11_reportes',
    '12_ajustes',
    '13_csv_de_productos',
    '14_modales',
    '15_cajas',
    '16_usuarios',
    '17_carga_de_datos',
    '18_navegacion',
    '19_eventos',
    '20_exportar_historial',
    '21_arranque',
];
foreach ($modulos as $modulo) {
    $archivo = __DIR__ . '/js/' . $modulo . '.js';
    $v = @filemtime($archivo) ?: '1';
    echo '<script src="js/' . $modulo . '.js?v=' . $v . '"></script>' . "\n";
}
?>
</body>
</html>
