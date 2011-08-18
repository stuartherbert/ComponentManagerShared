<?php

/**
 * Copyright (c) 2011 Stuart Herbert.
 * Copyright (c) 2010 Gradwell dot com Ltd.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     Phix_Project
 * @subpackage  ComponentManager
 * @author      Stuart Herbert <stuart@stuartherbert.com>
 * @copyright   2011 Stuart Herbert. www.stuartherbert.com
 * @copyright   2010 Gradwell dot com Ltd. www.gradwell.com
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://www.phix-project.org
 * @version     @@PACKAGE_VERSION@@
 */

namespace Phix_Project\ComponentManager\Entities;

class ComponentFolder
{
        const BUILD_PROPERTIES = 'build.properties';

        const STATE_UNKNOWN = 0;
        const STATE_EMPTY = 1;
        const STATE_UPTODATE = 2;
        const STATE_NEEDSUPGRADE = 3;

        /**
         * The folder that contains the component we represent
         * @var string folder
         */
        public $folder = null;

        /**
         * The path to the build.properties file in the folder
         * @var string
         */
        public $buildPropertiesFile = null;
        
        /**
         * The current state of the folder
         * @var int 
         */
        public $state = self::STATE_UNKNOWN;

        /**
         * The current version of the properties file
         * @var int
         */
        public $componentVersion = 0;

        public $pathToDataFolder = null;

        public function __construct($folder)
        {
                $this->folder = $folder;
                $this->buildPropertiesFile = $folder . '/' . self::BUILD_PROPERTIES;
                $this->pathToDataFolder = static::DATA_FOLDER;
                $this->loadFolderState();
        }

        public function loadFolderState()
        {
                // has this folder been turned into a component before?
                if (!file_exists($this->buildPropertiesFile))
                {
                        $this->state = self::STATE_EMPTY;
                        return;
                }

                // we have a build.properties file
                // let's have a peak inside
                $properties = \parse_ini_file($this->buildPropertiesFile);

                // if it does not have the contents we expect
                // we will discard it
                $expected = array ('component.type', 'component.version');
                foreach ($expected as $expectedProperty)
                {
                        if (!isset($properties[$expectedProperty]))
                        {
                                $this->state = self::STATE_EMPTY;
                                return;
                        }
                }

                // okay, we have a build.properties file that we like
                $this->componentVersion = $properties['component.version'];
                $this->state = self::STATE_UPTODATE;

                // now, does the folder need an upgrade?
                if ($this->componentVersion < static::LATEST_VERSION)
                {
                        $this->state = self::STATE_NEEDSUPGRADE;
                }

                // all done
        }

        public function getStateAsText()
        {
                $stateText = array
                (
                        self::STATE_UNKNOWN             => 'unknown',
                        self::STATE_EMPTY               => 'empty',
                        self::STATE_UPTODATE            => 'up to date',
                        self::STATE_NEEDSUPGRADE        => 'needs upgrade'
                );

                if (isset($stateText[$this->state]))
                {
                        return $stateText[$this->state];
                }

                return 'state not recognised';
        }

        public function copyFilesFromDataFolder($files, $dest='')
        {
                foreach ($files as $filename)
                {
                        $srcFile = $this->pathToDataFolder . '/' . $filename;
                        $destFile = $this->folder . '/' . $dest . $filename;

                        if (!copy($srcFile, $destFile))
                        {
                                throw new \Exception('unable to copy ' . $srcFile . ' to ' . $destFile);
                        }
                }
        }

        public function copyFileFromDataFolderWithNewName($file, $dest)
        {
                $srcFile  = $this->pathToDataFolder . '/' . $file;
                $destFile = $this->folder . '/' . $dest;

                if (!copy($srcFile, $destFile))
                {
                        throw new \Exception('unable to copy ' . $srcFile . ' to ' . $destFile);
                }
	}

        public function replaceFolderContentsFromDataFolder($src, $dest='')
        {
                $srcFolder  = $this->pathToDataFolder . '/' . $src;
                $destFolder = $this->folder . '/' . $dest;

                if (\is_dir($destFolder))
                {
                        $this->recursiveRmdir($destFolder);
                }
                else if (@\lstat($destFolder))
                {
                        \unlink($destFolder);
                }
                \mkdir($destFolder);

                $this->copyFolders($src, $dest);
        }

        protected function recursiveRmdir($folder)
        {
                if (!is_dir($folder))
                {
                        // we're done
                        return;
                }

                $dir = opendir($folder);
                if (!$dir)
                {
                        throw new \Exception("unable to open folder " . $folder . ' for reading');
                }

                while (false !== ($entry = readdir($dir)))
                {
                        if ($entry == '.' || $entry == '..')
                        {
                                continue;
                        }

                        $fqFile = $folder . DIRECTORY_SEPARATOR . $entry;
                        if (is_dir($fqFile))
                        {
                                $this->recursiveRmdir($fqFile);
                        }
                        else
                        {
                                \unlink($fqFile);
                        }
                }

                closedir($dir);

                \rmdir($folder);
        }

        public function copyFolders($src, $dest='')
        {
                $srcFolder = $this->pathToDataFolder . '/' . $src;
                $destFolder = $this->folder . '/' . $dest;

                $this->recursiveCopyFolders($srcFolder, $destFolder);
        }

        private function recursiveCopyFolders($src, $dest)
        {
                if (\file_exists($dest) && !\is_dir($dest))
                {
                        \unlink($dest);
                }
                
                if (!\is_dir($dest))
                {
                        \mkdir($dest);
                }

                $dir = opendir($src);
                if (!$dir)
                {
                        throw new \Exception('unable to open folder ' . $src . ' for reading');
                }
                
                while (false !== ($entry = readdir($dir)))
                {
                        if ($entry == '.' || $entry == '..')
                        {
                                continue;
                        }

                        $srcEntry = $src . DIRECTORY_SEPARATOR . $entry;
                        $dstEntry = $dest . DIRECTORY_SEPARATOR . $entry;

                        if (is_file($srcEntry))
                        {
                                \copy($srcEntry, $dstEntry);
                        }
                        else if (is_dir($srcEntry))
                        {
                                $this->recursiveCopyFolders($srcEntry, $dstEntry);
                        }
                }
                closedir($dir);
        }

        public function enableExecutionOf($file, $dest='')
        {
                $destFolder = $this->folder . DIRECTORY_SEPARATOR . $dest;
                $fqFile = $destFolder . $file;

                chmod($fqFile, 0755);
        }

        public function testHasBuildProperties()
        {
                if (file_exists($this->buildPropertiesFile))
                {
                        return true;
                }

                return false;
        }

        public function editBuildPropertiesVersionNumber($newNumber)
	{
		$this->setBuildProperty('component.version', $newNumber);
	}

	public function addBuildProperty($property, $value)
	{
		if (!$this->testHasBuildProperties())
		{
			return false;
		}

		$buildProperties = file_get_contents($this->buildPropertiesFile);
		if (\preg_match('|^' . $property . '=|', $buildProperties))
		{
			$buildProperties = \preg_replace('|^' . $property . '=.*$|m', $property . '=' . $value, $buildProperties);
        		\file_put_contents($this->buildPropertiesFile, $buildProperties);
		}
	}

	public function setBuildProperty($property, $value)
	{
		if (!$this->testHasBuildProperties())
		{
			return false;
		}

		$buildProperties = file_get_contents($this->buildPropertiesFile);
		if (\preg_match('|^' . $property . '=|', $buildProperties))
		{
			$buildProperties = \preg_replace('|^' . $property . '=.*$|m', $property . '=' . $value, $buildProperties);
		}
		else
		{
			$buildProperties .= $property . '=' . $value . PHP_EOL;
		}
		\file_put_contents($this->buildPropertiesFile, $buildProperties);
	}
        
	public function upgradeComponent($targetVersion)
	{
		// just make sure we're not being asked to do something
		// that is impossible
		if ($this->componentVersion >= $targetVersion)
		{
			throw new \Exception('Folder ' . $this->folder . ' is on version ' . $this->componentVersion . ' which is newer than known latest version ' . self::LATEST_VERSION);

		}

		// ok, let's do the upgrades
		$thisVersion = $this->componentVersion;
		while ($thisVersion < $targetVersion)
		{
			$method = 'upgradeFrom' . $thisVersion . 'To' . ($thisVersion + 1);
			\call_user_func(array($this, $method));
			$thisVersion++;
			$this->editBuildPropertiesVersionNumber($thisVersion);
		}

		// all done
	}
}
