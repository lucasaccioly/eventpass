<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return ! $event instanceof Event || $this->user()?->can('update', $event) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date'],
            'capacity' => ['required', 'integer', 'between:1,500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $max = (int) config('eventpass.max_events_per_user');

                if ($this->isMethod('post') && $this->user()?->events()->count() >= $max) {
                    $validator->errors()->add('name', "Limite de {$max} eventos atingido.");
                }

                $event = $this->route('event');

                if ($event instanceof Event && $validator->errors()->isEmpty()) {
                    $registered = $event->registrations()->count();

                    if ($this->integer('capacity') < $registered) {
                        $validator->errors()->add('capacity', "Já existem {$registered} inscritos; as vagas não podem ser menos que isso.");
                    }
                }
            },
        ];
    }
}
