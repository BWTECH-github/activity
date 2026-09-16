#!/bin/bash
# Belegt, dass ein gesperrtes Konto ueber sein RSS-Token nichts mehr liest.
#
# Aufbau wie in der Pruefung vom 16.09.2026: Token setzen, aktiv abrufen,
# Konto sperren, erneut abrufen. Vorher lieferten beide Abrufe dieselben
# Eintraege; jetzt muss der zweite "Your feed URL is invalid" sagen.
set -u

INSTANZ=/opt/oco-schnell
OCC="sudo -u www-data php $INSTANZ/occ"
KONTO=oco-probe-nutzer
TOKEN=PruefTokenSperre1234567890abcd   # genau 30 Zeichen
BASIS=http://127.0.0.1:18130

laenge() { printf '%s' "$1" | wc -c; }
echo "Tokenlaenge: $(laenge "$TOKEN") (muss 30 sein)"

$OCC user:setting --value="$TOKEN" "$KONTO" activity rsstoken >/dev/null
$OCC user:enable "$KONTO" >/dev/null 2>&1

abruf() {
	curl -s "$BASIS/index.php/apps/activity/rss.php?token=$TOKEN"
}

echo
echo "=== Konto aktiv ==="
A="$(abruf)"
echo "  Bytes:     $(printf '%s' "$A" | wc -c)"
echo "  Eintraege: $(printf '%s' "$A" | grep -c '<item>')"
echo "  Beschreibung: $(printf '%s' "$A" | grep -o '<description>[^<]*' | head -1)"

$OCC user:disable "$KONTO" >/dev/null
echo
echo "=== Konto gesperrt ==="
echo "  occ meldet: $($OCC user:list --attributes=enabled 2>/dev/null | grep -A1 "$KONTO" | tr -d '\n' | tr -s ' ')"
B="$(abruf)"
echo "  Bytes:     $(printf '%s' "$B" | wc -c)"
echo "  Eintraege: $(printf '%s' "$B" | grep -c '<item>')"
echo "  Beschreibung: $(printf '%s' "$B" | grep -o '<description>[^<]*' | head -1)"

echo
if printf '%s' "$B" | grep -q 'feed URL is invalid'; then
	echo "ERGEBNIS: bestanden - der Feed verweigert die Auskunft"
else
	echo "ERGEBNIS: DURCHGEFALLEN - der Feed liefert weiter Eintraege"
fi

# Aufraeumen: Konto wieder aktiv, Token weg.
$OCC user:enable "$KONTO" >/dev/null
$OCC user:setting --delete "$KONTO" activity rsstoken >/dev/null
echo "aufgeraeumt (Konto aktiv, Token entfernt)"
