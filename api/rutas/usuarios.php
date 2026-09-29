<?php
switch ($accion) {
case 'sesion_info': {
        $u = usuarioActual();
        salida([
            'ok'      => true,
            'usuario' => $u ? [
                'id'                 => (int) $u['id'],
                'usuario'            => $u['usuario'],
                'nombre'             => $u['nombre'],
                'rol'                => $u['rol'],
                'es_admin'           => $u['rol'] === 'admin',
                'debe_cambiar_clave' => (int) $u['debe_cambiar_clave'] === 1,
            ] : null,
            'caja'    => cajaAbierta() ? cajaResumen(cajaAbierta()) : null,
        ]);
    }

}
