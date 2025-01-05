<?php

declare(strict_types=1);

namespace borderLimbo;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;

class BorderLimbo extends PluginBase implements Listener{

	private static array $oldMovement;
	private static Config $config;

	public static array $limboStatus;

	public function onEnable() : void{
		$this->saveResource('config.yml');
		self::$config = new Config($this->getDataFolder() .'config.yml', Config::YAML);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->start();
	}

	public function start(): void{
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(){
			foreach (Server::getInstance()->getOnlinePlayers() as $player){
				$properties = BorderLimbo::getOldMovementProperties($player);
				$settings = BorderLimbo::getSettings();
				if(!isset(BorderLimbo::$limboStatus[$player->getName()]) && $properties['oldTime'] + $settings->get("limbo-time-report") <= time()){
					$player->sendTitle("§cНе стойте в AFK", "§7Или вы будете кикнуты в Limbo");
				}
				if(!isset(BorderLimbo::$limboStatus[$player->getName()]) && $properties['oldTime'] + $settings->get("limbo-time") <= time()){
					$player->sendTitle("§6Телепортация в Limbo");
					self::$limboStatus[$player->getName()] = true;
					$player->teleport(new Vector3($settings->get("x"), $settings->get("y"), $settings->get("z")));
					return;
				}
			}
		}), 20);
	}

	public function handleMovement(PlayerMoveEvent $event): void{
		$player = $event->getPlayer();
		if(BorderLimbo::$oldMovement[strtolower($player->getName())]["tpTime"] !== 15){
			return;
		}
		self::$oldMovement[strtolower($player->getName())] = [
			'oldPos' => $player->getPosition(),
			'tpTime' => 15,
			'oldTime' => time()
		];

		if(isset(self::$limboStatus[$player->getName()])){
			$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{
				foreach (Server::getInstance()->getOnlinePlayers() as $player){
					if (isset(BorderLimbo::$limboStatus[$player->getName()])){
						$time = BorderLimbo::$oldMovement[strtolower($player->getName())]['tpTime'];
						if($time <= 0){
							unset(BorderLimbo::$limboStatus[$player->getName()]);
							$player->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
							self::$oldMovement[strtolower($player->getName())] = [
								'oldPos' => $player->getPosition(),
								'tpTime' => 15,
								'oldTime' => time()
							];
							$this->getScheduler()->cancelAllTasks();
							$this->start();
							return;
						}
						if($time === 15){
							BorderLimbo::$oldMovement[strtolower($player->getName())] = [
								'oldPos' => $player->getPosition(),
								'tpTime' => 6,
								'oldTime' => time()
							];
						}
						BorderLimbo::$oldMovement[strtolower($player->getName())] = [
							'oldPos' => $player->getPosition(),
							'tpTime' => $time - 1,
							'oldTime' => time()
						];

						$player->sendTitle("§7Телепортация в лобби через", "§6". $time ." секунд");


					}
				}
			}), 20);
		}
	}

	public function onPreLogin(PlayerPreLoginEvent $event): void{
		self::$oldMovement[strtolower($event->getPlayerInfo()->getUsername())] = [
			"oldPos" => " ",
			"tpTime" => 15,
			"oldTime" => time()
		];
	}

	public static function getOldMovementProperties(Player $player): array{
		return self::$oldMovement[strtolower($player->getName())];
	}

	public static function getSettings(): Config{
		return self::$config;
	}

	public function onCmdPre(CommandEvent $event): void{
		$player = $event->getSender();
		if(isset(self::$limboStatus[$player->getName()])){
			$player->sendMessage("нельзя писать команды в лимбо");
			$event->cancel();
		}
	}

	public function onQuit(PlayerQuitEvent $event): void{
		$player = $event->getPlayer();
		if(isset(self::$limboStatus[$player->getName()])){
			$player->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
			unset(self::$limboStatus[$player->getName()]);
		}
	}
}