<?php

namespace nasiridrishi\primescoreboard\animation;

use nasiridrishi\primescoreboard\PrimeScoreboard;

class AnimationManager {

    private static AnimationManager $instance;

    /**
     * @var Animation[]
     */
    private array $animations = [];

    public function __construct() {
        self::$instance = $this;
    }

    public static function getInstance(): AnimationManager{
        return self::$instance;
    }

    public function addAnimation(string $name, Animation $animation): void{
        if(isset($this->animations[$name])){
            PrimeScoreboard::getInstance()->getLogger()->warning("Tried to register animation with name $name but it already exists");
        }
        $name = "animation:" . strtolower($name);
        $this->animations[$name] = $animation;
        PrimeScoreboard::getInstance()->getLogger()->info("Registered animation with name $name");
    }

    public function setAnimations(string $c): string {
        if(preg_match_all("/\{([^}]+)\}/", $c, $matches)){
            foreach($matches[1] as $match){
                $animation = $this->getAnimation($match);
                if($animation !== null){
                    $c = str_replace("{" . $match . "}", $animation->getNext(), $c);
                }else{
                    PrimeScoreboard::getInstance()->getLogger()->warning("Could not find animation with name " . $match);
                }
            }
        }
        return $c;
    }

    public function getAnimation(string $name): ?Animation{
        return $this->animations[$name] ?? null;
    }
}