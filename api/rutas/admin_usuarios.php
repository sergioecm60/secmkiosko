<?php
switch ($accion) {
case 'usuarios': {
        $st = $bd->query(
            'SELECT us.id, us.usuario, us.nombre, us.rol, us.activo, us.creado, us.ultimo_ingreso,
                    (SELECT COUNT(*) FROM `cajas` c WHERE c.usuario_id = us.id) AS cajas
             FROM `usuarios` us ORDER BY us.rol, us.nombre'
        );
        salida(['ok' => true, 'usuarios' => $st->fetchAll()]);
    }

}
