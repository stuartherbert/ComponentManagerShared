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

use SimpleXMLElement;

use Phix_Project\TasksLib\TaskQueue;
use Phix_Project\TasksLib\Files_RmTask;
use Phix_Project\TasksLib\Files_MkdirTask;
use Phix_Project\TasksLib\Files_CpTask;
use Phix_Project\TasksLib\Files_ChmodTask;

class ComponentFolder
{
        const BUILD_PROPERTIES = 'build.properties';
        const PACKAGE_XML = 'package.xml';

        const STATE_UNKNOWN = 0;
        const STATE_EMPTY = 1;
        const STATE_UPTODATE = 2;
        const STATE_NEEDSUPGRADE = 3;
        const STATE_INCOMPATIBLE = 4;

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
         * The path to the package.xml file in the component's root folder
         * @var string
         */
        public $packageXmlFile = null;
        
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
                $this->packageXmlFile = $folder . '/' . self::PACKAGE_XML;
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
                                $this->state = self::STATE_INCOMPATIBLE;
                                return;
                        }
                }
                
                // is this for a component.type that we support?
                if ($properties['component.type'] !== static::COMPONENT_TYPE)
                {
                        $this->state = self::STATE_INCOMPATIBLE;
                        return;
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
                $taskQueue = new TaskQueue();
                
                foreach ($files as $filename)
                {
                        $srcFile = $this->pathToDataFolder . '/' . $filename;
                        $destFile = $this->folder . '/' . $dest . $filename;
                        
                        $cpTask = new Files_CpTask();
                        $cpTask->initWithFilesOrFolders($srcFile, $destFile);
                        $taskQueue->queueTask($cpTask);
                }
                
                $taskQueue->executeTasks();
        }

        public function copyFileFromDataFolderWithNewName($file, $dest)
        {
                $srcFile  = $this->pathToDataFolder . '/' . $file;
                $destFile = $this->folder . '/' . $dest;
                
                $taskQueue = new TaskQueue();
                
                $cpTask = new Files_CpTask();
                $cpTask->initWithFilesOrFolders($srcFile, $destFile);
                $taskQueue->queueTask($cpTask);
                
                $taskQueue->executeTasks();
	}

        public function replaceFolderContentsFromDataFolder($src, $dest='')
        {
                $srcFolder  = $this->pathToDataFolder . '/' . $src;
                $destFolder = $this->folder . '/' . $dest;

                // queue up the work we need to do
                $taskQueue = new TaskQueue();
                
                $rmTask = new Files_RmTask();
                $rmTask->initWithFileOrFolder($destFolder);
                $taskQueue->queueTask($rmTask);
                
                $mkdirTask = new Files_MkdirTask();
                $mkdirTask->initWithFolder($destFolder);
                $taskQueue->queueTask($mkdirTask);
                
                $cpTask = new Files_CpTask();
                $cpTask->initWithFilesOrFolders($src, $dest);
                $taskQueue->queueTask($cpTask);
                
                // execute the tasks!
                // 
                // if there are problems, an exception will automatically
                // be thrown
                $taskQueue->executeTasks();
        }

        public function copyFolders($src, $dest='')
        {
                if ($src{0} !== DIRECTORY_SEPARATOR)
                {
                        $srcFolder = $this->pathToDataFolder . '/' . $src;
                }
                else
                {
                        $srcFolder = $src;
                }
                $destFolder = $this->folder . '/' . $dest;

                // queue up the work we need to do
                $taskQueue = new TaskQueue();
                
                $rmTask = new Files_RmTask();
                $rmTask->initWithFileOrFolder($destFolder);
                $taskQueue->queueTask($rmTask);
                
                $mkdirTask = new Files_MkdirTask();
                $mkdirTask->initWithFolder($destFolder);
                $taskQueue->queueTask($mkdirTask);
                
                $cpTask = new Files_CpTask();
                $cpTask->initWithFilesOrFolders($srcFolder, $destFolder);
                $taskQueue->queueTask($cpTask);
                
                // execute the tasks!
                //
                // if there are problems, an exception will automatically
                // be thrown
                $taskQueue->executeTasks();
        }

        public function enableExecutionOf($file, $dest='')
        {
                $destFolder = $this->folder . DIRECTORY_SEPARATOR . $dest;
                $fqFile = $destFolder . $file;

                // queue up the work we need to do
                $taskQueue = new TaskQueue();
                
                $chmodTask = new Files_ChmodTask();
                $chmodTask->initWithFileAndMode($fqFile, 0755);
                $taskQueue->queueTask($chmodTask);
                
                // execute the task
                //
                // if there are problems, an exception will automatically
                // be thrown
                $taskQueue->executeTasks();
        }

        public function testHasBuildProperties()
        {
                if (\file_exists($this->buildPropertiesFile))
                {
                        return true;
                }

                return false;
        }

        public function editBuildPropertiesVersionNumber($newNumber)
	{
		$this->setBuildProperty('component.version', $newNumber);
	}

	public function addBuildProperty($property, $value, $after=null)
	{
		if (!$this->testHasBuildProperties())
		{
			return false;
		}

		$buildProperties = file_get_contents($this->buildPropertiesFile);                
		if ($this->hasBuildProperty($property, $buildProperties))
		{
			$buildProperties = \preg_replace('|^' . $property . '=.*$|m', $property . '=' . $value, $buildProperties);
		}
                else if ($after == null)
                {
                        $buildProperties .= $property . '=' . $value . PHP_EOL;
                }
                else
                {
                        $buildProperties = \preg_replace('|^(' . $after . '=.*$)|m', '\$1' . $property . '=' . $value . PHP_EOL, $buildProperties);
                }
     		\file_put_contents($this->buildPropertiesFile, $buildProperties);
	}

        public function hasBuildProperty($property, $buildProperties)
        {
		return \preg_match('|' . $property . '=|', $buildProperties);                
        }
        
	public function setBuildProperty($property, $value)
	{
		if (!$this->testHasBuildProperties())
		{
			return false;
		}

		$buildProperties = file_get_contents($this->buildPropertiesFile);
		if ($this->hasBuildProperty($property, $buildProperties))
		{
			$buildProperties = \preg_replace('|^' . $property . '=.*$|m', $property . '=' . $value, $buildProperties);
		}
		else
		{
			$buildProperties .= $property . '=' . $value . PHP_EOL;
		}
		\file_put_contents($this->buildPropertiesFile, $buildProperties);
	}
        
        public function testHasPackageXml()
        {
                if (\file_exists($this->packageXmlFile))
                {
                        return true;
                }

                return false;
        }
        
        public function loadPackageXml()
        {
                if (!$this->testHasPackageXml())
                {
                        return false;
                }
                
                return simplexml_load_file($this->packageXmlFile);
        }
        
        public function savePackageXml(SimpleXMLElement $xmlNode)
        {
                \file_put_contents($this->packageXmlFile, $xmlNode->asXML());
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
