<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\Http;

use OriNette\Console\Http\ConsoleRequestFactory;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use PHPUnit\Framework\TestCase;

final class ConsoleRequestFactoryTest extends TestCase
{

	public function testNoUrl(): void
	{
		$requestFactory = $this->createFactory(null);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to create HTTP request.
Problem: Request factory for console mode is used and no URL was provided.
Solution: Specify URL either via 'console > http > url' extension option or via
          '--ori-url' command option.
MSG);

		$requestFactory->fromGlobals();
	}

	public function testConfigUrl(): void
	{
		$requestFactory = $this->createFactory('https://orisai.dev');

		$request = $requestFactory->fromGlobals();
		self::assertSame('https://orisai.dev/', $request->getUrl()->getAbsoluteUrl());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testArgvUrl(): void
	{
		$requestFactory = $this->createFactory(null);

		$_SERVER['argv'] = [
			'foo',
			'--ori-url=https://example.com',
		];

		$request = $requestFactory->fromGlobals();
		self::assertSame('https://example.com/', $request->getUrl()->getAbsoluteUrl());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testArgvUrlPriority(): void
	{
		$requestFactory = $this->createFactory('https://orisai.dev');

		$_SERVER['argv'] = [
			'foo',
			'--ori-url=https://bartos.dev',
		];

		$request = $requestFactory->fromGlobals();
		self::assertSame('https://bartos.dev/', $request->getUrl()->getAbsoluteUrl());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testInvalidArgvUrl(): void
	{
		$requestFactory = $this->createFactory('https://orisai.dev');

		$_SERVER['argv'] = [
			'foo',
			'--ori-url=bartos.dev',
		];

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage("Command option '--ori-url' has to be valid URL, 'bartos.dev' given.");

		$requestFactory->fromGlobals();
	}

	public function testHeaders(): void
	{
		$requestFactory = $this->createFactory('https://orisai.dev');

		$requestFactory->addHeader('User-Agent', 'overridden');
		$requestFactory->addHeader('user-agent', 'orisai/nette-console');
		$requestFactory->addHeader('custom', 'custom');

		$request = $requestFactory->fromGlobals();
		self::assertSame(
			[
				'user-agent' => 'orisai/nette-console',
				'custom' => 'custom',
			],
			$request->getHeaders(),
		);
	}

	private function createFactory(?string $url): ConsoleRequestFactory
	{
		return new ConsoleRequestFactory($url, '--ori-url', 'console > http > url');
	}

}
