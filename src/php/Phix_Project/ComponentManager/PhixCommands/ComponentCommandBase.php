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
 * @link        http://www.phix-project.org/
 * @version     @@PACKAGE_VERSION@@
 */

namespace Phix_Project\ComponentManager\PhixCommands;

use Phix_Project\Phix\CommandsList;
use Phix_Project\Phix\Context;
use Phix_Project\PhixExtensions\CommandBase;
use Phix_Project\CommandLineLib\DefinedSwitches;
use Phix_Project\CommandLineLib\DefinedSwitch;

use Phix_Project\ComponentManager\Entities\ComponentFolder;

class ComponentCommandBase extends CommandBase
{
        protected function validateFolder(&$args, $argsIndex, Context $context)
        {
                $se = $context->stderr;

                // $args[$argsIndex] should point at the folder where we
                // want to create the initial structure

                if (!isset($args[$argsIndex]))
                {
                        // new - if the folder is missing, assume that the
                        // user wants us to use the current working directory
                        $args[$argsIndex] = getcwd();
                }

                // is the folder a real directory?

                if (!\is_dir($args[$argsIndex]))
                {
                        $se->output($context->errorStyle, $context->errorPrefix);
                        $se->outputLine(null, 'folder ' . $args[$argsIndex] . ' not found');

                        return 1;
                }

                // can we write to the folder?

                if (!\is_writeable($args[$argsIndex]))
                {
                        $se->output($context->errorStyle, $context->errorPrefix);
                        $se->outputLine(null, 'cannot write to folder ' . $args[$argsIndex]);

                        return 1;
                }

                // if we get here, we have run out of things that we can
                // check for right now

                return null;
        }
}
