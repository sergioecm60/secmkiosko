<?php
switch ($accion) {

        /* ============================================================
           PRODUCTOS
           ============================================================ */
        case 'productos': {
            $soloActivos = ((string) p('activos', '')) === '1';
            $buscar = trim((string) p('buscar', ''));
            $cat    = trim((string) p('categoria', ''));

            $sql = 'SELECT pr.*, pv.`nombre` AS proveedor
                    FROM productos pr
                    LEFT JOIN proveedores pv ON pv.id = pr.proveedor_id
                    WHERE 1 = 1';
            $par = [];
            if ($soloActivos) {
                $sql .= ' AND pr.activo = 1';
            }
            if ($buscar !== '') {
                $sql .= ' AND (pr.nombre LIKE ? OR pr.codigo LIKE ? OR pr.categoria LIKE ?)';
                $like = '%' . $buscar . '%';
                array_push($par, $like, $like, $like);
            }
            if ($cat !== '') {
                $sql .= ' AND pr.categoria = ?';
                $par[] = $cat;
            }
            $sql .= ' ORDER BY pr.nombre ASC';

            $st = $bd->prepare($sql);
            $st->execute($par);
            $lista = array_map('prod', $st->fetchAll());

            salida(['ok' => true, 'productos' => $lista]);
        }

        case 'producto': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT pr.*, pv.`nombre` AS proveedor
                                FROM productos pr
                                LEFT JOIN proveedores pv ON pv.id = pr.proveedor_id
                                WHERE pr.id = ?');
            $st->execute([$id]);
            $f = $st->fetch();
            if (!$f) {
                salida(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
            }
            $st = $bd->prepare('SELECT * FROM movimientos WHERE producto_id = ? ORDER BY id DESC LIMIT 40');
            $st->execute([$id]);
            salida(['ok' => true, 'producto' => prod($f), 'kardex' => $st->fetchAll()]);
        }

        case 'producto_guardar': {
            $id    = pInt('id', 0);
            $nombre = pTxt('nombre', 120);
            $esNuevo = $id <= 0;

            if ($esNuevo && $nombre === '') {
                salida(['ok' => false, 'error' => 'El nombre del producto es obligatorio.'], 422);
            }
            $codigo    = pTxt('codigo', 40);
            $categoria = pTxt('categoria', 60);
            $precio    = max(0, pNum('precio'));
            $minimo    = max(0, pNum('minimo'));
            $unidad    = pTxt('unidad', 20) ?: 'pieza';
            $foto      = pTxt('foto', 400000);
            $activo    = ((string) p('activo', '1')) === '1' ? 1 : 0;
            $costo     = p('costo') === null ? 0.0 : max(0, pNum('costo'));
            $obs       = pTxt('observaciones', 500);
            $provId    = pInt('proveedor_id', 0);
            // Venta espontanea: el producto se carga en el momento y se cobra,
            // pero no sale de un stock que se cuente (ver seccion 9 del esquema).
            $sinStock  = ((string) p('sin_stock', '0')) === '1' ? 1 : 0;

            // Compra en multiplos: maple, cajon, bolsa, docena...
            $uCompra    = pTxt('unidad_compra', 20);
            $factorCompra = p('factor_compra') === null ? 1.0 : max(0.001, pNum('factor_compra', 1));
            $pCompra    = p('precio_compra') === null || pNum('precio_compra') <= 0
                            ? null : redondear(pNum('precio_compra'));
            $presetTxt  = pTxt('presets', 120) ?: null;

            // Codigo repetido
            if ($codigo !== '') {
                $st = $bd->prepare('SELECT id FROM productos WHERE codigo = ? AND id <> ?');
                $st->execute([$codigo, $id]);
                if ($st->fetch()) {
                    salida(['ok' => false, 'error' => 'Ese código ya lo tiene otro producto (' . $codigo . ').'], 409);
                }
            }

            // Digito verificador del EAN-13. Un codigo mal tipeado no lo
            // encuentra ni el lector de barras, asi que mejor rechazarlo
            // ahora que descubrirlo cuando el cliente esta esperando.
            if (!codigoAceptable($codigo)) {
                $deberia = codigoMalExplicacion($codigo);
                salida([
                    'ok'    => false,
                    'error' => 'El código ' . $codigo . ' no es un EAN-13 válido: el último dígito tendría que ser '
                               . $deberia . ', no ' . substr($codigo, -1) . '. Revisalo o dejalo vacío.',
                ], 422);
            }

            $bd->beginTransaction();
            try {
                if ($id > 0) {
                    $st = $bd->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                    $st->execute([$id]);
                    $viejo = $st->fetch();
                    if (!$viejo) {
                        throw new RuntimeException('El producto ya no existe.');
                    }
                    // En una edicion, lo que no se envia se conserva
                    $nombre    = $nombre !== '' ? $nombre : $viejo['nombre'];
                    $categoria = p('categoria') === null ? (string) $viejo['categoria'] : $categoria;
                    $unidad    = p('unidad') === null ? ($viejo['unidad'] ?: 'pieza') : $unidad;
                    $precio    = p('precio') === null ? (float) $viejo['precio'] : $precio;
                    $minimo    = p('minimo') === null ? (float) $viejo['minimo'] : $minimo;
                    $activo    = p('activo') === null ? (int) $viejo['activo'] : $activo;
                    $foto      = p('foto') === null ? (string) $viejo['foto'] : $foto;
                    $costo     = p('costo') === null ? (float) $viejo['costo'] : $costo;
                    $obs       = p('observaciones') === null ? (string) $viejo['observaciones'] : $obs;
                    $provId    = p('proveedor_id') === null ? (int) $viejo['proveedor_id'] : $provId;
                    if (p('sin_stock') === null) { $sinStock = (int) ($viejo['sin_stock'] ?? 0); }
                    if (p('unidad_compra') === null) { $uCompra = (string) ($viejo['unidad_compra'] ?? ''); }
                    if (p('factor_compra') === null) { $factorCompra = (float) ($viejo['factor_compra'] ?? 1); }
                    if (p('precio_compra') === null) {
                        $pCompra = isset($viejo['precio_compra']) && $viejo['precio_compra'] !== null
                            ? (float) $viejo['precio_compra'] : null;
                    }
                    if (p('presets') === null) { $presetTxt = $viejo['presets'] ?? null; }
                    $stockViejo = (float) $viejo['stock'];
                    $stockNuevo = $stockViejo;
                    if (p('stock') !== null) {
                        $stockNuevo = pNum('stock');
                    }
                    $st = $bd->prepare(
                        'UPDATE productos SET nombre=?, codigo=?, categoria=?, precio=?, costo=?, stock=?, minimo=?,
                                             unidad=?, foto=?, sin_stock=?, activo=?, observaciones=?, proveedor_id=?,
                                             unidad_compra=?, factor_compra=?, precio_compra=?, presets=?
                         WHERE id = ?'
                    );
                    $st->execute([$nombre, $codigo ?: null, $categoria ?: null, $precio, $costo, $stockNuevo,
                                  $minimo, $unidad, $foto ?: null, $sinStock, $activo, $obs ?: null, $provId ?: null,
                                  $uCompra ?: null, $factorCompra, $pCompra, $presetTxt, $id]);
                    $dif = redondear($stockNuevo - $stockViejo);
                    if ($dif !== 0.0 && !$sinStock) {
                        registrarMovimiento([
                            'tipo' => $dif > 0 ? 'entrada' : 'salida',
                            'producto_id' => $id, 'producto_nombre' => $nombre,
                            'cantidad' => $dif, 'stock_anterior' => $stockViejo, 'stock_actual' => $stockNuevo,
                            'referencia' => 'Edición de producto', 'usuario' => nombreUsuario(),
                        ]);
                    }
                } else {
                    $stock = $sinStock ? 0.0 : pNum('stock');
                    $st = $bd->prepare(
                        'INSERT INTO productos (nombre,codigo,categoria,precio,costo,stock,minimo,unidad,foto,sin_stock,activo,observaciones,proveedor_id,
                                                unidad_compra,factor_compra,precio_compra,presets)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $st->execute([$nombre, $codigo ?: null, $categoria ?: null, $precio, $costo, $stock,
                                  $minimo, $unidad, $foto ?: null, $sinStock, $activo, $obs ?: null, $provId ?: null,
                                  $uCompra ?: null, $factorCompra, $pCompra, $presetTxt]);
                    $id = (int) $bd->lastInsertId();
                    // Un producto de venta libre no tiene existencias: no se
                    // inventa un movimiento de alta con stock 0.
                    if (!$sinStock) {
                        registrarMovimiento([
                            'tipo' => 'alta', 'producto_id' => $id, 'producto_nombre' => $nombre,
                            'cantidad' => redondear($stock), 'stock_anterior' => 0, 'stock_actual' => $stock,
                            'referencia' => 'Alta de producto', 'usuario' => nombreUsuario(),
                        ]);
                    }
                }
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }

            guardarFormatos($bd, $id, p('formatos_compra'), 'compra');
            guardarFormatos($bd, $id, p('formatos_venta'), 'venta');

            // El costo del producto sale del formato de compra predeterminado:
            // maple de $5.000 entre 30 huevos = $166,67 por huevo.
            $costoBase = costoBaseDe($bd, $id);
            if ($costoBase !== null) {
                $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoBase, $id]);
            }

            // El precio del producto es el de UNA unidad base y sale del
            // formato de venta predeterminado. Asi un formato cargado con
            // "% sobre costo" no deja el producto en precio 0, y una caja
            // de 24 a $26.000 deja el puré en $1.083,33 y no en $26.000.
            $stV = $bd->prepare('SELECT precio, margen, factor FROM productos_formatos
                                  WHERE producto_id = ? AND ambito = "venta"
                               ORDER BY predet DESC, factor ASC, id ASC LIMIT 1');
            $stV->execute([$id]);
            if ($fv = $stV->fetch()) {
                $precioUnidad = precioVentaDe([
                    'precio' => $fv['precio'] !== null ? (float) $fv['precio'] : null,
                    'margen' => $fv['margen'] !== null ? (float) $fv['margen'] : null,
                    'factor' => (float) $fv['factor'],
                ], $costoBase ?? costoProducto($bd, $id));
                $factor = (float) $fv['factor'] > 0 ? (float) $fv['factor'] : 1.0;
                $porUnidad = redondear($precioUnidad / $factor);
                if ($porUnidad > 0) {
                    $bd->prepare('UPDATE productos SET precio = ? WHERE id = ?')->execute([$porUnidad, $id]);
                }
            }

            $st = $bd->prepare('SELECT pr.*, pv.`nombre` AS proveedor
                            FROM productos pr LEFT JOIN proveedores pv ON pv.id = pr.proveedor_id
                            WHERE pr.id = ?');
            $st->execute([$id]);
            salida(['ok' => true, 'producto' => prod($st->fetch()), 'id' => $id]);
        }

        case 'producto_borrar': {
            $id = pInt('id');
            $st = $bd->prepare('SELECT * FROM productos WHERE id = ?');
            $st->execute([$id]);
            $pr = $st->fetch();
            if (!$pr) {
                salida(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
            }
            $bd->beginTransaction();
            try {
                // Se conservan los items de venta con su nombre historico
                $bd->prepare('UPDATE venta_items SET producto_id = NULL WHERE producto_id = ?')->execute([$id]);
                $bd->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
                registrarMovimiento([
                    'tipo' => 'baja', 'producto_id' => null, 'producto_nombre' => $pr['nombre'],
                    'cantidad' => 0, 'stock_anterior' => (float) $pr['stock'], 'stock_actual' => 0,
                    'referencia' => 'Eliminación de producto', 'usuario' => nombreUsuario(),
                ]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true]);
        }

        /* Ajuste manual de existencias (compra, merma, conteo fisico) */
        case 'stock_mover': {
            $id  = pInt('id');
            $tipo = pTxt('tipo', 20);           // entrada | salida
            $cant = pNum('cantidad');
            $motivo = pTxt('referencia', 80) ?: ($tipo === 'entrada' ? 'Entrada manual' : 'Salida manual');
            $provId   = pInt('proveedor_id', 0);
            $documento = pTxt('documento', 40);
            $formatoId = pInt('formato_id', 0);

            if ($id <= 0 || $cant === 0.0) {
                salida(['ok' => false, 'error' => 'Indica el producto y la cantidad.'], 422);
            }
            $delta = ($tipo === 'salida') ? -abs($cant) : abs($cant);

            $bd->beginTransaction();
            try {
                $st = $bd->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                $st->execute([$id]);
                $pr = $st->fetch();
                if (!$pr) {
                    throw new RuntimeException('Producto no encontrado.');
                }
                // Los productos de venta libre no llevan control de existencias.
                if ((int) ($pr['sin_stock'] ?? 0) === 1) {
                    throw new RuntimeException('Este producto se vende sin stock, no se le pueden registrar movimientos.');
                }
                $antes = (float) $pr['stock'];

                // Si se eligio un formato de compra (maple, cajon, bolsa, caja),
                // la cantidad viene en esa presentacion y se pasa a unidad base.
                $factor = 1.0;
                $formato = null;
                if ($formatoId > 0) {
                    $stF = $bd->prepare('SELECT * FROM productos_formatos
                                         WHERE id = ? AND producto_id = ? AND ambito = "compra"');
                    $stF->execute([$formatoId, $id]);
                    $formato = $stF->fetch();
                    if (!$formato) {
                        $formato = formatoPorNombre($bd, $id, 'compra',
                            (string) pTxt('formato_unidad', 20),
                            p('formato_factor') !== null ? pNum('formato_factor') : null);
                    }
                    if (!$formato) {
                        throw new RuntimeException('Ese formato de compra no es del producto.');
                    }
                    $f = (float) $formato['factor'];
                    if ($f > 0) { $factor = $f; }
                } elseif (p('usar_unidad_compra') !== null && $delta > 0) {
                    $f = (float) ($pr['factor_compra'] ?? 1);
                    if ($f > 0) { $factor = $f; }
                }
                $delta = redondear($delta * $factor);
                $ahora = redondear($antes + $delta);

                // Al comprar de un proveedor, se actualiza el costo del producto
                $costoNuevo = (float) ($pr['costo'] ?? 0);
                if ($delta > 0 && $provId > 0 && p('costo_unitario') !== null && pNum('costo_unitario') > 0) {
                    $costoNuevo = pNum('costo_unitario');
                    $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoNuevo, $id]);
                }

                // El precio del formato de compra define el costo por unidad
                // base: si el maple de 30 sale $5.000, el huevo queda $166,67.
                // El precio enviado manda sobre el del formato, asi una compra
                // a otro precio actualiza el costo sin tocar el catalogo.
                $precioFmt = null;
                if ($delta > 0 && $formato !== null) {
                    if (p('precio_formato') !== null && pNum('precio_formato') > 0) {
                        $precioFmt = pNum('precio_formato');
                    } elseif ($formato['precio'] !== null && (float) $formato['precio'] > 0) {
                        $precioFmt = (float) $formato['precio'];
                    }
                }
                if ($precioFmt !== null && $factor > 0) {
                    $costoNuevo = redondear($precioFmt / $factor);
                    $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoNuevo, $id]);
                } elseif ($delta > 0 && p('precio_compra') !== null && pNum('precio_compra') > 0 && $factor > 0) {
                    $costoNuevo = redondear(pNum('precio_compra') / $factor);
                    $bd->prepare('UPDATE productos SET costo = ? WHERE id = ?')->execute([$costoNuevo, $id]);
                }

                $unidadFmt = $formato !== null ? (string) $formato['unidad'] : null;
                $cantidadFmt = $formato !== null ? abs($cant) : null;
                $bd->prepare('UPDATE productos SET stock = ? WHERE id = ?')->execute([$ahora, $id]);
                registrarMovimiento([
                    'tipo' => $delta > 0 ? 'entrada' : 'salida', 'producto_id' => $id,
                    'producto_nombre' => $pr['nombre'], 'cantidad' => $delta,
                    'stock_anterior' => $antes, 'stock_actual' => $ahora,
                    'referencia' => $motivo, 'usuario' => nombreUsuario(),
                    'proveedor_id' => $provId ?: null, 'documento' => $documento ?: null,
                    'nota' => $unidadFmt !== null
                        ? ('Formato: ' . $unidadFmt . ' x ' . rtrim(rtrim(number_format((float) $cantidadFmt, 4, '.', ''), '0'), '.'))
                        : null,
                ]);
                $bd->commit();
            } catch (Throwable $e) {
                $bd->rollBack();
                throw $e;
            }
            salida(['ok' => true, 'stock' => $ahora, 'costo' => $costoNuevo]);
        }

        case 'categorias': {
            $st = $bd->query("SELECT DISTINCT categoria FROM productos WHERE categoria <> '' AND categoria IS NOT NULL ORDER BY categoria");
            salida(['ok' => true, 'categorias' => $st->fetchAll(PDO::FETCH_COLUMN)]);
        }

}
