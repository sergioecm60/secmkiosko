<?php
/**
 * Kiosco — genera el respaldo de datos de ejemplo dentro de datos/ejemplo/.
 *
 * Uso (desde la consola de Laragon, en la carpeta del proyecto):
 *     php respaldar-ejemplo.php
 *
 * O haciendo doble clic en respaldar-ejemplo.bat
 *
 * IMPORTANTE: este respaldo es para datos DE EJEMPLO / pruebas.
 * Los respaldos reales se generan desde respaldo.php y NUNCA deben
 * subirse a un repositorio: .gitignore bloquea la carpeta datos/ entera
 * salvo esta subcarpeta.
 */
declare(strict_types=1);
require __DIR__ . '/inc/config.php';

if (PHP_SAPI !== 'cli') {
    exit("Este script se ejecuta desde la consola, no desde el navegador.\n");
}

$destino = __DIR__ . '/datos/ejemplo';
if (!is_dir($destino) && !@mkdir($destino, 0775, true)) {
    exit("No se pudo crear la carpeta: $destino\n");
}
$archivo = $destino . '/datos-kiosco-ejemplo.sql';

try {
    if (!instalado()) {
        exit("La base todavia no esta instalada. Abre instalar.php primero.\n");
    }
    $bd = pdoBd();

    $ventas = (int) $bd->query('SELECT COUNT(*) FROM ventas')->fetchColumn();
    if ($ventas > 0) {
        echo "AVISO: la base tiene $ventas venta(s). Se genera igual, pero no lo subas.\n";
    }

    volcarSQL($archivo);

    // Este archivo va a git, asi que despues de volcar se le sacan las
    // claves reales. Se edita el TEXTO del archivo, nunca la base: la clave
    // del administrador de esta maquina no se toca.
    $saneados = sanearClavesRespaldo($archivo);

    $n = [
        'productos'    => (int) $bd->query('SELECT COUNT(*) FROM productos')->fetchColumn(),
        'ventas'       => (int) $bd->query('SELECT COUNT(*) FROM ventas')->fetchColumn(),
        'movimientos'  => (int) $bd->query('SELECT COUNT(*) FROM movimientos')->fetchColumn(),
        'proveedores'  => (int) $bd->query('SELECT COUNT(*) FROM proveedores')->fetchColumn(),
        'formatos'     => (int) $bd->query('SELECT COUNT(*) FROM productos_formatos')->fetchColumn(),
    ];

    echo "\nRespaldo de ejemplo creado\n";
    echo "  archivo : $archivo\n";
    echo "  tamano  : " . number_format(filesize($archivo) / 1024, 1) . " KB\n";
    echo "  contenido: {$n['productos']} productos, {$n['formatos']} formatos, "
       . "{$n['ventas']} ventas, {$n['movimientos']} movimientos, "
       . "{$n['proveedores']} proveedores\n\n";
    echo "  Claves saneadas: $saneados usuario(s) quedaron como admin/admin\n";
    echo "  y con cambio de clave obligatorio. La base de esta maquina NO se toco.\n\n";

    echo "  Para versionarlo:  git add datos/ejemplo/  y despues  git commit\n";
} catch (Throwable $e) {
    exit("ERROR: " . $e->getMessage() . "\n");
}

/**
 * Reemplaza los hashes de clave del respaldo por el de fabrica y obliga a
 * cambiarla. Trabaja sobre el archivo .sql ya escrito, SOLO sobre las filas
 * de la tabla usuarios, y nunca sobre la base de datos.
 *
 * El orden de columnas sale del CREATE TABLE de usuarios:
 *   0 id, 1 usuario, 2 nombre, 3 clave, 4 rol, 5 activo,
 *   6 debe_cambiar_clave, 7 creado, 8 ultimo_ingreso
 */
function sanearClavesRespaldo(string $archivo): int
{
    $contenido = (string) file_get_contents($archivo);
    $deFabrica = password_hash('admin', PASSWORD_DEFAULT);
    $cuantas = 0;

    $lineas = preg_split("/\r?\n/", $contenido);
    $total = count($lineas);
    for ($i = 0; $i < $total; $i++) {
        if (!preg_match('/^INSERT INTO `usuarios`/', $lineas[$i])) {
            continue;
        }
        // Se reescriben las filas de este INSERT (puede ocupar varias lineas).
        for ($j = $i + 1; $j < $total; $j++) {
            $fila = $lineas[$j];
            if (trim($fila) === '' || str_starts_with(trim($fila), '--')) {
                break;
            }
            $campos = partirValoresSQL(trim(rtrim(trim($fila), ';')));
            if ($campos === null || count($campos) < 9) {
                continue;
            }
            $campos[3] = "'" . $deFabrica . "'";   // clave
            $campos[6] = '1';                        // debe_cambiar_clave
            $lineas[$j] = '(' . implode(',', $campos) . ');';
            $cuantas++;
        }
    }

    file_put_contents($archivo, implode("\n", $lineas), LOCK_EX);
    return $cuantas;
}

/** Parte una tupla SQL de valores respecting comillas simples. */
function partirValoresSQL(string $tupla): ?array
{
    $tupla = trim($tupla);
    if (!str_starts_with($tupla, '(')) {
        return null;
    }
    $tupla = substr($tupla, 1, -1);
    $valores = [];
    $actual = '';
    $enComilla = false;
    $largo = strlen($tupla);
    for ($i = 0; $i < $largo; $i++) {
        $c = $tupla[$i];
        if ($c === "'") {
            if ($enComilla && ($i + 1) < $largo && $tupla[$i + 1] === "'") {
                $actual .= "''";
                $i++;
                continue;
            }
            $enComilla = !$enComilla;
            $actual .= $c;
            continue;
        }
        if ($c === ',' && !$enComilla) {
            $valores[] = trim($actual);
            $actual = '';
            continue;
        }
        $actual .= $c;
    }
    $valores[] = trim($actual);
    return $valores;
}
