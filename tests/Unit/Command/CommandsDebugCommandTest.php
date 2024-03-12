<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\Command;

use OriNette\Console\Command\CommandsDebugCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\OriNette\Console\Doubles\HiddenAndAliasedCommand;
use Tests\OriNette\Console\Doubles\SimpleCommand;
use Tests\OriNette\Console\Doubles\UrlPrintingCommand;
use Tests\OriNette\Console\Helpers\CommandOutputHelper;
use function putenv;
use const PHP_VERSION_ID;

final class CommandsDebugCommandTest extends TestCase
{

	public function testNoCommands(): void
	{
		$command = new CommandsDebugCommand([]);
		$tester = new CommandTester($command);

		$code = $tester->execute([]);

		self::assertSame(
			<<<'MSG'
All commands are lazy-loaded.

MSG,
			$tester->getDisplay(),
		);
		self::assertSame($command::SUCCESS, $code);
	}

	public function testCommands(): void
	{
		$commands = [
			['service1', UrlPrintingCommand::class, true, false],
			['service2', HiddenAndAliasedCommand::class, false, true],
			['service3', SimpleCommand::class, true, true],
			['service3', Command::class, false, false],
		];
		$command = new CommandsDebugCommand($commands);
		$tester = new CommandTester($command);

		putenv('COLUMNS=80');
		$tester->execute([]);

		if (PHP_VERSION_ID < 8_01_00) {
			self::markTestSkipped('Printing of X acts weird on PHP < 8.1');
		}

		$expected = <<<'MSG'
Following commands are missing ❌ either name or description. Check orisai/nette-console documentation about lazy loading to learn how to fix it.

Name Description Service name Service type
✔️   ❌          service1     Tests\OriNette\Console\Doubles\UrlPrintingCommand
❌   ✔️          service2     Tests\OriNette\Console\Doubles\HiddenAndAliasedCommand
✔️   ✔️          service3     Tests\OriNette\Console\Doubles\SimpleCommand
❌   ❌          service3     Symfony\Component\Console\Command\Command

MSG;

		self::assertSame(
			$expected,
			CommandOutputHelper::getCommandOutput($tester),
		);
		self::assertSame($command::FAILURE, $tester->getStatusCode());
	}

}
