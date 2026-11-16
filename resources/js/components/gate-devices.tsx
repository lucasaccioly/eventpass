import { Form, usePage } from '@inertiajs/react';
import { Copy, ExternalLink, ScanLine, Smartphone } from 'lucide-react';
import { useState } from 'react';
import DeviceController from '@/actions/App/Http/Controllers/DeviceController';
import GateTestController from '@/actions/App/Http/Controllers/GateTestController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/dates';

export type Device = {
    id: number;
    name: string;
    created_at: string;
    last_used_at: string | null;
};

export default function GateDevices({
    eventId,
    devices,
}: {
    eventId: number;
    devices: Device[];
}) {
    const link = (usePage().flash as { device_link?: string }).device_link;
    const [copied, setCopied] = useState(false);

    return (
        <section className="space-y-4 rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="font-medium">Portaria</h2>
                    <p className="text-sm text-muted-foreground">
                        Cada celular da entrada recebe um link próprio, que só
                        valida ingressos deste evento.
                    </p>
                </div>
                <Form
                    {...GateTestController.form(eventId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setCopied(false)}
                >
                    {({ processing }) => (
                        <Button variant="secondary" disabled={processing}>
                            <ScanLine /> Testar portaria
                        </Button>
                    )}
                </Form>
            </div>

            {link && (
                <div className="space-y-2 rounded-lg bg-muted p-3 text-sm">
                    <p className="font-medium">
                        Abra este link no celular da portaria, ou aqui mesmo
                        para testar. Ele aparece só agora.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Input
                            readOnly
                            value={link}
                            className="min-w-48 flex-1 font-mono"
                            aria-label="Link do dispositivo"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                void navigator.clipboard
                                    .writeText(link)
                                    .then(() => setCopied(true))
                            }
                        >
                            <Copy /> {copied ? 'Copiado' : 'Copiar'}
                        </Button>
                        <Button asChild>
                            <a href={link} target="_blank" rel="noreferrer">
                                <ExternalLink /> Abrir portaria
                            </a>
                        </Button>
                    </div>
                </div>
            )}

            {devices.length > 0 && (
                <ul className="divide-y text-sm">
                    {devices.map((device) => (
                        <li
                            key={device.id}
                            className="flex items-center justify-between gap-2 py-2"
                        >
                            <span className="flex items-center gap-2">
                                <Smartphone className="size-4 text-muted-foreground" />
                                {device.name}
                                <span className="text-muted-foreground">
                                    · criado {formatDateTime(device.created_at)}
                                </span>
                            </span>
                            <Form
                                {...DeviceController.destroy.form({
                                    event: eventId,
                                    device: device.id,
                                })}
                                options={{ preserveScroll: true }}
                            >
                                <Button variant="ghost" size="sm">
                                    Revogar
                                </Button>
                            </Form>
                        </li>
                    ))}
                </ul>
            )}

            <Form
                {...DeviceController.store.form(eventId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="flex flex-wrap items-start gap-2"
                onSuccess={() => setCopied(false)}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="min-w-48 flex-1">
                            <Input
                                name="name"
                                required
                                maxLength={60}
                                placeholder="Nome do dispositivo (ex.: Portão A)"
                                aria-label="Nome do dispositivo"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <Button disabled={processing} variant="outline">
                            Adicionar dispositivo
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
