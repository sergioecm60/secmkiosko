<?php
/**
 * Kiosco — API JSON.
 * Uso:  api.php?accion=nombre      (GET)  o   api.php?accion=nombre  (POST, cuerpo JSON)
 */
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/sesion.php';

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
    $precio = (float) $f['precio'];
    $costo  = (float) ($f['costo'] ?? 0);
    $margen = ($precio > 0) ? (($precio - $costo) / $precio) * 100 : 0.0;

    return [
        'id'        => (int) $f['id'],
        'nombre'    => $f['nombre'],
        'codigo'    => $f['codigo'] ?? '',
        'categoria' => $f['categoria'] ?? '',
        'precio'    => $precio,
        'costo'     => $costo,
        'margen'    => redondear($margen),
        'utilidad'  => redondear($precio - $costo),
        'observaciones' => $f['observaciones'] ?? '',
        'proveedor_id'   => isset($f['proveedor_id']) && $f['proveedor_id'] !== null ? (int) $f['proveedor_id'] : 0,
        'proveedor'      => $f['proveedor'] ?? '',
        'stock'     => (float) $f['stock'],
        'unidad'         => $f['unidad'] ?: 'pieza',
        'formatos_compra' => formatosProducto((int) $f['id'], 'compra'),
        'formatos_venta'  => formatosProducto((int) $f['id'], 'venta', $costo, $precio),
        'minimo'    => (float) $f['minimo'],
        'sin_stock' => (int) ($f['sin_stock'] ?? 0) === 1,
        'foto'      => $f['foto'] ?? '',
        'activo'    => (int) $f['activo'] === 1,
        'creado'    => $f['creado'] ?? null,
    ];
}

/** Medios de pago activos, ordenados. */
function mediosPago(bool $soloActivos = true): array
{
    $sql = 'SELECT `id`,`nombre`,`icono`,`exige_referencia`,`es_efectivo`,`orden` FROM `medios_pago`';
    if ($soloActivos) {
        $sql .= ' WHERE activo = 1';
    }
    $sql .= ' ORDER BY `orden`, `nombre`';
    return array_map(fn($m) => [
        'id'        => (int) $m['id'],
        'nombre'    => $m['nombre'],
        'icono'     => $m['icono'] ?: '💳',
        'referencia' => (int) $m['exige_referencia'] === 1,
        'efectivo'  => (int) $m['es_efectivo'] === 1,
    ], pdoBd()->query($sql)->fetchAll());
}

/**
 * Reemplaza por completo la lista de formatos de un ambito.
 * Cada fila: { unidad, factor, precio, margen, predet }.
 * Las que vengan sin unidad se ignoran; si no llega la lista, no se toca nada
 * (asi una edicion parcial no borra lo que ya estaba).
 */
function guardarFormatos(PDO $bd, int $productoId, $lista, string $ambito): void
{
    if ($lista === null || !is_array($lista)) {
        return;
    }
    $bd->prepare('DELETE FROM productos_formatos WHERE producto_id = ? AND ambito = ?')
       ->execute([$productoId, $ambito]);

    $ins = $bd->prepare('INSERT INTO productos_formatos (producto_id, ambito, unidad, factor, precio, margen, predet)
                         VALUES (?, ?, ?, ?, ?, ?, ?)');

    // Solo un formato queda como predeterminado: si el cliente marco varios,
    // gana el ultimo. Si no marco ninguno, se usa el primero.
    $cuantas = 0;
    foreach ($lista as $f) {
        if (is_array($f) && !empty($f['predet'])) {
            $cuantas++;
        }
    }
    $indicePredet = -1;
    if ($cuantas > 0) {
        $vistos = 0;
        foreach ($lista as $i => $f) {
            if (is_array($f) && !empty($f['predet'])) {
                $indicePredet = $i;
            }
        }
    }

    $primerVálido = -1;
    foreach ($lista as $i => $f) {
        if (!is_array($f) || texto($f['unidad'] ?? '', 20) === '') {
            continue;
        }
        if ($primerVálido < 0) {
            $primerVálido = $i;
        }
    }
    if ($indicePredet < 0) {
        $indicePredet = $primerVálido;
    }

    foreach ($lista as $i => $f) {
        if (!is_array($f)) {
            continue;
        }
        $unidad = texto($f['unidad'] ?? '', 20);
        if ($unidad === '') {
            continue;
        }
        $factor = isset($f['factor']) && is_numeric($f['factor']) ? (float) $f['factor'] : 1.0;
        if ($factor <= 0) {
            $factor = 1.0;
        }
        $precio = isset($f['precio']) && $f['precio'] !== '' && is_numeric($f['precio'])
            ? redondear((float) $f['precio']) : null;
        $margen = isset($f['margen']) && $f['margen'] !== '' && is_numeric($f['margen'])
            ? (float) $f['margen'] : null;
        $ins->execute([$productoId, $ambito, $unidad, $factor, $precio, $margen, $i === $indicePredet ? 1 : 0]);
    }
    return;
    // Si ninguna quedo marcada como predeterminada, la primera es la de uso.
    if ($primero) {
        $st = $bd->prepare('SELECT id FROM productos_formatos WHERE producto_id = ? AND ambito = ?
                             ORDER BY factor ASC, id ASC LIMIT 1');
        $st->execute([$productoId, $ambito]);
        if ($fila = $st->fetch()) {
            $bd->prepare('UPDATE productos_formatos SET predet = 1 WHERE id = ?')->execute([(int) $fila['id']]);
        }
    }
}

/**
 * Costo por unidad base del producto, tomado del formato de compra
 * predeterminado. Es lo que despues usa el % de margen de los formatos de
 * venta para sugerir precios.
 */
function costoProducto(PDO $bd, int $productoId): float
{
    $st = $bd->prepare('SELECT costo FROM productos WHERE id = ?');
    $st->execute([$productoId]);
    return (float) ($st->fetchColumn() ?: 0);
}

/**
 * Busca un formato por nombre y cantidad. Los ids de formato cambian cada vez
 * que se guarda el producto, asi que un carrito armado antes de un cambio
 * se resuelve igual por "docena x 12" o "maple x 30".
 */
function formatoPorNombre(PDO $bd, int $productoId, string $ambito, string $unidad, ?float $factor): ?array
{
    $unidad = trim($unidad);
    if ($unidad === '') {
        return null;
    }
    $st = $bd->prepare('SELECT * FROM productos_formatos
                         WHERE producto_id = ? AND ambito = ? AND LOWER(unidad) = LOWER(?)');
    $st->execute([$productoId, $ambito, $unidad]);
    $candidatos = $st->fetchAll();
    if (!$candidatos) {
        return null;
    }
    if ($factor !== null && $factor > 0) {
        foreach ($candidatos as $f) {
            if (abs((float) $f['factor'] - $factor) < 0.0001) {
                return $f;
            }
        }
    }
    return $candidatos[0];
}

function costoBaseDe(PDO $bd, int $productoId): ?float
{
    $st = $bd->prepare('SELECT unidad, factor, precio FROM productos_formatos
                         WHERE producto_id = ? AND ambito = "compra" AND precio IS NOT NULL AND precio > 0
                      ORDER BY predet DESC, id ASC LIMIT 1');
    $st->execute([$productoId]);
    $f = $st->fetch();
    if (!$f) {
        return null;
    }
    $factor = (float) $f['factor'] > 0 ? (float) $f['factor'] : 1.0;
    return redondear((float) $f['precio'] / $factor);
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
        'envio'      => (float) ($f['envio'] ?? 0),
        'metodo'     => $f['metodo'],
        'recibido'   => $f['recibido'] === null ? null : (float) $f['recibido'],
        'vuelto'     => $f['vuelto'] === null ? null : (float) $f['vuelto'],
        'referencia' => $f['referencia'] ?? '',
        'nota'       => $f['nota'] ?? '',
        'anulada'    => (int) $f['anulada'] === 1,
        'motivo'     => $f['motivo'] ?? '',
    ];
}

/**
 * Detalle de una venta con la unidad del producto, para poder mostrar
 * "250 g" en vez de "0,25 pieza" en la pantalla y en el ticket.
 */
function itemsDe(PDO $bd, int $ventaId): array
{
    $st = $bd->prepare(
        'SELECT vi.nombre, vi.codigo, vi.precio, vi.cantidad, vi.importe,
                vi.formato_unidad, vi.formato_cantidad,
                COALESCE(pr.unidad, "pieza") AS unidad
         FROM venta_items vi
         LEFT JOIN productos pr ON pr.id = vi.producto_id
         WHERE vi.venta_id = ? ORDER BY vi.id'
    );
    $st->execute([$ventaId]);
    return array_map(fn($i) => [
        'nombre'   => $i['nombre'],
        'codigo'   => $i['codigo'] ?? '',
        'precio'   => (float) $i['precio'],
        'cantidad' => (float) $i['cantidad'],
        'importe'  => (float) $i['importe'],
        'unidad'   => $i['unidad'] ?: 'pieza',
        'formato'      => $i['formato_unidad'] ?: null,
        'cant_formato' => $i['formato_unidad'] !== null ? (float) $i['formato_cantidad'] : null,
    ], $st->fetchAll());
}

/* ---------------------------------------------------------------
   Comandas de cocina
   --------------------------------------------------------------- */

/** Estados por los que pasa un pedido, en orden. */
const ESTADOS_COMANDA = ['pendiente', 'preparando', 'listo', 'entregado', 'cancelado'];

/**
 * Guarda la comanda y sus lineas. Se llama DENTRO de la transaccion de la
 * venta, asi que si algo falla se cae la venta entera: nunca queda una
 * comanda de un pedido que no se cobro.
 *
 * Cada linea puede venir de un atajo con producto_id (esa ya se cobro como
 * parte del carrito) o ser solo texto para la cocina.
 */
function guardarComanda(PDO $bd, array $d, int $ventaId, int $folio, float $total, string $usuario, float $envio = 0.0): int
{
    $cliente = trim((string) ($d['cliente'] ?? ''));
    if ($cliente === '') {
        throw new RuntimeException('La comanda necesita el nombre del cliente.');
    }
    $items = $d['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        throw new RuntimeException('La comanda no tiene nada que preparar.');
    }

    $tipo = in_array($d['tipo'] ?? '', ['delivery', 'retiro', 'mesa'], true) ? (string) $d['tipo'] : 'delivery';
    $zonaId = entero($d['zona_id'] ?? 0);
    $zonaNombre = trim((string) ($d['zona_nombre'] ?? ''));
    $costoZona = 0.0;
    if ($zonaId <= 0) {
        $zonaId = null;
        $zonaNombre = '';
    } else {
        // El nombre y el costo salen de la tabla, no de lo que manda el
        // navegador: el precio del reparto no se negocia desde el cliente.
        $stZ = $bd->prepare('SELECT nombre, costo FROM zonas WHERE id = ? AND activo = 1');
        $stZ->execute([$zonaId]);
        $z = $stZ->fetch();
        if (!$z) {
            throw new RuntimeException('La zona elegida ya no existe.');
        }
        $zonaNombre = (string) $z['nombre'];
        $costoZona = (float) $z['costo'];
    }
    // El envio solo suma si el pedido es a domicilio.
    $envio = $tipo === 'delivery' ? $costoZona : 0.0;

    $st = $bd->prepare(
        'INSERT INTO comandas (venta_id,folio,cliente,telefono,direccion,zona_id,zona_nombre,
                                tipo,lugar,notas,estado,total,envio,usuario)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $ventaId, $folio, mb_substr($cliente, 0, 80),
        trim((string) ($d['telefono'] ?? '')) ?: null,
        trim((string) ($d['direccion'] ?? '')) ?: null,
        $zonaId, $zonaNombre ?: null,
        $tipo, trim((string) ($d['lugar'] ?? '')) ?: null,
        trim((string) ($d['notas'] ?? '')) ?: null,
        'pendiente', $total, $envio, $usuario,
    ]);
    $comandaId = (int) $bd->lastInsertId();

    $stIt = $bd->prepare(
        'INSERT INTO comanda_items (comanda_id,producto_id,cantidad,texto,detalle,es_producto)
         VALUES (?,?,?,?,?,?)'
    );
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $texto = trim((string) ($it['texto'] ?? ''));
        if ($texto === '') {
            continue;
        }
        $pid = entero($it['producto_id'] ?? 0);
        $cant = redondear(numero($it['cantidad'] ?? 1));
        if ($cant <= 0) {
            $cant = 1.0;
        }
        $detalle = trim((string) ($it['detalle'] ?? ''));
        $stIt->execute([$comandaId, $pid > 0 ? $pid : null, $cant,
                        mb_substr($texto, 0, 160), $detalle ?: null, $pid > 0 ? 1 : 0]);
    }

    $n = (int) $bd->query('SELECT COUNT(*) FROM comanda_items WHERE comanda_id = ' . $comandaId)->fetchColumn();
    if ($n === 0) {
        throw new RuntimeException('La comanda no tiene nada que preparar.');
    }
    return $comandaId;
}

/** Devuelve una comanda con sus lineas, en el formato que espera la cocina. */
function comandaCompleta(PDO $bd, int $id): ?array
{
    $st = $bd->prepare('SELECT * FROM comandas WHERE id = ?');
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) {
        return null;
    }
    $stI = $bd->prepare(
        'SELECT ci.*, COALESCE(p.nombre, "") AS producto_nombre
         FROM comanda_items ci
         LEFT JOIN productos p ON p.id = ci.producto_id
         WHERE ci.comanda_id = ? ORDER BY ci.id'
    );
    $stI->execute([$id]);
    $c['items'] = array_map(fn($i) => [
        'id'        => (int) $i['id'],
        'producto_id' => $i['producto_id'] !== null ? (int) $i['producto_id'] : null,
        'cantidad'  => (float) $i['cantidad'],
        'texto'     => (string) $i['texto'],
        'detalle'   => $i['detalle'] ?: null,
        'es_producto' => (int) $i['es_producto'] === 1,
        'estado'    => (string) $i['estado'],
    ], $stI->fetchAll());
    $c['id']     = (int) $c['id'];
    $c['folio']  = $c['folio'] !== null ? (int) $c['folio'] : null;
    $c['total']  = (float) $c['total'];
    $c['envio']  = (float) ($c['envio'] ?? 0);
    $c['zona_id'] = $c['zona_id'] !== null ? (int) $c['zona_id'] : 0;
    $c['es_producto'] = 0;
    return $c;
}

/* ---------- Arranque ---------- */
if (!instalado()) {
    salida(['ok' => false, 'error' => 'El sistema no esta instalado. Abre instalar.php', 'instalar' => true], 503);
}

$accion = (string) ($_GET['accion'] ?? '');
$bd     = pdoBd();

/* ---------- Permisos por acción ----------
   publico      : no hace falta entrar
   autenticado : cualquier usuario con sesión (admin, vendedor o cocina)
   venta        : sólo quien cobra (admin o vendedor) — la cocina no
   admin        : sólo el administrador
 ------------------------------------------------ */
const ROL_PUBLICO      = 'publico';
const ROL_AUTENTICADO = 'autenticado';
const ROL_VENTA        = 'venta';
const ROL_ADMIN       = 'admin';

$permisos = [
    // Público
    'ping'              => ROL_PUBLICO,
    'sesion_info'       => ROL_PUBLICO,
    'login'             => ROL_PUBLICO,

    // Cualquiera que haya entrado
    'estado'            => ROL_AUTENTICADO,
    'categorias'        => ROL_AUTENTICADO,
    'medios_pago'       => ROL_AUTENTICADO,
    'productos'         => ROL_AUTENTICADO,
    'producto'          => ROL_AUTENTICADO,
    'proveedores'       => ROL_AUTENTICADO,
    'kardex'            => ROL_AUTENTICADO,
    'ventas'            => ROL_VENTA,
    'venta'             => ROL_VENTA,
    'reportes'          => ROL_VENTA,
    'venta_crear'       => ROL_VENTA,
    'venta_anular'      => ROL_VENTA,   // el vendedor sólo con su caja abierta
    'caja_abrir'        => ROL_VENTA,
    'caja_cerra'        => ROL_VENTA,
    'cajas_mias'        => ROL_VENTA,
    'clave_cambiar'     => ROL_AUTENTICADO,

    // Comandas: las ve y las mueve cualquiera (incluido el de cocina),
    // pero crearlas es parte del cobro, asi que es ROL_VENTA.
    'comandas'          => ROL_AUTENTICADO,
    'comanda'           => ROL_AUTENTICADO,
    'comanda_estado'    => ROL_AUTENTICADO,
    'comanda_item'      => ROL_AUTENTICADO,
    'comanda_atajos'    => ROL_AUTENTICADO,
    'zonas'             => ROL_AUTENTICADO,
    'atajo_guardar'     => ROL_ADMIN,
    'atajo_borrar'      => ROL_ADMIN,
    'zona_guardar'      => ROL_ADMIN,
    'zona_borrar'       => ROL_ADMIN,

    // Sólo administrador
    'config_guardar'    => ROL_ADMIN,
    'producto_guardar'  => ROL_ADMIN,
    'producto_borrar'   => ROL_ADMIN,
    'stock_mover'       => ROL_ADMIN,
    'proveedor_guardar' => ROL_ADMIN,
    'proveedor_borrar'  => ROL_ADMIN,
    'medio_pago_guardar'=> ROL_ADMIN,
    'medio_pago_borrar' => ROL_ADMIN,
    'kiosco_limpiar'    => ROL_ADMIN,
    'usuarios'          => ROL_ADMIN,
    'usuario_guardar'   => ROL_ADMIN,
    'usuario_borrar'    => ROL_ADMIN,
    'cajas_todas'       => ROL_VENTA,   // el vendedor sólo recibe las suyas (filtro abajo)
    'caja_detalle'      => ROL_VENTA,   // el vendedor sólo las propias (se chequea abajo)
];

$requerido = $permisos[$accion] ?? ROL_AUTENTICADO;

if ($requerido !== ROL_PUBLICO) {
    exigirSesion(function () {
        salida(['ok' => false, 'error' => 'Tu sesión se venció. Volvé a entrar.', 'sesion' => true], 401);
    });
    if ($requerido === ROL_ADMIN && !esAdmin()) {
        salida([
            'ok'     => false,
            'error'  => 'Sólo el administrador puede hacer esto.',
            'permiso' => true,
        ], 403);
    }
    // La cocina prepara pedidos pero no cobra: no entra al punto de venta.
    if ($requerido === ROL_VENTA && !puedeVender()) {
        salida([
            'ok'      => false,
            'error'   => 'Tu rol no puede cobrar ni manejar la caja.',
            'permiso' => true,
        ], 403);
    }
    // Mientras la clave sea la de fábrica no se opera el kiosco.
    $u = usuarioActual();
    if ($u && (int) $u['debe_cambiar_clave'] === 1 && !in_array($accion, ['clave_cambiar', 'sesion_info'], true)) {
        salida([
            'ok'    => false,
            'error' => 'Tenés que cambiar la clave antes de empezar a vender.',
            'clave' => true,
        ], 428);
    }
}

try {
    switch ($accion) {

        /* ============================================================
           ESTADO GENERAL
           ============================================================ */
        case 'estado': {
            $cfg = leerConfig();
            $yo  = usuarioActual();
            $miCaja = $yo ? cajaDeUsuario((int) $yo['id']) : null;

            $st = $bd->prepare('SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND DATE(fecha) = CURDATE()');
            $st->execute();
            $hoyVenta = $st->fetch();

            $st = $bd->query('SELECT COUNT(*) AS n FROM productos WHERE activo = 1');
            $nProd = (int) $st->fetchColumn();

            $st = $bd->query('SELECT COALESCE(SUM(stock * precio),0) AS v, COALESCE(SUM(stock * costo),0) AS c
                             FROM productos WHERE activo = 1');
            $inv = $st->fetch();

            salida([
                'ok'     => true,
                'config' => $cfg,
                'hoy'    => [
                    'ventas' => (int) $hoyVenta['n'],
                    'total'  => (float) $hoyVenta['t'],
                ],
                'productos'          => $nProd,
                'valor_inventario'   => redondear((float) $inv['v']),
                'costo_inventario'   => redondear((float) $inv['c']),
                'medios_pago'        => mediosPago(),
                'usuario'            => $yo ? [
                    'id'       => (int) $yo['id'],
                    'usuario'  => $yo['usuario'],
                    'nombre'   => $yo['nombre'],
                    'rol'      => $yo['rol'],
                    'es_admin' => $yo['rol'] === 'admin',
                ] : null,
                'caja'               => cajaResumen($miCaja),
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

            $sql = 'SELECT pr.*, pv.`nombre` AS proveedor
                    FROM productos pr
                    LEFT JOIN proveedores pv ON pv.id = pr.proveedor_id
                    WHERE 1 = 1';
            $par = [];
            if ($soloActivos) {
                $sql .= ' AND pr.activo = 1';
            }
            if ($buscar !== '') {
                $sql .= ' AND (pr.nombre LIKE ? OR pr.codigo LIKE ? OR pr.categoria LIKE ?)';
                $like = '%' . $buscar . '%';
                array_push($par, $like, $like, $like);
            }
            if ($cat !== '') {
                $sql .= ' AND pr.categoria = ?';
                $par[] = $cat;
            }
            $sql .= ' ORDER BY pr.nombre ASC';

            $st = $bd->prepare($sql);
            $st->execute($par);
            $lista = array_map('prod', $st->fetchAll());

            salida(['ok' => true, 'productos' => $lista]);
        }

        case 'producto': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT pr.*, pv.`nombre` AS proveedor
                                FROM productos pr
                                LEFT JOIN proveedores pv ON pv.id = pr.proveedor_id
                                WHERE pr.id = ?');
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
            $costo     = p('costo') === null ? 0.0 : max(0, pNum('costo'));
            $obs       = pTxt('observaciones', 500);
            $provId    = pInt('proveedor_id', 0);
            // Venta espontanea: el producto se carga en el momento y se cobra,
            // pero no sale de un stock que se cuente (ver seccion 9 del esquema).
            $sinStock  = ((string) p('sin_stock', '0')) === '1' ? 1 : 0;

            // Compra en multiplos: maple, cajon, bolsa, docena...
            $uCompra    = pTxt('unidad_compra', 20);
            $factorCompra = p('factor_compra') === null ? 1.0 : max(0.001, pNum('factor_compra', 1));
            $pCompra    = p('precio_compra') === null || pNum('precio_compra') <= 0
                            ? null : redondear(pNum('precio_compra'));
            $presetTxt  = pTxt('presets', 120) ?: null;

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
                    $costo     = p('costo') === null ? (float) $viejo['costo'] : $costo;
                    $obs       = p('observaciones') === null ? (string) $viejo['observaciones'] : $obs;
                    $provId    = p('proveedor_id') === null ? (int) $viejo['proveedor_id'] : $provId;
                    if (p('sin_stock') === null) { $sinStock = (int) ($viejo['sin_stock'] ?? 0); }
                    if (p('unidad_compra') === null) { $uCompra = (string) ($viejo['unidad_compra'] ?? ''); }
                    if (p('factor_compra') === null) { $factorCompra = (float) ($viejo['factor_compra'] ?? 1); }
                    if (p('precio_compra') === null) {
                        $pCompra = isset($viejo['precio_compra']) && $viejo['precio_compra'] !== null
                            ? (float) $viejo['precio_compra'] : null;
                    }
                    if (p('presets') === null) { $presetTxt = $viejo['presets'] ?? null; }
                    $stockViejo = (float) $viejo['stock'];
                    $stockNuevo = $stockViejo;
                    if (p('stock') !== null) {
                        $stockNuevo = pNum('stock');
                    }
                    $st = $bd->prepare(
                        'UPDATE productos SET nombre=?, codigo=?, categoria=?, precio=?, costo=?, stock=?, minimo=?,
                                             unidad=?, foto=?, sin_stock=?, activo=?, observaciones=?, proveedor_id=?,
                                             unidad_compra=?, factor_compra=?, precio_compra=?, presets=?
                         WHERE id = ?'
                    );
                    $st->execute([$nombre, $codigo ?: null, $categoria ?: null, $precio, $costo, $stockNuevo,
                                  $minimo, $unidad, $foto ?: null, $sinStock, $activo, $obs ?: null, $provId ?: null,
                                  $uCompra ?: null, $factorCompra, $pCompra, $presetTxt, $id]);
                    $dif = redondear($stockNuevo - $stockViejo);
                    if ($dif !== 0.0 && !$sinStock) {
                        registrarMovimiento([
                            'tipo' => $dif > 0 ? 'entrada' : 'salida',
                            'producto_id' => $id, 'producto_nombre' => $nombre,
                            'cantidad' => $dif, 'stock_anterior' => $stockViejo, 'stock_actual' => $stockNuevo,
                            'referencia' => 'Edición de producto', 'usuario' => nombreUsuario(),
                        ]);
                    }
                } else {
                    $stock = $sinStock ? 0.0 : pNum('stock');
                    $st = $bd->prepare(
                        'INSERT INTO productos (nombre,codigo,categoria,precio,costo,stock,minimo,unidad,foto,sin_stock,activo,observaciones,proveedor_id,
                                                unidad_compra,factor_compra,precio_compra,presets)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $st->execute([$nombre, $codigo ?: null, $categoria ?: null, $precio, $costo, $stock,
                                  $minimo, $unidad, $foto ?: null, $sinStock, $activo, $obs ?: null, $provId ?: null,
                                  $uCompra ?: null, $factorCompra, $pCompra, $presetTxt]);
                    $id = (int) $bd->lastInsertId();
                    // Un producto de venta libre no tiene existencias: no se
                    // inventa un movimiento de alta con stock 0.
                    if (!$sinStock) {
                        registrarMovimiento([
                            'tipo' => 'alta', 'producto_id' => $id, 'producto_nombre' => $nombre,
                            'cantidad' => redondear($stock), 'stock_anterior' => 0, 'stock_actual' => $stock,
                            'referencia' => 'Alta de producto', 'usuario' => nombreUsuario(),
                        ]);
                    }
                }
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }

            guardarFormatos($bd, $id, p('formatos_compra'), 'compra');
            guardarFormatos($bd, $id, p('formatos_venta'), 'venta');

            // El costo del producto sale del formato de compra predeterminado:
            // maple de $5.000 entre 30 huevos = $166,67 por huevo.
            $costoBase = costoBaseDe($bd, $id);
            if ($costoBase !== null) {
                $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoBase, $id]);
            }

            // El precio del producto es el de UNA unidad base y sale del
            // formato de venta predeterminado. Asi un formato cargado con
            // "% sobre costo" no deja el producto en precio 0, y una caja
            // de 24 a $26.000 deja el puré en $1.083,33 y no en $26.000.
            $stV = $bd->prepare('SELECT precio, margen, factor FROM productos_formatos
                                  WHERE producto_id = ? AND ambito = "venta"
                               ORDER BY predet DESC, factor ASC, id ASC LIMIT 1');
            $stV->execute([$id]);
            if ($fv = $stV->fetch()) {
                $precioUnidad = precioVentaDe([
                    'precio' => $fv['precio'] !== null ? (float) $fv['precio'] : null,
                    'margen' => $fv['margen'] !== null ? (float) $fv['margen'] : null,
                    'factor' => (float) $fv['factor'],
                ], $costoBase ?? costoProducto($bd, $id));
                $factor = (float) $fv['factor'] > 0 ? (float) $fv['factor'] : 1.0;
                $porUnidad = redondear($precioUnidad / $factor);
                if ($porUnidad > 0) {
                    $bd->prepare('UPDATE productos SET precio = ? WHERE id = ?')->execute([$porUnidad, $id]);
                }
            }

            $st = $bd->prepare('SELECT pr.*, pv.`nombre` AS proveedor
                            FROM productos pr LEFT JOIN proveedores pv ON pv.id = pr.proveedor_id
                            WHERE pr.id = ?');
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
            $provId   = pInt('proveedor_id', 0);
            $documento = pTxt('documento', 40);
            $formatoId = pInt('formato_id', 0);

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
                // Los productos de venta libre no llevan control de existencias.
                if ((int) ($pr['sin_stock'] ?? 0) === 1) {
                    throw new RuntimeException('Este producto se vende sin stock, no se le pueden registrar movimientos.');
                }
                $antes = (float) $pr['stock'];

                // Si se eligio un formato de compra (maple, cajon, bolsa, caja),
                // la cantidad viene en esa presentacion y se pasa a unidad base.
                $factor = 1.0;
                $formato = null;
                if ($formatoId > 0) {
                    $stF = $bd->prepare('SELECT * FROM productos_formatos
                                         WHERE id = ? AND producto_id = ? AND ambito = "compra"');
                    $stF->execute([$formatoId, $id]);
                    $formato = $stF->fetch();
                    if (!$formato) {
                        $formato = formatoPorNombre($bd, $id, 'compra',
                            (string) pTxt('formato_unidad', 20),
                            p('formato_factor') !== null ? pNum('formato_factor') : null);
                    }
                    if (!$formato) {
                        throw new RuntimeException('Ese formato de compra no es del producto.');
                    }
                    $f = (float) $formato['factor'];
                    if ($f > 0) { $factor = $f; }
                } elseif (p('usar_unidad_compra') !== null && $delta > 0) {
                    $f = (float) ($pr['factor_compra'] ?? 1);
                    if ($f > 0) { $factor = $f; }
                }
                $delta = redondear($delta * $factor);
                $ahora = redondear($antes + $delta);

                // Al comprar de un proveedor, se actualiza el costo del producto
                $costoNuevo = (float) ($pr['costo'] ?? 0);
                if ($delta > 0 && $provId > 0 && p('costo_unitario') !== null && pNum('costo_unitario') > 0) {
                    $costoNuevo = pNum('costo_unitario');
                    $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoNuevo, $id]);
                }

                // El precio del formato de compra define el costo por unidad
                // base: si el maple de 30 sale $5.000, el huevo queda $166,67.
                // El precio enviado manda sobre el del formato, asi una compra
                // a otro precio actualiza el costo sin tocar el catalogo.
                $precioFmt = null;
                if ($delta > 0 && $formato !== null) {
                    if (p('precio_formato') !== null && pNum('precio_formato') > 0) {
                        $precioFmt = pNum('precio_formato');
                    } elseif ($formato['precio'] !== null && (float) $formato['precio'] > 0) {
                        $precioFmt = (float) $formato['precio'];
                    }
                }
                if ($precioFmt !== null && $factor > 0) {
                    $costoNuevo = redondear($precioFmt / $factor);
                    $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoNuevo, $id]);
                } elseif ($delta > 0 && p('precio_compra') !== null && pNum('precio_compra') > 0 && $factor > 0) {
                    $costoNuevo = redondear(pNum('precio_compra') / $factor);
                    $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoNuevo, $id]);
                }

                $unidadFmt = $formato !== null ? (string) $formato['unidad'] : null;
                $cantidadFmt = $formato !== null ? abs($cant) : null;
                $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?')->execute([$ahora, $id]);
                registrarMovimiento([
                    'tipo' => $delta > 0 ? 'entrada' : 'salida', 'producto_id' => $id,
                    'producto_nombre' => $pr['nombre'], 'cantidad' => $delta,
                    'stock_anterior' => $antes, 'stock_actual' => $ahora,
                    'referencia' => $motivo, 'usuario' => nombreUsuario(),
                    'proveedor_id' => $provId ?: null, 'documento' => $documento ?: null,
                    'nota' => $unidadFmt !== null
                        ? ('Formato: ' . $unidadFmt . ' x ' . rtrim(rtrim(number_format((float) $cantidadFmt, 4, '.', ''), '0'), '.'))
                        : null,
                ]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true, 'stock' => $ahora, 'costo' => $costoNuevo]);
        }

        case 'categorias': {
            $st = $bd->query("SELECT DISTINCT categoria FROM productos WHERE categoria <> '' AND categoria IS NOT NULL ORDER BY categoria");
            salida(['ok' => true, 'categorias' => $st->fetchAll(PDO::FETCH_COLUMN)]);
        }

        /* ============================================================
           MEDIOS DE PAGO (configurables)
           ============================================================ */
        case 'medios_pago': {
            salida(['ok' => true, 'medios' => mediosPago(((string) p('todos', '')) === '1')]);
        }

        case 'medio_pago_guardar': {
            $id   = pInt('id', 0);
            $nombre = pTxt('nombre', 40);
            if ($nombre === '') {
                salida(['ok' => false, 'error' => 'El nombre del medio de pago es obligatorio.'], 422);
            }
            $icono   = pTxt('icono', 8) ?: '💳';
            $refReq  = ((string) p('referencia', '0')) === '1' ? 1 : 0;
            $esEfec  = ((string) p('efectivo', '0')) === '1' ? 1 : 0;
            $activo  = p('activo') === null ? 1 : (((string) p('activo', '1')) === '1' ? 1 : 0);
            $orden   = pInt('orden', 99);

            $st = $bd->prepare('SELECT id FROM medios_pago WHERE LOWER(nombre) = LOWER(?) AND id <> ?');
            $st->execute([$nombre, $id]);
            if ($st->fetch()) {
                salida(['ok' => false, 'error' => 'Ya existe un medio de pago con ese nombre.'], 409);
            }

            if ($id > 0) {
                $bd->prepare('UPDATE medios_pago SET nombre=?, icono=?, exige_referencia=?, es_efectivo=?, activo=?, orden=? WHERE id=?')
                   ->execute([$nombre, $icono, $refReq, $esEfec, $activo, $orden, $id]);
            } else {
                $bd->prepare('INSERT INTO medios_pago (nombre,icono,exige_referencia,es_efectivo,activo,orden) VALUES (?,?,?,?,?,?)')
                   ->execute([$nombre, $icono, $refReq, $esEfec, $activo, $orden]);
                $id = (int) $bd->lastInsertId();
            }
            salida(['ok' => true, 'id' => $id, 'medios' => mediosPago(false)]);
        }

        case 'medio_pago_borrar': {
            $id = pInt('id');
            $uso = (int) $bd->query('SELECT COUNT(*) FROM ventas WHERE metodo = (SELECT nombre FROM medios_pago WHERE id = ' . $id . ')')
                      ->fetchColumn();
            if ($uso > 0) {
                // No se borra algo que ya se uso: se desactiva
                $bd->prepare('UPDATE medios_pago SET activo = 0 WHERE id = ?')->execute([$id]);
                salida(['ok' => true, 'desactivado' => true, 'uso' => $uso]);
            }
            $bd->prepare('DELETE FROM medios_pago WHERE id = ?')->execute([$id]);
            salida(['ok' => true, 'desactivado' => false, 'uso' => 0]);
        }

        /* ============================================================
           PROVEEDORES
           ============================================================ */
        case 'proveedores': {
            $st = $bd->query(
                'SELECT pv.*, (SELECT COUNT(*) FROM productos WHERE proveedor_id = pv.id) AS articulos
                 FROM proveedores pv ORDER BY pv.nombre'
            );
            salida(['ok' => true, 'proveedores' => array_map(fn($f) => [
                'id' => (int) $f['id'], 'nombre' => $f['nombre'],
                'telefono' => $f['telefono'] ?? '', 'email' => $f['email'] ?? '',
                'observaciones' => $f['observaciones'] ?? '',
                'activo' => (int) $f['activo'] === 1, 'articulos' => (int) $f['articulos'],
            ], $st->fetchAll())]);
        }

        case 'proveedor_guardar': {
            $id     = pInt('id', 0);
            $nombre = pTxt('nombre', 120);
            if ($nombre === '') {
                salida(['ok' => false, 'error' => 'El nombre del proveedor es obligatorio.'], 422);
            }
            $tel  = pTxt('telefono', 40);
            $mail = pTxt('email', 120);
            $obs  = pTxt('observaciones', 500);
            $act  = p('activo') === null ? 1 : (((string) p('activo', '1')) === '1' ? 1 : 0);

            if ($id > 0) {
                $bd->prepare('UPDATE proveedores SET nombre=?, telefono=?, email=?, observaciones=?, activo=? WHERE id=?')
                   ->execute([$nombre, $tel ?: null, $mail ?: null, $obs ?: null, $act, $id]);
            } else {
                $bd->prepare('INSERT INTO proveedores (nombre,telefono,email,observaciones,activo) VALUES (?,?,?,?,?)')
                   ->execute([$nombre, $tel ?: null, $mail ?: null, $obs ?: null, $act]);
                $id = (int) $bd->lastInsertId();
            }
            salida(['ok' => true, 'id' => $id]);
        }

        case 'proveedor_borrar': {
            $id = pInt('id');
            $usados = (int) $bd->query('SELECT COUNT(*) FROM productos WHERE proveedor_id = ' . $id)->fetchColumn();
            if ($usados > 0) {
                $bd->prepare('UPDATE productos SET proveedor_id = NULL WHERE proveedor_id = ?')->execute([$id]);
            }
            $bd->prepare('DELETE FROM proveedores WHERE id = ?')->execute([$id]);
            salida(['ok' => true, 'desasignados' => $usados]);
        }

        /* ============================================================
           VENTAS
           ============================================================ */
        case 'venta_crear': {
            // Sin caja abierta no se cobra: así cada venta pertenece a un
            // turno y el cierre se puede conciliar.
            $caja = cajaAbierta();
            if (!$caja) {
                salida([
                    'ok'    => false,
                    'error' => 'Primero abrí tu caja. Sin caja abierta no se puede cobrar.',
                    'caja'  => true,
                ], 409);
            }

            $items = p('items', []);
            if (!is_array($items) || count($items) === 0) {
                salida(['ok' => false, 'error' => 'El carrito está vacío.'], 422);
            }
            $descuento = max(0, pNum('descuento'));
            $metodoId  = pInt('medio_pago_id');
            $metodo    = pTxt('metodo', 20) ?: 'Efectivo';
            $recibido  = pNum('recibido', -1);
            $vuelto    = pNum('vuelto', 0);
            $ref       = pTxt('referencia', 60);
            $nota      = pTxt('nota', 200);
            $usuario   = nombreUsuario();
            $datosComanda = p('comanda');

            // El costo del reparto se resuelve de la zona configurada antes de
            // sumar el total: si mandan otra cifra por el cliente, se ignora.
            $envio = 0.0;
            if (is_array($datosComanda) && ($datosComanda['tipo'] ?? '') === 'delivery') {
                $zonaId = entero($datosComanda['zona_id'] ?? 0);
                if ($zonaId > 0) {
                    $stZ = $bd->prepare('SELECT costo FROM zonas WHERE id = ? AND activo = 1');
                    $stZ->execute([$zonaId]);
                    $costoZona = $stZ->fetchColumn();
                    if ($costoZona === false) {
                        throw new RuntimeException('La zona elegida ya no existe.');
                    }
                    $envio = redondear((float) $costoZona);
                }
            }

            // El medio de pago se resuelve por id contra la tabla, para que el
            // cierre pueda agrupar por método. El nombre es sólo respaldo.
            $mp = null;
            if ($metodoId > 0) {
                $st = $bd->prepare('SELECT * FROM `medios_pago` WHERE id = ? AND activo = 1');
                $st->execute([$metodoId]);
                $mp = $st->fetch();
            }
            if (!$mp) {
                $st = $bd->prepare('SELECT * FROM `medios_pago` WHERE nombre = ? AND activo = 1');
                $st->execute([$metodo]);
                $mp = $st->fetch();
            }
            if (!$mp) {
                throw new RuntimeException('Elegí un medio de pago de la lista.');
            }
            $metodo   = (string) $mp['nombre'];
            $metodoId = (int) $mp['id'];
            if ((int) $mp['exige_referencia'] === 1 && $ref === '') {
                throw new RuntimeException('Anotá la referencia de ' . $metodo . ' para poder conciliar la caja.');
            }

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

                    // Con formato de venta (docena, 2x1, maple...): la cantidad
                    // viene en esas unidades y el precio es el del formato.
                    $formato = null;
                    $factor  = 1.0;
                    $fmtId   = entero($it['formato_id'] ?? 0);
                    if ($fmtId > 0) {
                        $stF = $bd->prepare('SELECT * FROM productos_formatos
                                             WHERE id = ? AND producto_id = ? AND ambito = "venta"');
                        $stF->execute([$fmtId, $pid]);
                        $formato = $stF->fetch();
                        if (!$formato) {
                            // El formato pudo cambiar de id si el producto se
                            // guardo entre la carga y el cobro: se busca por
                            // nombre y cantidad antes de fallar.
                            $formato = formatoPorNombre($bd, $pid, 'venta',
                                (string) ($it['formato_unidad'] ?? ''),
                                isset($it['formato_factor']) ? (float) $it['formato_factor'] : null);
                        }
                        if (!$formato) {
                            throw new RuntimeException('Un formato del carrito no es del producto ' . $pr['nombre'] . '.');
                        }
                        $factor = (float) $formato['factor'] > 0 ? (float) $formato['factor'] : 1.0;
                    }

                    $baseCant = redondear($cant * $factor);
                    // El precio sale del formato o del producto, nunca del
                    // navegador: si no hay nada configurado se cae al precio
                    // que envio el cliente para no dejar la linea en cero.
                    $precio = $formato !== null
                        ? precioVentaDe($formato, (float) ($pr['costo'] ?? 0), (float) ($pr['precio'] ?? 0))
                        : (float) $pr['precio'];
                    if ($precio <= 0.0 && isset($it['precio']) && is_numeric($it['precio'])) {
                        $precio = (float) $it['precio'];
                    }
                    $precio = redondear($precio);
                    if ($precio < 0) {
                        $precio = 0.0;
                    }
                    $importe = redondear($precio * $cant);
                    $subtotal = redondear($subtotal + $importe);
                    $lineas[] = [
                        'p' => $pr, 'cant' => $baseCant, 'precio' => $precio,
                        'importe' => $importe, 'formato' => $formato, 'cant_fmt' => $cant,
                    ];
                }
                if (!$lineas) {
                    throw new RuntimeException('No hay líneas válidas en el carrito.');
                }
                if ($descuento > $subtotal) {
                    $descuento = $subtotal;
                }
                $total = redondear($subtotal - $descuento + $envio);

                // 2. Folio
                $st = $bd->query('SELECT COALESCE(MAX(folio),0) FROM ventas');
                $folio = ((int) $st->fetchColumn()) + 1;
                if ($folio < (int) valorConfig('folio', 1)) {
                    $folio = (int) valorConfig('folio', 1);
                }

                // 3. Cabecera
                $st = $bd->prepare(
                    'INSERT INTO ventas (folio,fecha,subtotal,descuento,total,envio,metodo,medio_pago_id,recibido,vuelto,referencia,nota,usuario,caja_id)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $st->execute([
                    $folio, date('Y-m-d H:i:s'), $subtotal, $descuento, $total, $envio, $metodo, $metodoId,
                    $recibido >= 0 ? $recibido : null, $recibido >= 0 ? $vuelto : null,
                    $ref ?: null, $nota ?: null, $usuario, (int) $caja['id'],
                ]);
                $ventaId = (int) $bd->lastInsertId();

                // 4. Items + descuento de existencias + kardex
                $stItem = $bd->prepare(
                    'INSERT INTO venta_items (venta_id,producto_id,nombre,codigo,precio,cantidad,importe,costo_unitario,
                                               formato_id,formato_unidad,formato_cantidad)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $sinStock = [];
                $costoVendido = 0.0;
                $descuenta = [];   // producto_id => [stock antes, unidades a descontar]
                foreach ($lineas as $l) {
                    $pr     = $l['p'];
                    $pid    = (int) $pr['id'];
                    $costoLinea = redondear((float) ($pr['costo'] ?? 0) * $l['cant']);
                    $costoVendido = redondear($costoVendido + $costoLinea);
                    $stItem->execute([$ventaId, $pid, $pr['nombre'], $pr['codigo'],
                                      $l['precio'], $l['cant'], $l['importe'], (float) ($pr['costo'] ?? 0),
                                      $l['formato'] !== null ? (int) $l['formato']['id'] : null,
                                      $l['formato'] !== null ? (string) $l['formato']['unidad'] : null,
                                      $l['formato'] !== null ? (float) $l['cant_fmt'] : null]);
                    // El mismo producto puede entrar varias veces con distinto
                    // formato (1 docena + 6 sueltas): se acumula todo y se
                    // descuenta una sola vez. Los productos de venta libre
                    // (sin_stock) no entran: no hay existencias que sacar.
                    if ((int) ($pr['sin_stock'] ?? 0) === 1) {
                        continue;
                    }
                    if (!isset($descuenta[$pid])) {
                        $descuenta[$pid] = ['antes' => (float) $pr['stock'], 'resta' => 0.0, 'pr' => $pr];
                    }
                    $descuenta[$pid]['resta'] = redondear($descuenta[$pid]['resta'] + $l['cant']);
                }

                // Descuento de existencias: una actualizacion por producto.
                $stUpd = $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?');
                foreach ($descuenta as $pid => $d) {
                    $ahora = redondear($d['antes'] - $d['resta']);
                    if ($ahora < 0 && !in_array($d['pr']['nombre'], $sinStock, true)) {
                        $sinStock[] = $d['pr']['nombre'];
                    }
                    $stUpd->execute([$ahora, $pid]);
                    $descuenta[$pid]['ahora'] = $ahora;
                }

                // Kardex: una linea por formato, con el stock encadenado.
                $recorrido = [];
                foreach ($lineas as $l) {
                    $pr   = $l['p'];
                    $pid  = (int) $pr['id'];
                    // La venta libre no entra en $descuenta porque no descuenta
                    // stock, asi que tampoco tiene kardex que escribir.
                    if ((int) ($pr['sin_stock'] ?? 0) === 1) {
                        continue;
                    }
                    $d    = $descuenta[$pid];
                    $antes = $recorrido[$pid] ?? $d['antes'];
                    $ahora = redondear($antes - $l['cant']);
                    $recorrido[$pid] = $ahora;
                    registrarMovimiento([
                        'tipo' => 'venta', 'producto_id' => $pid, 'producto_nombre' => $pr['nombre'],
                        'cantidad' => -$l['cant'], 'stock_anterior' => $antes, 'stock_actual' => $ahora,
                        'referencia' => 'Venta folio ' . $folio, 'usuario' => $usuario,
                        'nota' => $l['formato'] !== null
                            ? ((string) $l['formato']['unidad'] . ' x ' . rtrim(rtrim(number_format((float) $l['cant_fmt'], 4, '.', ''), '0'), '.'))
                            : null,
                    ]);
                }

                // La comanda se guarda en la MISMA transaccion que la venta:
                // o queda el pedido pagado y su comanda, o no queda nada.
                $comandaId = null;
                if (is_array($datosComanda)) {
                    $comandaId = guardarComanda($bd, $datosComanda, $ventaId, $folio, $total, $usuario, $envio);
                }

                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }

            $st = $bd->prepare('SELECT * FROM ventas WHERE id = ?');
            $st->execute([$ventaId]);
            $v = venta($st->fetch());
            $v['costo']    = $costoVendido;
            $v['utilidad'] = redondear($total - $costoVendido);
            $v['items'] = itemsDe($bd, $ventaId);

            salida(['ok' => true, 'venta' => $v, 'sin_stock' => $sinStock, 'comanda_id' => $comandaId]);
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
                // El vendedor sólo anula lo que está en su caja abierta.
                // Con la caja ya cerrada, sólo el administrador.
                if (!puedeAnularVenta($v)) {
                    throw new RuntimeException(
                        'Con la caja cerrada sólo el administrador puede anular esta venta.'
                    );
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
                    $s = $bd->prepare('SELECT stock, sin_stock FROM productos WHERE id = ? FOR UPDATE');
                    $s->execute([$pid]);
                    $fila = $s->fetch();
                    if (!$fila) {
                        continue;
                    }
                    // La venta libre nunca toca el stock: al anular tampoco
                    // hay que reponer.
                    if ((int) ($fila['sin_stock'] ?? 0) === 1) {
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
                $bd->prepare('UPDATE ventas SET anulada=1, anulada_en=NOW(), motivo=?, anulada_por=? WHERE id=?')
                   ->execute([$motivo, nombreUsuario(), $id]);
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
            $v = venta($v);
            $v['items'] = itemsDe($bd, $id);
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

            // Costo de la mercancia vendida en el periodo (costo congelado al vender)
            $st = $bd->prepare(
                'SELECT COALESCE(SUM(vi.cantidad * vi.costo_unitario),0) AS costo
                 FROM venta_items vi INNER JOIN ventas v2 ON v2.id = vi.venta_id
                 WHERE v2.anulada = 0 AND v2.fecha BETWEEN ? AND ?'
            );
            $st->execute([$d, $h]);
            $costoPeriodo = (float) $st->fetchColumn();

            $st = $bd->prepare('SELECT COUNT(*) AS anuladas FROM ventas WHERE anulada = 1 AND fecha BETWEEN ? AND ?');
            $st->execute([$d, $h]);
            $anuladas = (int) $st->fetchColumn();

            $st = $bd->prepare('SELECT HOUR(fecha) AS h, COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?
                                GROUP BY HOUR(fecha) ORDER BY h');
            $st->execute([$d, $h]);
            $porHora = array_map(fn($r) => ['h' => (int) $r['h'], 'ventas' => (int) $r['n'], 'total' => (float) $r['t']], $st->fetchAll());

            $st = $bd->prepare(
                'SELECT vi.nombre, SUM(vi.cantidad) AS unidades, SUM(vi.importe) AS vendido,
                        SUM(vi.cantidad * vi.costo_unitario) AS costo, SUM(vi.cantidad) AS c
                 FROM venta_items vi
                 INNER JOIN ventas v ON v.id = vi.venta_id
                 WHERE v.anulada = 0 AND v.fecha BETWEEN ? AND ?
                 GROUP BY vi.nombre ORDER BY c DESC, vi.nombre ASC LIMIT 12'
            );
            $st->execute([$d, $h]);
            $top = array_map(fn($r) => [
                'nombre' => $r['nombre'],
                'unidades' => (float) $r['unidades'],
                'vendido' => (float) $r['vendido'],
                'costo' => (float) $r['costo'],
                'margen' => (float) $r['vendido'] > 0
                    ? redondear((($r['vendido'] - $r['costo']) / $r['vendido']) * 100) : 0.0,
            ], $st->fetchAll());

            $st = $bd->prepare('SELECT metodo, COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?
                                GROUP BY metodo ORDER BY t DESC');
            $st->execute([$d, $h]);
            $porPago = array_map(fn($r) => ['metodo' => $r['metodo'], 'ventas' => (int) $r['n'], 'total' => (float) $r['t']], $st->fetchAll());

            // "Quedan pocas" y la valuacion de inventario excluyen los
            // productos de venta libre: no llevan control de existencias.
            $st = $bd->query('SELECT id, nombre, stock, minimo, unidad, precio
                             FROM productos
                             WHERE activo = 1 AND sin_stock = 0 AND stock <= GREATEST(minimo, 0)
                             ORDER BY (stock - GREATEST(minimo,0)) ASC, nombre ASC LIMIT 30');
            $faltantes = array_map(fn($r) => [
                'id' => (int) $r['id'], 'nombre' => $r['nombre'],
                'stock' => (float) $r['stock'], 'minimo' => (float) $r['minimo'],
                'unidad' => $r['unidad'], 'precio' => (float) $r['precio'],
            ], $st->fetchAll());

            $st = $bd->query('SELECT COALESCE(SUM(stock*precio),0) AS v, COALESCE(SUM(stock*minimo),0) AS m,
                                     COALESCE(SUM(stock*costo),0) AS c, COALESCE(SUM(stock),0) AS u
                             FROM productos WHERE activo = 1 AND sin_stock = 0');
            $inv = $st->fetch();
            $costoInv = (float) $inv['c'];
            $ventaInv = (float) $inv['v'];

            salida([
                'ok'    => true,
                'resumen' => [
                    'ventas'      => (int) $res['ventas'],
                    'total'       => (float) $res['total'],
                    'promedio'    => (float) $res['promedio'],
                    'descuentos'  => (float) $res['descuentos'],
                    'mayor'       => (float) $res['mayor'],
                    'anuladas'    => $anuladas,
                    'costo'       => redondear($costoPeriodo),
                    'utilidad'    => redondear((float) $res['total'] - $costoPeriodo),
                    'margen'      => (float) $res['total'] > 0
                        ? redondear(((($res['total'] - $costoPeriodo) / $res['total']) * 100)) : 0.0,
                    'inventario'  => $ventaInv,
                    'costo_inventario' => $costoInv,
                    'ganancia_potencial' => redondear($ventaInv - $costoInv),
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
            $st = $bd->prepare(
                'SELECT m.*, pr.unidad
                 FROM movimientos m
                 LEFT JOIN productos pr ON pr.id = m.producto_id
                 WHERE m.producto_id = ? ORDER BY m.id DESC LIMIT 100'
            );
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

        /* ============================================================
           SESIÓN, CAJAS Y USUARIOS
           ============================================================ */

        /* ---------- ¿Quién está entrado y con qué caja? ---------- */
        case 'sesion_info': {
        $u = usuarioActual();
        salida([
            'ok'      => true,
            'usuario' => $u ? [
                'id'                 => (int) $u['id'],
                'usuario'            => $u['usuario'],
                'nombre'             => $u['nombre'],
                'rol'                => $u['rol'],
                'es_admin'           => $u['rol'] === 'admin',
                'debe_cambiar_clave' => (int) $u['debe_cambiar_clave'] === 1,
            ] : null,
            'caja'    => cajaAbierta() ? cajaResumen(cajaAbierta()) : null,
        ]);
    }

    /* ---------- Abrir caja ---------- */
    case 'caja_abrir': {
        $u = usuarioActual();
        if (cajaDeUsuario((int) $u['id'])) {
            throw new RuntimeException('Ya tenés una caja abierta. Cierrala antes de abrir otra.');
        }
        $monto = max(0, pNum('monto_inicial'));
        $st = $bd->prepare('INSERT INTO `cajas` (usuario_id, monto_inicial) VALUES (?,?)');
        $st->execute([(int) $u['id'], $monto]);
        salida(['ok' => true, 'caja' => cajaResumen(cajaDeUsuario((int) $u['id']))]);
    }

    /* ---------- Ver mi caja abierta ---------- */
    case 'cajas_mias': {
        $u = usuarioActual();
        salida([
            'ok'    => true,
            'caja'  => $caja = cajaDeUsuario((int) $u['id']),
            'resumen' => $caja ? resumenCaja($bd, (int) $caja['id']) : null,
        ]);
    }

        /* ---------- Cerrar caja con conciliación ---------- */
        case 'caja_cerra': {
            $u = usuarioActual();
            $cajaId = pInt('caja_id');

            if ($cajaId > 0) {
                // Cerrar una caja ajena: sólo el administrador puede.
                $caja = cajaPorId($cajaId);
                if (!$caja) {
                    throw new RuntimeException('Esa caja no existe.');
                }
                if ((int) $caja['usuario_id'] !== (int) $u['id'] && $u['rol'] !== 'admin') {
                    salida(['ok' => false, 'error' => 'Esa caja es de otro cajero.'], 403);
                }
                if ((int) $caja['abierta'] !== 1) {
                    throw new RuntimeException('Esa caja ya estaba cerrada.');
                }
            } else {
                $caja = cajaDeUsuario((int) $u['id']);
                if (!$caja) {
                    throw new RuntimeException('No tenés ninguna caja abierta.');
                }
                $cajaId = (int) $caja['id'];
            }

        $declarados = p('declarados', []);
        $declarados = is_array($declarados) ? $declarados : [];
        $motivo = pTxt('motivo', 200);

        $resumen = resumenCaja($bd, $cajaId);
        $esperadoEfectivo = efectivoEsperadoCaja($bd, $cajaId);

        $bd->beginTransaction();
        try {
            $st = $bd->prepare(
                'INSERT INTO `caja_cierre_metodos` (caja_id, medio_pago_id, esperado, declarado, diferencia)
                 VALUES (?,?,?,?,?)'
            );
            foreach ($resumen['metodos'] as $m) {
                if ($m['ventas'] === 0) {
                    continue;   // sin ventas no hay nada que conciliar
                }
                $dec = array_key_exists((string) $m['id'], $declarados)
                    ? redondear(numero($declarados[(string) $m['id']]))
                    : $m['esperado'];
                $st->execute([$cajaId, $m['id'], $m['esperado'], $dec, redondear($dec - $m['esperado'])]);
            }

            // El efectivo declarado es la gaveta real: incluye el fondo inicial.
            // El frontend manda un id por método, no la palabra "efectivo", así que
            // buscamos cuál de los métodos de la conciliación es el de efectivo.
            $idEfectivo = 0;
            foreach ($resumen['metodos'] as $m) {
                if ($m['es_efectivo']) { $idEfectivo = $m['id']; break; }
            }
            $decEfectivo = $idEfectivo && array_key_exists((string) $idEfectivo, $declarados)
                ? redondear(numero($declarados[(string) $idEfectivo]))
                : $esperadoEfectivo;
            $difEfectivo = redondear($decEfectivo - $esperadoEfectivo);

            if (abs($difEfectivo) > 0.009 && $motivo === '') {
                throw new RuntimeException(
                    'El efectivo no cuadra (faltan o sobran ' . monto(abs($difEfectivo)) . '). ' .
                    'Contá bien y, si el descuadre es real, escribí el motivo antes de cerrar.'
                );
            }

            $st = $bd->prepare(
                'UPDATE `cajas` SET cerrada_en = NOW(), abierta = 0,
                        efectivo_esperado = ?, efectivo_declarado = ?,
                        total_esperado = ?, total_declarado = ?, diferencia = ?, motivo = ?
                 WHERE id = ?'
            );
            $st->execute([
                $esperadoEfectivo, $decEfectivo,
                $resumen['esperado'], $resumen['declarado'],
                $difEfectivo, $motivo ?: null, $cajaId,
            ]);
            $bd->commit();
        } catch (Throwable $e) {
            $bd->rollBack();
            throw $e;
        }

        salida([
            'ok'      => true,
            'caja'    => cajaResumen(cajaPorId($cajaId)),
            'resumen' => $resumen,
        ]);
    }

        /* ---------- Historial de cajas ----------
           El administrador ve todas; el vendedor, sólo las suyas. */
        case 'cajas_todas': {
            $desde = pTxt('desde', 10) ?: date('Y-m-01');
            $hasta = pTxt('hasta', 10) ?: date('Y-m-d');
            $u     = usuarioActual();

            $sql = 'SELECT c.*, us.nombre AS cajero, us.usuario AS cajero_usuario,
                           (SELECT COUNT(*) FROM `ventas` v WHERE v.caja_id = c.id AND v.anulada = 0) AS ventas
                    FROM `cajas` c
                    JOIN `usuarios` us ON us.id = c.usuario_id
                    WHERE DATE(c.abierta_en) BETWEEN ? AND ?';
            $par = [$desde, $hasta];
            if ($u['rol'] !== 'admin') {
                $sql .= ' AND c.usuario_id = ?';
                $par[] = (int) $u['id'];
            }
            $sql .= ' ORDER BY c.id DESC';

            $st = $bd->prepare($sql);
            $st->execute($par);
            $cajas = array_map(fn($c) => cajaResumen($c), $st->fetchAll());

            $desc = 0;
            foreach ($cajas as $c) {
                if (!$c['abierta'] && $c['diferencia'] !== null && abs($c['diferencia']) > 0.009) {
                    $desc++;
                }
            }
            salida([
                'ok' => true, 'cajas' => $cajas,
                'desde' => $desde, 'hasta' => $hasta, 'descuadres' => $desc,
                'solo_mias' => $u['rol'] !== 'admin',
            ]);
        }

    /* ---------- Detalle de una caja ---------- */
    case 'caja_detalle': {
        $id = pInt('id');
        $u = usuarioActual();
        $st = $bd->prepare(
            'SELECT c.*, us.nombre AS cajero, us.usuario AS cajero_usuario
             FROM `cajas` c JOIN `usuarios` us ON us.id = c.usuario_id WHERE c.id = ?'
        );
        $st->execute([$id]);
        $c = $st->fetch();
        if (!$c) {
            salida(['ok' => false, 'error' => 'La caja no existe.'], 404);
        }
        // El vendedor sólo puede mirar sus propias cajas.
        if ($u['rol'] !== 'admin' && (int) $c['usuario_id'] !== (int) $u['id']) {
            salida(['ok' => false, 'error' => 'Esa caja es de otro cajero.'], 403);
        }

        $st = $bd->prepare(
            'SELECT id, folio, fecha, total, metodo, medio_pago_id, recibido, vuelto, referencia, anulada
             FROM `ventas` WHERE caja_id = ? ORDER BY id'
        );
        $st->execute([$id]);
        $ventas = array_map(fn($v) => [
            'id'       => (int) $v['id'],
            'folio'    => (int) $v['folio'],
            'fecha'    => $v['fecha'],
            'total'    => (float) $v['total'],
            'metodo'   => $v['metodo'],
            'recibido' => $v['recibido'] === null ? null : (float) $v['recibido'],
            'vuelto'   => $v['vuelto'] === null ? null : (float) $v['vuelto'],
            'referencia'=> $v['referencia'] ?? '',
            'anulada'  => (int) $v['anulada'] === 1,
        ], $st->fetchAll());

        salida([
            'ok'      => true,
            'caja'    => cajaResumen($c),
            'resumen' => resumenCaja($bd, $id),
            'ventas'  => $ventas,
        ]);
    }

    /* ---------- Usuarios (administrador) ---------- */
    case 'usuarios': {
        $st = $bd->query(
            'SELECT us.id, us.usuario, us.nombre, us.rol, us.activo, us.creado, us.ultimo_ingreso,
                    (SELECT COUNT(*) FROM `cajas` c WHERE c.usuario_id = us.id) AS cajas
             FROM `usuarios` us ORDER BY us.rol, us.nombre'
        );
        salida(['ok' => true, 'usuarios' => $st->fetchAll()]);
    }

    case 'comandas': {
        // Tablero de cocina. Por defecto sólo lo que está en curso; el
        // histórico se pide con ?incluir=cerradas.
        $estados = ['pendiente', 'preparando', 'listo'];
        if (pTxt('incluir') === 'cerradas') {
            $estados = ESTADOS_COMANDA;
        }
        $lugares = array_map('trim', explode(',', pTxt('estados', 60)));
        $lugares = array_values(array_filter($lugares, fn($e) => in_array($e, ESTADOS_COMANDA, true)));
        if (!$lugares) {
            $lugares = $estados;
        }
        $in = implode(',', array_fill(0, count($lugares), '?'));
        $st = $bd->prepare("SELECT * FROM comandas WHERE estado IN ($in) ORDER BY id DESC LIMIT 200");
        $st->execute($lugares);
        $salida = ['ok' => true, 'comandas' => []];
        foreach ($st->fetchAll() as $fila) {
            $c = comandaCompleta($bd, (int) $fila['id']);
            if ($c) {
                $salida['comandas'][] = $c;
            }
        }
        salida($salida);
    }

    case 'comanda': {
        $c = comandaCompleta($bd, pInt('id'));
        if (!$c) {
            salida(['ok' => false, 'error' => 'Esa comanda ya no existe.'], 404);
        }
        salida(['ok' => true, 'comanda' => $c]);
    }

    case 'comanda_estado': {
        $id    = pInt('id');
        $nuevo = pTxt('estado', 20);
        if (!in_array($nuevo, ESTADOS_COMANDA, true)) {
            throw new RuntimeException('Estado de comanda desconocido.');
        }
        // Cancelar tira el pedido: lo decide el administrador, no la cocina.
        if ($nuevo === 'cancelado' && !esAdmin()) {
            salida(['ok' => false, 'error' => 'Sólo el administrador puede cancelar una comanda.'], 403);
        }
        $cerrado = in_array($nuevo, ['entregado', 'cancelado'], true) ? date('Y-m-d H:i:s') : null;

        $st = $bd->prepare('SELECT * FROM comandas WHERE id = ?');
        $st->execute([$id]);
        $actual = $st->fetch();
        if (!$actual) {
            salida(['ok' => false, 'error' => 'Esa comanda ya no existe.'], 404);
        }
        // Una comanda cancelada o ya entregada no vuelve atrás: si el pedido
        // se equivozó se carga uno nuevo.
        if (in_array((string) $actual['estado'], ['entregado', 'cancelado'], true)
            && $nuevo !== (string) $actual['estado']) {
            salida(['ok' => false, 'error' => 'Esa comanda ya está cerrada.'], 409);
        }
        $st = $bd->prepare('UPDATE comandas SET estado = ?, actualizado = ?, cerrado_en = ? WHERE id = ?');
        $st->execute([$nuevo, date('Y-m-d H:i:s'), $cerrado, $id]);
        salida(['ok' => true, 'comanda' => comandaCompleta($bd, $id)]);
    }

    case 'comanda_item': {
        // Cocina tacha linea por linea: "1 miga listo" sin esperar al resto.
        $idItem = pInt('id_item');
        $nuevo  = pTxt('estado', 20) === 'listo' ? 'listo' : 'pendiente';
        $st = $bd->prepare('UPDATE comanda_items SET estado = ? WHERE id = ?');
        $st->execute([$nuevo, $idItem]);
        if ($st->rowCount() === 0) {
            salida(['ok' => false, 'error' => 'Esa línea ya no existe.'], 404);
        }
        $st = $bd->prepare('SELECT comanda_id FROM comanda_items WHERE id = ?');
        $st->execute([$idItem]);
        $cid = (int) $st->fetchColumn();
        $st = $bd->prepare('UPDATE comandas SET actualizado = ? WHERE id = ?');
        $st->execute([date('Y-m-d H:i:s'), $cid]);
        salida(['ok' => true, 'comanda' => comandaCompleta($bd, $cid)]);
    }

    case 'comanda_atajos': {
        $st = $bd->query('SELECT * FROM comanda_atajos WHERE activo = 1 ORDER BY seccion, orden, id');
        $salida = ['ok' => true, 'atajos' => []];
        foreach ($st->fetchAll() as $a) {
            $salida['atajos'][] = [
                'id' => (int) $a['id'], 'seccion' => $a['seccion'], 'etiqueta' => $a['etiqueta'],
                'producto_id' => $a['producto_id'] !== null ? (int) $a['producto_id'] : null,
                'formato_unidad' => $a['formato_unidad'] ?: null,
                'texto' => $a['texto'] ?: null, 'detalle' => $a['detalle'] ?: null,
                'orden' => (int) $a['orden'],
            ];
        }
        salida($salida);
    }

    case 'atajo_guardar': {
        $id   = pInt('id');
        $etq  = trim(pTxt('etiqueta', 60));
        if ($etq === '') {
            throw new RuntimeException('La etiqueta del atajo no puede estar vacía.');
        }
        $texto = trim(pTxt('texto', 160));
        $pid   = pInt('producto_id');
        if ($texto === '' && $pid <= 0) {
            throw new RuntimeException('Elegí un producto o escribí lo que tiene que preparar cocina.');
        }
        $vals = [
            trim(pTxt('seccion', 40)) ?: 'Comidas', $etq,
            $pid > 0 ? $pid : null, trim(pTxt('formato_unidad', 20)) ?: null,
            $texto ?: null, trim(pTxt('detalle', 160)) ?: null,
            pInt('orden'), p('activo', 1) ? 1 : 0,
        ];
        if ($id > 0) {
            $st = $bd->prepare('UPDATE comanda_atajos
                SET seccion=?, etiqueta=?, producto_id=?, formato_unidad=?, texto=?, detalle=?, orden=?, activo=?
                WHERE id=?');
            $st->execute([...$vals, $id]);
        } else {
            $st = $bd->prepare('INSERT INTO comanda_atajos
                (seccion,etiqueta,producto_id,formato_unidad,texto,detalle,orden,activo) VALUES (?,?,?,?,?,?,?,?)');
            $st->execute($vals);
        }
        salida(['ok' => true, 'id' => $id > 0 ? $id : (int) $bd->lastInsertId()]);
    }

    case 'atajo_borrar': {
        $st = $bd->prepare('DELETE FROM comanda_atajos WHERE id = ?');
        $st->execute([pInt('id')]);
        salida(['ok' => true]);
    }

    case 'zonas': {
        $st = $bd->query('SELECT * FROM zonas WHERE activo = 1 ORDER BY orden, id');
        salida(['ok' => true, 'zonas' => array_map(fn($z) => [
            'id' => (int) $z['id'], 'nombre' => $z['nombre'], 'costo' => (float) $z['costo'],
        ], $st->fetchAll())]);
    }

    case 'zona_guardar': {
        $nombre = trim(pTxt('nombre', 60));
        if ($nombre === '') {
            throw new RuntimeException('La zona necesita un nombre.');
        }
        $id = pInt('id');
        if ($id > 0) {
            $st = $bd->prepare('UPDATE zonas SET nombre=?, costo=?, activo=? WHERE id=?');
            $st->execute([$nombre, pNum('costo'), p('activo', 1) ? 1 : 0, $id]);
        } else {
            $st = $bd->prepare('INSERT INTO zonas (nombre,costo,orden,activo) VALUES (?,?,?,?)');
            $st->execute([$nombre, pNum('costo'), pInt('orden'), p('activo', 1) ? 1 : 0]);
            $id = (int) $bd->lastInsertId();
        }
        salida(['ok' => true, 'id' => $id]);
    }

    case 'zona_borrar': {
        $st = $bd->prepare('DELETE FROM zonas WHERE id = ?');
        $st->execute([pInt('id')]);
        salida(['ok' => true]);
    }

    case 'usuario_guardar': {
        $id   = pInt('id');
        $user = strtolower(trim(pTxt('usuario', 40)));
        $nombre = trim(pTxt('nombre', 80));
        $rol  = pTxt('rol', 10);
        if (!in_array($rol, ['admin', 'vendedor', 'cocina'], true)) {
            $rol = 'vendedor';
        }
        $clave = (string) p('clave', '');
        $activo = p('activo', 1) ? 1 : 0;

        if ($user === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $user)) {
            throw new RuntimeException('El usuario debe tener de 3 a 40 caracteres: letras, números, punto, guion o guion bajo.');
        }
        if ($nombre === '') {
            throw new RuntimeException('Escribí el nombre de la persona.');
        }

        if ($id > 0) {
            $st = $bd->prepare('SELECT * FROM `usuarios` WHERE id = ?');
            $st->execute([$id]);
            $existente = $st->fetch();
            if (!$existente) {
                throw new RuntimeException('Ese usuario ya no existe.');
            }
            // No dejar el kiosco sin ningún administrador activo.
            if ($existente['rol'] === 'admin' && ($rol !== 'admin' || !$activo)) {
                $otros = (int) $bd->query("SELECT COUNT(*) FROM `usuarios` WHERE rol='admin' AND activo=1 AND id <> " . $id)->fetchColumn();
                if ($otros === 0) {
                    throw new RuntimeException('Tiene que quedar al menos un administrador activo.');
                }
            }
            if (cajaDeUsuario($id) && $rol !== 'admin') {
                // sin consecuencia real, pero evita confusiones de permisos
                $rol = 'vendedor';
            }
            $st = $bd->prepare('UPDATE `usuarios` SET usuario=?, nombre=?, rol=?, activo=? WHERE id=?');
            $st->execute([$user, $nombre, $rol, $activo, $id]);
            if ($clave !== '') {
                if (strlen($clave) < 4) {
                    throw new RuntimeException('La clave debe tener al menos 4 caracteres.');
                }
                $bd->prepare('UPDATE `usuarios` SET clave=?, debe_cambiar_clave=1 WHERE id=?')
                   ->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
            }
            salida(['ok' => true, 'id' => $id, 'clave_renovada' => $clave !== '']);
        }

        if ($clave === '' || strlen($clave) < 4) {
            throw new RuntimeException('Asigná una clave de al menos 4 caracteres.');
        }
        $st = $bd->prepare('SELECT COUNT(*) FROM `usuarios` WHERE usuario = ?');
        $st->execute([$user]);
        if ((int) $st->fetchColumn() > 0) {
            throw new RuntimeException('Ya existe un usuario con ese nombre.');
        }
        $st = $bd->prepare(
            'INSERT INTO `usuarios` (usuario, nombre, clave, rol, activo, debe_cambiar_clave) VALUES (?,?,?,?,1,1)'
        );
        $st->execute([$user, $nombre, password_hash($clave, PASSWORD_DEFAULT), $rol]);
        salida(['ok' => true, 'id' => (int) $bd->lastInsertId()]);
    }

    case 'usuario_borrar': {
        $id = pInt('id');
        $u  = usuarioActual();
        if ($id === (int) $u['id']) {
            throw new RuntimeException('No te podés borrar a vos mismo.');
        }
        $st = $bd->prepare('SELECT * FROM `usuarios` WHERE id = ?');
        $st->execute([$id]);
        $usr = $st->fetch();
        if (!$usr) {
            throw new RuntimeException('Ese usuario no existe.');
        }
        if ($usr['rol'] === 'admin') {
            $otros = (int) $bd->query("SELECT COUNT(*) FROM `usuarios` WHERE rol='admin' AND activo=1 AND id <> " . $id)->fetchColumn();
            if ($otros === 0) {
                throw new RuntimeException('Tiene que quedar al menos un administrador activo.');
            }
        }
        if (cajaDeUsuario($id)) {
            throw new RuntimeException('Ese usuario tiene una caja abierta. Pedile que la cierre antes.');
        }
        // No se borra físicamente: el historial de ventas y cajas lo referencia.
        $bd->prepare('UPDATE `usuarios` SET activo = 0 WHERE id = ?')->execute([$id]);
        salida(['ok' => true, 'desactivado' => true]);
    }

    /* ---------- Cambiar mi propia clave ---------- */
    case 'clave_cambiar': {
        $u = usuarioActual();
        if (!$u) {
            salida(['ok' => false, 'error' => 'Sesión vencida.'], 401);
        }
        $actual  = (string) p('actual', '');
        $nueva   = (string) p('nueva', '');
        $repetir = (string) p('repetir', '');

        if (!password_verify($actual, (string) $u['clave'])) {
            throw new RuntimeException('La clave actual no es correcta.');
        }
        if (strlen($nueva) < 4) {
            throw new RuntimeException('La clave nueva debe tener al menos 4 caracteres.');
        }
        if ($nueva !== $repetir) {
            throw new RuntimeException('Las dos claves nuevas no coinciden.');
        }
        $bd->prepare('UPDATE `usuarios` SET clave = ?, debe_cambiar_clave = 0 WHERE id = ?')
           ->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $u['id']]);
        salida(['ok' => true]);
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
