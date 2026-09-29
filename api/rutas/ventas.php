<?php
/** La categoria esta marcada como "va a cocina" en la lista maestra.
 *  OJO con el nombre: dice "va", no "siempre va". El servidor lo usa para NO
 *  dejar apagar una linea que el administrador marco como cocina. */
function categoriaEsCocina(PDO $bd, string $nombre): bool
{
    if ($nombre === '') {
        return false;
    }
    $st = $bd->prepare('SELECT cocina FROM categorias WHERE nombre = ?');
    $st->execute([$nombre]);
    return (int) $st->fetchColumn() === 1;
}

switch ($accion) {
case 'venta_crear': {
            // Sin caja abierta no se cobra: así cada venta pertenece a un
            // turno y el cierre se puede conciliar.
            $caja = cajaAbierta();
            if (!$caja) {
                salida([
                    'ok'    => false,
                    'error' => 'Primero abrí tu caja. Sin caja abierta no se puede cobrar.',
                    'caja'  => true,
                ], 409);
            }

            $items = p('items', []);
            if (!is_array($items) || count($items) === 0) {
                salida(['ok' => false, 'error' => 'El carrito está vacío.'], 422);
            }
            $descuento = max(0, pNum('descuento'));
            $metodoId  = pInt('medio_pago_id');
            $metodo    = pTxt('metodo', 20) ?: 'Efectivo';
            $recibido  = pNum('recibido', -1);
            $vuelto    = pNum('vuelto', 0);
            $ref       = pTxt('referencia', 60);
            $nota      = pTxt('nota', 200);
            $usuario   = nombreUsuario();
            $datosComanda = p('comanda');

            // El costo del reparto se resuelve de la zona configurada antes de
            // sumar el total: si mandan otra cifra por el cliente, se ignora.
            $envio = 0.0;
            if (is_array($datosComanda) && ($datosComanda['tipo'] ?? '') === 'delivery') {
                $zonaId = entero($datosComanda['zona_id'] ?? 0);
                if ($zonaId > 0) {
                    $stZ = $bd->prepare('SELECT costo FROM zonas WHERE id = ? AND activo = 1');
                    $stZ->execute([$zonaId]);
                    $costoZona = $stZ->fetchColumn();
                    if ($costoZona === false) {
                        throw new RuntimeException('La zona elegida ya no existe.');
                    }
                    $envio = redondear((float) $costoZona);
                }
            }

            // El medio de pago se resuelve por id contra la tabla, para que el
            // cierre pueda agrupar por método. El nombre es sólo respaldo.
            $mp = null;
            if ($metodoId > 0) {
                $st = $bd->prepare('SELECT * FROM `medios_pago` WHERE id = ? AND activo = 1');
                $st->execute([$metodoId]);
                $mp = $st->fetch();
            }
            if (!$mp) {
                $st = $bd->prepare('SELECT * FROM `medios_pago` WHERE nombre = ? AND activo = 1');
                $st->execute([$metodo]);
                $mp = $st->fetch();
            }
            if (!$mp) {
                throw new RuntimeException('Elegí un medio de pago de la lista.');
            }
            $metodo   = (string) $mp['nombre'];
            $metodoId = (int) $mp['id'];
            if ((int) $mp['exige_referencia'] === 1 && $ref === '') {
                throw new RuntimeException('Anotá la referencia de ' . $metodo . ' para poder conciliar la caja.');
            }

            $bd->beginTransaction();
            try {
                // 1. Calcular
                $subtotal = 0.0;
                $lineas = [];
                foreach ($items as $it) {
                    $pid  = entero($it['id'] ?? 0);
                    $cant = redondear(numero($it['cantidad'] ?? 0));
                    if ($pid <= 0 || $cant <= 0) {
                        continue;
                    }
                    $st = $bd->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                    $st->execute([$pid]);
                    $pr = $st->fetch();
                    if (!$pr) {
                        throw new RuntimeException('Un producto del carrito ya no existe (id ' . $pid . ').');
                    }

                    // Con formato de venta (docena, 2x1, maple...): la cantidad
                    // viene en esas unidades y el precio es el del formato.
                    $formato = null;
                    $factor  = 1.0;
                    $fmtId   = entero($it['formato_id'] ?? 0);
                    if ($fmtId > 0) {
                        $stF = $bd->prepare('SELECT * FROM productos_formatos
                                             WHERE id = ? AND producto_id = ? AND ambito = "venta"');
                        $stF->execute([$fmtId, $pid]);
                        $formato = $stF->fetch();
                        if (!$formato) {
                            // El formato pudo cambiar de id si el producto se
                            // guardo entre la carga y el cobro: se busca por
                            // nombre y cantidad antes de fallar.
                            $formato = formatoPorNombre($bd, $pid, 'venta',
                                (string) ($it['formato_unidad'] ?? ''),
                                isset($it['formato_factor']) ? (float) $it['formato_factor'] : null);
                        }
                        if (!$formato) {
                            throw new RuntimeException('Un formato del carrito no es del producto ' . $pr['nombre'] . '.');
                        }
                        $factor = (float) $formato['factor'] > 0 ? (float) $formato['factor'] : 1.0;
                    }

                    $baseCant = redondear($cant * $factor);
                    // El precio sale del formato o del producto, nunca del
                    // navegador: si no hay nada configurado se cae al precio
                    // que envio el cliente para no dejar la linea en cero.
                    $precio = $formato !== null
                        ? precioVentaDe($formato, (float) ($pr['costo'] ?? 0), (float) ($pr['precio'] ?? 0))
                        : (float) $pr['precio'];
                    if ($precio <= 0.0 && isset($it['precio']) && is_numeric($it['precio'])) {
                        $precio = (float) $it['precio'];
                    }
                    $precio = redondear($precio);
                    if ($precio < 0) {
                        $precio = 0.0;
                    }
                    $importe = redondear($precio * $cant);
                    $subtotal = redondear($subtotal + $importe);
                    $lineas[] = [
                        'p' => $pr, 'cant' => $baseCant, 'precio' => $precio,
                        'importe' => $importe, 'formato' => $formato, 'cant_fmt' => $cant,
                        // Lo decide el cajero en la linea del carrito. El texto
                        // de la comanda se arma con el nombre real del producto,
                        // asi que sale del servidor y no del navegador.
                        'cocina' => (bool) (entero($it['cocina'] ?? 0)),
                    ];
                }
                if (!$lineas) {
                    throw new RuntimeException('No hay líneas válidas en el carrito.');
                }

                // El servidor tiene la ultima palabra sobre el destino a
                // cocina: si la categoria esta marcada como de cocina (tragos,
                // rotiseria), la linea va a cocina aunque el navegador digera
                // que no. Es la unica regla que se le gana al cliente, y a
                // proposito: un trago que se queda en el mostrador sin
                // preparar es venta perdida. El navegador solo puede APAGAR
                // una categoria normal, nunca ENCENDER una de cocina.
                foreach ($lineas as &$l) {
                    $l['cocina'] = $l['cocina'] || categoriaEsCocina($bd, (string) ($l['p']['categoria'] ?? ''));
                }
                unset($l);
                if ($descuento > $subtotal) {
                    $descuento = $subtotal;
                }
                $total = redondear($subtotal - $descuento + $envio);

                // 2. Folio. Se reserva con una fila contadora bloqueada con
                //    FOR UPDATE: con dos cajeros cobrando en el mismo
                //    instante, el segundo espera a que el primero libere el
                //    lock en vez de sacar el mismo numero y chocar con el
                //    UNIQUE de ventas.folio.
                $st = $bd->prepare('SELECT `valor` FROM `contadores` WHERE `nombre` = ? FOR UPDATE');
                $st->execute(['folio']);
                $actual = $st->fetchColumn();
                if ($actual === false) {
                    // Contador ausente (base vieja): se crea y se vuelve a leer.
                    $bd->prepare('INSERT INTO `contadores` (`nombre`,`valor`) VALUES (?,?)')
                       ->execute(['folio', 0]);
                    $st = $bd->prepare('SELECT `valor` FROM `contadores` WHERE `nombre` = ? FOR UPDATE');
                    $st->execute(['folio']);
                    $actual = $st->fetchColumn();
                }
                $folio = ((int) $actual) + 1;
                $bd->prepare('UPDATE `contadores` SET `valor` = ? WHERE `nombre` = ?')
                   ->execute([$folio, 'folio']);

                // 3. Cabecera
                $st = $bd->prepare(
                    'INSERT INTO ventas (folio,fecha,subtotal,descuento,total,envio,metodo,medio_pago_id,recibido,vuelto,referencia,nota,usuario,caja_id)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $st->execute([
                    $folio, date('Y-m-d H:i:s'), $subtotal, $descuento, $total, $envio, $metodo, $metodoId,
                    $recibido >= 0 ? $recibido : null, $recibido >= 0 ? $vuelto : null,
                    $ref ?: null, $nota ?: null, $usuario, (int) $caja['id'],
                ]);
                $ventaId = (int) $bd->lastInsertId();

                // 4. Items + descuento de existencias + kardex
                $stItem = $bd->prepare(
                    'INSERT INTO venta_items (venta_id,producto_id,nombre,codigo,precio,cantidad,importe,costo_unitario,
                                               formato_id,formato_unidad,formato_cantidad)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $sinStock = [];
                $costoVendido = 0.0;
                $descuenta = [];   // producto_id => [stock antes, unidades a descontar]
                foreach ($lineas as $l) {
                    $pr     = $l['p'];
                    $pid    = (int) $pr['id'];
                    $costoLinea = redondear((float) ($pr['costo'] ?? 0) * $l['cant']);
                    $costoVendido = redondear($costoVendido + $costoLinea);
                    $stItem->execute([$ventaId, $pid, $pr['nombre'], $pr['codigo'],
                                      $l['precio'], $l['cant'], $l['importe'], (float) ($pr['costo'] ?? 0),
                                      $l['formato'] !== null ? (int) $l['formato']['id'] : null,
                                      $l['formato'] !== null ? (string) $l['formato']['unidad'] : null,
                                      $l['formato'] !== null ? (float) $l['cant_fmt'] : null]);
                    // El mismo producto puede entrar varias veces con distinto
                    // formato (1 docena + 6 sueltas): se acumula todo y se
                    // descuenta una sola vez. Los productos de venta libre
                    // (sin_stock) no entran: no hay existencias que sacar.
                    if ((int) ($pr['sin_stock'] ?? 0) === 1) {
                        continue;
                    }
                    if (!isset($descuenta[$pid])) {
                        $descuenta[$pid] = ['antes' => (float) $pr['stock'], 'resta' => 0.0, 'pr' => $pr];
                    }
                    $descuenta[$pid]['resta'] = redondear($descuenta[$pid]['resta'] + $l['cant']);
                }

                // Descuento de existencias: una actualizacion por producto.
                $stUpd = $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?');
                foreach ($descuenta as $pid => $d) {
                    $ahora = redondear($d['antes'] - $d['resta']);
                    if ($ahora < 0 && !in_array($d['pr']['nombre'], $sinStock, true)) {
                        $sinStock[] = $d['pr']['nombre'];
                    }
                    $stUpd->execute([$ahora, $pid]);
                    $descuenta[$pid]['ahora'] = $ahora;
                }

                // Kardex: una linea por formato, con el stock encadenado.
                $recorrido = [];
                foreach ($lineas as $l) {
                    $pr   = $l['p'];
                    $pid  = (int) $pr['id'];
                    // La venta libre no entra en $descuenta porque no descuenta
                    // stock, asi que tampoco tiene kardex que escribir.
                    if ((int) ($pr['sin_stock'] ?? 0) === 1) {
                        continue;
                    }
                    $d    = $descuenta[$pid];
                    $antes = $recorrido[$pid] ?? $d['antes'];
                    $ahora = redondear($antes - $l['cant']);
                    $recorrido[$pid] = $ahora;
                    registrarMovimiento([
                        'tipo' => 'venta', 'producto_id' => $pid, 'producto_nombre' => $pr['nombre'],
                        'cantidad' => -$l['cant'], 'stock_anterior' => $antes, 'stock_actual' => $ahora,
                        'referencia' => 'Venta folio ' . $folio, 'usuario' => $usuario,
                        'nota' => $l['formato'] !== null
                            ? ((string) $l['formato']['unidad'] . ' x ' . rtrim(rtrim(number_format((float) $l['cant_fmt'], 4, '.', ''), '0'), '.'))
                            : null,
                    ]);
                }

                // La comanda se guarda en la MISMA transaccion que la venta:
                // o queda el pedido pagado y su comanda, o no queda nada.
                $comandaId = null;

                // Sale UNA sola vez: si hay lineas marcadas para cocina, la
                // comanda se arma sola con esas, atada a este folio. El cajero
                // no tipea el pedido dos veces.
                $paraCocina = array_values(array_filter($lineas, fn($l) => $l['cocina'] === true));
                if ($paraCocina) {
                    $items = [];
                    foreach ($paraCocina as $l) {
                        $detalle = $l['formato'] !== null
                            ? (string) $l['formato']['unidad'] : null;
                        $items[] = [
                            'producto_id' => (int) $l['p']['id'],
                            'cantidad'    => $l['cant_fmt'],
                            'texto'       => (string) $l['p']['nombre'],
                            'detalle'     => $detalle,
                        ];
                    }
                    // Sin delivery, sin zona y sin datos: es consumo en el
                    // mostrador. La comanda sale con el mismo folio de la
                    // venta, asi se puede rastrear que se cobro.
                    $comandaId = guardarComanda($bd, [
                        'tipo'     => 'mesa',
                        'cliente'  => $ref ?: 'Mostrador',
                        'items'    => $items,
                    ], $ventaId, $folio, $total, $usuario);
                }

                // Comanda de delivery u otra cosa que venga aparte: la que
                // armaba el cajero a mano con su propio boton.
                if (is_array($datosComanda)) {
                    $comandaId = guardarComanda($bd, $datosComanda, $ventaId, $folio, $total, $usuario);
                }

                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }

            $st = $bd->prepare('SELECT * FROM ventas WHERE id = ?');
            $st->execute([$ventaId]);
            $v = venta($st->fetch());
            $v['costo']    = $costoVendido;
            $v['utilidad'] = redondear($total - $costoVendido);
            $v['items'] = itemsDe($bd, $ventaId);

            salida(['ok' => true, 'venta' => $v, 'sin_stock' => $sinStock, 'comanda_id' => $comandaId]);
        }

        case 'venta_anular': {
            $id    = pInt('id');
            $motivo = pTxt('motivo', 200) ?: 'Anulación manual';

            $bd->beginTransaction();
            try {
                $st = $bd->prepare('SELECT * FROM ventas WHERE id = ? FOR UPDATE');
                $st->execute([$id]);
                $v = $st->fetch();
                if (!$v) {
                    throw new RuntimeException('La venta no existe.');
                }
                if ((int) $v['anulada'] === 1) {
                    throw new RuntimeException('Esa venta ya estaba anulada.');
                }
                // El vendedor sólo anula lo que está en su caja abierta.
                // Con la caja ya cerrada, sólo el administrador.
                if (!puedeAnularVenta($v)) {
                    throw new RuntimeException(
                        'Con la caja cerrada sólo el administrador puede anular esta venta.'
                    );
                }
                $st = $bd->prepare('SELECT * FROM venta_items WHERE venta_id = ?');
                $st->execute([$id]);
                $items = $st->fetchAll();

                $stUpd = $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?');
                foreach ($items as $it) {
                    if ($it['producto_id'] === null) {
                        continue;
                    }
                    $pid = (int) $it['producto_id'];
                    $s = $bd->prepare('SELECT stock, sin_stock FROM productos WHERE id = ? FOR UPDATE');
                    $s->execute([$pid]);
                    $fila = $s->fetch();
                    if (!$fila) {
                        continue;
                    }
                    // La venta libre nunca toca el stock: al anular tampoco
                    // hay que reponer.
                    if ((int) ($fila['sin_stock'] ?? 0) === 1) {
                        continue;
                    }
                    $antes = (float) $fila['stock'];
                    $ahora = redondear($antes + (float) $it['cantidad']);
                    $stUpd->execute([$ahora, $pid]);
                    registrarMovimiento([
                        'tipo' => 'anulacion', 'producto_id' => $pid, 'producto_nombre' => $it['nombre'],
                        'cantidad' => (float) $it['cantidad'], 'stock_anterior' => $antes, 'stock_actual' => $ahora,
                        'referencia' => 'Anulación venta folio ' . (int) $v['folio'], 'usuario' => nombreUsuario(),
                    ]);
                }
                $bd->prepare('UPDATE ventas SET anulada=1, anulada_en=NOW(), motivo=?, anulada_por=? WHERE id=?')
                   ->execute([$motivo, nombreUsuario(), $id]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true]);
        }

        case 'ventas': {
            $d = desde();
            $h = hasta();
            $buscar = trim((string) p('buscar', ''));
            $soloValidas = ((string) p('validas', '0')) === '1';

            $sql = 'SELECT * FROM ventas WHERE fecha BETWEEN ? AND ?';
            $par = [$d, $h];
            if ($soloValidas) {
                $sql .= ' AND anulada = 0';
            }
            if ($buscar !== '') {
                $sql .= ' AND (folio LIKE ? OR referencia LIKE ? OR nota LIKE ?)';
                $like = '%' . $buscar . '%';
                array_push($par, $like, $like, $like);
            }
            $sql .= ' ORDER BY id DESC LIMIT 500';

            $st = $bd->prepare($sql);
            $st->execute($par);
            $lista = array_map('venta', $st->fetchAll());

            // Totales del rango
            $sql2 = 'SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM ventas
                     WHERE fecha BETWEEN ? AND ? AND anulada = 0';
            $st2 = $bd->prepare($sql2);
            $st2->execute([$d, $h]);
            $tot = $st2->fetch();

            salida([
                'ok'     => true,
                'ventas' => $lista,
                'total'  => (int) $tot['n'],
                'importe' => (float) $tot['t'],
            ]);
        }

        case 'venta': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT * FROM ventas WHERE id = ?');
            $st->execute([$id]);
            $v = $st->fetch();
            if (!$v) {
                salida(['ok' => false, 'error' => 'La venta no existe.'], 404);
            }
            $v = venta($v);
            $v['items'] = itemsDe($bd, $id);
            salida(['ok' => true, 'venta' => $v]);
        }

}
