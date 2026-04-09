# Zuri Pocketmine-MP Anticheat 🛡️
**Zuri** is an anticheat made to protect the server from any may unfair advantages from the players. A powerful anticheat made to destroy hackers from your server for PocketMine-MP.

**Zuri** attempts to enforce "vanilla Minecraft" mechanics, as well as preventing players from abusing weaknesses in Minecraft or its protocol, making your server more safe. 

Organized in different sections, various checks are performed to test player behavior across movement, combat, block interaction, inventory handling, chat abuse, and packet consistency. 

The plugin is designed to combine fast main-thread checks with heavier calculations that can be offloaded when needed, so servers can keep detection active without turning every event into a lag spike.

> ⚠️ **Spoon or Fork of Pocketmine-MP are not supported.** Do not try to create an issue, it will closed automatically.

😁 If you are interested with our projects, you may help us by:
- [Donate via Ko-Fi](https://ko-fi.com/xqwtxon)
- [Become a Patreon](https://patreon.com/xwertxy)

Also, adding :star: a **Star** is also appreciated. ✨

🤔 Do you struggling with **bugs and issues?** Don't hesitate to tell us about it by [creating an issue](https://github.com/ReinfyTeam/Zuri-Rewrite/issues) or you may join us on our official [discord server](https://discord.com/invite/7u7qKsvSxg)!

> ☢ For Plugin Developers:
> The full documentation about API and it's usage is can be found in the [github wiki](https://github.com/ReinfyTeam/Zuri/wiki).

> ![Zuri Anticheat Meme](https://raw.githubusercontent.com/ReinfyTeam/Zuri/main/meme.jpg)
>
> Zuri can catch hacker efficiently, with over **40+ check modules**. Unlike other **$100 Anticheat**, it is more systematic, lightweight, and easy to configure. It's too good right? 🤦

# Features
- This plugin has total of 40+ checks that cover the most common public cheat categories, including speed, fly, reach, scaffold, timer, and packet manipulation.
- Checks are organized by module groups, and new modules can self-declare their name, subtype, and correlation group for easier extension.
- You can easily configure everything in the config. ✅
   - Configure easily the max violations, punishment type, thresholds, bypass rules, and module constants without editing source code.
- You can switch ready tuning presets for combat-sensitive detections (`custom`, `low-latency`, `high-latency`) from `zuri.tuning-presets.active`.
   - This is useful when your server has either very stable low ping PvP traffic or mixed high-latency public traffic.
- It is more **lightweight** compared to paid anticheat. You don't have to struggle about the performance, with this anticheat, it can possible block them all easily! 💰
   - The checks are split so the simple ones stay direct while heavier calculations are evaluated through the async pipeline, with payload identity handled by module name and subtype.
- ✨ It is easy to use when it comes at the game, you can easily debug things, manage them all at the game, and **disable checks** according to your command.
   - This is useful when a server owner wants to test a module, reduce false positives, or temporarily isolate a problem during maintenance.
- Cross-check correlation can delay high-impact punishments until enough behavior groups are seen in the configured time window.
  - This helps reduce over-aggressive punishments when only one detection family is active.
- ❌ Limit players joining by their ip limit, you can change and configure on how many players can join with same ip address. *(optional)*
   - This helps reduce duplicate account flooding and simple bot joins from the same network.
- 🌟 It also checks the player if they are using a **Proxy or VPN** *(optional)*
   - This can be used to block suspicious network sources before they reach gameplay checks.
- ‼ It also have support for ProxyUDP. *(on development stage)*
   - That is intended for environments that need proxy-aware packet handling beyond standard player checks.
- 💥 You can manage plugin at the in-game using **Interactive UI** by using command! `/zuri ui`
   - The UI is meant for quick inspection and administrative control without requiring the console.

If you want to create your own module, see [`TUTORIAL.md`](TUTORIAL.md).

# Forks / Dependencies
Here are the **dependencies** were used in the plugin:

- [FormAPI Fix](https://github.com/DavyCraft648/FormAPI-PM)
- [Modified DiscordWebhookAPI](https://github.com/CortexPE/DiscordWebhookAPI/)
- [AntiInstabreak by PMMP](https://github.com/pmmp/AntiInstabreak) (**Instabreak (A)**)
- [Commando by Paroxity & CortexPE](https://github.com/Paroxity/Commando)
- [LibVapmPMMP](https://github.com/VennDev/LibVapmPMMP)
- [InfoAPI](https://github.com/SOF3/InfoAPI) for API placeholders used languages for server developers.

Some are for fixes and some are modified for compability.
These libraries cover the parts that are outside the anticheat core itself, such as forms, webhook delivery, and specific block-break detection support. 

Async handling now also uses [LibVapmPMMP](https://github.com/VennDev/LibVapmPMMP) as the thread/coroutine backend for heavier check evaluation, which keeps more expensive checks away from the normal event path.

# Current Modules
**BETA** - means to be in testing, and to be optimize in the next version. <br>
**DISABLED** - means the code is not working or has a false-positive in certain methods. <br>
**OPTIONAL** - means this is optional optimization checks for certain purposes. <br>

Every module below is grouped by the type of behavior it watches so server owners can quickly understand what part of the game is being monitored. Some checks only flag when they see repeated suspicious behavior, while others can punish immediately when a limit is crossed.

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
- **InputSpoof**
   - **A:** Detect invalid or spoofed movement vector values in PlayerAuthInput packets.
- **MessageSpoof**
   - **A:** Checks if the message exceeds the minecraft chat limit.
- **SelfHit**
   - **A:** Check if the entity id are same with damager id.
- **Regen** (BETA)
   - **A:** Check the heal rate is valid for the damage.
   - **B:** Check the consistency and tolerance of heal rate is valid when player regenerated hearts.
- **Timer** (BETA)
   - **A:** Check the packet time consistency if it is balanced.
   - **B:** Check the ticks between packet is balanced.
   - **C:** Check MovePlayerPacket is stable or has delay with PlayerAuthInputPacket.
   - **D:** Correlate auth-input ticks and real-time drift to detect sustained timer acceleration patterns.
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
- **GhostHand**
   - **A:** Detect hits that pass through solid block lines between damager and target.
- **Hitbox**
   - **A:** Detect invalid aim alignment and off-hitbox attack vectors during combat.
- **ItemLerp**
   - **A:** Detect repeated attacks immediately after held-slot swaps that mimic item-lerp abuse.
- **Velocity** (BETA)
   - **A:** Detect suspicious anti-knockback style movement after recent combat hits.
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
- **Rotation** (BETA)
   - **A:** Detect repeated fixed-step combat rotation patterns commonly used by aim-assist style clients.
   - **B:** Detect repeated combat yaw snap patterns with near-locked pitch changes.
- **Reach**
    - **A:** Check distance between the player, check also if the player is in top.
    - **B:** Check distance squared between the player. Check also gamemode for possible reach distance.
    - **C:** Check eye height and cuboid if it is actually hitting the player legitable.
   - **D:** Correlate eye-to-eye distance with sprint and ping compensation through async evaluation.
    - **E:** Detect out-of-bounds eye-to-hitbox edge reach with stability and ping gating.
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
- **Jesus** (DISABLED)
     - **A:** Check the player is walking through water.
- **Omnisprint** (DISABLED)
     - **A:** Check keys input by the player.
- **NoSlow** (BETA)
   - **A:** Detect abnormal movement speed while using consumables, bows, and similar slowdown states.
- **Phase**
     - **A:** Check if the player stucks at the block, teleport when to a safe place.
- **Speed**
     - **A:** Calculates the possible speed motion of the player.
    - **B:** Calculates the distance difference from to the player.
- **Spider** (DISABLED)
     - **A:** Check if the player is climbing or abnormally moving upwards to non-climbable blocks.
- **Step** (DISABLED)
     - **A:** Check if the player is moving upwards so fast.
- **AntiBot**
     - **A:** Check if the player has a valid device os.
     - **B:** Check if the player is using hack client a.k.a. toolbox.
- **EditionFaker**
     - **A:** Check if the player has a valid platform.
     - **B:** Check device title id if it is valid.
- **DeviceSpoofID**
   - **A:** Validate device-id entropy and pattern consistency to catch spoofed client identities.
- **ProxyBot** (OPTIONAL)
     - **A:** Check player if it is using proxy, tor or other internet exploit ip services.
- **Scaffold** (BETA)
     - **A:** Check if the hand item is null while placing multiple blocks in 1 instance.
     - **B:** Check pitch if it is valid when placing blocks.
     - **C:** Check pitch if it is valid and the block distance is valid.
     - **D:** Check if the hand item is null while placing blocks.
   - **E:** Detect fast expansion bridging patterns with abnormal player-to-block and sequential block distances.
   - **F:** Detect fast block expansion where block advancement exceeds player movement progression.
- **Tower** (BETA)
     - **A:** Check if the player moving upwards straight while placing blocks check if the player is actually placing the block downwards.
- **NetworkLimit** (BETA)
     - **A:** Limit players same ip to prevent malicious bots.
- **AirJump** (BETA)
     - **A:** Compare up distance and last data and calculate delta up distance.

# Feedbacks and Issue's
- 😁 Your feedback and reviews are highly appriciated, if you ever find a bug or false-positive in certain modules, you can create an issue in our [github repository](https://github.com/ReinfyTeam/Zuri/issues)!
   - Please include the module name, subtype, server version, and what the player was doing so the issue can be reproduced faster.
- 👍 You can also view [Frequently asked questions article](https://github.com/ReinfyTeam/Zuri/wiki/Well-Known-Issues) about common encountered issues to our plugin, be sure to read that before creating an issue!
   - This is especially useful for lag-related detections, teleport behavior, and other cases where server conditions can affect the result.
> Please wait for the developer response to the issue since we have high amount of task and issue that we to do fix also ;)
