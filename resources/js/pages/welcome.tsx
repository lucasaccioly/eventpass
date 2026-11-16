import { Form, Head, Link, usePage } from '@inertiajs/react';
import { QrCode, Radio, ScanLine, Ticket } from 'lucide-react';
import DemoController from '@/actions/App/Http/Controllers/DemoController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: Ticket,
        title: 'Inscrição pública',
        text: 'Cada evento tem uma página aberta. A inscrição respeita o limite de vagas, mesmo com muita gente ao mesmo tempo.',
    },
    {
        icon: QrCode,
        title: 'Ingresso em PDF',
        text: 'O PDF com QR code é gerado numa fila Redis (Horizon), e a página do ingresso atualiza sozinha via SSE.',
    },
    {
        icon: ScanLine,
        title: 'Portaria pelo celular',
        text: 'Cada celular da entrada recebe um token Sanctum que só valida ingressos daquele evento, uma vez cada.',
    },
    {
        icon: Radio,
        title: 'Painel ao vivo',
        text: 'Inscritos, check-ins e últimas entradas atualizam em tempo real enquanto a portaria trabalha.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Ingressos para eventos" />
            <div className="min-h-svh bg-muted/40 px-4 py-10">
                <div className="mx-auto max-w-3xl space-y-10">
                    <header className="flex items-center justify-between gap-4">
                        <p className="flex items-center gap-2 font-semibold">
                            <Ticket className="size-5" /> EventPass
                        </p>
                        <nav className="flex gap-2 text-sm">
                            {auth.user ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={dashboard()}>Meus eventos</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={login()}>Entrar</Link>
                                    </Button>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={register()}>
                                            Criar conta
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </header>

                    <section className="space-y-4">
                        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            Inscrição, ingresso e portaria de eventos, do
                            cadastro ao check-in.
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Projeto de portfólio em Laravel 13, Inertia e React.
                            Experimente com uma conta demo: ela já vem com um
                            evento preenchido e some em 24 horas.
                        </p>
                        {!auth.user && (
                            <Form {...DemoController.form()}>
                                {({ processing }) => (
                                    <Button size="lg" disabled={processing}>
                                        {processing && <Spinner />}
                                        Entrar como demo
                                    </Button>
                                )}
                            </Form>
                        )}
                    </section>

                    <section className="grid gap-4 sm:grid-cols-2">
                        {features.map(({ icon: Icon, title, text }) => (
                            <div
                                key={title}
                                className="space-y-2 rounded-xl border bg-background p-5"
                            >
                                <Icon className="size-5 text-muted-foreground" />
                                <h2 className="font-medium">{title}</h2>
                                <p className="text-sm text-muted-foreground">
                                    {text}
                                </p>
                            </div>
                        ))}
                    </section>

                    <footer className="text-sm text-muted-foreground">
                        <a
                            href="https://github.com/lucasaccioly/eventpass"
                            className="underline underline-offset-4"
                        >
                            Código-fonte no GitHub
                        </a>
                    </footer>
                </div>
            </div>
        </>
    );
}
