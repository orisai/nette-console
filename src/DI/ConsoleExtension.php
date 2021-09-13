<?php declare(strict_types = 1);

namespace OriNette\Console\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Extensions\DIExtension;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\Console\Command\DIParametersCommand;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function array_shift;
use function assert;
use function explode;
use function is_a;
use function is_array;
use function is_string;

/**
 * @property-read stdClass $config
 */
final class ConsoleExtension extends CompilerExtension
{

	public const COMMAND_TAG = 'console.command';

	/** @var array<mixed> */
	private array $parameters;

	private ServiceDefinition $applicationDefinition;

	private ServiceDefinition $commandLoaderDefinition;

	private ServiceDefinition $diParametersCommandDefinition;

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
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
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

		$this->registerApplication($this->registerCommandLoader($builder), $config, $builder);
		$this->registerDIParametersCommand($builder);
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
			->setFactory(Application::class)
			->setType(Application::class)
			->addSetup('setAutoExit', [false])
			->addSetup('setCatchExceptions', [$config->catchExceptions])
			->addSetup('setCommandLoader', [$commandLoaderDefinition]);

		if ($config->name !== null) {
			$applicationDefinition->addSetup('setName', [$config->name]);
		}

		if ($config->version !== null) {
			$applicationDefinition->addSetup('setVersion', [$config->version]);
		}

		$this->compiler->addExportedType(Application::class);
	}

	private function registerDIParametersCommand(ContainerBuilder $builder): void
	{
		$this->parameters = $builder->parameters;

		$this->diParametersCommandDefinition = $builder->addDefinition($this->prefix('command.diParameters'))
			->setFactory(DIParametersCommand::class);
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

		$commandLoaderDefinition = $this->commandLoaderDefinition;
		$applicationDefinition = $this->applicationDefinition;

		$this->addCommandsToApplication($commandLoaderDefinition, $applicationDefinition, $builder);
		$this->configureDIParametersCommand($config, $builder);
		$this->setDispatcher($applicationDefinition, $builder);
	}

	private function addCommandsToApplication(
		ServiceDefinition $commandLoaderDefinition,
		ServiceDefinition $applicationDefinition,
		ContainerBuilder $builder
	): void
	{
		$commandDefinitions = $builder->findByType(Command::class);
		$commandsMap = [];
		foreach ($commandDefinitions as $commandDefinition) {
			assert($commandDefinition instanceof ServiceDefinition);

			$commandConfig = $this->configureCommand($commandDefinition, $builder);
			$processedCommandDefinition = $commandConfig[0];
			$commandName = $commandConfig[1];

			if ($commandName !== null) {
				$commandsMap[$commandName] = $processedCommandDefinition->getName();
			} else {
				$applicationDefinition->addSetup('add', [$processedCommandDefinition]);
			}
		}

		$commandLoaderDefinition->getFactory()->arguments = [$commandsMap];
	}

	/**
	 * @return array{ServiceDefinition, string|null}
	 */
	private function configureCommand(ServiceDefinition $definition, ContainerBuilder $builder): array
	{
		[$name, $description] = $this->getCommandMeta($definition);
		[$newDefinition, $name] = $this->processCommandDefinition($definition, $name, $description, $builder);

		return [$newDefinition, $name];
	}

	/**
	 * @return array{string|null, string|null}
	 */
	private function getCommandMeta(ServiceDefinition $definition): array
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
		$tag = $definition->getTag(self::COMMAND_TAG);
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

}
