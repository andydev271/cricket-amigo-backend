<?php

namespace App\Events;

use App\Models\CricketMatch;
use App\Services\ScoringService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after every scoring write (ball or undo). Public channel — a live
 * score is spectator data; the stream key is never broadcast.
 */
class BallScored implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public CricketMatch $match) {}

    public function broadcastOn(): array
    {
        return [new Channel("match.{$this->match->id}")];
    }

    public function broadcastWith(): array
    {
        return app(ScoringService::class)->summary($this->match);
    }
}
