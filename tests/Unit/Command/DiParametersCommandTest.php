<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\Command;

use Nette\DI\Container;
use OriNette\Console\Command\DIParametersCommand;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Tester\CommandTester;

final class DiParametersCommandTest extends TestCase
{

	public function testNoParameters(): void
	{
		$container = new Container([]);
		$command = new DIParametersCommand($container, false, null);
		$tester = new CommandTester($command);

		$code = $tester->execute([]);

		self::assertSame(
			<<<'MSG'
No parameters found in DI container.

MSG,
			$tester->getDisplay(),
		);
		self::assertSame($command::SUCCESS, $code);
	}

	public function testNoExport(): void
	{
		$container = new Container([]);
		$command = new DIParametersCommand($container, true, null);
		$tester = new CommandTester($command);

		$code = $tester->execute([]);

		self::assertSame(
			// phpcs:disable SlevomatCodingStandard.Files.LineLength.LineTooLong
			<<<'MSG'
No parameters found in DI container.
Export of parameters into DIC is disabled. You may enable it for only this command by setting console extension option 'di > parameters > backup' to 'true'

MSG,
			// phpcs:enable
			$tester->getDisplay(),
		);
		self::assertSame($command::FAILURE, $code);
	}

	public function testParameters(): void
	{
		$parameters = [
			'consoleMode' => true,
			'appDir' => '/path/to/website/src',
			'integer' => 1_234,
			'null' => null,
			'arrAyy' => [],
			'privileges' => [
				'ori.user.create',
				'ori.administration.entry',
				'ori.role',
				'app.darkMagic',
				'ori.user.edit',
			],
			'debugMode' => true,
			'application' => [
				'build' => [
					'version' => '0.1.0',
					'stable' => false,
					'name' => 'Surprisingly working',
				],
				'name' => 'Application name',
			],
			'rootDir' => '/path/to/website',
			'z_max' => [],
			'object' => new stdClass(),
		];

		$container = new Container([]);
		$command = new DIParametersCommand($container, false, $parameters);
		$tester = new CommandTester($command);

		$code = $tester->execute([]);

		self::assertSame(
			<<<'MSG'
  consoleMode: true
  debugMode: true
  appDir: /path/to/website/src
  rootDir: /path/to/website
  integer: 1234
  null: null
  object: stdClass

  application:
    name: Application name

    build:
      stable: false
      name: Surprisingly working
      version: 0.1.0

  arrAyy: []

  privileges:
    0: ori.user.create
    1: ori.administration.entry
    2: ori.role
    3: app.darkMagic
    4: ori.user.edit

  z_max: []

MSG,
			$tester->getDisplay(),
		);
		self::assertSame($command::SUCCESS, $code);

		$container2 = new Container($parameters);
		$command2 = new DIParametersCommand($container2, false, null);
		$tester2 = new CommandTester($command2);

		$code2 = $tester2->execute([]);

		self::assertSame($tester->getDisplay(), $tester2->getDisplay());
		self::assertSame($code, $code2);
	}

}
