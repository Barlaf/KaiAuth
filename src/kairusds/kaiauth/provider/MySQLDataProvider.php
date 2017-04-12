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
use kairusds\kaiauth\task\MySQLPingTask;

class MySQLDataProvider implements DataProvider {

	protected $plugin;
	protected $database;

	public function __construct(Main $plugin) {
		$this->plugin = $plugin;
		$config = $this->plugin->getConfig()->get("mysql-settings");

		if(!isset($config["host"]) or !isset($config["user"]) or !isset($config["password"]) or !isset($config["database"])) {
			$this->plugin->getLogger()->critical("Invalid MySQL settings");
			$this->plugin->setDataProvider(new SQLite3DataProvider($this->plugin));
			return;
		}

		$this->database = new \mysqli($config["host"], $config["user"], $config["password"], $config["database"], isset($config["port"]) ? $config["port"] : 3306);
		if($this->database->connect_error) {
			$this->plugin->getLogger()->critical("Couldn't connect to MySQL: " . $this->database->connect_error);
			$this->plugin->setDataProvider(new SQLite3DataProvider($this->plugin));
			return;
		}

		$this->database->query(base64_decode("Q1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMga2FpYXV0aF9wbGF5ZXJzICgKICBuYW1lIFZBUkNIQVIoMTYpIFBSSU1BUlkgS0VZLAogIGhhc2ggQ0hBUigxMjgpLAogIHJlZ2lzdGVyZGF0ZSBJTlQsCiAgbG9naW5kYXRlIElOVCwKICBsYXN0aXAgVkFSQ0hBUig1MCkKKTs="));
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new MySQLPingTask($this->plugin, $this->database), 20 * 30);
		$this->plugin->getLogger()->info("Connected to MySQL server");
	}

	public function getPlayer(IPlayer $player) {
		$name = strtolower($player->getName());
		$result = $this->database->query("SELECT * FROM kaiauth_players WHERE name = '" . $this->database->escape_string($name) . "'");

		if($result instanceof \mysqli_result) {
			$data = $result->fetch_assoc();
			$result->free();
			if(isset($data["name"]) and strtolower($data["name"]) === $name) {
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
		$name = strtolower($player->getName());
		$this->database->query("DELETE FROM kaiauth_players WHERE name = '" . $this->database->escape_string($name) . "'");
	}

	public function registerPlayer(IPlayer $player, $hash) {
		$name = strtolower($player->getName());
		$data = [
			"registerdate" => time(),
			"logindate" => time(),
			"lastip" => null,
			"hash" => $hash
		];

		$this->database->query("INSERT INTO kaiauth_players
			(name, registerdate, logindate, lastip, hash)
			VALUES
			('" . $this->database->escape_string($name)."', " . intval($data["registerdate"]) . ", " . intval($data["logindate"]) . ", '', '" . $hash . "')
		");

		return $data;
	}

	public function savePlayer(IPlayer $player, array $config) {
		$name = strtolower($player->getName());
		$this->database->query("UPDATE kaiauth_players SET registerdate = " . intval($config["registerdate"]) . ", logindate = " . intval($config["logindate"]) . ", lastip = '" . $this->database->escape_string($config["lastip"]) . "', hash = '" . $this->database->escape_string($config["hash"]) . "' WHERE name = '" . $this->database->escape_string($name) . "'");
	}

	public function updatePlayer(IPlayer $player, $lastIP = null, $loginDate = null) {
		$name = strtolower($player->getName());
		
		if($lastIP !== null) {
			$this->database->query("UPDATE kaiauth_players SET lastip = '" . $this->database->escape_string($lastIP) . "' WHERE name = '" . $this->database->escape_string($name) . "'");
		}
		
		if($loginDate !== null) {
			$this->database->query("UPDATE kaiauth_players SET logindate = " . intval($loginDate) . " WHERE name = '" . $this->database->escape_string($name) . "'");
		}
	}
	
	public function changePassword(IPlayer $player, $hash) {
		$name = strtolower($player->getName());
		$this->database->query("UPDATE kaiauth_players SET hash = '$hash' WHERE name = '" . $this->database->escape_string($name) . "'");
	}

	public function close() {
		$this->database->close();
	}
	
}
