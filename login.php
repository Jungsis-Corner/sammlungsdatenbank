<?php
// ==================== login.php ====================
// 1) Gemeinsame Session-Konfiguration (Cookie-Domain/-Pfad, session_start)
require_once __DIR__ . '/boot.php';

// 2) Zugangsdaten aus config.php laden (Admin gehasht, niemals im Code selbst)
require_once __DIR__ . '/config.php';

// 2b) Gast-Logins protokollieren (für die Statistik-Seite). Darf den
//     eigentlichen Login-Vorgang niemals blockieren, daher robust/still.
function log_guest_login(string $quelle): void {
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn || $conn->connect_errno) return;
        $stmt = $conn->prepare("INSERT INTO Login_Log (Quelle) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $quelle);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (\Throwable $e) {
        // bewusst ignorieren
    }
}

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

  log_guest_login('login_autologin');

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

    if ($role === 'guest') {
        log_guest_login('login_form');
    }

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
  <script src="/sammlung/assets/theme-toggle.js"></script>
  <style>
    html[data-theme="dark"]{
      background: #121212 !important;
    }
    html[data-theme="dark"] body{
      background: #121212 !important;
      color: #e8e8e8 !important;
    }
    html[data-theme="dark"] input,
    html[data-theme="dark"] button{
      background: #2a2a2a !important;
      color: #e8e8e8 !important;
      border: 1px solid #444 !important;
    }
    html[data-theme="dark"] a{
      color: #8ab4ff !important;
    }
    .theme-toggle-floating{
      position: fixed;
      bottom: 16px;
      right: 16px;
      z-index: 9999;
      border-radius: 50%;
      width: 44px;
      height: 44px;
      padding: 0;
      font-size: 20px;
      line-height: 42px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,.25);
      background: #555;
      color: #fff;
      border: 1px solid #333;
      cursor: pointer;
    }
  </style>
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
