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

### Backend enrutado, frontend en módulos

`api.php` y `app.js` fueron monolitos de 1950 y 3325 líneas. Partir cualquier cambio
era arriesgoso, así que ambos están partidos por responsabilidad. El código no se reescribió:
el contenido de los módulos es el mismo, byte a byte, que el del monolito.

```
index.php        El kiosco (contiene el HTML de todas las vistas)
api.php          Enrutador: recibe ?accion= y hace require de la ruta que corresponde
login.php        Ingresar y cambiar clave
salir.php        Cerrar sesión
instalar.php     Instalador web
respaldo.php     Respaldo y restauración de la base

api/rutas/       11 archivos, uno por responsabilidad
inc/             config.php (conexión, esquema ESQUEMA_VERSION = 7, helpers) y
                 sesion.php (sesión y control de permisos)
js/              21 módulos del navegador, numerados en orden de carga
css/estilos.css  Todo el CSS
datos/           Respaldos .sql (ignorados por git, menos `datos/ejemplo/`)
```

**La raíz es la lista de direcciones del sistema.** Un archivo está ahí si y sólo si el
navegador lo puede pedir por URL. `config.php` y `sesion.php` no tienen dirección propia
—siempre entran por `require`—, así que viven en `inc/` y no ensucian la raíz.

Ojo con esto al tocar `inc/config.php`: `carpetaDatos()` resuelve la carpeta de respaldos
con `dirname(__DIR__)`, no con `__DIR__`, justamente porque el archivo está un nivel más
abajo que `datos/`. Con `__DIR__` los respaldos se irían a escribir en `inc/datos` y
`respaldo.php` mostraría la carpeta vacía.

**El código de barras se valida al guardar.** Un EAN-13 lleva un dígito verificador: los
12 primeros se pesan de 1,3,1,3... y el último tiene que completar la decena. El control vive
en `codigoAceptable()` de `inc/config.php` y se aplica **sólo** a los códigos de 13 dígitos,
así que un código interno con letras (`PAPA-001`) sigue entrando sin problema. Está en las
tres capas: al tipear (`revisarCodigoBarras`, que pinta el campo), al guardar
(`guardarProducto`) y en el servidor (`api/rutas/productos.php`, que responde 422). El del
servidor es el que importa: es el único que no se puede esquivar.

Esto no es un detalle menor. De una lista de 10 códigos de productos reales que nos pasó,
**5 tenían mal el dígito verificador**. Con un código así, el producto se guarda, aparece en
la grilla, se vende, y el lector de barras nunca lo encuentra: el error se descubre cuando el
cliente está esperando y no cuando se tipeó el número.

**Las categorías las maneja el administrador, no cada producto.** Antes el filtro de Vender
se armaba con un `Set` de lo que hubiera escrito cada producto: el sistema no sabia qué
categorías existían y cada quien escribía la suya. Con 10 productos no se notaba; con 200 eran
200 cosas que corregir a mano. Peor: `Bebidas` y `bebidas` cuentan como dos y Vender mostraba
dos fichas para lo mismo.

Ahora hay una tabla `categorias` (esquema **9**) que es la lista maestra, con `UNIQUE` sobre
`nombre`. Como la collation es `utf8mb4_unicode_ci`, MySQL no distingue mayúsculas, así que el
`UNIQUE` impide que se repitan con otra caja. El CRUD está en *Ajustes → Mercadería* y las
tres acciones de escritura (`categoria_guardar`, `categoria_borrar`) están en el mapa de
`api.php` como `ROL_ADMIN`; leer la lista es para cualquiera.

Dos decisiones que importan:

- **Renombrar mueve los productos.** Un `UPDATE productos SET categoria = ?` en la misma
  transacción. Si no, los productos quedan apuntando a un nombre que ya no existe y
  desaparecen de la ficha de Vender sin avisar.
- **`productos.categoria` sigue siendo texto, no una llave foránea.** Se__

parecía lo correcto, pero el buscador y los reportes comparan ese campo en varias partes
  (`05_punto_de_venta.js` busca por `p.categoria`, el filtro agrupa por él) y pasarlo a id
  obliga a tocar todo eso para ganar poco. Lo que sí se hace es **validar contra la tabla al
  guardar el producto**: si viene una categoría que no está en la lista, el servidor la
  rechaza con 422 y avisa que la cree en Ajustes. Es la barrera que importa, porque es la
  única que no se puede esquivar desde el formulario.

`Venta libre` es la excepción: la usa el atajo de carga rápida (`07_productos.js`) para los
productos sin control de stock, así que se siembra sola en la migración y no se deja borrar.
Si se perdiera, ese botón quedaría sin categoría con la que trabajar.

### Un cobro, dos papeles

En un kiosco con rotisería la venta es mezclada: el cliente lleva dos porciones de pizza, una
hamburguesa, un pancho, una Coca de cola, una de limón y una cerveza, y paga **una sola vez**.
Lo que va a la cocina y lo que se lleva el mostrador salen del mismo carrito.

Antes eran dos botones y dos cargas: el cajero armaba el ticket, cobraba, y después volvía a
cargar todo en la comanda de cocina. El doble tipeo no es un problema de paciencia: si se
equivoca en una de las dos, o la cocina prepara algo que nadie pagó o se cobra algo que nadie
preparó.

Ahora cada línea del carrito tiene un botón 🍳 que decide su destino. La línea nace con el
flag de su categoría (`categorias.cocina`, esquema **10**, que el administrador define en
Ajustes) y el cajero lo da la vuelta con un toque. Al confirmar el cobro, `venta_crear`
arma la comanda **en la misma transacción** que la venta (`api/rutas/ventas.php`), con las
líneas marcadas y atada al mismo `folio`, y sale impresa sin pedir nada. Si el papel no sale,
la comanda queda igual en el tablero de cocina.

La asimetría está a propósito: **el navegador solo puede apagar, nunca encender.** Si la
categoría está marcada como cocina (tragos, por ejemplo), el servidor la manda igual aunque
el cliente mande `cocina=0`. Un trago que se queda en el mostrador sin preparar es venta
perdida; una Coca de más en la comanda es un papel que la cocina descarta. Cuando el
administrador marque una categoría como cocina, es una decisión de él, no del cajero.

`guardarComanda()` ya existía (`api.php`) y ya se llamaba desde el cobro, pero el frontend
nunca le pasaba nada: la comanda se armaba con su propio botón. Eso es lo que se conectó.

**`api.php` es sólo el enrutador.** Quedó en 822 líneas (antes 1950) y cada `case` delega
con `require __DIR__ . '/api/rutas/<archivo>.php';`; las rutas abren su propio
`switch ($accion)`. Para tocar, por ejemplo, el cobro, se edita `api/rutas/ventas.php` y no
se busca dentro de 800 líneas.

| Ruta | Qué maneja |
|---|---|
| `productos.php` | alta, edición, baja, stock y categorías |
| `ventas.php` | carrito, cobro, comprobantes y anulación |
| `inventario.php` | kardex |
| `reportes.php` | ganancia, márgenes, ventas por hora y faltantes |
| `configuracion.php` | ajustes del negocio y respaldos |
| `cajas.php` | apertura, cierre y conciliación |
| `usuarios.php` | sesión y datos del usuario conectado |
| `admin_usuarios.php` | listado de usuarios |
| `usuario_crud.php` | alta, baja y cambio de clave de usuarios |
| `comandas.php` | comanda de cocina y atajos |
| `zonas.php` | zonas de delivery y su costo |

**Ojo con `js/`:** siguen siendo **scripts clásicos, no módulos ES**. Comparten a propósito
el mismo ámbito global y los eventos se enganchan en un único `DOMContentLoaded` al final
(`js/21_arranque.js`). El prefijo numérico **define el orden de carga** y `index.php` los
incluye en ese orden, así que un módulo no puede usar en su nivel superior algo definido en
un módulo de número mayor. Si alguna vez se pasa a módulos ES, hay que revisar ese enganche.

Al partir archivos hay que verificar tres cosas, porque un corte mal hecho no se ve: que
cada archivo tenga los comentarios balanceados, que no queden declaraciones de nivel
superior repetidas entre módulos (fatal al cargar) y que el conjunto reconstructo dé el
mismo código que el monolito.

### Servidor web: nginx o Apache, indistinto

La app **no depende del servidor web**. Se verificó que no usa `.htaccess`, `mod_rewrite`,
`REQUEST_URI`, `PATH_INFO` ni rutas del sistema de archivos, y que el frontend llama siempre
a `api.php?accion=...` por ruta directa. No hay reescritura de URLs, así que la misma
instalación funciona con nginx + php-fpm o con Apache + mod_php.

En esta máquina corre **nginx** en el puerto 8081, con vhost `secmkiosko.test` generado
por Laragon en `C:\laragon\etc\nginx\sites-enabled\auto.secmkiosko.test.conf`.
**Verificado también sobre Apache**, respondiendo 200 en `login.php`, `index.php` y
`api.php`, que es lo que sostiene la afirmación del README.

**La cookie de sesión está atada al host.** Si entrás por `secmkiosko.test:8081` la sesión
vive en ese host; si abrís `localhost:8081/secmkiosko` en la misma pestaña, el navegador no
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

---

## 8. Trabajo en equipo con git

Más de una persona (o más de una sesión) trabaja sobre este repo al mismo tiempo. El
código se empuja seguido, así que **hay que traer lo del remoto antes de editar, no
después de terminar.**

### El orden correcto

```bash
git fetch origin          # ver si el remoto se movió
git rebase origin/main    # si se movió, traérselo ANTES de editar
# ... recién acá tocar archivos ...
git commit
git push origin main
```

### Por qué importa tanto

Porque el remoto se mueve mientras uno está trabajando. Pasó esto: se empezó a tocar la
cabecera sobre `estilos.css`, y mientras tanto el otro lado pushed seis commits que
partieron el proyecto en `api/rutas/`, `js/`, `inc/` y `css/`. Al final el `push` fue
rechazado y había que rebasar un commit escrito contra un archivo que ya no existía en
esa ruta.

Si en lugar de eso se hubiera seguido diez minutos más, se estaría editando `app.js` un
archivo que ya había sido partido en 21 módulos.

### Reglas

- **Nunca `git push --force`.** Si el push es rechazado, es porque el otro lado trabajó.
  Rebasear, no pisar.
- **Commits chicos y frecuentes.** Un commit por tema. Si se trabaja media hora, se hace push.
  Un commit gigante es lo que hace que un rebase sea una pesadilla.
- **Antes de un cambio grande** (mover archivos, renombrar, partir un módulo): `git fetch`,
  y si el remoto se movió, rebasear **primero**.
- **Si un archivo fue movido o renombrado**, el `git rebase` lo detecta solo y aplica los
  cambios sobre la ruta nueva. Conviene verificar después que el cambio quedó donde debe.
- **Nunca compartir credenciales.** El push va por autenticación del navegador (GCM).
- Si un `push` es rechazado, primero `git log --oneline main..origin/main` para ver qué
  hay del otro lado. No asumir que es basura.

### Antes de dar por terminado un cambio

```bash
git status -sb              # tiene que decir main...origin/main, sin nada pendiente
git log --oneline -5        # confirmar que el historial está como corresponde
```

Y si el cambio tocó el armado (mover o partir archivos), cargar la app en el navegador y
confirmar que no rompió nada: que abra, que la consola JavaScript esté limpia y que las
vistas principales dibujen.
