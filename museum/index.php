<?php
// museum/index.php – alter Kurzlink. Der Museum-Modus startet über museum/start.php
// (Gast-Session mit Museum-Flag und Zähler). Die Adresse bleibt gültig und leitet nur weiter.
header('Location: /sammlung/museum/start.php?museum=1');
exit;
