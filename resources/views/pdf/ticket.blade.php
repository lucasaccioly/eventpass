<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ingresso — {{ $event->name }}</title>
    <style>
        @page { margin: 48px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; }
        .ticket { border: 2px dashed #9ca3af; border-radius: 16px; padding: 32px; }
        .brand { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #6b7280; }
        h1 { font-size: 26px; margin: 8px 0 4px; }
        .meta { font-size: 13px; color: #374151; margin: 2px 0; }
        .holder { margin-top: 28px; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 2px; }
        .name { font-size: 22px; font-weight: bold; margin-top: 4px; }
        .qr { text-align: center; margin-top: 32px; }
        .qr img { width: 240px; height: 240px; }
        .code { text-align: center; font-family: DejaVu Sans Mono, monospace; font-size: 28px; letter-spacing: 6px; font-weight: bold; margin-top: 8px; }
        .hint { text-align: center; font-size: 11px; color: #6b7280; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="brand">EventPass · Ingresso</div>
        <h1>{{ $event->name }}</h1>
        <p class="meta">{{ $event->starts_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
        <p class="meta">{{ $event->venue }}</p>

        <div class="holder">Participante</div>
        <div class="name">{{ $registration->name }}</div>

        <div class="qr"><img src="{{ $qr }}" alt="QR code do ingresso"></div>
        <div class="code">{{ $registration->code }}</div>

        <p class="hint">Apresente este QR code na entrada. Cada ingresso vale uma única entrada.</p>
    </div>
</body>
</html>
