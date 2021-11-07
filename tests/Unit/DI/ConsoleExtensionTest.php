<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\DI;

use Generator;
use Nette\DI\InvalidConfigurationException;
use Nette\Http\RequestFactory;
use OriNette\Console\Command\CommandsDebugCommand;
use OriNette\Console\Command\DIParametersCommand;
use OriNette\Console\DI\LazyCommandLoader;
use OriNette\Console\Http\ConsoleRequestFactory;
use OriNette\DI\Boot\ManualConfigurator;
use Orisai\Exceptions\Logic\InvalidState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as ComponentEventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcher;
use Tests\OriNette\Console\Doubles\AnotherDefaultNameCommand;
use Tests\OriNette\Console\Doubles\CustomApplication;
use Tests\OriNette\Console\Doubles\DefaultBothCommand;
use Tests\OriNette\Console\Doubles\DefaultNameCommand;
use Tests\OriNette\Console\Doubles\HiddenAndAliasedCommand;
use Tests\OriNette\Console\Doubles\SimpleCommand;
use function array_keys;
use function array_map;
use function assert;
use function dirname;
use function explode;
use function implode;
use function rtrim;
use const PHP_EOL;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

final class ConsoleExtensionTest extends TestCase
{

	public function testMinimal(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.minimal.neon');

		$container = $configurator->createContainer();

		self::assertFalse($container->isCreated('console.commandLoader'));

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);
		self::assertSame($application, $container->getService('console.application'));

		self::assertTrue($container->isCreated('console.commandLoader'));

		$commandLoader = $container->getService('console.commandLoader');
		self::assertInstanceOf(LazyCommandLoader::class, $commandLoader);

		self::assertSame('UNKNOWN', $application->getName());
		self::assertSame('UNKNOWN', $application->getVersion());
		self::assertFalse($application->isAutoExitEnabled());
		self::assertFalse($application->areExceptionsCaught());

		self::assertSame([], array_keys($application->all('tests')));

		$parametersCommand = $application->get('di:parameters');
		self::assertInstanceOf(LazyCommand::class, $parametersCommand);
		self::assertInstanceOf(DIParametersCommand::class, $parametersCommand->getCommand());
	}

	public function testConfigured(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.commands.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		self::assertSame('Name', $application->getName());
		self::assertSame('Version', $application->getVersion());
		self::assertTrue($application->areExceptionsCaught());

		self::assertSame([
			'help',
			'list',
			'boring-normie',
			'di:parameters',
			'commands-debug',
			'default',
			'both-default',
			'tagged',
			'another-default',
			'tagged-name:a',
			'tagged-name:b',
			'tagged-name:c',
			'unicorn',
			'AgathaChristie',
			'pizza',
			'defaults-neutralizer',
		], array_keys($application->all()));
	}

	/**
	 * @param class-string<Command> $class
	 * @param array<string>         $aliases
	 *
	 * @dataProvider provideCommandConfig
	 */
	public function testCommandConfig(
		string $service,
		string $class,
		string $name,
		string $description,
		array $aliases,
		bool $hidden
	): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.commands.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		$command = $application->get($name);

		self::assertSame($name, $command->getName());
		self::assertSame($description, $command->getDescription());
		self::assertSame($aliases, $command->getAliases());
		self::assertSame($hidden, $command->isHidden());

		if ($command instanceof LazyCommand) {
			$wrappedCommand = $command->getCommand();
			self::assertSame($command, $container->getService("console.lazy.$service"));
			self::assertInstanceOf($class, $wrappedCommand);
			self::assertSame($wrappedCommand, $container->getService($service));
		} else {
			self::assertInstanceOf($class, $command);
			self::assertSame($command, $container->getService($service));
		}
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideCommandConfig(): Generator
	{
		yield [
			'command.defaultName',
			DefaultNameCommand::class,
			'default',
			'',
			[],
			false,
		];

		yield [
			'command.bothDefault',
			DefaultBothCommand::class,
			'both-default',
			'Default description',
			[],
			false,
		];

		yield [
			'command.tagged',
			DefaultNameCommand::class,
			'tagged',
			'Fully tagged',
			[],
			false,
		];

		yield [
			'command.tagged.description',
			AnotherDefaultNameCommand::class,
			'another-default',
			'Custom description',
			[],
			false,
		];

		yield [
			'command.tagged.name.a',
			SimpleCommand::class,
			'tagged-name:a',
			'',
			[],
			false,
		];

		yield [
			'command.tagged.name.b',
			SimpleCommand::class,
			'tagged-name:b',
			'',
			[],
			false,
		];

		yield [
			'command.tagged.name.c',
			SimpleCommand::class,
			'tagged-name:c',
			'',
			[],
			false,
		];

		yield [
			'command.hidden',
			SimpleCommand::class,
			'unicorn',
			'I am hiding so people can\'t hang me on their wall.',
			[],
			true,
		];

		yield [
			'command.aliased',
			SimpleCommand::class,
			'AgathaChristie',
			'Woman of many names.',
			['MaryWestmacott', 'AgathaMaryClarissaMiller'],
			false,
		];

		yield [
			'command.hiddenAndAliased',
			SimpleCommand::class,
			'pizza',
			'Hidden and aliased. Also hungry.',
			['kebab', 'quesadilla'],
			true,
		];

		yield [
			'command.hiddenAndAliased.negated',
			HiddenAndAliasedCommand::class,
			'defaults-neutralizer',
			'',
			[],
			false,
		];

		yield [
			'command.notLazy',
			Command::class,
			'boring-normie',
			'Description does not make me lazy enough.',
			[],
			false,
		];
	}

	public function testTagDiscovery(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.tagDiscovery.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		self::assertSame([
			'help',
			'list',
			'di:parameters:renamed',
			'commands-debug',
			'tagged',
			'both-default',
		], array_keys($application->all()));
	}

	public function testTagDiscoveryInternalCommands(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.tagDiscovery.internalCommands.neon');

		$container = $configurator->createContainer();

		$application = $container->getService('console.application');
		self::assertInstanceOf(Application::class, $application);

		self::assertSame([
			'help',
			'list',
			'di:parameters',
			'commands-debug',
			'tagged',
			'both-default',
		], array_keys($application->all()));

		$defaultApplication = $container->getService('defaultTagConsole.application');
		self::assertInstanceOf(Application::class, $defaultApplication);

		self::assertSame([
			'help',
			'list',
			'default-di-params',
			'default-commands-debug',
			'default',
			'both-default',
		], array_keys($defaultApplication->all()));
	}

	public function testEventDispatcher(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.eventDispatcher.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		$dispatcher = $container->getByType(ContractsEventDispatcher::class);
		assert($dispatcher instanceof ComponentEventDispatcher);

		$command = null;
		$dispatcher->addListener(
			ConsoleEvents::COMMAND,
			static function (ConsoleCommandEvent $event) use (&$command): void {
				$command = $event->getCommand();
			},
		);

		$application->run(
			new ArrayInput(['command' => DefaultNameCommand::getDefaultName()]),
			new NullOutput(),
		);

		self::assertInstanceOf(DefaultNameCommand::class, $command);
	}

	public function testMultipleConsoles(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.multiple.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);
		self::assertSame($application, $container->getService('console.application'));

		$customApplication = $container->getByType(CustomApplication::class);
		self::assertInstanceOf(CustomApplication::class, $customApplication);
		self::assertSame($customApplication, $container->getService('customConsole.application'));
	}

	public function testHttpNoService(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.http.noService.neon');

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Option 'console > http > override' is enabled.
Problem: Service of type 'Nette\Http\RequestFactory' not found.
Solution: Register extension 'Nette\Bridges\HttpDI\HttpExtension' or
          'RequestFactory' service.
MSG);

		$configurator->createContainer();
	}

	public function testHttpBadUrl(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.http.badUrl.neon');

		$this->expectException(InvalidConfigurationException::class);
		$this->expectExceptionMessage(
			"Failed assertion 'has to be valid URL' for item 'console › http › url' with value 'orisai.dev'.",
		);

		$configurator->createContainer();
	}

	public function testHttpConfigUrl(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.http.neon');

		$container = $configurator->createContainer();

		$requestFactory = $container->getByType(RequestFactory::class);
		self::assertInstanceOf(ConsoleRequestFactory::class, $requestFactory);

		$request = $requestFactory->fromGlobals();
		self::assertSame('https://orisai.dev/', $request->getUrl()->getAbsoluteUrl());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testHttpArgvUrl(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.http.noUrl.neon');

		$_SERVER['argv'] = [
			'foo',
			'--ori-url=https://example.com',
		];

		$container = $configurator->createContainer();

		$requestFactory = $container->getByType(RequestFactory::class);
		self::assertInstanceOf(ConsoleRequestFactory::class, $requestFactory);

		$request = $requestFactory->fromGlobals();
		self::assertSame('https://example.com/', $request->getUrl()->getAbsoluteUrl());
	}

	public function testParametersBackupCustomRemoved(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.parametersBackup.customRemoved.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);

		$command = $application->get(DIParametersCommand::getDefaultName());
		self::assertInstanceOf(LazyCommand::class, $command);
		self::assertInstanceOf(DIParametersCommand::class, $command->getCommand());
	}

	public function testCommandsDebugCommand(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.commands.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);

		$command = $application->get(CommandsDebugCommand::getDefaultName());
		self::assertInstanceOf(LazyCommand::class, $command);
		self::assertInstanceOf(CommandsDebugCommand::class, $command->getCommand());

		$tester = new CommandTester($command);

		$code = $tester->execute([]);

		$expected = PHP_MAJOR_VERSION >= 8 && PHP_MINOR_VERSION >= 1 ? <<<'MSG'
Following commands are missing ❌ either name or description. Check orisai/nette-console documentation about lazy loading to learn how to fix it.
 ---------------------------------- ------ -------------
  Service                            Name   Description
  command.defaultName                ✔️     ❌
  command.tagged.name.a              ✔️     ❌
  command.tagged.name.b              ✔️     ❌
  command.tagged.name.c              ✔️     ❌
  command.hiddenAndAliased.negated   ✔️     ❌
  command.notLazy                    ❌     ✔️
 ---------------------------------- ------ -------------

MSG : <<<'MSG'
Following commands are missing ❌ either name or description. Check orisai/nette-console documentation about lazy loading to learn how to fix it.
 ---------------------------------- ------ -------------
  Service                            Name   Description
  command.defaultName                ✔️     ❌
  command.tagged.name.a              ✔️     ❌
  command.tagged.name.b              ✔️     ❌
  command.tagged.name.c              ✔️     ❌
  command.hiddenAndAliased.negated   ✔️     ❌
  command.notLazy                    ❌      ✔️
 ---------------------------------- ------ -------------

MSG;

		self::assertEquals(
			$expected,
			implode(
				PHP_EOL,
				array_map(
					static fn (string $s): string => rtrim($s),
					explode(PHP_EOL, $tester->getDisplay()),
				),
			),
		);
		self::assertSame($command::FAILURE, $code);
	}

}
