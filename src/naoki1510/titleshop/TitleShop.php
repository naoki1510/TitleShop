<?php

namespace naoki1510\titleshop;

/** 
 * @todo remove not to use. 
 * いらないものを消す
 */
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Armor;
use pocketmine\item\Bow;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\level\Explosion;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use naoki1510\nametagapi\NameTagAPI;


class TitleShop extends PluginBase implements Listener
{
    /** @var string[] */
    private $cue = [];

    public function onEnable()
    {
		// 起動時のメッセージ
        $this->getLogger()->info("§eTitleShop was loaded.");
        // デフォルトファイル保存（まだ作ってない
        $this->saveDefaultConfig();
		// イベントリスナー登録
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    
    /* 購入処理系 */

    public function onPlayerTap(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        // スニークしてる時は無視
        if ($player->isSneaking()) return;
        // ブロックの取得
        $block = $e->getBlock();
        switch ($block->getId()) {
            // 看板のIDの時
            case Block::WALL_SIGN:
            case Block::SIGN_POST:
                $sign = $block->getLevel()->getTile($block->asPosition());
                // 看板の取得に失敗した時
                if (!$sign instanceof Sign) return;
                // 1行目がTitle, TitleShopじゃない時
                if (preg_match('/^(§[0-9a-fklmnor])*\[Title(Shop)?\]$/iu', trim($sign->getLine(0))) != 1) return;
                // コストをConfigから取得
                $cost = $this->getConfig()->get('cost', 1000);
                // 称号購入
                $this->buy($player);
                
                break;

            default:
                return;
                break;
        }
        // ブロック配置の防止
        $e->setCancelled();
    }

    public function onSignChange(SignChangeEvent $e)
    {
        if (preg_match('/^(§[0-9a-fklmnor])*\[?Title(Shop)?\]?$/iu', trim($e->getLine(0))) == 1) {
            if(!$e->getPlayer()->isOp()){
                $e->getPlayer()->sendMessage('You must be op to create shop sign.');
                $e->setLines(['§aYou must be op.', '§bYou must be op.', '§cYou must be op.', '§dYou must be op.']);
                return;
            }
            $this->reloadSign($e);
        }
    }

    /** @param SignChangeEvent|Sign $sign */
    public function reloadSign($sign)
    {
        try {
            if (preg_match('/^(§[0-9a-fklmnor])*\[?Title(Shop)?\]?$/iu', trim($sign->getLine(0))) == 1) {
                preg_match('/^(§[0-9a-fklmnor])*(.*)$/u', trim($sign->getLine(1)), $m);
                
                $sign->setLine(0, '§a[TitleShop]');
                $sign->setLine(1, '§l称号をつけることが出来ます');
                $sign->setLine(2, '§c一度の変更につき$' . $this->getConfig()->get('cost', 1000) . 'かかります。');
            }
        } catch (\BadMethodCallException $e) {
            // $signから文字を変更できなかった時
            $this->getLogger()->warning($e->getMessage());
        }

    }

    /** 購入 */
    public function buy(Player $player) : bool
    {
        $cost = $this->getConfig()->get('cost');
        // お金が足りるか
        if ((EconomyAPI::getInstance()->myMoney($player) ?? 0) < $cost) {
            $player->sendMessage('お金が足りません。');
            return false;
        }
        // キューが空の時
        if (empty($this->cue[$player->getName()])) {
            $this->cue[$player->getName()] = true;
            //購入確認フォーム
            $pk = new ModalFormRequestPacket();
            $pk->formId = INT32_MAX - 1510;
            $form['type'] = "custom_form";
            $form['title'] = '称号の変更';
            $form['content'] = [["type" => "input", "text" => "称号をここに入力してください。"]];
            $pk->formData = json_encode($form);
            $player->dataPacket($pk);
            return true;
        }
        return false;
    }

    /** 
     * フォーム受信
     */
    public function onRecievePacket(DataPacketReceiveEvent $ev)
    {
        $pk = $ev->getPacket();
        $player = $ev->getPlayer();

        if (!$pk instanceof ModalFormResponsePacket) return;
        if ($pk->formId !== INT32_MAX - 1510) return;

        $data = json_decode($pk->formData, true);
        if ($data === null){
            $player->sendMessage("購入をキャンセルしました。");
            $this->cue[$player->getName()] = null;
            return;
        } 
        if(empty($data[0]))
            NameTagAPI::getInstance()->setTag($this, $player, '', NameTagAPI::POS_LEFT);
            $player->sendMessage("Your tag was reset.");
        }else{
            NameTagAPI::getInstance()->setTag($this, $player, '[' . $data[0] . '§r]', NameTagAPI::POS_LEFT);
            $player->sendMessage("Your tag was changed.");
            // todo: reduce money
        }
        $this->cue[$player->getName()] = null;
    }
}