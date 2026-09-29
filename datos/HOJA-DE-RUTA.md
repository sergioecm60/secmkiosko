# Hoja de ruta — secmkiosko

Estado del proyecto, decisiones tomadas y qué falta. Acá vive el detalle técnico; el
[`README.md`](../README.md) sólo dice qué es y cómo se levanta.

**Última actualización:** 2026-09-29

---

## 1. Dónde estamos

Punto de venta y control de existencias funcionando, con comandas de cocina, delivery,
roles y caja. Corre en una sola PC, sin internet.

- **Esquema de base:** versión 7, 15 tablas
- **Rama:** `main`, con el código en `86b6a19` y commits de documentación encima
- **Estado de la base al cierre:** 49 productos, 242 formatos, 3 zonas, 14 atajos de comanda,
  4 medios de pago, 1 proveedor, 1 usuario, 1 caja, 0 ventas, 0 comandas

El detalle día por día está en
[`datos/ejemplo/RESUMEN-TRABAJO.md`](ejemplo/RESUMEN-TRABAJO.md). Este documento es el
 panorama para el que quiera retomar sin leer los commits.

---

## 2. Cómo está armado

### Estructura plana, y a propósito

No hay subcarpetas de código. Funciona, está comentado y `app.js` ya está partido en
secciones numeradas. Partirlo en carpetas tiene sentido cuando empiece a molestar.

```
index.php      El kiosco (contiene el HTML de todas las vistas)
app.js         Toda la lógica del navegador
estilos.css    Todo el CSS
api.php        API JSON: todo el CRUD y las acciones de negocio
config.php     Conexión, esquema (ESQUEMA_VERSION = 7) y helpers
sesion.php     Sesión, roles y control de permisos
login.php      Ingresar y cambiar clave
salir.php      Cerrar sesión
instalar.php   Instalador web
respaldo.php   Respaldo y restauración de la base
```

**Ojo con `app.js`:** son scripts clásicos, no módulos. Todas las secciones comparten a
propósito el mismo ámbito global y los eventos se enganchan en un único `DOMContentLoaded`
al final. Si alguna vez se pasa a módulos ES, hay que revisar ese enganche.

### Servidor web: nginx o Apache, indistinto

La app **no depende del servidor web**. Se verificó que no usa `.htaccess`, `mod_rewrite`,
`REQUEST_URI`, `PATH_INFO` ni rutas del sistema de archivos, y que el frontend llama siempre
a `api.php?accion=...` por ruta directa. No hay reescritura de URLs, así que la misma
instalación funciona con nginx + php-fpm o con Apache + mod_php.

En esta máquina corre **nginx** en el puerto 8080, con vhost `secmkiosko.test` generado
por Laragon en `C:\laragon\etc\nginx\sites-enabled\auto.secmkiosko.test.conf`.
**Verificado también sobre Apache**, respondiendo 200 en `login.php`, `index.php` y
`api.php`, que es lo que sostiene la afirmación del README.

**La cookie de sesión está atada al host.** Si entrás por `secmkiosko.test:8080` la sesión
vive en ese host; si abrís `localhost:8080/secmkiosko` en la misma pestaña, el navegador no
manda la cookie y la API responde *"tu sesión se venció"*. **Probá siempre contra la misma
dirección**, o los tests fallan con síntomas que no dicen la verdad
(`api is not defined`, `ReferenceError: agregarItemComanda is not defined`).

### Despliegue previsto

El destino es multiusuario, no una PC sola:

- **LAN en Windows:** XAMPP o Laragon en una PC que hace de servidor, puerto abierto en el
  firewall, y las demás PCs entran por navegador a la IP del servidor.
- **Linux físico o virtual**, en la nube o en la oficina, con el mismo código.

El sistema es **de red de confianza**: la conexión es HTTP plano y `cookie_secure` sale de
la configuración en `false`. Para exponerlo a internet hay que poner HTTPS adelante y usar
un túnel o VPN, no abrir puertos. Está anotado en el README.

Dos cosas que ya están bien para multiusuario y conviene no romper: el stock se descuenta
con `SELECT ... FOR UPDATE` dentro de una transacción (no se sobrevende con dos cajeros
simultáneos), y cada vendedor sólo anula lo que está en su caja abierta.

**MySQL:** escucha en todas las interfaces, pero `root` sólo acepta `localhost`, así que
desde la red responde `Host not allowed`. Aun así conviene atarlo a `127.0.0.1`, porque la
app no lo necesita expuesto.

### Configuración

`config.php`, todo por variable de entorno con valor por defecto:

| Constante | Variable | Defecto |
|---|---|---|
| `DB_HOST` | `KIOSCO_DB_HOST` | `127.0.0.1` |
| `DB_USUARIO` | `KIOSCO_DB_USER` | `root` |
| `DB_CLAVE` | `KIOSCO_DB_PASS` | vacío |
| `DB_NOMBRE` | `KIOSCO_DB_NAME` | `kiosco` |

**No hay credenciales en el código.** En Linux hay que setear las variables, porque `root`
sin clave es el default de Laragon y no el de una instalación normal.

### Roles y permisos

Tres roles: `admin`, `vendedor`, `cocina`. El mapa está en `api.php` con cuatro niveles:
`publico`, `autenticado`, `venta`, `admin`.

- `esAdmin()` — rol `admin`
- `puedeVender()` — admin o vendedor. **Cocina queda afuera** aunque tenga sesión abierta
- `esCocina()` — rol `cocina`, no entra al punto de venta

Mientras `debe_cambiar_clave = 1` la API rechaza todo salvo `clave_cambiar` y `sesion_info`:
no se opera el kiosco con la clave de fábrica.

### El modelo de comanda

La decisión de fondo: **la comanda es el papel con lo que hay que preparar; la venta es el
cobro. Son dos pasos separados.** Antes la comanda estaba dentro del modal de delivery con
un botón que decía "Guardar comanda y cobrar", lo que ataba las dos cosas y no servía para
consumo en el local, donde no hay dirección ni por qué cobrar desde ahí.

Ahora el cajero cobra en el carrito (de ahí sale el remito) y aparte saca la comanda desde
el botón que está al lado de *Producto rápido*: copia el carrito con "Traer del carrito",
suma atajos o escribe líneas a mano, y la imprime para la cocina.

`comanda_crear` guarda la comanda sola, sin venta ni folio, con permiso `ROL_VENTA` porque
ya no es parte del cobro. El cliente es opcional: sin ninguno, la comanda queda como
*Mostrador*.

### La columna `sin_stock`

Distingue un producto de venta libre de uno normal:

| | Producto normal | Venta libre |
|---|---|---|
| Descuenta stock al vender | sí | **no** |
| Genera movimiento en el kardex | sí | **no** |
| Repone stock al anular | sí | **no** |
| Avisa "agotado" o "stock bajo" | sí | **no** |
| Sale en faltantes y valuación | sí | **no** |
| Admite movimientos de stock | sí | **no**, el backend los rechaza |

El costo queda en 0 a propósito: los valores del proveedor cambian seguido y armar una
receta por producto preparado no está a la altura del negocio todavía. Manda el precio que
se cobra.

### Formatos de compra y venta

Un maple, una caja de 24 o una docena tienen su propio precio. El precio del producto sale
del **formato predeterminado dividido por su factor**, y el backend lo recalcula siempre:
un `precio` mandoso desde el navegador no cambia el total.

---

## 3. Invariantes

Estas son las cosas que costaron encontrar bugs. Si se tocan, hay que volver a probar.

1. **El precio se recalcula en el servidor.** Jamás confiar en el precio que manda el navegador.
2. **`sin_stock` no toca inventario.** Sin movimiento de kardex, sin valuación.
3. **Reutilizar un producto no pisa stock, costo ni proveedor.** Si el nombre ya existe,
   actualiza el precio en vez de duplicar.
4. **`norm()` antes de comparar claves de línea**, para que "Pan", " pan" y "PAN" sean la
   misma línea de comanda. Acota los bordes y compara en minúsculas.
5. **Una comanda sin items no se inserta.** Validar antes de insertar o queda huérfana.
6. **La comanda no fuerza cobro.** `venta_id` y `folio` van nulos.
7. **La clave de fábrica no opera:** `debe_cambiar_clave = 1` bloquea todo.
8. **Cocina no cobra**, aunque tenga la sesión abierta.
9. **Probar siempre contra el mismo host** que el de la sesión.
10. **El modal abierto se marca con la clase `on`**, nunca con `abierto`.

---

## 4. Pendientes

Los tres primeros son cambios chicos, ninguno rompe nada, y **no se empezaron a tocar a
propósito** porque falta decidir con el usuario.

1. **Botón de borrar atajo en el panel de comanda.** El pedido fue "editar también desde el
   botón" y se entendió que crear, cambiar y borrar iban juntos en el mismo lugar. Hoy hay
   botón nuevo y lápiz de editar; **borrar sólo se puede desde Ajustes**. El backend ya lo
   soporta (`atajo_borrar`, `ROL_ADMIN`), así que es UI nada más.
2. **"Traer del carrito" es acumulativo.** Si se pulsa dos veces, las cantidades se duplican
   porque las líneas se fusionan sumando. Decidir si se hace idempotente, si pide
   confirmación, o si limpia la comanda en curso antes de copiar.
3. **`ir("cocina")` no limpia `estado.cocTimer`.** Detalle previo, sin relación con la comanda.
4. **Carrera de folio con dos cajeros cobrando a la vez.** `venta_crear` calcula el folio con
   `SELECT COALESCE(MAX(folio),0)+1 FROM ventas` **sin bloquear**, dentro de la transacción.
   Si dos cajeros cobran en el mismo instante, los dos leen el mismo máximo y calculan el
   mismo folio. Como `ventas.folio` es `UNIQUE KEY uq_folio`, **una de las dos ventas falla**
   con error de clave duplicada: no se guarda y el cajero ve un fallo sin explicación. No
   corrompe datos ni descuenta stock dos veces, pero es un error visible y hay que resolverlo
   antes de poner dos terminales.

   Opciones: bloquear la lectura (`SELECT ... FOR UPDATE` sobre una fila contadora, o
   `GET_LOCK()` de MySQL), usar el `AUTO_INCREMENT` del `id` como folio, o un contador en
   `config` con `UPDATE ... SET folio = folio + 1` leído con `LAST_INSERT_ID()`.

   **El stock no sufre esto:** ya usa `FOR UPDATE` en los cinco lugares donde toca
   `productos`, así que dos cajeros simultáneos no sobrevenden.
5. **Sin token CSRF.** La cookie va con `SameSite=Lax` y `HttpOnly`, que en los navegadores
   actuales bloquea el POST desde otro sitio, así que en LAN el riesgo es bajo. No es un
   token real: si el servicio llegara a servirse en el mismo dominio que otra aplicación, la
   garantía se diluye.
6. **Sin límite de intentos en el login.** No hay bloqueo por intentos fallidos, o sea que la
   fuerza bruta es posible. Bajo en LAN, relevante si se expone a internet.

Los primeros tres son cambios chicos y ninguno rompe nada. Nada de esto se empezó a tocar a
propósito: falta decidir con el usuario, y el segundo es una decisión de cómo debería
comportarse la UI, no un bug con una única respuesta obvia. **El cuarto sí conviene
resolverlo antes del despliegue multi-PC.**

El segundo no es un bug con respuesta obvia: es una decisión de cómo debería comportarse la
interfaz.

### Un producto que hay que revisar

Existe un producto **id 61, "pancho"** (`sin_stock = 1`, categoría Almacen, precio 2000, sin
formatos, creado el 2026-09-28) que no estaba en el baseline de 48 productos y que no creó
ninguno de los tests. Por eso se decidió **dejarlo**: borrar es destructivo e irreversible, y
en el peor caso se tiraba algo cargado a mano.

Si sobra, se borra con una línea y no rompe nada. Si se conserva como producto real,
conviene cargarle un formato de venta, porque el precio de los productos con formato se
deriva del predeterminado y este no tiene ninguno.

---

## 5. Cómo se probó

Tests de navegador con Chrome headless por CDP, levantando un listener en `9222` y hablando
`Runtime.evaluate` contra la página real. **No hay framework de tests**: son scripts sueltos
en temporales, no versionados a propósito.

- **PHP:** `C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe`
- **Node:** `C:\laragon\bin\nodejs\node-v22\node.exe`
- **MySQL:** `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`
- **Lint:** `php -l` y `node --check`

Última corrida: **41/41** API, **35/35** navegador (producto rápido), **33/33** comanda,
**15/15** atajos y permisos. Total **48/48**.

### Corrientes al testear

- Un usuario recién creado queda con `debe_cambiar_clave = 1` y `login.php` lo devuelve a
  cambiar la clave sin dejarlo entrar. Hay que limpiarlo por SQL antes de probar el rol.
- `confirmar()` re-renderiza la tabla, así que un nodo inyectado a mano ya no existe para
  el `removeChild` de limpieza.
- **Las pruebas ensucian la base:** ventas, comandas, usuarios, atajos y stock. Hay que
  limpiar al terminar o los números de la §1 dejan de ser ciertos.
- Todos los archivos versionados son UTF-8 válido, sin CJK ni caracteres de reemplazo.

---

## 6. Seguridad de los respaldos

`.gitignore` bloquea `datos/*` y sólo deja pasar `datos/ejemplo/`. Motivo: los respaldos
automáticos traen productos, **costos de compra**, márgenes, ventas y proveedores. Subidos
a un repositorio público quedan expuestos de forma permanente, porque el historial de git
no se borra de verdad. El costo y el margen no son datos públicos.

El respaldo versionado está saneado a propósito: el proveedor es "Proveedor de ejemplo del
sistema" y el `admin` viene con `debe_cambiar_clave = 1`.

`respaldar-ejemplo.bat` / `.php` generan el único respaldo que se versiona, y avisan si
encuentran ventas reales antes de escribir. Para llevar la base a otra PC, usar
`respaldo.php` y transportar el archivo por fuera del repo.

**Pendiente:** `datos/ejemplo/` se va a eliminar del repositorio. Mientras tanto se sigue
manteniendo, así que no tires el `.gitignore` ni la carpeta sin avisar.

Si alguna vez se subió un respaldo real por error, el procedimiento está en
[`LEEME-RESPALDOS.txt`](../LEEME-RESPALDOS.txt).

---

## 7. Atajos de teclado

| Tecla | Acción |
|---|---|
| `F2` | Ir a la búsqueda (para escanear) |
| `Enter` | Agregar el producto buscado |
| `F4` | Abrir el cobro |
| `Esc` | Cerrar ventana, limpiar búsqueda, borrar lo tipeado en el cobro |
