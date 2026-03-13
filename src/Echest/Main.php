<?php

namespace Echest;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\event\inventory\InventoryCloseEvent;

class Main extends PluginBase implements Listener{

    private Config $config;
    private array $editing = [];

    public function onEnable(): void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
    }

    private function msg(string $key, array $replace = []) : string{

        $messages = $this->config->get("messages");
        $msg = $messages[$key] ?? "";
        $prefix = $messages["prefix"] ?? "";

        $msg = str_replace("{prefix}",$prefix,$msg);

        foreach($replace as $k=>$v){
            $msg = str_replace("{".$k."}",$v,$msg);
        }

        return $msg;
    }

    private function openVirtualChest(Player $player, array $contents): SimpleInventory{

        $inv = new SimpleInventory(27);
        $inv->setContents($contents);

        $player->setCurrentWindow($inv);

        return $inv;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            $sender->sendMessage($this->msg("player-only"));
            return true;
        }

        if(!isset($args[0])){

            $sender->sendMessage($this->msg("opening-own"));

            $inv = $this->openVirtualChest($sender,$sender->getEnderInventory()->getContents());

            $this->editing[$sender->getName()] = [
                "self" => true,
                "inventory" => $inv
            ];

            return true;
        }

        if(!$sender->hasPermission("echest.other")){
            $sender->sendMessage($this->msg("no-permission"));
            return true;
        }

        $target = Server::getInstance()->getPlayerByPrefix($args[0]);

        if($target instanceof Player){

            $sender->sendMessage($this->msg("viewing-player",[
                "player"=>$target->getName()
            ]));

            $inv = $this->openVirtualChest($sender,$target->getEnderInventory()->getContents());

            $this->editing[$sender->getName()] = [
                "online"=>$target,
                "inventory"=>$inv
            ];

            return true;
        }

        $offline = Server::getInstance()->getOfflinePlayer($args[0]);

        if(!$offline->hasPlayedBefore()){
            $sender->sendMessage($this->msg("player-not-found"));
            return true;
        }

        $uuid = $offline->getUniqueId()->toString();
        $file = $this->getServer()->getDataPath()."playerdata/".$uuid.".dat";

        $serializer = new BigEndianNbtSerializer();
        $nbt = $serializer->read(file_get_contents($file))->mustGetCompoundTag();

        $inventory = [];

        if($nbt->getTag("EnderChestInventory") !== null){

            foreach($nbt->getListTag("EnderChestInventory") as $itemNBT){

                $inventory[$itemNBT->getByte("Slot")] = Item::nbtDeserialize($itemNBT);

            }
        }

        $sender->sendMessage($this->msg("viewing-player",[
            "player"=>$offline->getName()
        ]));

        $inv = $this->openVirtualChest($sender,$inventory);

        $this->editing[$sender->getName()] = [
            "file"=>$file,
            "inventory"=>$inv
        ];

        return true;
    }

    public function onInventoryClose(InventoryCloseEvent $event): void{

        $player = $event->getPlayer();

        if(!isset($this->editing[$player->getName()])){
            return;
        }

        $data = $this->editing[$player->getName()];
        $inv = $data["inventory"];

        if(isset($data["self"])){

            $player->getEnderInventory()->setContents($inv->getContents());

        }elseif(isset($data["online"])){

            $data["online"]->getEnderInventory()->setContents($inv->getContents());

        }elseif(isset($data["file"])){

            $serializer = new BigEndianNbtSerializer();
            $nbt = $serializer->read(file_get_contents($data["file"]))->mustGetCompoundTag();

            $items = [];

            foreach($inv->getContents() as $slot=>$item){

                $tag = $item->nbtSerialize();
                $tag->setByte("Slot",$slot);
                $items[] = $tag;

            }

            $nbt->setTag("EnderChestInventory",$items);

            file_put_contents($data["file"],$serializer->write(new TreeRoot($nbt)));
        }

        unset($this->editing[$player->getName()]);

        $player->sendMessage($this->msg("saved"));
    }
}
