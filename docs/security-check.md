# Sicherheits-Querprüfung (Phase 1, Aufgabe S8)

Stand: 13.10.2026, Branch `s8-querpruefung`. Geprüft wurden alle PHP-Dateien im Repo (ohne `config.php`, die
bewusst nicht gelesen wird) per Quelltextsuche und Lesen der betroffenen Stellen. Der Bericht listet, was
geprüft wurde, was gefunden und behoben wurde und was als Hinweis offen bleibt.

## 1. Prüfpunkte und Ergebnis

| Nr. | Prüfpunkt | Ergebnis |
| --- | --- | --- |
| P1 | `$_GET`/`$_POST` in `header('Location…')` | in Ordnung. Alle Fälle laufen über `safe_target()`/`safe_local_path()` (Login, Gast), `safe_back_url()` (einkauf, einkauf_delete), `http_build_query()` (edit, delete, duplicate, view) oder feste Werte (`go.php`, `inc_manage_lookup.php`: `$self` aus `$cfg`, `page` als int, `q` per `urlencode`) |
| P2 | Ausgabe von Benutzerdaten ohne `htmlspecialchars` | in Ordnung. Keine Roh-Ausgabe von `$_GET`/`$_POST`/`$_REQUEST`. Ungeschützte `<?= … ?>` geben nur int-Werte oder Datenbank-IDs aus (z. B. `$id`, `$selKat`, `$idH`). `http_build_query()` in `href` ist prozentkodiert und damit unkritisch |
| P3 | `->query()` mit Variablen | behoben (siehe B4). Übrig sind nur Aufrufe mit festen Ausdrücken oder Bezeichnern aus `lookup_ident()` |
| P4 | `delete.php`, `duplicate.php`, `einkauf_delete.php`: Admin, POST, CSRF | in Ordnung, alle drei prüfen `is_admin()` und `require_post_csrf()`. Gleiches gilt für `edit.php`, `einkauf.php` (Toggle), `einkauf_edit.php` und `inc_manage_lookup.php` |
| P5 | Seiten ohne Anmeldung | nur beabsichtigt: `login.php`, `logout.php`, `guest.php`, `go.php`, `404.php`, `offline.php`, `manifest.php`, `museum/*` (Gast-Einstieg). Alle anderen Seiten laden `require_login.php` |
| P6 | Dynamische Tabellen- und Spaltennamen | in Ordnung. Lookup-Tabellen laufen über `lookup_ident()`, die Toggle-Spalte in `einkauf.php` über eine feste Liste, `$lieferDateSql` ist ein fester SQL-Ausdruck im Code |
| P7 | CSV-Injection in `export.php` | behoben (B1) |
| P8 | Meldungen in der URL (`?msg=`, `?err=`) | behoben (B2) |
| P9 | `inc_errors.php` als Erstes in jeder Seite | behoben (B3) |
| P10 | Hilfsdateien per URL erreichbar | behoben (B5) |
| P11 | Altlast `museum/index.php` | behoben (B6) |

## 2. Behobene Befunde

| Nr. | Befund | Maßnahme |
| --- | --- | --- |
| B1 | `export.php` schrieb Zellen ungeprüft: Werte, die mit `=`, `+`, `-` oder `@` beginnen, konnten in Excel/LibreOffice als Formel laufen | `csv_safe_cell()` in `inc_security.php` stellt ein `'` voran. Hinweis: In Excel ist das Apostroph beim CSV-Import sichtbar |
| B2 | Meldungen standen in der URL: `einkauf_edit.php` (`&msg=Gespeichert.`), `inc_manage_lookup.php` (`?err=delete`), `delete.php`/`index.php` (`msg=deleted`) | Neuer Flash-Helfer `flash_set()`/`flash_pull()` in `inc_security.php`, Meldung einmalig über die Session |
| B3 | `inc_errors.php` fehlte in `login.php`, `logout.php`, `go.php`, `guest.php`, `einstellungen.php`, `logview.php`, `einkauf_delete.php`, `museum/start.php` | als erste Anweisung ergänzt |
| B4 | Abfragen mit eingesetzten IDs (`duplicate.php`, `edit.php` ×5, `einkauf_edit.php` ×2, `view.php` ×3). Alle IDs waren int-gecastet, also nicht ausnutzbar, aber entgegen der Regel „immer Prepared Statements“ | auf `db_select()` mit Platzhaltern umgestellt, gegen eine lokale MySQL 8.4 getestet (12 Fälle, davon ein Angriffsstring) |
| B5 | `inc_*.php`, `boot.php`, `require_login.php`, `config.example.php` waren per URL abrufbar (harmlos, aber unnötig) | `FilesMatch` in `.htaccess`; `include` aus PHP bleibt möglich |
| B6 | `museum/index.php` (Altbestand) setzte keine gültige Anmeldung (`logged_in`/`is_guest` fehlten), `museum/sw.js` rief die Datei beim Installieren auf und löste dabei eine Gast-Session samt Zählereintrag aus | `museum/index.php` leitet auf `museum/start.php?museum=1` weiter (die Adresse bleibt gültig), aus dem Precache entfernt, `CACHE_NAME` museum v3 |

## 3. Hinweise ohne Änderung (bewusst offen)

| Nr. | Hinweis | Einordnung |
| --- | --- | --- |
| H1 | CSP enthält `unsafe-inline` für Skripte und Styles | Aufgabe S9 (nach Phase 5, Inline-Code auslagern, Nonce) |
| H2 | Das Login-Formular hat kein CSRF-Token | gering: Login-CSRF bringt einem Angreifer keinen Zugriff. Die Drosselung (S3) begrenzt Versuche. Bei Bedarf in Phase 5 (U17) mit der Anmeldeseite lösen |
| H3 | `schema.sql` lässt sich in MySQL 8.4 nicht unverändert importieren: `ALTER TABLE … ADD FOREIGN KEY` für `Einkauf` steht vor dem Primärschlüssel von `Kategorie` (Fehler 6125) | Aufgabe D (Phase 2): Fremdschlüssel in einen eigenen Abschnitt am Ende verschieben. Betrifft nur Neuinstallationen und Forks |
| H4 | Die Gast-Zählerquelle `museum_link` wird nicht mehr erzeugt (`museum/index.php` leitet nur weiter), neue Gast-Starts erscheinen als `museum_pwa` | rein statistisch, Altdaten bleiben erhalten |
| H5 | `?msg` wird in `edit.php` (Zeilen 906, 1070) noch aus URL-Parametern entfernt | Rest von früher, ohne Wirkung. Kann bei Gelegenheit entfallen |
| H6 | Auf PHP-Antworten dupliziert der Server jeden `Header always set` (gemessen auf Strato); `.htaccess` entfernt die Kopien per `Header onsuccess unset` | siehe Kommentar in `.htaccess`; neue Header müssen dort mit einer `unset`-Zeile ergänzt werden |

## 4. Wiederholen

Quelltextsuchen, mit denen sich die Prüfung wiederholen lässt (Git Bash im Projektordner):

```
grep -nE "header\(\s*['\"]Location" *.php museum/*.php | grep -E "\\\$"
grep -n -- '->query(' *.php | grep -v 'query("[^$]*")' | grep -v "query('[^$]*')"
for f in *.php museum/*.php; do grep -q "inc_errors.php" "$f" || echo "$f"; done
grep -lE '\$_POST|REQUEST_METHOD' *.php | while read f; do grep -q require_post_csrf $f || echo $f; done
```

Erwartet: Die zweite Suche zeigt nur Abfragen mit Bezeichnern aus `lookup_ident()` (`edit.php`, `einkauf.php`,
`einkauf_edit.php`, `index.php`) und `statistik.php`/`logview.php` mit festen Texten; die dritte nur Dateien,
die `inc_errors.php` bewusst nicht selbst einbinden: reine Include-Dateien (`boot.php`, `inc_*.php`, `require_login.php`),
die `manage_*.php` (laden es über `inc_manage_lookup.php`), reine Weiterleitungen und statische Seiten (`museum/index.php`,
`museum/exit.php`, `museum/offline.php`, `offline.php`, `manifest.php`); die vierte nur `login.php` (Login selbst).
