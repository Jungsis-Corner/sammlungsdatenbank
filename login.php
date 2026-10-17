<?php
// ==================== login.php ====================
require_once __DIR__ . '/inc_errors.php';
// 1) Gemeinsame Session-Konfiguration (Cookie-Domain/-Pfad, session_start)
require_once __DIR__ . '/boot.php';

// 2) Zugangsdaten aus config.php laden (Admin gehasht, niemals im Code selbst)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc_security.php';
require_once __DIR__ . '/inc_login_throttle.php';

// 2b) Gast-Session und Gast-Zähler liegen zentral in inc_guest.php (start_guest_session()).
require_once __DIR__ . '/inc_guest.php';
require_once __DIR__ . '/inc_icons.php';

// 3) Zielberechnung: nur interne absolute Pfade, niemals login.php
function safe_target(?string $to): string {
  $fallback = '/sammlung/index.php';
  $target = (string)$to;
  if ($target === '') {
    return $fallback;
  }
  // Relative Eingaben (z. B. "view.php?id=5") absolut machen
  if ($target[0] !== '/') {
    $target = '/sammlung/' . ltrim($target, '/');
  }
  // Nie auf login.php zurücklenken (Loop-Schutz)
  if (stripos($target, '/sammlung/login.php') !== false) {
    return $fallback;
  }
  return safe_local_path($target, $fallback);
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
  start_guest_session('login_autologin');

  header('Location: ' . safe_target($_GET['to'] ?? null));
  exit;
}

// 6) Formular-Login (POST)
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = (string)($_POST['username'] ?? '');
  $p = (string)($_POST['password'] ?? '');

  $loginOk = false;
  $role = null;

  // Gast-Zugang bewusst einfach gehalten (nur Lesezugriff, kein Risiko, öffentlich dokumentiert).
  // Er wird vor der Drosselung geprüft: nie gesperrt, nie gezählt.
  $isGuestLogin = ($u === 'gast' && $p === 'gast');

  $blocked = false;
  $throttleDb = null;
  $ipHash = $userHash = '';
  if (!$isGuestLogin) {
      $throttleDb = login_throttle_open();
      $ipHash   = login_ip_hash();
      $userHash = login_user_hash($u);
      login_throttle_cleanup($throttleDb);
      $blocked = login_is_blocked($throttleDb, $ipHash, $userHash);
  }

  if ($isGuestLogin) {
      $loginOk = true;
      $role = 'guest';
  } elseif (!$blocked && $u === ADMIN_USERNAME && password_verify($p, ADMIN_PASSWORD_HASH)) {
      $loginOk = true;
      $role = 'admin';
  }

  if ($blocked) {
    // Gesperrte Versuche werden nicht mitgezählt und das Passwort wird nicht geprüft
    $error = 'Zu viele Anmeldeversuche. Bitte später erneut versuchen.';
  } elseif ($loginOk) {
    if ($role === 'admin') {
        login_reset($throttleDb, $ipHash, $userHash);
        login_record($throttleDb, $ipHash, $userHash, true);
    }
    if ($role === 'guest') {
        start_guest_session('login_form');
    } else {
        session_regenerate_id(true);
        // neu + alt-kompatible Flags setzen
        $_SESSION['logged_in'] = true;
        $_SESSION['loggedin']  = true;        // alt
        $_SESSION['user_id']   = 1;
        $_SESSION['username']  = $u;
        $_SESSION['role']      = 'admin';
        $_SESSION['is_guest']  = false;
        $_SESSION['login_method'] = 'password';
    }

    header('Location: ' . safe_target($_POST['to'] ?? null));
    exit;
  } else {
    login_record($throttleDb, $ipHash, $userHash, false);
    usleep(LOGIN_DELAY_US);
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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Anmeldung – Sammlung</title>
  <script src="/sammlung/assets/theme-toggle.js?v=5"></script>
  <script src="/sammlung/assets/login.js?v=1" defer></script>
  <link rel="stylesheet" href="/sammlung/assets/seite.css?v=1">
  <link rel="stylesheet" href="/sammlung/assets/base.css?v=3">
</head>
<body class="seite">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
<main id="inhalt">
  <div class="karte">
    <h1>Anmeldung</h1>

    <?php if ($error): ?>
      <p class="fehler" role="alert" id="loginFehler"><?=htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE)?></p>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="hidden" name="to" value="<?=htmlspecialchars($to, ENT_QUOTES | ENT_SUBSTITUTE)?>">
      <div>
        <label for="f_username">Benutzer</label>
        <input type="text" id="f_username" name="username" required autofocus autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false"<?= $error ? ' aria-describedby="loginFehler"' : '' ?>>
      </div>
      <div>
        <label for="f_password">Passwort</label>
        <div class="passwort-zeile">
          <input type="password" id="f_password" name="password" required autocomplete="current-password"<?= $error ? ' aria-describedby="loginFehler"' : '' ?>>
          <button type="button" id="pwZeigen" class="btn" hidden aria-pressed="false" aria-label="Passwort anzeigen"><svg class="ico ico-solo" aria-hidden="true" focusable="false"><use href="#i-eye"/></svg></button>
        </div>
      </div>
      <button type="submit" class="btn save">Anmelden</button>
    </form>

    <p class="gast">
      Nur ansehen? <a href="login.php?autologin=gast&amp;to=<?=rawurlencode($to)?>">Als Gast fortfahren</a>
    </p>
  </div>
</main>
</body>
</html>
