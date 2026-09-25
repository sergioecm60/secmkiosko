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
require __DIR__ . '/config.php';

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
    volcarSQL($archivo);

    $bd = pdoBd();
    $n = [
        'productos'    => (int) $bd->query('SELECT COUNT(*) FROM productos')->fetchColumn(),
        'ventas'       => (int) $bd->query('SELECT COUNT(*) FROM ventas')->fetchColumn(),
        'movimientos'  => (int) $bd->query('SELECT COUNT(*) FROM movimientos')->fetchColumn(),
        'proveedores'  => (int) $bd->query('SELECT COUNT(*) FROM proveedores')->fetchColumn(),
    ];

    echo "Respaldo de ejemplo creado\n";
    echo "  archivo : $archivo\n";
    echo "  tamano  : " . number_format(filesize($archivo) / 1024, 1) . " KB\n";
    echo "  contenido: {$n['productos']} productos, {$n['ventas']} ventas, "
       . "{$n['movimientos']} movimientos, {$n['proveedores']} proveedores\n\n";

    $esEjemplo = $n['ventas'] === 0;
    if (!$esEjemplo) {
        echo "  AVISO: la base tiene {$n['ventas']} venta(s).\n";
        echo "  Si son ventas REALES de tu negocio, no subas este archivo.\n";
        echo "  Borralo con:\n";
        echo "      del \"datos\\ejemplo\\datos-kiosco-ejemplo.sql\"\n";
        echo "  y volvelo a generar cuando la base este vacia.\n\n";
    }

    echo "  Para versionarlo:  git add datos/ejemplo/  y despues  git commit\n";
} catch (Throwable $e) {
    exit("ERROR: " . $e->getMessage() . "\n");
}
