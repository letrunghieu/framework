<?php

namespace Illuminate\Translation\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;

class TranslatorCollectCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'trans:collect';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Collect all translation keys to current language';

	/**
	 * The filesystem instance
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * The blade compiler instance
	 *
	 * @var \Illuminate\View\Compilers\BladeCompiler
	 */
	protected $compiler;

	/**
	 * The translator instance
	 *
	 * @var \Illuminate\Translation\Translator
	 */
	protected $translator;

	/**
	 * Current application locale
	 *
	 * @var string
	 */
	protected $locale;

	/**
	 * Collections of missed localization keys
	 *
	 * @var \Illuminate\Support\Collection
	 */
	protected $missedKeys;

	/**
	 * List of localization commands
	 *
	 * @var array
	 */
	protected $functions = [
		'trans' => 'function',
		'trans_choice' => 'function',
		'get' => 'method',
		'choice' => 'method',
	];

	/**
	 * List of methods of Translator
	 *
	 * @var array
	 */
	protected $methods = [
		'\\Illuminate\\Support\\Facades\\Lang::get',
		'\\Illuminate\\Support\\Facades\\Lang::choice'
	];

	/**
	 * Create a instance.
	 * 
	 * @param Application $laravel current laravel application instance
	 *
	 * @return void
	 */
	public function __construct(Application $laravel)
	{
		parent::__construct();
		$this->setLaravel($laravel);
		$this->files = $this->getFileSystem();
		$this->translator = $this->getTranslator();
		$this->compiler = $this->getBladeCompiler();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->locale = $this->getCurrentLocale();
		$this->missedKeys = [];

		$scanFolders = [
			'controllers', 'views'
		];

		foreach ($scanFolders as $folder)
		{
			$this->getLocalizationStuffs($this->laravel['path'] . '/' . $folder);
		}

		foreach ($this->missedKeys as $group => $content)
		{
			$this->updateLocalizationKeys($group, $content);
		}
	}

	/**
	 * Get current locale of the application
	 * 
	 * @return string current locale
	 * @throws \RuntimeException there is no locale specified in the application
	 */
	protected function getCurrentLocale()
	{
		if ($this->laravel['config']['app.locale'])
		{
			return $this->laravel['config']['app.locale'];
		} else
		{
			throw new \RuntimeException('There is no locale specified in this application.');
		}
	}

	/**
	 * Get the current translator registered in the application
	 * 
	 * @return \Illuminate\Translation\Translator current translator
	 * @throws \RuntimeException no translator is registered in the application
	 */
	protected function getTranslator()
	{
		if ($this->laravel['translator'])
		{
			return $this->laravel['translator'];
		} else
		{
			throw new \RuntimeException('There is no translator registered.');
		}
	}

	/**
	 * Get the current file system registered in the application
	 * 
	 * @return \Illuminate\Filesystem\Filesystem the registered file system
	 * @throws \RuntimeException there is no file system registered in the application
	 */
	protected function getFileSystem()
	{
		if ($this->laravel['files'])
		{
			return $this->laravel['files'];
		} else
		{
			throw new \RuntimeException('There is no file system registered.');
		}
	}

	/**
	 * Get the current blade compiler registered in the application
	 * 
	 * @return \Illuminate\View\Compilers\BladeCompiler
	 * @throws \RuntimeException no blade compiler registered in the application
	 */
	protected function getBladeCompiler()
	{
		if ($this->laravel['blade.compiler'])
		{
			return $this->laravel['blade.compiler'];
		} else
		{
			throw new \RuntimeException('There is no blade compiler registered.');
		}
	}

	/**
	 * Collect all localization keys in files in a folder
	 * 
	 * @param string $folder
	 * 
	 * @return void
	 */
	protected function getLocalizationStuffs($folder)
	{
		$files = $this->files->allFiles($folder);
		foreach ($files as $file)
		{
			/* @var $file \Symfony\Component\Finder\SplFileInfo */
			if ($file->getExtension() == 'php')
			{
				$this->parseFile($file->getRealPath());
			}
		}
	}

	/**
	 * Parse a file to get localization keys
	 * 
	 * @param string $filepath
	 * 
	 * @return void
	 */
	protected function parseFile($filepath)
	{
		$fileContent = $this->files->get($filepath);

		$compiledContent = $this->compiler->compileString($fileContent);

		$this->parseContent($compiledContent);
	}

	/**
	 * Parse the string with tokenization
	 * 
	 * @param string $content
	 * 
	 * @return void
	 */
	protected function parseContent($content)
	{
		$tokens = token_get_all($content);
		$count = count($tokens);
		$functions = [];
		$bufferFunctions = [];

		for ($i = 0; $i < $count; $i++)
		{
			$value = $tokens[$i];
			if (is_string($value))
			{
				if ($value == ')' && isset($bufferFunctions[0]))
				{
					$functions[] = array_shift($bufferFunctions);
				}

				continue;
			}

			if (isset($bufferFunctions[0]) && ($value[0] === T_CONSTANT_ENCAPSED_STRING))
			{
				$val = $value[1];
				if ($val[0] === '"')
				{
					$val = str_replace('\\"', '"', $val);
				} else
				{
					$val = str_replace("\\'", "'", $val);
				}

				$bufferFunctions[0][] = substr($val, 1, -1);

				continue;
			}

			if (($value[0] === T_STRING) && is_string($tokens[$i + 1]) && ($tokens[$i + 1] === '('))
			{
				array_unshift($bufferFunctions, [$value[1], $value[2], $i]);
				$i++;

				continue;
			}
		}

		foreach ($functions as $func)
		{
			$funtion = array_shift($func);

			if (!isset($this->functions[$funtion]))
			{
				continue;
			}

			array_shift($func);

			$tokenIndex = array_shift($func);

			if ($this->functions[$funtion] === 'method')
			{
				$fromTokenIndex = $tokenIndex - 10;
				if (!isset($tokens[$fromTokenIndex]) || !is_array($tokens[$fromTokenIndex]) || ($tokens[$fromTokenIndex][0] !== T_WHITESPACE))
				{
					// If the previous 10th token is not a space
					continue;
				} else if (!$this->isTranslatorCall($tokens, $fromTokenIndex, $tokenIndex))
				{
					// If the string made from last 10 tokens is not a method in  the Translator
					continue;
				}
			}

			$key = $func[0];

			if (!$this->translator->has($key, $this->locale))
			{
				if (strpos($key, '.') > 1)
				{
					array_set($this->missedKeys, $key, '');
				}
			}
		}
	}

	/**
	 * Is this token a method from Translator
	 * 
	 * @param array $tokens  list of original tokens
	 * @param int $fromToken the index of first token 
	 * @param int $toToken   the index of last tokan
	 * 
	 * @return boolean
	 */
	protected function isTranslatorCall($tokens, $fromToken, $toToken)
	{
		$result = "";

		for ($i = $fromToken; $i <= $toToken; $i++)
		{
			if (is_string($tokens[$i]))
			{
				$result .= $tokens[$i];
			} else
			{
				$result .= $tokens[$i][1];
			}
		}
		return in_array(trim($result), $this->methods);
	}

	protected function updateLocalizationKeys($group, &$newKeys)
	{
		$currentKeys = $this->translator->getLoader()->load($this->locale, $group);
		$mergedKeys = array_merge_recursive_distinct($currentKeys, $newKeys);

		$langPath = $this->translator->getLoader()->getPath();
		$folder = "{$langPath}/{$this->locale}";
		$file = "{$folder}/{$group}.php";

		if (!$this->files->exists($folder))
		{
			$this->files->makeDirectory($folder, 0755, true);
		}

		$fileContent = "<?php" . PHP_EOL . "return " . var_export($mergedKeys, true) . ";" . PHP_EOL;
		$this->files->put($file, $fileContent);

		$this->info("File: '{$file}' updated.");
	}
	
}
