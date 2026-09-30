<?php
switch ($accion) {
case 'comandas': {
        // Tablero de cocina. Por defecto sÃ³lo lo que estÃ¡ en curso; el
        // histÃ³rico se pide con ?incluir=cerradas.
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

    /* Ya no existe el caso 'comanda_crear'. Antes el cajero armaba un papel
       para la cocina sin cobrar nada, desde un modal aparte, y el cobro
       capaz iba por otro lado: dos anotaciones del mismo pedido, una gratis.
       Ahora la comanda sale del cobro, atada a la venta y con el nombre de
       quien retira. Para dejar un pedido sin cobrar no hay atajo. */

    case 'comanda_estado': {
        $id    = pInt('id');
        $nuevo = pTxt('estado', 20);
        if (!in_array($nuevo, ESTADOS_COMANDA, true)) {
            throw new RuntimeException('Estado de comanda desconocido.');
        }
        // Cancelar tira el pedido: lo decide el administrador, no la cocina.
        if ($nuevo === 'cancelado' && !esAdmin()) {
            salida(['ok' => false, 'error' => 'SÃ³lo el administrador puede cancelar una comanda.'], 403);
        }
        $cerrado = in_array($nuevo, ['entregado', 'cancelado'], true) ? date('Y-m-d H:i:s') : null;

        $st = $bd->prepare('SELECT * FROM comandas WHERE id = ?');
        $st->execute([$id]);
        $actual = $st->fetch();
        if (!$actual) {
            salida(['ok' => false, 'error' => 'Esa comanda ya no existe.'], 404);
        }
        // Una comanda cancelada o ya entregada no vuelve atrÃ¡s: si el pedido
        // se equivozÃ³ se carga uno nuevo.
        if (in_array((string) $actual['estado'], ['entregado', 'cancelado'], true)
            && $nuevo !== (string) $actual['estado']) {
            salida(['ok' => false, 'error' => 'Esa comanda ya estÃ¡ cerrada.'], 409);
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
            salida(['ok' => false, 'error' => 'Esa lÃ­nea ya no existe.'], 404);
        }
        $st = $bd->prepare('SELECT comanda_id FROM comanda_items WHERE id = ?');
        $st->execute([$idItem]);
        $cid = (int) $st->fetchColumn();
        $st = $bd->prepare('UPDATE comandas SET actualizado = ? WHERE id = ?');
        $st->execute([date('Y-m-d H:i:s'), $cid]);
        salida(['ok' => true, 'comanda' => comandaCompleta($bd, $cid)]);
    }

    /* Los casos 'comanda_atajos', 'atajo_guardar' y 'atajo_borrar' se
       fueron con la pantalla de atajos. Lo que va a la cocina ahora son
       productos del catalogo, con su precio, y se cargan desde Productos.
       La tabla comanda_atajos se deja en la base: no molesta, y borrar
       datos no es un ejercicio de git. */
}
