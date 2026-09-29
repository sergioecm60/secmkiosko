<?php
switch ($accion) {
case 'comandas': {
        // Tablero de cocina. Por defecto sólo lo que está en curso; el
        // histórico se pide con ?incluir=cerradas.
        $estados = ['pendiente', 'preparando', 'listo'];
        if (pTxt('incluir') === 'cerradas') {
            $estados = ESTADOS_COMANDA;
        }
        $lugares = array_map('trim', explode(',', pTxt('estados', 60)));
        $lugares = array_values(array_filter($lugares, fn($e) => in_array($e, ESTADOS_COMANDA, true)));
        if (!$lugares) {
            $lugares = $estados;
        }
        $in = implode(',', array_fill(0, count($lugares), '?'));
        $st = $bd->prepare("SELECT * FROM comandas WHERE estado IN ($in) ORDER BY id DESC LIMIT 200");
        $st->execute($lugares);
        $salida = ['ok' => true, 'comandas' => []];
        foreach ($st->fetchAll() as $fila) {
            $c = comandaCompleta($bd, (int) $fila['id']);
            if ($c) {
                $salida['comandas'][] = $c;
            }
        }
        salida($salida);
    }

    case 'comanda': {
        $c = comandaCompleta($bd, pInt('id'));
        if (!$c) {
            salida(['ok' => false, 'error' => 'Esa comanda ya no existe.'], 404);
        }
        salida(['ok' => true, 'comanda' => $c]);
    }

    case 'comanda_crear': {
        // El cajero arma la comanda por su cuenta, sin cobrar nada: es el
        // papel para la cocina, no la venta. El cobro va aparte, en el
        // carrito, y sale su propio remito.
        $id = guardarComanda($bd, cuerpo(), null, null, 0.0, nombreUsuario());
        salida(['ok' => true, 'comanda' => comandaCompleta($bd, $id)]);
    }

    case 'comanda_estado': {
        $id    = pInt('id');
        $nuevo = pTxt('estado', 20);
        if (!in_array($nuevo, ESTADOS_COMANDA, true)) {
            throw new RuntimeException('Estado de comanda desconocido.');
        }
        // Cancelar tira el pedido: lo decide el administrador, no la cocina.
        if ($nuevo === 'cancelado' && !esAdmin()) {
            salida(['ok' => false, 'error' => 'Sólo el administrador puede cancelar una comanda.'], 403);
        }
        $cerrado = in_array($nuevo, ['entregado', 'cancelado'], true) ? date('Y-m-d H:i:s') : null;

        $st = $bd->prepare('SELECT * FROM comandas WHERE id = ?');
        $st->execute([$id]);
        $actual = $st->fetch();
        if (!$actual) {
            salida(['ok' => false, 'error' => 'Esa comanda ya no existe.'], 404);
        }
        // Una comanda cancelada o ya entregada no vuelve atrás: si el pedido
        // se equivozó se carga uno nuevo.
        if (in_array((string) $actual['estado'], ['entregado', 'cancelado'], true)
            && $nuevo !== (string) $actual['estado']) {
            salida(['ok' => false, 'error' => 'Esa comanda ya está cerrada.'], 409);
        }
        $st = $bd->prepare('UPDATE comandas SET estado = ?, actualizado = ?, cerrado_en = ? WHERE id = ?');
        $st->execute([$nuevo, date('Y-m-d H:i:s'), $cerrado, $id]);
        salida(['ok' => true, 'comanda' => comandaCompleta($bd, $id)]);
    }

    case 'comanda_item': {
        // Cocina tacha linea por linea: "1 miga listo" sin esperar al resto.
        $idItem = pInt('id_item');
        $nuevo  = pTxt('estado', 20) === 'listo' ? 'listo' : 'pendiente';
        $st = $bd->prepare('UPDATE comanda_items SET estado = ? WHERE id = ?');
        $st->execute([$nuevo, $idItem]);
        if ($st->rowCount() === 0) {
            salida(['ok' => false, 'error' => 'Esa línea ya no existe.'], 404);
        }
        $st = $bd->prepare('SELECT comanda_id FROM comanda_items WHERE id = ?');
        $st->execute([$idItem]);
        $cid = (int) $st->fetchColumn();
        $st = $bd->prepare('UPDATE comandas SET actualizado = ? WHERE id = ?');
        $st->execute([date('Y-m-d H:i:s'), $cid]);
        salida(['ok' => true, 'comanda' => comandaCompleta($bd, $cid)]);
    }

    case 'comanda_atajos': {
        $st = $bd->query('SELECT * FROM comanda_atajos WHERE activo = 1 ORDER BY seccion, orden, id');
        $salida = ['ok' => true, 'atajos' => []];
        foreach ($st->fetchAll() as $a) {
            $salida['atajos'][] = [
                'id' => (int) $a['id'], 'seccion' => $a['seccion'], 'etiqueta' => $a['etiqueta'],
                'producto_id' => $a['producto_id'] !== null ? (int) $a['producto_id'] : null,
                'formato_unidad' => $a['formato_unidad'] ?: null,
                'texto' => $a['texto'] ?: null, 'detalle' => $a['detalle'] ?: null,
                'orden' => (int) $a['orden'],
            ];
        }
        salida($salida);
    }

    case 'atajo_guardar': {
        $id   = pInt('id');
        $etq  = trim(pTxt('etiqueta', 60));
        if ($etq === '') {
            throw new RuntimeException('La etiqueta del atajo no puede estar vacía.');
        }
        $texto = trim(pTxt('texto', 160));
        $pid   = pInt('producto_id');
        if ($texto === '' && $pid <= 0) {
            throw new RuntimeException('Elegí un producto o escribí lo que tiene que preparar cocina.');
        }
        $vals = [
            trim(pTxt('seccion', 40)) ?: 'Comidas', $etq,
            $pid > 0 ? $pid : null, trim(pTxt('formato_unidad', 20)) ?: null,
            $texto ?: null, trim(pTxt('detalle', 160)) ?: null,
            pInt('orden'), p('activo', 1) ? 1 : 0,
        ];
        if ($id > 0) {
            $st = $bd->prepare('UPDATE comanda_atajos
                SET seccion=?, etiqueta=?, producto_id=?, formato_unidad=?, texto=?, detalle=?, orden=?, activo=?
                WHERE id=?');
            $st->execute([...$vals, $id]);
        } else {
            $st = $bd->prepare('INSERT INTO comanda_atajos
                (seccion,etiqueta,producto_id,formato_unidad,texto,detalle,orden,activo) VALUES (?,?,?,?,?,?,?,?)');
            $st->execute($vals);
        }
        salida(['ok' => true, 'id' => $id > 0 ? $id : (int) $bd->lastInsertId()]);
    }

    case 'atajo_borrar': {
        $st = $bd->prepare('DELETE FROM comanda_atajos WHERE id = ?');
        $st->execute([pInt('id')]);
        salida(['ok' => true]);
    }

}
