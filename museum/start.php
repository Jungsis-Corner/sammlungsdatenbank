<?php
declare(strict_types=1);

// Session-Cookie-Parameter wie in require_login.php/login.php
$domain = '.jungsi.de';
$path   = '/sammlung/';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $path,
    'domain'   => $domain,
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

// Wenn schon jemand "normal" eingeloggt ist (Admin/User), Museum-Modus nicht erzwingen:
if (!empty($_SESSION['logged_in']) && empty($_SESSION['is_guest'])) {
  header('Location: /sammlung/index.php');
  exit;
}

// Gast setzen (kompatibel zu deiner require_login.php)
$istNeueSitzung = empty($_SESSION['museum_mode']);

$_SESSION['is_guest']  = true;
$_SESSION['role']      = 'guest';
$_SESSION['logged_in'] = true;
$_SESSION['username']  = 'gast';

// Museum-Modus Flag
$_SESSION['museum_mode'] = true;

// Optional: Zeitpunkt (für Timeout/Auto-Reset)
$_SESSION['museum_started_at'] = time();

// Gast-Login protokollieren - nur beim ERSTEN Start dieser Sitzung,
// nicht bei jedem erneuten Öffnen der installierten PWA innerhalb
// derselben Sitzung.
if ($istNeueSitzung) {
    require_once __DIR__ . '/../config.php';
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn && !$conn->connect_errno) {
            if ($stmt = $conn->prepare("INSERT INTO Login_Log (Quelle) VALUES ('museum_pwa')")) {
                $stmt->execute();
                $stmt->close();
            }
            $conn->close();
        }
    } catch (\Throwable $e) {
        // bewusst ignorieren - darf den Museumszugang nie blockieren
    }
}

header('Location: /sammlung/index.php?museum=1');
exit;