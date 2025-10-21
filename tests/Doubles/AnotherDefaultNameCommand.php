<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Doubles;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AnotherDefaultNameCommand extends Command
{

	public static function getDefaultName(): string
	{
		return 'another-default';
	}

	/**
	 * @throws void
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return 0;
	}

}
