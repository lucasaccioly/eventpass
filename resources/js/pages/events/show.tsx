import { Form, Head, Link } from '@inertiajs/react';
import { ExternalLink, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import EventStatsStreamController from '@/actions/App/Http/Controllers/EventStatsStreamController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useSse } from '@/hooks/use-sse';
import { formatDateTime, formatTime } from '@/lib/dates';
import { edit, index } from '@/routes/events';
import type { EventItem, EventStats } from '@/types';

export default function EventShow({
    event,
    stats: initialStats,
}: {
    event: EventItem;
    stats: EventStats;
}) {
    const [stats, setStats] = useState(initialStats);
    useSse<EventStats>(
        EventStatsStreamController.url(event.id),
        'stats',
        setStats,
    );
    const publicUrl = `/e/${event.slug}`;

    return (
        <>
            <Head title={event.name} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={event.name}
                        description={`${formatDateTime(event.starts_at)} · ${event.venue}`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={publicUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink /> Página pública
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={edit(event.id)}>
                                <Pencil /> Editar
                            </Link>
                        </Button>
                        <Form
                            {...EventController.destroy.form(event.id)}
                            onBefore={() =>
                                window.confirm(
                                    'Excluir o evento e todas as inscrições?',
                                )
                            }
                        >
                            <Button variant="destructive">
                                <Trash2 /> Excluir
                            </Button>
                        </Form>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Stat label="Inscritos" value={stats.registered} />
                    <Stat label="Check-ins" value={stats.checked_in} />
                    <Stat
                        label="Vagas restantes"
                        value={stats.capacity - stats.registered}
                    />
                </div>

                <section className="rounded-xl border p-4">
                    <h2 className="mb-3 flex items-center gap-2 font-medium">
                        <span className="relative flex size-2">
                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-green-500 opacity-75" />
                            <span className="relative inline-flex size-2 rounded-full bg-green-500" />
                        </span>
                        Últimas entradas
                    </h2>
                    {stats.latest.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nenhum check-in ainda.
                        </p>
                    ) : (
                        <ul className="divide-y text-sm" aria-live="polite">
                            {stats.latest.map((entry) => (
                                <li
                                    key={`${entry.name}-${entry.checked_in_at}`}
                                    className="flex justify-between py-2"
                                >
                                    <span>{entry.name}</span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatTime(entry.checked_in_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-3xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

EventShow.layout = {
    breadcrumbs: [{ title: 'Meus eventos', href: index() }],
};
