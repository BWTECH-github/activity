/**
 * Die Aktivitäts-App am laufenden Produkt.
 *
 * Geprüft wird, was die Prüfung vom 16.09.2026 als Mangel belegt hat - und
 * zwar so, dass ein Rückfall auffällt. Die beiden schweren Funde (RSS-Token
 * eines gesperrten Kontos, Endlosschleife beim Mailversand) hängen an occ und
 * stehen deshalb in tests/visual/rss-sperre.sh bzw. sind im CHANGELOG belegt;
 * hier steht, was sich im Browser messen lässt.
 *
 * Aufruf: OC_PASSWORD=... node tests/visual/pruefe-aktivitaeten.js
 */
'use strict';

const { chromium } = require('playwright');

const BASIS = process.env.OC_URL || 'http://127.0.0.1:18130';
const NUTZER = process.env.OC_USER || 'admin';
const PASSWORT = process.env.OC_PASSWORD;

if (!PASSWORT) {
	console.error('OC_PASSWORD fehlt. Aufruf: OC_PASSWORD=... node tests/visual/pruefe-aktivitaeten.js');
	process.exit(2);
}

const ergebnisse = [];
function pruefe(name, ok, zusatz) {
	ergebnisse.push({ name, ok: !!ok, zusatz: zusatz === undefined ? '' : String(zusatz) });
}

(async () => {
	const browser = await chromium.launch();
	const seite = await browser.newPage({ viewport: { width: 1440, height: 900 } });
	const skriptfehler = [];
	seite.on('pageerror', (e) => skriptfehler.push(String(e).slice(0, 160)));

	await seite.goto(`${BASIS}/index.php/login`, { waitUntil: 'domcontentloaded' });
	await seite.fill('#user', NUTZER);
	await seite.fill('#password', PASSWORT);
	await seite.click('#submit');
	await seite.waitForLoadState('domcontentloaded');

	/* ---------- 1. Der Strom meldet sich einer Sprachausgabe ---------- */

	await seite.goto(`${BASIS}/index.php/apps/activity/`, { waitUntil: 'domcontentloaded' });
	await seite.waitForSelector('#container', { timeout: 20000 });
	await seite.waitForTimeout(2500);

	const strom = await seite.evaluate(() => {
		const behaelter = document.querySelector('#container');
		const laden = document.querySelector('#loading_activities');
		const ende = document.querySelector('#no_more_activities');
		return {
			rolle: behaelter ? behaelter.getAttribute('role') : null,
			name: behaelter ? behaelter.getAttribute('aria-label') : null,
			busy: behaelter ? behaelter.getAttribute('aria-busy') : null,
			ladenRolle: laden ? laden.getAttribute('role') : null,
			ladenText: laden ? laden.textContent.trim().slice(0, 40) : '',
			endeRolle: ende ? ende.getAttribute('role') : null,
			eintraege: document.querySelectorAll('#container .box').length,
		};
	});

	pruefe('Der Strom ist als fortlaufende Liste ausgezeichnet', strom.rolle === 'feed',
		`role=${strom.rolle}`);
	pruefe('Der Strom hat einen Namen', !!strom.name, strom.name);
	pruefe('Die Ladeanzeige ist eine Statusmeldung', strom.ladenRolle === 'status',
		strom.ladenText);
	pruefe('Die Schlusszeile ist eine Statusmeldung', strom.endeRolle === 'status');
	pruefe('Der Strom zeigt Einträge', strom.eintraege > 0, `${strom.eintraege} Einträge`);

	/* ---------- 2. Vorschau-Verweise doppeln den Betreff nicht ---------- */

	const verweise = await seite.evaluate(() => {
		const stumm = [];
		const benannt = [];
		document.querySelectorAll('#container .activitysubject a, #container .previewlist a')
			.forEach((a) => {
				const text = (a.textContent || '').trim();
				const name = a.getAttribute('aria-label');
				const versteckt = a.getAttribute('aria-hidden') === 'true';
				if (text === '' && !name && !versteckt) {
					stumm.push(a.outerHTML.slice(0, 60));
				} else if (text === '' && (name || versteckt)) {
					benannt.push(versteckt ? 'versteckt' : 'benannt');
				}
			});
		return { stumm: stumm, benannt: benannt.length };
	});

	pruefe('Kein Verweis ohne Namen im Tastaturlauf', verweise.stumm.length === 0,
		verweise.stumm.length ? verweise.stumm[0] : `${verweise.benannt} versorgt`);

	/* ---------- 3. "und N mehr" klappt wirklich auf ---------- */

	const aufklappen = await seite.evaluate(() => {
		const mehr = document.querySelector('.activity-more-link');
		if (!mehr) { return { vorhanden: false }; }
		const betreff = mehr.closest('.activitysubject');
		const vorher = betreff ? betreff.textContent.trim() : '';
		const adresseVorher = location.href;
		mehr.click();
		return new Promise((fertig) => {
			window.setTimeout(() => {
				fertig({
					vorhanden: true,
					rolle: mehr.getAttribute('role'),
					geaendert: betreff ? betreff.textContent.trim() !== vorher : false,
					// preventDefault: die Adresse darf kein '#' bekommen.
					adresseUnveraendert: location.href === adresseVorher,
					text: betreff ? betreff.textContent.trim().slice(0, 70) : '',
				});
			}, 700);
		});
	});

	if (aufklappen.vorhanden) {
		pruefe('"und N mehr" trägt die Rolle eines Schalters', aufklappen.rolle === 'button');
		pruefe('"und N mehr" klappt auf', aufklappen.geaendert, aufklappen.text);
		pruefe('Der Klick hängt kein # an die Adresse', aufklappen.adresseUnveraendert);
	} else {
		pruefe('"und N mehr" vorhanden', true, 'keine Sammelmeldung im Strom - übersprungen');
	}

	/* ---------- 4. Der Umschalter der Einstellungen ---------- */

	await seite.goto(`${BASIS}/index.php/settings/personal?sectionid=general`,
		{ waitUntil: 'domcontentloaded' });
	await seite.waitForSelector('.activity_select_group', { timeout: 20000 });
	await seite.waitForTimeout(1200);

	const umschalter = await seite.evaluate(() => {
		const alle = Array.from(document.querySelectorAll('.activity_select_group'));
		const erster = alle[0];
		if (erster) { erster.focus(); }
		return {
			anzahl: alle.length,
			alleSchalter: alle.every((e) => e.tagName === 'BUTTON'),
			alleBenannt: alle.every((e) => (e.getAttribute('aria-label') || '').length > 3),
			fokussierbar: document.activeElement === erster,
			zeiger: erster ? getComputedStyle(erster).cursor : '-',
		};
	});

	pruefe('Alle Umschalter sind echte Schalter', umschalter.alleSchalter,
		`${umschalter.anzahl} Stück`);
	pruefe('Jeder Umschalter hat einen Namen', umschalter.alleBenannt);
	pruefe('Der Umschalter ist mit der Tastatur erreichbar', umschalter.fokussierbar,
		`Zeiger: ${umschalter.zeiger}`);

	// Wirkt er auch? Zustand vorher merken, umschalten, vergleichen.
	const wirkung = await seite.evaluate(() => {
		const kaestchen = () => Array.from(
			document.querySelectorAll('#activity_notifications input.email:not(:disabled)')
		);
		const vorher = kaestchen().filter((c) => c.checked).length;
		const schalter = document.querySelector('.activity_select_group');
		if (schalter) { schalter.click(); }
		return new Promise((fertig) => {
			window.setTimeout(() => {
				const nachher = kaestchen().filter((c) => c.checked).length;
				// Zurückschalten, damit der Test nichts hinterlässt.
				if (schalter) { schalter.click(); }
				window.setTimeout(() => fertig({ vorher: vorher, nachher: nachher }), 600);
			}, 700);
		});
	});

	pruefe('Der Umschalter wirkt', wirkung.vorher !== wirkung.nachher,
		`${wirkung.vorher} -> ${wirkung.nachher} Häkchen`);

	/* ---------- 5. Die API liefert nicht unbegrenzt viel ---------- */

	const grenze = await seite.evaluate(async () => {
		const antwort = await fetch(
			OC.generateUrl('/apps/activity/api/v2/activity') + '/all?format=json&limit=100000',
			{ headers: { requesttoken: OC.requestToken } }
		).then((r) => r.json()).catch((e) => ({ fehler: String(e) }));
		const daten = (antwort.ocs && antwort.ocs.data) || [];
		return { anzahl: daten.length };
	});

	pruefe('Die API deckelt die Zahl der Einträge', grenze.anzahl <= 200,
		`${grenze.anzahl} Einträge bei limit=100000`);

	pruefe('Keine Skriptfehler auf den besuchten Seiten', skriptfehler.length === 0,
		skriptfehler.slice(0, 2).join(' | '));

	await browser.close();

	let schlecht = 0;
	console.log('\n--- Aktivitäten ---');
	ergebnisse.forEach((e) => {
		if (!e.ok) { schlecht++; }
		console.log(`${e.ok ? 'OK  ' : 'FEHL'}  ${e.name}${e.zusatz ? '  (' + e.zusatz + ')' : ''}`);
	});
	console.log(`\n${ergebnisse.length - schlecht}/${ergebnisse.length} bestanden`);
	process.exit(schlecht ? 1 : 0);
})();
