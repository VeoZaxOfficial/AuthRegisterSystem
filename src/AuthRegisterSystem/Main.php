<?php

namespace AuthRegisterSystem;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\scheduler\PluginTask;

class Main extends PluginBase implements Listener {

    public $data;
    private $authenticated = [];
    public $joinData = [];
    private $owner;
    private $loginAttempts = [];

    public function onEnable() {
        @mkdir($this->getDataFolder());

$this->saveDefaultConfig();
$this->owner = strtolower($this->getConfig()->get("owner"));

$this->data = new Config($this->getDataFolder() . "players.json", Config::JSON);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getServer()->getScheduler()->scheduleRepeatingTask(
            new AuthTask($this),
            20
        );
    }

    public function checkAuthTimeout(){

        foreach($this->getServer()->getOnlinePlayers() as $player){

            $name = strtolower($player->getName());

            if(!isset($this->joinData[$name])) continue;

            $stored = $this->getStoredName($name);
            $info = $stored !== null ? $this->data->get($stored) : null;

            $isRegistered = $info !== null && isset($info["password"]);
            $isAuth = $stored !== null && isset($this->authenticated[strtolower($stored)]);

            $time = time() - $this->joinData[$name]["time"];

            if(!$isRegistered){
                if($time >= 60){
                    $player->kick("", $this->msg("register_timeout"));
                }
                continue;
            }

            if(!$isAuth){
                if($time >= 60){
                    $player->kick("", $this->msg("login_timeout"));
                }
            }
        }
    }

    public function getStoredName($name){
        foreach($this->data->getAll() as $stored => $info){
            if(strtolower($stored) === strtolower($name)){
                return $stored;
            }
        }
        return null;
    }

private function msg($key, $replace = []){
    $messages = $this->getConfig()->get("messages");

    if(!isset($messages[$key])){
        return "Message not found: " . $key;
    }

    $msg = $messages[$key];

    if(isset($messages["prefix"])){
        $replace["prefix"] = $messages["prefix"];
    }

    foreach($replace as $k => $v){
        $msg = str_replace("{" . $k . "}", $v, $msg);
    }

    return $msg;
}

    private function isAuthenticated(Player $player){
        $name = strtolower($player->getName());

        $stored = $this->getStoredName($name);
        if($stored === null) return false;

        return isset($this->authenticated[strtolower($stored)]);
    }

    public function onJoin(PlayerJoinEvent $event){

        $player = $event->getPlayer();
        $name = strtolower($player->getName());

        unset($this->joinData[$name], $this->loginAttempts[$name]);

        $this->loginAttempts[$name] = 0;
        $this->joinData[$name] = ["time" => time()];

        $stored = $this->getStoredName($name);

        if($stored === null){

            $this->data->set($player->getName(), []);
            $this->data->save();

            $player->sendMessage($this->msg("register_prompt"));
            return;
        }

        $info = $this->data->get($stored);

        unset($this->authenticated[strtolower($stored)]);

        if(isset($info["password"])){
            $player->sendMessage($this->msg("login_prompt"));
            return;
        }

        $player->sendMessage($this->msg("register_prompt"));
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){

        if(!$sender instanceof Player) return true;

        $name = strtolower($sender->getName());
        $stored = $this->getStoredName($name);

        switch(strtolower($cmd->getName())){

            case "register":
                if($stored === null) return true;

                $info = $this->data->get($stored);

                if(isset($info["password"])){
                    $sender->sendMessage($this->msg("already_registered"));
                    return true;
                }

                if(count($args) < 1 || strlen($args[0]) < 6){
                    $sender->sendMessage($this->msg("register_usage"));
                    return true;
                }

                $info["password"] = password_hash($args[0], PASSWORD_DEFAULT);
                $this->data->set($stored, $info);
                $this->data->save();

                $this->authenticated[strtolower($stored)] = true;
                unset($this->joinData[$name]);

                $sender->sendMessage($this->msg("register_success", ["password" => $args[0]]));
                return true;

            case "login":
                if($stored === null) return true;

                $info = $this->data->get($stored);

                if(!isset($info["password"])){
                    $sender->sendMessage($this->msg("not_secured"));
                    return true;
                }

                if(!isset($this->loginAttempts[$name])){
                    $this->loginAttempts[$name] = 0;
                }

                if(count($args) < 1 || !password_verify($args[0], $info["password"])){

                    $this->loginAttempts[$name]++;

                    $remaining = 3 - $this->loginAttempts[$name];

                    if($remaining > 0){
                        $sender->sendMessage($this->msg("wrong_password", ["attempts" => $remaining]));
                    }

                    if($this->loginAttempts[$name] >= 3){
                        $sender->kick($this->msg("too_many_attempts"));
                        unset($this->loginAttempts[$name]);
                    }

                    return true;
                }

                $this->authenticated[strtolower($stored)] = true;
                unset($this->joinData[$name], $this->loginAttempts[$name]);

                $sender->sendMessage($this->msg("login_success"));
                return true;

            case "changepassword":
                if($stored === null){
                    $sender->sendMessage($this->msg("not_registered"));
                    return true;
                }

                $info = $this->data->get($stored);

                if(!isset($info["password"])){
                    $sender->sendMessage($this->msg("not_secured"));
                    return true;
                }

                if(!isset($this->authenticated[strtolower($stored)])){
                    $sender->sendMessage($this->msg("not_logged_in"));
                    return true;
                }

                if(count($args) < 2 || strlen($args[0]) < 6 || $args[0] !== $args[1]){
                    $sender->sendMessage($this->msg("password_error"));
                    return true;
                }

                $info["password"] = password_hash($args[0], PASSWORD_DEFAULT);
                $this->data->set($stored, $info);
                $this->data->save();

                $sender->sendMessage($this->msg("password_changed"));
                return true;

            case "auth":
                if(strtolower($sender->getName()) !== $this->owner){
                    $sender->sendMessage($this->msg("auth_no_permission"));
                    return true;
                }

                if(count($args) < 2 || strtolower($args[0]) !== "remove"){
                    $sender->sendMessage($this->msg("auth_usage"));
                    return true;
                }

                $target = $this->getStoredName(strtolower($args[1]));

                if($target === null){
                    $sender->sendMessage($this->msg("auth_not_found"));
                    return true;
                }

                $this->data->remove($target);
                $this->data->save();
                unset($this->authenticated[strtolower($target)]);

                $sender->sendMessage($this->msg("auth_removed", ["player" => $target]));
                return true;
        }

        return true;
    }

    public function onMove(PlayerMoveEvent $event){
        if(!$this->isAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    public function onCommandProcess(PlayerCommandPreprocessEvent $event){
        $cmd = explode(" ", strtolower($event->getMessage()))[0];

        if(in_array($cmd, ["/login","/register","/changepassword"])) return;

        if(!$this->isAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    public function onDamage(EntityDamageEvent $event){
        if($event->getEntity() instanceof Player){
            if(!$this->isAuthenticated($event->getEntity())){
                $event->setCancelled(true);
            }
        }
    }

    public function onBreak(BlockBreakEvent $event){
        if(!$this->isAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    public function onPlace(BlockPlaceEvent $event){
        if(!$this->isAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        $name = strtolower($event->getPlayer()->getName());

        unset($this->joinData[$name], $this->loginAttempts[$name]);

        $stored = $this->getStoredName($name);
        if($stored !== null){
            unset($this->authenticated[strtolower($stored)]);
        }
    }
}

class AuthTask extends PluginTask {

    public function __construct(Main $plugin){
        parent::__construct($plugin);
    }

    public function onRun($tick){
        $this->getOwner()->checkAuthTimeout();
    }
}