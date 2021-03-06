<?php

/**
 * Copyright 2017 KairusDarkSeeker
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace kairusds\kaiauth\task;

use pocketmine\scheduler\PluginTask;
use kairusds\kaiauth\Main;

class MySQLPingTask extends PluginTask{

	private $database;

	public function __construct(Main $plugin, \mysqli $database) {
		parent::__construct($plugin);
		$this->database = $database;
	}

	public function onRun($currentTick) {
		$this->database->ping();
	}
	
}
