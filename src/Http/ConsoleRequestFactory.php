<?php declare(strict_types = 1);

namespace OriNette\Console\Http;

use Nette\Http\Request;
use Nette\Http\RequestFactory;
use Nette\Http\UrlScript;
use Nette\Utils\Validators;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
final class ConsoleRequestFactory extends RequestFactory
{

	private ?string $url;

	private string $argvOptionName;

	private string $configOptionName;

	public function __construct(?string $url, string $argvOptionName, string $configOptionName)
	{
		$this->url = $url;
		$this->argvOptionName = $argvOptionName;
		$this->configOptionName = $configOptionName;
	}

	public function fromGlobals(): Request
	{
		return new Request(
			new UrlScript($this->getUrl()),
		);
	}

	private function getUrl(): string
	{
		$argv = new ArgvInput();
		if ($argv->hasParameterOption($this->argvOptionName)) {
			$url = $argv->getParameterOption($this->argvOptionName, null);

			if ($url !== null) {
				if (!Validators::isUrl($url)) {
					throw InvalidArgument::create()
						->withMessage("Command option '$this->argvOptionName' has to be valid URL, '$url' given.");
				}

				return $url;
			}
		}

		if ($this->url !== null) {
			return $this->url;
		}

		$message = Message::create()
			->withContext('Trying to create HTTP request.')
			->withProblem('Request factory for console mode is used and no URL was provided.')
			->withSolution(
				"Specify URL either via '$this->configOptionName' extension option or via " .
				"'$this->argvOptionName' command option.",
			);

		throw InvalidState::create()
			->withMessage($message);
	}

}
