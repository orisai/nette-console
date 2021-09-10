<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Doubles;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class LazyCommand extends Command
{

	/**
	 * @var string|null
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
	 */
	protected static $defaultName = 'tests:lazy';

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return 0;
	}

}
