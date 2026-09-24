<?php

/**
 * ownCloud - Activity App
 *
 * @author Frank Karlitschek
 * @copyright 2013 Frank Karlitschek frank@owncloud.org
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/** @var $l OC_L10N */
/** @var $theme OC_Defaults */
/** @var $_ array */
script('activity', [
	'formatter',
	'script'
]);
style('activity', 'style');
?>

<?php $_['appNavigation']->printPage(); ?>

<div id="app-content">
	<div id="emptycontent" class="hidden">
		<div class="icon-activity"></div>
		<h2><?php p($l->t('No activity yet')); ?></h2>
		<p><?php p($l->t('This stream will show events like additions, changes & shares')); ?></p>
	</div>

	<?php
	/*
	 * Der Strom lädt beim Blättern nach, ohne dass die Seite wechselt. Ohne
	 * Auszeichnung erfährt eine Sprachausgabe davon nichts: sie liest weiter
	 * vor, was beim Laden der Seite da war, während unten dreißig neue Einträge
	 * erscheinen. role="feed" benennt den Bereich als fortlaufende Liste, die
	 * beiden Statuszeilen melden Laden und Ende.
	 */
	?>
	<div id="container" role="feed" aria-busy="false"
		aria-label="<?php p($l->t('Activities')); ?>"
		data-activity-filter="<?php p($_['filter']) ?>" data-avatars-enabled="<?php p($_['avatars']) ?>">
	</div>

	<div id="loading_activities" class="icon-loading" role="status">
		<span class="hidden-visually"><?php p($l->t('Loading activities…')); ?></span>
	</div>

	<div id="no_more_activities" class="hidden" role="status">
		<?php p($l->t('No more events to load')) ?>
	</div>
</div>
