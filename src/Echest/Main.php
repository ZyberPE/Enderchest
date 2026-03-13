<?php

namespace Echest;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
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

        $msg = str_replace("{prefix}", $prefix, $msg);

        foreach($replace as $k => $v){
            $msg = str_replace("{".$k."}", $v, $msg);
        }

        return $msg;
    }

    private function findPlayerPartial(string $name): ?Player{
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            if(stripos($player->getName(), $name) !== false){
                return $player;
            }
        }
        return null;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            $sender->sendMessage($this->msg("player-only"));
            return true;
        }

        if(!isset($args[0])){
            $sender->sendMessage($this->msg("opening-own"));
            $sender->setCurrentWindow($sender->getEnderInventory());
            return true;
        }

        if(!$sender->hasPermission("echest.other")){
            $sender->sendMessage($this->msg("no-permission"));
            return true;
        }

        $target = $this->findPlayerPartial($args[0]);

        if($target instanceof Player){

            $sender->sendMessage($this->msg("viewing-player",[
                "player"=>$target->getName()
            ]));

            $sender->setCurrentWindow($target->getEnderInventory());
            return true;
        }

        $offline = Server::getInstance()->getOfflinePlayer($args[0]);

        if(!$offline->hasPlayedBefore()){
            $sender->sendMessage($this->msg("player-not-found"));
            return true;
        }

        $uuid = $offline->getUniqueId()->toString();
        $file = $this->getServer()->getDataPath()."playerdata/".$uuid.".dat";

        if(!file_exists($file)){
            $sender->sendMessage($this->msg("player-not-found"));
            return true;
        }

        $serializer = new BigEndianNbtSerializer();
        $nbt = $serializer->read(file_get_contents($file))->mustGetCompoundTag();

        $inventory = new SimpleInventory(27);

        if($nbt->getTag("EnderChestInventory") !== null){

            foreach($nbt->getListTag("EnderChestInventory") as $itemNBT){

                $inventory->setItem(
                    $itemNBT->getByte("Slot"),
                    Item::nbtDeserialize($itemNBT)
                );
            }
        }

        $sender->setCurrentWindow($inventory);

        $this->editing[$sender->getName()] = [
            "file"=>$file,
            "inventory"=>$inventory
        ];

        $sender->sendMessage($this->msg("offline-loaded",[
            "player"=>$offline->getName()
        ]));

        return true;
    }

    public function onInventoryClose(InventoryCloseEvent $event): void{

        $player = $event->getPlayer();

        if(!isset($this->editing[$player->getName()])){
            return;
        }

        $data = $this->editing[$player->getName()];
        $inventory = $data["inventory"];
        $file = $data["file"];

        $serializer = new BigEndianNbtSerializer();
        $nbt = $serializer->read(file_get_contents($file))->mustGetCompoundTag();

        $items = [];

        foreach($inventory->getContents() as $slot=>$item){

            $tag = $item->nbtSerialize();
            $tag->setByte("Slot",$slot);
            $items[] = $tag;
        }

        $nbt->setTag("EnderChestInventory",$items);

        file_put_contents($file,$serializer->write(new TreeRoot($nbt)));

        unset($this->editing[$player->getName()]);

        $player->sendMessage($this->msg("saved"));
    }
}
