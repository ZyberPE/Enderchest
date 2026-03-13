<?php

namespace Echest;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

    private Config $config;
    private array $editing = [];

    public function onEnable(): void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
    }

    private function msg(string $key,array $replace=[]): string{

        $messages = $this->config->get("messages");
        $msg = $messages[$key] ?? "";
        $prefix = $messages["prefix"] ?? "";

        $msg = str_replace("{prefix}",$prefix,$msg);

        foreach($replace as $k=>$v){
            $msg = str_replace("{".$k."}",$v,$msg);
        }

        return $msg;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            $sender->sendMessage($this->msg("player-only"));
            return true;
        }

        if(!isset($args[0])){

            $sender->sendMessage($this->msg("opening-own"));

            $sender->getEnderInventory()->open($sender);

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

            $target->getEnderInventory()->open($sender);

            return true;
        }

        $sender->sendMessage($this->msg("player-not-found"));
        return true;
    }

}
