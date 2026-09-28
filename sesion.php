<?php
/**
 * Kiosco — sesión, usuarios y control de caja.
 *
 * Tres roles:
 *   admin     — todo: catálogo, proveedores, precios, stock, cajas ajenas.
 *   vendedor  — abre y cierra su propia caja y vende. No toca el catálogo.
 *   cocina    — sólo el tablero de comandas: prepara pedidos y los marca
 *               listos. No cobra, no abre caja y no ve el catálogo.
 *
 * Para vender hay que tener una caja abierta: así cada cobro queda atado a
 * un turno y el cierre se puede conciliar sin que el vendedor se coma los
 * descuadres de otro. La cocina no vende, así que no necesita caja.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/* ---------------------------------------------------------------
   Sesión
   --------------------------------------------------------------- */
function sesionIniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('KIOSCO');
    session_start();
}

/** El usuario de la sesión, o null si nadie entró. */
function usuarioActual(): ?array
{
    static $cache = null;
    static $cacheId = null;

    sesionIniciar();
    $id = $_SESSION['usuario_id'] ?? null;
    if (!$id) {
        $cache = null;
        return null;
    }
    // En la misma petición no repetimos la consulta.
    if ($cache !== null && $cacheId === $id) {
        return $cache;
    }

    $st = pdoBd()->prepare('SELECT * FROM `usuarios` WHERE id = ? AND activo = 1');
    $st->execute([(int) $id]);
    $u = $st->fetch();
    if (!$u) {
        // El usuario fue desactivado o borrado mientras tenía la sesión abierta.
        unset($_SESSION['usuario_id']);
        $cache = null;
        return null;
    }
    $cache = $u;
    $cacheId = $id;
    return $cache;
}

function esAdmin(): bool
{
    $u = usuarioActual();
    return $u !== null && $u['rol'] === 'admin';
}

function esVendedor(): bool
{
    $u = usuarioActual();
    return $u !== null && $u['rol'] === 'vendedor';
}

/** Personal de cocina: sólo prepara pedidos, no cobra. */
function esCocina(): bool
{
    $u = usuarioActual();
    return $u !== null && $u['rol'] === 'cocina';
}

/**
 * Quién puede cobrar y manejar la caja: el administrador y los vendedores.
 * El personal de cocina queda afuera aunque tenga sesión abierta.
 */
function puedeVender(): bool
{
    return esAdmin() || esVendedor();
}

function nombreCompleto(): string
{
    $u = usuarioActual();
    return $u ? (string) $u['nombre'] : '';
}

/* ---------------------------------------------------------------
   Guardas
   --------------------------------------------------------------- */

/**
 * Exige usuario con sesión iniciada. Si no hay, llama a $denegar()
 * (que no debe volver) y corta igual.
 */
function exigirSesion(callable $denegar): array
{
    $u = usuarioActual();
    if (!$u) {
        $denegar();
    }
    return $u;
}

function exigirAdmin(callable $denegar): array
{
    $u = exigirSesion($denegar);
    if ($u['rol'] !== 'admin') {
        $denegar();
    }
    return $u;
}

/** Guarda un nombre de usuario en el historial de ventas. */
function marcarIngreso(int $usuarioId): void
{
    pdoBd()->prepare('UPDATE `usuarios` SET ultimo_ingreso = NOW() WHERE id = ?')
          ->execute([$usuarioId]);
}

function cerrarSesion(): void
{
    sesionIniciar();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

/* ---------------------------------------------------------------
   Cajas
   --------------------------------------------------------------- */

/** La caja abierta de un usuario, o null. */
function cajaDeUsuario(int $usuarioId): ?array
{
    $st = pdoBd()->prepare('SELECT * FROM `cajas` WHERE usuario_id = ? AND abierta = 1 ORDER BY id DESC LIMIT 1');
    $st->execute([$usuarioId]);
    return $st->fetch() ?: null;
}

/** Una caja por su id, o null. */
function cajaPorId(int $cajaId): ?array
{
    $st = pdoBd()->prepare('SELECT * FROM `cajas` WHERE id = ?');
    $st->execute([$cajaId]);
    return $st->fetch() ?: null;
}

/** Formato de dinero para los mensajes de error del servidor. */
function monto(float $n): string
{
    return (valorConfig('moneda', '$') ?: '$')
        . number_format(redondear($n), 2, ',', '.');
}

/** La caja tal como la ve el navegador. */
function cajaResumen(?array $c): ?array
{
    if (!$c) {
        return null;
    }
    return [
        'id'          => (int) $c['id'],
        'usuario_id'  => (int) $c['usuario_id'],
        'abierta'     => (int) $c['abierta'] === 1,
        'abierta_en'  => $c['abierta_en'],
        'cerrada_en'  => $c['cerrada_en'],
        'monto_inicial'   => (float) $c['monto_inicial'],
        'efectivo_esperado'  => $c['efectivo_esperado'] === null ? null : (float) $c['efectivo_esperado'],
        'efectivo_declarado' => $c['efectivo_declarado'] === null ? null : (float) $c['efectivo_declarado'],
        'total_esperado'  => $c['total_esperado'] === null ? null : (float) $c['total_esperado'],
        'total_declarado' => $c['total_declarado'] === null ? null : (float) $c['total_declarado'],
        'diferencia'  => $c['diferencia'] === null ? null : (float) $c['diferencia'],
        'motivo'      => $c['motivo'] ?? '',
        'cajero'      => $c['cajero'] ?? null,
        'cajero_usuario' => $c['cajero_usuario'] ?? null,
        'ventas'      => isset($c['ventas']) ? (int) $c['ventas'] : null,
    ];
}

/** La caja abierta de quien está conectado ahora. */
function cajaAbierta(): ?array
{
    $u = usuarioActual();
    return $u ? cajaDeUsuario((int) $u['id']) : null;
}

function exigirCajaAbierta(callable $denegar): array
{
    $caja = cajaAbierta();
    if (!$caja) {
        $denegar();
    }
    return $caja;
}

/** ¿Es este usuario el dueño de la caja? */
function cajaPertenece(array $caja, int $usuarioId): bool
{
    return (int) $caja['usuario_id'] === $usuarioId;
}

/**
 * Cómo cerró cada medio de pago en una caja: lo que el sistema registró
 * contra lo que el cajero contó. Un descuadre en efectivo casi siempre
 * es una venta cobrada con el método equivocado, por eso se muestra
 * método por método y no solo el total.
 */
function conciliacionCaja(PDO $bd, int $cajaId): array
{
    $st = $bd->prepare(
        'SELECT m.id, m.nombre, m.icono, m.es_efectivo,
                COALESCE(SUM(v.total), 0) AS esperado,
                COALESCE(SUM(v.total), 0) AS declarado,
                COUNT(v.id) AS ventas
         FROM `medios_pago` m
         LEFT JOIN `ventas` v
                ON v.medio_pago_id = m.id
               AND v.caja_id = ?
               AND v.anulada = 0
         GROUP BY m.id, m.nombre, m.icono, m.es_efectivo
         ORDER BY m.es_efectivo DESC, m.orden, m.id'
    );
    $st->execute([$cajaId]);
    $filas = $st->fetchAll();

    $cierres = [];
    $st = $bd->prepare('SELECT medio_pago_id, declarado FROM `caja_cierre_metodos` WHERE caja_id = ?');
    $st->execute([$cajaId]);
    foreach ($st->fetchAll() as $f) {
        $cierres[(int) $f['medio_pago_id']] = (float) $f['declarado'];
    }

    $out = [];
    foreach ($filas as $f) {
        $id = (int) $f['id'];
        $esperado = redondear((float) $f['esperado']);
        $declarado = array_key_exists($id, $cierres) ? $cierres[$id] : $esperado;
        $out[] = [
            'id'         => $id,
            'nombre'     => $f['nombre'],
            'icono'      => $f['icono'] ?: '💳',
            'es_efectivo'=> (int) $f['es_efectivo'] === 1,
            'ventas'     => (int) $f['ventas'],
            'esperado'   => $esperado,
            'declarado'  => $declarado,
            'diferencia' => redondear($declarado - $esperado),
        ];
    }
    return $out;
}

/** Totales de una caja: venta por venta y por medio de pago. */
function resumenCaja(PDO $bd, int $cajaId): array
{
    $st = $bd->prepare(
        'SELECT COUNT(*) AS ventas, COALESCE(SUM(total),0) AS total,
                COALESCE(SUM(descuento),0) AS descuentos
         FROM `ventas` WHERE caja_id = ? AND anulada = 0'
    );
    $st->execute([$cajaId]);
    $r = $st->fetch() ?: [];

    $metodos = conciliacionCaja($bd, $cajaId);

    $esperado = 0.0;
    $declarado = 0.0;
    foreach ($metodos as $m) {
        $esperado  = redondear($esperado + $m['esperado']);
        $declarado = redondear($declarado + $m['declarado']);
    }

    return [
        'ventas'       => (int) ($r['ventas'] ?? 0),
        'total'        => redondear((float) ($r['total'] ?? 0)),
        'descuentos'   => redondear((float) ($r['descuentos'] ?? 0)),
        'metodos'      => $metodos,
        'esperado'     => $esperado,
        'declarado'    => $declarado,
        'diferencia'   => redondear($declarado - $esperado),
    ];
}

/**
 * El efectivo que debería haber en la gaveta al cerrar:
 * lo que se puso al abrir + lo cobrado en efectivo.
 */
function efectivoEsperadoCaja(PDO $bd, int $cajaId): float
{
    $st = $bd->prepare('SELECT monto_inicial FROM `cajas` WHERE id = ?');
    $st->execute([$cajaId]);
    $inicial = redondear((float) $st->fetchColumn());

    $st = $bd->prepare(
        'SELECT COALESCE(SUM(v.total),0) FROM `ventas` v
         JOIN `medios_pago` m ON m.id = v.medio_pago_id
         WHERE v.caja_id = ? AND v.anulada = 0 AND m.es_efectivo = 1'
    );
    $st->execute([$cajaId]);
    return redondear($inicial + (float) $st->fetchColumn());
}

/** ¿Puede este usuario anular esta venta? */
function puedeAnularVenta(array $venta): bool
{
    $u = usuarioActual();
    if (!$u) {
        return false;
    }
    // El administrador siempre.
    if ($u['rol'] === 'admin') {
        return true;
    }
    // El vendedor solo con SU caja todavía abierta.
    if ($venta['caja_id'] === null) {
        return false;
    }
    $caja = cajaDeUsuario((int) $u['id']);
    return $caja !== null && (int) $caja['id'] === (int) $venta['caja_id'];
}
