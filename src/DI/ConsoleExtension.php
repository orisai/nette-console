<?php declare(strict_types = 1);

namespace OriNette\Console\DI;

use Nette\Bridges\HttpDI\HttpExtension;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Extensions\DIExtension;
use Nette\DI\MissingServiceException;
use Nette\Http\RequestFactory;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Validators;
use OriNette\Console\Command\CommandsDebugCommand;
use OriNette\Console\Command\DIParametersCommand;
use OriNette\Console\Http\ConsoleRequestFactory;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use Orisai\Utils\Reflection\Classes;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function array_keys;
use function array_map;
use function array_shift;
use function assert;
use function explode;
use function is_a;
use function is_array;
use function is_string;
use function str_starts_with;
use function strtolower;

/**
 * @property-read stdClass $config
 */
final class ConsoleExtension extends CompilerExtension
{

	public const DefaultCommandTag = 'console.command';

	/** @var array<mixed> */
	private array $parameters;

	private ServiceDefinition $applicationDefinition;

	private ServiceDefinition $commandLoaderDefinition;

	private ServiceDefinition $diParametersCommandDefinition;

	private ServiceDefinition $commandsDebugCommandDefinition;

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'autowired' => Expect::string(Application::class),
			'catchExceptions' => Expect::bool(false),
			'name' => Expect::anyOf(
				Expect::string(),
				Expect::null(),
			)->default(null),
			'version' => Expect::anyOf(
				Expect::string(),
				Expect::int(),
				Expect::float(),
				Expect::null(),
			)->default(null),
			'di' => Expect::structure([
				'parameters' => Expect::structure([
					'backup' => Expect::bool(false),
				]),
			]),
			'discovery' => Expect::structure([
				'tag' => Expect::anyOf(
					Expect::string(),
					Expect::null(),
				)->default(null),
			]),
			'http' => Expect::structure([
				'override' => Expect::bool(false),
				'url' => Expect::anyOf(
					Expect::string(),
					Expect::null(),
				)->default(null)->assert(
					static fn (?string $url): bool => $url === null || Validators::isUrl($url),
					'has to be valid URL',
				),
				'headers' => Expect::arrayOf(
					Expect::anyOf(Expect::string(), Expect::null()),
					Expect::string(),
				)->default([
					'user-agent' => 'orisai/nette-console',
				]),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

		$this->registerApplication($this->registerCommandLoader($builder), $config, $builder);
		$this->registerDIParametersCommand($config, $builder);
		$this->registerCommandsDebugCommand($config, $builder);
	}

	private function registerCommandLoader(ContainerBuilder $builder): ServiceDefinition
	{
		return $this->commandLoaderDefinition = $builder->addDefinition($this->prefix('commandLoader'))
			->setFactory(LazyCommandLoader::class)
			->setType(CommandLoaderInterface::class)
			->setAutowired(false);
	}

	private function registerApplication(
		ServiceDefinition $commandLoaderDefinition,
		stdClass $config,
		ContainerBuilder $builder
	): void
	{
		$this->applicationDefinition = $applicationDefinition = $builder->addDefinition($this->prefix('application'))
			->setFactory($config->autowired)
			->setAutowired($config->autowired)
			->addSetup('setAutoExit', [false])
			->addSetup('setCatchExceptions', [$config->catchExceptions])
			->addSetup('setCommandLoader', [$commandLoaderDefinition]);

		if ($config->name !== null) {
			$applicationDefinition->addSetup('setName', [$config->name]);
		}

		if ($config->version !== null) {
			$applicationDefinition->addSetup('setVersion', [$config->version]);
		}

		$this->compiler->addExportedType($config->autowired);
	}

	private function registerDIParametersCommand(stdClass $config, ContainerBuilder $builder): void
	{
		$this->parameters = $builder->parameters;

		$this->diParametersCommandDefinition = $builder->addDefinition($this->prefix('command.diParameters'))
			->setFactory(DIParametersCommand::class)
			->addTag($config->discovery->tag ?? self::DefaultCommandTag, []);
	}

	private function registerCommandsDebugCommand(stdClass $config, ContainerBuilder $builder): void
	{
		$this->commandsDebugCommandDefinition = $builder->addDefinition($this->prefix('command.commandsDebug'))
			->setFactory(CommandsDebugCommand::class)
			->addTag($config->discovery->tag ?? self::DefaultCommandTag, []);
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

		$commandLoaderDefinition = $this->commandLoaderDefinition;
		$applicationDefinition = $this->applicationDefinition;

		$this->addCommandsToApplication($commandLoaderDefinition, $applicationDefinition, $config, $builder);
		$this->configureDIParametersCommand($config, $builder);
		$this->setDispatcher($applicationDefinition, $builder);
		$this->configureHttpRequest($applicationDefinition, $config, $builder);
	}

	private function addCommandsToApplication(
		ServiceDefinition $commandLoaderDefinition,
		ServiceDefinition $applicationDefinition,
		stdClass $config,
		ContainerBuilder $builder
	): void
	{
		$tagName = $config->discovery->tag;
		$commandDefinitions = $this->findCommandDefinitions($tagName, $builder);

		$commandsMap = [];
		$notLazyCommands = [];
		foreach ($commandDefinitions as $commandDefinition) {
			assert($commandDefinition instanceof ServiceDefinition);

			if ($this->isCommandFromAnotherConsole($commandDefinition)) {
				continue;
			}

			$commandConfig = $this->configureCommand(
				$commandDefinition,
				$builder,
				$tagName ?? self::DefaultCommandTag,
			);

			$processedCommandDefinition = $commandConfig[0];
			$commandName = $commandConfig[1];
			$commandDescription = $commandConfig[2];

			if ($commandName !== null) {
				$commandsMap[$commandName] = $processedCommandDefinition->getName();
			} else {
				$applicationDefinition->addSetup('add', [$processedCommandDefinition]);
			}

			if ($commandName === null || $commandDescription === null) {
				$notLazyCommands[] = [$commandDefinition->getName(), $commandName !== null, $commandDescription !== null];
			}
		}

		$commandLoaderDefinition->setArguments([$commandsMap]);

		$this->commandsDebugCommandDefinition->setArguments([
			'commands' => $notLazyCommands,
		]);
	}

	/**
	 * @return array<Definition>
	 */
	private function findCommandDefinitions(?string $tagName, ContainerBuilder $builder): array
	{
		return $tagName === null
			? $builder->findByType(Command::class)
			: array_map(
				static fn (string $name): Definition => $builder->getDefinition($name),
				array_keys($builder->findByTag($tagName)),
			);
	}

	private function isCommandFromAnotherConsole(ServiceDefinition $definition): bool
	{
		$definitionName = $definition->getName();
		assert(is_string($definitionName));

		foreach ($this->compiler->getExtensions(self::class) as $extension) {

			if ($extension->name !== $this->name
				&& (str_starts_with($definitionName, "$extension->name.lazy.")
					|| str_starts_with($definitionName, "$extension->name.command."))
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array{ServiceDefinition, string|null, string|null}
	 */
	private function configureCommand(ServiceDefinition $definition, ContainerBuilder $builder, string $tagName): array
	{
		[$name, $description] = $this->getCommandMeta($definition, $tagName);
		[$newDefinition, $name] = $this->processCommandDefinition($definition, $name, $description, $builder);

		return [$newDefinition, $name, $description];
	}

	/**
	 * @return array{string|null, string|null}
	 */
	private function getCommandMeta(ServiceDefinition $definition, string $tagName): array
	{
		$name = null;
		$description = null;

		// From factory - service definition has `factory` set
		$factory = $definition->getFactory()->entity;
		if (is_string($factory) && is_a($factory, Command::class, true)) {
			$commandName = $factory::getDefaultName();
			if ($commandName !== null) {
				$name = $commandName;
			}

			$commandDescription = $factory::getDefaultDescription();
			if ($commandDescription !== null) {
				$description = $commandDescription;
			}
		}

		// From type - service definition has `type` set
		$type = $definition->getType();
		if (is_string($type) && is_a($type, Command::class, true)) {
			$commandName = $type::getDefaultName();
			if ($commandName !== null) {
				$name = $commandName;
			}

			$commandDescription = $type::getDefaultDescription();
			if ($commandDescription !== null) {
				$description = $commandDescription;
			}
		}

		// From tag - service tag is set
		$tag = $definition->getTag($tagName);
		if (is_string($tag)) {
			$name = $tag;
		}

		if (is_array($tag)) {
			$tagDescription = $tag['description'] ?? null;
			if (is_string($tagDescription)) {
				$description = $tagDescription;
			}

			// symfony/console compatibility
			$tagName = $tag['command'] ?? null;
			if (is_string($tagName)) {
				$name = $tagName;
			}

			// other nette/di implementations compatibility
			$tagName = $tag['name'] ?? null;
			if (is_string($tagName)) {
				$name = $tagName;
			}
		}

		return [$name, $description];
	}

	/**
	 * @return array{ServiceDefinition, string|null}
	 */
	private function processCommandDefinition(
		ServiceDefinition $definition,
		?string $name,
		?string $description,
		ContainerBuilder $builder
	): array
	{
		$aliases = null;
		$hidden = null;

		if ($name !== null) {
			$aliases = explode('|', $name);

			$name = array_shift($aliases);
			if ($name === '') {
				$hidden = true;
				$name = array_shift($aliases);
			} else {
				$hidden = false;
			}
		}

		if ($name !== null && $description !== null) {
			$newDefinition = $builder->addDefinition("$this->name.lazy.{$definition->getName()}")
				->setFactory(LazyCommand::class, [
					$name,
					$aliases,
					$description,
					$hidden,
					new PhpLiteral('fn(): ? => $this->getService(?)', [
						new PhpLiteral(Command::class),
						$definition->getName(),
					]),
				]);

			return [$newDefinition, $name];
		}

		if ($name !== null) {
			$definition->addSetup('setName', [$name]);
		}

		if ($description !== null) {
			$definition->addSetup('setDescription', [$description]);
		}

		if ($hidden !== null) {
			$definition->addSetup('setHidden', [$hidden]);
		}

		if ($aliases !== null) {
			$definition->addSetup('setAliases', [$aliases]);
		}

		return [$definition, $name];
	}

	private function configureDIParametersCommand(stdClass $config, ContainerBuilder $builder): void
	{
		$commandDefinition = $this->diParametersCommandDefinition;

		$exportIsDisabled = $this->isParametersExportDisabled($builder);
		$backup = $config->di->parameters->backup === true;

		$commandDefinition->setArgument('exportHint', $exportIsDisabled && !$backup);

		if ($exportIsDisabled && $backup) {
			$commandDefinition->setArgument('parameters', $this->parameters);
		}
	}

	private function isParametersExportDisabled(ContainerBuilder $builder): bool
	{
		if ($this->parameters !== []) {
			if ($builder->parameters === []) {
				return true;
			}

			foreach ($this->compiler->getExtensions(DIExtension::class) as $extension) {
				$extensionConfig = (object) $extension->config;
				if (!$extensionConfig->export->parameters) {
					return true;
				}
			}
		}

		return false;
	}

	private function setDispatcher(ServiceDefinition $applicationDefinition, ContainerBuilder $builder): void
	{
		$dispatcherName = $builder->getByType(EventDispatcherInterface::class);

		if ($dispatcherName === null) {
			return;
		}

		$applicationDefinition->addSetup('setDispatcher', [
			$builder->getDefinition($dispatcherName),
		]);
	}

	private function configureHttpRequest(
		ServiceDefinition $applicationDefinition,
		stdClass $config,
		ContainerBuilder $builder
	): void
	{
		$httpConfig = $config->http;

		if (!$httpConfig->override) {
			return;
		}

		$factoryClass = RequestFactory::class;
		try {
			$requestFactoryDefinition = $builder->getDefinitionByType($factoryClass);
		} catch (MissingServiceException $exception) {
			$factoryClassShort = Classes::getShortName($factoryClass);
			$httpExtensionClass = HttpExtension::class;
			$message = Message::create()
				->withContext("Option '$this->name > http > override' is enabled.")
				->withProblem("Service of type '$factoryClass' not found.")
				->withSolution("Register extension '$httpExtensionClass' or '$factoryClassShort' service.");

			throw InvalidState::create()
				->withMessage($message);
		}

		$optionName = '--ori-url';

		assert($requestFactoryDefinition instanceof ServiceDefinition);
		$requestFactoryDefinition->setFactory(ConsoleRequestFactory::class, [
			'url' => $httpConfig->url,
			'argvOptionName' => $optionName,
			'configOptionName' => "$this->name > http > url",
		]);

		foreach ($httpConfig->headers as $name => $value) {
			$lowerName = strtolower($name);
			if ($name !== $lowerName) {
				$message = Message::create()
					->withContext("Incorrect case of config key '$this->name > http > headers > $name'.")
					->withProblem('Only lowercase header names are supported.')
					->withSolution("Use '$lowerName' instead of '$name'.");

				throw InvalidArgument::create()
					->withMessage($message);
			}

			if ($value === null) {
				continue;
			}

			$requestFactoryDefinition->addSetup('addHeader', [
				$name,
				$value,
			]);
		}

		$applicationDefinition->addSetup(
			'getDefinition()->addArgument(?)',
			[
				new Statement(InputArgument::class, [
					$optionName,
					InputArgument::REQUIRED,
					'URL address of simulated HTTP request',
				]),
			],
		);
	}

}
