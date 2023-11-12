<?php

namespace nasiridrishi\primescoreboard\hook;

use pocketmine\plugin\PluginBase;

abstract class PrimeHook {

    public function __construct(protected PluginBase $plugin) {
    }

    /**
     * @return PluginBase
     */
    public function getPlugin(): PluginBase {
        return $this->plugin;
    }
}