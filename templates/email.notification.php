<?php
/**
 * Textfassung der Sammelmail; folgt html.notification.php Satz für Satz.
 *
 * @var \OCP\IL10N $l
 * @var array $_
 *
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 *
 * Modified by BW-Tech GmbH on 2026-09-16.
 * Changes:
 *   - follow the HTML mail: same order, link to the activity stream at the end
 */
$l = $_['overwriteL10N'];

print_unescaped($l->t('Hello %s,', [$_['username']]));
p("\n");
p("\n");

print_unescaped($l->t('You are receiving this email because the following things happened at %s', [$_['owncloud_installation']]));
p("\n");
p("\n");

foreach ($_['activities'] as $activityData) {
	print_unescaped($l->t('* %1$s - %2$s', $activityData));
	p("\n");
}
if ($_['skippedCount']) {
	print_unescaped($l->n('* and %n more ', '* and %n more ', $_['skippedCount']));
	p("\n");
}
p("\n");

// Derselbe Weg wie die Schaltfläche der HTML-Fassung.
print_unescaped($l->t('Show all activities:'));
p("\n");
p($_['activity_link']);
p("\n");
p("\n");

print_unescaped($this->inc('plain.mail.footer', ['app' => 'core']));
