<?php

namespace ReinfyTeam\Zuri\command;

use CortexPE\Commando\BaseCommand;
use ReinfyTeam\Zuri\config\ConfigPath;
use ReinfyTeam\Zuri\config\language\LanguagePath;
use ReinfyTeam\Zuri\utils\Utils;
use ReinfyTeam\Zuri\ZuriAC;
use pocketmine\command\CommandSender;

class ZuriCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission(Utils::readString(ZuriAC::getConfigManager()->getData(ConfigPath::ZURI_COMMAND_PERMISSION)));
        $this->setDescription(ZuriAC::getLanguageManager()->getCurrentLanguage()->translate(LanguagePath::ZURI_COMMAND_DESCRIPTION));
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