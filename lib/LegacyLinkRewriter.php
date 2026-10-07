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

namespace OCA\Activity;

/**
 * Schreibt Links um, die eine Altinstanz gespeichert hat, deren Datenbank auf
 * diese Instanz umgezogen ist.
 *
 * Der Kern speichert Links samt Webroot (linkToRoute, linkTo, imagePath) und
 * teils samt Host. Lag die Altinstanz unter /owncloud oder auf einem anderen
 * Host, zeigen diese Links nach dem Umzug ins Leere.
 *
 * Umgeschrieben wird nur, was eindeutig zur Altinstanz gehört: relative Pfade
 * unter ihrem Webroot und, wenn die alte Basis-URL einen Host nennt, absolute
 * URLs dieses Hosts (http wie https). Fremde Hosts, Links dieser Instanz und
 * bereits umgeschriebene Links bleiben unberührt, ein zweiter Lauf ändert
 * nichts mehr. Anfrage und Anker eines Links bleiben erhalten.
 */
class LegacyLinkRewriter {
	private const ABSOLUTE = '#^https?://([^/?\#]*)(.*)$#is';

	/** @var string|null host[:port] der Altinstanz in Kleinbuchstaben, null = nur das Webroot ist bekannt */
	private $oldAuthority;

	/** @var string Webroot der Altinstanz ohne Schrägstrich am Ende ('' = Wurzel) */
	private $oldWebroot;

	/** @var string Präfix umgeschriebener Links: Webroot oder absolute Basis-URL dieser Instanz */
	private $newBase;

	/** @var string|null host[:port] dieser Instanz, wenn $newBase absolut ist */
	private $newAuthority;

	/** @var string Webroot dieser Instanz ohne Schrägstrich am Ende */
	private $newWebroot;

	/**
	 * @param string $oldBaseUrl z. B. https://cloud.example.com/owncloud oder /owncloud
	 * @param string $newBase Webroot ('' oder /cloud) oder absolute Basis-URL dieser Instanz
	 * @throws \InvalidArgumentException bei unbrauchbaren Angaben
	 */
	public function __construct(string $oldBaseUrl, string $newBase) {
		if (\trim($oldBaseUrl) === '') {
			throw new \InvalidArgumentException('The old base URL must not be empty');
		}
		[$this->oldAuthority, $this->oldWebroot] = self::parseBase($oldBaseUrl, 'old base URL');
		[$this->newAuthority, $this->newWebroot] = self::parseBase($newBase, 'new base');
		$this->newBase = \rtrim(\trim($newBase), '/');
	}

	/**
	 * @param string $link gespeicherter Link, Icon- oder Aktions-URL
	 * @return string|null neuer Link, oder null, wenn nichts zu ändern ist
	 */
	public function rewrite(string $link): ?string {
		if (\preg_match(self::ABSOLUTE, $link, $m)) {
			if ($this->oldAuthority === null || \strtolower($m[1]) !== $this->oldAuthority) {
				return null;
			}
			$path = $m[2];
			// Ohne bekannten neuen Host (Ziel ist ein Webroot) oder bei gleichem
			// Host lässt sich eine URL der Altinstanz nicht sicher von einer
			// dieser Instanz unterscheiden – dann gilt dieselbe Vorsicht wie bei
			// relativen Links.
			$mayBeNew = $this->newAuthority === null || $this->newAuthority === $this->oldAuthority;
		} elseif (\strpos($link, '/') === 0 && \strpos($link, '//') !== 0) {
			$path = $link;
			$mayBeNew = true;
		} else {
			return null;
		}

		if (!self::isUnder($path, $this->oldWebroot)) {
			return null;
		}
		// Liegt das neue Webroot unterhalb des alten (etwa / → /cloud), gehört
		// ein Link darunter schon zu dieser Instanz: neu angelegt oder bereits
		// umgeschrieben. Das hält neue Daten unberührt und den zweiten Lauf
		// zum No-op.
		if ($mayBeNew
			&& \strlen($this->newWebroot) > \strlen($this->oldWebroot)
			&& self::isUnder($path, $this->newWebroot)
		) {
			return null;
		}

		$rest = (string)\substr($path, \strlen($this->oldWebroot));
		if ($rest === '' || $rest[0] !== '/') {
			$rest = '/' . $rest;
		}
		$result = $this->newBase . $rest;
		return $result === $link ? null : $result;
	}

	/**
	 * @return array{0: string|null, 1: string} host[:port] (oder null) und Webroot
	 */
	private static function parseBase(string $base, string $label): array {
		$base = \trim($base);
		$authority = null;
		$path = $base;
		if (\preg_match(self::ABSOLUTE, $base, $m)) {
			$authority = \strtolower($m[1]);
			$path = $m[2];
			if ($authority === '') {
				throw new \InvalidArgumentException("The $label has no host: $base");
			}
		}
		if ($path !== ''
			&& ($path[0] !== '/' || \strpos($path, '//') === 0 || \strpbrk($path, '?#') !== false)
		) {
			throw new \InvalidArgumentException(
				"The $label must be an http(s) URL or a path starting with a single /: $base"
			);
		}
		return [$authority, \rtrim($path, '/')];
	}

	/**
	 * Liegt $path unter $prefix – auf ganzer Pfadgrenze, /owncloud2 liegt
	 * also nicht unter /owncloud?
	 */
	private static function isUnder(string $path, string $prefix): bool {
		if ($prefix !== '' && \strpos($path, $prefix) !== 0) {
			return false;
		}
		$next = (string)\substr($path, \strlen($prefix), 1);
		return $next === '' || \strpos('/?#', $next) !== false;
	}
}
