<?php
switch ($accion) {
case 'zonas': {
        $st = $bd->query('SELECT * FROM zonas WHERE activo = 1 ORDER BY orden, id');
        salida(['ok' => true, 'zonas' => array_map(fn($z) => [
            'id' => (int) $z['id'], 'nombre' => $z['nombre'], 'costo' => (float) $z['costo'],
        ], $st->fetchAll())]);
    }

    case 'zona_guardar': {
        $nombre = trim(pTxt('nombre', 60));
        if ($nombre === '') {
            throw new RuntimeException('La zona necesita un nombre.');
        }
        $id = pInt('id');
        if ($id > 0) {
            $st = $bd->prepare('UPDATE zonas SET nombre=?, costo=?, activo=? WHERE id=?');
            $st->execute([$nombre, pNum('costo'), p('activo', 1) ? 1 : 0, $id]);
        } else {
            $st = $bd->prepare('INSERT INTO zonas (nombre,costo,orden,activo) VALUES (?,?,?,?)');
            $st->execute([$nombre, pNum('costo'), pInt('orden'), p('activo', 1) ? 1 : 0]);
            $id = (int) $bd->lastInsertId();
        }
        salida(['ok' => true, 'id' => $id]);
    }

    case 'zona_borrar': {
        $st = $bd->prepare('DELETE FROM zonas WHERE id = ?');
        $st->execute([pInt('id')]);
        salida(['ok' => true]);
    }

}
