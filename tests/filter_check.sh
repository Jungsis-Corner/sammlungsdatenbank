#!/bin/bash
# T4 Filter-Gleichheit: Für feste Filter-URLs müssen die Trefferzahl der Liste (index.php), die Anzahl im Export
# (export.php) und eine unabhängig geschriebene SQL-Zählung übereinstimmen. Nur lokal gegen die Testkopie.
#
#   tests/filter_check.sh [Basis-URL]
# Umgebung: ADMIN_USER, ADMIN_PASS, MYSQL (Aufruf des Clients, Standard: mysql -uroot -h127.0.0.1 -P3399 -N d9t).
# Die IDs in der Tabelle unten (Hersteller 280, Standort 1, Box 9, Marke 32, Kategorie 57 …) gelten für den Dump vom 13.10.2026;
# bei einer anderen Datenbank anpassen.

ADMIN_USER=${ADMIN_USER:-admin}; ADMIN_PASS=${ADMIN_PASS:-geheim}
B=${1:-http://127.0.0.1:8124/sammlung}
SQL=${MYSQL:-mysql -uroot -h127.0.0.1 -P3399 -N d9t}
J=$(mktemp); trap 'rm -f "$J"' EXIT
curl -s -c "$J" -b "$J" -o /dev/null "$B/login.php"
curl -s -c "$J" -b "$J" -o /dev/null --data-urlencode "username=$ADMIN_USER" --data-urlencode "password=$ADMIN_PASS" "$B/login.php"

fail=0; n=0
# Zeile: Name | Query-String | WHERE-Teil der Kontrollzählung (S = Sammlung, K = Kategorie, M = Material)
FROM="FROM Sammlung S LEFT JOIN Kategorie K ON K.ID=S.Kategorie LEFT JOIN Material M ON M.ID=S.Material"
while IFS='|' read -r name qs where; do
  [ -z "$name" ] && continue
  n=$((n+1))
  soll=$($SQL -e "SELECT COUNT(*) $FROM ${where:+WHERE $where}")
  html=$(curl -s -b "$J" "$B/index.php?$qs")
  liste=$(printf '%s' "$html" | grep -o ', [0-9.]* Einträge\?</caption>' | head -1 | sed 's/[^0-9]//g')
  [ -z "$liste" ] && printf '%s' "$html" | grep -q "Keine Treffer" && liste=0
  export_=$(curl -s -b "$J" "$B/export.php?$qs" | grep -o "<strong>[0-9.]*</strong> Eintr" | head -1 | sed 's/<[^>]*>//g;s/[^0-9]//g')
  if [ "$soll" = "$liste" ] && [ "$soll" = "$export_" ]; then
    printf "  OK      %-34s %6s\n" "$name" "$soll"
  else
    fail=$((fail+1)); printf "  FEHLER  %-34s SQL %s, Liste %s, Export %s   (%s)\n" "$name" "$soll" "${liste:-?}" "${export_:-?}" "$qs"
  fi
done <<'LISTE'
ohne Filter||
Spiele|filter=spiel|K.Ebene='spiele'
Spiele Original|filter=spiel_original|K.Ebene='spiele' AND S.`Original/Homebrew`='Original'
Spiele Homebrew|filter=spiel_homebrew|K.Ebene='spiele' AND S.`Original/Homebrew`='Homebrew'
Kategorie exakt (57)|filter=57|S.Kategorie=57
Kategorie 57 mit Unterkategorien|kat=57&inkl=1|K.Pfad LIKE CONCAT((SELECT Pfad FROM Kategorie WHERE ID=57),'%')
Marke 32 mit Unterkategorien|kat=32&inkl=1|K.Pfad LIKE CONCAT((SELECT Pfad FROM Kategorie WHERE ID=32),'%')
Marke 36 mit Unterkategorien|kat=36&inkl=1|K.Pfad LIKE CONCAT((SELECT Pfad FROM Kategorie WHERE ID=36),'%')
Kategorie 57 ohne Unterkategorien|kat=57|S.Kategorie=57
Hersteller 280|hersteller=280|S.Hersteller=280
Standort 1|standort=1|S.Standort=1
Box 9|box=9|S.Box=9
Zum Verkauf|verkauf=1|S.`Zum Verkauf`=1
Suche "Atari"|q=Atari|(S.Bezeichnung LIKE '%Atari%' OR S.Barcode LIKE '%Atari%' OR S.Beschreibung LIKE '%Atari%')
Suche mit Prozentzeichen|q=100%25|(S.Bezeichnung LIKE '%100\%%' OR S.Barcode LIKE '%100\%%' OR S.Beschreibung LIKE '%100\%%')
Material "Software"|material=Software|M.Material LIKE 'Software%'
Material "Literatur"|material=Literatur|M.Material LIKE 'Literatur%'
Original|oh=Original|S.`Original/Homebrew`='Original'
Einkaufsdatum 2026-09-13|einkaufsdatum=2026-09-13|S.Einkaufsdatum='2026-09-13'
Spiele + Standort 1|filter=spiel&standort=1|K.Ebene='spiele' AND S.Standort=1
Spiele + Box 8 + Original|filter=spiel&box=8&oh=Original|K.Ebene='spiele' AND S.Box=8 AND S.`Original/Homebrew`='Original'
Kategorie 57 inkl. + Suche|kat=57&inkl=1&q=a|K.Pfad LIKE CONCAT((SELECT Pfad FROM Kategorie WHERE ID=57),'%') AND (S.Bezeichnung LIKE '%a%' OR S.Barcode LIKE '%a%' OR S.Beschreibung LIKE '%a%')
unbekannter Hersteller|hersteller=99999999|S.Hersteller=99999999
LISTE

echo "Geprüft: $n Filter, Abweichungen: $fail"
[ $fail = 0 ]
