<?php

namespace nasiridrishi\primescoreboard;

use nasiridrishi\primescoreboard\animation\Animation;
use nasiridrishi\primescoreboard\animation\AnimationManager;
use nasiridrishi\primescoreboard\hook\PrimeHook;
use nasiridrishi\primescoreboard\hook\PrimePlaceHolderHook;
use nasiridrishi\primescoreboard\ScoreboardConfig;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class PrimeScoreboard extends PluginBase {

    /**
     * @var ScoreboardConfig[]
     */
    private array $scoreboardConfigs = [];

    private TaskHandler $scheduler;


    private ?PrimePlaceHolderHook $placeHolderHook = null;

    private static PrimeScoreboard $instance;
    private AnimationManager $animationManager;

    /**
     * @return PrimeScoreboard
     */
    public static function getInstance(): PrimeScoreboard {
        return self::$instance;
    }


    protected function onLoad(): void {
        self::$instance = $this;
        $this->animationManager = new AnimationManager();
    }

    protected function onEnable(): void {
        $this->schedule();
        $this->loadSBFromConfig();
        $this->loadAnimationsFromConfig();
        $this->placeHolderHook = $this->findHook("PrimePlaceHolder", PrimePlaceHolderHook::class);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        var_dump($command->getName());
        if($command->getName() === "sbreload"){
            foreach($this->getServer()->getOnlinePlayers() as $player){
                PlayerSession::getSession($player)->setScoreboard(null);
            }
            $this->reloadConfig();
            $this->animationManager->setAnimations([]);
            $this->loadSBFromConfig();
            $this->loadAnimationsFromConfig();
            $sender->sendMessage("Scoreboard reloaded!");
            $this->schedule();
            return true;
        }
        return false;
    }


    private function findHook(string $pluginName, string $hookClass, bool $required = false): ?PrimeHook {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName);
        if($plugin === null){
            if($required){
                $this->getLogger()->error("Could not find plugin " . $pluginName . "!");
                $this->getServer()->getPluginManager()->disablePlugin($this);
            }
            return null;
        }
        $this->getLogger()->info("Found supported plugin " . $pluginName . "!");
        return new $hookClass($plugin);
    }

    public function getPlaceHolderHook(): ?PrimePlaceHolderHook {
        return $this->placeHolderHook;
    }

    private function schedule(): void{
        if(isset($this->scheduler) and !$this->scheduler->isCancelled()){
            $this->scheduler->cancel();
        }
        $this->scheduler = $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{
            foreach($this->getServer()->getOnlinePlayers() as $player){
                $session = PlayerSession::getSession($player);
                $scoreboard = $session->getScoreboard();
                if($scoreboard !== null){
                    if($scoreboard->getConfig()->canShow($player)){
                        $scoreboard->update();
                    }else{
                        $this->setScoreboard($player);
                    }
                }else{
                    $this->setScoreboard($player);
                }
            }
        }), $this->getConfig()->get("refresh_rate", 20), 0);
    }

    private function loadSBFromConfig(): void{
        $this->saveDefaultConfig();
        foreach($this->getConfig()->get("scoreboards") as $objective => $scoreboard){
            $title = $scoreboard["title"];
            $lines = $scoreboard["lines"];
            $priority = $scoreboard["priority"];
            $world = $scoreboard["world"] ?? null;
            if($world !== null){
                $world = $this->getServer()->getWorldManager()->getWorldByName($world);
            }
            $permission = $scoreboard["permission"] ?? null;
            if(count($lines) > 15){
                throw new \InvalidArgumentException("Scoreboard " . $objective . " has more than 15 lines!");
            }
            $this->scoreboardConfigs[$objective] = new ScoreboardConfig($title, $lines, $priority, $world ?? null, $permission);
        }
    }

    private function loadAnimationsFromConfig(): void{
        foreach($this->getConfig()->get("animations") as $animationName => $animation){
            $lines = $animation["lines"];
            foreach($lines as $key => $line){
                $lines[$key] = TextFormat::colorize($line);
            }
            $this->animationManager->addAnimation($animationName, new Animation($lines));
        }
    }

    private function setScoreboard(Player $player): void{
        $priority = -1;
        $config = null;
        foreach($this->scoreboardConfigs as $scoreboardConfig){
            if($scoreboardConfig->getPriority() > $priority && $scoreboardConfig->canShow($player)){
                $priority = $scoreboardConfig->getPriority();
                $config = $scoreboardConfig;
            }
        }
        $config?->setScoreboard($player);
    }

    /**
     * @return AnimationManager
     */
    public function getAnimationManager(): AnimationManager {
        return $this->animationManager;
    }
}