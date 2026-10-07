<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace OCA\Activity\Controller;

use OCA\Activity\Data;
use OCA\Activity\GroupHelper;
use OCA\Activity\PlainTextParser;
use OCA\Activity\UserSettings;
use OCP\Activity\IManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class Feed extends Controller {
	public const DEFAULT_PAGE_SIZE = 30;

	/** @var \OCA\Activity\Data */
	protected $data;

	/** @var \OCA\Activity\GroupHelper */
	protected $helper;

	/** @var \OCA\Activity\UserSettings */
	protected $settings;

	/** @var IURLGenerator */
	protected $urlGenerator;

	/** @var IManager */
	protected $activityManager;

	/** @var IConfig */
	protected $config;

	/** @var IFactory */
	protected $l10nFactory;

	/** @var IUserManager */
	protected $userManager;

	/** @var IL10N */
	protected $l;

	/** @var string */
	protected $user;

	/** @var string */
	protected $tokenUser;

	/**
	 * constructor of the controller
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param Data $data
	 * @param GroupHelper $helper
	 * @param UserSettings $settings
	 * @param IURLGenerator $urlGenerator
	 * @param IManager $activityManager
	 * @param IFactory $l10nFactory
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param string $user
	 */
	public function __construct(
		$appName,
		IRequest $request,
		Data $data,
		GroupHelper $helper,
		UserSettings $settings,
		IURLGenerator $urlGenerator,
		IManager $activityManager,
		IFactory $l10nFactory,
		IConfig $config,
		IUserManager $userManager,
		$user
	) {
		parent::__construct($appName, $request);
		$this->data = $data;
		$this->helper = $helper;
		$this->settings = $settings;
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
		$this->l10nFactory = $l10nFactory;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->user = $user;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function show() {
		try {
			$user = $this->activityManager->getCurrentUserId();

			/*
			 * Ein gesperrtes Konto darf hier nichts mehr lesen.
			 *
			 * Diese Route traegt die Annotation PublicPage; der Kern prueft die
			 * Anmeldung deshalb nicht, und das Konto kommt allein ueber das
			 * RSS-Token zustande. OC\Activity\Manager::getUserFromToken() fragt nur, ob
			 * der Wert 30 Zeichen lang ist und genau einem Konto gehoert -
			 * nicht, ob dieses Konto noch aktiv ist.
			 *
			 * Damit ueberlebte der Strom jede Sperrung: Sitzungen, App-
			 * Passwoerter und WebDAV waren abgeschnitten (Session.php prueft
			 * isEnabled an drei Stellen), der Feed lieferte weiter - und zwar
			 * auch NEUE Eintraege, also Dateinamen und Freigaben, die nach der
			 * Sperre entstanden sind. Am 16.09.2026 an der Testinstanz
			 * gemessen: WebDAV 401, RSS 200 mit 16 Eintraegen.
			 *
			 * Ein Passwortwechsel half ebenfalls nicht, das Token haengt nicht
			 * daran. Die Mailbenachrichtigung dieser App kennt die Pruefung
			 * uebrigens (BackgroundJob\EmailNotification), nur der Feed nicht.
			 */
			$konto = $this->userManager->get($user);
			if ($konto === null || !$konto->isEnabled()) {
				throw new \UnexpectedValueException('Account is disabled or gone');
			}

			$userLang = $this->config->getUserValue($user, 'core', 'lang');

			// Overwrite user and language in the helper
			$this->l = $this->l10nFactory->get('activity', $userLang);
			$parser = new PlainTextParser($this->l);
			$this->helper->setL10n($this->l);
			$this->helper->setUser($user);

			$description = (string) $this->l->t('Personal activity feed for %s', [$user]);
			$response = $this->data->get($this->helper, $this->settings, $user, 0, self::DEFAULT_PAGE_SIZE, 'desc', 'all');
			$data = $response['data'];

			$activities = [];
			foreach ($data as $activity) {
				$activity['subject_prepared'] = $parser->parseMessage($activity['subject_prepared']);
				$activity['message_prepared'] = $parser->parseMessage($activity['message_prepared']);
				$activities[] = $activity;
			}
		} catch (\UnexpectedValueException $e) {
			$this->l = $this->l10nFactory->get('activity');
			$description = (string) $this->l->t('Your feed URL is invalid');

			$activities = [
				[
					'activity_id'	=> -1,
					'timestamp'		=> \time(),
					'subject'		=> true,
					'subject_prepared'	=> $description,
				]
			];
		}

		$response = new TemplateResponse('activity', 'rss', [
			'rssLang'		=> $this->l->getLanguageCode(),
			'rssLink'		=> $this->urlGenerator->linkToRouteAbsolute('activity.Feed.show'),
			'rssPubDate'	=> \date('r'),
			'description'	=> $description,
			'activities'	=> $activities,
		], '');

		if ($this->request->getHeader('accept') !== null && \stristr($this->request->getHeader('accept'), 'application/rss+xml')) {
			$response->addHeader('Content-Type', 'application/rss+xml');
		} else {
			$response->addHeader('Content-Type', 'text/xml; charset=UTF-8');
		}

		return $response;
	}
}
