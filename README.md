# secmkiosko

Punto de venta, comandas de cocina y control de existencias para kiosco, almacén y delivery.
Corre entero en una PC, sin internet y sin servicios en la nube.

**PHP 8** · **MySQL 8** · **JavaScript** · **CSS** · **nginx** o **Apache** · **Windows** o **Linux** · MIT

---

## Qué es

El cajero carga productos, cobra y sale un remito. Aparte saca la comanda de cocina, que es
un papel con lo que hay que preparar: no está atada a la venta ni obliga a cobrar. El stock
se descuenta solo, queda el kardex de cada movimiento y los reportes muestran ganancia y
margen con el costo congelado al vender.

## Requisitos

PHP 8 con `pdo_mysql`, MySQL 8 (o MariaDB 10.4+) y cualquier servidor web que ejecute PHP.
En Windows, [Laragon](https://laragon.org/download) trae las tres cosas y las levanta con
**Start All**. En Linux, nginx con `php-fpm` o Apache con `mod_php` sirviendo la carpeta.

**No hace falta ningún `.htaccess` ni regla de reescritura**: el frontend llama siempre a
`api.php?accion=...` por ruta directa.

## Puesta en marcha

```bash
git clone https://github.com/sergioecm60/secmkiosko.git
```

Copiá la carpeta al raíz del sitio, abrí `instalar.php` y seguí los pasos. Entrás con
`admin` / `admin`; el sistema te obliga a cambiar la clave en el primer ingreso.

La base de datos **no viaja en el repo**: se crea al instalar, o se restaura desde un
respaldo `.sql` generado en *Ajustes → Respaldos*.

---

## Estructura

```
index.php        El kiosco (contiene el HTML de todas las vistas)
api.php          Enrutador: recibe ?accion= y carga la ruta que corresponde
login.php        Ingresar y cambiar clave
salir.php        Cerrar sesión
instalar.php     Instalador web
respaldo.php     Respaldo y restauración de la base

api/rutas/       La API, partida por responsabilidad (productos, ventas, cajas, ...)
inc/             config.php (conexión, esquema, helpers) y sesion.php (sesión y permisos)
js/              La lógica del navegador, en módulos numerados por orden de carga
css/estilos.css  Todo el CSS
datos/           Respaldos .sql (ignorados por git, menos `datos/ejemplo/`)
```

En la raíz queda **sólo lo que el navegador pide por URL** (más los documentos). Lo que sólo
se incluye desde otro archivo, como `config.php` y `sesion.php`, vive en `inc/`: no tienen
dirección propia, entonces no ensucian la raíz ni aparecen en los listados del servidor.

Para tocar una parte del sistema se va directo al archivo que la maneja: el cobro está en
`api/rutas/ventas.php` y en `js/06_cobro.js`. No hay que bucear en un archivo único.

---

## Dónde se puede montar

El servidor y el sistema operativo no importan: no hay `.htaccess`, ni reglas de
reescritura, ni rutas del sistema de archivos. El frontend llama siempre a
`api.php?accion=...` por ruta directa, así que la misma instalación anda con
**nginx + php-fpm** o con **Apache + mod_php**.

### Red local, varias PCs

Lo habitual: una PC con la base y el kiosco, y el resto de las PCs de la red entran por
el navegador a la IP de esa máquina.

- **Windows:** instalá [XAMPP](https://www.apachefriends.org/) o
  [Laragon](https://laragon.org/download) en la PC que hace de servidor y abrí el puerto
  del servidor web en el firewall (80 u 8080).
- Desde cualquier otra PC se entra con `http://IP-DEL-SERVIDOR/secmkiosko/`.
- Cada cajero entra con su usuario. El sistema exige caja abierta antes de cobrar y cada
  uno sólo anula lo que está en su caja, así que varios pueden vender al mismo tiempo
  sobre el mismo stock sin pisarse.

### Linux, física o virtual

Sirve igual en un servidor propio, en una VM o en la nube. Lo único a tener en cuenta es
el **MySQL**: la app se conecta desde la misma máquina donde corre PHP, así que conviene
atarlo a `127.0.0.1` y no dejarlo escuchando en toda la red.

### Exponerlo a internet

**No abras el puerto tal cual.** El sistema está pensado para una red de confianza y la
conexión es en HTTP plano: sin HTTPS las contraseñas y la cookie de sesión viajan en
claro. Antes de exponerlo hace falta, como mínimo:

1. **HTTPS** con un proxy adelante (nginx con certificado, Caddy o Apache con TLS).
2. **Un túnel o VPN** en lugar de abrir puertos: WireGuard, Tailscale o similar. Es la
   opción más simple y evita dejar el servicio expuesto a todo internet.
3. **MySQL atado a `127.0.0.1`**, con el puerto 3306 cerrado en el firewall.
4. Cambiar la clave de fábrica en el primer ingreso, como ya pide el sistema.

> En LAN, sin salir de la red de confianza, alcanza con el firewall y los usuarios del
> sistema. Para internet, lo de arriba es obligatorio.

---

## Documentación

| | |
|---|---|
| [`datos/HOJA-DE-RUTA.md`](datos/HOJA-DE-RUTA.md) | Estado del proyecto, decisiones, cómo está armado y qué falta |
| [`datos/ejemplo/RESUMEN-TRABAJO.md`](datos/ejemplo/RESUMEN-TRABAJO.md) | Resumen de traspaso |
| [`LEEME-RESPALDOS.txt`](LEEME-RESPALDOS.txt) | Qué se sube a git y qué no, y qué hacer si subís datos reales |

## Respaldos

**Descargá uno todos los días** desde *Ajustes → Respaldos*. También se genera solo antes de
cualquier borrado. Un respaldo real trae costos, márgenes y ventas, así que está bloqueado
por `.gitignore` a propósito.

## Licencia

MIT. Usalo, modificalo y vendelo como quieras.
