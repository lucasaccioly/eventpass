import { Form, Head } from '@inertiajs/react';
import { CalendarDays, MapPin } from 'lucide-react';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import InputError from '@/components/input-error';
import PublicShell from '@/components/public-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/dates';
import type { EventItem } from '@/types';

type PublicEvent = Pick<
    EventItem,
    'name' | 'slug' | 'description' | 'venue' | 'starts_at' | 'capacity'
>;

export default function PublicEvent({
    event,
    spots_left,
}: {
    event: PublicEvent;
    spots_left: number;
}) {
    return (
        <PublicShell>
            <Head title={event.name} />
            <h1 className="text-2xl font-semibold tracking-tight">
                {event.name}
            </h1>
            <ul className="mt-3 space-y-1 text-sm text-muted-foreground">
                <li className="flex items-center gap-2">
                    <CalendarDays className="size-4" />
                    {formatDateTime(event.starts_at)}
                </li>
                <li className="flex items-center gap-2">
                    <MapPin className="size-4" />
                    {event.venue}
                </li>
            </ul>
            {event.description && (
                <p className="mt-4 text-sm whitespace-pre-line">
                    {event.description}
                </p>
            )}

            <hr className="my-6" />

            {spots_left === 0 ? (
                <p className="rounded-lg bg-muted p-4 text-center font-medium">
                    Inscrições esgotadas
                </p>
            ) : (
                <Form
                    {...RegistrationController.store.form(event.slug)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {spots_left}{' '}
                                {spots_left === 1
                                    ? 'vaga restante'
                                    : 'vagas restantes'}
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome completo</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    maxLength={120}
                                    autoComplete="name"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    maxLength={160}
                                    autoComplete="email"
                                />
                                <InputError message={errors.email} />
                            </div>
                            <Button className="w-full" disabled={processing}>
                                Quero meu ingresso
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </PublicShell>
    );
}
