<?php

namespace App\Enums\Filter;

/**
 * Actions with a cooldown in filter_cooldowns. Values are part of docs/shared-contract.md.
 */
enum CooldownAction: string
{
    case Reverse = 'reverse';
    case ReverseJob = 'reverse_job';
    case Global = 'global';
    case Exit = 'exit';
    case Exchange = 'exchange';
    case Stall = 'stall';
    case RidePet = 'ride_pet';
    case GrabPet = 'grab_pet';
    case AllChat = 'all_chat';
    case AllChatSafe = 'all_chat_safe';
    case Teleport = 'teleport';
    case TeleportJob = 'teleport_job';
    case BerserkJob = 'berserk_job';
    case BerserkFw = 'berserk_fw';
    case ResScroll = 'res_scroll';
    case ResScrollJob = 'res_scroll_job';
    case FriendRequest = 'friend_request';
}
