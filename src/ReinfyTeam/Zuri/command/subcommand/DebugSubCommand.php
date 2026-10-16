<?php

namespace ReinfyTeam\Zuri\command\subcommand;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\config\language\LanguagePath;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\ZuriAC;

class DebugSubCommand extends BaseSubCommand {

    protected function prepare() : void {
        $this->setPermission(Utils::readString(ZuriAC::getConfigManager()->getData(ConfigPath::ZURI_COMMAND_PERMISSION)));
        $this->setDescription(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::DEBUG_SUBCOMMAND_DESCRIPTION));
        
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param array<string> $args
     * @return void
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
        
    }
}