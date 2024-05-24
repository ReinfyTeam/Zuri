# Zuri Pocketmine-MP Anticheat
**Zuri** is an anticheat made to protect the server from any may unfair advantages from the players. A powerful anticheat made to destroy hackers from your server for PocketMine-MP.

**Zuri** attempts to enforce "vanilla Minecraft" mechanics, as well as preventing players from abusing weaknesses in Minecraft or its protocol, making your server more safe. Organized in different sections, various checks are performed to test players doing, covering a wide range including flying and speeding, fighting hacks, fast block breaking and nukers, inventory hacks, chat spam and other types of malicious behaviour.

> ⚠️ **Spoon or Fork of Pocketmine-MP are not supported.** Do not try to create an issue, they will be closed automatically.

😁 If you are interested with our projects, you may help us by:
- [Donate via Ko-Fi](https://ko-fi.com/xqwtxon)
- [Become a Patreon](https://patreon.com/xwertxy)

Also, adding :star: a **Star** is also appreciated. ✨

🤔 Do you struggling with **bugs and issues?** Don't hesitate to tell us about it by [creating an issue](https://github.com/ReinfyTeam/Zuri-Rewrite/issues) or you may join us on our official [discord server](https://discord.com/invite/7u7qKsvSxg)!

> ☢ For Plugin Developers:
> The full documentation about API and it's usage is can be found in the [github wiki](https://github.com/ReinfyTeam/Zuri/wiki).

# Why?
- This plugin has total of 40+ checks that can catch hacker more efficient and no more false-positive! 😏

> ![Zuri Anticheat Meme](/meme.jpg)
>
> Zuri can catch hacker efficiently, with over **40+ check modules**. Unlike other **$100 Anticheat**, it is more systematic, lightweight, and easy to configure. It's too good right? 🤦

# Features
- You can easily configure everything in the config. ✅
  - Configure easily the max violations and checks and more! ⚙️
- It is more **lightweight** compared to paid anticheat. You don't have to struggle about the performance, with this anticheat, it can possible block them all easily! 💰
- ✨ It is easy to use when it comes at the game, you can easily debug things, manage them all at the game, and **disable checks** according to your command. 
- ❌ Limit players joining by their ip limit, you can change and configure on how many players can join with same ip address. *(optional)*
- 🌟 It also checks the player if they are using a **Proxy or VPN** *(optional)*
- 💥 You can manage plugin at in-game using **UI** by using command! `/zuri ui`

# Current Modules
**BETA** - means to be in testing, and to be optimize in the next version. <br>
**DISABLED** - means the code is not working or has a false-positive in certain methods. <br>
**OPTIONAL** - means this is optional optimization checks for certain purposes. <br>

- **AimAssist** (BETA)
    - **A:** Check if the player yaw is normalized and valid on the auth input.
    - **B:** Check if the player pitch is normalized and valid on the auth input.
    - **C:** Check if the player exceeds the pitch and yaw limit.
    - **D:** Calculate the possible yaw and pitch limit.
- **Crasher**
   - **A:** Check if the player is on impossible y-axis.
- **FastDrop**
   - **A:** Check the time difference every drops.
- **FastEat**
   - **A:** Check the animation time difference when the item is consumed.
- **FastThrow**
   - **A:** Check if the player is throwing so fast, just like java edition but different in bedrock edition.
- **ImpossiblePitch**
   - **A:** Check if the player pitch is valid.
- **InvalidPackets**
   - **A:** Check the packet consistency is balance against the auth input and move event.
- **MessageSpoof**
   - **A:** Checks if the message exceeds the minecraft chat limit.
- **SelfHit**
   - **A:** Check if the entity id are same with damager id.
- **Regen** (BETA)
   - **A:** Check the heal rate is valid for the damage.
   - **B:** Check the consistency and tolerance of heal rate is valid when player regenerated hearts.
- **Timer** (DISABLED)
   - **A:** Check the packet time consistency if it is balanced.
   - **B:** Check the ticks between packet is balanced.
- **Instabreak** 
   - **A:** Check the block break information and calculate the possible expected time to break the block.
- **WrongMining**
   - **A:** Check the block break per seconds is valid for their gamemode.
- **BlockReach**
   - **A:** Check if the player is interacting block that is not currently interactable.
- **FillBlock**
   - **A:** Check if the player is placing many blocks in one instance.
- **Tower** (BETA)
   - **A:** Check if the player is actually placing blocks upwards.
- **Spam**
   - **A:** Check time consistency sending to many messages per seconds.
   - **B:** Check characters that are repeated on the last message.
- **FastBow** (BETA)
   - **A:** Check ticks consistency of the bow and calculate the time difference of the last shoot.
- **ImpossibleHit**
    - **A:** Check if the player has any opening chest or eating a food while hitting the entity.
- **Autoclick**
    - **A:** Check the average speed of ticks clicked and calculate the average deviation.
    - **B:** Check the last ticks clicked per hit.
    - **C:** Check if the animation swing time difference are balanced.
- **Killaura**
    - **A:** Check if the player is breaking block while attacking.
    - **B:** Calculate the delta pitch and yaw is valid.
    - **C:** Check multiple entities were in combat by player has a valid distance to attack the another entity.
    - **D:** Check player if it is actually hand has swingging animation or not.
    - **E:** Checks the range of the entities if it is valid.
- **Reach**
    - **A:** Check distance between the player, check also if the player is in top.
    - **B:** Check distance squared between the player. Check also gamemode for possible reach distance.
    - **C:** Check eye height and cuboid if it is actually hitting the player legitable.
- **Fly**
    - **A:** Check if the player is moving the air upwards.
    - **B:** Check bad packet flags exploit affects the fly ability.
    - **C:** Check block surroundings and air ticks if the player is legitable to fly.
- **AutoArmor** (BETA)
    - **A:** Check if they opened actually the inventory.
- **ChestAura**
    - **A:** Check if player is opening so fast the inventory and too many transactions in one 1 seconds.
- **Cheststealer**
    - **A:** Check if the player is legitably getting items not so fast.
- **InventoryCleaner** (BETA)
    - **A:** Check if the player is dropping many items once.
- **InventoryMove**
    - **A:** Check if the player is moving when inventory is open.
- **AirMovement**
    - **A:** Check if the player is moving air upwards legitably.
- **AntiImmobile** (BETA)
    - **A:** Check the player if has a immobile flags and moving.
- **AntiVoid** (BETA)
     - **A:** Check y is getting back to last y impossibly.
- **ClickTP** (BETA)
     - **A:** Check if the player is teleporting without actually use of teleportation.
- **FastLadder** (BETA)
     - **A:** Check if the player is climbing fast in ladders.
- **FastSwim** (DISABLED)
     - **A:** Check if the player swimming so fast.
- **Jesus** (DISABLED)
     - **A:** Check the player is walking through water.
- **Omnisprint** (DISABLED)
     - **A:** Check keys input by the player.
- **Phase**
     - **A:** Check if the player stucks at the block, teleport when to a safe place.
- **Speed**
     - **A:** Calculates the possible speed of the player.
- **Spider** (DISABLED)
     - **A:** Check if the player is climbing or abnormally moving upwards to non-climbable blocks.
- **Step** (DISABLED)
     - **A:** Check if the player is moving upwards so fast.
- **Velocity**
     - **A:** Check the motion velocity of the player, calculate possible horizontal and vertical knockback.
- **AntiBot**
     - **A:** Check if the player has a valid device os.
     - **B:** Check if the player is using hack client a.k.a. toolbox.
- **EditionFaker**
     - **A:** Check if the player has a valid platform.
- **ProxyBot** (OPTIONAL)
     - **A:** Check player if it is using proxy, tor or other internet exploit ip services.
- **Scaffold** (BETA)
     - **A:** Check if the hand item is null while placing multiple blocks in 1 instance.
     - **B:** Check pitch if it is valid when placing blocks.
     - **C:** Check pitch if it is valid and the block distance is valid.
     - **D:** Check if the hand item is null while placing blocks.
- **Tower** (BETA)
     - **A:** Check if the player moving upwards straight while placing blocks check if the player is actually placing the block downwards.

<hr>

> **This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser *General Public License* as published by the __Free Software Foundation__, either version 3 of the License, or (at your option) any later version.**
