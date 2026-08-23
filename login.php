<?php
// ==================== login.php ====================
// 1) Gemeinsame Session-Konfiguration (Cookie-Domain/-Pfad, session_start)
require_once __DIR__ . '/boot.php';

// 2) Zugangsdaten aus config.php laden (Admin gehasht, niemals im Code selbst)
require_once __DIR__ . '/config.php';

// 3) Zielberechnung: nur interne absolute Pfade, niemals login.php
function safe_target(?string $to): string {
  $target = $to ?: '/sammlung/index.php';
  // Nie auf login.php zurücklenken (Loop-Schutz)
  if (stripos($target, '/sammlung/login.php') !== false) {
    $target = '/sammlung/index.php';
  }
  // Nur interne Ziele erlauben
  if (strpos($target, '://') !== false) {
    $target = '/sammlung/index.php';
  }
  // Absolut machen
  if ($target[0] !== '/') {
    $target = '/sammlung/' . ltrim($target, '/');
  }
  return $target;
}

// 4) Bereits eingeloggt? -> direkt weiter (Loop-Schutz)
if (
  (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ||
  (!empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) ||   // alt-kompatibel
  !empty($_SESSION['is_guest'])
) {
  header('Location: ' . safe_target($_GET['to'] ?? null));
  exit;
}

// 5) Gast-Autologin per GET
if (isset($_GET['autologin']) && $_GET['autologin'] === 'gast') {
  session_regenerate_id(true);
  // neu + alt-kompatible Flags setzen
  $_SESSION['logged_in'] = true;
  $_SESSION['loggedin']  = true;         // alt
  $_SESSION['user_id']   = 0;
  $_SESSION['username']  = 'Gast';
  $_SESSION['role']      = 'guest';
  $_SESSION['is_guest']  = true;
  $_SESSION['login_method'] = 'guest';

  header('Location: ' . safe_target($_GET['to'] ?? null));
  exit;
}

// 6) Formular-Login (POST)
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = $_POST['username'] ?? '';
  $p = $_POST['password'] ?? '';

  $loginOk = false;
  $role = null;

  if ($u === ADMIN_USERNAME && password_verify($p, ADMIN_PASSWORD_HASH)) {
      $loginOk = true;
      $role = 'admin';
  } elseif ($u === 'gast' && $p === 'gast') {
      // Gast-Zugang bewusst einfach gehalten (nur Lesezugriff, kein Risiko)
      $loginOk = true;
      $role = 'guest';
  }

  if ($loginOk) {
    session_regenerate_id(true);
    // neu + alt-kompatible Flags setzen
    $_SESSION['logged_in'] = true;
    $_SESSION['loggedin']  = true;        // alt
    $_SESSION['user_id']   = ($role === 'admin') ? 1 : 0;
    $_SESSION['username']  = $u;
    $_SESSION['role']      = $role;
    $_SESSION['is_guest']  = ($role !== 'admin');
    $_SESSION['login_method'] = 'password';

    header('Location: ' . safe_target($_POST['to'] ?? null));
    exit;
  } else {
    $error = 'Ungültige Zugangsdaten.';
  }
}

// 7) Ziel für das Formular übernehmen
$to = safe_target($_GET['to'] ?? null);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family:system-ui,Arial,sans-serif; max-width:520px; margin:5vh auto; padding:0 16px;">
  <h1>Login</h1>

  <?php if ($error): ?>
    <p style="color:#b00"><?=htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE)?></p>
  <?php endif; ?>

  <form method="post" action="login.php" style="display:grid; gap:8px;">
    <input type="hidden" name="to" value="<?=htmlspecialchars($to, ENT_QUOTES | ENT_SUBSTITUTE)?>">
    <label>Benutzer<br><input name="username" required autofocus></label>
    <label>Passwort<br><input type="password" name="password" required></label>
    <button type="submit">Anmelden</button>
  </form>

  <p style="margin-top:1rem">
    Oder <a href="login.php?autologin=gast&to=<?=rawurlencode($to)?>">als Gast fortfahren</a>
  </p>
</body>
</html>
