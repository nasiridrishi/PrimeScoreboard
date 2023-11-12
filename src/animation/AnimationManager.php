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

    /**
     * @param array $animations
     */
    public function setAnimations(array $animations): void {
        $this->animations = $animations;
    }

    public function getAnimation(string $name): ?Animation{
        return $this->animations[$name] ?? null;
    }
}