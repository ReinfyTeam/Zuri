# Zuri-Rewrite

> Zuri-Rewrite is a new fork code improvement of original Zuri Anticheat.

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
- It is more **lightweight** compared to paid anticheat. You dont have to struggle about the performance, with this anticheat, it can possible block them all easily! 💰
- ✨ It is easy to use when it comes at the game, you can easily debug things, manage them all at the game, and **disable checks** according to your wishes. 
- ❌ Limit players joining by their ip limit, you can change it how many players can join with same ip address.
- 🌟 It also checks the player if they are using a **Proxy or VPN**
- 💥 You can customize all **messages** and prefix easily with language manager!

# Checks
| **Module Name**             | **Punishment Type**  | **Percentage of Accuracy** |
|-----------------------------|----------------------|----------------------------|
| AntiBot                       | Kick Immediately   | 100% detect                |
| EditionFaker                 | Kick Immediately   | 100% detect                |
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
| Tower                       | Ban Immediately      | 74% detect                |
| Scaffold                    | Kick/Ban Immediately | 70% detect                |
| Nuker                       | Ban Immediately      | 100% detect                |
| FastBreak                       | Ban Immediately      | 100% detect                |
| FillBlock                       | Ban Immediately      | 100% detect                |
| WrongMining                       | Ban Immediately      | 100% detect                |

**BadPackets Total:** 17

<hr>


> **This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser *General Public License* as published by the __Free Software Foundation__, either version 3 of the License, or (at your option) any later version.**
