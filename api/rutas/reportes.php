<?php
switch ($accion) {
case 'reportes': {
            $d = desde();
            $h = hasta();

            $st = $bd->prepare(
                'SELECT COUNT(*) AS ventas, COALESCE(SUM(total),0) AS total,
                        COALESCE(AVG(total),0) AS promedio, COALESCE(SUM(descuento),0) AS descuentos,
                        COALESCE(MAX(total),0) AS mayor
                 FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?'
            );
            $st->execute([$d, $h]);
            $res = $st->fetch();

            // Costo de la mercancia vendida en el periodo (costo congelado al vender)
            $st = $bd->prepare(
                'SELECT COALESCE(SUM(vi.cantidad * vi.costo_unitario),0) AS costo
                 FROM venta_items vi INNER JOIN ventas v2 ON v2.id = vi.venta_id
                 WHERE v2.anulada = 0 AND v2.fecha BETWEEN ? AND ?'
            );
            $st->execute([$d, $h]);
            $costoPeriodo = (float) $st->fetchColumn();

            $st = $bd->prepare('SELECT COUNT(*) AS anuladas FROM ventas WHERE anulada = 1 AND fecha BETWEEN ? AND ?');
            $st->execute([$d, $h]);
            $anuladas = (int) $st->fetchColumn();

            $st = $bd->prepare('SELECT HOUR(fecha) AS h, COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?
                                GROUP BY HOUR(fecha) ORDER BY h');
            $st->execute([$d, $h]);
            $porHora = array_map(fn($r) => ['h' => (int) $r['h'], 'ventas' => (int) $r['n'], 'total' => (float) $r['t']], $st->fetchAll());

            $st = $bd->prepare(
                'SELECT vi.nombre, SUM(vi.cantidad) AS unidades, SUM(vi.importe) AS vendido,
                        SUM(vi.cantidad * vi.costo_unitario) AS costo, SUM(vi.cantidad) AS c
                 FROM venta_items vi
                 INNER JOIN ventas v ON v.id = vi.venta_id
                 WHERE v.anulada = 0 AND v.fecha BETWEEN ? AND ?
                 GROUP BY vi.nombre ORDER BY c DESC, vi.nombre ASC LIMIT 12'
            );
            $st->execute([$d, $h]);
            $top = array_map(fn($r) => [
                'nombre' => $r['nombre'],
                'unidades' => (float) $r['unidades'],
                'vendido' => (float) $r['vendido'],
                'costo' => (float) $r['costo'],
                'margen' => (float) $r['vendido'] > 0
                    ? redondear((($r['vendido'] - $r['costo']) / $r['vendido']) * 100) : 0.0,
            ], $st->fetchAll());

            $st = $bd->prepare('SELECT metodo, COUNT(*) AS n, COALESCE(SUM(total),0) AS t
                                FROM ventas WHERE anulada = 0 AND fecha BETWEEN ? AND ?
                                GROUP BY metodo ORDER BY t DESC');
            $st->execute([$d, $h]);
            $porPago = array_map(fn($r) => ['metodo' => $r['metodo'], 'ventas' => (int) $r['n'], 'total' => (float) $r['t']], $st->fetchAll());

            // "Quedan pocas" y la valuacion de inventario excluyen los
            // productos de venta libre: no llevan control de existencias.
            $st = $bd->query('SELECT id, nombre, stock, minimo, unidad, precio
                             FROM productos
                             WHERE activo = 1 AND sin_stock = 0 AND stock <= GREATEST(minimo, 0)
                             ORDER BY (stock - GREATEST(minimo,0)) ASC, nombre ASC LIMIT 30');
            $faltantes = array_map(fn($r) => [
                'id' => (int) $r['id'], 'nombre' => $r['nombre'],
                'stock' => (float) $r['stock'], 'minimo' => (float) $r['minimo'],
                'unidad' => $r['unidad'], 'precio' => (float) $r['precio'],
            ], $st->fetchAll());

            $st = $bd->query('SELECT COALESCE(SUM(stock*precio),0) AS v, COALESCE(SUM(stock*minimo),0) AS m,
                                     COALESCE(SUM(stock*costo),0) AS c, COALESCE(SUM(stock),0) AS u
                             FROM productos WHERE activo = 1 AND sin_stock = 0');
            $inv = $st->fetch();
            $costoInv = (float) $inv['c'];
            $ventaInv = (float) $inv['v'];

            salida([
                'ok'    => true,
                'resumen' => [
                    'ventas'      => (int) $res['ventas'],
                    'total'       => (float) $res['total'],
                    'promedio'    => (float) $res['promedio'],
                    'descuentos'  => (float) $res['descuentos'],
                    'mayor'       => (float) $res['mayor'],
                    'anuladas'    => $anuladas,
                    'costo'       => redondear($costoPeriodo),
                    'utilidad'    => redondear((float) $res['total'] - $costoPeriodo),
                    'margen'      => (float) $res['total'] > 0
                        ? redondear(((($res['total'] - $costoPeriodo) / $res['total']) * 100)) : 0.0,
                    'inventario'  => $ventaInv,
                    'costo_inventario' => $costoInv,
                    'ganancia_potencial' => redondear($ventaInv - $costoInv),
                    'unidades'    => (float) $inv['u'],
                ],
                'por_hora'  => $porHora,
                'top'       => $top,
                'por_pago'  => $porPago,
                'faltantes' => $faltantes,
            ]);
        }

}
