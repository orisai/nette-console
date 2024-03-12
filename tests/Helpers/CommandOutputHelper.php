<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Helpers;

use Symfony\Component\Console\Tester\CommandTester;
use function array_map;
use function assert;
use function explode;
use function implode;
use function preg_replace;
use function rtrim;
use const PHP_EOL;

final class CommandOutputHelper
{

	public static function getCommandOutput(CommandTester $tester): string
	{
		$display = preg_replace('~\R~u', PHP_EOL, $tester->getDisplay());
		assert($display !== null);

		return implode(
			PHP_EOL,
			array_map(
				static fn (string $s): string => rtrim($s),
				explode(PHP_EOL, $display),
			),
		);
	}

}
