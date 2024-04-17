# Zuri Pocketmine-MP Anticheat
**Zuri** is an anticheat made to protect the server from any may unfair advantages from the players. A powerful anticheat made to destroy hackers from your server for PocketMine-MP.

> ⚠️ **Spoon or Fork of Pocketmine-MP are not supported.** Do not try to create an issue, they will be closed automatically.

😁 If you are interested with our projects, you may help us by:
- [Donate via Ko-Fi](https://ko-fi.com/xqwtxon)
- [Become a Patreon](https://patreon.com/xwertxy)

Also, adding :star: a **Star** is also appreciated. ✨

🤔 Do you struggling with **bugs and issues?** Don't hesitate to tell us about it by [creating an issue](https://github.com/ReinfyTeam/Zuri-Rewrite/issues) or you may join us on our official [discord server](https://discord.com/invite/7u7qKsvSxg)!

# Plugin Developers
This is module using a special method that requires an API: If the server you are using a method intended for the digging of special players. ☢

The full documentation can be found in the [wiki](https://github.com/ReinfyTeam/Zuri-Rewrite/wiki).

Example:
```php
// $player must instance of Player from PMMP //
$player = API::getPlayer($player);
$api->setAttackSpecial(< true or false >);
$api->setBlocksBrokeASec(< it must is number >);
```

# Why?
- This plugin has total of 40+ checks that can catch hacker more efficient and no more false-positive! 😏

> ![Zuri Anticheat Meme](https://github.com/ReinfyTeam/Zuri-Rewrite/assets/143252455/223ce4ad-8dbe-4f87-9900-3af95135afe3)
>
> Zuri can catch hacker efficiently, with over **40+ check modules**. Unlike other **$100 Anticheat**, it is more systematic, lightweight, and easy to configure. It's too bad right? 🤦

# Features
- You can easily configure everything in the config. ✅
  - Configure easily the max violations and checks and more! ⚙️
- It is more **lightweight** compared to paid anticheat. You don't have to struggle about the performance, with this anticheat, it can possible block them all easily! 💰
- ✨ It is easy to use when it comes at the game, you can easily debug things, manage them all at the game, and **disable checks** according to your command. 
- ❌ Limit players joining by their ip limit, you can change and configure on how many players can join with same ip address.
- 🌟 It also checks the player if they are using a **Proxy or VPN** *(optional)*
- 💥 You can manage plugin at in-game using **UI** by using command! `/zuri ui`
# Checks
| **Module Name**             | **Punishment Type**  | **Percentage of Accuracy** |
|-----------------------------|----------------------|----------------------------|
| AntiBot                       | Kick Immediately   | 100% detect                |
| EditionFaker                 | Kick Immediately   | 100% detect                |
| ProxyBot                 | Kick Immediately   | 100% detect                |
| AntiImmobile                   | Kick                 | 100% detect                |
| AutoClick                   | Kick                 | 90% detect                |
| RapidHit                    | Kick                 | 95% detect                |
| KillAura                    | Kick                 | 90% detect                |
| HitBox                      | Kick                 | 70% detect                |
| Reach                       | Kick                 | 97% detect                 |
| Fly                         | Kick                 | 98% detect                |
| NoClip                      | Kick                 | 80% detect                |
| NoWeb                       | Kick                 | 70% detect                |
| JetPack                     | Kick                 | 97% detect                |
| AirJump                     | Kick                 | 90% detect                |
| HighJump                    | Kick                 | 99% detect                |
| Glide                       | Kick                 | 80% detect                |
| AntiVoid                    | Kick                 | 60% detect                 |
| Speed                       | Kick                 | 99% detect                 |
| Jesus                       | Kick                 | 99% detect                 |
| AutoMidTP                   | Kick                 | 70% detect                |
| ClickTP                     | Kick                 | 74% detect                |
| Step                        | Kick                 | 78% detect                |
| AimAssist                   | Kick                 | 85% detect                 |
| AutoArmor                   | Kick                 | 92% detect                 |
| FastLadder                  | Kick                 | 89% detect                 |
| Spider                      | Kick                 | 89% detect                 |
| TriggerBot                  | Kick                 | 90% detect                |
| NoPacket                    | Kick                 | 86% detect                |
| Velocity/NoKB               | Kick                 | 99% detect                |
| ChestAura/ChestStealer      | Kick                 | 70% detect                |
| InventoryCleaner            | Kick                 | 70% detect                |
| InventoryMove               | Kick                 | 90% detect                |
| Timer                       | Flag/Kick            | 70% detect                |
| Phase                       | Flag                 | 90% detect                |
| VClip                       | Flag                 | 94% detect                |
| ImpossiblePitch                       | Flag                 | 93% detect                |
| MessageSpoof                       | Flag                 | 100% detect                |
| InvalidPackets                       | Flag                 | 90% detect                |
| InstaBreak                  | Flag/Kick            | 100% detect                |
| Spam                        | CAPTCHA              | 100% detect                |
| Tower                       | Ban Immediately      | 74% detect                |
| Scaffold                    | Kick/Ban Immediately | 70% detect                |
| Nuker                       | Ban Immediately      | 90% detect                |
| FastBreak                       | Ban Immediately      | 100% detect                |
| FillBlock                       | Ban Immediately      | 98% detect                |
| WrongMining                       | Ban Immediately      | 90% detect                |

**Total Checks:** 64

<hr>


> **This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser *General Public License* as published by the __Free Software Foundation__, either version 3 of the License, or (at your option) any later version.**