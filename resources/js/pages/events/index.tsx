import { Head, Link } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import { create, index, show } from '@/routes/events';
import type { EventItem } from '@/types';

export default function EventsIndex({ events }: { events: EventItem[] }) {
    return (
        <>
            <Head title="Meus eventos" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Meus eventos"
                        description="Crie eventos, divulgue a página de inscrição e acompanhe a entrada ao vivo."
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <CalendarPlus /> Novo evento
                        </Link>
                    </Button>
                </div>

                {events.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Você ainda não tem eventos.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {events.map((event) => (
                            <li key={event.id}>
                                <Link
                                    href={show(event.id)}
                                    className="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-muted/50"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {event.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDateTime(event.starts_at)} ·{' '}
                                            {event.venue}
                                        </p>
                                    </div>
                                    <span className="text-sm tabular-nums">
                                        {event.registrations_count ?? 0}/
                                        {event.capacity} inscritos
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Meus eventos', href: index() }],
};
