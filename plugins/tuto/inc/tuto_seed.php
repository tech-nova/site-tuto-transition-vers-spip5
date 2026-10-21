<?php

/**
 * Contenu initial du site, lu dans le répertoire seed/ du dépôt
 *
 * - site.yaml : réglages (metas) ;
 * - rubriques.yaml : rubriques, article d'accueil de chacune, liens « Pour aller plus loin » ;
 * - lexique.yaml : groupe de mots-clés « Lexique » et ses mots, liés aux rubriques ;
 * - textes/*.spip : textes des articles ;
 * - IMG/ : logos éventuels, nommés d'après la clé de la rubrique (pourquoi.png…).
 *
 * Chaque objet porte une clé stable. La correspondance clé → identifiant est
 * gardée dans la configuration `tuto/seed` : relancer le seed ne crée que ce
 * qui manque. Avec `$maj`, les objets existants reprennent le contenu du seed.
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/config');
include_spip('inc/yaml');
include_spip('action/editer_objet');
include_spip('action/editer_liens');

/**
 * Crée ou met à jour le contenu du site
 *
 * @param string $dir Répertoire seed/
 * @param bool $maj Remplacer le contenu des objets déjà créés
 * @param callable $journal Reçoit un message par action
 */
function tuto_seed(string $dir, bool $maj, callable $journal): void {
	tuto_seed_session_admin();

	$site = yaml_decode_file("$dir/site.yaml");
	foreach ($site['metas'] as $nom => $valeur) {
		ecrire_meta($nom, $valeur);
	}
	$journal('Réglages du site');

	// Toutes les rubriques d'abord : les textes peuvent pointer vers n'importe laquelle
	$arbre = yaml_decode_file("$dir/rubriques.yaml");
	$rubriques = [];
	tuto_seed_rubriques($arbre, 0, $maj, $rubriques, $journal);
	ecrire_config('tuto/id_parcours', $rubriques['parcours']['id']);
	ecrire_config('tuto/id_sources', $rubriques['sources']['id']);

	$ids_rubriques = array_map(fn($r) => $r['id'], $rubriques);

	// Le lexique avant les textes, qui y renvoient avec [?terme#lexique]
	$lexique = yaml_decode_file("$dir/lexique.yaml");
	tuto_seed_lexique($lexique, $ids_rubriques, $maj);
	$journal(count($lexique['mots']) . ' mots dans le lexique');

	foreach ($rubriques as $cle => $rubrique) {
		tuto_seed_article($dir, $cle, $rubrique, $ids_rubriques, $maj, $journal);
		foreach ($rubrique['desc']['liens'] ?? [] as [$url, $titre]) {
			tuto_seed_objet('site', $cle . '-' . substr(md5($url), 0, 10), $rubrique['id'], [
				'nom_site' => $titre,
				'url_site' => $url,
			], $maj);
		}
		tuto_seed_logo($dir, $cle, $rubrique['id']);
	}
	$journal('Articles et liens');

	include_spip('inc/rubriques');
	calculer_rubriques();
	propager_les_secteurs();
}

/**
 * Agir comme le premier webmestre : auteur des contenus, droit de publier
 */
function tuto_seed_session_admin(): void {
	$auteur = sql_fetsel('*', 'spip_auteurs', "statut='0minirezo'", '', 'id_auteur', '0,1');
	if (!$auteur) {
		throw new RuntimeException('Aucun administrateur : installer SPIP avant le seed.');
	}
	$GLOBALS['visiteur_session'] = $auteur;
	$GLOBALS['connect_id_auteur'] = (int) $auteur['id_auteur'];
	$GLOBALS['connect_statut'] = $auteur['statut'];
}

/**
 * Crée les rubriques d'un niveau de l'arbre, puis leurs sous-rubriques
 *
 * @param array $rubriques Reçoit clé → ['id' => …, 'desc' => description du seed]
 */
function tuto_seed_rubriques(array $niveau, int $id_parent, bool $maj, array &$rubriques, callable $journal): void {
	foreach ($niveau as $desc) {
		$id = tuto_seed_objet('rubrique', $desc['cle'], $id_parent, [
			'titre' => $desc['titre'],
			'descriptif' => $desc['descriptif'] ?? '',
		], $maj);
		tuto_seed_url('rubrique', $id, $desc['url']);
		$rubriques[$desc['cle']] = ['id' => $id, 'desc' => $desc];
		$journal('Rubrique ' . $desc['titre']);
		tuto_seed_rubriques($desc['rubriques'] ?? [], $id, $maj, $rubriques, $journal);
	}
}

/**
 * Crée l'article d'accueil d'une rubrique
 */
function tuto_seed_article(string $dir, string $cle, array $rubrique, array $ids_rubriques, bool $maj, callable $journal): void {
	$desc = $rubrique['desc']['article'] ?? null;
	if (!$desc) {
		return;
	}
	$texte = file_get_contents("$dir/textes/" . $desc['texte']);
	$texte = preg_replace_callback(
		'/->rubrique:([\w-]+)\]/',
		fn($m) => '->rub' . ($ids_rubriques[$m[1]] ?? 0) . ']',
		$texte
	);
	$id_article = tuto_seed_objet('article', $cle, $rubrique['id'], [
		'surtitre' => $desc['surtitre'] ?? '',
		'titre' => $desc['titre'],
		'chapo' => $desc['chapo'] ?? '',
		'texte' => trim($texte),
		'ps' => $desc['ps'] ?? '',
	], $maj);
	sql_updateq('spip_rubriques', ['id_article_accueil' => $id_article], 'id_rubrique=' . $rubrique['id']);
}

/**
 * Logo d'une rubrique : seed/IMG/<clé>.(png|jpg|svg…), s'il existe
 */
function tuto_seed_logo(string $dir, string $cle, int $id_rubrique): void {
	$fichiers = glob("$dir/IMG/$cle.*") ?: [];
	if (!$fichiers) {
		return;
	}
	include_spip('action/editer_logo');
	logo_modifier('rubrique', $id_rubrique, 'on', $fichiers[0]);
}

/**
 * Crée le groupe « Lexique », ses mots et leurs liens vers les rubriques
 */
function tuto_seed_lexique(array $lexique, array $ids_rubriques, bool $maj): void {
	$id_groupe = tuto_seed_objet('groupe_mots', 'lexique', 0, [
		'titre' => $lexique['groupe'],
		'tables_liees' => 'rubriques',
		'minirezo' => 'oui',
		'comite' => 'oui',
		'forum' => 'non',
		'unseul' => 'non',
		'obligatoire' => 'non',
	], $maj);
	ecrire_config('tuto/id_groupe_lexique', $id_groupe);

	foreach ($lexique['mots'] as $desc) {
		$deja = tuto_seed_id('mot', $desc['cle']);
		$id_mot = tuto_seed_objet('mot', $desc['cle'], $id_groupe, [
			'titre' => $desc['titre'],
			'descriptif' => $desc['definition'],
			'texte' => $desc['analogie'],
			'synonymes' => $desc['synonymes'] ?? '',
		], $maj);
		tuto_seed_url('mot', $id_mot, 'lexique-' . $desc['cle']);
		if (!$deja || $maj) {
			objet_dissocier(['mot' => $id_mot], ['rubrique' => '*']);
			$etapes = array_filter(array_map(fn($e) => $ids_rubriques[$e] ?? 0, $desc['etapes'] ?? []));
			if ($etapes) {
				objet_associer(['mot' => $id_mot], ['rubrique' => $etapes]);
			}
		}
	}
}

/**
 * Identifiant d'un objet déjà créé par le seed, s'il existe toujours
 */
function tuto_seed_id(string $objet, string $cle): int {
	$id = (int) lire_config("tuto/seed/$objet/$cle");
	if ($id && sql_countsel(table_objet_sql($objet), id_table_objet($objet) . '=' . $id)) {
		return $id;
	}

	return 0;
}

/**
 * Crée un objet publié, ou le met à jour s'il existe déjà et que `$maj` est demandé
 */
function tuto_seed_objet(string $objet, string $cle, int $id_parent, array $champs, bool $maj): int {
	$id = tuto_seed_id($objet, $cle);
	if ($id) {
		if ($maj) {
			objet_modifier($objet, $id, $champs);
		}
		return $id;
	}

	$id = (int) objet_inserer($objet, $id_parent ?: null, $champs);
	if (!$id) {
		throw new RuntimeException("Création impossible : $objet $cle");
	}
	// Lier l'auteur au nouvel objet appelle actualiser_sessions() qui, sans
	// fichier de session (ligne de commande), ne laisse dans visiteur_session
	// qu'un tableau partiel : la publication serait refusée.
	tuto_seed_session_admin();
	if (in_array($objet, ['article', 'site'])) {
		objet_instituer($objet, $id, ['statut' => 'publie']);
	}
	ecrire_config("tuto/seed/$objet/$cle", $id);

	return $id;
}

/**
 * Donne à un objet son URL propre, si elle n'est pas déjà prise
 */
function tuto_seed_url(string $objet, int $id, string $url): void {
	if (sql_countsel('spip_urls', 'url=' . sql_quote($url) . ' AND type=' . sql_quote($objet) . ' AND id_objet=' . $id)) {
		return;
	}
	include_spip('action/editer_url');
	$set = ['url' => $url, 'type' => $objet, 'id_objet' => $id, 'id_parent' => 0, 'perma' => 1];
	url_insert($set, false, '-');
}
