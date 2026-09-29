<?php
switch ($accion) {
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
            // Ajustar el folio desde Ajustes tiene que mover el contador, o
            // la proxima venta volveria a arrancar desde el valor viejo.
            if (p('folio') !== null) {
                $siguiente = max(1, pInt('folio', 1)) - 1;
                $bd->prepare('INSERT INTO `contadores` (`nombre`,`valor`) VALUES (?,?)
                             ON DUPLICATE KEY UPDATE `valor` = ?')
                   ->execute(['folio', $siguiente, $siguiente]);
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
                    $bd->prepare('INSERT INTO `contadores` (`nombre`,`valor`) VALUES (?,?)
                                 ON DUPLICATE KEY UPDATE `valor` = ?')
                       ->execute(['folio', 0, 0]);
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

}
