<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\InvoiceDunning;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceDunningTriggered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public InvoiceDunning $dunning
    ) {
        //
    }
}
