/*
 * SPIP, côté Symfony — comportements de la page
 *
 * Progression du parcours (localStorage), lexique (touche « / »), menu mobile,
 * copie des blocs de code, onglets de code et simulateurs « À manipuler ».
 * Repris du site d'origine, adapté à des pages SPIP distinctes.
 */
(() => {
	'use strict';

	const $ = (selector, root = document) => root.querySelector(selector);
	const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

	/* ---------- Progression ---------- */

	const PROGRESS_KEY = 'spip-cote-symfony:v1';
	let storage;
	try { storage = window.localStorage; } catch { /* navigation privée */ }

	const lessonIds = () => $$('#course-nav a[data-lesson]').map(a => a.dataset.lesson);

	function loadProgress() {
		try {
			const data = JSON.parse(storage?.getItem(PROGRESS_KEY) || '{}');
			const ids = lessonIds();
			return {
				completed: [...new Set(Array.isArray(data.completed) ? data.completed.map(String).filter(x => ids.includes(x)) : [])],
				checks: [...new Set(Array.isArray(data.checks) ? data.checks.map(String) : [])]
			};
		} catch {
			return { completed: [], checks: [] };
		}
	}

	function saveProgress(state) {
		try {
			if (!storage) return false;
			storage.setItem(PROGRESS_KEY, JSON.stringify(state));
			return true;
		} catch {
			return false;
		}
	}

	let progress = loadProgress();
	let persistent = saveProgress(progress);

	function persist() {
		persistent = saveProgress(progress);
		updateProgress();
	}

	function completeLesson(id) {
		if (id && !progress.completed.includes(id)) {
			progress = { ...progress, completed: [...progress.completed, id] };
			persist();
		}
	}

	function updateProgress() {
		const ids = lessonIds();
		const done = progress.completed.length;
		const count = $('#progress-count');
		if (count) count.textContent = `${done} / ${ids.length}`;
		const bar = $('#course-progress');
		if (bar) bar.value = done;
		const note = $('#storage-note');
		if (note) note.textContent = persistent ? 'Progression gardée dans ce navigateur.' : 'Progression gardée pour cette visite.';

		$$('#course-nav a[data-lesson]').forEach((a, i) => {
			const isDone = progress.completed.includes(a.dataset.lesson);
			a.classList.toggle('is-done', isDone);
			$('.nav-num', a).textContent = isDone ? '✓' : String(i + 1).padStart(2, '0');
		});

		// Accueil : étapes lues et bouton « Reprendre »
		$$('[data-course-lesson]').forEach(row => {
			const isDone = progress.completed.includes(row.dataset.courseLesson);
			row.classList.toggle('completed', isDone);
			const index = $('.course-index', row);
			index.textContent = isDone ? '✓' : index.dataset.numero;
		});
		const start = $('#start-course');
		if (start) {
			const next = $$('#course-nav a[data-lesson]').find(a => !progress.completed.includes(a.dataset.lesson));
			if (next) start.href = next.href;
			start.firstChild.textContent = done > 0 ? 'Reprendre le parcours ' : 'Commencer le parcours ';
		}

		// Étape : bouton « lu »
		const button = $('#complete-lesson');
		if (button) {
			const isDone = progress.completed.includes(button.dataset.lesson);
			button.setAttribute('aria-pressed', String(isDone));
			button.classList.toggle('is-complete', isDone);
			button.innerHTML = `<span class="complete-check" aria-hidden="true">${isDone ? '✓' : '+'}</span><span>${isDone ? 'Étape marquée comme lue' : 'Marquer cette étape comme lue'}</span>`;
		}

		$$('[data-confidence]').forEach(input => { input.checked = progress.checks.includes(input.dataset.confidence); });
	}

	function announce(message) {
		const zone = $('#announcement');
		if (zone) zone.textContent = message;
	}

	/* ---------- Lexique ---------- */

	const dialog = $('#glossary-dialog');
	let glossaryTrigger = null;

	const normalize = text => String(text).toLowerCase().replace(/œ/g, 'oe').replace(/æ/g, 'ae').normalize('NFD').replace(/[̀-ͯ]/g, '');
	const entries = () => $$('.glossary-entry[data-recherche]', dialog || document);

	function filterGlossary() {
		const q = normalize($('#glossary-search').value.trim());
		let found = 0;
		entries().forEach(entry => {
			const match = normalize(entry.dataset.recherche).includes(q);
			entry.hidden = !match;
			if (match) found++;
		});
		$('#glossary-count').textContent = `${found} ${found > 1 ? 'repères' : 'repère'}`;
		$('.empty-glossary', dialog).hidden = found > 0;
		$('#glossary-results').scrollTop = 0;
	}

	function openGlossary(term = '') {
		if (!dialog) return;
		glossaryTrigger = document.activeElement;
		$('#glossary-search').value = term;
		filterGlossary();
		if (!dialog.open) dialog.showModal();
		$('#glossary-search').focus();
		$('#glossary-search').select();
	}

	// Un lien [?terme#lexique] ouvre le lexique sur son mot
	function glossaryEntryFor(link) {
		return entries().find(entry => new URL(entry.dataset.url, location.href).href === link.href);
	}

	if (dialog) {
		$('#glossary-search').addEventListener('input', filterGlossary);
		dialog.addEventListener('click', e => {
			if (e.target !== dialog) return;
			const r = dialog.getBoundingClientRect();
			if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dialog.close();
		});
		dialog.addEventListener('close', () => { if (glossaryTrigger?.isConnected) glossaryTrigger.focus({ preventScroll: true }); });
		$$('a.spip_glossaire').forEach(link => {
			const entry = glossaryEntryFor(link);
			if (entry) link.title = `Voir « ${entry.dataset.nom} » dans le lexique`;
		});
	}

	/* ---------- Menu mobile ---------- */

	function closeMenu(returnFocus = true) {
		const wasOpen = document.body.classList.contains('nav-open');
		document.body.classList.remove('nav-open');
		$('#menu-button')?.setAttribute('aria-expanded', 'false');
		if (wasOpen && returnFocus) $('#menu-button')?.focus();
	}

	/* ---------- Copie des blocs de code ---------- */

	async function copyCode(button) {
		const pre = $('pre', button.closest('.codebox'));
		try {
			if (!navigator.clipboard?.writeText) throw new Error('selection');
			await navigator.clipboard.writeText(pre.textContent);
			button.textContent = 'Copié ✓';
			announce('Le code a été copié.');
		} catch {
			const range = document.createRange();
			range.selectNodeContents(pre);
			const selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(range);
			pre.focus();
			button.textContent = 'Code sélectionné';
			announce('Le code est sélectionné. Utilise le raccourci de copie de ton navigateur.');
		}
		setTimeout(() => { if (button.isConnected) button.textContent = 'Copier'; }, 2200);
	}

	/* ---------- Simulateurs « À manipuler » ---------- */

	const escapeHtml = text => String(text).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

	// Services : le même service reçoit un journal du site ou de test
	function updateService(demo) {
		const mode = $('input[type=radio]:checked', demo)?.value || 'normal';
		demo.dataset.mode = mode;
		$$('[data-service-destination], [data-service-explication]', demo).forEach(el => { el.textContent = el.dataset[mode]; });
	}

	// Événements : les listeners actifs modifient le HTML de affichage_final
	function dispatchPage(active) {
		let html = '<article>Bonjour SPIP !</article>';
		const log = ['Le code SPIP déclenche affichage_final.'];
		if (active.includes('signature')) {
			html += '<footer>Fabriqué avec SPIP</footer>';
			log.push('Listener Signature : ajoute le pied de page.');
		}
		if (active.includes('repere')) {
			html = html.replace('<article>', '<article data-guide="symfony">');
			log.push('Listener Repère : ajoute un attribut au contenu.');
		}
		log.push('Le code SPIP récupère le HTML modifié.');
		return { html, log };
	}

	function dispatch(demo) {
		const active = $$('[data-listener]:checked', demo).map(input => input.dataset.listener);
		const result = dispatchPage(active);
		$('.event-result', demo).innerHTML = `<div class="dispatch-log"><span class="mini-label">CE QUI VIENT DE SE PASSER</span><ol>${result.log.map(x => `<li>${x}</li>`).join('')}</ol></div><div class="event-preview"><span class="mini-label">APERÇU DU CONTENU</span><div class="mini-page">${result.html}</div></div><div class="event-code"><span class="mini-label">LE HTML RÉCUPÉRÉ</span><pre tabindex="0"><code>${escapeHtml(result.html)}</code></pre></div>`;
	}

	// Trajet d'une page : une étape affichée à la fois
	function showHttpStep(demo, index) {
		const steps = $$('[data-http-step]', demo);
		index = Math.max(0, Math.min(index, steps.length - 1));
		demo.dataset.step = index;
		steps.forEach((button, i) => {
			button.classList.toggle('active', i === index);
			button.setAttribute('aria-pressed', String(i === index));
		});
		$$('[data-http-detail]', demo).forEach((detail, i) => { detail.hidden = i !== index; });
		$('[data-http-prev]', demo).disabled = index === 0;
		$('[data-http-next]', demo).disabled = index === steps.length - 1;
	}

	/* ---------- Événements ---------- */

	document.addEventListener('click', e => {
		const target = e.target.closest('button, a');
		if (!target) return;

		if (target.matches('[data-complete-lesson]')) completeLesson(target.dataset.completeLesson);
		if (target.matches('.skip-link')) { e.preventDefault(); $('#main').focus(); return; }
		if (target.matches('a.spip_glossaire') && dialog) {
			const entry = glossaryEntryFor(target);
			if (entry) { e.preventDefault(); openGlossary(entry.dataset.nom); return; }
		}
		if (target.matches('#open-glossary, [data-open-glossary]')) { openGlossary(); return; }
		if (target.matches('.close-dialog')) { dialog.close(); return; }
		if (target.id === 'clear-glossary') { $('#glossary-search').value = ''; filterGlossary(); $('#glossary-search').focus(); return; }
		if (target.id === 'menu-button') {
			if (document.body.classList.contains('nav-open')) closeMenu();
			else { document.body.classList.add('nav-open'); target.setAttribute('aria-expanded', 'true'); $('#nav-close').focus(); }
			return;
		}
		if (target.id === 'nav-close') { closeMenu(); return; }
		if (target.id === 'complete-lesson') {
			const id = target.dataset.lesson;
			const isDone = progress.completed.includes(id);
			progress = { ...progress, completed: isDone ? progress.completed.filter(x => x !== id) : [...progress.completed, id] };
			persist();
			announce(isDone ? 'Étape retirée des leçons lues.' : 'Étape marquée comme lue.');
			return;
		}
		if (target.matches('.copy-button')) { copyCode(target); return; }
		if (target.matches('[data-code-tab]')) {
			const group = target.closest('.code-switch');
			$$('[data-code-tab]', group).forEach(el => { el.classList.toggle('active', el === target); el.setAttribute('aria-pressed', String(el === target)); });
			$$('[data-code-panel]', group).forEach(el => { el.hidden = el.dataset.codePanel !== target.dataset.codeTab; });
			return;
		}
		if (target.matches('[data-dispatch]')) { dispatch(target.closest('.demo')); return; }
		const http = target.closest('[data-demo="trajet"]');
		if (http) {
			const current = Number(http.dataset.step || 0);
			if (target.matches('[data-http-step]')) showHttpStep(http, Number(target.dataset.httpStep));
			if (target.matches('[data-http-next]')) showHttpStep(http, current + 1);
			if (target.matches('[data-http-prev]')) showHttpStep(http, current - 1);
			return;
		}
		if (target.matches('[data-reset-progress]')) {
			progress = { completed: [], checks: [] };
			persist();
			const status = $('.reset-status', target.parentElement);
			if (status) status.textContent = 'Progression remise à zéro. Tu peux reprendre le parcours librement.';
		}
	});

	document.addEventListener('change', e => {
		const demo = e.target.closest('.demo');
		if (demo?.dataset.demo === 'service') updateService(demo);
		if (demo?.dataset.demo === 'events' && e.target.matches('[data-listener]')) {
			$('.event-result', demo).innerHTML = '<p class="demo-prompt">Configuration modifiée. Déclenche le pipeline pour voir le résultat.</p>';
		}
		if (e.target.matches('[data-confidence]')) {
			const id = e.target.dataset.confidence;
			progress = { ...progress, checks: e.target.checked ? [...new Set([...progress.checks, id])] : progress.checks.filter(x => x !== id) };
			persist();
		}
	});

	document.addEventListener('keydown', e => {
		const editing = e.target.matches('input, textarea, select') || e.target.isContentEditable;
		if (e.key === '/' && !editing && !e.metaKey && !e.ctrlKey && !e.altKey) { e.preventDefault(); openGlossary(); }
		if (e.key === 'Escape' && document.body.classList.contains('nav-open')) closeMenu();
	});

	window.matchMedia('(min-width: 961px)').addEventListener('change', e => { if (e.matches) closeMenu(false); });

	$$('[data-demo="service"]').forEach(updateService);
	updateProgress();
})();
