<?php namespace Illuminate\Translation;

interface LoaderInterface {

	/**
	 * Load the messages for the given locale.
	 *
	 * @param  string  $locale
	 * @param  string  $group
	 * @param  string  $namespace
	 * @return array
	 */
	public function load($locale, $group, $namespace = null);

	/**
	 * Add a new namespace to the loader.
	 *
	 * @param  string  $namespace
	 * @param  string  $hint
	 * @return void
	 */
	public function addNamespace($namespace, $hint);
	
	/**
	 * Get current path where messages loaded from
	 * 
	 * @return string
	 */
	public function getPath();

}
