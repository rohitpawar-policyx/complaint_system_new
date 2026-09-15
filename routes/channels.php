<?php

use App\Models\Complaint;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
| Chat channel for a single complaint. This is the actual enforcement
| point stopping a customer from subscribing to another customer's
| complaint chat by editing the ID in JavaScript - Echo always calls
| POST /broadcasting/auth (registered by BroadcastServiceProvider) before
| the WebSocket server admits a private-channel subscription, and this
| closure runs server-side on every one of those calls.
*/
Broadcast::channel('chat.{complaintId}', function ($user, $complaintId) {
    $complaint = Complaint::find($complaintId);

    if ($complaint === null) {
        return false;
    }

    return $user->isAdmin() || $complaint->user_id === $user->id;
});
