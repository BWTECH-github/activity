<?php
/**
 * @copyright Copyright (c) 2026, BW-Tech GmbH
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

use OCA\Activity\LegacyLinkRewriter;

class LegacyLinkRewriterTest extends TestCase {
	public function rewriteData(): array {
		$old = 'https://cloud.example.com/owncloud';
		return [
			// Altinstanz unter /owncloud, neue Instanz an der Wurzel
			'relativer Link unter altem Webroot' => [$old, '', '/owncloud/index.php/f/12', '/index.php/f/12'],
			'Icon unter altem Webroot' => [$old, '', '/owncloud/core/img/actions/shared.svg', '/core/img/actions/shared.svg'],
			'Aktion unter altem Webroot' => [$old, '', '/owncloud/ocs/v1.php/apps/files_sharing/api/v1/shares/pending/7', '/ocs/v1.php/apps/files_sharing/api/v1/shares/pending/7'],
			'Anfrage bleibt erhalten' => [$old, '', '/owncloud/index.php/settings/personal?sectionid=customgroups&group=projekt-x', '/index.php/settings/personal?sectionid=customgroups&group=projekt-x'],
			'absolute URL des alten Hosts' => [$old, '', 'https://cloud.example.com/owncloud/index.php/f/12', '/index.php/f/12'],
			'alter Host über http und in Großbuchstaben' => [$old, '', 'http://Cloud.Example.com/owncloud/index.php/f/12#x', '/index.php/f/12#x'],
			'nur das alte Webroot' => [$old, '', '/owncloud', '/'],
			'Webroot direkt mit Anfrage' => [$old, '', '/owncloud?x=1', '/?x=1'],
			// Unberührt
			'Link der neuen Instanz' => [$old, '', '/index.php/apps/announcementcenter', null],
			'zweiter Lauf' => [$old, '', '/index.php/f/12', null],
			'fremder Host' => [$old, '', 'https://other.example.com/owncloud/index.php/f/12', null],
			'alter Host außerhalb des Webroots' => [$old, '', 'https://cloud.example.com/other/x', null],
			'nur Präfix, keine Pfadgrenze' => [$old, '', '/owncloud2/index.php', null],
			'leerer Link' => [$old, '', '', null],
			'protokollrelativer Link' => [$old, '', '//cloud.example.com/owncloud/x', null],
			'kein Pfad' => [$old, '', 'index.php/f/12', null],
			'mailto' => [$old, '', 'mailto:someone@example.com', null],
			// Nur Webroot angegeben: absolute URLs bleiben, weil der Host unbekannt ist
			'Webroot-Angabe, relativer Link' => ['/owncloud/', '', '/owncloud/index.php/f/1', '/index.php/f/1'],
			'Webroot-Angabe, absoluter Link' => ['/owncloud', '', 'https://cloud.example.com/owncloud/index.php/f/1', null],
			// Altinstanz an der Wurzel eines anderen Hosts
			'Wurzel: absoluter Link wird relativ' => ['https://old.example.com', '', 'https://old.example.com/index.php/f/1', '/index.php/f/1'],
			'Wurzel: Host ohne Pfad' => ['https://old.example.com/', '', 'https://old.example.com', '/'],
			'Wurzel: relativer Link bleibt' => ['https://old.example.com', '', '/index.php/f/1', null],
			// Neues Webroot unterhalb des alten: neue Links nie doppelt präfixen
			'Wurzel nach /cloud' => ['https://h.example.com', '/cloud', '/index.php/f/1', '/cloud/index.php/f/1'],
			'Wurzel nach /cloud, zweiter Lauf' => ['https://h.example.com', '/cloud', '/cloud/index.php/f/1', null],
			'Wurzel nach /cloud, absolute URL' => ['https://h.example.com', '/cloud', 'https://h.example.com/index.php/f/1', '/cloud/index.php/f/1'],
			'Wurzel nach /cloud, absolute URL der neuen Instanz' => ['https://h.example.com', '/cloud', 'https://h.example.com/cloud/index.php/f/1', null],
			'/oc nach /oc/neu' => ['/oc', '/oc/neu', '/oc/index.php', '/oc/neu/index.php'],
			'/oc nach /oc/neu, zweiter Lauf' => ['/oc', '/oc/neu', '/oc/neu/index.php', null],
			// Absolute Zielbasis
			'absolute Zielbasis' => [$old, 'https://kunde.owncloud.online', 'https://cloud.example.com/owncloud/index.php/apps/files/?dir=/Dokumente', 'https://kunde.owncloud.online/index.php/apps/files/?dir=/Dokumente'],
			'absolute Zielbasis, zweiter Lauf' => [$old, 'https://kunde.owncloud.online', 'https://kunde.owncloud.online/index.php/apps/files/?dir=/Dokumente', null],
			'gleicher Host, Webroot entfällt' => ['https://h.example.com/owncloud', 'https://h.example.com', 'https://h.example.com/owncloud/index.php/f/1', 'https://h.example.com/index.php/f/1'],
			'gleicher Host, Webroot entfällt, zweiter Lauf' => ['https://h.example.com/owncloud', 'https://h.example.com', 'https://h.example.com/index.php/f/1', null],
			'gleicher Host, Wurzel nach /cloud' => ['https://h.example.com', 'https://h.example.com/cloud/', 'https://h.example.com/index.php/f/1', 'https://h.example.com/cloud/index.php/f/1'],
			'gleicher Host, Wurzel nach /cloud, zweiter Lauf' => ['https://h.example.com', 'https://h.example.com/cloud/', 'https://h.example.com/cloud/index.php/f/1', null],
			'alles gleich' => ['https://h.example.com', 'https://h.example.com', 'https://h.example.com/index.php/f/1', null],
		];
	}

	/**
	 * @dataProvider rewriteData
	 */
	public function testRewrite(string $oldBaseUrl, string $newBase, string $link, ?string $expected): void {
		$rewriter = new LegacyLinkRewriter($oldBaseUrl, $newBase);
		$this->assertSame($expected, $rewriter->rewrite($link));
		if ($expected !== null) {
			$this->assertNull($rewriter->rewrite($expected), 'Ein zweiter Lauf muss ein No-op sein');
		}
	}

	public function invalidData(): array {
		return [
			'leer' => ['', ''],
			'nur Leerzeichen' => ['  ', ''],
			'ohne Schrägstrich' => ['owncloud', ''],
			'ohne Host' => ['https:///owncloud', ''],
			'protokollrelativ' => ['//cloud.example.com/owncloud', ''],
			'mit Anfrage' => ['https://cloud.example.com/owncloud?x=1', ''],
			'Ziel ohne Schrägstrich' => ['/owncloud', 'cloud'],
		];
	}

	/**
	 * @dataProvider invalidData
	 */
	public function testInvalidBase(string $oldBaseUrl, string $newBase): void {
		$this->expectException(\InvalidArgumentException::class);
		new LegacyLinkRewriter($oldBaseUrl, $newBase);
	}
}
