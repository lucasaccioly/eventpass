import { Form, Head } from '@inertiajs/react';
import type { ReactElement } from 'react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toLocalInput } from '@/lib/dates';
import { index } from '@/routes/events';
import type { EventItem } from '@/types';

export default function EventForm({ event }: { event: EventItem | null }) {
    const action = event
        ? EventController.update.form(event.id)
        : EventController.store.form();
    const title = event ? 'Editar evento' : 'Novo evento';

    return (
        <>
            <Head title={title} />
            <div className="max-w-xl p-4">
                <Heading
                    title={title}
                    description="A página pública de inscrição é criada automaticamente."
                />
                <Form {...action} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <Field label="Nome" error={errors.name}>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    maxLength={120}
                                    defaultValue={event?.name}
                                />
                            </Field>
                            <Field label="Descrição" error={errors.description}>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    maxLength={2000}
                                    defaultValue={event?.description ?? ''}
                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                />
                            </Field>
                            <Field label="Local" error={errors.venue}>
                                <Input
                                    id="venue"
                                    name="venue"
                                    required
                                    maxLength={160}
                                    defaultValue={event?.venue}
                                />
                            </Field>
                            <div className="grid gap-6 sm:grid-cols-2">
                                <Field
                                    label="Data e hora"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                        required
                                        defaultValue={
                                            event
                                                ? toLocalInput(event.starts_at)
                                                : undefined
                                        }
                                    />
                                </Field>
                                <Field label="Vagas" error={errors.capacity}>
                                    <Input
                                        id="capacity"
                                        name="capacity"
                                        type="number"
                                        min={1}
                                        max={500}
                                        required
                                        defaultValue={event?.capacity ?? 50}
                                    />
                                </Field>
                            </div>
                            <Button disabled={processing}>
                                {event ? 'Salvar' : 'Criar evento'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactElement<{ id: string }>;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={children.props.id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

EventForm.layout = {
    breadcrumbs: [{ title: 'Meus eventos', href: index() }],
};
