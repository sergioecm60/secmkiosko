# Resumen de trabajo — secmkiosko

Documento de traspaso. Resume qué se hizo, por qué, cómo está armado el sistema hoy y qué
queda pendiente. Escrito para que otro shell o persona pueda retomar sin leer todo el código.

- **Proyecto:** kiosco POS (punto de venta) + control de existencias + comandas de cocina.
- **Stack:** PHP 8.3 y MySQL 8.4 sobre Laragon, **nginx** (no Apache), JS vanilla en el
  navegador, sin frameworks ni build, sin servicios en la nube. Corre entero en una PC Windows.
- **Repo:** <https://github.com/sergioecm60/secmkiosko>, rama `main`.
- **Último commit:** `86b6a19`.
- **Fecha del resumen:** 2026-09-28.

---

## 1. Cómo se levanta

```
git clone https://github.com/sergioecm60/secmkiosko C:\laragon\www\secmkiosko
```

1. Laragon → **Start All** (nginx + PHP + MySQL).
2. Entrar a <http://secmkiosko.test:8080/instalar.php> y seguir los pasos.
3. Entrar a <http://secmkiosko.test:8080> con `admin` / `admin`.
   El sistema **obliga a cambiar la clave en el primer ingreso** (`debe_cambiar_clave`).

Config de base en `config.php`, todo por variable de entorno con valor por defecto:

| Constante | Variable | Defecto |
|---|---|---|
| `DB_HOST` | `KIOSCO_DB_HOST` | `127.0.0.1` |
| `DB_USUARIO` | `KIOSCO_DB_USER` | `root` |
| `DB_CLAVE` | `KIOSCO_DB_PASS` | vacío |
| `DB_NOMBRE` | `KIOSCO_DB_NAME` | `kiosco` |

**No hay credenciales en el código.** La clave de MySQL se inyecta por entorno.

### Ojo con el host

Toda prueba tiene que pegarle a `secmkiosko.test:8080` y no a `localhost:8080` ni a
`127.0.0.1:8080`. La cookie de sesión está atada al host, así que si se prueba contra otra
dirección la sesión no se mantiene y los tests fallan con síntomas confusos
(`api is not defined`, `ReferenceError: agregarItemComanda is not defined`) que en realidad
son "el navegador quedó en `login.php`".

---

## 2. Historial de commits

| Commit | Qué hizo |
|---|---|
| `5cb1735` | Base: punto de venta y control de existencias. |
| `64e7229` | Costo y margen, proveedores y medios de pago configurables. |
| `2341af9` | README de costo, margen, proveedores y medios de pago. |
| `58520b6` | Locale `es-AR` y terminología del catálogo de ejemplo. |
| `ff42cd1` | Versiona un respaldo de ejemplo de la base, con los reales bloqueados. |
| `d9d0a93` | **Delivery con comanda de cocina, roles y venta sin control de stock.** También entra producto rápido. Lo más grande del proyecto: +5944 líneas. |
| `a1ee4a7` | README: la máquina ahora corre nginx, no Apache. |
| `c08d79d` | Limpia caracteres corruptos y aclara que la estructura plana es intencional. |
| `86b6a19` | **Saca la comanda del modal de delivery y la separa del cobro.** |

---

## 3. Trabajo de esta sesión

Fueron tres bloques. El primero y segundo ya están commiteados; el tercero también.

### 3.1 Producto rápido y venta sin control de stock

Producto que se cobra sin que baje del inventario.

- El precio **nunca** se toma del navegador: lo recalcula el backend desde el formato
  predeterminado del producto dividido por su factor. Un `precio` mandaroso desde el cliente
  no cambia el total.
- `sin_stock = 1`   descuenta, no hace nada. No genera movimiento de kardex, no aparece en los avisos de stock
  bajo ni en la valuación de inventario. Sirve para cosas que se venden siempre y nunca hace
  falta controlar (pan, hielo, servilletas).

- Si el nombre ya existe, **actualiza el precio en vez de duplicar**. Al reutilizar un producto
  se preservan stock, costo y proveedor: no pisa lo que ya estaba.
- Se respected que el rol de cocina no entre al punto de venta: `puedeVender()` acepta admin y
  vendedor, y nada más.
- Venta sin control de stock, kardex con filtros, anulación de venta, reportes y el alta en la
  UI.

**Pruebas:** API 41/41 y navegador 35/35.

### 3.2 Roles, delivery, y limpieza de caracteres

- Sistema de roles completo con mapa de permisos en `api.php` (§5).
- Módulo de delivery con zonas, mesas, retiro y costo por zona.
- `c08d79d` limpió un `U+FF0B` (signo más de ancho completo) que rompía el botón de Producto
  rápido, y dos ideogramas chinos donde en `respaldo.php` debía decir "exporta". No se
  reproducen acá a propósito: volver a pegarlos reintroduciría justo lo que se sacó.
  También dejó escrito en el README que la estructura plana de archivos es **intencional**:
  este proyecto no usa subcarpetas para el código, y no hay que "arreglarla".
- `a1ee4a7`: documentó que la máquina sirve con nginx. Antes el repo estaba preparado para
  Apache y ya no.

### 3.3 La comanda deja de ser parte del cobro

Este fue el cambio de fondo, pedido explícitamente: *"cambia atajos de comanda y ponelo en un
botón como producto al lado de producto"*, con la aclaración de que el local es el caso
principal.

**El problema.** La comanda estaba dentro del modal de delivery y el botón decía "Guardar
comanda y cobrar". Eso ataba las dos cosas: para lo que se consume en el local no servía,
porque no hay dirección y no hay por qué cobrar desde ahí. El cajero tenía que guardar el
pedido de una forma o de otra.

**El modelo nuevo.** La comanda es el papel con lo que hay que preparar; la venta es el cobro.
Son dos pasos separados:

1. El cajero carga productos y cobra en el carrito. De ahí sale el remito.
2. Aparte saca la comanda desde un botón nuevo al lado de **Producto rápido**.

En la comanda: copia el carrito con "Traer del carrito", suma atajos o escribe líneas a mano,
y la imprime para llevársela a la cocina. Para consumo local no hay que completar nada.

**Cambios de backend** (`api.php`):

- Nueva acción `comanda_crear`: guarda la comanda sola, sin venta ni folio. Permiso
  `ROL_VENTA`, porque ya no es parte del cobro.
- `guardarComanda()` acepta `$ventaId` y `$folio` nulos. Firma actual:
  `guardarComanda(PDO $bd, array $d, ?int $ventaId, ?int $folio, float $total, string $usuario)`.
- Cliente opcional: si no se pone ninguno, la comanda queda como `Mostrador`.
- Se limpian y validan las líneas **antes** de insertar. Antes se insertaba la comanda y
  recién después se chequeaba que tuviera items, así que una comanda vacía dejaba una fila
  huérfana en el tablero de cocina.
- Se eliminó el parámetro `$envio` de `guardarComanda()`: era muerto, se reasignaba siempre
  desde la zona.
- El acoplamiento histórico de `venta_crear` con `datosComanda` **se dejó** por compatibilidad
  con llamadas viejas, pero el frontend ya no lo usa.

**Cambios de frontend** (`app.js`, `index.php`, `estilos.css`):

- El modal `#m-delivery` se reemplaza por `#m-comanda`. Tipo inicial "En el local"; nombre,
  mesa, zona y dirección quedan opcionales. La zona carga su costo desde el backend y queda
  como dato informativo.
- Botón `#btn-comanda` ("🍳 Comanda de cocina") junto a `#btn-rapido`.
- Un atajo usado desde la comanda **agrega sólo la línea de preparación**: no toca el carrito.
  Antes metía el producto en el carrito, que es exactamente el bug de "cobraban de más",
  porque el cobro es un paso aparte.
- El envío de la comanda dejó de sumar al total del ticket.
- Al guardar se ofrece imprimir; el botón Imprimir queda listo para sacar otra copia sin
  volver a armar la comanda (`ultimaComanda`).
- Líneas repetidas se acumulan en cantidad. `norm()` pasó a quitar los bordes y a comparar en
  minúsculas, así que "Pan" y "pan " son la misma línea.
- El administrador crea y edita atajos con botón y lápiz **dentro del panel de la comanda**; el
  vendedor sólo los usa. El backend ya lo negaba, ahora también la pantalla.

**Un bug de paso.** Borrar un atajo o una zona no hacía nada: se pasaba un tercer argumento a
`confirmar()`, que sólo recibe título y texto y devuelve una promesa. La confirmación aparecía
pero el borrado nunca se ejecutaba. Corregido.

**Pruebas:** 33/33 de comanda y 15/15 de atajos y permisos, sobre Chrome headless contra
`secmkiosko.test:8080`. Total 48/48 en la última corrida.

---

## 4. Estructura

Plana, y a propósito. No hay subcarpetas de código:

```
api.php      API JSON: todo el CRUD y las acciones de negocio
app.js       Toda la lógica del navegador
index.php    El kiosco
login.php    Entrar y cambiar clave
sesion.php   Sesión y roles
config.php   Conexión, esquema (ESQUEMA_VERSION = 7) y helpers
instalar.php Instalador web
respaldo.php Respaldo de la base
estilos.css  Todo el CSS
```

Esquema en **versión 7**. 15 tablas: `productos`, `productos_formatos`, `medios_pago`,
`proveedores`, `usuarios`, `cajas`, `caja_cierre_metodos`, `ventas`, `venta_items`,
`movimientos`, `comandas`, `comanda_items`, `comanda_atajos`, `zonas`, `config`.

---

## 5. Roles y permisos

Tres roles reales: `admin`, `vendedor`, `cocina`. El mapa está en `api.php` (§líneas ~464-523)
con cuatro niveles: `publico`, `autenticado`, `venta`, `admin`.

- `esAdmin()` → rol `admin`.
- `puedeVender()` → admin o vendedor. Cocina queda afuera aunque tenga sesión abierta.
- `esCocina()` → rol `cocina`. No entra al punto de venta.

Puntos que importan para la comanda:

| Acción | Nivel |
|---|---|
| `comandas`, `comanda`, `comanda_estado`, `comanda_item`, `comanda_atajos`, `zonas` | autenticado |
| `comanda_crear` | venta |
| `atajo_guardar`, `atajo_borrar`, `zona_guardar`, `zona_borrar` | admin |
| `venta_crear`, `venta_anular`, `caja_abrir`, `caja_cerra` | venta |

Además, mientras `debe_cambiar_clave = 1` la API rechaza todo salvo `clave_cambiar` y
`sesion_info`: no se opera el kiosco con la clave de fábrica.

---

## 6. Estado de la base ahora

49 productos, 242 formatos, 3 zonas, 14 atajos de comanda, 4 medios de pago, 1 proveedor,
1 usuario, 1 caja, 0 ventas, 0 ítems de venta, 0 movimientos, 0 comandas.

Pendiente de confirmar: el producto **id 61, "pancho"** (`sin_stock = 1`, categoría Almacen,
sin formatos, creado 2026-09-28 22:19) no estaba en el baseline de 48 y no lo creó ninguno de
los tests de esta sesión. No se borró porque parece cargado a mano. Decidir si se queda.

---

## 7. Pendientes

1. **Botón de borrar atajo en el panel de comanda.** El pedido fue "editar también desde el
   botón" y se entendió que crear, cambiar y borrar iban juntos en el mismo lugar. Hoy hay
   botón nuevo y lápiz de editar; **borrar sólo se puede desde Ajustes**. El backend ya lo
   soporta (`atajo_borrar`, `ROL_ADMIN`), así que es UI nada más.
2. **"Traer del carrito" es acumulativo.** Si se pulsa dos veces, las cantidades se duplican
   porque las líneas se fusionan sumando. Decidir si se hace idempotente, si se pide
   confirmación, o si se limpia la comanda en curso antes de copiar.
3. **`ir("cocina")` no limpia `estado.cocTimer`.** Detalle previo, sin relación con la comanda.
4. Decidir qué hacer con "pancho" (§6).

---

## 8. Cómo se probó

Tests de navegador con Chrome headless por CDP, levantando un listener en `9222` y hablando
`Runtime.evaluate` contra la página real. No hay framework de tests: son scripts sueltos en
temporales, **no versionados a propósito**.

Herramientas:

- PHP: `C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe`
- Node: `C:\laragon\bin\nodejs\node-v22\node.exe`
- MySQL: `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`

Corrientes al testear (a mano, con `git checkout` o `git reset` después):

- **El modal abierto se marca con la clase `on`**, no con `abierto`. Una aserción con
  `abierto` devuelve `false` siempre y el test pasa al vacío.
- Un usuario recién creado queda con `debe_cambiar_clave = 1` y `login.php` lo devuelve a
  cambiar la clave sin dejarlo entrar. Hay que limpiarlo por SQL antes de probar el rol.
- `confirmar()` re-renderiza la tabla, así que un nodo que el test haya inyectado a mano ya no
  existe para el `removeChild` de limpieza.
- Las pruebas **ensucian la base**: ventas, comandas, usuarios, atajos y stock. Hay que
  limpiar al terminar, o los números de la §6 dejan de ser ciertos.

---

## 9. Seguridad de los respaldos

`.gitignore` bloquea `datos/*` y sólo deja pasar `datos/ejemplo/`.

Motivo: los respaldos automáticos contienen datos reales del negocio —productos, **costos de
compra**, márgenes, ventas y proveedores— y subirlos a un repositorio público los deja
expuestos de forma permanente. El costo de compra y el margen no son datos públicos.

El respaldo versionado está saneado a propósito: el proveedor está marcado como
"Proveedor de ejemplo del sistema" y el `admin` viene con `debe_cambiar_clave = 1`.

`respaldar-ejemplo.bat` / `respaldar-ejemplo.php` generan un respaldo **sin** datos reales, y
son los únicos que se versionan. Para llevar la base a otra PC, usar `respaldo.php` y
transportar el archivo por fuera del repo.

---

## 10. Invariantes que no hay que romper

Estas son las cosas que costaron encontrar bugs. Si se tocan, hay que volver a probar.

1. **El precio se recalcula en el servidor.** Jamás confiar en el precio que manda el navegador.
2. **`sin_stock` no toca inventario.** Sin movimiento, sin kartes, sin valuación.
3. **Reutilizar un producto no pisa stock, costo ni proveedor.**
4. **`norm()` antes de comparar claves de línea**, para que "Pan", " pan" y "PAN" sean la
   misma línea de comanda.
5. **Una comanda sin items no se inserta.** Validar antes de insertar, o queda huérfana.
6. **La comanda no fuerza cobro.** `venta_id` y `folio` van nulos; el cobro es otro paso.
7. **La clave de fábrica no opera:** `debe_cambiar_clave = 1` bloquea todo.
8. **Cocina no cobra**, aunque tenga la sesión abierta.
9. **Probar siempre contra `secmkiosko.test:8080`.**
