<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * URL d'un terme du lexique, pour le raccourci `[?terme#lexique]`
 *
 * Le terme est cherché parmi les mots du groupe « Lexique » : titre exact,
 * puis titre qui commence par le terme, puis synonymes. Un numéro
 * (`[?terme#lexique12]`) désigne directement le mot.
 */
function glossaire_lexique($terme, $id = '') {
	$id_mot = $id ? (int) $id : tuto_lexique_trouver($terme);

	return $id_mot ? generer_objet_url($id_mot, 'mot') : '';
}

/**
 * Identifiant du mot du lexique qui correspond à un terme
 */
function tuto_lexique_trouver(string $terme): int {
	static $mots = null;
	if ($mots === null) {
		$mots = sql_allfetsel(
			'id_mot, titre, synonymes',
			'spip_mots',
			'id_groupe=' . (int) lire_config('tuto/id_groupe_lexique')
		);
	}
	$cherche = tuto_normaliser($terme);
	foreach ($mots as $mot) {
		if (tuto_normaliser($mot['titre']) === $cherche) {
			return (int) $mot['id_mot'];
		}
	}
	foreach ($mots as $mot) {
		if (str_starts_with(tuto_normaliser($mot['titre']), $cherche)) {
			return (int) $mot['id_mot'];
		}
	}
	foreach ($mots as $mot) {
		if (in_array($cherche, preg_split('/\s*,\s*/', tuto_normaliser($mot['synonymes'])))) {
			return (int) $mot['id_mot'];
		}
	}

	return 0;
}

/**
 * Minuscules sans accents, pour comparer des termes
 */
function tuto_normaliser(string $texte): string {
	include_spip('inc/charsets');

	return trim(strtolower(translitteration(supprimer_numero($texte))));
}

/**
 * Durée de lecture estimée d'un texte, en minutes
 *
 * Environ 100 mots par minute : une lecture attentive, avec du code et
 * des démonstrations à manipuler.
 */
function tuto_temps_lecture(string $texte): int {
	$mots = count(preg_split('/\s+/u', trim(textebrut($texte))));

	return max(1, (int) round($mots / 100));
}

/**
 * Chemin d'une page de la documentation SPIP 5.0 à partir de son URL
 */
function tuto_chemin_doc(string $url): string {
	return preg_match('#/5\.0/(.+)$#', $url, $m) ? $m[1] : $url;
}

/**
 * Colonnes renseignées d'un modèle `colonnes` (paramètres etiquette1, titre1, texte1, code1…)
 *
 * @param array|string $env Environnement du modèle, éventuellement sérialisé (`#ENV*`)
 */
function tuto_colonnes($env): array {
	if (is_string($env)) {
		$env = unserialize($env) ?: [];
	}
	$colonnes = [];
	for ($i = 1; $i <= 3; $i++) {
		$colonne = [];
		foreach (['etiquette', 'titre', 'texte', 'code'] as $champ) {
			$colonne[$champ] = trim($env[$champ . $i] ?? '');
		}
		if (array_filter($colonne)) {
			$colonnes[$i] = $colonne;
		}
	}

	return $colonnes;
}

/**
 * Assemble les blocs composés de plusieurs éléments du texte
 *
 * Les modèles `depliant`, `code_bascule` et `legende_code` posent des
 * marqueurs ; le texte compris entre ces marqueurs reste un texte SPIP
 * ordinaire, modifiable dans l'éditeur. Ils sont assemblés ici, sur le texte
 * entièrement calculé : `[(#TEXTE|tuto_assembler)]`.
 *
 * @filtre
 */
function tuto_assembler(string $texte): string {
	if (str_contains($texte, 'spip_code_block')) {
		$texte = tuto_assembler_codes($texte);
	}
	if (str_contains($texte, 'tuto-onglet')) {
		$texte = tuto_assembler_onglets($texte);
	}
	if (str_contains($texte, 'tuto-depliant')) {
		$texte = preg_replace(
			'#<div class="tuto-depliant" data-titre="([^"]*)"></div>(.*?)<div class="tuto-depliant-fin"></div>#s',
			'<details class="depliant"><summary>$1</summary>$2</details>',
			$texte
		);
	}
	if (str_contains($texte, 'tuto-lecture')) {
		$texte = preg_replace(
			'#(?:<div class="tuto-lecture">.*?</div>\s*)+#s',
			'<div class="next-reading">$0</div>',
			$texte
		);
	}

	return $texte;
}

/**
 * Habille chaque bloc de code : titre, bouton « Copier » et légende
 */
function tuto_assembler_codes(string $texte): string {
	return preg_replace_callback(
		'#(?:<div class="tuto-legende-code" data-titre="([^"]*)" data-legende="([^"]*)"></div>\s*)?'
			. '<div class="precode">\s*(<pre[^>]*>.*?</pre>)\s*</div>#s',
		function ($m) {
			$titre = $m[1] ?? '';
			if ($titre === '') {
				$titre = preg_match('/data-language="([^"]+)"/', $m[3], $l) ? 'Code ' . strtoupper($l[1]) : 'Code';
			}
			$pre = preg_replace('/^<pre/', '<pre tabindex="0"', $m[3]);
			$legende = empty($m[2]) ? '' : '<p class="code-caption">' . $m[2] . '</p>';

			return '<figure class="codebox"><figcaption><span>' . $titre . '</span>'
				. '<button type="button" class="copy-button" aria-label="Copier le code : ' . $titre . '">Copier</button>'
				. '</figcaption>' . $pre . $legende . '</figure>';
		},
		$texte
	);
}

/**
 * Regroupe les blocs placés entre des modèles `code_bascule` en onglets
 */
function tuto_assembler_onglets(string $texte): string {
	return preg_replace_callback(
		'#(<div class="tuto-onglet" data-onglet="[^"]*"></div>.*?)<div class="tuto-onglets-fin"></div>#s',
		function ($m) {
			static $groupe = 0;
			$groupe++;
			$parties = preg_split('#<div class="tuto-onglet" data-onglet="([^"]*)"></div>#', $m[1], -1, PREG_SPLIT_DELIM_CAPTURE);
			$boutons = $panneaux = '';
			for ($i = 1; $i < count($parties); $i += 2) {
				$n = ($i - 1) / 2;
				$actif = ($n === 0);
				$id = "onglet-$groupe-$n";
				$boutons .= '<button type="button" data-code-tab="' . $id . '" aria-pressed="' . ($actif ? 'true' : 'false') . '"'
					. ($actif ? ' class="active"' : '') . '>' . $parties[$i] . '</button>';
				$panneaux .= '<div data-code-panel="' . $id . '"' . ($actif ? '' : ' hidden') . '>' . trim($parties[$i + 1]) . '</div>';
			}

			return '<div class="code-switch"><div class="code-tabs" role="group" aria-label="Versions de l’exemple">'
				. $boutons . '</div>' . $panneaux . '</div>';
		},
		$texte
	);
}

/**
 * Valeurs renseignées des paramètres numérotés d'un modèle (repere1, repere2…)
 *
 * @param array|string $env Environnement du modèle, éventuellement sérialisé (`#ENV*`)
 */
function tuto_parametres_numerotes($env, string $nom, int $max): array {
	if (is_string($env)) {
		$env = unserialize($env) ?: [];
	}
	$valeurs = [];
	for ($i = 1; $i <= $max; $i++) {
		if (trim($env[$nom . $i] ?? '') !== '') {
			$valeurs[$i] = trim($env[$nom . $i]);
		}
	}

	return $valeurs;
}

/**
 * Identifiants des étapes du parcours, dans l'ordre
 *
 * Les étapes sont les sous-rubriques publiées du secteur « parcours ».
 */
function tuto_etapes(): array {
	static $etapes = null;
	if ($etapes === null) {
		$etapes = array_map('intval', array_column(sql_allfetsel(
			'id_rubrique',
			'spip_rubriques',
			'id_parent=' . (int) lire_config('tuto/id_parcours') . " AND statut='publie'",
			'',
			'0+titre, titre'
		), 'id_rubrique'));
	}

	return $etapes;
}

/**
 * Rang d'une étape dans le parcours (1, 2…), 0 si ce n'est pas une étape
 */
function tuto_etape_rang($id_rubrique): int {
	$rang = array_search((int) $id_rubrique, tuto_etapes(), true);

	return $rang === false ? 0 : $rang + 1;
}

/**
 * Étape précédente (-1) ou suivante (+1) ; 0 en début ou fin de parcours
 */
function tuto_etape_voisine($id_rubrique, int $sens): int {
	$rang = tuto_etape_rang($id_rubrique);

	return $rang ? (tuto_etapes()[$rang - 1 + $sens] ?? 0) : 0;
}

/**
 * Nombre sur deux chiffres : 1 → 01
 */
function tuto_deux_chiffres($nombre): string {
	return str_pad((string) (int) $nombre, 2, '0', STR_PAD_LEFT);
}
