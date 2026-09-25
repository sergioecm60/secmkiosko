<?php
/**
 * Kiosco — instalacion.
 * Crea la base de datos, las tablas y la configuracion inicial.
 * Se puede abrir desde el navegador o ejecutar con:  php instalar.php
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

$log  = [];
$malas = false;

function paso(string $texto, bool $ok = true, string $detalle = ''): void
{
    global $log, $malas;
    if (!$ok) {
        $malas = true;
    }
    $log[] = ['texto' => $texto, 'ok' => $ok, 'detalle' => $detalle];
}

try {
    // 1. Base de datos
    if (baseExiste()) {
        paso('Base de datos "' . DB_NOMBRE . '" ya existe.');
    } else {
        crearBase();
        paso('Base de datos "' . DB_NOMBRE . '" creada.');
    }

    // 2. Conexion
    $pdo = pdoBd();
    paso('Conexion establecida con MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn() . '.');

    // 3. Tablas
    $antes = instalado();
    crearTablas();
    paso($antes ? 'Tablas verificadas y actualizadas.' : 'Tablas creadas.', true,
        'productos · ventas · venta_items · movimientos · config');

    // 4. Configuracion inicial
    $faltantes = 0;
    foreach (CONFIG_INICIAL as $clave => $valor) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM `config` WHERE `clave` = ?');
        $st->execute([$clave]);
        if ((int) $st->fetchColumn() === 0) {
            guardarConfig($clave, (string) $valor);
            $faltantes++;
        }
    }
    paso('Configuracion inicial' . ($faltantes ? " ($faltantes valores nuevos)" : ' completa.'));

    // 5. Mejoras de esquema (idempotente)
    try {
        $cambios = actualizarEsquema();
        paso('Esquema actualizado: ' . implode(' · ', $cambios) . '.');
    } catch (Throwable $e) {
        paso('No se pudieron aplicar todas las mejoras de esquema', false, $e->getMessage());
    }

    // 5. Productos de ejemplo (opcional): por navegador (?demo=1) o por consola (php instalar.php demo)
    $pideDemo = (($_GET['demo'] ?? '') === '1')
        || in_array('demo', $argv ?? [], true);
    if ($pideDemo) {
        $total = (int) $pdo->query('SELECT COUNT(*) FROM `productos`')->fetchColumn();
        if ($total > 0) {
            paso('Productos de ejemplo omitidos: ya hay ' . $total . ' producto(s).');
        } else {
            // nombre, codigo, categoria, precio, costo, stock, minimo, unidad
            $demo = [
                ['Agua mineral 600 ml',        '7501234567890', 'Bebidas',    12.00,  7.10,  48, 12, 'botella'],
                ['Refresco de cola 600 ml',    '7501234567891', 'Bebidas',    18.00, 10.80,  36, 12, 'botella'],
                ['Jugo de naranja 1 L',        '7501234567892', 'Bebidas',    26.00, 16.40,  18,  6, 'botella'],
                ['Cerveza lata 355 ml',        '7501234567893', 'Bebidas',    28.00, 18.90,  24,  6, 'lata'],
                ['Leche entera 1 L',           '7501234567894', 'Lácteos',    24.00, 15.20,  20,  8, 'botella'],
                ['Yogur natural 500 g',        '7501234567895', 'Lácteos',    28.00, 17.60,  14,  6, 'pieza'],
                ['Queso Oaxaca 200 g',         '7501234567896', 'Lácteos',    46.00, 30.20,   9,  4, 'pieza'],
                ['Huevos blancos (kg)',        '7501234567897', 'Abarrotes',  48.00, 33.50,  15,  5, 'kg'],
                ['Pan de caja',                '7501234567898', 'Panadería',  34.00, 20.10,  11,  5, 'pieza'],
                ['Galletas de avena x12',      '7501234567899', 'Botanas',    38.00, 24.70,  22,  8, 'paquete'],
                ['Chocolate en barra',         '7501234567900', 'Botanas',    16.00,  9.40,  40, 12, 'pieza'],
                ['Café soluble 250 g',         '7501234567901', 'Despensa',   78.00, 55.30,   8,  4, 'paquete'],
                ['Cereal de maíz 500 g',       '7501234567902', 'Despensa',   52.00, 36.80,  10,  4, 'paquete'],
                ['Aceite vegetal 1 L',         '7501234567903', 'Despensa',   44.00, 31.60,  12,  6, 'botella'],
                ['Arroz grano largo 1 kg',     '7501234567904', 'Despensa',   32.00, 22.40,  25, 10, 'paquete'],
                ['Frijol negro 500 g',         '7501234567905', 'Despensa',   28.00, 19.60,  18,  8, 'paquete'],
                ['Azúcar 1 kg',                '7501234567906', 'Despensa',   30.00, 21.10,  20,  8, 'paquete'],
                ['Pasta para spaghetti 500 g', '7501234567907', 'Despensa',   26.00, 17.90,  16,  8, 'paquete'],
                ['Detergente líquido 1 L',     '7501234567908', 'Limpieza',   68.00, 50.40,   7,  4, 'botella'],
                ['Jabón de platos 500 ml',   '7501234567909', 'Limpieza',   36.00, 24.20,  11,  5, 'botella'],
                ['Papel higiénico x4',         '7501234567910', 'Higiene',    58.00, 42.30,  14,  6, 'paquete'],
                ['Servilletas x100',           '7501234567911', 'Higiene',    22.00, 14.10,  20,  8, 'paquete'],
                ['Bolsas para basura x20',     '7501234567912', 'Higiene',    28.00, 18.90,  13,  6, 'paquete'],
                ['Pilas alcalinas x4',         '7501234567913', 'Varios',     45.00, 31.80,   6,  4, 'paquete'],
            ];
            $st = $pdo->prepare(
                'INSERT INTO `productos` (`nombre`,`codigo`,`categoria`,`precio`,`costo`,`stock`,`minimo`,`unidad`,`activo`)
                 VALUES (?,?,?,?,?,?,?,?,1)'
            );
            foreach ($demo as $p) {
                $st->execute($p);
            }
            registrarMovimiento([
                'tipo' => 'alta', 'producto_id' => null, 'producto_nombre' => 'Carga inicial de productos',
                'cantidad' => 0, 'stock_anterior' => 0, 'stock_actual' => 0, 'referencia' => 'instalacion',
            ]);
            paso('Productos de ejemplo cargados: ' . count($demo) . '.');
        }
    }
} catch (Throwable $e) {
    paso('Error durante la instalacion', false, $e->getMessage());
}

if (PHP_SAPI === 'cli') {
    foreach ($log as $l) {
        echo ($l['ok'] ? '[OK]   ' : '[FALLA] ') . $l['texto'];
        if ($l['detalle'] !== '') {
            echo ' — ' . $l['detalle'];
        }
        echo PHP_EOL;
    }
    exit($malas ? 1 : 0);
}

$listo = !$malas;
?><!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<title>Instalación — Kiosco</title>
<style>
  body{font:15px/1.55 "Segoe UI",system-ui,sans-serif;background:#eef1f7;color:#131e33;margin:0;
       display:grid;place-items:center;min-height:100vh;padding:24px}
  .caja{background:#fff;border:1px solid #dce3ee;border-radius:14px;max-width:640px;width:100%;
        box-shadow:0 8px 30px rgba(19,30,51,.1);overflow:hidden}
  .cab{padding:24px 26px;background:linear-gradient(140deg,#1d4ed8,#1e40af);color:#fff}
  .cab h1{margin:0 0 4px;font-size:21px}
  .cab p{margin:0;opacity:.9;font-size:13.5px}
  .cuerpo{padding:22px 26px}
  ul{list-style:none;margin:0 0 20px;padding:0}
  li{display:flex;gap:11px;align-items:flex-start;padding:9px 0;border-bottom:1px solid #eaeff6;font-size:14px}
  li:last-child{border-bottom:0}
  .ic{width:22px;height:22px;flex:0 0 22px;border-radius:50%;display:grid;place-items:center;
      font-size:12px;font-weight:800;color:#fff;margin-top:1px}
  .ic.ok{background:#15803d} .ic.mal{background:#b91c1c}
  .det{color:#5d6d87;font-size:12.5px;display:block;margin-top:2px}
  .mono{font-family:Consolas,monospace;background:#f7f9fc;padding:1px 5px;border-radius:4px;font-size:12.5px}
  .pie{padding:16px 26px;background:#f7f9fc;border-top:1px solid #dce3ee;display:flex;gap:10px;flex-wrap:wrap}
  .btn{display:inline-block;height:44px;padding:0 22px;border-radius:9px;text-decoration:none;font-weight:650;
       display:inline-flex;align-items:center;border:1px solid #dce3ee;background:#fff;color:#131e33}
  .btn.pri{background:#15803d;border-color:#15803d;color:#fff}
  .nota{background:#fdf3e3;border:1px solid #f0d9ae;color:#b45309;padding:12px 14px;border-radius:9px;
        font-size:13.5px;margin-bottom:18px}
</style>
</head>
<body>
<div class="caja">
  <div class="cab">
    <h1><?= $listo ? '✅ Instalación completada' : '❌ La instalación encontró problemas' ?></h1>
    <p>Kiosco · control de ventas y existencias</p>
  </div>
  <div class="cuerpo">
    <?php if ($listo): ?>
      <div class="nota">
        <?php if ((int) pdoBd()->query('SELECT COUNT(*) FROM productos')->fetchColumn() === 0): ?>
          Todavía no hay productos. Podés <strong>cargar los productos de ejemplo</strong> para probar el sistema,
          o empezar con tu catálogo real.
        <?php else: ?>
          Ya tenés <span class="mono"><?= (int) pdoBd()->query('SELECT COUNT(*) FROM productos')->fetchColumn() ?></span>
          producto(s) en el catálogo. Podés editarlos o vaciarlos cuando quieras desde <em>Productos</em>.
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <ul>
      <?php foreach ($log as $l): ?>
        <li>
          <span class="ic <?= $l['ok'] ? 'ok' : 'mal' ?>"><?= $l['ok'] ? '✓' : '!' ?></span>
          <span><?= htmlspecialchars($l['texto'], ENT_QUOTES, 'UTF-8') ?>
            <?php if ($l['detalle'] !== ''): ?>
              <span class="det"><?= htmlspecialchars($l['detalle'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="pie">
    <?php if ($listo): ?>
      <a class="btn pri" href="index.php">Abrir Kiosco →</a>
      <?php if ((int) pdoBd()->query('SELECT COUNT(*) FROM productos')->fetchColumn() === 0): ?>
        <a class="btn" href="?demo=1">Cargar productos de ejemplo</a>
      <?php endif; ?>
    <?php else: ?>
      <a class="btn" href="instalar.php">Reintentar</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
