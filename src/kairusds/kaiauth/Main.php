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

namespace kairusds\kaiauth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\IPlayer;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\Permission;
use pocketmine\plugin\PluginBase;
use kairusds\kaiauth\provider\DataProvider;
use kairusds\kaiauth\provider\MySQLDataProvider;
use kairusds\kaiauth\provider\SQLite3DataProvider;
use kairusds\kaiauth\task\ShowMessageTask;

class Main extends PluginBase{

	protected $needAuth = [];
	protected $listener;
	protected $provider;
	protected $messageTask = null;

	public function isPlayerAuthenticated(Player $player) {
		return !isset($this->needAuth[spl_object_hash($player)]);
	}

	public function isPlayerRegistered(IPlayer $player) {
		return $this->provider->isPlayerRegistered($player);
	}

	public function authenticatePlayer(Player $player) {
		if($this->isPlayerAuthenticated($player)) {
			return true;
		}

		if(isset($this->needAuth[spl_object_hash($player)])) {
			$attachment = $this->needAuth[spl_object_hash($player)];
			$player->removeAttachment($attachment);
			unset($this->needAuth[spl_object_hash($player)]);
		}
		
		$this->provider->updatePlayer($player, $player->getAddress(), time());
		$player->sendMessage("§9§lKaiAuth> §r§7You have been logged in.");
		$this->getMessageTask()->removePlayer($player);
		unset($this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())]);
		return true;
	}

	public function deauthenticatePlayer(Player $player) {
		if(!$this->isPlayerAuthenticated($player)) {
			return true;
		}

		$attachment = $player->addAttachment($this);
		$this->removePermissions($attachment);
		$this->needAuth[spl_object_hash($player)] = $attachment;
		$this->sendAuthenticateMessage($player);
		$this->getMessageTask()->addPlayer($player);
		return true;
	}

	public function registerPlayer(IPlayer $player, $password) {
		if(!$this->isPlayerRegistered($player)) {
			$this->provider->registerPlayer($player, $this->hash(strtolower($player->getName()), $password));
			return true;
		}
		
		return false;
	}

	public function unregisterPlayer(IPlayer $player) {
		if($this->isPlayerRegistered($player)) {
			$this->provider->unregisterPlayer($player);
		}

		return true;
	}

	public function setDataProvider(DataProvider $provider) {
		$this->provider = $provider;
	}

	public function getDataProvider() {
		return $this->provider;
	}

	public function closePlayer(Player $player) {
		unset($this->needAuth[spl_object_hash($player)]);
		$this->getMessageTask()->removePlayer($player);
	}

	public function sendAuthenticateMessage(Player $player) {
		$config = $this->provider->getPlayer($player);
		$player->sendMessage("§9§lKaiAuth> §r§7This server requires account authentication.");
		
		if($config === null) {
			$player->sendMessage("§9§lKaiAuth> §r§7Please register by typing /register <password> <confirmPassword>.");
		}else{
			$player->sendMessage("§9§lKaiAuth> §r§7Please login by typing /login <password>.");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if(!$sender instanceof Player) {
			$sender->sendMessage("§cSilly console.");
			return true;
		}
		
		switch($command->getName()) {
			case "unregister":
				$this->provider->unregisterPlayer($sender);
				$sender->sendMessage("§9§lKaiAuth> §r§7Your account has been disbanded.");
				$this->deauthenticatePlayer($sender);
				break;
			case "chpwd":
				if(count($args) !== 2) {
					$sender->sendMessage("§cUsage: /chpwd <oldPassword> <newPassword>");
					return true;
				}
				
				$data = $this->provider->getPlayer($sender);
				
				if(!hash_equals($data["hash"], $this->hash(strtolower($sender->getName()), $args[0]))) {
					$sender->sendMessage("§9§lKaiAuth> §r§7Your old password is incorrect, please try again.");
					return true;
				}
				
				$this->provider->changePassword($sender, $this->hash(strtolower($sender->getName()), $args[1]));
				$sender->sendMessage("§9§lKaiAuth> §r§7Your password has been changed.");
				break;
			case "login":
				if(!$this->isPlayerRegistered($sender) or ($data = $this->provider->getPlayer($sender)) === null) {
					$sender->sendMessage("§9§lKaiAuth> §r§7This account is not registered. You can claim it by typing /register <password>.");
					return true;
				}
				
				if(count($args) !== 1) {
					$sender->sendMessage("§cUsage: /login <password>");
					return true;
				}
				
				$password = implode(" ", $args);
				
				if(hash_equals($data["hash"], $this->hash(strtolower($sender->getName()), $password)) && $this->authenticatePlayer($sender)) {
					return true;
				}else{
					$sender->sendMessage("§9§lKaiAuth> §r§7Your password is incorrect, please try again.");
					return true;
				}
				break;
			case "register":
				if($this->isPlayerRegistered($sender)) {
					$sender->sendMessage("§9§lKaiAuth> §r§7This account is already registered.");
					return true;
				}
				
				if(count($args) !== 2) {
					$sender->sendMessage("§cUsage: /register <password> <confirmPassword>");
					return true;
				}
				
				$password = $args[0];
				
				if($password !== $args[1]) {
					$sender->sendMessage("§9§lKaiAuth> §r§7The passwords doesn't match, please try again.");
					return true;
				}
				
				if(strlen($password) < $this->getConfig()->get("min-password-length")) {
					$sender->sendMessage("§9§lKaiAuth> §r§7Your password is too short.");
					return true;
				}
				
				if($this->registerPlayer($sender, $password) and $this->authenticatePlayer($sender)) {
					return true;
				}
				
				$sender->sendMessage("§9§lKaiAuth> §r§7An unknown error occured.");
				break;
		}
		return false;
	}

	public function onEnable() {
		$this->saveDefaultConfig();
		$provider = $this->getConfig()->get("dataProvider");
		
		switch(strtolower($provider)) {
			case "sqlite3":
				$this->getLogger()->debug("Using SQLite3 data provider");
				$provider = new SQLite3DataProvider($this);
				break;
			case "mysql":
				$this->getLogger()->debug("Using MySQL data provider");
				$provider = new MySQLDataProvider($this);
				break;
			case "none":
			default:
				$provider = new SQLite3DataProvider($this);
				break;
		}

		if($provider instanceof DataProvider) {
			$this->provider = $provider;
		}

		$this->listener = new EventListener($this);

		foreach($this->getServer()->getOnlinePlayers() as $player) {
			$this->deauthenticatePlayer($player);
		}
		
		$message = [
			" ",
			"  _  __     _               _   _     ",
			" | |/ /    (_)   /\        | | | |    ",
			" | ' / __ _ _   /  \  _   _| |_| |__  ",
			" |  < / _` | | / /\ \| | | | __| '_ \ ",
			" | . \ (_| | |/ ____ \ |_| | |_| | | |",
			" |_|\_\__,_|_/_/    \_\__,_|\__|_| |_|",
			" ",
			" Made by KairusDarkSeeker"
		];
		$this->getLogger()->notice(implode("\n", $message));
	}

	public function onDisable() {
		$this->provider->close();
		$this->messageTask = null;
	}

	public static function orderPermissionsCallback($perm1, $perm2) {
		if(self::isChild($perm1, $perm2)) {
			return -1;
		}elseif(self::isChild($perm2, $perm1)) {
			return 1;
		}else{
			return 0;
		}
	}

	public static function isChild($perm, $name) {
		$perm = explode(".", $perm);
		$name = explode(".", $name);

		foreach($perm as $k => $component) {
			if(!isset($name[$k])) {
				return false;
			}elseif($name[$k] !== $component) {
				return false;
			}
		}

		return true;
	}

	protected function removePermissions(PermissionAttachment $attachment) {
		$permissions = [];
		
		foreach($this->getServer()->getPluginManager()->getPermissions() as $permission) {
			$permissions[$permission->getName()] = false;
		}

		$permissions["pocketmine.command.help"] = true;
		$permissions[Server::BROADCAST_CHANNEL_USERS] = true;
		$permissions[Server::BROADCAST_CHANNEL_ADMINISTRATIVE] = false;
		uksort($permissions, [Main::class, "orderPermissionsCallback"]);
		$attachment->setPermissions($permissions);
	}
	
	private function hash($salt, $password) {
		return bin2hex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
	}
	
	protected function getMessageTask() {
		if($this->messageTask === null) {
			$this->messageTask = new ShowMessageTask($this);
			$this->getServer()->getScheduler()->scheduleRepeatingTask($this->messageTask, 20);
		}

		return $this->messageTask;
	}
	
}
