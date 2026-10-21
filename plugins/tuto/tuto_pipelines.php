<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Ajoute le champ « synonymes » au formulaire d'édition des mots-clés
 *
 * @pipeline formulaire_fond
 */
function tuto_formulaire_fond(array $flux): array {
	if ($flux['args']['form'] === 'editer_mot') {
		$champ = recuperer_fond('formulaires/inc-editer_mot_synonymes', $flux['args']['contexte']);
		$flux['data'] = preg_replace(
			'/(<div class="editer editer_descriptif)/',
			$champ . '$1',
			$flux['data'],
			1
		);
	}

	return $flux;
}

/**
 * Styles des modèles pour leur aperçu dans l'éditeur
 *
 * @pipeline header_prive_css
 */
function tuto_header_prive_css(string $flux): string {
	$css = find_in_path('css/tuto_modeles_prive.css');
	if ($css) {
		$flux .= '<link rel="stylesheet" href="' . timestamp($css) . '" type="text/css" />' . "\n";
	}

	return $flux;
}

/**
 * Catégories des modèles du guide dans le catalogue « Insérer modèles »
 *
 * @pipeline inserer_modeles_lister_categories
 */
function tuto_inserer_modeles_lister_categories(array $categories): array {
	$categories['tuto_mise_en_page'] = ['nom' => 'Mise en page du guide'];
	$categories['tuto_manipuler'] = ['nom' => 'À manipuler'];

	return $categories;
}
