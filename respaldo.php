<?php
/**
 * Kiosco — respaldos y restauracion.
 *
 *   respaldo.php                      pagina de administracion de respaldos
 *   respaldo.php?accion=descargar     descarga el volcado .sql completo
 *   respaldo.php?accion=csv          导出 productos a CSV
 *   respaldo.php?accion=ventas_csv    exporta ventas a CSV
 *   respaldo.php?restaurar=1          recibe un .sql por POST y lo restaura
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

if (!instalado()) {
    header('Location: instalar.php');
    exit;
}

$accion = (string) ($_GET['accion'] ?? '');
$bd     = pdoBd();

/** Cabecera para descargar un archivo. */
function bajar(string $nombre, string $contenido, string $tipo): never
{
    header('Content-Type: ' . $tipo . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-store');
    echo $contenido;
    exit;
}

function marca(): string
{
    return date('Y-m-d_His');
}

/* =====================================================================
   Descargas
   ===================================================================== */
if ($accion === 'descargar') {
    $sql = volcarSQL();
    bajar('kiosco_respaldo_' . marca() . '.sql', $sql, 'application/sql');
}

if ($accion === 'csv') {
    $filas = ['Nombre;Codigo;Categoria;Precio;Stock;Minimo;Unidad'];
    foreach ($bd->query('SELECT * FROM productos ORDER BY nombre') as $p) {
        $celdas = [
            $p['nombre'], $p['codigo'] ?? '', $p['categoria'] ?? '',
            number_format((float) $p['precio'], 2, '.', ''),
            number_format((float) $p['stock'], 2, '.', ''),
            number_format((float) $p['minimo'], 2, '.', ''),
            $p['unidad'] ?: 'pieza',
        ];
        $filas[] = implode(';', array_map(function ($c) {
            return preg_match('/[";\r\n]/', (string) $c) ? '"' . str_replace('"', '""', $c) . '"' : $c;
        }, $celdas));
    }
    bajar('productos_' . date('Y-m-d') . '.csv', "\xEF\xBB\xBF" . implode("\r\n", $filas), 'text/csv');
}

if ($accion === 'ventas_csv') {
    $filas = ['Folio;Fecha;Hora;Articulos;Metodo;Subtotal;Descuento;Total;Estado;Referencia;Detalle'];
    $st = $bd->query(
        'SELECT v.*, (SELECT COALESCE(SUM(cantidad),0) FROM venta_items WHERE venta_id = v.id) AS piezas
         FROM ventas v ORDER BY v.id DESC LIMIT 5000'
    );
    foreach ($st as $v) {
        $ts = strtotime($v['fecha']);
        $items = $bd->prepare('SELECT nombre, cantidad, precio, importe FROM venta_items WHERE venta_id = ? ORDER BY id');
        $items->execute([$v['id']]);
        $detalle = [];
        foreach ($items as $i) {
            $detalle[] = $i['cantidad'] . 'x ' . $i['nombre'] . ' (' . number_format((float) $i['importe'], 2) . ')';
        }
        $filas[] = implode(';', [
            $v['folio'],
            date('d/m/Y', (int) $ts),
            date('H:i', (int) $ts),
            number_format((float) $v['piezas'], 0, '.', ''),
            $v['metodo'],
            number_format((float) $v['subtotal'], 2, '.', ''),
            number_format((float) $v['descuento'], 2, '.', ''),
            number_format((float) $v['total'], 2, '.', ''),
            ((int) $v['anulada'] === 1) ? 'Anulada' : 'Valida',
            $v['referencia'] ?? '',
            implode(' | ', $detalle),
        ]);
    }
    bajar('ventas_' . date('Y-m-d') . '.csv', "\xEF\xBB\xBF" . implode("\r\n", $filas), 'text/csv');
}

/* =====================================================================
   Restauracion
   ===================================================================== */
$mensaje = '';
$tipoMsj = 'ok';

if (isset($_GET['restaurar'])) {
    if (!($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']))) {
        $mensaje = 'No se recibió ningún archivo.';
        $tipoMsj = 'mal';
    } else {
        $f = $_FILES['archivo'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $mensaje = 'Falló la subida del archivo (' . $f['error'] . ').';
            $tipoMsj = 'mal';
        } elseif (!preg_match('/\.sql$/i', $f['name'])) {
            $mensaje = 'El archivo debe terminar en .sql';
            $tipoMsj = 'mal';
        } else {
            // 1. Red de seguridad: respaldo del estado actual
            $seguro = crearRespaldo('Automatico antes de restaurar');
            $candidatas = [];
            try {
                $candidatas = $bd->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            } catch (Throwable $e) {
                // la base puede estar vacia
            }

            $sql = file_get_contents($f['tmp_name']);
            $sentencias = dividirSentencias($sql);

            $hechas = 0;
            $fallos = [];
            $bd->beginTransaction();
            try {
                if ($candidatas) {
                    $bd->exec('SET FOREIGN_KEY_CHECKS = 0');
                    foreach ($candidatas as $t) {
                        $bd->exec("DROP TABLE IF EXISTS `$t`");
                    }
                }
                foreach ($sentencias as $s) {
                    try {
                        $bd->exec($s);
                        $hechas++;
                    } catch (Throwable $e2) {
                        $fallos[] = mb_substr($e2->getMessage(), 0, 120);
                    }
                }
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                $mensaje = 'La restauración se detuvo: ' . $e->getMessage();
                $tipoMsj = 'mal';
            }

            if ($mensaje === '') {
                $mensaje = "Restauración terminada. Se ejecutaron $hechas instrucciones.";
                if ($fallos) {
                    $tipoMsj = 'aviso';
                    $mensaje .= ' Con ' . count($fallos) . ' error(es): ' . htmlspecialchars(implode(' · ', array_slice($fallos, 0, 4)), ENT_QUOTES, 'UTF-8');
                }
                $mensaje .= '<br><small>Se guardó antes el estado anterior en <code>'
                    . htmlspecialchars(basename($seguro), ENT_QUOTES, 'UTF-8') . '</code></small>';
            }
        }
    }
}

/* =====================================================================
   Respaldo rapido (usado por los botones de la zona de peligro)
   ===================================================================== */
if (isset($_GET['rapido'])) {
    try {
        $ruta = crearRespaldo('Manual desde el sistema');
        header('Location: respaldo.php?hecho=1');
        exit;
    } catch (Throwable $e) {
        $mensaje = 'No se pudo crear el respaldo: ' . $e->getMessage();
        $tipoMsj = 'mal';
    }
}
if (isset($_GET['hecho'])) {
    $mensaje = 'Respaldo creado en la carpeta <code>datos/</code> de esta computadora.';
}

/* =====================================================================
   Datos para la pagina
   ===================================================================== */
$respaldoHecho = false;
$rutaHecho = '';
$archivos = [];
foreach (glob(carpetaDatos() . DIRECTORY_SEPARATOR . 'respaldo_*.sql') ?: [] as $f) {
    $archivos[] = ['n' => basename($f), 't' => filemtime($f), 'k' => number_format(filesize($f) / 1024, 1) . ' KB'];
}
usort($archivos, fn($a, $b) => $b['t'] <=> $a['t']);

$ultimo = $archivos[0] ?? null;

$info = [
    'Productos'   => (int) $bd->query('SELECT COUNT(*) FROM productos')->fetchColumn(),
    'Ventas'      => (int) $bd->query('SELECT COUNT(*) FROM ventas')->fetchColumn(),
    'Movimientos' => (int) $bd->query('SELECT COUNT(*) FROM movimientos')->fetchColumn(),
    'Tamaño BD'   => number_format((int) $bd->query(
        'SELECT ROUND(SUM(data_length + index_length) / 1024) FROM information_schema.TABLES WHERE table_schema = "' .
        DB_NOMBRE . '"'
    )->fetchColumn() / 1024, 2) . ' MB',
    'MySQL'       => (string) $bd->query('SELECT VERSION()')->fetchColumn(),
    'PHP'         => PHP_VERSION,
];
?><!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Respaldos — Kiosco</title>
<link rel="stylesheet" href="estilos.css?v=1">
<style>
  body{display:block; overflow:auto}
  .pagina{max-width:960px; margin:0 auto; padding:26px 18px 60px}
  h1{font-size:24px; margin:0 0 4px}
  .intro{color:var(--muted); margin:0 0 22px; font-size:14px}
  .seccion{margin-bottom:18px}
  .fila-info{display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line2); font-size:13.5px}
  .fila-info:last-child{border-bottom:0}
  .fila-info b{font-variant-numeric:tabular-nums}
  .arriba{display:flex; align-items:center; gap:12px; margin-bottom:18px}
  .volver{width:38px; height:38px; border-radius:9px; border:1px solid var(--line); background:var(--panel);
          display:grid; place-items:center; text-decoration:none; font-size:17px; flex:0 0 38px}
  .volver:hover{background:var(--panel2)}
  .msj{padding:13px 16px; border-radius:10px; margin-bottom:18px; font-size:14px; border:1px solid}
  .msj.ok{background:var(--okbg); color:var(--ok); border-color:var(--ok)}
  .msj.mal{background:var(--badbg); color:var(--bad); border-color:var(--bad)}
  .msj.aviso{background:var(--warnbg); color:var(--warn); border-color:var(--warn)}
  .subida{border:2px dashed var(--line); border-radius:11px; padding:26px; text-align:center; background:var(--panel2)}
  .subida input[type=file]{display:block; margin:0 auto 14px}
  table.resp{width:100%; border-collapse:collapse; font-size:13px}
  table.resp td{padding:8px 10px; border-bottom:1px solid var(--line2)}
  table.resp tr:last-child td{border-bottom:0}
  .fecha-rel{color:var(--muted); font-size:12px}
</style>
</head>
<body>

<div class="pagina">
  <div class="arriba">
    <a class="volver" href="index.php" title="Volver al kiosco">←</a>
    <div>
      <h1>💾 Respaldos y restauración</h1>
      <div style="color:var(--muted);font-size:13px">Protege tu inventario y tu historial de ventas</div>
    </div>
  </div>

  <?php if ($mensaje !== ''): ?>
    <div class="msj <?= htmlspecialchars($tipoMsj, ENT_QUOTES, 'UTF-8') ?>"><?= $mensaje ?></div>
  <?php endif; ?>

  <div class="rejilla2">

    <div class="card seccion">
      <div class="card-cab"><h2>⬇ Descargar respaldo</h2></div>
      <div class="card-cue">
        <p class="parrafo">
          Un respaldo es un archivo de texto con <strong>todo</strong> tu sistema: productos,
          existencias, ventas y movimientos. Con ese archivo puedes reconstruir el kiosco
          completo aunque se rompa la computadora.
        </p>
        <a class="btn ok" href="?accion=descargar" style="height:46px;padding:0 20px;font-size:15px">
          Descargar respaldo completo (.sql)
        </a>
        <p class="parrafo" style="margin:14px 0 0;font-size:12.5px">
          💡 Guárdalo en un USB o en tu correo. Con una copia al día, nunca perdiste más de un día de ventas.
        </p>
      </div>
    </div>

    <div class="card seccion">
      <div class="card-cab"><h2>📈 Estado de los datos</h2></div>
      <div class="card-cue">
        <?php foreach ($info as $k => $v): ?>
          <div class="fila-info"><span class="fuente"><?= htmlspecialchars($k) ?></span><b><?= htmlspecialchars((string) $v) ?></b></div>
        <?php endforeach; ?>
        <?php if ($ultimo): ?>
          <div class="fila-info" style="border-top:2px solid var(--line); margin-top:8px; padding-top:12px">
            <span class="fuente">Último respaldo en disco</span>
            <b style="font-size:12.5px">
              <?= htmlspecialchars(date('d/m/Y H:i', $ultimo['t'])) ?>
              <span class="fecha-rel">(<?= $ultimo['k'] ?>)</span>
            </b>
          </div>
        <?php else: ?>
          <div class="fila-info" style="border-top:2px solid var(--line); margin-top:8px; padding-top:12px">
            <span class="fuente">Último respaldo en disco</span>
            <b style="color:var(--warn)">Nunca</b>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card seccion">
      <div class="card-cab"><h2>📂 Respaldos guardados en esta PC</h2></div>
      <div class="card-cue" style="padding:0">
        <?php if (!$archivos): ?>
          <div class="vacio" style="padding:30px">
            <div class="ico">🗂</div>
            <h3>Todavía no hay respaldos</h3>
            <p>Se guardan aquí automáticamente antes de cada borrado, y también cuando tú quieras.</p>
            <a class="btn pri" href="?rapido=1">Crear uno ahora</a>
          </div>
        <?php else: ?>
          <div style="max-height:290px; overflow:auto">
            <table class="resp">
              <tr>
                <td>
                  <a href="datos/<?= rawurlencode($ultimo === null ? '' : $archivos[0]['n']) ?>"
                     style="color:var(--brand);font-weight:600;text-decoration:none"><?= htmlspecialchars($archivos[0]['n']) ?></a>
                </td>
                <td class="fecha-rel"><?= date('d/m/Y H:i', $archivos[0]['t']) ?></td>
                <td class="fecha-rel"><?= htmlspecialchars($archivos[0]['k']) ?></td>
              </tr>
              <?php foreach (array_slice($archivos, 1, 40) as $a): ?>
                <tr>
                  <td><a href="datos/<?= rawurlencode($a['n']) ?>" style="color:var(--brand);text-decoration:none"><?= htmlspecialchars($a['n']) ?></a></td>
                  <td class="fecha-rel"><?= date('d/m/Y H:i', $a['t']) ?></td>
                  <td class="fecha-rel"><?= htmlspecialchars($a['k']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
          <div style="padding:12px 16px; border-top:1px solid var(--line)">
            <a class="btn sm" href="?rapido=1">+ Crear respaldo ahora</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card seccion">
      <div class="card-cab"><h2>⬆ Exportar a Excel</h2></div>
      <div class="card-cue">
        <p class="parrafo">Archivos CSV que se abren directo en Excel, para regresar o mandar a tu contador.</p>
        <div style="display:flex; gap:9px; flex-wrap:wrap">
          <a class="btn" href="?accion=csv">Productos (CSV)</a>
          <a class="btn" href="?accion=ventas_csv">Ventas (CSV)</a>
          <a class="btn" href="phpmyadmin/" target="_blank" rel="noopener">🗄 phpMyAdmin</a>
        </div>
      </div>
    </div>

  </div>

  <div class="card seccion" style="border-color:var(--bad)">
    <div class="card-cab" style="border-color:var(--bad)">
      <h2 style="color:var(--bad)">♻️ Restaurar un respaldo</h2>
    </div>
    <div class="card-cue">
      <p class="parrafo">
        Sube un archivo <code>.sql</code> para <strong>reemplazar todos los datos actuales</strong> por los del archivo.
        Antes de hacerlo, el sistema guarda automáticamente el estado actual, por si acaso.
      </p>
      <form method="post" enctype="multipart/form-data" action="?restaurar=1">
        <div class="subida">
          <input type="file" name="archivo" accept=".sql" required>
          <button class="btn peligro" type="submit">Restaurar este respaldo</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card seccion">
    <div class="card-cab"><h2>🖥️ Respaldo automático de Laragon</h2></div>
    <div class="card-cue">
      <p class="parrafo" style="margin:0">
        Además del respaldo manual, Laragon puede respaldar MySQL solo, todos los días.
        En el menú de Laragon busca <strong>MySQL → Backup automático</strong>, elige la carpeta
        <code>datos/</code> y la frecuencia. Con eso, aunque se te olvide descargar, tu información
        queda a salvo en la propia computadora.
      </p>
    </div>
  </div>
</div>

</body>
</html>
