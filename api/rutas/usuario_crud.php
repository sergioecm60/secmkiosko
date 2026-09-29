<?php
switch ($accion) {
case 'usuario_guardar': {
        $id   = pInt('id');
        $user = strtolower(trim(pTxt('usuario', 40)));
        $nombre = trim(pTxt('nombre', 80));
        $rol  = pTxt('rol', 10);
        if (!in_array($rol, ['admin', 'vendedor', 'cocina'], true)) {
            $rol = 'vendedor';
        }
        $clave = (string) p('clave', '');
        $activo = p('activo', 1) ? 1 : 0;

        if ($user === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $user)) {
            throw new RuntimeException('El usuario debe tener de 3 a 40 caracteres: letras, números, punto, guion o guion bajo.');
        }
        if ($nombre === '') {
            throw new RuntimeException('Escribí el nombre de la persona.');
        }

        if ($id > 0) {
            $st = $bd->prepare('SELECT * FROM `usuarios` WHERE id = ?');
            $st->execute([$id]);
            $existente = $st->fetch();
            if (!$existente) {
                throw new RuntimeException('Ese usuario ya no existe.');
            }
            // No dejar el kiosco sin ningún administrador activo.
            if ($existente['rol'] === 'admin' && ($rol !== 'admin' || !$activo)) {
                $otros = (int) $bd->query("SELECT COUNT(*) FROM `usuarios` WHERE rol='admin' AND activo=1 AND id <> " . $id)->fetchColumn();
                if ($otros === 0) {
                    throw new RuntimeException('Tiene que quedar al menos un administrador activo.');
                }
            }
            if (cajaDeUsuario($id) && $rol !== 'admin') {
                // sin consecuencia real, pero evita confusiones de permisos
                $rol = 'vendedor';
            }
            $st = $bd->prepare('UPDATE `usuarios` SET usuario=?, nombre=?, rol=?, activo=? WHERE id=?');
            $st->execute([$user, $nombre, $rol, $activo, $id]);
            if ($clave !== '') {
                if (strlen($clave) < 4) {
                    throw new RuntimeException('La clave debe tener al menos 4 caracteres.');
                }
                $bd->prepare('UPDATE `usuarios` SET clave=?, debe_cambiar_clave=1 WHERE id=?')
                   ->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
            }
            salida(['ok' => true, 'id' => $id, 'clave_renovada' => $clave !== '']);
        }

        if ($clave === '' || strlen($clave) < 4) {
            throw new RuntimeException('Asigná una clave de al menos 4 caracteres.');
        }
        $st = $bd->prepare('SELECT COUNT(*) FROM `usuarios` WHERE usuario = ?');
        $st->execute([$user]);
        if ((int) $st->fetchColumn() > 0) {
            throw new RuntimeException('Ya existe un usuario con ese nombre.');
        }
        $st = $bd->prepare(
            'INSERT INTO `usuarios` (usuario, nombre, clave, rol, activo, debe_cambiar_clave) VALUES (?,?,?,?,1,1)'
        );
        $st->execute([$user, $nombre, password_hash($clave, PASSWORD_DEFAULT), $rol]);
        salida(['ok' => true, 'id' => (int) $bd->lastInsertId()]);
    }

    case 'usuario_borrar': {
        $id = pInt('id');
        $u  = usuarioActual();
        if ($id === (int) $u['id']) {
            throw new RuntimeException('No te podés borrar a vos mismo.');
        }
        $st = $bd->prepare('SELECT * FROM `usuarios` WHERE id = ?');
        $st->execute([$id]);
        $usr = $st->fetch();
        if (!$usr) {
            throw new RuntimeException('Ese usuario no existe.');
        }
        if ($usr['rol'] === 'admin') {
            $otros = (int) $bd->query("SELECT COUNT(*) FROM `usuarios` WHERE rol='admin' AND activo=1 AND id <> " . $id)->fetchColumn();
            if ($otros === 0) {
                throw new RuntimeException('Tiene que quedar al menos un administrador activo.');
            }
        }
        if (cajaDeUsuario($id)) {
            throw new RuntimeException('Ese usuario tiene una caja abierta. Pedile que la cierre antes.');
        }
        // No se borra físicamente: el historial de ventas y cajas lo referencia.
        $bd->prepare('UPDATE `usuarios` SET activo = 0 WHERE id = ?')->execute([$id]);
        salida(['ok' => true, 'desactivado' => true]);
    }

    /* ---------- Cambiar mi propia clave ---------- */
    case 'clave_cambiar': {
        $u = usuarioActual();
        if (!$u) {
            salida(['ok' => false, 'error' => 'Sesión vencida.'], 401);
        }
        $actual  = (string) p('actual', '');
        $nueva   = (string) p('nueva', '');
        $repetir = (string) p('repetir', '');

        if (!password_verify($actual, (string) $u['clave'])) {
            throw new RuntimeException('La clave actual no es correcta.');
        }
        if (strlen($nueva) < 4) {
            throw new RuntimeException('La clave nueva debe tener al menos 4 caracteres.');
        }
        if ($nueva !== $repetir) {
            throw new RuntimeException('Las dos claves nuevas no coinciden.');
        }
        $bd->prepare('UPDATE `usuarios` SET clave = ?, debe_cambiar_clave = 0 WHERE id = ?')
           ->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $u['id']]);
        salida(['ok' => true]);
        }

}
