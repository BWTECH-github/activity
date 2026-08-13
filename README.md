# Aktivitäten

Sammelt alle Ereignisse rund um Dateien und Ordner an einer Stelle und schickt
auf Wunsch eine Zusammenfassung per E-Mail.

## Was erfasst wird

Anlegen, Ändern, Umbenennen, Löschen und Wiederherstellen von Dateien und
Ordnern, Freigaben (intern wie per Link), Kommentare, Schlagworte und Zugriffe
über öffentliche Links.

Der Strom ist über das App-Menü erreichbar. Wer viel Betrieb hat, kann ihn auf
*Favoriten* einschränken oder die Filter nutzen, um nur eine Art von Ereignis zu
sehen.

## Voraussetzungen

* owncloud.online 11.x
* PHP 8.4
* ein laufender Cron — ohne ihn werden keine E-Mails verschickt und alte
  Einträge nicht abgeräumt
* eingerichteter E-Mail-Versand, wenn die Benachrichtigungen genutzt werden

## Installation

Über den Market, oder von Hand:

```bash
cd /var/www/owncloud.online/apps
git clone https://github.com/BWTECH-github/activity.git
chown -R www-data:www-data activity
sudo -u www-data php8.4 ../occ app:enable activity
```

## Einstellungen

Jedes Konto stellt unter *Persönliche Einstellungen → Aktivitäten* selbst ein,
welche Ereignisse im Strom auftauchen und für welche eine E-Mail kommen soll.
Der Versand geht stündlich, täglich oder wöchentlich — je nachdem, wie oft man
gestört werden möchte.

## Hintergrundaufträge

| Auftrag | Aufgabe |
| --- | --- |
| `OCA\Activity\BackgroundJob\EmailNotification` | verschickt die gesammelten E-Mails |
| `OCA\Activity\BackgroundJob\ExpireActivities` | räumt alte Einträge ab |

Beide laufen über den normalen Cron.

## Kommandozeile

```bash
# den Mailversand sofort anstoßen, statt auf den Cron zu warten
sudo -u www-data php8.4 occ activity:send-emails
```

## Eigene Ereignisse beisteuern

Andere Apps können den Aktivitätsstrom erweitern. Dazu implementiert man
`\OCP\Activity\IExtension` und registriert die Klasse beim Activity-Manager. Die
Kommentare an den einzelnen Methoden der Schnittstelle beschreiben, was jede
zurückgeben muss.

## Fehlersuche

| Symptom | Ursache | Abhilfe |
| --- | --- | --- |
| Keine E-Mails | Cron läuft nicht, oder der Mailversand ist nicht eingerichtet | `occ background:cron` und die Mail-Einstellungen prüfen |
| Strom bleibt leer | App war beim Ereignis noch nicht aktiv — rückwirkend wird nichts erfasst | abwarten, ab jetzt wird gesammelt |
| Tabelle wächst unbegrenzt | Aufräumauftrag läuft nicht | Cron prüfen |

## Herkunft

Fork der gleichnamigen ownCloud-App, gepflegt von der BW-Tech GmbH für
owncloud.online und PHP 8.4. Lizenz: AGPLv3.
