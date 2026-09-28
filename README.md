# secmkiosko

Sistema de punto de venta, delivery y control de existencias para kiosco y almacén.
Corre íntegramente en una PC con Windows, **sin internet y sin servicios en la nube**.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4) ![MySQL](https://img.shields.io/badge/MySQL-8-4479A1) ![nginx](https://img.shields.io/badge/servidor-nginx-009639) ![Licencia](https://img.shields.io/badge/licencia-MIT-green)

---

## Qué hace

- **Punto de venta** con búsqueda por nombre o código de barras, carrito con cantidades
  y cobro por efectivo, tarjeta o transferencia, con cálculo de vuelto.
- **Producto rápido**: lo que se vende espontáneamente (un pancho, una pizza armada en el
  momento) se carga con su precio y queda en el catálogo. Va marcado **sin control de stock**,
  así que no descuenta existencias, no genera kardex y no aparece en los avisos ni en la
  valuación de inventario. Si el nombre ya existe, actualiza el precio en vez de duplicarlo.
- **Delivery, retiro y mesa**: el pedido se cobra como una venta normal y además genera una
  **comanda de cocina** con el mismo número de folio. Se guarda todo en una sola transacción:
  o queda el pedido pagado y su comanda, o no queda nada.
- **Cocina (KDS)**: tablero de pedidos con estados (nueva, preparando, lista, entregada),
  tiempo transcurrido y cuenta de cuántos hay en cada estado. Atajos de comanda para los
  productos que siempre se piden.
- **Roles y permisos**: administrador, vendedor y cocina. Cada rol ve y toca lo que le
  corresponde; la cocina no cobra y el vendedor no entra a ajustes.
- **Costo de compra y margen** por producto. El costo se congela al vender, así el
  margen histórico no cambia cuando después sube el precio de compra.
- **Formatos de compra y venta**: un maple, una caja de 24 o una docena tienen su propio
  precio, y el precio del producto sale del formato predeterminado dividido por su factor.
- **Control de existencias**: el stock se descuenta solo al vender, avisa cuando un producto
  queda por debajo del mínimo y avisa si una venta deja el inventario en negativo.
- **Kardex**: cada entrada, salida, venta o anulación queda registrada con stock anterior,
  stock actual, fecha y motivo.
- **Entradas de mercancía** con proveedor y número de remito: sabés de qué compra salió
  cada caja. Si cargás el costo nuevo, se actualiza el margen del producto.
- **Historial de ventas** con anulación (devuelve el stock) y reimpresión de tickets.
- **Reportes**: ganancia bruta, costo de mercancía, margen, ventas por hora, productos más
  vendidos, reparto por forma de pago y lista de productos por reponer.
- **Medios de pago configurables**: agregás Mercado Pago, débito, cobros, etc., y definís
  cuáles reciben vuelto y cuáles piden referencia.
- **Proveedores** con sus datos y notas.
- **Respaldo y restauración** de toda la base en un archivo `.sql`.

## Requisitos

- Windows 10 u 11
- [Laragon](https://laragon.org/download) (Full o Lite) — **nginx**, PHP y MySQL
- Un navegador (Chrome o Edge). Para cobrar: **http://secmkiosko.test:8080**

> **Ojo: este proyecto corre sobre nginx, no sobre Apache.** Antes de esta versión el
> repo estaba preparado para Apache; ya no. Ver [Servidor nginx](#servidor-nginx).

## Instalación

1. Copiá la carpeta `secmkiosko` dentro de `C:\laragon\www\`.
2. Abrí Laragon y presioná **Start All** (que arranque nginx y MySQL).
3. Entrá a <http://secmkiosko.test:8080/instalar.php> y seguí los pasos.
4. Entrá a <http://secmkiosko.test:8080> con el usuario `admin` y la clave `admin`.
   El sistema te obliga a cambiarla en el primer ingreso.

La primera vez podés cargar productos de ejemplo desde el instalador, y después reemplazarlos
por tu catálogo real (o importarlos desde un CSV).

## Servidor nginx

Laragon genera el vhost solo, en
`C:\laragon\etc\nginx\sites-enabled\auto.secmkiosko.test.conf`:

```nginx
server {
    listen 8080;
    server_name secmkiosko.test *.secmkiosko.test;
    root "C:/laragon/www/secmkiosko";
    index index.html index.htm index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
        autoindex on;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass php_upstream;
    }
    charset utf-8;
}
```

Datos del server en esta máquina:

| Qué | Valor |
|---|---|
| Servidor | nginx 1.27.3, puerto **8080** (no 80) |
| vhost | `secmkiosko.test` → `C:/laragon/www/secmkiosko` |
| PHP | FastCGI vía `php_upstream` (php-cgi en `127.0.0.1:10987`) |
| Vhost por defecto | `localhost:8080`, sirve cualquier carpeta de `www/` |

Diferencias con Apache que importan:

- **No hay `.htaccess` y no debe haberlos.** nginx no los lee. Si algún día hace falta
  una regla de reescritura, va en el vhost de arriba.
- **La cookie de sesión es del host.** Entrando por `secmkiosko.test:8080` la sesión vive
  en ese host; si después abrís `localhost:8080/secmkiosko` en la misma pestaña, el navegador
  no manda la cookie y la API responde *"tu sesión se venció"*. Es normal: usá siempre la
  misma dirección.
- Para editar el vhost a mano, sacale el prefijo `auto.` al archivo, si no Laragon lo pisa.

## Respaldos

Es lo más importante: **descargá un respaldo todos los días**.
Está en *Ajustes → Respaldos*, y también se crea solo antes de cualquier borrado.

Para migrar a otra computadora: instalá Laragon, copiá la carpeta `secmkiosko` a `www`,
ejecutá `instalar.php` una vez y restaurá el `.sql`.

### Qué se sube a git y qué no

| Carpeta | ¿En git? | Contenido |
|---|---|---|
| `datos/ejemplo/` | ✅ sí, **por ahora** | Volcado con datos de **prueba** |
| `datos/` (resto) | ❌ **nunca** | Respaldos reales: costos, márgenes, ventas, proveedores |

El `.gitignore` bloquea `datos/*` y solo deja pasar `datos/ejemplo/`. Es a propósito:
un respaldo real contiene tu información de negocio, y en un repositorio público
queda expuesta de forma permanente (el historial de git no se borra de verdad).

Para regenerar el respaldo de ejemplo, doble clic en **`respaldar-ejemplo.bat`**.
El script te avisa si encuentra ventas en la base antes de escribir nada.

> **Pendiente:** `datos/ejemplo/` se va a eliminar del repositorio. Mientras tanto se sigue
> manteniendo y versionando, así que no tires el `.gitignore` ni la carpeta sin avisar.

Si alguna vez subiste un respaldo real por error, el procedimiento para limpiarlo
está en **`LEEME-RESPALDOS.txt`**.

## Estructura

Hoy todo vive suelto en la raíz del proyecto. Funciona bien, pero es la primera cosa a
ordenar: separar en `app/` (JS y CSS), `includes/` (PHP de servidor) y `vistas/`
(el HTML) para que un cambio no toque un archivo de 3.000 líneas.

```
secmkiosko/
├── index.php              Interfaz del punto de venta (contiene el HTML de todas las vistas)
├── app.js                 Lógica de la interfaz
├── estilos.css            Hoja de estilos
├── api.php                API JSON (productos, ventas, comandas, cajas, reportes, kardex)
├── config.php             Conexión a la base, esquema, respaldos y utilidades
├── sesion.php             Inicio de sesión, roles y control de permisos
├── login.php              Pantalla de ingreso
├── salir.php              Cierre de sesión
├── instalar.php           Crea la base y las tablas
├── respaldo.php           Descarga y restauracion de respaldos
├── respaldar-ejemplo.bat  Genera el respaldo de datos de prueba
├── respaldar-ejemplo.php  (el script que llama el .bat)
├── LEEME-RESPALDOS.txt    Qué se sube a git y qué no
├── LICENSE
└── datos/
    ├── ejemplo/           Volcado de prueba (se versiona, se va a eliminar)
    └── respaldo_*.sql     Respaldos reales (ignorados por git)
```

## Base de datos

Esquema actual: **7** (`config.esquema_version`). `instalar.php` y `config.php` lo actualizan
solos, así que no hace falta tocar la base a mano.

| Tabla | Contenido |
|---|---|
| `productos` | Catálogo, precio de venta, **costo**, stock, stock mínimo, categoría, unidad, proveedor, **`sin_stock`** |
| `productos_formatos` | Formatos de compra y venta (docena, maple, caja) con factor, precio y margen |
| `ventas` | Cabecera: folio, fecha, totales, medio de pago, anulación, envío, caja |
| `venta_items` | Detalle de cada venta, con el **costo congelado al vender** para el margen histórico |
| `movimientos` | Kardex de inventario, con **proveedor y número de remito** |
| `proveedores` | Datos de proveedores |
| `medios_pago` | Métodos de pago configurables (icono, si recibe vuelto, si pide referencia) |
| `usuarios` | Usuarios, roles y estado |
| `cajas` | Apertura y cierre de caja por usuario |
| `caja_cierre_metodos` | Conteo declarado por medio de pago al cerrar la caja |
| `comandas` | Pedidos de delivery, retiro o mesa |
| `comanda_items` | Ítems de cada comanda |
| `comanda_atajos` | Atajos de comanda (los productos de siempre) |
| `zonas` | Zonas de reparto con precio de envío |
| `config` | Datos del negocio, moneda, folios, tema, versión del esquema |

### La columna `sin_stock`

Es lo que distingue a un producto de venta libre de uno normal:

| | Producto normal | Producto de venta libre |
|---|---|---|
| Descuenta stock al vender | sí | **no** |
| Genera movimiento en el kardex | sí | **no** |
| Repone stock al anular la venta | sí | **no** |
| Avisa "agotado" o "stock bajo" | sí | **no** |
| Sale en faltantes / valuación | sí | **no** |
| Admite movimientos de stock | sí | **no** (el backend los rechaza) |

El costo se deja en 0 a propósito: los valores del proveedor cambian todos los días y armar
una receta por producto preparado no está a la altura del negocio todavía. Lo que manda es el
precio que se cobra.

## Atajos de teclado

| Tecla | Acción |
|---|---|
| `F2` | Ir a la búsqueda (para escanear) |
| `Enter` | Agregar el producto buscado |
| `F4` | Abrir el cobro |
| `Esc` | Cerrar ventana / limpiar búsqueda / borrar lo tipeado en el cobro |

## Notas de seguridad

- La base es local. No expongas el puerto 8080 a internet sin poner un proxy con HTTPS.
- La API usa sentencias preparadas y rechaza peticiones desde orígenes externos.
- El login responde el mismo mensaje para usuario inexistente y clave mala, a propósito.
- Las credenciales de MySQL se pueden sobrescribir con variables de entorno
  (`KIOSCO_DB_HOST`, `KIOSCO_DB_USER`, `KIOSCO_DB_PASS`, `KIOSCO_DB_NAME`)
  para no tener que tocar el código.
- En el respaldo de ejemplo las claves quedan saneadas a `admin`/`admin` con cambio
  de clave obligatorio.

## Licencia

MIT. Usalo, modificalo y vendelo como quieras.
