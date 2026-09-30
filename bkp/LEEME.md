# Base de datos de desarrollo

Este volcado existe para que otra persona pueda probar el sistema con los
mismos datos que se usaron durante el desarrollo, sin tener que cargar el
catálogo a mano.

## Cómo usarla

```bash
mysql -u root -e "CREATE DATABASE kiosco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root kiosco < bkp/base-de-desarrollo.sql
```

Después abrir `instalar.php` una vez para aplicar el esquema, o entrar
directo si el volcado ya trae las tablas completas (este caso).

## Qué contiene

132 registros de las 16 tablas: el catálogo de productos con sus precios,
la categoría `Comidas y Tragos` marcada como cocina, las 3 zonas de reparto,
el usuario `admin` y los movimientos de las ventas de prueba.

## La clave es de desarrollo, no la real

El usuario `admin` viene con la clave:

```
kiosco-dev-2026
```

Se generó con `generar-bkp.php`, que cambia la clave por esa antes de
volcar y después la restaura en la base local. La clave real nunca sale de
la máquina.

Si en algún momento esta base se convierte en la de producción, cambiala
desde Ajustes antes de usarla.

## Por qué está en Git y hay que borrarla

Está acá porque mientras tanto el equipo necesita una base conocida para
probar. **Pero el repositorio es público y los respaldos reales no
deberían subirse nunca** (ver el motivo en `.gitignore`).

Cuando el sistema esté listo para producción, borrar esta carpeta y sacarla
del `.gitignore`:

```bash
git rm -r bkp
```

Ojo: con eso desaparece del último commit, pero **queda en el historial**
y se sigue entrando por el enlace del commit en GitHub. Si llegó a
subirse, la forma de sacarlo de verdad es reescribir el historial
(`git filter-repo`) o dar de baja el repositorio y empezar de nuevo.
Por eso el dump va sin costos de compra ni márgenes reales: si el día de
mañana hay que limpiar, es más barato no haber subido el secreto.
