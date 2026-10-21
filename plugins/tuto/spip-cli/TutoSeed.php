<?php

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TutoSeed extends Command
{
	protected function configure(): void
	{
		$this
			->setName('tuto:seed')
			->setDescription('Crée le contenu du site à partir du répertoire seed/.')
			->setHelp('Ne crée que les objets manquants. Avec --maj, remplace aussi le contenu des objets existants par celui du seed.')
			->addOption('maj', null, InputOption::VALUE_NONE, 'Remplacer le contenu existant par celui du seed')
			->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Répertoire du seed', 'seed');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->demarrerSpip();

		$dir = $input->getOption('dir');
		if (!str_starts_with($dir, '/')) {
			$dir = _ROOT_RACINE . $dir;
		}
		if (!is_file("$dir/rubriques.yaml")) {
			$this->io->error("Pas de seed dans $dir");
			return Command::FAILURE;
		}

		include_spip('inc/tuto_seed');
		tuto_seed($dir, (bool) $input->getOption('maj'), fn($message) => $this->io->text($message));
		$this->io->success('Contenu à jour');

		return Command::SUCCESS;
	}
}
