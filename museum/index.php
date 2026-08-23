<?php
session_start();

// Option B (nur wenn für dich ok):
// Gast-Session setzen, falls noch niemand eingeloggt ist
if (empty($_SESSION['user'])) {
  $_SESSION['user'] = 'gast';
  $_SESSION['role'] = 'guest';
}

header('Location: /sammlung/index.php');
exit;