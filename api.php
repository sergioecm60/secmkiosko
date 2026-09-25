<?php
/**
 * Kiosco — API JSON.
 * Uso:  api.php?accion=nombre      (GET)  o   api.php?accion=nombre  (POST, cuerpo JSON)
 */
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* ---------- Proteccion contra peticiones desde sitios ajenos ---------- */
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origen !== '') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $misma = parse_url($origen, PHP_URL_HOST) === ($host !== '' ? preg_replace('/:\d+$/', '', $host) : null);
    if (!$misma) {
        http_response_code(403);
        salida(['ok' => false, 'error' => 'Origen no permitido.']);
    }
}

/* ---------- Utilidades ---------- */
function salida(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cuerpo(): array
{
    static $c = null;
    if ($c === null) {
        $crudo = file_get_contents('php://input') ?: '';
        $c = json_decode($crudo, true);
        $c = is_array($c) ? $c : [];
    }
    return $c;
}

function p(string $clave, $defecto = null)
{
    $c = cuerpo();
    if (array_key_exists($clave, $c)) {
        return $c[$clave];
    }
    return $_GET[$clave] ?? $defecto;
}

function pTxt(string $clave, int $max = 200, string $defecto = ''): string
{
    return texto(p($clave, $defecto), $max);
}

function pNum(string $clave, float $defecto = 0.0): float
{
    return numero(p($clave, $defecto), $defecto);
}

function pInt(string $clave, int $defecto = 0): int
{
    return entero(p($clave, $defecto), $defecto);
}

function hoy(): string
{
    return date('Y-m-d');
}

function desde(): string
{
    $v = (string) p('desde', '');
    if ($v !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v . ' 00:00:00';
    }
    return hoy() . ' 00:00:00';
}

function hasta(): string
{
    $v = (string) p('hasta', '');
    if ($v !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v . ' 23:59:59';
    }
    return hoy() . ' 23:59:59';
}

/** Convierte una fila de productos a los tipos que espera el navegador. */
function prod(array $f): array
{
    return [
        'id'        => (int) $f['id'],
        'nombre'    => $f['nombre'],
        'codigo'    => $f['codigo'] ?? '',
        'categoria' => $f['categoria'] ?? '',
        'precio'    => (float) $f['precio'],
        'stock'     => (float) $f['stock'],
        'minimo'    => (float) $f['minimo'],
        'unidad'    => $f['unidad'] ?: 'pieza',
        'foto'      => $f['foto'] ?? '',
        'activo'    => (int) $f['activo'] === 1,
        'creado'    => $f['creado'] ?? null,
    ];
}

function venta(array $f): array
{
    return [
        'id'         => (int) $f['id'],
        'folio'      => (int) $f['folio'],
        'fecha'      => $f['fecha'],
        'subtotal'   => (float) $f['subtotal'],
        'descuento'  => (float) $f['descuento'],
        'total'      => (float) $f['total'],
        'metodo'     => $f['metodo'],
        'recibido'   => $f['recibido'] === null ? null : (float) $f['recibido'],
        'vuelto'     => $f['vuelto'] === null ? null : (float) $f['vuelto'],
        'referencia' => $f['referencia'] ?? '',
        'nota'       => $f['nota'] ?? '',
        'anulada'    => (int) $f['anulada'] === 1,
        'motivo'     => $f['motivo'] ?? '',
    ];
}

/* ---------- Arranque ---------- */
if (!instalado()) {
    salida(['ok' => false, 'error' => 'El sistema no esta instalado. Abre instalar.php', 'instalar' => true], 503);
}

$accion = (string) ($_GET['accion'] ?? '');
$bd     = pdoBd();

try {
    switch ($accion) {

        /* ============================================================
           ESTADO GENERAL
           ============================================================ */
        case 'estado': {
            $cfg = leerConfig();

            $st = $bd->prepare('SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND DATE(fecha) = CURDATE()');
            $st->execute();
            $hoyVenta = $st->fetch();

            $st = $bd->query('SELECT COUNT(*) AS n FROM productos WHERE activo = 1');
            $nProd = (int) $st->fetchColumn();

            $st = $bd->query('SELECT COALESCE(SUM(stock * precio),0) AS v FROM productos WHERE activo = 1');
            $valorInventario = (float) $st->fetchColumn();

            salida([
                'ok'     => true,
                'config' => $cfg,
                'hoy'    => [
                    'ventas' => (int) $hoyVenta['n'],
                    'total'  => (float) $hoyVenta['t'],
                ],
                'productos'          => $nProd,
                'valor_inventario'   => redondear($valorInventario),
                'php_version'        => PHP_VERSION,
                'mysql_version'      => (string) $bd->query('SELECT VERSION()')->fetchColumn(),
                'servidor'           => $_SERVER['SERVER_SOFTWARE'] ?? 'desconocido',
            ]);
        }

        /* ============================================================
           PRODUCTOS
           ============================================================ */
        case 'productos': {
            $soloActivos = ((string) p('activos', '')) === '1';
            $buscar = trim((string) p('buscar', ''));
            $cat    = trim((string) p('categoria', ''));

            $sql = 'SELECT * FROM productos WHERE 1 = 1';
            $par = [];
            if ($soloActivos) {
                $sql .= ' AND activo = 1';
            }
            if ($buscar !== '') {
                $sql .= ' AND (nombre LIKE ? OR codigo LIKE ? OR categoria LIKE ?)';
                $like = '%' . $buscar . '%';
                array_push($par, $like, $like, $like);
            }
            if ($cat !== '') {
                $sql .= ' AND categoria = ?';
                $par[] = $cat;
            }
            $sql .= ' ORDER BY nombre ASC';

            $st = $bd->prepare($sql);
            $st->execute($par);
            $lista = array_map('prod', $st->fetchAll());

            salida(['ok' => true, 'productos' => $lista]);
        }

        case 'producto': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT * FROM productos WHERE id = ?');
            $st->execute([$id]);
            $f = $st->fetch();
            if (!$f) {
                salida(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
            }
            $st = $bd->prepare('SELECT * FROM movimientos WHERE producto_id = ? ORDER BY id DESC LIMIT 40');
            $st->execute([$id]);
            salida(['ok' => true, 'producto' => prod($f), 'kardex' => $st->fetchAll()]);
        }

        case 'producto_guardar': {
            $id    = pInt('id', 0);
            $nombre = pTxt('nombre', 120);
            $esNuevo = $id <= 0;

            if ($esNuevo && $nombre === '') {
                salida(['ok' => false, 'error' => 'El nombre del producto es obligatorio.'], 422);
            }
            $codigo    = pTxt('codigo', 40);
            $categoria = pTxt('categoria', 60);
            $precio    = max(0, pNum('precio'));
            $minimo    = max(0, pNum('minimo'));
            $unidad    = pTxt('unidad', 20) ?: 'pieza';
            $foto      = pTxt('foto', 400000);
            $activo    = ((string) p('activo', '1')) === '1' ? 1 : 0;

            // Codigo repetido
            if ($codigo !== '') {
                $st = $bd->prepare('SELECT id FROM productos WHERE codigo = ? AND id <> ?');
                $st->execute([$codigo, $id]);
                if ($st->fetch()) {
                    salida(['ok' => false, 'error' => 'Ese código ya lo tiene otro producto (' . $codigo . ').'], 409);
                }
            }

            $bd->beginTransaction();
            try {
                if ($id > 0) {
                    $st = $bd->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                    $st->execute([$id]);
                    $viejo = $st->fetch();
                    if (!$viejo) {
                        throw new RuntimeException('El producto ya no existe.');
                    }
                    // En una edicion, lo que no se envia se conserva
                    $nombre    = $nombre !== '' ? $nombre : $viejo['nombre'];
                    $categoria = p('categoria') === null ? (string) $viejo['categoria'] : $categoria;
                    $unidad    = p('unidad') === null ? ($viejo['unidad'] ?: 'pieza') : $unidad;
                    $precio    = p('precio') === null ? (float) $viejo['precio'] : $precio;
                    $minimo    = p('minimo') === null ? (float) $viejo['minimo'] : $minimo;
                    $activo    = p('activo') === null ? (int) $viejo['activo'] : $activo;
                    $foto      = p('foto') === null ? (string) $viejo['foto'] : $foto;
                    $stockViejo = (float) $viejo['stock'];
                    $stockNuevo = $stockViejo;
                    if (p('stock') !== null) {
                        $stockNuevo = pNum('stock');
                    }
                    $st = $bd->prepare(
                        'UPDATE productos SET nombre=?, codigo=?, categoria=?, precio=?, stock=?, minimo=?, unidad=?, foto=?, activo=?
                         WHERE id = ?'
                    );
                    $st->execute([$nombre, $codigo ?: null, $categoria ?: null, $precio, $stockNuevo,
                                  $minimo, $unidad, $foto ?: null, $activo, $id]);
                    $dif = redondear($stockNuevo - $stockViejo);
                    if ($dif !== 0.0) {
                        registrarMovimiento([
                            'tipo' => $dif > 0 ? 'entrada' : 'salida',
                            'producto_id' => $id, 'producto_nombre' => $nombre,
                            'cantidad' => $dif, 'stock_anterior' => $stockViejo, 'stock_actual' => $stockNuevo,
                            'referencia' => 'Edición de producto', 'usuario' => nombreUsuario(),
                        ]);
                    }
                } else {
                    $stock = pNum('stock');
                    $st = $bd->prepare(
                        'INSERT INTO productos (nombre,codigo,categoria,precio,stock,minimo,unidad,foto,activo)
                         VALUES (?,?,?,?,?,?,?,?,?)'
                    );
                    $st->execute([$nombre, $codigo ?: null, $categoria ?: null, $precio, $stock,
                                  $minimo, $unidad, $foto ?: null, $activo]);
                    $id = (int) $bd->lastInsertId();
                    registrarMovimiento([
                        'tipo' => 'alta', 'producto_id' => $id, 'producto_nombre' => $nombre,
                        'cantidad' => redondear($stock), 'stock_anterior' => 0, 'stock_actual' => $stock,
                        'referencia' => 'Alta de producto', 'usuario' => nombreUsuario(),
                    ]);
                }
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }

            $st = $bd->prepare('SELECT * FROM productos WHERE id = ?');
            $st->execute([$id]);
            salida(['ok' => true, 'producto' => prod($st->fetch()), 'id' => $id]);
        }

        case 'producto_borrar': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT * FROM productos WHERE id = ?');
            $st->execute([$id]);
            $pr = $st->fetch();
            if (!$pr) {
                salida(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
            }
            $bd->beginTransaction();
            try {
                // Se conservan los items de venta con su nombre historico
                $bd->prepare('UPDATE venta_items SET producto_id = NULL WHERE producto_id = ?')->execute([$id]);
                $bd->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
                registrarMovimiento([
                    'tipo' => 'baja', 'producto_id' => null, 'producto_nombre' => $pr['nombre'],
                    'cantidad' => 0, 'stock_anterior' => (float) $pr['stock'], 'stock_actual' => 0,
                    'referencia' => 'Eliminación de producto', 'usuario' => nombreUsuario(),
                ]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true]);
        }

        /* Ajuste manual de existencias (compra, merma, conteo fisico) */
        case 'stock_mover': {
            $id  = pInt('id');
            $tipo = pTxt('tipo', 20);           // entrada | salida
            $cant = pNum('cantidad');
            $motivo = pTxt('referencia', 80) ?: ($tipo === 'entrada' ? 'Entrada manual' : 'Salida manual');

            if ($id <= 0 || $cant === 0.0) {
                salida(['ok' => false, 'error' => 'Indica el producto y la cantidad.'], 422);
            }
            $delta = ($tipo === 'salida') ? -abs($cant) : abs($cant);

            $bd->beginTransaction();
            try {
                $st = $bd->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                $st->execute([$id]);
                $pr = $st->fetch();
                if (!$pr) {
                    throw new RuntimeException('Producto no encontrado.');
                }
                $antes = (float) $pr['stock'];
                $ahora = redondear($antes + $delta);
                $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?')->execute([$ahora, $id]);
                registrarMovimiento([
                    'tipo' => $delta > 0 ? 'entrada' : 'salida', 'producto_id' => $id,
                    'producto_nombre' => $pr['nombre'], 'cantidad' => $delta,
                    'stock_anterior' => $antes, 'stock_actual' => $ahora,
                    'referencia' => $motivo, 'usuario' => nombreUsuario(),
                ]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true, 'stock' => $ahora]);
        }

        case 'categorias': {
            $st = $bd->query("SELECT DISTINCT categoria FROM productos WHERE categoria <> '' AND categoria IS NOT NULL ORDER BY categoria");
            salida(['ok' => true, 'categorias' => $st->fetchAll(PDO::FETCH_COLUMN)]);
        }

        /* ============================================================
           VENTAS
           ============================================================ */
        case 'venta_crear': {
            $items = p('items', []);
            if (!is_array($items) || count($items) === 0) {
                salida(['ok' => false, 'error' => 'El carrito está vacío.'], 422);
            }
            $descuento = max(0, pNum('descuento'));
            $metodo    = pTxt('metodo', 20) ?: 'Efectivo';
            $recibido  = pNum('recibido', -1);
            $vuelto    = pNum('vuelto', 0);
            $ref       = pTxt('referencia', 60);
            $nota      = pTxt('nota', 200);
            $usuario   = nombreUsuario();

            $bd->beginTransaction();
            try {
                // 1. Calcular
                $subtotal = 0.0;
                $lineas = [];
                foreach ($items as $it) {
                    $pid  = entero($it['id'] ?? 0);
                    $cant = redondear(numero($it['cantidad'] ?? 0));
                    if ($pid <= 0 || $cant <= 0) {
                        continue;
                    }
                    $st = $bd->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                    $st->execute([$pid]);
                    $pr = $st->fetch();
                    if (!$pr) {
                        throw new RuntimeException('Un producto del carrito ya no existe (id ' . $pid . ').');
                    }
                    $precio = redondear($it['precio'] ?? $pr['precio']);
                    if ($precio < 0) {
                        $precio = 0.0;
                    }
                    $importe = redondear($precio * $cant);
                    $subtotal = redondear($subtotal + $importe);
                    $lineas[] = ['p' => $pr, 'cant' => $cant, 'precio' => $precio, 'importe' => $importe];
                }
                if (!$lineas) {
                    throw new RuntimeException('No hay líneas válidas en el carrito.');
                }
                if ($descuento > $subtotal) {
                    $descuento = $subtotal;
                }
                $total = redondear($subtotal - $descuento);

                // 2. Folio
                $st = $bd->query('SELECT COALESCE(MAX(folio),0) FROM ventas');
                $folio = ((int) $st->fetchColumn()) + 1;
                if ($folio < (int) valorConfig('folio', 1)) {
                    $folio = (int) valorConfig('folio', 1);
                }

                // 3. Cabecera
                $st = $bd->prepare(
                    'INSERT INTO ventas (folio,fecha,subtotal,descuento,total,metodo,recibido,vuelto,referencia,nota,usuario)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $st->execute([
                    $folio, date('Y-m-d H:i:s'), $subtotal, $descuento, $total, $metodo,
                    $recibido >= 0 ? $recibido : null, $recibido >= 0 ? $vuelto : null,
                    $ref ?: null, $nota ?: null, $usuario,
                ]);
                $ventaId = (int) $bd->lastInsertId();

                // 4. Items + descuento de existencias + kardex
                $stItem = $bd->prepare(
                    'INSERT INTO venta_items (venta_id,producto_id,nombre,codigo,precio,cantidad,importe)
                     VALUES (?,?,?,?,?,?,?)'
                );
                $stUpd  = $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?');
                $sinStock = [];
                foreach ($lineas as $l) {
                    $pr   = $l['p'];
                    $antes = (float) $pr['stock'];
                    $ahora = redondear($antes - $l['cant']);
                    if ($ahora < 0) {
                        $sinStock[] = $pr['nombre'];
                    }
                    $stItem->execute([$ventaId, (int) $pr['id'], $pr['nombre'], $pr['codigo'],
                                      $l['precio'], $l['cant'], $l['importe']]);
                    $stUpd->execute([$ahora, (int) $pr['id']]);
                    registrarMovimiento([
                        'tipo' => 'venta', 'producto_id' => (int) $pr['id'], 'producto_nombre' => $pr['nombre'],
                        'cantidad' => -$l['cant'], 'stock_anterior' => $antes, 'stock_actual' => $ahora,
                        'referencia' => 'Venta folio ' . $folio, 'usuario' => $usuario,
                    ]);
                }

                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }

            $st = $bd->prepare('SELECT * FROM ventas WHERE id = ?');
            $st->execute([$ventaId]);
            $v = venta($st->fetch());
            $st = $bd->prepare('SELECT * FROM venta_items WHERE venta_id = ? ORDER BY id');
            $st->execute([$ventaId]);
            $v['items'] = array_map(fn($i) => [
                'nombre' => $i['nombre'], 'codigo' => $i['codigo'] ?? '',
                'precio' => (float) $i['precio'], 'cantidad' => (float) $i['cantidad'],
                'importe' => (float) $i['importe'],
            ], $st->fetchAll());

            salida(['ok' => true, 'venta' => $v, 'sin_stock' => $sinStock]);
        }

        case 'venta_anular': {
            $id    = pInt('id');
            $motivo = pTxt('motivo', 200) ?: 'Anulación manual';

            $bd->beginTransaction();
            try {
                $st = $bd->prepare('SELECT * FROM ventas WHERE id = ? FOR UPDATE');
                $st->execute([$id]);
                $v = $st->fetch();
                if (!$v) {
                    throw new RuntimeException('La venta no existe.');
                }
                if ((int) $v['anulada'] === 1) {
                    throw new RuntimeException('Esa venta ya estaba anulada.');
                }
                $st = $bd->prepare('SELECT * FROM venta_items WHERE venta_id = ?');
                $st->execute([$id]);
                $items = $st->fetchAll();

                $stUpd = $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?');
                foreach ($items as $it) {
                    if ($it['producto_id'] === null) {
                        continue;
                    }
                    $pid = (int) $it['producto_id'];
                    $s = $bd->prepare('SELECT stock FROM productos WHERE id = ? FOR UPDATE');
                    $s->execute([$pid]);
                    $fila = $s->fetch();
                    if (!$fila) {
                        continue;
                    }
                    $antes = (float) $fila['stock'];
                    $ahora = redondear($antes + (float) $it['cantidad']);
                    $stUpd->execute([$ahora, $pid]);
                    registrarMovimiento([
                        'tipo' => 'anulacion', 'producto_id' => $pid, 'producto_nombre' => $it['nombre'],
                        'cantidad' => (float) $it['cantidad'], 'stock_anterior' => $antes, 'stock_actual' => $ahora,
                        'referencia' => 'Anulación venta folio ' . (int) $v['folio'], 'usuario' => nombreUsuario(),
                    ]);
                }
                $bd->prepare('UPDATE ventas SET anulada=1, anulada_en=NOW(), motivo=? WHERE id=?')
                   ->execute([$motivo, $id]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true]);
        }

        case 'ventas': {
            $d = desde();
            $h = hasta();
            $buscar = trim((string) p('buscar', ''));
            $soloValidas = ((string) p('validas', '0')) === '1';

            $sql = 'SELECT * FROM ventas WHERE fecha BETWEEN ? AND ?';
            $par = [$d, $h];
            if ($soloValidas) {
                $sql .= ' AND anulada = 0';
            }
            if ($buscar !== '') {
                $sql .= ' AND (folio LIKE ? OR referencia LIKE ? OR nota LIKE ?)';
                $like = '%' . $buscar . '%';
                array_push($par, $like, $like, $like);
            }
            $sql .= ' ORDER BY id DESC LIMIT 500';

            $st = $bd->prepare($sql);
            $st->execute($par);
            $lista = array_map('venta', $st->fetchAll());

            // Totales del rango
            $sql2 = 'SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM ventas
                     WHERE fecha BETWEEN ? AND ? AND anulada = 0';
            $st2 = $bd->prepare($sql2);
            $st2->execute([$d, $h]);
            $tot = $st2->fetch();

            salida([
                'ok'     => true,
                'ventas' => $lista,
                'total'  => (int) $tot['n'],
                'importe' => (float) $tot['t'],
            ]);
        }

        case 'venta': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT * FROM ventas WHERE id = ?');
            $st->execute([$id]);
            $v = $st->fetch();
            if (!$v) {
                salida(['ok' => false, 'error' => 'La venta no existe.'], 404);
            }
            $st = $bd->prepare('SELECT * FROM venta_items WHERE venta_id = ? ORDER BY id');
            $st->execute([$id]);
            $v = venta($v);
            $v['items'] = array_map(fn($i) => [
                'nombre' => $i['nombre'], 'codigo' => $i['codigo'] ?? '',
                'precio' => (float) $i['precio'], 'cantidad' => (float) $i['cantidad'],
                'importe' => (float) $i['importe'],
            ], $st->fetchAll());
            salida(['ok' => true, 'venta' => $v]);
        }

        /* ============================================================
           REPORTES
           ============================================================ */
        case 'reportes': {
            $d = desde();
            $h = hasta();

            $st = $bd->prepare(
                'SELECT COUNT(*) AS ventas, COALESCE(SUM(total),0) AS total,
                        COALESCE(AVG(total),0) AS promedio, COALESCE(SUM(descuento),0) AS descuentos,
                        COALESCE(MAX(total),0) AS mayor
                 FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?'
            );
            $st->execute([$d, $h]);
            $res = $st->fetch();

            $st = $bd->prepare('SELECT COUNT(*) AS anuladas FROM ventas WHERE anulada = 1 AND fecha BETWEEN ? AND ?');
            $st->execute([$d, $h]);
            $anuladas = (int) $st->fetchColumn();

            $st = $bd->prepare('SELECT HOUR(fecha) AS h, COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?
                                GROUP BY HOUR(fecha) ORDER BY h');
            $st->execute([$d, $h]);
            $porHora = array_map(fn($r) => ['h' => (int) $r['h'], 'ventas' => (int) $r['n'], 'total' => (float) $r['t']], $st->fetchAll());

            $st = $bd->prepare(
                'SELECT vi.nombre, SUM(vi.cantidad) AS unidades, SUM(vi.importe) AS vendido, SUM(vi.cantidad) AS c
                 FROM venta_items vi
                 INNER JOIN ventas v ON v.id = vi.venta_id
                 WHERE v.anulada = 0 AND v.fecha BETWEEN ? AND ?
                 GROUP BY vi.nombre ORDER BY c DESC, vi.nombre ASC LIMIT 12'
            );
            $st->execute([$d, $h]);
            $top = array_map(fn($r) => ['nombre' => $r['nombre'], 'unidades' => (float) $r['unidades'], 'vendido' => (float) $r['vendido']], $st->fetchAll());

            $st = $bd->prepare('SELECT metodo, COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?
                                GROUP BY metodo ORDER BY t DESC');
            $st->execute([$d, $h]);
            $porPago = array_map(fn($r) => ['metodo' => $r['metodo'], 'ventas' => (int) $r['n'], 'total' => (float) $r['t']], $st->fetchAll());

            $st = $bd->query('SELECT id, nombre, stock, minimo, unidad, precio
                             FROM productos
                             WHERE activo = 1 AND stock <= GREATEST(minimo, 0)
                             ORDER BY (stock - GREATEST(minimo,0)) ASC, nombre ASC LIMIT 30');
            $faltantes = array_map(fn($r) => [
                'id' => (int) $r['id'], 'nombre' => $r['nombre'],
                'stock' => (float) $r['stock'], 'minimo' => (float) $r['minimo'],
                'unidad' => $r['unidad'], 'precio' => (float) $r['precio'],
            ], $st->fetchAll());

            $st = $bd->query('SELECT COALESCE(SUM(stock*precio),0) AS v, COALESCE(SUM(stock),0) AS u
                             FROM productos WHERE activo = 1');
            $inv = $st->fetch();

            salida([
                'ok'    => true,
                'resumen' => [
                    'ventas'      => (int) $res['ventas'],
                    'total'       => (float) $res['total'],
                    'promedio'    => (float) $res['promedio'],
                    'descuentos'  => (float) $res['descuentos'],
                    'mayor'       => (float) $res['mayor'],
                    'anuladas'    => $anuladas,
                    'inventario'  => (float) $inv['v'],
                    'unidades'    => (float) $inv['u'],
                ],
                'por_hora'  => $porHora,
                'top'       => $top,
                'por_pago'  => $porPago,
                'faltantes' => $faltantes,
            ]);
        }

        case 'kardex': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT * FROM movimientos WHERE producto_id = ? ORDER BY id DESC LIMIT 100');
            $st->execute([$id]);
            salida(['ok' => true, 'movimientos' => $st->fetchAll()]);
        }

        /* ============================================================
           CONFIGURACION
           ============================================================ */
        case 'config_guardar': {
            $permitidas = ['negocio', 'moneda', 'direccion', 'telefono', 'pie', 'logo', 'folio', 'pin', 'tema'];
            $cambios = 0;
            foreach ($permitidas as $clave) {
                if (p($clave) !== null) {
                    $valor = $clave === 'folio' ? (string) max(1, pInt('folio', 1)) : pTxt($clave, 120);
                    guardarConfig($clave, $valor);
                    $cambios++;
                }
            }
            salida(['ok' => true, 'cambios' => $cambios, 'config' => leerConfig()]);
        }

        case 'kiosco_limpiar': {
            $que = pTxt('que', 20);
            $motivo = pTxt('motivo', 120) ?: 'Mantenimiento';

            // Respaldo automatico antes de tocar nada
            $respaldo = '';
            try {
                $respaldo = basename(crearRespaldo('Automatico antes de borrar: ' . $que));
            } catch (Throwable $e) {
                $respaldo = 'no se pudo crear: ' . $e->getMessage();
            }

            $bd->beginTransaction();
            try {
                if ($que === 'ventas') {
                    $bd->exec('DELETE FROM venta_items');
                    $bd->exec('DELETE FROM ventas');
                    $total = (int) $bd->query('SELECT COUNT(*) FROM movimientos')->fetchColumn();
                    guardarConfig('folio', '1');
                } elseif ($que === 'productos') {
                    $bd->exec('UPDATE venta_items SET producto_id = NULL');
                    $bd->exec('DELETE FROM productos');
                    $total = (int) $bd->query('SELECT COUNT(*) FROM movimientos')->fetchColumn();
                } elseif ($que === 'kardex') {
                    $bd->exec('DELETE FROM movimientos');
                    $total = 0;
                } else {
                    throw new RuntimeException('Opción no reconocida.');
                }
                registrarMovimiento([
                    'tipo' => 'sistema', 'producto_id' => null,
                    'producto_nombre' => 'Mantenimiento', 'cantidad' => 0,
                    'stock_anterior' => 0, 'stock_actual' => 0,
                    'referencia' => 'Borrado: ' . $que . ' (' . $total . ' movimientos previos)',
                    'usuario' => nombreUsuario(),
                ]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true, 'respaldo' => $respaldo]);
        }

        default:
            salida(['ok' => false, 'error' => 'Acción no reconocida: ' . $accion], 400);
    }
} catch (Throwable $e) {
    if ($bd->inTransaction()) {
        $bd->rollBack();
    }
    salida(['ok' => false, 'error' => $e->getMessage()], 500);
}
