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

namespace OCA\Activity\Command;

use OCA\Activity\LegacyLinkRewriter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Nach dem Umzug einer Datenbank: Die Spalte link übernommener Aktivitäten
 * enthält Host und Webroot der Altinstanz (FilesHooks speichert sie mit
 * linkToRouteAbsolute). Der RSS-Feed gibt sie je Eintrag aus, der Strom bei
 * Einträgen ohne Datei-Verweis im Betreff, die API an die Clients.
 */
class RewriteLegacyLinks extends Command {
	/** @var IDBConnection */
	private $connection;

	/** @var IConfig */
	private $config;

	/** @var int */
	private $batchSize;

	/**
	 * @param IDBConnection $connection
	 * @param IConfig $config
	 * @param int $batchSize Zeilen je Abfrage und Transaktion
	 */
	public function __construct(IDBConnection $connection, IConfig $config, int $batchSize = 1000) {
		parent::__construct();
		$this->connection = $connection;
		$this->config = $config;
		$this->batchSize = \max(1, $batchSize);
	}

	protected function configure() {
		$this->setName('activity:rewrite-legacy-links')
			->setDescription('Point the links of activities taken over from a previous instance to this instance')
			->addOption(
				'old-base-url',
				null,
				InputOption::VALUE_REQUIRED,
				'Base URL of the instance the database was moved from, e.g. https://cloud.example.com/owncloud'
			)
			->addOption(
				'new-base-url',
				null,
				InputOption::VALUE_REQUIRED,
				'Base URL of this instance (default: overwrite.cli.url)'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	public function execute(InputInterface $input, OutputInterface $output): int {
		$oldBaseUrl = $input->getOption('old-base-url');
		if (!\is_string($oldBaseUrl) || \trim($oldBaseUrl) === '') {
			$output->writeln('<error>--old-base-url is required</error>');
			return 1;
		}
		$newBaseUrl = $input->getOption('new-base-url');
		if (!\is_string($newBaseUrl)) {
			$newBaseUrl = (string)$this->config->getSystemValue('overwrite.cli.url', '');
		}
		// Aktivitäts-Links sind absolut, der RSS-Feed braucht sie so.
		if (!\preg_match('#^https?://[^/?\#]+#i', \trim($newBaseUrl))) {
			$output->writeln('<error>The new base URL must be an absolute http(s) URL: set overwrite.cli.url or pass --new-base-url</error>');
			return 1;
		}
		try {
			$rewriter = new LegacyLinkRewriter($oldBaseUrl, $newBaseUrl);
		} catch (\InvalidArgumentException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}

		$output->writeln(
			'Old base URL: ' . \rtrim(\trim($oldBaseUrl), '/')
			. ', new base URL: ' . \rtrim(\trim($newBaseUrl), '/')
		);
		$updated = $this->rewrite($rewriter);
		$output->writeln("$updated activities were updated");
		return 0;
	}

	/**
	 * Geht die Tabelle in Stapeln über den Primärschlüssel durch; die Spalte
	 * link hat keinen Index, ein LIKE hülfe also nicht. Geänderte Zeilen eines
	 * Stapels werden in einer Transaktion geschrieben.
	 */
	private function rewrite(LegacyLinkRewriter $rewriter): int {
		$update = $this->connection->getQueryBuilder();
		$update->update('activity')
			->set('link', $update->createParameter('link'))
			->where($update->expr()->eq('activity_id', $update->createParameter('id')));

		$updated = 0;
		$lastId = 0;
		do {
			$select = $this->connection->getQueryBuilder();
			$select->select(['activity_id', 'link'])
				->from('activity')
				->where($select->expr()->gt('activity_id', $select->createNamedParameter($lastId, IQueryBuilder::PARAM_INT)))
				->andWhere($select->expr()->isNotNull('link'))
				->orderBy('activity_id', 'ASC')
				->setMaxResults($this->batchSize);
			$result = $select->execute();
			$rows = $result->fetchAllAssociative();
			$result->free();

			$changes = [];
			foreach ($rows as $row) {
				$lastId = (int)$row['activity_id'];
				$newLink = $rewriter->rewrite((string)$row['link']);
				if ($newLink !== null) {
					$changes[$lastId] = $newLink;
				}
			}

			if ($changes !== []) {
				$this->connection->beginTransaction();
				try {
					foreach ($changes as $id => $link) {
						$update->setParameter('link', $link)
							->setParameter('id', $id, IQueryBuilder::PARAM_INT);
						$update->execute();
					}
					$this->connection->commit();
				} catch (\Throwable $e) {
					$this->connection->rollBack();
					throw $e;
				}
				$updated += \count($changes);
			}
		} while (\count($rows) === $this->batchSize);

		return $updated;
	}
}
