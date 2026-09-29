<?php
switch ($accion) {
case 'caja_abrir': {
        $u = usuarioActual();
        if (cajaDeUsuario((int) $u['id'])) {
            throw new RuntimeException('Ya tenés una caja abierta. Cierrala antes de abrir otra.');
        }
        $monto = max(0, pNum('monto_inicial'));
        $st = $bd->prepare('INSERT INTO `cajas` (usuario_id, monto_inicial) VALUES (?,?)');
        $st->execute([(int) $u['id'], $monto]);
        salida(['ok' => true, 'caja' => cajaResumen(cajaDeUsuario((int) $u['id']))]);
    }

    /* ---------- Ver mi caja abierta ---------- */
    case 'cajas_mias': {
        $u = usuarioActual();
        salida([
            'ok'    => true,
            'caja'  => $caja = cajaDeUsuario((int) $u['id']),
            'resumen' => $caja ? resumenCaja($bd, (int) $caja['id']) : null,
        ]);
    }

        /* ---------- Cerrar caja con conciliación ---------- */
        case 'caja_cerra': {
            $u = usuarioActual();
            $cajaId = pInt('caja_id');

            if ($cajaId > 0) {
                // Cerrar una caja ajena: sólo el administrador puede.
                $caja = cajaPorId($cajaId);
                if (!$caja) {
                    throw new RuntimeException('Esa caja no existe.');
                }
                if ((int) $caja['usuario_id'] !== (int) $u['id'] && $u['rol'] !== 'admin') {
                    salida(['ok' => false, 'error' => 'Esa caja es de otro cajero.'], 403);
                }
                if ((int) $caja['abierta'] !== 1) {
                    throw new RuntimeException('Esa caja ya estaba cerrada.');
                }
            } else {
                $caja = cajaDeUsuario((int) $u['id']);
                if (!$caja) {
                    throw new RuntimeException('No tenés ninguna caja abierta.');
                }
                $cajaId = (int) $caja['id'];
            }

        $declarados = p('declarados', []);
        $declarados = is_array($declarados) ? $declarados : [];
        $motivo = pTxt('motivo', 200);

        $resumen = resumenCaja($bd, $cajaId);
        $esperadoEfectivo = efectivoEsperadoCaja($bd, $cajaId);

        $bd->beginTransaction();
        try {
            $st = $bd->prepare(
                'INSERT INTO `caja_cierre_metodos` (caja_id, medio_pago_id, esperado, declarado, diferencia)
                 VALUES (?,?,?,?,?)'
            );
            foreach ($resumen['metodos'] as $m) {
                if ($m['ventas'] === 0) {
                    continue;   // sin ventas no hay nada que conciliar
                }
                $dec = array_key_exists((string) $m['id'], $declarados)
                    ? redondear(numero($declarados[(string) $m['id']]))
                    : $m['esperado'];
                $st->execute([$cajaId, $m['id'], $m['esperado'], $dec, redondear($dec - $m['esperado'])]);
            }

            // El efectivo declarado es la gaveta real: incluye el fondo inicial.
            // El frontend manda un id por método, no la palabra "efectivo", así que
            // buscamos cuál de los métodos de la conciliación es el de efectivo.
            $idEfectivo = 0;
            foreach ($resumen['metodos'] as $m) {
                if ($m['es_efectivo']) { $idEfectivo = $m['id']; break; }
            }
            $decEfectivo = $idEfectivo && array_key_exists((string) $idEfectivo, $declarados)
                ? redondear(numero($declarados[(string) $idEfectivo]))
                : $esperadoEfectivo;
            $difEfectivo = redondear($decEfectivo - $esperadoEfectivo);

            if (abs($difEfectivo) > 0.009 && $motivo === '') {
                throw new RuntimeException(
                    'El efectivo no cuadra (faltan o sobran ' . monto(abs($difEfectivo)) . '). ' .
                    'Contá bien y, si el descuadre es real, escribí el motivo antes de cerrar.'
                );
            }

            $st = $bd->prepare(
                'UPDATE `cajas` SET cerrada_en = NOW(), abierta = 0,
                        efectivo_esperado = ?, efectivo_declarado = ?,
                        total_esperado = ?, total_declarado = ?, diferencia = ?, motivo = ?
                 WHERE id = ?'
            );
            $st->execute([
                $esperadoEfectivo, $decEfectivo,
                $resumen['esperado'], $resumen['declarado'],
                $difEfectivo, $motivo ?: null, $cajaId,
            ]);
            $bd->commit();
        } catch (Throwable $e) {
            $bd->rollBack();
            throw $e;
        }

        salida([
            'ok'      => true,
            'caja'    => cajaResumen(cajaPorId($cajaId)),
            'resumen' => $resumen,
        ]);
    }

        /* ---------- Historial de cajas ----------
           El administrador ve todas; el vendedor, sólo las suyas. */
        case 'cajas_todas': {
            $desde = pTxt('desde', 10) ?: date('Y-m-01');
            $hasta = pTxt('hasta', 10) ?: date('Y-m-d');
            $u     = usuarioActual();

            $sql = 'SELECT c.*, us.nombre AS cajero, us.usuario AS cajero_usuario,
                           (SELECT COUNT(*) FROM `ventas` v WHERE v.caja_id = c.id AND v.anulada = 0) AS ventas
                    FROM `cajas` c
                    JOIN `usuarios` us ON us.id = c.usuario_id
                    WHERE DATE(c.abierta_en) BETWEEN ? AND ?';
            $par = [$desde, $hasta];
            if ($u['rol'] !== 'admin') {
                $sql .= ' AND c.usuario_id = ?';
                $par[] = (int) $u['id'];
            }
            $sql .= ' ORDER BY c.id DESC';

            $st = $bd->prepare($sql);
            $st->execute($par);
            $cajas = array_map(fn($c) => cajaResumen($c), $st->fetchAll());

            $desc = 0;
            foreach ($cajas as $c) {
                if (!$c['abierta'] && $c['diferencia'] !== null && abs($c['diferencia']) > 0.009) {
                    $desc++;
                }
            }
            salida([
                'ok' => true, 'cajas' => $cajas,
                'desde' => $desde, 'hasta' => $hasta, 'descuadres' => $desc,
                'solo_mias' => $u['rol'] !== 'admin',
            ]);
        }

    /* ---------- Detalle de una caja ---------- */
    case 'caja_detalle': {
        $id = pInt('id');
        $u = usuarioActual();
        $st = $bd->prepare(
            'SELECT c.*, us.nombre AS cajero, us.usuario AS cajero_usuario
             FROM `cajas` c JOIN `usuarios` us ON us.id = c.usuario_id WHERE c.id = ?'
        );
        $st->execute([$id]);
        $c = $st->fetch();
        if (!$c) {
            salida(['ok' => false, 'error' => 'La caja no existe.'], 404);
        }
        // El vendedor sólo puede mirar sus propias cajas.
        if ($u['rol'] !== 'admin' && (int) $c['usuario_id'] !== (int) $u['id']) {
            salida(['ok' => false, 'error' => 'Esa caja es de otro cajero.'], 403);
        }

        $st = $bd->prepare(
            'SELECT id, folio, fecha, total, metodo, medio_pago_id, recibido, vuelto, referencia, anulada
             FROM `ventas` WHERE caja_id = ? ORDER BY id'
        );
        $st->execute([$id]);
        $ventas = array_map(fn($v) => [
            'id'       => (int) $v['id'],
            'folio'    => (int) $v['folio'],
            'fecha'    => $v['fecha'],
            'total'    => (float) $v['total'],
            'metodo'   => $v['metodo'],
            'recibido' => $v['recibido'] === null ? null : (float) $v['recibido'],
            'vuelto'   => $v['vuelto'] === null ? null : (float) $v['vuelto'],
            'referencia'=> $v['referencia'] ?? '',
            'anulada'  => (int) $v['anulada'] === 1,
        ], $st->fetchAll());

        salida([
            'ok'      => true,
            'caja'    => cajaResumen($c),
            'resumen' => resumenCaja($bd, $id),
            'ventas'  => $ventas,
        ]);
    }

}
