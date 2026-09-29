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
function guardarComanda(PDO $bd, array $d, ?int $ventaId, ?int $folio, float $total, string $usuario): int
{
    $cliente = trim((string) ($d['cliente'] ?? ''));
    // Para lo que se consume en el local no hace falta nombre: el papel va a
    // la cocina, y con "Mostrador" alcanza para saber de quién es.
    if ($cliente === '') {
        $cliente = 'Mostrador';
    }
    // Las lineas se limpian ANTES de insertar nada. Si se validara despues,
    // una comanda sin items que preparar quedaria huérfana en el tablero de
    // cocina, y el cajero veria un papel vacío que no puede borrar.
    $items = [];
    foreach (($d['items'] ?? []) as $it) {
        if (!is_array($it)) {
            continue;
        }
        $texto = trim((string) ($it['texto'] ?? ''));
        if ($texto === '') {
            continue;
        }
        $pid = entero($it['producto_id'] ?? 0);
        $cant = redondear(numero($it['cantidad'] ?? 1));
        $items[] = [
            'producto_id' => $pid > 0 ? $pid : null,
            'cantidad'    => $cant > 0 ? $cant : 1.0,
            'texto'       => mb_substr($texto, 0, 160),
            'detalle'     => trim((string) ($it['detalle'] ?? '')) ?: null,
        ];
    }
    if (count($items) === 0) {
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
        $stIt->execute([$comandaId, $it['producto_id'], $it['cantidad'],
                        $it['texto'], $it['detalle'], $it['producto_id'] ? 1 : 0]);
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

    // Comandas: las ve y las mueve cualquiera (incluido el de cocina).
    // Crearlas ya no es parte del cobro: el cajero arma la comanda por su
    // cuenta, asi que alcanza con que pueda vender.
    'comandas'          => ROL_AUTENTICADO,
    'comanda'           => ROL_AUTENTICADO,
    'comanda_crear'     => ROL_VENTA,
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
        case 'productos':
        case 'producto':
        case 'producto_guardar':
        case 'producto_borrar':
        case 'stock_mover':
        case 'categorias': {
            require __DIR__ . '/api/rutas/productos.php';
            break;
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
        case 'venta_crear':
        case 'venta_anular':
        case 'ventas':
        case 'venta': {
            require __DIR__ . '/api/rutas/ventas.php';
            break;
        }

                /* ============================================================
           REPORTES
           ============================================================ */
        case 'reportes': {
            require __DIR__ . '/api/rutas/reportes.php';
            break;
        }

        /* ============================================================
           INVENTARIO
           ============================================================ */
        case 'kardex': {
            require __DIR__ . '/api/rutas/inventario.php';
            break;
        }

        /* ============================================================
           CONFIGURACION
           ============================================================ */
        case 'config_guardar':
        case 'kiosco_limpiar': {
            require __DIR__ . '/api/rutas/configuracion.php';
            break;
        }

        /* ============================================================
           USUARIOS
           ============================================================ */
        case 'sesion_info': {
            require __DIR__ . '/api/rutas/usuarios.php';
            break;
        }

        /* ============================================================
           CAJAS
           ============================================================ */
        case 'caja_abrir':
        case 'cajas_mias':
        case 'caja_cerra':
        case 'cajas_todas':
        case 'caja_detalle': {
            require __DIR__ . '/api/rutas/cajas.php';
            break;
        }

        /* ============================================================
           ADMIN_USUARIOS
           ============================================================ */
        case 'usuarios': {
            require __DIR__ . '/api/rutas/admin_usuarios.php';
            break;
        }

        /* ============================================================
           COMANDAS
           ============================================================ */
        case 'comandas':
        case 'comanda':
        case 'comanda_crear':
        case 'comanda_estado':
        case 'comanda_item':
        case 'comanda_atajos':
        case 'atajo_guardar':
        case 'atajo_borrar': {
            require __DIR__ . '/api/rutas/comandas.php';
            break;
        }

        /* ============================================================
           ZONAS
           ============================================================ */
        case 'zonas':
        case 'zona_guardar':
        case 'zona_borrar': {
            require __DIR__ . '/api/rutas/zonas.php';
            break;
        }

        /* ============================================================
           USUARIO_CRUD
           ============================================================ */
        case 'usuario_guardar':
        case 'usuario_borrar':
        case 'clave_cambiar': {
            require __DIR__ . '/api/rutas/usuario_crud.php';
            break;
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
