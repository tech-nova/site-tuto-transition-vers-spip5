<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Ajoute aux mots-clés un champ « synonymes », utilisé par la recherche du lexique
 *
 * @pipeline declarer_tables_objets_sql
 */
function tuto_declarer_tables_objets_sql(array $tables): array {
	$tables['spip_mots']['field']['synonymes'] = "text DEFAULT '' NOT NULL";
	$tables['spip_mots']['champs_editables'][] = 'synonymes';
	$tables['spip_mots']['rechercher_champs']['synonymes'] = 2;

	return $tables;
}
