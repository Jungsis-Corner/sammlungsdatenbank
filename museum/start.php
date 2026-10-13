<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc_errors.php';

// Gemeinsame Session-Konfiguration (Cookie-Domain/-Pfad, session_start)
require_once __DIR__ . '/../boot.php';

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