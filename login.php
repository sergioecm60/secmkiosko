<?php
/**
 * Kiosco — ingreso al sistema.
 *
 * Al primer ingreso con la clave "admin" el sistema obliga a cambiarla:
 * mientras `debe_cambiar_clave` esté en 1 no se entra al punto de venta.
 */
declare(strict_types=1);
require __DIR__ . '/sesion.php';

if (!baseExiste()) {
    header('Location: instalar.php');
    exit;
}

sesionIniciar();

/* ---------- ¿Ya está adentro? ---------- */
$usuario = usuarioActual();
$forzarCambio = $usuario !== null && (int) $usuario['debe_cambiar_clave'] === 1;
if ($usuario !== null && !$forzarCambio) {
    header('Location: index.php');
    exit;
}

$error = '';
$ok = false;
$modo = $forzarCambio ? 'cambiar' : 'entrar';

/* ---------- Procesar el formulario ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    /* --- Login --- */
    if ($accion === 'entrar') {
        $nombre = trim((string)($_POST['usuario'] ?? ''));
        $clave  = (string)($_POST['clave'] ?? '');

        if ($nombre === '' || $clave === '') {
            $error = 'Escribí tu usuario y tu clave.';
        } else {
            $st = pdoBd()->prepare('SELECT * FROM `usuarios` WHERE usuario = ? AND activo = 1');
            $st->execute([$nombre]);
            $u = $st->fetch();

            if (!$u || !password_verify($clave, (string)$u['clave'])) {
                // Mismo mensaje para usuario inexistente y clave mala: no
                // conviene leaky help en una caja.
                $error = 'Usuario o clave incorrectos.';
                usleep(400000);
            } else {
                // Reinicio de sesión contra session fixation.
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (int)$u['id'];
                marcarIngreso((int)$u['id']);

                if ((int)$u['debe_cambiar_clave'] === 1) {
                    $modo = 'cambiar';
                } else {
                    header('Location: index.php');
                    exit;
                }
            }
        }
    }

    /* --- Cambio de clave --- */
    if ($accion === 'cambiar') {
        $actual = (string)($_POST['actual'] ?? '');
        $nueva  = (string)($_POST['nueva'] ?? '');
        $repetir = (string)($_POST['repetir'] ?? '');

        if (!password_verify($actual, (string)$usuario['clave'])) {
            $error = 'La clave actual no es correcta.';
        } elseif (strlen($nueva) < 4) {
            $error = 'La clave nueva debe tener al menos 4 caracteres.';
        } elseif ($nueva !== $repetir) {
            $error = 'Las dos claves nuevas no coinciden.';
        } else {
            pdoBd()->prepare('UPDATE `usuarios` SET clave = ?, debe_cambiar_clave = 0 WHERE id = ?')
                  ->execute([password_hash($nueva, PASSWORD_DEFAULT), (int)$usuario['id']]);
            $ok = true;
            header('Location: index.php');
            exit;
        }
    }
}

$cfg = leerConfig();
$marca = texto($cfg['negocio'] ?? 'Kiosco', 40);
$iniciales = texto($cfg['logo'] ?? 'K', 2);
if ($iniciales === '') { $iniciales = 'K'; }
$moneda = texto($cfg['moneda'] ?? '$', 4);
$titulo = texto(valorConfig('pin', ''), 60); // si hay pin, se muestra arriba
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar · <?= htmlspecialchars($marca, ENT_QUOTES) ?></title>
<link rel="stylesheet" href="estilos.css">
<style>
  /* El login es una pantalla propia: centra el formulario y deja el
     fondo tranquilo, sin la barra ni el panel del punto de venta. */
  body{display:grid; place-items:center; overflow:auto; padding:24px; user-select:auto}
  .login{width:100%; max-width:400px}
  .login-cab{text-align:center; margin-bottom:22px}
  .login-cab .logo{width:56px; height:56px; flex:none; margin:0 auto 12px; font-size:27px; border-radius:15px}
  .login-cab h1{font-size:19px; margin-bottom:3px}
  .login-cab p{margin:0; color:var(--muted); font-size:13px}
  .login .card-cue{padding:20px}
  .login .campo{margin-bottom:13px}
  .login .btn{width:100%; justify-content:center; padding:11px}
  .clave{position:relative}
  .clave input{padding-right:42px}
  .ver{
    position:absolute; right:6px; bottom:7px; width:32px; height:32px;
    border:1px solid var(--line); background:var(--panel2); border-radius:8px;
    cursor:pointer; font-size:15px; line-height:1;
  }
  .pista{font-size:12px; color:var(--muted); margin:14px 0 0; text-align:center; line-height:1.5}
  .alerta{padding:10px 13px; border-radius:9px; font-size:13px; margin-bottom:14px}
  .alerta.mal{background:var(--badbg); color:var(--bad); border:1px solid var(--bad)}
  .alerta.aviso-w{background:var(--warnbg); color:var(--warn); border:1px solid var(--warn)}
  .roles{margin-top:16px; padding-top:15px; border-top:1px solid var(--line2); font-size:12.5px; color:var(--muted)}
  .roles b{color:var(--ink)}
</style>
</head>
<body>
<div class="login">

  <div class="login-cab">
    <div class="logo"><?= htmlspecialchars($iniciales, ENT_QUOTES) ?></div>
    <h1><?= htmlspecialchars($marca, ENT_QUOTES) ?></h1>
    <p><?= $modo === 'cambiar' ? 'Elegí una clave nueva para entrar' : 'Sistema de caja y ventas' ?></p>
  </div>

  <div class="card">
    <div class="card-cue">
      <?php if ($error !== ''): ?>
        <div class="alerta mal"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <?php if ($modo === 'cambiar'): ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="accion" value="cambiar">
          <div class="campo">
            <label>Clave actual</label>
            <input type="password" name="actual" required autofocus autocomplete="current-password">
          </div>
          <div class="campo">
            <label>Clave nueva</label>
            <input type="password" name="nueva" required minlength="4" autocomplete="new-password">
          </div>
          <div class="campo">
            <label>Repetí la clave nueva</label>
            <input type="password" name="repetir" required minlength="4" autocomplete="new-password">
          </div>
          <button class="btn pri" type="submit">🔑 Guardar y entrar</button>
        </form>

      <?php else: ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="accion" value="entrar">
          <div class="campo">
            <label>Usuario</label>
            <input type="text" name="usuario" required autofocus
                   autocapitalize="none" spellcheck="false" autocomplete="username">
          </div>
          <div class="campo clave">
            <label>Clave</label>
            <input type="password" name="clave" id="clave" required autocomplete="current-password">
            <button class="ver" type="button" id="ver" title="Mostrar la clave" tabindex="-1">👁</button>
          </div>
          <button class="btn pri" type="submit">Entrar</button>
        </form>

        <p class="pista">
          La primera vez entrá con <code>admin</code> / <code>admin</code>
          y el sistema te pide cambiar la clave.
        </p>
        <div class="roles">
          <b>Administrador:</b> carga productos, proveedores y precios, ajusta stock,
          ve e imprime todas las cajas y también puede vender.<br>
          <b>Vendedor:</b> abre y cierra su caja y vende. No toca el catálogo.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($modo === 'entrar'): ?>
<script>
  document.getElementById('ver').addEventListener('click', function () {
    var c = document.getElementById('clave');
    c.type = c.type === 'password' ? 'text' : 'password';
    c.focus();
  });
</script>
<?php endif; ?>
</body>
</html>
