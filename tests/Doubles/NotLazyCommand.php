<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Doubles;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class NotLazyCommand extends Command
{

	public function __construct()
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return 0;
	}

}
