# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/orisai/nette-console/compare/1.4.2...HEAD)

## [1.4.2](https://github.com/orisai/nette-console/compare/1.4.1...1.4.2) - 2024-03-12

### Fixed

- Compatibility with `nette/http:3.3.0`

## [1.4.1](https://github.com/orisai/nette-console/compare/1.4.0...1.4.1) - 2023-10-18

### Fixed

- `ConsoleExtension` - `name` and `version` - fix dynamic parameters for older nette/di and optimize setting non-dynamic parameters.

## [1.4.0](https://github.com/orisai/nette-console/compare/1.3.1...1.4.0) - 2023-10-14

### Added

- `ConsoleExtension`
	- `name` - allows dynamic parameter
	- `version` - allows dynamic parameter

## [1.3.1](https://github.com/orisai/nette-console/compare/1.3.0...1.3.1) - 2023-08-19

### Fixed

- `--ori-url` is added as an option instead of an argument (it broke commands using arguments)

## [1.3.0](https://github.com/orisai/nette-console/compare/1.2.4...1.3.0) - 2023-06-19

### Added

- `ConsoleExtension`
	- `http > url` - allows dynamic parameter
- `commands-debug` command
	- type (class name)
	- redesign

## [1.2.4](https://github.com/orisai/nette-console/compare/1.2.3...1.2.4) - 2023-06-18

### Fixed

- Base URL given via `--ori-url` command parameter

## [1.2.3](https://github.com/orisai/nette-console/compare/1.2.2...1.2.3) - 2023-03-13

### Changed

- Composer
	- allows nette/utils ^4.0.0

## [1.2.2](https://github.com/orisai/nette-console/compare/1.2.1...1.2.2) - 2022-12-22

### Changed

- Composer
  - allows nette/php-generator ^4.0.0

## [1.2.1](https://github.com/orisai/nette-console/compare/1.2.0...1.2.1) - 2022-12-09

### Changed

- Composer
	- allows PHP 8.2

## [1.2.0](https://github.com/orisai/nette-console/compare/1.1.2...1.2.0) - 2022-10-17

### Added

- `ConsoleExtension`
  - `http > headers` option

## [1.1.2](https://github.com/orisai/nette-console/compare/1.1.1...1.1.2) - 2022-05-06

### Changed

- `ConsoleExtension`
  - `DEFAULT_COMMAND_TAG` -> `DefaultCommandTag` (is it even a BC break when nobody notices?)

## [1.1.1](https://github.com/orisai/nette-console/compare/1.1.0...1.1.1) - 2021-11-07

### Added

- *symfony/console ^6.0.0* and *symfony/event-dispatcher ^6.0.0* support

## [1.1.0](https://github.com/orisai/nette-console/compare/1.0.0...1.1.0) - 2021-11-07

### Added

- `ConsoleExtension`
  - `discovery > tag` option
  - `autowired` option

## [1.0.0](https://github.com/orisai/nette-console/releases/tag/1.0.0) - 2021-10-31

### Added

- `ConsoleExtension`
    - `name`, `version`, `catchExceptions` options
    - no-config commands lazy-loading
    - commands tag overloading
    - nette/http integration
    - symfony/event-dispatcher-contracts integration
- Commands
    - `commands-debug`
    - `di:parameters`
