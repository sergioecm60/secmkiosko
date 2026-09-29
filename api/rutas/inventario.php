<?php
switch ($accion) {
case 'kardex': {
            $id = pInt('id');
            $st = $bd->prepare(
                'SELECT m.*, pr.unidad
                 FROM movimientos m
                 LEFT JOIN productos pr ON pr.id = m.producto_id
                 WHERE m.producto_id = ? ORDER BY m.id DESC LIMIT 100'
            );
            $st->execute([$id]);
            salida(['ok' => true, 'movimientos' => $st->fetchAll()]);
        }

}
