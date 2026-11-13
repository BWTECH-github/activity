<?php
/* Copyright (c) 2014, Joas Schilling nickvergessen@owncloud.com
 * This file is licensed under the Affero General Public License version 3
 * or later. See the COPYING-README file. */

/** @var $l OC_L10N */
/** @var $_ array */
?>
<nav class="activity-filters" aria-label="<?php p($l->t('Filter')); ?>">
	<?php foreach ($_['navigations'] as $navigationEntries) {
		foreach ($navigationEntries as $navigation) {
			$active = $_['activeNavigation'] === $navigation['id']; ?>
	<button type="button" class="oco-chip oco-chip--filter<?php if ($active): ?> oco-chip--on<?php endif; ?>"
		data-navigation="<?php p($navigation['id']) ?>" aria-pressed="<?php p($active ? 'true' : 'false') ?>">
		<?php p($navigation['name']) ?>
	</button>
	<?php
		}
	} ?>
</nav>
