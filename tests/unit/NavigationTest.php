<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Activity\Tests\Unit;

use OCA\Activity\Navigation;
use OCA\Activity\Tests\Unit\Mock\Extension;

/**
 * Class NavigationTest
 *
 * @package OCA\Activity\Tests
 * @group DB
 */
class NavigationTest extends TestCase {
	public function getTemplateData() {
		return [
			['all'],
			['all', 'self'],
			['random'],
		];
	}

	/**
	 * @dataProvider getTemplateData
	 */
	public function testGetTemplate($constructorActive, $forceActive = null) {
		$activityLanguage = \OCP\Util::getL10N('activity', 'en');
		$activityManager = new \OC\Activity\Manager(
			$this->createMock('OCP\IRequest'),
			$this->createMock('OCP\IConfig'),
			$this->createMock('OCP\IUserSession')
		);
		$activityManager->registerExtension(function () use ($activityLanguage) {
			return new Extension($activityLanguage, $this->createMock('\OCP\IURLGenerator'));
		});
		$navigation = new Navigation(
			$activityLanguage,
			$activityManager,
			\OC::$server->getURLGenerator(),
			'test',
			$constructorActive
		);
		$output = $navigation->getTemplate($forceActive)->fetchPage();

		// Remove tabs and new lines
		$output = \str_replace(["\t", "\r", "\n"], '', $output);

		// Turn the chips into an array
		$navigationEntries = \explode('</button>', $output);

		$links = $navigation->getLinkList();

		// Check whether all top links are available
		foreach ($links['top'] as $link) {
			$found = false;
			foreach ($navigationEntries as $navigationEntry) {
				if (\strpos($navigationEntry, 'data-navigation="' . $link['id'] . '"') !== false) {
					$found = true;
					$this->assertStringContainsString($link['name'], $navigationEntry);
					if ($forceActive == $link['id'] || ($forceActive == null && $constructorActive == $link['id'])) {
						$this->assertStringContainsString('oco-chip--on', $navigationEntry);
						$this->assertStringContainsString('aria-pressed="true"', $navigationEntry);
					} else {
						$this->assertStringNotContainsString('oco-chip--on', $navigationEntry);
						$this->assertStringContainsString('aria-pressed="false"', $navigationEntry);
					}
				}
			}
			$this->assertTrue($found, 'Could not find navigation entry "' . $link['name'] . '"');
		}

		// Check size of app links
		$this->assertSame(1, \sizeof($links['apps']));
		$this->assertStringNotContainsString('data-navigation="files"', $output, 'Files app should not be included when there are no other apps.');
	}
}
