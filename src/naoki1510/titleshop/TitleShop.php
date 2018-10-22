<?php 

namespace naoki1510\titleshop;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\{ TextFormat, Config };
use pocketmine\event\Listener;
use pocketmine\event\player\{ PlayerInteractEvent, PlayerJoinEvent };
use pocketmine\event\block\{ SignChangeEvent, BlockBreakEvent };

class TagShop extends PluginBase implements Listener
{
	public function onEnable()
	{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveConfig();
		$this->data = new Config($this->getDataFolder() . "TitleData.yml", Config::YAML);
	}

	public function onSignChange(SignChangeEvent $event)
	{
		$player = $event->getPlayer();
		$line = $event->getLines();
		if ($line[0] === "title" or $line[0] === TextFormat::GREEN . "[TitleShop]") {
			if (!$player->isOp()) {
				//$player->sendMessage(TextFormat::YELLOW . "あなたはタグショップを作成する権限がありません。");
				return false;
			}
			$tag = $line[1];
			$price = $line[2];
			$event->setLine(0, TextFormat::GREEN . "[TitleShop]");
			$event->setLine(1, TextFormat::WHITE .  $tag);
			$event->setLine(2, TextFormat::YELLOW . "cost: " . $api->getMonitorUnit() . $price);
		}
	}

	public function onTouch(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$api = API::getInstance();
        if ($event->getBlock()->getID() == 323 || $event->getBlock()->getID() == 63 || $event->getBlock()->getID() == 68) {
            $sign = $player->getLevel()->getTile($event->getBlock())->getText();
            $tag = ltrim($sign[1], TextFormat::AQUA . "タグ: " . TextFormat::RESET);
            $price = ltrim($sign[2], TextFormat::YELLOW . "販売価格: " . $api->getMonitorUnit());
            $description = $sign[3];
            if ($sign[0] == TextFormat::GREEN . "[TAGSHOP]") {
            	if ($api->Check($player) < $price) {
            		$player->sendMessage(TextFormat::YELLOW . "所持金が不足しています。");
            		return false;
            	}
            	if (!$this->buy[$name]) {
            		$player->sendMessage(TextFormat::GREEN . "本当にタグ: " . TextFormat::RESET . $tag . TextFormat::GREEN . "を購入しますか？");
            		$this->buy[$name] = true;
            		return true;
            	} else {
	            	if ($api->takeMoney($player, $price)) {
	            		$player->setNameTag(TextFormat::AQUA . "[" . TextFormat::RESET . $tag . TextFormat::AQUA . "] " . TextFormat::RESET . $name);
	            		$player->setDisplayName(TextFormat::AQUA . "[" . TextFormat::RESET . $tag . TextFormat::AQUA . "] " . TextFormat::RESET . $name);
	            		$this->data->set($name, $tag);
	            		$this->data->save();
	            		$player->sendMessage(TextFormat::GREEN . "タグを購入しました。");
	            		$this->buy[$name] = false;
	            		return true;
	            	} else {
	            		$player->sendMessage(TextFormat::RED . "タグ購入時にエラーが発生しました。");
	            		$this->buy[$name] = false;
	            		return false;
	            	}
	            }
            }
        }
	}

	public function onBreak(BlockBreakEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
        if ($event->getBlock()->getID() == 323 || $event->getBlock()->getID() == 63 || $event->getBlock()->getID() == 68) {
            $sign = $player->getLevel()->getTile($event->getBlock())->getText();
            if ($sign[0] == TextFormat::GREEN . "[TAGSHOP]") {
            	if (!$player->isOp()) {
            		$player->sendMessage(TextFormat::RED . "あなたはタグショップを破壊する権限がありません。");
            		$event->setCancelled();
            		return false;
            	}
            	return true;
            }
        }
	}

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		$name = $player->getName();
		if ($this->data->exists($name)) {
			$tag = $this->data->get($name);
    		$player->setNameTag(TextFormat::AQUA . "[" . TextFormat::RESET . $tag . TextFormat::AQUA . "] " . TextFormat::RESET . $name);
    		$player->setDisplayName(TextFormat::AQUA . "[" . TextFormat::RESET . $tag . TextFormat::AQUA . "] " . TextFormat::RESET . $name);
		}
		$this->buy[$name] = false;
	}
}
