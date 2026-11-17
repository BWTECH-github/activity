<?php

namespace OCA\activity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use OC\DB\MDB2SchemaReader;
use OCP\Migration\ISchemaMigration;

class Version20161122085340 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if ($schema->hasTable("{$prefix}activity")) {
			return;
		}

		// not that valid ....
		$schemaReader = new MDB2SchemaReader(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection()->getDatabasePlatform());
		if (!$schema->hasTable("{$prefix}activity_mq")) {
			$schemaReader->loadSchemaFromFile(__DIR__ . '/../database.xml', $schema);
			return;
		}
		// Altbestand: activity_mq steht schon, activity fehlt. Die database.xml direkt zu laden scheiterte
		// an der vorhandenen Tabelle, also nur die fehlenden Tabellen übernehmen
		$fromXml = new Schema();
		$schemaReader->loadSchemaFromFile(__DIR__ . '/../database.xml', $fromXml);
		self::copyMissingTables($fromXml, $schema);
	}

	/**
	 * Legt jede Tabelle aus $from, die in $to fehlt, mit Spalten und Indizes in $to an.
	 */
	private static function copyMissingTables(Schema $from, Schema $to) {
		foreach ($from->getTables() as $source) {
			if ($to->hasTable($source->getName())) {
				continue;
			}
			$table = $to->createTable($source->getName());
			foreach ($source->getColumns() as $column) {
				$table->addColumn($column->getName(), Type::getTypeRegistry()->lookupName($column->getType()), [
					'notnull' => $column->getNotnull(),
					'default' => $column->getDefault(),
					'length' => $column->getLength(),
					'precision' => $column->getPrecision(),
					'scale' => $column->getScale(),
					'fixed' => $column->getFixed(),
					'unsigned' => $column->getUnsigned(),
					'autoincrement' => $column->getAutoincrement(),
					'comment' => $column->getComment(),
				])->setPlatformOptions($column->getPlatformOptions());
			}
			foreach ($source->getIndexes() as $index) {
				if ($index->isPrimary()) {
					$table->setPrimaryKey($index->getColumns(), $index->getName());
				} elseif ($index->isUnique()) {
					$table->addUniqueIndex($index->getColumns(), $index->getName());
				} else {
					$table->addIndex($index->getColumns(), $index->getName());
				}
			}
		}
	}
}
