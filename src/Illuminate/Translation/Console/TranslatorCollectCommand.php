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
		'trans' => 'trans',
		'choice' => 'choice',
		'\\Illuminate\\Support\\Facades\\Lang::get' => 'trans',
		'\\Illuminate\\Support\\Facades\\Lang::choice' => 'choice',
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
		$this->missedKeys = new \Illuminate\Support\Collection();

		$scanFolders = [
			'controllers', 'views'
		];

		foreach ($scanFolders as $folder)
		{
			$this->getLocalizationStuffs($this->laravel['path'] . '/' . $folder);
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
			if ($this->files->extension($file) == 'php')
			{
				$this->parseFile($file);
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
				array_unshift($bufferFunctions, array($value[1], $value[2]));
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

			$key = $func[0];

			if (!$this->translator->has($key, $this->locale))
			{
				$this->missedKeys->push($key);
			}
		}
	}

}
