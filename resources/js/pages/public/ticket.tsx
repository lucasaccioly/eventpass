import { Head } from '@inertiajs/react';
import PublicShell from '@/components/public-shell';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/dates';

export type TicketStatus = 'pending' | 'ready' | 'failed';

export type TicketProps = {
    public_id: string;
    name: string;
    code: string;
    status: TicketStatus;
    event: { name: string; slug: string; venue: string; starts_at: string };
};

export default function TicketPage({ ticket }: { ticket: TicketProps }) {
    return (
        <PublicShell>
            <Head title={`Ingresso · ${ticket.event.name}`} />
            <p className="text-sm text-muted-foreground">Ingresso de</p>
            <h1 className="text-2xl font-semibold tracking-tight">
                {ticket.name}
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
                {ticket.event.name} · {formatDateTime(ticket.event.starts_at)} ·{' '}
                {ticket.event.venue}
            </p>

            <div className="my-6 rounded-xl border border-dashed p-4 text-center">
                <p className="text-xs tracking-widest text-muted-foreground uppercase">
                    Código
                </p>
                <p className="font-mono text-2xl font-semibold tracking-[0.2em]">
                    {ticket.code}
                </p>
            </div>

            {ticket.status === 'pending' && (
                <p className="flex items-center justify-center gap-2 text-sm">
                    <Spinner /> Gerando seu ingresso…
                </p>
            )}

            <p className="mt-6 text-xs text-muted-foreground">
                Guarde este link: é o único acesso ao seu ingresso.
            </p>
        </PublicShell>
    );
}
