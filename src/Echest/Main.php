<?php

namespace Echest;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class Main extends PluginBase{

    private Config $config;

    public function onEnable(): void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
    }

    private function msg(string $key, array $replace = []) : string{
        $msg = $this->config->get("messages")[$key] ?? "";
        $prefix = $this->config->get("messages")["prefix"] ?? "";

        $msg = str_replace("{prefix}", $prefix, $msg);

        foreach($replace as $k => $v){
            $msg = str_replace("{".$k."}", $v, $msg);
        }

        return $msg;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            $sender->sendMessage($this->msg("player-only"));
            return true;
        }

        if(!$sender->hasPermission("echest.use")){
            $sender->sendMessage($this->msg("no-permission"));
            return true;
        }

        if(!isset($args[0])){
            $sender->sendMessage($this->msg("viewing-own"));
            $sender->setCurrentWindow($sender->getEnderInventory());
            return true;
        }

        if(!$sender->hasPermission("echest.other")){
            $sender->sendMessage($this->msg("no-permission"));
            return true;
        }

        $search = strtolower($args[0]);

        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            if(stripos($player->getName(), $search) !== false){

                $sender->sendMessage($this->msg("viewing-player", [
                    "player" => $player->getName()
                ]));

                $sender->setCurrentWindow($player->getEnderInventory());
                return true;
            }
        }

        $offline = Server::getInstance()->getOfflinePlayer($args[0]);

        if($offline === null){
            $sender->sendMessage($this->msg("player-not-found"));
            return true;
        }

        $sender->sendMessage($this->msg("offline-loaded", [
            "player" => $offline->getName()
        ]));

        $sender->setCurrentWindow($offline->getEnderInventory());

        return true;
    }
}
