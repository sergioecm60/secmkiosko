<?php
switch ($accion) {
case 'categorias': {
        $st = $bd->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY orden, nombre');
        salida(['ok' => true, 'categorias' => array_map(fn($c) => [
            'id' => (int) $c['id'], 'nombre' => $c['nombre'], 'orden' => (int) $c['orden'],
            'cocina' => (int) $c['cocina'],
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
            $st = $bd->prepare('UPDATE categorias SET nombre=?, orden=?, cocina=? WHERE id=?');
            $st->execute([$nombre, pInt('orden'), p('cocina', 0) ? 1 : 0, $id]);
        } else {
            $st = $bd->prepare('INSERT INTO categorias (nombre, orden, cocina, activo) VALUES (?,?,?,1)');
            $st->execute([$nombre, pInt('orden'), p('cocina', 0) ? 1 : 0]);
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

  /* ============================================================
     CODIGOS DE BARRAS INTERNOS DE COCINA
     ============================================================
     Los productos que prepara la cocina no llevan etiqueta, asi que no tienen
     EAN-13 de fabrica. Para que igual se carguen con la pistola, se les da uno
     propio y se imprime una hoja con todos.

     El prefijo 20 es el que GS1 reserva para uso interno: nunca se le asigna a
     un producto de venta real. Por eso el numero tiene la forma de EAN-13 y
     la pistola lo lee, pero ningun escaner de un proveedor lo va a resolver
     contra un catalogo real. Con prefijo 779 (Argentina) habriamos inventado
     un codigo con el mismo aspecto que uno de fabricante, y si el numero
     llegara a un sistema externo intentaria cobrar algo inexistente.

     El cuerpo va a ser el id del producto, no un contador: asi el codigo es
     estable aunque se reordenen los productos, y se puede volver a generar
     sin cambiar las hojas ya impresas. */
  case 'codigos_cocina_generar': {
          $st = $bd->query(
              'SELECT pr.id, pr.nombre, pr.codigo, pr.categoria, c.cocina
               FROM productos pr
               JOIN categorias c ON c.nombre = pr.categoria COLLATE utf8mb4_unicode_ci
               WHERE c.cocina = 1 AND pr.activo = 1
               ORDER BY pr.nombre'
          );
          $filas = $st->fetchAll();

          $yaInternos = 0;
          $tocados = 0;
          $omitidos = [];
          $stUp = $bd->prepare('UPDATE productos SET codigo = ?, observaciones = ? WHERE id = ?');

          foreach ($filas as $f) {
              $codigo = $f['codigo'] ?? '';
              $esperado = '20' . str_pad((string) $f['id'], 10, '0', STR_PAD_LEFT);
              $esperado .= digitoDe($esperado);

              if ($codigo === $esperado) { $yaInternos++; continue; }
              if ($codigo !== '') {
                  // Cualquier otro codigo se deja como esta. Ojo con el que
                  // parece interno solo por el prefijo: GS1 si emite EAN-13 de
                  // circulacion restringida que arrancan con 20 (020-029 y
                  // 200-299), asi que el prefijo NO prueba que sea nuestro.
                  // Nuestro es solo el que lleva adentro el id de este
                  // producto, y eso ya se comparo arriba con $esperado. Antes
                  // se clasificaba solo por el prefijo y eso terminaba pisando
                  // en silencio un EAN de verdad: el codigo anterior se perdia
                  // sin dejar rastro. Un 20 que no es el esperado va a
                  // $omitidos, que se muestra, para que lo revise el usuario.
                  $omitidos[] = ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'codigo' => $codigo];
                  continue;
              }

              $base = '20' . str_pad((string) $f['id'], 10, '0', STR_PAD_LEFT);
              $nuevo = $base . digitoDe($base);

              // Red de seguridad: si otro producto ya tiene este codigo (pasa
              // si el catalogo se importo de otra parte) se saltea en vez de
              // dejar dos productos con el mismo codigo, que en el POS es
              // indistinguible y cobra el que toque.
              $stCh = $bd->prepare('SELECT id FROM productos WHERE codigo = ? AND id <> ? LIMIT 1');
              $stCh->execute([$nuevo, (int) $f['id']]);
              if ($stCh->fetchColumn() !== false) {
                  $omitidos[] = ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'codigo' => $nuevo];
                  continue;
              }

              $obs = trim(($f['observaciones'] ?? ''));
              $marca = 'Código interno de cocina, prefijo 20.';
              $obs = $obs === '' ? $marca : rtrim($obs) . ' ' . $marca;

              $stUp->execute([$nuevo, $obs, (int) $f['id']]);
              $tocados++;
          }

          salida([
              'ok' => true,
              'generados' => $tocados,
              'ya_tenian' => $yaInternos,
              'omitidos' => $omitidos,
              'hoja' => hojaDeCodigos($bd),
          ]);
      }

  }

  /** Ultimo digito de un EAN-13: el que completa la decena. */
  function digitoDe(string $doce): string
  {
      $s = 0;
      for ($i = 0; $i < 12; $i++) {
          $s += ((int) $doce[$i]) * ($i % 2 === 0 ? 1 : 3);
      }
      return (string) ((10 - ($s % 10)) % 10);
  }

  /** Arma la lista de la hoja imprimible: todos los productos con código. */
  function hojaDeCodigos(PDO $bd): array
  {
      $st = $bd->query(
          'SELECT pr.id, pr.nombre, pr.codigo, pr.precio, pr.unidad, pr.categoria, c.cocina
           FROM productos pr
           LEFT JOIN categorias c ON c.nombre = pr.categoria COLLATE utf8mb4_unicode_ci
           WHERE pr.codigo IS NOT NULL AND TRIM(pr.codigo) <> \'\' AND pr.activo = 1
           ORDER BY c.cocina DESC, pr.nombre'
      );
      return $st->fetchAll();
  }
