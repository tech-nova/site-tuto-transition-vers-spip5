<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function tuto_upgrade(string $nom_meta_base_version, string $version_cible): void {
	$maj = [];
	$maj['create'] = [
		['maj_tables', ['spip_mots']],
	];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function tuto_vider_tables(string $nom_meta_base_version): void {
	sql_alter('TABLE spip_mots DROP COLUMN synonymes');
	effacer_meta($nom_meta_base_version);
}
