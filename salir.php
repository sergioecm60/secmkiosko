<?php
declare(strict_types=1);
require __DIR__ . '/inc/sesion.php';

cerrarSesion();
header('Location: login.php');
exit;
