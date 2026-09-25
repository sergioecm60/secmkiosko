# secmkiosko

Sistema de punto de venta y control de existencias para kiosco y almacén.
Corre íntegramente en una PC con Windows, **sin internet y sin servicios en la nube**.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4) ![MySQL](https://img.shields.io/badge/MySQL-8-4479A1) ![Licencia](https://img.shields.io/badge/licencia-MIT-green)

---

## Qué hace

- **Punto de venta** con búsqueda por nombre o código de barras, carrito con cantidades
  y cobro por efectivo, tarjeta o transferencia, con cálculo de vuelto.
- **Control de existencias**: el stock se descuenta solo al vender, avisa cuando un producto
  queda por debajo del mínimo y avisa si una venta deja el inventario en negativo.
- **Kardex**: cada entrada, salida, venta o anulación queda registrada con stock anterior,
  stock actual, fecha y motivo.
- **Historial de ventas** con anulación (devuelve el stock) y reimpresión de tickets.
- **Reportes**: importe, ticket promedio, ventas por hora, productos más vendidos,
  reparto por forma de pago y lista de productos por reponer.
- **Entradas y salidas de mercancía** para compras, mermas o conteos físicos.
- **Respaldo y restauración** de toda la base en un archivo `.sql`.

## Requisitos

- Windows 10 u 11
- [Laragon](https://laragon.org/download) (Full o Lite) —Apache, PHP y MySQL
- Un navegador (Chrome o Edge). Para cobre: **C:\laragon\www\kiosco**

## Instalación

1. Copiá la carpeta `kiosco` dentro de `C:\laragon\www\`.
2. Abrí Laragon y presioná **Start All**.
3. Entrá a <http://localhost/kiosco/instalar.php> y seguí los pasos.
4. Listo: el kiosco queda en <http://localhost/kiosco>.

La primera vez podés cargar productos de ejemplo desde el instalador, y después reemplazarlos
por tu catálogo real (o importarlos desde un CSV).

## Respaldos

Es lo más importante: **descargá un respaldo todos los días**.
Está en *Ajustes → Respaldos*, y también se crea solo antes de cualquier borrado.

Para migrar a otra computadora: instalá Laragon, copiá la carpeta `kiosco` a `www`,
ejecutá `instalar.php` una vez y restaurá el `.sql`.

## Estructura

```
kiosco/
├── index.php      Interfaz del punto de venta
├── api.php        API JSON (productos, ventas, reportes, kardex)
├── config.php     Conexión a la base, respaldos y utilidades
├── instalar.php   Crea la base y las tablas
├── respaldo.php   Descarga y restauracion de respaldos
├── estilos.css    Hoja de estilos
├── app.js         Lógica de la interfaz
└── datos/         Respaldos .sql (no se versiona)
```

## Base de datos

| Tabla | Contenido |
|---|---|
| `productos` | Catálogo, precio, stock, stock mínimo, categoría, unidad |
| `ventas` | Cabecera: folio, fecha, totales, medio de pago, anulación |
| `venta_items` | Detalle de cada venta (se conserva el nombre histórico) |
| `movimientos` | Kardex de inventario |
| `config` | Datos del negocio, moneda, folios, tema |

## Atajos de teclado

| Tecla | Acción |
|---|---|
| `F2` | Ir a la búsqueda (para escanear) |
| `Enter` | Agregar el producto buscado |
| `F4` | Cobrar la venta actual |
| `Esc` | Cerrar ventana / limpiar búsqueda |

## Notas de seguridad

- La base es local. No expongas el puerto 80 a internet sin poner un proxy con HTTPS.
- Las credenciales de MySQL se pueden sobrescribir con variables de entorno
  (`KIOSCO_DB_HOST`, `KIOSCO_DB_USER`, `KIOSCO_DB_PASS`, `KIOSCO_DB_NAME`)
  para no tener que tocar el código.
- La API usa sentencias preparadas y rechaza peticiones desde orígenes externos.

## Licencia

MIT. Usalo, modificalo y vendelo como quieras.