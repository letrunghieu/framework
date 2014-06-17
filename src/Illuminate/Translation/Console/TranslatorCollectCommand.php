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

	protected function getLocalizationStuffs($folder)
	{
		
	}

	protected function parseFile($filepath)
	{
		$foundLines = [];

		$fileContent = $this->files->get($filepath);

		foreach (token_get_all($fileContent) as $token)
		{
			$result = $this->parseToken($token);
		}

		return $foundLines;
	}

	protected function parseToken($token)
	{
		if (is_array($token))
		{
			list($id, $content) = $token;
			
			switch ($id)
			{
				case T_INLINE_HTML:
					return $this->parseBladeCalls($content);
			}
		}

		return false;
	}
	
	protected function parseBladeCalls($content)
	{
		$patterns = array();
		
		$matches = null;
		$count = preg_match_all('/\B@(?:lang|choice)(?:[ \t]*)(\( ( (?>[^()]+) | (?1) )* \))?/x', $content, $matches);
		
		if($count)
		{
			for($i = 0; $i < $count; $i++)
			{
				$key = $this->extractLocalizationKey($matches[1][$i]);
				if ($key)
				{
					$patterns[] = $key;
				}
			}
		}
		
		return $patterns;
	}
	
	protected function extractLocalizationKey($string)
	{
		$matches = null;
		if (preg_match('/\(\s*(["\']) ( (?:\\{2})* |(?:.*?[^\\](?:\\{2})*) )\1 (?:,(?:.*))?\s*\)/x', $string, $matches))
		{
			return $matches[2];
		}
		return false;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		
	}

}
