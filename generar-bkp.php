<?php
/**
 * Genera el volcado de la base para compartirla con el equipo.
 *
 * Usa volcarSQL() -- la misma funcion que usa el boton de respaldo de la app --
 * pero cambia la clave del usuario admin por una de desarrollo conocida. El
 * hash real no debe viajar a un repositorio publico, ni siquiera "por un
 * tiempo": despues de pushear, borrar el archivo no lo saca del historial.
 */
declare(strict_types=1);

require __DIR__ . '/inc/config.php';

$ruta = __DIR__ . '/bkp/base-de-desarrollo.sql';
$claveDev = 'kiosco-dev-2026';

$pdo = pdoBd();

// Guardamos el hash real y el estado original para dejar todo como estaba.
$st = $pdo->prepare('SELECT id, clave, debe_cambiar_clave FROM usuarios WHERE usuario = ?');
$st->execute(['admin']);
$original = $st->fetch(PDO::FETCH_ASSOC);

if (!$original) {
    fwrite(STDERR, "No existe el usuario admin; no se genera nada.\n");
    exit(1);
}

try {
    $nuevo = password_hash($claveDev, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE usuarios SET clave = ?, debe_cambiar_clave = 0 WHERE id = ?')
        ->execute([$nuevo, $original['id']]);

    @mkdir(__DIR__ . '/bkp', 0777, true);
    volcarSQL($ruta);

    // Volvemos a dejar la clave real que tenia la base.
    $pdo->prepare('UPDATE usuarios SET clave = ?, debe_cambiar_clave = ? WHERE id = ?')
        ->execute([$original['clave'], $original['debe_cambiar_clave'], $original['id']]);

    echo "Volcado escrito en: bkp/base-de-desarrollo.sql\n";
    echo 'Bytes: ' . filesize($ruta) . "\n";
} catch (Throwable $e) {
    // Si algo falla, la clave real vuelve igual: el finally de abajo no aplica
    // porque estamos fuera de transaccion, asi que se restaura aqui mismo.
    $pdo->prepare('UPDATE usuarios SET clave = ?, debe_cambiar_clave = ? WHERE id = ?')
        ->execute([$original['clave'], $original['debe_cambiar_clave'], $original['id']]);
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
