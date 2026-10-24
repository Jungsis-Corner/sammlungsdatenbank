#!/bin/bash
# Smoke-Test der Sammlungsdatenbank (T2). Nur gegen eine lokale Testkopie mit Test-config.php und Wegwerf-Datenbank
# (Anleitung: tests/README.md), nie gegen die Produktion mit echten Zugangsdaten.
#
#   tests/smoke.sh [Basis-URL]              voller Test (Standard http://127.0.0.1:8124/sammlung)
#   tests/smoke.sh --header <URL>           nur Header-Prüfung einer öffentlichen Seite (auch live: keine Anmeldung, nur HEAD)
#   tests/smoke.sh --geschuetzt <Basis-URL> nur die Prüfung, dass interne Dateien nicht ausgeliefert werden (braucht Apache, live möglich)
#
# Umgebung: ADMIN_USER (admin), ADMIN_PASS (geheim), TMPDIR für die Cookie-Dateien.
# Ausgabe: je Fehler eine Zeile "FEHLER …", am Ende die Summe; Exit-Code 1 bei Fehlern.
# Die Header- und Schutzprüfungen setzen Apache mit .htaccess voraus (der PHP-Entwicklungsserver ignoriert sie).

ADMIN_USER=${ADMIN_USER:-admin}
ADMIN_PASS=${ADMIN_PASS:-geheim}
TMP=${TMPDIR:-/tmp}/smoke.$$
mkdir -p "$TMP"
trap 'rm -rf "$TMP"' EXIT
fail=0; total=0

ok()  { total=$((total+1)); }
bad() { total=$((total+1)); fail=$((fail+1)); echo "  FEHLER $*"; }

# ---------- Header-Prüfung (nur Kopfzeilen) ----------
pruefe_header() {   # pruefe_header <URL>
  local h; h=$(curl -sI "$1" | tr -d '\r')
  [ -n "$h" ] || { bad "Header: keine Antwort von $1"; return; }
  local n
  for name in x-content-type-options x-frame-options referrer-policy permissions-policy strict-transport-security content-security-policy; do
    n=$(printf '%s\n' "$h" | grep -ci "^$name:")
    if [ "$n" = "1" ]; then ok; else bad "Header $name kommt ${n}-mal vor (erwartet: 1)"; fi
  done
  local csp; csp=$(printf '%s\n' "$h" | grep -i "^content-security-policy:" | head -1)
  case "$csp" in *unsafe-inline*|*unsafe-eval*) bad "CSP enthält unsafe-inline/unsafe-eval";; *) ok;; esac
  case "$csp" in *"script-src 'self'"*) ok;; *) bad "CSP: script-src 'self' fehlt";; esac
  printf '%s\n' "$h" | grep -qi "^content-security-policy-report-only:" && bad "CSP-Report-Only steht noch im Header" || ok
  printf '%s\n' "$h" | grep -qi "^strict-transport-security: max-age=[0-9]" && ok || bad "HSTS ohne max-age"
}

# ---------- Interne Dateien dürfen nicht ausgeliefert werden ----------
pruefe_geschuetzt() {   # pruefe_geschuetzt <Basis-URL>
  local p code
  for p in schema.sql config.php config.example.php CLAUDE.md migrations/README.md migrations/2026-10-13_06_geld.sql \
           cache/ .htaccess .gitignore php-error.log maintenance.flag var/ docs/uebergabe.md; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "$1/$p")
    case "$code" in 403|404) ok;; *) bad "$p liefert HTTP $code (erwartet 403/404)";; esac
  done
  code=$(curl -s -o /dev/null -w "%{http_code}" "$1/gibt-es-nicht-$$.php")
  [ "$code" = "404" ] && ok || bad "Unbekannte Seite liefert HTTP $code (erwartet 404)"
}

case "$1" in
  --header)    pruefe_header "${2:?URL fehlt}";    echo "Geprüft: $total, Fehler: $fail"; [ $fail = 0 ]; exit $?;;
  --geschuetzt) pruefe_geschuetzt "${2:?URL fehlt}"; echo "Geprüft: $total, Fehler: $fail"; [ $fail = 0 ]; exit $?;;
esac

B=${1:-http://127.0.0.1:8124/sammlung}
J="$TMP/jar"; G="$TMP/jar_gast"; O="$TMP/out.html"

# ---------- Anmeldung ----------
curl -s -c "$J" -b "$J" -o /dev/null "$B/login.php"
loc=$(curl -s -c "$J" -b "$J" -o /dev/null -w "%{redirect_url}" --data-urlencode "username=$ADMIN_USER" --data-urlencode "password=$ADMIN_PASS" "$B/login.php")
[ -n "$loc" ] && ok || { bad "Admin-Anmeldung schlägt fehl (kein Redirect)"; echo "Abbruch."; exit 1; }
curl -s -c "$G" -b "$G" -o /dev/null "$B/login.php"
loc=$(curl -s -c "$G" -b "$G" -o /dev/null -w "%{redirect_url}" --data-urlencode "username=gast" --data-urlencode "password=gast" "$B/login.php")
[ -n "$loc" ] && ok || bad "Gast-Anmeldung schlägt fehl"

# falsches Passwort darf nicht anmelden
loc=$(curl -s -o /dev/null -w "%{redirect_url}" --data-urlencode "username=$ADMIN_USER" --data-urlencode "password=falsch-$$" "$B/login.php")
[ -z "$loc" ] && ok || bad "Falsches Passwort wird weitergeleitet ($loc)"

# Redirect-Ziel darf nie extern sein
for ziel in '//evil.example' 'https://evil.example' '/\evil.example' 'javascript:alert(1)'; do
  loc=$(curl -s -o /dev/null -w "%{redirect_url}" --data-urlencode "username=gast" --data-urlencode "password=gast" --data-urlencode "to=$ziel" "$B/login.php")
  case "$loc" in *evil.example*|javascript:*) bad "Login-Weiterleitung to=$ziel landet bei $loc";; *) ok;; esac
done

# ---------- Seiten ----------
# Ermittelt zwei vorhandene IDs für Detail/Bearbeiten (die erste und eine mittlere der Liste)
IDS=$(curl -s -b "$J" "$B/index.php" | grep -o 'view.php?id=[0-9]*' | head -2 | sed 's/.*=//')
ID1=$(echo "$IDS" | sed -n 1p); ID2=$(echo "$IDS" | sed -n 2p)
[ -n "$ID1" ] && ok || { bad "Keine Einträge in der Liste gefunden"; ID1=1; ID2=2; }

# check <Jar> <Beschriftung> <URL> [erwarteter Status, Standard 200]
check() {
  local jar=$1 label=$2 url=$3 want=${4:-200} code
  code=$(curl -s -c "$jar" -b "$jar" -o "$O" -w "%{http_code}" "$url")
  if [ "$code" != "$want" ]; then bad "$label: HTTP $code (erwartet $want)"; return; fi
  ok
  [ "$want" = "200" ] || return
  # PHP-Meldungen im HTML
  if grep -qE "(Warning|Notice|Deprecated|Fatal error|Uncaught|Stack trace)[: ]" "$O"; then bad "$label: PHP-Meldung im HTML"; fi
  # CSP (S9): kein Inline-CSS/-JS, keine Inline-Handler
  if grep -qiE "<style[ >]" "$O"; then bad "$label: Inline-<style>"; fi
  if grep -qiE "<script([^>]*)>" "$O" && grep -iE "<script([^>]*)>" "$O" | grep -viq "src="; then
    # Skripte ohne src: nur tolerieren, wenn type kein JavaScript ist (z. B. JSON)
    if grep -iE "<script([^>]*)>" "$O" | grep -vi "src=" | grep -viq 'type="application/\(ld+\)\?json"'; then bad "$label: Inline-<script>"; fi
  fi
  if grep -qiE '<[a-z][^>]*[ "]style="' "$O"; then bad "$label: style=-Attribut"; fi
  if grep -qiE '<[a-z][^>]* on(click|change|submit|input|load|keyup|keydown|focus|blur|error)=' "$O"; then bad "$label: Inline-Handler (onclick …)"; fi
  # Seitengerüst (Barrierefreiheit, Phase 6): Titel, Sprunglink, main
  if [ "$3" != "$B/manifest.php" ] && grep -qi "<html" "$O"; then
    tr '\n' ' ' < "$O" | grep -qi '<title>[^<]*[^< ][^<]*</title>' || bad "$label: <title> fehlt oder leer"
    grep -qi 'id="inhalt"' "$O" || bad "$label: id=\"inhalt\" fehlt"
  fi
}

ADMIN_SEITEN=(
  "index.php" "index.php?page=2" "index.php?sort=Jahr&dir=desc" "index.php?pp=50" "index.php?q=a"
  "view.php?id=$ID1" "view.php?id=$ID2" "edit.php?id=$ID1" "edit.php"
  "einkauf.php" "einkauf_edit.php" "statistik.php" "export.php" "einstellungen.php" "bildpruefung.php"
  "manage_kategorie.php" "manage_hersteller.php" "manage_publisher.php" "manage_material.php" "manage_standort.php"
  "manage_box.php" "manage_verkaeufer.php" "manage_zustand.php" "manage_verpackung.php" "manage_datentraeger.php"
  "logview.php" "offline.php" "manifest.php"
)
for p in "${ADMIN_SEITEN[@]}"; do check "$J" "Admin $p" "$B/$p"; done

check "$J" "404.php" "$B/404.php" 404
check "$TMP/jar_leer" "Anmeldeseite" "$B/login.php"

# Seiten, die nur Admins sehen: Gast bekommt 403
for p in einstellungen.php bildpruefung.php manage_hersteller.php manage_kategorie.php einkauf_edit.php logview.php; do
  check "$G" "Gast $p" "$B/$p" 403
done
# Gast sieht Liste, Detail, Statistik
for p in "index.php" "view.php?id=$ID1" "statistik.php"; do check "$G" "Gast $p" "$B/$p"; done
# Gast: Export aus
code=$(curl -s -b "$G" -o /dev/null -w "%{http_code}" "$B/export.php?download=1")
case "$code" in 403|404|302) ok;; *) bad "Gast-Export liefert HTTP $code";; esac

# Ohne Anmeldung: Umleitung zur Anmeldung, nie Inhalt
for p in index.php "view.php?id=$ID1" edit.php einkauf.php statistik.php export.php einstellungen.php; do
  r=$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/$p")
  case "$r" in "302 "*login.php*) ok;; *) bad "Ohne Anmeldung $p: $r (erwartet Umleitung auf login.php)";; esac
done

# ---------- POST ohne CSRF-Token ----------
for ep in delete.php duplicate.php einkauf_delete.php edit.php; do
  for tok in "" "csrf=" "csrf=falsch"; do
    code=$(curl -s -b "$J" -o /dev/null -w "%{http_code}" -d "id=$ID1" ${tok:+-d "$tok"} "$B/$ep")
    case "$code" in 400|403|405|302) ok;; *) bad "POST $ep ohne gültiges Token (${tok:-kein Token}): HTTP $code";; esac
  done
  # GET darf nichts ändern
  code=$(curl -s -b "$J" -o /dev/null -w "%{http_code}" "$B/$ep?id=$ID1&delete=1")
  case "$code" in 200|302|400|403|404|405) ok;; *) bad "GET $ep: HTTP $code";; esac
done
# der Eintrag muss noch da sein
check "$J" "Eintrag $ID1 nach POST-Versuchen" "$B/view.php?id=$ID1"

# ---------- Filter-Seiten (Auszug, die vollständige Prüfung steht in tests/filter_check.sh) ----------
for p in "index.php?filter=spiel" "index.php?verkauf=1" "index.php?standort=1" "index.php?box=1" "index.php?material=1"; do
  check "$J" "Filter $p" "$B/$p"
done

echo "Geprüft: $total, Fehler: $fail"
[ $fail = 0 ]
