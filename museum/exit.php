<?php
// museum/exit.php – beendet den Museum-Modus ("Museum beenden" in index.php).
// Die Gast-Session wird über logout.php sauber beendet (Session zerstört, Cookie gelöscht),
// danach folgt die normale Anmeldung.
header('Location: /sammlung/logout.php');
exit;
