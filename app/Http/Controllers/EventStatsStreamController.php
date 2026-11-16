<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\PollingStream;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventStatsStreamController extends Controller
{
    public function __invoke(Event $event): StreamedResponse
    {
        Gate::authorize('view', $event);

        return PollingStream::response('stats', fn (): array => $event->stats());
    }
}
