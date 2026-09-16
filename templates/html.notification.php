<?php
/**
 * Sammelmail der Aktivitäten.
 *
 * @var OCP\IL10N $l
 * @var array $_
 *
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 *
 * Modified by BW-Tech GmbH on 2026-09-16.
 * Changes:
 *   - use the shared owncloud.online mail frame instead of an own 2015 layout
 *   - escape the display name; the greeting printed it unescaped
 *
 * Die Mail trug bis hierher ihren eigenen Rahmen von 2015: eine
 * 600-Pixel-Tabelle, Verdana in 0,8em, farbige Kopfzelle - und das Logo über
 * eine absolute Adresse auf die Instanz, die außerhalb des Netzes niemand
 * erreicht. Sie war damit die letzte Mail der Instanz, die aus der Reihe fiel.
 * Rahmen, Schrift und Logo kommen jetzt aus dem Kern; hier steht nur der Inhalt.
 *
 * 'app' => 'core' ist Pflicht: inc() lädt das Blatt im Template-Objekt dieser
 * App, und ohne die Angabe sucht es die Bausteine unter
 * apps-external/activity/templates und bricht ab.
 */
$l = $_['overwriteL10N'];

print_unescaped($this->inc('html.mail.header', ['app' => 'core']));
?>
<p style="margin:0 0 16px;">
	<?php
	/*
	 * p() statt print_unescaped: der Anzeigename kommt roh aus
	 * MailQueueHandler (getDisplayName()). Vorher stand er unmaskiert in der
	 * Anrede - wer seinen Namen auf Markup setzt, bestimmte damit den Inhalt
	 * einer Mail, die der Server unter der Marke der Instanz verschickt, und
	 * ein Administrator kann den Namen auch für fremde Konten setzen.
	 */
	p($l->t('Hello %s,', [$_['username']]));
	?>
</p>

<p style="margin:0 0 20px;">
	<?php print_unescaped($l->t(
		'You are receiving this email because the following things happened at <a href="%s">%s</a>',
		[$_['owncloud_installation'], $theme->getName()]
	)); ?>
</p>

<?php
/*
 * Die Einträge tragen bereits fertiges Markup: MailQueueHandler schickt sie
 * durch den HTML-Parser der Aktivitäten (parseMessage), der die Verweise auf
 * Dateien und Konten selbst baut und die Fremddaten dabei entschärft. Deshalb
 * print_unescaped - p() würde hier die Verweise als Text anzeigen.
 *
 * Statt <ul><li> eine Tabelle: Zeitangabe und Ereignis stehen damit in zwei
 * Spalten, und die Zeit fällt als Nebenangabe zurück. Listenpunkte werden von
 * Mailprogrammen sehr unterschiedlich eingerückt.
 */
?>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;">
<?php foreach ($_['activities'] as $activityData) { ?>
	<tr>
		<td style="padding:0 0 12px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.55;color:#1f2733;">
			<?php print_unescaped($activityData[0]); ?><br>
			<span style="font-size:12px;color:#5b6675;"><?php p($activityData[1]); ?></span>
		</td>
	</tr>
<?php } ?>
<?php if ($_['skippedCount']) { ?>
	<tr>
		<td style="padding:0 0 12px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.55;color:#5b6675;">
			<?php p($l->n('and %n more ', 'and %n more ', $_['skippedCount'])); ?>
		</td>
	</tr>
<?php } ?>
</table>

<?php
print_unescaped($this->inc('html.mail.button', [
	'app' => 'core',
	'url' => $_['activity_link'],
	'label' => $l->t('Show all activities'),
	'hint' => $l->t('If the button does not work, open this address:'),
]));

print_unescaped($this->inc('html.mail.end', ['app' => 'core']));
