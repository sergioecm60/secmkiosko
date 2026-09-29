<?php
switch ($accion) {
case 'categorias': {
        $st = $bd->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY orden, nombre');
        salida(['ok' => true, 'categorias' => array_map(fn($c) => [
            'id' => (int) $c['id'], 'nombre' => $c['nombre'], 'orden' => (int) $c['orden'],
        ], $st->fetchAll())]);
    }

case 'categoria_guardar': {
        $nombre = trim(pTxt('nombre', 60));
        if ($nombre === '') {
            salida(['ok' => false, 'error' => 'La categoría necesita un nombre.'], 422);
        }
        // El UNIQUE es por nombre y no distingue mayusculas, asi que aca se
        // avisa antes de que sea la base la que rechace el INSERT. Traer el
        // nombre ya guardado tambien sirve para notice el cambio de forma.
        $chocan = $bd->prepare('SELECT id, nombre FROM categorias WHERE nombre = ? AND id <> ? LIMIT 1');
        $chocan->execute([$nombre, pInt('id')]);
        if ($fila = $chocan->fetch()) {
            $sugerido = $fila['nombre'];
            $ayuda = ($sugerido !== $nombre) ? ' Ya tenés una guardada como "' . $sugerido . '".' : '';
            salida(['ok' => false, 'error' => 'Ya existe una categoría con ese nombre.' . $ayuda], 422);
        }

        $id = pInt('id');
        if ($id > 0) {
            // Si cambia el nombre hay que llevar los productos con el, o quedan
            // apuntando a un nombre que ya no existe y se pierden de la ficha.
            $viejo = $bd->prepare('SELECT nombre FROM categorias WHERE id = ?');
            $viejo->execute([$id]);
            $anterior = (string) ($viejo->fetchColumn() ?: '');
            if ($anterior !== '' && $anterior !== $nombre) {
                $mover = $bd->prepare('UPDATE productos SET categoria = ? WHERE categoria = ? COLLATE utf8mb4_unicode_ci');
                $mover->execute([$nombre, $anterior]);
            }
            $st = $bd->prepare('UPDATE categorias SET nombre=?, orden=? WHERE id=?');
            $st->execute([$nombre, pInt('orden'), $id]);
        } else {
            $st = $bd->prepare('INSERT INTO categorias (nombre, orden, activo) VALUES (?,?,1)');
            $st->execute([$nombre, pInt('orden')]);
            $id = (int) $bd->lastInsertId();
        }
        salida(['ok' => true, 'id' => $id]);
    }

case 'categoria_borrar': {
        $id = pInt('id');
        // "Venta libre" la necesita el atajo de carga rapida: si se borra, ese
        // boton queda sin categoria con la que trabajar.
        $ver = $bd->prepare('SELECT nombre FROM categorias WHERE id = ?');
        $ver->execute([$id]);
        if ((string) ($ver->fetchColumn() ?: '') === 'Venta libre') {
            salida(['ok' => false, 'error' => 'La categoría "Venta libre" la usa el sistema y no se puede borrar.'], 422);
        }
        // No se borra una categoria en uso: quedarian productos sin ficha.
        // Es mejor que el administrador reubique esos productos primero.
        $st = $bd->prepare('SELECT COUNT(*) FROM productos WHERE categoria = (SELECT nombre FROM categorias WHERE id = ?) COLLATE utf8mb4_unicode_ci');
        $st->execute([$id]);
        $usados = (int) $st->fetchColumn();
        if ($usados > 0) {
            salida([
                'ok' => false,
                'error' => "No se puede borrar: hay $usados producto(s) en esta categoría. Reubicá esos productos primero.",
                'usados' => $usados,
            ], 422);
        }
        $st = $bd->prepare('DELETE FROM categorias WHERE id = ?');
        $st->execute([$id]);
        salida(['ok' => true]);
    }

case 'categorias_usadas': {
        $st = $bd->query('SELECT categoria, COUNT(*) n FROM productos
                          WHERE categoria IS NOT NULL AND TRIM(categoria) <> \'\'
                          GROUP BY categoria');
        $mapa = [];
        foreach ($st->fetchAll() as $f) {
            $mapa[$f['categoria']] = (int) $f['n'];
        }
        salida(['ok' => true, 'usadas' => $mapa]);
    }

}
