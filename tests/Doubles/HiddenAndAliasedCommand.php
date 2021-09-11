<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Doubles;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class HiddenAndAliasedCommand extends Command
{

	/**
	 * @var string|null
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
	 */
	protected static $defaultName = '|i|am|hiding|aliasses';

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return 0;
	}

}
