<?php
/**
 * Kiosco — configuracion general y conexion a la base de datos.
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
        `anulada`    TINYINT(1)   NOT NULL DEFAULT 0,
        `anulada_en` DATETIME     NULL,
        `motivo`     VARCHAR(200) NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_folio` (`folio`),
        KEY `ix_fecha`   (`fecha`),
        KEY `ix_anulada` (`anulada`)
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
    'pie'      => '¡Gracias por su compra!',
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
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'datos';
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
         (`fecha`,`tipo`,`producto_id`,`producto_nombre`,`cantidad`,`stock_anterior`,`stock_actual`,`referencia`,`usuario`,`proveedor_id`,`documento`)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
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
    ]);
}

function nombreUsuario(): string
{
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
    $hechas[] = 'movimientos: proveedor_id, documento';

    // El costo se congela al vender: si mañana suben el precio de compra,
    // el margen historico de las ventas viejas no debe cambiar.
    agregarColumna('venta_items', '`costo_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    $hechas[] = 'venta_items: costo_unitario';

    // --- 5. Proveedor en la venta (de whom compramos, no, pero queda el dato del cajero) ---
    agregarColumna('ventas', '`proveedor_id` INT UNSIGNED NULL');
    $hechas[] = 'ventas: proveedor_id';

    // --- 6. Medios de pago por defecto ---
    $n = (int) $pdo->query('SELECT COUNT(*) FROM `medios_pago`')->fetchColumn();
    if ($n === 0) {
        $st = $pdo->prepare('INSERT INTO `medios_pago` (`nombre`,`icono`,`exige_referencia`,`orden`,`es_efectivo`) VALUES (?,?,?,?,?)');
        $defectos = [
            ['Efectivo',       '💵', 0, 1, 1],
            ['Tarjeta',        '💳', 1, 2, 0],
            ['Transferencia',  '📱', 1, 3, 0],
            ['Mercado Pago',   '🅿️', 1, 4, 0],
        ];
        foreach ($defectos as $d) {
            $st->execute($d);
        }
        $hechas[] = 'medios de pago por defecto';
    }

    return $hechas;
}

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
    $salida .= "-- " . APP_NOMBRE . " — respaldo de la base de datos\n";
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
    $sql = preg_replace('/^﻿/', '', $sql);
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
