<?php

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TutoSeed extends Command
{
	protected function configure(): void
	{
		$this
			->setName('tuto:seed')
			->setDescription('Crée ou met à jour le contenu du site à partir du répertoire seed/.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->demarrerSpip();
		$this->io->text('seed : à venir');
		return Command::SUCCESS;
	}
}
