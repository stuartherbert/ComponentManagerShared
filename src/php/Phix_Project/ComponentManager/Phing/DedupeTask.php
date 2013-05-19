<?php

require_once "phing/Task.php";

/**
 * Remove duplicate files from a folder
 *
 * Useful for cleaning up after we've used PEAR / Pyrus to build
 * the vendor folder
 */
class DedupeTask extends Task
{
	protected $src;
	protected $from;

	public function removeEmptyFolders($root)
	{
		if (!is_dir($root)) {
			return;
		}

		$objects = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach($objects as $fullname => $object)
		{
			// we don't want every file
			$basename = basename($fullname);

			// skip over '.' and '..'
			if ($basename == '.' || $basename == '..') {
				continue;
			}

			// skip over files
			if (!is_dir($fullname)) {
				continue;
			}

			// is this directory empty?
			$contents = glob($fullname . DIRECTORY_SEPARATOR . '*');
			$contents = array_merge($contents, glob($fullname . DIRECTORY_SEPARATOR . '.*'));

			if (count($contents) == 2 && basename($contents[0]) == '.' && basename($contents[1]) == '..') {
				// we think this is empty
				$this->log("Removing empty directory '{$fullname}'.", Project::MSG_DEBUG);

				rmdir($fullname);
			}
		}

		// all done
	}

	public function getFiles($root)
	{
		$return = array();

		$objects = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach($objects as $fullname => $object)
		{
			// we don't want every file
			$basename = basename($fullname);

			// skip over '.' and '..'
			if ($basename == '.' || $basename == '..') {
				continue;
			}

			// if we get here, we want this file
			$name = str_replace($root, '', $fullname);
    		$return[] = $name;
		}

		// all done
		return $return;
	}

	public function setSrc($src)
	{
		$this->src = $src;
	}

	public function setFrom($from)
	{
		$this->from = $from;
	}

	public function main()
	{
		if (!$this->src)
		{
			throw new BuildException("Attribute src is required.", $this->getLocation());
		}

		if (!$this->from)
		{
			throw new BuildException("Attribute from is required.", $this->getLocation());
		}

		$this->log("Running Dedupe to remove files in '{$this->src}' from '{$this->from}'.", Project::MSG_DEBUG);

		// get the list of files to dedupe
		$files = $this->getFiles($this->src);

		// let's clean things up
		foreach ($files as $subpath) {
			if (is_file($this->from . $subpath)) {
				unlink($this->from . $subpath);
			}
		}

		// finally, let's remove all empty directories
		$this->removeEmptyFolders($this->from);
	}
}