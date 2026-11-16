<?php

namespace App\Http\Controllers;

use App\Actions\RegisterParticipant;
use App\Http\Requests\RegistrationRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

class RegistrationController extends Controller
{
    public function store(RegistrationRequest $request, Event $event, RegisterParticipant $register): RedirectResponse
    {
        $registration = $register->handle(
            $event,
            $request->string('name')->value(),
            $request->string('email')->value(),
        );

        return to_route('tickets.show', $registration);
    }
}
