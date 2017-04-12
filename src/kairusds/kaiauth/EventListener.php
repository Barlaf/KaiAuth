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

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;

class EventListener implements Listener{
	
	private $plugin;

	public function __construct(Main $plugin) {
		$this->plugin = $plugin;
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}

	public function onJoin(PlayerJoinEvent $event) {
		$config = $this->plugin->getDataProvider()->getPlayer($event->getPlayer());
		if($config !== null && $config["lastip"] === $event->getPlayer()->getAddress()) {
			$this->plugin->authenticatePlayer($event->getPlayer());
			$event->getPlayer()->sendMessage("§9§lKaiAuth> §r§7You have been automatically logged in by your IP address.");
			return;
		}
		$this->plugin->deauthenticatePlayer($event->getPlayer());
	}

	public function onPreLogin(PlayerPreLoginEvent $event) {
		$player = $event->getPlayer();
		foreach($this->plugin->getServer()->getOnlinePlayers() as $p) {
			if($p !== $player && strtolower($player->getName()) === strtolower($p->getName())) {
				if($this->plugin->isPlayerAuthenticated($p)) {
					$event->setCancelled();
					$player->kick("Already logged in!", false);
					return;
				} 
			}
		}
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$this->plugin->sendAuthenticateMessage($event->getPlayer());
		}
	}

	public function onCommandPreprocess(PlayerCommandPreprocessEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$message = $event->getMessage();
			if($message{0} === "/") {
				$event->setCancelled();
				$command = substr($message, 1);
				$args = explode(" ", $command);
				if($args[0] === "register" || $args[0] === "login" || $args[0] === "help") {
					$this->plugin->getServer()->dispatchCommand($event->getPlayer(), $command);
					return;
				}else {
					$this->plugin->sendAuthenticateMessage($event->getPlayer());
					return;
				}
			}
			$event->setCancelled();
		}
	}

	public function onMove(PlayerMoveEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$event->setCancelled();
			$event->getPlayer()->onGround = true;
		}
	}

	public function onInteract(PlayerInteractEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$event->setCancelled();
		}
	}

	public function onDrop(PlayerDropItemEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$event->setCancelled();
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event) {
		$this->plugin->closePlayer($event->getPlayer());
	}

	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$event->setCancelled();
		}
	}

	public function onDamage(EntityDamageEvent $event) {
		if($event->getEntity() instanceof Player and !$this->plugin->isPlayerAuthenticated($event->getEntity())) {
			$event->setCancelled();
		}
	}

	public function onBlockBreak(BlockBreakEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$event->setCancelled();
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) {
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())) {
			$event->setCancelled();
		}
	}

	public function onPickup(InventoryPickupItemEvent $event) {
		$player = $event->getInventory()->getHolder();
		if($player instanceof Player and !$this->plugin->isPlayerAuthenticated($player)) {
			$event->setCancelled();
		}
	}
	
}
