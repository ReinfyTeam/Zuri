# Zuri-Rewrite
> Zuri-Rewrite is a new fork code improvement of original Zuri Anticheat.
**Zuri** is an anticheat made to protect the server from any may unfair advantages from the players. A powerful anticheat made to destroy hackers from your server for PocketMine-MP.

If you are interested with our projects, you may help us by:
- [Donate via Ko-Fi](https://ko-fi.com/xqwtxon)
- [Become a Patreon](https://patreon.com/xwertxy)

# Checks
| **Module Name**             | **Punishment Type**  | **Percentage of Accuracy** |
|-----------------------------|----------------------|----------------------------|
| AntiImmobile                   | Kick                 | 100% detect                |
| AutoClick                   | Kick                 | 100% detect                |
| RapidHit                    | Kick                 | 100% detect                |
| KillAura                    | Kick                 | 100% detect                |
| HitBox                      | Kick                 | 100% detect                |
| Reach                       | Kick                 | 90% detect                 |
| Fly                         | Kick                 | 100% detect                |
| NoClip                      | Kick                 | 100% detect                |
| NoWeb                       | Kick                 | 100% detect                |
| JetPack                     | Kick                 | 100% detect                |
| AirJump                     | Kick                 | 100% detect                |
| HighJump                    | Kick                 | 100% detect                |
| Glide                       | Kick                 | 100% detect                |
| AntiVoid                    | Kick                 | 95% detect                 |
| Speed                       | Kick                 | 99% detect                 |
| Jesus                       | Kick                 | 99% detect                 |
| AutoMidTP                   | Kick                 | 100% detect                |
| ClickTp                     | Kick                 | 100% detect                |
| Step                        | Kick                 | 100% detect                |
| AimAssist                   | Kick                 | 90% detect                 |
| AutoArmor                   | Kick                 | 90% detect                 |
| FastLadder                  | Kick                 | 80% detect                 |
| Spider                      | Kick                 | 90% detect                 |
| TriggerBot                  | Kick                 | 100% detect                |
| NoPacket                    | Kick                 | 100% detect                |
| Velocity/NoKB               | Kick                 | 100% detect                |
| ChestAura/ChestStealer      | Kick                 | 100% detect                |
| InventoryCleaner            | Kick                 | 100% detect                |
| InventoryMove               | Kick                 | 100% detect                |
| Timer                       | Flag/Kick            | 100% detect                |
| Phase                       | Flag                 | 100% detect                |
| VClip                       | Flag                 | 100% detect                |
| InstaBreak                  | Flag/Kick            | 100% detect                |
| Spam                        | CAPTCHA              | 100% detect                |
| Tower                       | Ban Immediately      | 100% detect                |
| Scaffold                    | Kick/Ban Immediately | 100% detect                |
| Nuker, FastBreak, FillBlock | Ban Immediately      | 100% detect                |

**BadPackets Total:** 17

# Plugin Developers
This is module using a special method that requires an API: If the server you are using a method intended for the digging of special players.

Example:
```php
// $player must instance of Player from PMMP //
$api = PlayerAPI::getInstance()->getAPIPlayer($player);
$api->setAttackSpecial(< true or false >);
$api->setBlocksBrokeASec(< it must is number >);
```


<hr>


This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.