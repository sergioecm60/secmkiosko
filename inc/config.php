<?php
/**
 * Kiosco â€” configuracion general y conexion a la base de datos.
 * Compatible con PHP 8.1+ y MySQL 8 / MariaDB 10.4+
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Argentina/Buenos_Aires');

define('APP_NOMBRE',    'Kiosco');
define('DB_HOST',       getenv('KIOSCO_DB_HOST') ?: '127.0.0.1');
define('DB_USUARIO',    getenv('KIOSCO_DB_USER') ?: 'root');
define('DB_CLAVE',      getenv('KIOSCO_DB_PASS') ?: '');
define('DB_NOMBRE',     getenv('KIOSCO_DB_NAME') ?: 'kiosco');
define('DB_CHARSET',    'utf8mb4');
define('DB_PUERTO',     3306);

const OPCIONES_PDO = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

/** Conexion al servidor MySQL sin seleccionar base de datos. */
function pdoServidor(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PUERTO, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USUARIO, DB_CLAVE, OPCIONES_PDO);
    return $pdo;
}

/** Conexion a la base de datos del kiosco. */
function pdoBd(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!baseExiste()) {
        crearBase();
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PUERTO, DB_NOMBRE, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USUARIO, DB_CLAVE, OPCIONES_PDO);
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

function baseExiste(): bool
{
    try {
        $st = pdoServidor()->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
        $st->execute([DB_NOMBRE]);
        return ((int) $st->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function crearBase(): void
{
    $pdo = pdoServidor();
    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci',
        DB_NOMBRE,
        DB_CHARSET,
        DB_CHARSET
    ));
}

/** Indica si las tablas del kiosco ya estan creadas. */
function instalado(): bool
{
    try {
        $st = pdoBd()->query("SHOW TABLES LIKE 'productos'");
        $st->fetchColumn();
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/* ---------------------------------------------------------------
   Tablas
   --------------------------------------------------------------- */
function crearTablas(): array
{
    $pdo = pdoBd();
    $sentencias = [];

    $sentencias[] = 'CREATE TABLE IF NOT EXISTS `config` (
        `clave` VARCHAR(60) NOT NULL,
        `valor` TEXT NULL,
        PRIMARY KEY (`clave`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $sentencias[] = 'CREATE TABLE IF NOT EXISTS `productos` (
        `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nombre`    VARCHAR(120) NOT NULL,
        `codigo`    VARCHAR(40)  NULL,
        `categoria` VARCHAR(60)  NULL,
        `precio`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `stock`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `minimo`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `unidad`    VARCHAR(20)  NOT NULL DEFAULT "pieza",
        `foto`      MEDIUMTEXT   NULL,
        `sin_stock` TINYINT(1)   NOT NULL DEFAULT 0,
        `activo`    TINYINT(1)   NOT NULL DEFAULT 1,
        `creado`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ix_codigo`    (`codigo`),
        KEY `ix_categoria` (`categoria`),
        KEY `ix_nombre`    (`nombre`),
        KEY `ix_activo`    (`activo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $sentencias[] = 'CREATE TABLE IF NOT EXISTS `ventas` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `folio`      INT UNSIGNED NOT NULL DEFAULT 1,
        `fecha`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `subtotal`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `descuento`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `metodo`     VARCHAR(20)  NOT NULL DEFAULT "Efectivo",
        `recibido`   DECIMAL(12,2) NULL,
        `vuelto`    DECIMAL(12,2) NULL,
        `referencia` VARCHAR(60)  NULL,
        `nota`       VARCHAR(200) NULL,
        `usuario`    VARCHAR(50)  NULL,
        `cliente`    VARCHAR(80)  NULL,
        `anulada`    TINYINT(1)   NOT NULL DEFAULT 0,
        `anulada_en` DATETIME     NULL,
        `motivo`     VARCHAR(200) NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_folio` (`folio`),
        KEY `ix_fecha`   (`fecha`),
        KEY `ix_anulada` (`anulada`),
        KEY `ix_ventas_cliente` (`cliente`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $sentencias[] = 'CREATE TABLE IF NOT EXISTS `venta_items` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `venta_id`    INT UNSIGNED NOT NULL,
        `producto_id` INT UNSIGNED NULL,
        `nombre`      VARCHAR(120) NOT NULL,
        `codigo`      VARCHAR(40)  NULL,
        `precio`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `cantidad`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `importe`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (`id`),
        KEY `ix_venta`    (`venta_id`),
        KEY `ix_producto` (`producto_id`),
        CONSTRAINT `fk_item_venta` FOREIGN KEY (`venta_id`)
            REFERENCES `ventas` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $sentencias[] = 'CREATE TABLE IF NOT EXISTS `movimientos` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `fecha`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `tipo`            VARCHAR(20)  NOT NULL,
        `producto_id`     INT UNSIGNED NULL,
        `producto_nombre` VARCHAR(120) NOT NULL,
        `cantidad`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `stock_anterior`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `stock_actual`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `referencia`      VARCHAR(80)  NULL,
        `usuario`         VARCHAR(50)  NULL,
        `nota`            VARCHAR(80)  NULL,
        PRIMARY KEY (`id`),
        KEY `ix_fecha`    (`fecha`),
        KEY `ix_producto` (`producto_id`),
        KEY `ix_tipo`     (`tipo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    foreach ($sentencias as $sql) {
        $pdo->exec($sql);
    }
    return $sentencias;
}

/* ---------------------------------------------------------------
   Configuracion (tabla config, pares clave/valor)
   --------------------------------------------------------------- */
const CONFIG_INICIAL = [
    'negocio'  => 'Mi Kiosco',
    'moneda'   => '$',
    'direccion' => '',
    'telefono' => '',
    'pie'      => 'Â¡Gracias por su compra!',
    'logo'     => 'K',
    'folio'    => '1',
    'pin'      => '',
    'tema'     => 'claro',
    'factura'  => 'no',
];

function leerConfig(): array
{
    $cfg = CONFIG_INICIAL;
    try {
        foreach (pdoBd()->query('SELECT clave, valor FROM `config`') as $fila) {
            $cfg[$fila['clave']] = $fila['valor'];
        }
    } catch (Throwable $e) {
        // si falla se usan los valores por defecto
    }
    return $cfg;
}

function valorConfig(string $clave, $defecto = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = leerConfig();
    }
    return array_key_exists($clave, $cache) ? $cache[$clave] : $defecto;
}

function guardarConfig(string $clave, string $valor): void
{
    $st = pdoBd()->prepare(
        'INSERT INTO `config` (`clave`,`valor`) VALUES (?,?)
         ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`)'
    );
    $st->execute([$clave, $valor]);
}

/* ---------------------------------------------------------------
   Utilidades compartidas
   --------------------------------------------------------------- */

/** Redondea a 2 decimales evitando errores de coma flotante. */
function redondear($n): float
{
    return round((float) $n, 2);
}

/**
 * Dice si un codigo tiene sentido aceptarlo.
 *
 * Solo se aplica el control a los que parecen un EAN-13 de verdad (13 digitos).
 * Un codigo interno con letras ("PAPA-001") o de otra longitud se deja pasar:
 * hay negocios que identifican los productos asi.
 *
 * El ultimo digito del EAN-13 es un verificador: se calcula pesando los 12
 * primeros de 1,3,1,3... y tiene que completar la decena. Un codigo mal
 * tipeado casi siempre se detecta aca, y es justo el error que hace que un
 * lector de barras no encuentre nunca el producto.
 */
function codigoAceptable(string $codigo): bool
{
    $codigo = trim($codigo);
    if ($codigo === '') return true;                 // sin codigo: permitido
    if (!preg_match('/^\d{13}$/', $codigo)) return true;  // no es EAN-13: permitido

    $suma = 0;
    for ($i = 0; $i < 12; $i++) {
        $suma += ((int) $codigo[$i]) * ($i % 2 === 0 ? 1 : 3);
    }
    return ((10 - $suma % 10) % 10) === (int) $codigo[12];
}

/** Explicacion lista para mostrar cuando un EAN-13 no cierra. */
function codigoMalExplicacion(string $codigo): string
{
    $suma = 0;
    for ($i = 0; $i < 12; $i++) {
        $suma += ((int) $codigo[$i]) * ($i % 2 === 0 ? 1 : 3);
    }
    return (string) ((10 - $suma % 10) % 10);
}

/**
 * Cantidades de carga rapida para la venta.
 * Si el producto define sus propios presets se usan esos; si no,
 * se sugiere un juego segun la unidad base (granel vs. por unidad).
 */
function presetsDe(string $unidad, $presets = null): array
{
    $crudos = [];
    if (is_string($presets) && trim($presets) !== '') {
        foreach (explode(',', $presets) as $p) {
            $p = trim(str_replace(',', '.', $p));
            if ($p !== '' && is_numeric($p) && (float) $p > 0) {
                $crudos[] = (float) $p;
            }
        }
    }
    if (!$crudos) {
        $u = strtolower(trim($unidad));
        $crudos = ($u === 'kg' || $u === 'g' || $u === 'litro' || $u === 'l')
            ? [0.1, 0.2, 0.25, 0.5, 1]
            : [1, 2, 3, 6, 12];
    }

    $salida = [];
    foreach ($crudos as $v) {
        $v = redondear($v);
        if ($v > 0 && !in_array($v, $salida, true)) {
            $salida[] = $v;
        }
    }
    sort($salida);
    return $salida;
}

/**
 * Catalogos de formatos para los datalist del formulario. No son cerrados:
 * el usuario siempre puede escribir uno propio ("bidon 20 L", "atado").
 * Los multiplos (decena, centena, docena) traen su factorSugerido para que
 * el factor se complete solo.
 */
function catalogoFormatos(string $ambito): array
{
    if ($ambito === 'compra') {
        return [
            ['nombre' => 'unidad',      'factor' => 1],
            ['nombre' => 'kg',          'factor' => 1],
            ['nombre' => 'bolsa',       'factor' => null],
            ['nombre' => 'caja',        'factor' => null],
            ['nombre' => 'maple',       'factor' => null],
            ['nombre' => 'cajon',       'factor' => null],
            ['nombre' => 'decena',      'factor' => 10],
            ['nombre' => 'docena',      'factor' => 12],
            ['nombre' => 'centena',     'factor' => 100],
            ['nombre' => 'bidon',       'factor' => null],
            ['nombre' => 'paleta',      'factor' => null],
            ['nombre' => 'atado',       'factor' => null],
        ];
    }
    return [
        ['nombre' => 'unidad',   'factor' => 1],
        ['nombre' => 'media',    'factor' => null],
        ['nombre' => 'docena',   'factor' => 12],
        ['nombre' => 'decena',   'factor' => 10],
        ['nombre' => 'centena',  'factor' => 100],
        ['nombre' => '2x1',      'factor' => 2],
        ['nombre' => '3x2',      'factor' => 3],
        ['nombre' => 'oferta',   'factor' => null],
        ['nombre' => 'kg',       'factor' => 1],
        ['nombre' => 'g',        'factor' => null],
        ['nombre' => 'L',        'factor' => 1],
        ['nombre' => 'mL',       'factor' => null],
        ['nombre' => 'botella',  'factor' => null],
    ];
}

/**
 * Nombre legible para un formato heredado de los presets.
 * 12 -> "docena", 6 -> "6 unidades"; y para fracciones de granel se usa la
 * unidad chica: 0.25 kg -> "250 g", 0.5 L -> "500 mL".
 */
function nombreFormatoMigrado(float $factor, string $unidadBase = ''): string
{
    $multiplos = [2 => '2x1', 3 => '3x2', 6 => '6 unidades', 10 => 'decena', 12 => 'docena', 24 => 'caja x24', 100 => 'centena'];
    $entero = (int) round($factor);
    if (abs($factor - $entero) < 0.0001 && isset($multiplos[$entero])) {
        return $multiplos[$entero];
    }
    if ($factor >= 1) {
        return rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.') . ' u';
    }
    $u = strtolower(trim($unidadBase));
    if ($u === 'kg' || $u === 'g') {
        return rtrim(rtrim(number_format($factor * 1000, 2, '.', ''), '0'), '.') . ' g';
    }
    if ($u === 'l' || $u === 'litro' || $u === 'ml') {
        return rtrim(rtrim(number_format($factor * 1000, 2, '.', ''), '0'), '.') . ' mL';
    }
    return rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.') . ' u';
}

/** Formatos de un producto, ya resueltos (precio, margen, equivalencias). */
function formatosProducto(int $productoId, string $ambito, float $costoBase = 0.0, float $precioBase = 0.0): array
{
    if ($productoId <= 0) {
        return [];
    }
    try {
        $st = pdoBd()->prepare(
            'SELECT id, unidad, factor, precio, margen, predet
               FROM productos_formatos
              WHERE producto_id = ? AND ambito = ?
           ORDER BY predet DESC, factor ASC, id ASC'
        );
        $st->execute([$productoId, $ambito]);
    } catch (Throwable $e) {
        return [];
    }

    $lista = [];
    foreach ($st->fetchAll() as $f) {
        $factor = (float) $f['factor'] > 0 ? (float) $f['factor'] : 1.0;
        $fila = [
            'id'      => (int) $f['id'],
            'unidad'  => $f['unidad'],
            'factor'  => $factor,
            'precio'  => $f['precio'] !== null ? (float) $f['precio'] : null,
            'margen'  => $f['margen'] !== null ? (float) $f['margen'] : null,
            'predet'  => (int) $f['predet'] === 1,
        ];
        if ($ambito === 'compra') {
            // El costo por unidad base sale de dividir el precio del formato.
            $fila['costo_base'] = $fila['precio'] !== null && $factor > 0
                ? redondear($fila['precio'] / $factor) : null;
        } else {
            $fila['costo_base'] = redondear($costoBase);
            $fila['precio_final'] = precioVentaDe($f, $costoBase, $precioBase);
            // Un formato migrado sin precio propio vale el precio del producto
            // por cada unidad base que contiene, que es como se cobraba antes.
            $fila['precio_final'] = precioVentaDe($f, $costoBase, $precioBase);
            if ($fila['precio_final'] > 0.0
                && $f['precio'] === null && ($f['margen'] === null || (float) $f['margen'] === 0.0)) {
                $fila['heredado'] = true;
            }
        }
        $lista[] = $fila;
    }
    return $lista;
}

/**
 * Precio final de un formato de venta: lo que paga el cliente por UNA unidad
 * de ese formato (por un maple, por una docena, por un pack de 6).
 *   - con precio fijo se usa tal cual;
 *   - con margen se calcula sobre el costo y se multiplica por la cantidad
 *     que trae el formato, porque el margen es por unidad base;
 *   - sin ninguno de los dos vale el precio del producto por la cantidad.
 */
function precioVentaDe(array $f, float $costoBase, float $precioBase = 0.0): float
{
    $factor = isset($f['factor']) ? (float) $f['factor'] : 1.0;
    if ($factor <= 0) {
        $factor = 1.0;
    }
    if (isset($f['precio']) && $f['precio'] !== null && (float) $f['precio'] > 0) {
        return redondear($f['precio']);
    }
    if (isset($f['margen']) && $f['margen'] !== null && (float) $f['margen'] !== 0.0) {
        return redondear($costoBase * (1 + ((float) $f['margen'] / 100)) * $factor);
    }
    return $precioBase > 0.0 ? redondear($precioBase * $factor) : 0.0;
}

function texto($s, int $max = 200): string
{
    return mb_substr(trim((string) $s), 0, $max);
}

function numero($n, float $defecto = 0.0): float
{
    if ($n === null || $n === '' || !is_numeric($n)) {
        return $defecto;
    }
    return (float) $n;
}

function entero($n, int $defecto = 0): int
{
    return is_numeric($n) ? (int) $n : $defecto;
}

/** Crea la carpeta de respaldos si no existe. */
function carpetaDatos(string $sub = ''): string
{
    // Este archivo vive en inc/, asi que la carpeta de respaldos esta un nivel arriba.
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'datos';
    if (!is_dir($base)) {
        @mkdir($base, 0775, true);
    }
    $ruta = $sub === '' ? $base : $base . DIRECTORY_SEPARATOR . $sub;
    if (!is_dir($ruta)) {
        @mkdir($ruta, 0775, true);
    }
    return $ruta;
}

/** Registra un movimiento de inventario (kardex). */
function registrarMovimiento(array $d): void
{
    $st = pdoBd()->prepare(
        'INSERT INTO `movimientos`
         (`fecha`,`tipo`,`producto_id`,`producto_nombre`,`cantidad`,`stock_anterior`,`stock_actual`,`referencia`,`usuario`,`proveedor_id`,`documento`,`nota`)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $d['fecha'] ?? date('Y-m-d H:i:s'),
        $d['tipo'],
        $d['producto_id'],
        $d['producto_nombre'],
        redondear($d['cantidad']),
        redondear($d['stock_anterior'] ?? 0),
        redondear($d['stock_actual'] ?? 0),
        $d['referencia'] ?? null,
        $d['usuario'] ?? null,
        $d['proveedor_id'] ?? null,
        $d['documento'] ?? null,
        // Que formato de compra o de venta originÃ³ el movimiento.
        $d['nota'] ?? null,
    ]);
}

/**
 * Nombre que queda impreso en el ticket y en el historial de ventas.
 * Si hay alguien con sesiÃ³n iniciada se usa su nombre real; si no,
 * se cae al valor de configuraciÃ³n (instalaciones viejas, respaldo, etc).
 */
function nombreUsuario(): string
{
    if (function_exists('usuarioActual')) {
        $u = usuarioActual();
        if ($u) {
            return texto($u['nombre'] . ' (' . $u['usuario'] . ')', 50);
        }
    }
    return texto(valorConfig('operador', ''), 50) ?: 'Cajero';
}

/* ---------------------------------------------------------------
   Migraciones: agregan tablas y columnas nuevas sin romper installs viejos
   --------------------------------------------------------------- */
function columnaExiste(string $tabla, string $columna): bool
{
    $st = pdoBd()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([DB_NOMBRE, $tabla, $columna]);
    return ((int) $st->fetchColumn()) > 0;
}

function tablaExiste(string $tabla): bool
{
    $st = pdoBd()->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $st->execute([DB_NOMBRE, $tabla]);
    return ((int) $st->fetchColumn()) > 0;
}

function agregarColumna(string $tabla, string $definicion): void
{
    // Quita las comillas invertidas para comparar contra COLUMN_NAME
    $col = trim(str_replace('`', '', preg_split('/\s+/', trim($definicion))[0]));
    if (!columnaExiste($tabla, $col)) {
        pdoBd()->exec("ALTER TABLE `$tabla` ADD COLUMN $definicion");
    }
}

/** Idem para indices: sin esto, un CREATE INDEX repetido revienta la migracion. */
function agregarIndice(string $tabla, string $indice, string $definicion): void
{
    $st = pdoBd()->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $st->execute([DB_NOMBRE, $tabla, $indice]);
    if ((int) $st->fetchColumn() === 0) {
        pdoBd()->exec("CREATE INDEX `$indice` ON `$tabla` ($definicion)");
    }
}

/**
 * Aplica las mejoras de esquema. Es idempotente: se puede correr
 * cuantas veces se quiera sin romper nada.
 */
function actualizarEsquema(): array
{
    $hechas = [];
    $pdo = pdoBd();

    // --- 1. Precio de costo y observaciones en productos ---
    agregarColumna('productos', '`costo` DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    agregarColumna('productos', '`observaciones` TEXT NULL');
    agregarColumna('productos', '`proveedor_id` INT UNSIGNED NULL');
    $hechas[] = 'productos: costo, observaciones, proveedor_id';

    // --- 1b. Compra en mÃºltiplos y venta fraccionada (granel) ---
    // El stock SIEMPRE se cuenta en la unidad base (kg, unidad, etc).
    // unidad_compra/factor_compra sirven para comprar en maples, cajones,
    // bolsas o docenas: 1 maple = 30 unidades, 1 bolsa = 10 kg.
    agregarColumna('productos', '`unidad_compra` VARCHAR(20) NULL');
    agregarColumna('productos', '`factor_compra` DECIMAL(12,3) NOT NULL DEFAULT 1.000');
    agregarColumna('productos', '`precio_compra` DECIMAL(12,2) NULL');
    agregarColumna('productos', '`presets` VARCHAR(120) NULL');
    $hechas[] = 'productos: unidad_compra, factor_compra, precio_compra, presets';

    // --- 1c. Formatos de compra y de venta (uno por ambito) ---
    // El stock SIEMPRE se cuenta en la unidad base. Un "formato" es la
    //Presentacion en que se compra o se vende: maple, cajon, bolsa, caja,
    // unidad, docena, 2x1... factor = cuantas unidades base vale ese formato.
    $pdo->exec('CREATE TABLE IF NOT EXISTS `productos_formatos` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `producto_id` INT UNSIGNED NOT NULL,
        `ambito`     VARCHAR(10)   NOT NULL DEFAULT "venta",
        `unidad`     VARCHAR(20)   NOT NULL,
        `factor`     DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
        `precio`     DECIMAL(12,2) NULL,
        `margen`     DECIMAL(8,2)  NULL,
        `predet`     TINYINT(1)    NOT NULL DEFAULT 0,
        `creado`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ix_prod_ambito` (`producto_id`, `ambito`),
        KEY `ix_prod` (`producto_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla productos_formatos';

    // --- 2. Proveedores ---
    $pdo->exec('CREATE TABLE IF NOT EXISTS `proveedores` (
        `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nombre`        VARCHAR(120) NOT NULL,
        `telefono`      VARCHAR(40)  NULL,
        `email`         VARCHAR(120) NULL,
        `observaciones` TEXT         NULL,
        `activo`        TINYINT(1)   NOT NULL DEFAULT 1,
        `creado`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ix_nombre` (`nombre`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla proveedores';

    // --- 3. Medios de pago configurables ---
    $pdo->exec('CREATE TABLE IF NOT EXISTS `medios_pago` (
        `id`                SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nombre`            VARCHAR(40)  NOT NULL,
        `icono`             VARCHAR(8)   NULL,
        `exige_referencia`  TINYINT(1)   NOT NULL DEFAULT 0,
        `es_efectivo`       TINYINT(1)   NOT NULL DEFAULT 0,
        `activo`            TINYINT(1)   NOT NULL DEFAULT 1,
        `orden`             SMALLINT     NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `ix_activo` (`activo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    agregarColumna('medios_pago', '`es_efectivo` TINYINT(1) NOT NULL DEFAULT 0');
    $hechas[] = 'tabla medios_pago';

    // Efectivo: recibe vuelto. Tarjeta/transferencia: solo referencia.
    $pdo->prepare('UPDATE medios_pago SET es_efectivo = 1, exige_referencia = 0 WHERE LOWER(nombre) LIKE ?')
       ->execute(['%efectivo%']);

    // --- 4. Proveedor y documento en los movimientos de mercancia ---
    agregarColumna('movimientos', '`proveedor_id` INT UNSIGNED NULL');
    agregarColumna('movimientos', '`documento` VARCHAR(40) NULL');
    // Que formato de compra o de venta produjo el movimiento.
    agregarColumna('movimientos', '`nota` VARCHAR(80) NULL');
    $hechas[] = 'movimientos: proveedor_id, documento';

    // El costo se congela al vender: si maÃ±ana suben el precio de compra,
    // el margen historico de las ventas viejas no debe cambiar.
    agregarColumna('venta_items', '`costo_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    $hechas[] = 'venta_items: costo_unitario';

    // Que formato de venta se uso (docena, 2x1, maple...) y cuantas unidades
    // base le correspondieron, para que el ticket y el historial sean legibles.
    agregarColumna('venta_items', '`formato_id` INT UNSIGNED NULL');
    agregarColumna('venta_items', '`formato_unidad` VARCHAR(20) NULL');
    agregarColumna('venta_items', '`formato_cantidad` DECIMAL(12,3) NULL');
    $hechas[] = 'venta_items: formato_id, formato_unidad, formato_cantidad';

    // --- 5. Proveedor en la venta (de whom compramos, no, pero queda el dato del cajero) ---
    agregarColumna('ventas', '`proveedor_id` INT UNSIGNED NULL');
    $hechas[] = 'ventas: proveedor_id';

    // --- 6. Medios de pago por defecto ---
    $n = (int) $pdo->query('SELECT COUNT(*) FROM `medios_pago`')->fetchColumn();
    if ($n === 0) {
        $st = $pdo->prepare('INSERT INTO `medios_pago` (`nombre`,`icono`,`exige_referencia`,`orden`,`es_efectivo`) VALUES (?,?,?,?,?)');
        $defectos = [
            ['Efectivo',       'ðŸ’µ', 0, 1, 1],
            ['Tarjeta',        'ðŸ’³', 1, 2, 0],
            ['Transferencia',  'ðŸ“±', 1, 3, 0],
            ['Mercado Pago',   'ðŸ…¿ï¸', 1, 4, 0],
        ];
        foreach ($defectos as $d) {
            $st->execute($d);
        }
        $hechas[] = 'medios de pago por defecto';
    }

    /* --- 7. Usuarios, roles y cajas ---------------------------------
       El login y el control de caja viven acÃ¡. La clave se guarda
       hasheada (password_hash de PHP), nunca en texto plano.       */
    $pdo->exec('CREATE TABLE IF NOT EXISTS `usuarios` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `usuario`    VARCHAR(40)  NOT NULL,
        `nombre`     VARCHAR(80)  NOT NULL,
        `clave`      VARCHAR(255) NOT NULL,
        `rol`        VARCHAR(10)  NOT NULL DEFAULT "vendedor",
        `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
        `debe_cambiar_clave` TINYINT(1) NOT NULL DEFAULT 0,
        `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ultimo_ingreso` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_usuario` (`usuario`),
        KEY `ix_rol` (`rol`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla usuarios';

    $pdo->exec('CREATE TABLE IF NOT EXISTS `cajas` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `usuario_id` INT UNSIGNED NOT NULL,
        `abierta_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `monto_inicial` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `cerrada_en` DATETIME     NULL,
        `efectivo_esperado`  DECIMAL(12,2) NULL,
        `efectivo_declarado` DECIMAL(12,2) NULL,
        `total_esperado`     DECIMAL(12,2) NULL,
        `total_declarado`    DECIMAL(12,2) NULL,
        `diferencia`         DECIMAL(12,2) NULL,
        `motivo`             VARCHAR(200) NULL,
        `abierta`            TINYINT(1)   NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `ix_usuario` (`usuario_id`),
        KEY `ix_abierta` (`abierta`),
        KEY `ix_abierta_en` (`abierta_en`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla cajas';

    // ConciliaciÃ³n mÃ©todo por mÃ©todo al cerrar: lo que el sistema dice
    // que entrÃ³ contra lo que el cajero contÃ³ de verdad.
    $pdo->exec('CREATE TABLE IF NOT EXISTS `caja_cierre_metodos` (
        `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `caja_id`       INT UNSIGNED NOT NULL,
        `medio_pago_id` SMALLINT UNSIGNED NOT NULL,
        `esperado`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `declarado`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `diferencia`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (`id`),
        KEY `ix_caja` (`caja_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla caja_cierre_metodos';

    // Cada venta queda atada a la caja en la que se cobrÃ³. Sin esto el
    // cierre no puede conciliar y el vendedor se come los descuadres.
    agregarColumna('ventas', '`caja_id` INT UNSIGNED NULL');
    agregarColumna('ventas', '`medio_pago_id` SMALLINT UNSIGNED NULL');
    agregarColumna('ventas', '`anulada_por` VARCHAR(50) NULL');
    // El reparto se cobra aparte de los productos. Va como columna propia (y
    // no metido en el descuento) para que el ticket y los reportes digan
    // cuanto fue reparto y cuanto comida.
    agregarColumna('ventas', '`envio` DECIMAL(12,2) NOT NULL DEFAULT 0.00');
    $hechas[] = 'ventas: caja_id, medio_pago_id, anulada_por, envio';

    /* --- 8. Comandas de cocina y delivery ---------------------------
       El pedido se cobra como una venta normal, pero ademÃ¡s queda una
       "comanda": la lista que cocina tiene que preparar y a quiÃ©n
       entregÃ¡rsela. Los atajos son los botones que el vendedor toca para
       no tener que escribir (1 pancho, 1 miga, 1 coc 2.25...). Un atajo
       puede apuntar a un producto del catalogo â€”entonces cobra y descuenta
       stock como cualquier otra lineaâ€” o ser solo texto para la cocina. */
    $pdo->exec('CREATE TABLE IF NOT EXISTS `zonas` (
        `id`      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nombre`  VARCHAR(60)  NOT NULL,
        `costo`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `activo`  TINYINT(1)   NOT NULL DEFAULT 1,
        `orden`   SMALLINT     NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `ix_activo` (`activo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla zonas';

    // Categorias: la lista maestra de las que el administrador maneja.
    // productos.categoria sigue siendo texto (no se quiere romper el
    // buscador ni los reportes), pero el nombre tiene que existir aca.
    $pdo->exec('CREATE TABLE IF NOT EXISTS `categorias` (
        `id`     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nombre` VARCHAR(60)  NOT NULL,
        `activo` TINYINT(1)   NOT NULL DEFAULT 1,
        `orden`  SMALLINT     NOT NULL DEFAULT 0,
        `cocina` TINYINT(1)   NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_nombre` (`nombre`),
        KEY `ix_activo` (`activo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla categorias';

    // Primera vez: se traen las categorias que ya/usan los productos.
    // MySQL no distingue mayusculas con utf8mb4_unicode_ci, asi que "Bebidas"
    // y "bebidas" son la misma fila: eso evita que Vender muestre dos fichas
    // para lo mismo. Los productos quedan con la forma que se puso primero.
    $st = $pdo->query("SELECT DISTINCT categoria FROM productos
                       WHERE categoria IS NOT NULL AND TRIM(categoria) <> ''
                       ORDER BY categoria");
    foreach ($st->fetchAll() as $fila) {
        $nombre = trim($fila['categoria']);
        if ($nombre === '') continue;
        $pdo->prepare('INSERT IGNORE INTO categorias (nombre, orden) VALUES (?, 0)')->execute([$nombre]);
    }

    // "Venta libre" la usa el atajo de carga rapida de Productos (07_productos.js):
    // es la unica categoria que el sistema necesita siempre, asi que se siembra
    // aunque ningun producto la este usando todavia.
    $pdo->prepare('INSERT IGNORE INTO categorias (nombre, orden) VALUES (?, 999)')->execute(['Venta libre']);

    // Y una de cocina, vacia y ya marcada. El cobro manda a la comanda lo que
    // sea de una categoria con cocina=1, asi que sin al menos una el sistema
    // queda entero pero sin comanda: se vende de mostrador y no se puede
    // probar el flujo mixto. Antes esta categoria solo existia si alguien la
    //aba a marcar a mano, y en una instalacion nueva nadie tiene motivo de
    // saber que tiene que hacerlo. Viene vacia a proposito: los productos los
    // carga el que abre el kiosco, y se renombra desde Ajustes al gusto.
    $st = $pdo->prepare('INSERT IGNORE INTO categorias (nombre, orden, cocina)
                         VALUES (?, 998, 1)');
    $st->execute(['Comidas y Tragos']);
    $hechas[] = 'categoria de cocina para la comanda';

    // Y ahora al reves: los productos toman la forma canonica de su fila.
    // Si alguien escribio "bebidas" a mano queda "Bebidas" y las fichas de
    // Vender no se parten en dos.
    $pdo->exec('UPDATE productos p
                JOIN categorias c ON p.categoria = c.nombre COLLATE utf8mb4_unicode_ci
                SET p.categoria = c.nombre
                WHERE p.categoria <> c.nombre');

    // Para las bases que ya venian de la version 9: la columna "cocina" no
    // estaba en el CREATE de arriba porque esa tabla ya existia. agregarColumna
    // no hace nada si la columna ya esta.
    agregarColumna('categorias', '`cocina` TINYINT(1) NOT NULL DEFAULT 0 AFTER `orden`');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `comandas` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `venta_id`     INT UNSIGNED NULL,
        `folio`        INT UNSIGNED NULL,
        `cliente`      VARCHAR(80)  NOT NULL,
        `telefono`     VARCHAR(40)  NULL,
        `direccion`    VARCHAR(160) NULL,
        `zona_id`      SMALLINT UNSIGNED NULL,
        `zona_nombre`  VARCHAR(60)  NULL,
        `tipo`         VARCHAR(20)  NOT NULL DEFAULT "delivery",
        `lugar`        VARCHAR(60)  NULL,
        `notas`        VARCHAR(255) NULL,
        `estado`       VARCHAR(20)  NOT NULL DEFAULT "pendiente",
        `total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `envio`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `usuario`      VARCHAR(50)  NULL,
        `creado`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `actualizado`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `cerrado_en`   DATETIME     NULL,
        PRIMARY KEY (`id`),
        KEY `ix_venta`   (`venta_id`),
        KEY `ix_estado`  (`estado`),
        KEY `ix_creado`  (`creado`),
        KEY `ix_cliente` (`cliente`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    // Para instalaciones que ya tenian la tabla sin esta columna.
    agregarColumna('comandas', '`envio` DECIMAL(12,2) NOT NULL DEFAULT 0.00');
    $hechas[] = 'tabla comandas';

    $pdo->exec('CREATE TABLE IF NOT EXISTS `comanda_items` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `comanda_id`  INT UNSIGNED NOT NULL,
        `producto_id` INT UNSIGNED NULL,
        `cantidad`    DECIMAL(12,2) NOT NULL DEFAULT 1.00,
        `texto`       VARCHAR(160) NOT NULL,
        `detalle`     VARCHAR(160) NULL,
        `es_producto` TINYINT(1)   NOT NULL DEFAULT 0,
        `estado`      VARCHAR(20)  NOT NULL DEFAULT "pendiente",
        `creado`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ix_comanda` (`comanda_id`),
        CONSTRAINT `fk_ci_comanda` FOREIGN KEY (`comanda_id`)
            REFERENCES `comandas` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla comanda_items';

    $pdo->exec('CREATE TABLE IF NOT EXISTS `comanda_atajos` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `seccion`        VARCHAR(40)  NOT NULL DEFAULT "Comidas",
        `etiqueta`       VARCHAR(60)  NOT NULL,
        `producto_id`    INT UNSIGNED NULL,
        `formato_unidad` VARCHAR(20)  NULL,
        `texto`          VARCHAR(160) NULL,
        `detalle`        VARCHAR(160) NULL,
        `orden`          SMALLINT     NOT NULL DEFAULT 0,
        `activo`         TINYINT(1)   NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `ix_activo` (`activo`),
        KEY `ix_orden`  (`orden`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla comanda_atajos';

    /* --- Contador de folios ----------------------------------------------
       Calcular el folio con MAX(folio)+1 sobre `ventas` no es seguro: dos
       cajeros cobrando en el mismo instante leen el mismo maximo y sacan
       el mismo folio. Como `ventas.folio` es UNIQUE, uno de los dos falla
       con error de clave duplicada y el cajero ve un fallo sin explicacion.

       Se resuelve con una fila contadora propia, bloqueada con FOR UPDATE
       dentro de la transaccion: el segundo cajero espera a que el primero
       termine de reservar su folio. */
    $pdo->exec('CREATE TABLE IF NOT EXISTS `contadores` (
        `nombre` VARCHAR(30)  NOT NULL,
        `valor`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`nombre`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $hechas[] = 'tabla contadores';

    // El contador nunca arranca por debajo del folio ya emitido ni del
    // configurado a mano, para no reasignar un numero que ya salio.
    $stC = $pdo->query('SELECT COALESCE(MAX(folio),0) FROM ventas')->fetchColumn();
    $cfgFolio = (int) ($pdo->query("SELECT valor FROM `config` WHERE clave = 'folio'")->fetchColumn() ?: 1);
    $pdo->prepare('INSERT INTO `contadores` (`nombre`,`valor`) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE `valor` = GREATEST(`valor`, VALUES(`valor`))')
        ->execute(['folio', max(1, (int) $stC, $cfgFolio)]);

    /* --- 9. Productos que se venden sin controlar stock ---------------
       Una venta espontanea (el pancho que pidio Pepe, una pizza armada en el
       momento) no viene de un stock que se cuente: se carga el producto en
       el instante, con su precio, y se cobra. El flag `sin_stock` marca
       esos productos para que no se descuenten existencias ni entren en los
       avisos de "quedan pocas" y en la valuacion de inventario. */
    agregarColumna('productos', '`sin_stock` TINYINT(1) NOT NULL DEFAULT 0');
    $hechas[] = 'productos: sin_stock';

    // Zonas y atajos por defecto, solo la primera vez.
    if ((int) $pdo->query('SELECT COUNT(*) FROM `zonas`')->fetchColumn() === 0) {
        $stZ = $pdo->prepare('INSERT INTO `zonas` (`nombre`,`costo`,`orden`) VALUES (?,?,?)');
        foreach ([['Centro', 0, 1], ['Barrio Norte', 500, 2], ['Barrio Sur', 800, 3]] as $z) {
            $stZ->execute($z);
        }
        $hechas[] = 'zonas por defecto';
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM `comanda_atajos`')->fetchColumn() === 0) {
        $stA = $pdo->prepare('INSERT INTO `comanda_atajos` (`seccion`,`etiqueta`,`texto`,`detalle`,`orden`)
                              VALUES (?,?,?,?,?)');
        // texto = lo que lee la cocina. producto_id queda en NULL hasta que
        // el administrador lo linkea al catalogo desde Ajustes.
        $defectos = [
            ['Comidas', '1 hamburguesa', 'Hamburguesa completa', 'con lechuga, tomate y queso', 1],
            ['Comidas', '1 pancho',      'Pancho',                 'hamburguesa completa',           2],
            ['Comidas', '1 miga',        'SÃ¡ndwich de miga',       null,                             3],
            ['Comidas', '1 milanesa',    'SÃ¡ndwich de milanesa',   null,                             4],
            ['Comidas', '1 pollo',       'Pollo con papas',        null,                             5],
            ['Bebidas', '1 cerveza',     'Cerveza',                null,                             1],
            ['Bebidas', '1 coc 1L',      'Coca Cola',              'botella de 1 litro',             2],
            ['Bebidas', '1 coc 1.5',     'Coca Cola',              'botella de litro y medio',       3],
            ['Bebidas', '1 coc 2.25',    'Coca Cola',              'botella de 2 litros y cuarto',   4],
            ['Tragos',  '1 vino',        'Vaso de vino',           null,                             1],
            ['Tragos',  '1 gin',         'Gin',                    null,                             2],
            ['Tragos',  '1 pina',        'PiÃ±a colada',            null,                             3],
            ['Tragos',  '1 gancia',      'Gancia fernet',          null,                             4],
            ['Tragos',  '1 mesclado',    'Trago mesclado',         null,                             5],
        ];
        foreach ($defectos as $a) {
            $stA->execute($a);
        }
        $hechas[] = 'atajos de comanda por defecto';
    }

    // --- 1d. Migrar los formatos viejos a la tabla nueva ---
    // Los productos que ya tengan unidad_compra se vuelven a comprar por ese
    // formato; los presets de venta se convierten en formatos de 1 unidad base
    // cada uno, que es como venÃ­an funcionando.
    $st = $pdo->query('SELECT id, nombre, unidad, precio, costo, unidad_compra, factor_compra,
                              precio_compra, presets
                       FROM productos');
    $migrados = 0;
    $hayFormatos = (int) $pdo->query('SELECT COUNT(*) FROM productos_formatos')->fetchColumn() > 0;
    if (!$hayFormatos) {
        $ins = $pdo->prepare('INSERT INTO productos_formatos (producto_id, ambito, unidad, factor, precio, margen, predet)
                              VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($st->fetchAll() as $pr) {
            $uCompra = trim((string) ($pr['unidad_compra'] ?? ''));
            if ($uCompra !== '') {
                $ins->execute([(int) $pr['id'], 'compra', mb_substr($uCompra, 0, 20),
                               (float) ($pr['factor_compra'] ?: 1) ?: 1,
                               $pr['precio_compra'] !== null ? (float) $pr['precio_compra'] : null, null, 1]);
            }
            // Formato base: una unidad base al precio del producto.
            $ins->execute([(int) $pr['id'], 'venta', (string) ($pr['unidad'] ?: 'pieza'), 1,
                           (float) $pr['precio'], null, 1]);
            // Los presets pasan a ser formatos de venta (docena, 2x1, etc.).
            foreach (presetsDe((string) ($pr['unidad'] ?: 'pieza'), $pr['presets']) as $q) {
                if ((float) $q === 1.0) {
                    continue;
                }
                $ins->execute([(int) $pr['id'], 'venta', nombreFormatoMigrado((float) $q, (string) ($pr['unidad'] ?: '')), (float) $q, null, null, 0]);
            }
            $migrados++;
        }
        if ($migrados > 0) {
            $hechas[] = "formatos migrados de $migrados producto(s)";
        }
    }

    // --- 10. Nombre de quien paga, en la propia venta ---
    /* El nombre ya vivia solo en la comanda ("quien retira") y solo se
       preguntaba cuando la venta tenia algo para cocina. Con esto la venta
       guarda a quien se le cobro, asi que el corte de caja puede saber a quien
       se le cobro cada venta. El campo es opcional: vacio significa Mostrador,
       que es lo mas comun en un kiosco. */
    agregarColumna('ventas', '`cliente` VARCHAR(80) NULL AFTER `usuario`');
    agregarIndice('ventas', 'ix_ventas_cliente', '`cliente`');
    $hechas[] = 'ventas: cliente';

    return $hechas;
}

/**
 * Las instalaciones viejas nunca corren instalar.php, asi que el esquema
 * nuevo se aplica solo en el primer request. La marca en `config` evita
 * repetir el trabajo: solo corre de nuevo si el codigo pide una version mayor.
 */
const ESQUEMA_VERSION = 11;

function migrarSiHaceFalta(): void
{
    if (!instalado()) {
        return;
    }
    try {
        $st = pdoBd()->prepare('SELECT valor FROM config WHERE clave = ? LIMIT 1');
        $st->execute(['esquema_version']);
        $actual = (int) ($st->fetchColumn() ?: 0);
        if ($actual >= ESQUEMA_VERSION) {
            return;
        }
        actualizarEsquema();
        pdoBd()->prepare('INSERT INTO config (clave, valor) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
            ->execute(['esquema_version', (string) ESQUEMA_VERSION]);
    } catch (Throwable $e) {
        // Si la migracion falla no se rompe la operacion: el esquema viejo
        // sigue funcionando y se reintenta en el proximo request.
    }
}

migrarSiHaceFalta();

/* ---------------------------------------------------------------
   Respaldo: genera un archivo .sql con toda la base de datos
   --------------------------------------------------------------- */

/** Escapa un valor para una cadena de MySQL. */
function escaparSQL($v): string
{
    if ($v === null) {
        return 'NULL';
    }
    return "'" . addslashes((string) $v) . "'";
}

/**
 * Construye el volcado SQL completo. Si $archivo no es null, lo escribe en disco
 * y devuelve la ruta; en cualquier otro caso devuelve el contenido.
 */
function volcarSQL(?string $archivo = null): string
{
    $pdo = pdoBd();
    $salida = '';

    $salida .= "-- ==================================================\n";
    $salida .= "-- " . APP_NOMBRE . " â€” respaldo de la base de datos\n";
    $salida .= '-- Generado: ' . date('d/m/Y H:i:s') . "\n";
    $salida .= "-- Base: " . DB_NOMBRE . " (" . PHP_VERSION . " / MySQL " . $pdo->query('SELECT VERSION()')->fetchColumn() . ")\n";
    $salida .= "-- Para restaurar: mysql -u root kiosco < este_archivo.sql\n";
    $salida .= "-- ==================================================\n\n";
    $salida .= "SET NAMES utf8mb4;\n";
    $salida .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $contador = 0;

    foreach ($tablas as $tabla) {
        $fila = $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_ASSOC);
        $salida .= "-- ---------------------------------------------------------\n";
        $salida .= "-- Tabla: $tabla\n";
        $salida .= "-- ---------------------------------------------------------\n";
        $salida .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $salida .= $fila['Create Table'] . ";\n\n";

        $total = (int) $pdo->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
        if ($total === 0) {
            $salida .= "-- (sin registros)\n\n";
            continue;
        }
        $salida .= "INSERT INTO `$tabla` VALUES\n";

        $lectura = $pdo->query("SELECT * FROM `$tabla`");
        $i = 0;
        while ($fila = $lectura->fetch(PDO::FETCH_NUM)) {
            $i++;
            $valores = array_map('escaparSQL', $fila);
            $salida .= '(' . implode(',', $valores) . ')' . ($i < $total ? ',' : ';') . "\n";
            $contador++;
        }
        $salida .= "\n";
    }

    $salida .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $salida .= "-- Total de registros respaldados: $contador\n";

    if ($archivo !== null) {
        file_put_contents($archivo, $salida);
    }
    return $salida;
}

/** Crea un respaldo en la carpeta datos/ y devuelve la ruta. */
function crearRespaldo(string $motivo = 'manual'): string
{
    $marca = date('Y-m-d_His');
    $ruta = carpetaDatos() . DIRECTORY_SEPARATOR . "respaldo_" . $marca . ".sql";
    volcarSQL($ruta);
    @file_put_contents($ruta . '.motivo.txt', $motivo . "\n" . date('d/m/Y H:i:s') . "\n");
    return $ruta;
}

/**
 * Divide un volcado SQL en sentencias, respetando comillas y escapes,
 * para poder ejecutarlo al restaurar.
 */
function dividirSentencias(string $sql): array
{
    $sql = preg_replace('/^ï»¿/', '', $sql);
    $sentencias = [];
    $actual = '';
    $enComilla = false;
    $escape = false;
    $n = strlen($sql);

    for ($i = 0; $i < $n; $i++) {
        $ch = $sql[$i];

        if ($escape) {
            $actual .= $ch;
            $escape = false;
            continue;
        }
        if ($ch === '\\' && $enComilla) {
            $actual .= $ch;
            $escape = true;
            continue;
        }
        if ($ch === "'") {
            $enComilla = !$enComilla;
            $actual .= $ch;
            continue;
        }
        if (!$enComilla && $ch === ';') {
            if (trim($actual) !== '') {
                $sentencias[] = trim($actual);
            }
            $actual = '';
            continue;
        }
        $actual .= $ch;
    }
    if (trim($actual) !== '') {
        $sentencias[] = trim($actual);
    }

    // Filtra comentarios de una sola linea
    return array_values(array_filter($sentencias, function ($s) {
        $s = ltrim($s);
        return $s !== '' && strpos($s, '--') !== 0;
    }));
}
