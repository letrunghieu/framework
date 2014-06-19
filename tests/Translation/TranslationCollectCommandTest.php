<?php

use Mockery as m;

class TranslationCollectCommandTest extends PHPUnit_Framework_TestCase {

	protected $file;
	protected $folder;

	protected function setUp()
	{
		parent::setUp();
		$this->file = __DIR__ . '/en/default.php';
		$this->folder = __DIR__ . '/en';
		$this->cleanOutput();
	}

	protected function tearDown()
	{
		m::close();
		$this->cleanOutput();
	}

	public function testCollectAllKeys()
	{
		$collector = $this->getCollectorObject();

		$collector->fire();

		$missedKeys = $collector->getMissedKeys();

		$this->assertArrayHasKey('default', $missedKeys);
		$this->assertArrayHasKey("string without params", $missedKeys['default']);
	}

	public function testLanguageFilesIsUpdated()
	{
		$collector = $this->getCollectorObject();
		
		$defaultData = "<?php return array('string without params' => 'foo', 'keys with params' => '');";
		if (!file_exists($this->folder))
		{
			mkdir($this->folder);
		}
		file_put_contents($this->file, $defaultData);

		$collector->fire();

		$content = require $this->file;

		$expected = array(
			'string without params' => 'foo',
			'keys with params' => '',
			'choice without params' => '',
			'choice with params' => '',
			'another choice key' => '',
			'\'escaped\' keys' => '',
			'foo' =>
			array(
				'another \\\\ "escaped" key' => '',
				'another \\\\ "escaped" choice key' => '',
			),
			'\'escaped\' choice keys' => '',
			'another key' => '',
		);

		$this->assertEquals($expected, $content);
	}

	protected function getCollectorObject()
	{
		$laravel = m::mock('\Illuminate\Foundation\Application');

		$files = m::mock('Illuminate\Filesystem\Filesystem')->makePartial();
		$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\FileLoader($files, __DIR__), 'en');
		$compiler = new \Illuminate\View\Compilers\BladeCompiler($files, 'view');
		$config = m::mock('\Illuminate\Config\Repository', [m::mock('\Illuminate\Config\LoaderInterface'), 'testing']);

		$controllerFile = $this->getMock('Symfony\Component\Finder\SplFileInfo', [], ['', '', '']);
		$controllerFile->expects($this->any())->method('getExtension')->willReturn('php');
		$controllerFile->expects($this->any())->method('getRealPath')->willReturn('/path/controllers/FooController.php');

		$viewFile = $this->getMock('Symfony\Component\Finder\SplFileInfo', [], ['', '', '']);
		$viewFile->expects($this->any())->method('getExtension')->willReturn('php');
		$viewFile->expects($this->any())->method('getRealPath')->willReturn('/view/view.blade.php');

		$files->shouldReceive('allFiles')->with('/path/controllers')->andReturn([$controllerFile]);
		$files->shouldReceive('allFiles')->with('/path/views')->andReturn([$viewFile]);
		$files->shouldReceive('get')->with('/path/controllers/FooController.php')->andReturn($this->getControllerPhpContent());
		$files->shouldReceive('get')->with('/view/view.blade.php')->andReturn($this->getViewPhpContent());

		$config->shouldReceive('offsetGet')->with('app.locale')->andReturn('en');
		$laravel->shouldReceive('offsetGet')->with('files')->andReturn($files);
		$laravel->shouldReceive('offsetGet')->with('translator')->andReturn($translator);
		$laravel->shouldReceive('offsetGet')->with('blade.compiler')->andReturn($compiler);
		$laravel->shouldReceive('offsetGet')->with('config')->andReturn($config);
		$laravel->shouldReceive('offsetGet')->with('path')->andReturn('/path');

		$collector = new TranslatorCollectCommandStuff($laravel);

		return $collector;
	}

	protected function getControllerPhpContent()
	{
		$php = <<<PHP
<?php

/**
 * Description of FooController
 * 
 * Long description of FooController
 * 
 * @package    package name
 * @subpackage sub_package
 * @author     Hieu Le
 */
class FooController extends BaseController
{
    public function index()
    {
        trans('default.string without params');
        trans('default.keys with params', ['foo' => 'bar']);
        trans_choice('default.choice without params', count([]));
        trans_choice('default.choice with params', 1, ['foo' => 'bar']);
    }
}

PHP;
		return $php;
	}

	protected function getViewPhpContent()
	{
		$php = <<<'PHP'
@section
<div>
{{trans_choice('default.another choice key', count($array), ['foo' => $bar])}}    
</div>
<div>
    <span class="{{$foo}}">
        @lang('default.\'escaped\' keys')
        @lang("default.foo.another \\ \"escaped\" key", ['foo' => $foo])
        @choice('default.\'escaped\' choice keys', 1)
        @choice("default.foo.another \\ \"escaped\" choice key", 1, ['foo' => $foo])
    </span>
</div>
@stop

{{trans('default.another key', ['foo' => $bar])}}

PHP;
		return $php;
	}

	protected function cleanOutput()
	{
		if (file_exists($this->file))
		{
			unlink($this->file);
		}

		if (file_exists($this->folder))
		{
			rmdir($this->folder);
		}
	}

}

class TranslatorCollectCommandStuff extends Illuminate\Translation\Console\TranslatorCollectCommand {

	public function getMissedKeys()
	{
		return $this->missedKeys;
	}

	/**
	 * Disable console write
	 */
	public function info($string)
	{
		return true;
	}
	
	protected function getCurrentLocale()
	{
		return 'en';
	}

}
