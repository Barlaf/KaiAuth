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

namespace kairusds\kaiauth\provider;

use pocketmine\IPlayer;
use kairusds\kaiauth\Main;

class SQLite3DataProvider implements DataProvider {

	protected $plugin;
	protected $database;

	public function __construct(Main $plugin) {
		$this->plugin = $plugin;
		if(!file_exists($this->plugin->getDataFolder() . "players.db")) {
			$this->database = new \SQLite3($this->plugin->getDataFolder() . "players.db");
			$this->database->exec(base64_decode("Q1JFQVRFIFRBQkxFIHBsYXllcnMgKAogIG5hbWUgVEVYVCBQUklNQVJZIEtFWSwKICBoYXNoIFRFWFQsCiAgcmVnaXN0ZXJkYXRlIElOVEVHRVIsCiAgbG9naW5kYXRlIElOVEVHRVIsCiAgbGFzdGlwIFRFWFQKKTs="));
		}else {
			$this->database = new \SQLite3($this->plugin->getDataFolder() . "players.db");
		}
	}

	public function getPlayer(IPlayer $player) {
		$name = strtolower($player->getName());
		$result = $this->database->query("SELECT * FROM players WHERE name = '$name';");
		$data = $result->fetchArray(SQLITE3_ASSOC);

		if(is_array($data)) {
			if(isset($data["name"]) and $data["name"] === $name) {
				unset($data["name"]);
				return $data;
			}
		}
		return null;
	}

	public function isPlayerRegistered(IPlayer $player) {
		return $this->getPlayer($player) !== null;
	}

	public function unregisterPlayer(IPlayer $player) {
		$name = trim(strtolower($player->getName()));
		$prepare = $this->database->query("DELETE FROM players WHERE name = '$name';");
	}

	public function registerPlayer(IPlayer $player, $hash) {
		$name = trim(strtolower($player->getName()));
		$data = [
			"registerdate" => time(),
			"logindate" => time(),
			"lastip" => null,
			"hash" => $hash
		];
		$prepare = $this->database->query("INSERT INTO players (name, registerdate, logindate, lastip, hash) VALUES ('$name', '" . $data["registerdate"] . "', '" . $data["logindate"] . "', NULL, '$hash');");
		return $data;
	}

	public function savePlayer(IPlayer $player, array $config) {
		$name = strtolower($player->getName());
		$this->database->query("UPDATE players SET registerdate = '" . $config["registerdate"] . "', logindate = '" . $config["logindate"] . "', lastip = '" . $config["lastip"] . "', hash = '" . $config["hash"] . "' WHERE name = '$name';");
	}

	public function updatePlayer(IPlayer $player, $lastIP = null, $loginDate = null) {
		$name = strtolower($player->getName());
		
		if($lastIP !== null) {
			$this->database->query("UPDATE players SET lastip = '$lastIP' WHERE name = '$name';");
		}
		
		if($loginDate !== null) {
			$this->database->query("UPDATE players SET logindate = '$loginDate' WHERE name = '$name';");
		}
	}
	
	public function changePassword(IPlayer $player, $hash) {
		$name = strtolower($player->getName());
		$this->database->query("UPDATE players SET hash = '$hash' WHERE name = '$name';");
	}

	public function close() {
		$this->database->close();
	}
	
}
