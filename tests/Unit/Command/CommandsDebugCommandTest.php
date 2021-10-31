<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\Command;

use OriNette\Console\Command\CommandsDebugCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use function preg_replace;

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
			['service1', true, false],
			['service2', false, true],
			['service3', true, true],
		];
		$command = new CommandsDebugCommand($commands);
		$tester = new CommandTester($command);

		$code = $tester->execute([]);

		self::assertSame(
			<<<'MSG'
Following commands are missing ❌ either name or description. Check orisai/nette-console documentation about lazy loading to learn how to fix it.
 ---------- ------ -------------
  Service    Name   Description
  service1   ✔️     ❌
  service2   ❌      ✔️
  service3   ✔️     ✔️
 ---------- ------ -------------

MSG,
			preg_replace('/ +$/m', '', $tester->getDisplay()),
		);
		self::assertSame($command::FAILURE, $code);
	}

}
