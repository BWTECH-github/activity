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

namespace OCA\Activity\Tests\Command;

use OCA\Activity\Command\RewriteLegacyLinks;
use OCA\Activity\Tests\Unit\TestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Altbestand einer Instanz unter https://alt.example.com/owncloud, so in der
 * Datenbank, wie eine 10.x-Altinstanz ihn schrieb.
 *
 * @group DB
 */
class RewriteLegacyLinksTest extends TestCase {
	private const TYPE = 'test_legacy_links';

	/** @var IDBConnection */
	private $connection;

	/** @var IConfig | \PHPUnit\Framework\MockObject\MockObject */
	private $config;

	protected function setUp(): void {
		parent::setUp();
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(IConfig::class);
		$this->deleteRows();
	}

	protected function tearDown(): void {
		$this->deleteRows();
		parent::tearDown();
	}

	private function deleteRows(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('activity')
			->where($qb->expr()->eq('type', $qb->createNamedParameter(self::TYPE)))
			->execute();
	}

	private function insert(string $link): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('activity')->values([
			'timestamp' => $qb->createNamedParameter(\time(), IQueryBuilder::PARAM_INT),
			'priority' => $qb->createNamedParameter(30, IQueryBuilder::PARAM_INT),
			'type' => $qb->createNamedParameter(self::TYPE),
			'user' => $qb->createNamedParameter('alice'),
			'affecteduser' => $qb->createNamedParameter('alice'),
			'app' => $qb->createNamedParameter('files'),
			'subject' => $qb->createNamedParameter('created_self'),
			'subjectparams' => $qb->createNamedParameter('[]'),
			'message' => $qb->createNamedParameter(''),
			'messageparams' => $qb->createNamedParameter('[]'),
			'file' => $qb->createNamedParameter('/Dokumente/Bericht.txt'),
			'link' => $qb->createNamedParameter($link),
		])->execute();
		return (int)$this->connection->lastInsertId('*PREFIX*activity');
	}

	private function link(int $id): string {
		$qb = $this->connection->getQueryBuilder();
		return (string)$qb->select('link')->from('activity')
			->where($qb->expr()->eq('activity_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->execute()->fetchOne();
	}

	public function testRewritesOnlyLinksOfTheOldInstance(): void {
		$this->config->method('getSystemValue')
			->with('overwrite.cli.url', '')
			->willReturn('https://kunde.owncloud.online/');

		$old1 = $this->insert('https://alt.example.com/owncloud/index.php/apps/files/?dir=/Dokumente');
		$old2 = $this->insert('http://ALT.example.com/owncloud/index.php/apps/files/?dir=/');
		$old3 = $this->insert('/owncloud/index.php/apps/files/?dir=/Projekt');
		$foreign = $this->insert('https://fremd.example.com/owncloud/index.php/apps/files/?dir=/');
		$current = $this->insert('https://kunde.owncloud.online/index.php/apps/files/?dir=/Neu');
		$empty = $this->insert('');

		// Stapelgröße 2: der Lauf muss über mehrere Stapel hinweg alles finden
		$tester = new CommandTester(new RewriteLegacyLinks($this->connection, $this->config, 2));
		$this->assertSame(0, $tester->execute(['--old-base-url' => 'https://alt.example.com/owncloud/']));
		$this->assertStringContainsString('3 activities were updated', $tester->getDisplay());

		$this->assertSame('https://kunde.owncloud.online/index.php/apps/files/?dir=/Dokumente', $this->link($old1));
		$this->assertSame('https://kunde.owncloud.online/index.php/apps/files/?dir=/', $this->link($old2));
		$this->assertSame('https://kunde.owncloud.online/index.php/apps/files/?dir=/Projekt', $this->link($old3));
		$this->assertSame('https://fremd.example.com/owncloud/index.php/apps/files/?dir=/', $this->link($foreign));
		$this->assertSame('https://kunde.owncloud.online/index.php/apps/files/?dir=/Neu', $this->link($current));
		$this->assertSame('', $this->link($empty));

		// Zweiter Lauf ist ein No-op
		$this->assertSame(0, $tester->execute(['--old-base-url' => 'https://alt.example.com/owncloud']));
		$this->assertStringContainsString('0 activities were updated', $tester->getDisplay());
	}

	public function testExplicitNewBaseUrl(): void {
		$this->config->expects($this->never())->method('getSystemValue');
		$id = $this->insert('https://alt.example.com/owncloud/index.php/apps/files/?dir=/');

		$tester = new CommandTester(new RewriteLegacyLinks($this->connection, $this->config));
		$this->assertSame(0, $tester->execute([
			'--old-base-url' => 'https://alt.example.com/owncloud',
			'--new-base-url' => 'https://neu.example.com/cloud',
		]));
		$this->assertSame('https://neu.example.com/cloud/index.php/apps/files/?dir=/', $this->link($id));
	}

	public function invalidInput(): array {
		return [
			'ohne alte Basis' => [[], 'https://kunde.owncloud.online'],
			'alte Basis ungültig' => [['--old-base-url' => 'owncloud'], 'https://kunde.owncloud.online'],
			'overwrite.cli.url fehlt' => [['--old-base-url' => 'https://alt.example.com'], ''],
			'neue Basis relativ' => [['--old-base-url' => 'https://alt.example.com', '--new-base-url' => '/cloud'], 'https://kunde.owncloud.online'],
		];
	}

	/**
	 * @dataProvider invalidInput
	 */
	public function testInvalidInputChangesNothing(array $input, string $cliUrl): void {
		$this->config->method('getSystemValue')->willReturn($cliUrl);
		$id = $this->insert('https://alt.example.com/index.php/apps/files/?dir=/');

		$tester = new CommandTester(new RewriteLegacyLinks($this->connection, $this->config));
		$this->assertSame(1, $tester->execute($input));
		$this->assertSame('https://alt.example.com/index.php/apps/files/?dir=/', $this->link($id));
	}
}
