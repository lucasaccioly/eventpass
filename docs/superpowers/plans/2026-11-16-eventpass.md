# EventPass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A public Laravel 13 portfolio app for event tickets. A participant registers, a queued job renders the PDF ticket, SSE pushes live status, and a gate page checks tickets in with Sanctum tokens. It ships with Pest tests, Docker, and a live demo on Render.

**Architecture:** One Laravel + Inertia + React app (official React starter kit).
- Redis queue under Horizon.
- dompdf for tickets.
- `response()->eventStream()` for SSE.
- Sanctum personal access tokens for gate devices.
- One Docker container (FrankenPHP + Horizon + scheduler under supervisord) on Render free, with Neon Postgres and Render Key Value.

**Tech Stack:**
- PHP 8.4, Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui.
- Horizon 5, Sanctum 4, Pest 5, Larastan 3, Pint.
- barryvdh/laravel-dompdf 3, chillerlan/php-qrcode 6, @laravel/stream-react, qr-scanner.

**Spec:** `docs/superpowers/specs/2026-11-16-eventpass-design.md` (approved 2026-11-16)

## Global Constraints

- **No employer references.** No employer name, employer project name or college name in any file, path or commit. Nothing is copied from the work codebase.
- **Language:** code, identifiers and commits in English. UI and README in pt-BR.
- **Commits:** the author is the existing git identity. End every message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Versions:** PHP 8.4, Laravel 13, Inertia 3, React 19, Horizon 5, Sanctum 4, Pest 5, Larastan 3.
- **Local machine:**
  - Available: PHP 8.4 (gd, redis, pdo_sqlite, zip), Node 25.
  - Missing: Docker, Redis, pdo_pgsql.
  - Local dev and tests use SQLite. Tests use `Queue::fake()` / `Storage::fake()`.
- **Limits:**
  - Capacity: 1–500.
  - 20 events per organizer, 10 devices per event.
  - Demo users: 200 live, 24 h TTL.
- **Throttles:**
  - Registrations: 10/min per IP.
  - Check-ins: 60/min per token.
  - Demo creation: 5/hour per IP.
- **SSE:**
  - Poll every 1 s.
  - Heartbeat comment every 15 s.
  - Close after 30 s.
  - Ticket stream ends at `ready`/`failed`.
- **Ticket code:** 10 chars from `23456789ABCDEFGHJKLMNPQRSTUVWXYZ` via `random_int`. It is what the QR encodes.
- **Secrets:** never committed. Claude never types secrets into websites; Lucas pastes them.
- **Tunables:** live in `config/eventpass.php` and are read from there, never hard-coded:
  - `stream_seconds` 30
  - `stream_poll_ms` 1000
  - `heartbeat_seconds` 15
  - `max_events_per_user` 20
  - `max_devices_per_event` 10
  - `max_demo_users` 200
  - `demo_ttl_hours` 24
  - `horizon_emails` (from `HORIZON_ALLOWED_EMAILS`, comma list)

## Review Focus

1. **Email case and whitespace.** `" Ana@X.com"` and `"ana@x.com"` are the same person for the duplicate check. Emails are stored trimmed and lowercased. Test in Task 3.
2. **Capacity lowered below the registrations count.** Editing an event to a capacity smaller than its current registrations is rejected with a validation error, never silently accepted. Test in Task 2.
3. **Codes typed by hand at the gate.** Lowercase or surrounding spaces (`" ab3k…"`) still match. Test in Task 6.
4. **Deleted event, live device.** Deleting an event revokes its device tokens. A token whose event no longer exists gets 401, not a 500. Test in Task 6.
5. **Wiped disk.** A `ready` ticket whose PDF file is gone (container restart) re-queues and shows "gerando" again, never a 500. Test in Task 4.

The last-seat race can't be proven on SQLite, where `lockForUpdate` is a no-op. The reviewer checks the transaction and lock by reading. The exact-capacity boundary is tested in Task 3.

---

## File map

| Path | Responsibility |
|---|---|
| `config/eventpass.php` | All tunables above |
| `app/Enums/TicketStatus.php` | `pending`, `ready`, `failed` |
| `app/Models/Event.php`, `Registration.php` (+ factories, migrations) | Data model from the spec |
| `app/Policies/EventPolicy.php` | Owner-only access |
| `app/Support/TicketCode.php` | Code generator |
| `app/Actions/RegisterParticipant.php` | Locked, transactional registration |
| `app/Actions/CreateGateDevice.php` | Sanctum token per device |
| `app/Actions/CreateDemoSandbox.php`, `app/Console/Commands/PruneDemoUsers.php` | Demo lifecycle |
| `app/Jobs/GenerateTicketPdf.php`, `resources/views/pdf/ticket.blade.php` | PDF rendering |
| `app/Http/Controllers/EventController.php` | Organizer CRUD and dashboard |
| `app/Http/Controllers/PublicEventController.php`, `TicketController.php` | Participant side |
| `app/Http/Controllers/EventStreamController.php` | Both SSE endpoints |
| `app/Http/Controllers/DeviceController.php`, `Api/GateController.php`, `DemoController.php` | Devices, check-in API, demo |
| `app/Http/Requests/*` | Form Requests |
| `resources/js/pages/events/{index,form,show}.tsx` | Organizer pages |
| `resources/js/pages/public/{event,ticket}.tsx`, `resources/js/pages/gate.tsx` | Public pages |
| `Dockerfile`, `docker/*`, `compose.yaml`, `render.yaml`, `.github/workflows/ci.yml` | Ship |

`App\Models\Event` shadows nothing at runtime. In tests that need the Event facade, alias it: `use Illuminate\Support\Facades\Event as EventBus;`.

---

### Task 1: Bootstrap the repo

**Files:**
- Create: the whole starter kit, plus `config/eventpass.php`, `phpstan.neon`, `tests/ArchTest.php`, `.github/workflows/ci.yml`
- Keep: `docs/superpowers/**`

**Interfaces:**
- Produces:
  - A green `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan` and `npm run build`.
  - `config('eventpass.*')` keys from Global Constraints.

- [ ] **Step 1: Install Composer.**
  - Download the installer from `https://getcomposer.org/installer` into the scratchpad.
  - Verify its SHA-384 against `https://composer.github.io/installer.sig`, and run it with `--install-dir="$HOME/bin" --filename=composer.phar`.
  - Add a `composer` shell shim in `$HOME/bin`.
  - Run: `composer --version`. Expected: `Composer version 2.x`.
- [ ] **Step 2: Scaffold.**
  - Run `composer create-project laravel/react-starter-kit ../eventpass-tmp` from `Portfolio/`.
  - Move everything, dotfiles included, into `eventpass/`, keeping `docs/`. Delete `eventpass-tmp`.
  - If the kit ships PHPUnit tests, install `pestphp/pest:^5` and convert them with `pestphp/pest-plugin-drift`.
- [ ] **Step 3: Set up the app.**
  - Set `APP_NAME=EventPass` and `APP_LOCALE=pt_BR` in `.env` and `.env.example`.
  - Install pt-BR validation messages via `laravel-lang/lang`, or add `lang/pt_BR/validation.php` with the keys used.
  - Set `'timezone' => 'America/Sao_Paulo'` in `config/app.php`.
  - Remove settings pages the app won't use: keep profile, password and appearance, drop two-factor if present.
- [ ] **Step 4: Add dependencies.**
  - Run `composer require laravel/horizon laravel/sanctum barryvdh/laravel-dompdf chillerlan/php-qrcode:^6` and `composer require --dev larastan/larastan:^3`.
  - Run `php artisan install:api`, which adds Sanctum and `routes/api.php`.
  - Run `php artisan horizon:install`.
  - Run `npm i @laravel/stream-react qr-scanner`.
- [ ] **Step 5: Add `config/eventpass.php`** with the Global Constraints keys.
  - Add `phpstan.neon` (level 6, paths `app`, `routes`, `database`).
  - Add `tests/ArchTest.php`:
    ```php
    arch()->preset()->laravel();
    arch('no debug calls')->expect(['dd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();
    arch('actions are final')->expect('App\Actions')->toBeFinal();
    ```
- [ ] **Step 6: Add CI.** In `.github/workflows/ci.yml`, job `php`:
  - PHP 8.4 with extensions gd, zip, pdo_sqlite.
  - `composer install`, then `cp .env.example .env && php artisan key:generate`.
  - `npm ci && npm run build`, because the kit's tests render Inertia pages that need the Vite manifest.
  - `vendor/bin/pint --test`, `vendor/bin/phpstan --memory-limit=1G`, `php artisan test`, and `npx tsc --noEmit`.
- [ ] **Step 7: Verify.**
  - Run: `php artisan test && vendor/bin/pint --test && vendor/bin/phpstan --memory-limit=1G && npx tsc --noEmit`.
  - Expected: all pass, including the kit's own auth tests and ArchTest.
- [ ] **Step 8: Commit.**
  - Run `git init -b main`.
  - Make sure `.gitignore` covers `.env`, `/vendor`, `/node_modules`, `/public/build` and `.superpowers/`.
  - Commit `chore: scaffold EventPass from the Laravel React starter kit`.

### Task 2: Events CRUD for organizers

**Files:**
- Create:
  - Migration `create_events_table`
  - `app/Models/Event.php`, `database/factories/EventFactory.php`
  - `app/Policies/EventPolicy.php`
  - `app/Http/Requests/EventRequest.php`
  - `app/Http/Controllers/EventController.php`
  - `resources/js/pages/events/{index,form,show}.tsx`
- Modify: `routes/web.php`, `app/Models/User.php`, the kit's sidebar nav
- Test: `tests/Feature/EventsTest.php`

**Interfaces:**
- Produces:
  - `Event` has `user_id`, `name`, `slug`, `description`, `venue`, `starts_at` (datetime cast), and `capacity`.
  - Relations: `Event::user()`, `Event::registrations()`, `User::events()`.
  - Route names: `events.index|create|store|show|edit|update|destroy` (auth + verified as the kit does). Route-model binding by id.
  - `EventPolicy::view|update|delete(User, Event): bool` checks ownership.
  - The slug is created as `Str::slug($name).'-'.Str::lower(Str::random(5))` on create and never changes.

- [ ] **Step 1: Write the failing tests:**
  - `it('lists only my events')`: two users, each with an event. The Inertia props `events` contain only mine.
  - `it('creates an event with a slug')`: POST valid data (name "Meetup PHP Recife", venue, starts_at in the future, capacity 50). Expect a redirect to `events.show`, and a slug that matches `/^meetup-php-recife-[a-z0-9]{5}$/`.
  - `it('validates capacity between 1 and 500')`: 0 and 501 give errors on `capacity`.
  - `it('forbids other users')`: show, update and delete on someone else's event return 403.
  - `it('caps events per user at 20')`: with 20 events, POST gives a `name` error "Limite de 20 eventos atingido".
  - `it('rejects capacity below current registrations')` (Review Focus 2): an event with 3 registrations, PUT capacity 2, gives a `capacity` error. Insert the registrations with `DB::table` until Task 3 adds the factory.
- [ ] **Step 2: Run** `php artisan test --filter=EventsTest`. Expected: FAIL (route or class missing).
- [ ] **Step 3: Implement** the migration, model, policy, request and controller.
  - `show` renders `events/show` with `event` and `stats: {registered, checked_in, capacity, latest: []}`. Task 5 fills `latest` and makes it live.
  - Delete cascades registrations at the FK level.
- [ ] **Step 4: Build the pages** with the kit's shadcn components, pt-BR copy and dates formatted `dd/MM/yyyy HH:mm`.
  - Index: a table with name, date, registered/capacity, and a link to the public page.
  - Form: shared by create and edit.
  - Show: a stats placeholder.
- [ ] **Step 5: Run** `php artisan test && npx tsc --noEmit`. Expected: PASS.
- [ ] **Step 6: Commit** `feat: organizers manage their events`.

### Task 3: Public registration

**Files:**
- Create:
  - Migration `create_registrations_table`
  - `app/Models/Registration.php`, `database/factories/RegistrationFactory.php`
  - `app/Enums/TicketStatus.php`, `app/Support/TicketCode.php`
  - `app/Actions/RegisterParticipant.php`
  - `app/Http/Requests/RegistrationRequest.php`
  - `app/Http/Controllers/PublicEventController.php`, `app/Http/Controllers/TicketController.php` (`show` only for now)
  - `resources/js/pages/public/{event,ticket}.tsx`
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` (rate limiter `registrations`)
- Test: `tests/Feature/RegistrationTest.php`, `tests/Unit/TicketCodeTest.php`

**Interfaces:**
- Produces:
  - `Registration` has `event_id`, `public_id` (ULID via `HasUlids` on `public_id` only; route key `public_id`), `name`, `email`, `code`, `ticket_status` (cast `TicketStatus`), `ticket_path`, and `checked_in_at` (datetime).
  - Unique `(event_id, email)` and unique `code`.
  - `TicketCode::generate(): string`.
  - `RegisterParticipant::handle(Event $event, string $name, string $email): Registration`. It throws `ValidationException` with key `email`, using the messages "Inscrições esgotadas" or "Este e-mail já está inscrito".
  - Routes:
    - `public.events.show` → `GET /e/{event:slug}`
    - `registrations.store` → `POST /e/{event:slug}/registrations`, with `throttle:registrations`
    - `tickets.show` → `GET /t/{registration:public_id}`
  - `GenerateTicketPdf` is dispatched with `->afterCommit()`; the class comes in Task 4. Create an empty job class now so the dispatch compiles. Task 4 replaces it.

- [ ] **Step 1: Write the failing tests:**
  - `TicketCodeTest`: 1000 codes all match `/^[2-9A-HJ-NP-Z]{10}$/` and are unique.
  - `it('registers and queues the ticket')`:
    - With `Queue::fake()`, POST name and email.
    - Redirects to `tickets.show` and the status is `pending`.
    - `Queue::assertPushed(GenerateTicketPdf::class)`.
  - `it('normalizes email for duplicates')` (Review Focus 1): register `"ana@x.com"`, then `" Ana@X.com "`. The second gets the email error "Este e-mail já está inscrito", and the stored email is `ana@x.com`.
  - `it('allows the same email on another event')`.
  - `it('closes at exact capacity')`: capacity 1, the first registration succeeds, the second gets "Inscrições esgotadas".
  - `it('throttles registrations to 10 per minute per IP')`: the 11th POST gives 429.
  - `it('shows the public page without auth')`: GET by slug gives 200 and props `event.name` and `spots_left`.
  - `it('404s unknown tickets')`.
- [ ] **Step 2: Run** `php artisan test --filter='RegistrationTest|TicketCodeTest'`. Expected: FAIL.
- [ ] **Step 3: Implement.**
  - `RegisterParticipant` runs a `DB::transaction` that does `Event::whereKey($id)->lockForUpdate()->first()`, then counts, then inserts.
  - It retries `TicketCode::generate()` on a unique violation of `code` (max 3).
  - Email is `Str::lower(trim())` in the Form Request's `prepareForValidation`.
- [ ] **Step 4: Build the pages.**
  - Public event page: name, date, venue, description, spots left, and the form. When the event is full, show "Inscrições esgotadas" instead of the form.
  - Ticket page: name, event, the "Gerando seu ingresso…" state, and the line "Guarde este link: é o único acesso ao seu ingresso."
- [ ] **Step 5: Run** `php artisan test`. Expected: PASS.
- [ ] **Step 6: Commit** `feat: participants register on the public event page`.

### Task 4: Ticket PDF job and download

**Files:**
- Create/replace: `app/Jobs/GenerateTicketPdf.php`, `resources/views/pdf/ticket.blade.php`
- Modify: `TicketController.php` (`pdf`), `routes/web.php`, `config/horizon.php`
- Test: `tests/Feature/TicketPdfTest.php`

**Interfaces:**
- Produces:
  - `GenerateTicketPdf(Registration $registration)`: `ShouldQueue`, `$tries = 3`, `$backoff = 5`, with `failed()` setting `Failed`.
  - It stores to `Storage::disk('local')` at `tickets/{public_id}.pdf`.
  - Route `tickets.pdf` → `GET /t/{registration:public_id}/pdf`.

- [ ] **Step 1: Write the failing tests** (with `Storage::fake('local')`):
  - `it('renders the pdf and marks ready')`:
    - Run `(new GenerateTicketPdf($r))->handle()`.
    - The file exists, its content starts with `%PDF`, and the status is `ready` with `ticket_path` set.
  - `it('is idempotent')`: a ready registration with an existing file. `handle()` leaves the file's mtime and content untouched.
  - `it('marks failed')`: calling `failed(new Exception)` sets `failed`.
  - `it('downloads a ready ticket')`: 200, `content-type: application/pdf`, filename `ingresso-{code}.pdf`.
  - `it('requeues when the file is gone')` (Review Focus 5):
    - With `Queue::fake()`, set the status to `ready` but with no file.
    - Expect a redirect to `tickets.show`, the status back to `pending`, and the job pushed.
  - `it('redirects pending downloads to the ticket page')`.
- [ ] **Step 2: Run** `php artisan test --filter=TicketPdfTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
  - The view gets event name, date (`d/m/Y H:i`), venue, participant name, the QR as a PNG data URI from `(new QRCode(new QROptions(['outputType' => QROutputInterface::GDIMAGE_PNG, 'scale' => 8])))->render($code)`, and the code in large monospace text.
  - A4 portrait, rendered with `Pdf::loadView`.
- [ ] **Step 4: Configure Horizon.** In `config/horizon.php` environments `production` and `local`: one supervisor, queue `default`, `maxProcesses` 1, tries 3.
- [ ] **Step 5: Run** `php artisan test`. Expected: PASS.
  - Manual check: run `php artisan queue:work --once` with `QUEUE_CONNECTION=database`, open one PDF, and confirm the QR scans to the code.
- [ ] **Step 6: Commit** `feat: queued PDF tickets with QR codes`.

### Task 5: Live updates over SSE

**Files:**
- Create: `app/Http/Controllers/EventStreamController.php`
- Modify: `routes/web.php`, `resources/js/pages/public/ticket.tsx`, `resources/js/pages/events/show.tsx`, `EventController@show` (fill `latest`), `phpunit.xml`
- Test: `tests/Feature/StreamsTest.php`

**Interfaces:**
- Produces:
  - Route `tickets.stream` → `GET /t/{registration:public_id}/stream`. Event `status`, data `{"status":"pending|ready|failed","pdf_url":string|null}`.
  - Route `events.stream` → `GET /events/{event}/stream` (auth plus the `view` policy). Event `stats`, data `{"registered":int,"checked_in":int,"capacity":int,"latest":[{"name":string,"checked_in_at":ISO8601}]}`, with `latest` holding the 10 most recent check-ins.
  - `EventStreamController::stats(Event $event): array` is public static and returns the same shape. `EventController@show` reuses it.

- [ ] **Step 1: Set test config.** In `phpunit.xml`, set env `EVENTPASS_STREAM_SECONDS=1` and `EVENTPASS_STREAM_POLL_MS=50`, and read them in `config/eventpass.php`.
- [ ] **Step 2: Write the failing tests** (use `$response->streamedContent()`):
  - `it('ends the ticket stream once ready')`: a ready registration. The content contains `event: status` and `"status":"ready"`, plus a `pdf_url` ending in `/pdf`.
  - `it('keeps pending until the stream window closes')`: a pending registration. The content contains `"status":"pending"` exactly once, because only changes are sent.
  - `it('streams stats to the owner')`: 2 registrations, 1 checked in. The content contains `"registered":2,"checked_in":1`.
  - `it('forbids stats to others')`: 403. As a guest: redirect to login.
  - `it('sends stats on show')`: the Inertia props `stats.latest[0].name` equal the checked-in name.
- [ ] **Step 3: Run** `php artisan test --filter=StreamsTest`. Expected: FAIL.
- [ ] **Step 4: Implement both endpoints with `response()->eventStream(function () { … })`:**
  - A loop until `now() > start + stream_seconds`.
  - Re-read state (`refresh()` / `stats()`) and `yield new StreamedEvent(event: …, data: …)` only when it differs from the last one sent.
  - Yield a `: ping` comment every `heartbeat_seconds`.
  - `usleep(stream_poll_ms * 1000)`.
  - The ticket stream returns right after yielding `ready` or `failed`.
  - Send `retry: 1000` so `EventSource` reconnects quickly.
  - Release the session lock before looping: `session()->save()`, because the kit's database session would otherwise block other requests from the same user.
- [ ] **Step 5: Wire the client with `useEventStream`** from `@laravel/stream-react`.
  - The ticket page switches to "Baixar ingresso (PDF)" when ready, or shows "Não foi possível gerar. Recarregue a página." when failed.
  - The dashboard shows three stat cards (Inscritos, Check-ins, Vagas) and the latest check-ins list ("Ana Souza — 19:42"), replacing its state with each payload.
- [ ] **Step 6: Run** `php artisan test && npx tsc --noEmit`. Expected: PASS.
  - Manual check: in `composer run dev`, register in one tab and watch the ticket page flip to ready.
- [ ] **Step 7: Commit** `feat: live ticket status and dashboard over SSE`.

### Task 6: Gate devices and check-in API

**Files:**
- Create:
  - `app/Actions/CreateGateDevice.php`
  - `app/Http/Controllers/DeviceController.php`, `app/Http/Controllers/Api/GateController.php`
  - `resources/js/pages/gate.tsx`
- Modify:
  - `routes/web.php`, `routes/api.php`
  - `AppServiceProvider` (rate limiter `checkins`)
  - `events/show.tsx`: a devices section and the "Testar portaria" button
  - `Event` model: a `deleting` hook revokes its tokens
- Test: `tests/Feature/GateTest.php`

**Interfaces:**
- Consumes:
  - `Registration::code`, `checked_in_at`.
  - `EventStreamController::stats` (the dashboard picks up check-ins with no extra work).
- Produces:
  - `CreateGateDevice::handle(Event $event, string $name): string` returns the plaintext token.
    - The token is created on `$event->user` with abilities `["checkin:{$event->id}"]`.
    - It throws `ValidationException` (`name`: "Limite de 10 dispositivos por evento") at the cap.
  - Web routes, all with auth and the policy:
    - `devices.store` → `POST /events/{event}/devices`. It flashes `device_link`, which is `url('/portaria').'#token='.$plain`.
    - `devices.destroy` → `DELETE /events/{event}/devices/{tokenId}`.
    - `devices.test` → `POST /events/{event}/devices/test`. It creates a device named "Este navegador" and redirects to `/portaria#token=…`. The button is a plain HTML `<form method="post" target="_blank">` with the CSRF field, so it opens in a new tab.
  - `EventController@show` gains the prop `devices: [{id, name, created_at}]` (this event's tokens only).
  - Gate page route `gate` → `GET /portaria`, with no auth.
  - API routes, with `auth:sanctum`:
    - `GET /api/gate` returns `{event:{name,starts_at,venue}}`.
    - `POST /api/checkins {code}`, with `throttle:checkins`.
  - Check-in responses:
    - 200 `{name, checked_in_at}`
    - 404 `{message:"Ingresso não encontrado"}`
    - 403 `{message:"Ingresso de outro evento"}`
    - 409 `{message:"Ingresso já usado", checked_in_at}`
    - 401 when the token is missing, revoked, or its event is gone
  - The event id is parsed from the token's single `checkin:{id}` ability.

- [ ] **Step 1: Write the failing tests:**
  - `it('creates a device token scoped to the event')`: abilities equal `["checkin:{id}"]`, and the flash link contains `/portaria#token=`.
  - `it('caps devices at 10 per event')`.
  - `it('checks in once')`: the first POST gives 200 with the name and `checked_in_at` set. The second gives 409 with the same `checked_in_at`.
  - `it('normalizes typed codes')` (Review Focus 3): `"  " . strtolower($code) . " "` gives 200.
  - `it('rejects another event\'s ticket')`: 403. `it('404s unknown codes')`.
  - `it('rejects revoked tokens')`: after `devices.destroy`, 401.
  - `it('revokes tokens when the event is deleted')` (Review Focus 4): delete the event. `/api/gate` and `/api/checkins` give 401 and the token row is gone.
  - `it('forbids managing devices on others\' events')`: 403.
  - `it('throttles check-ins at 60 per minute per token')`: the 61st gives 429.
- [ ] **Step 2: Run** `php artisan test --filter=GateTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
  - The check-in is one statement: `Registration::where('code', $c)->where('event_id', $id)->whereNull('checked_in_at')->update(['checked_in_at' => now()])`. 0 rows affected means look up why (404 / 403 / 409).
  - `/api/gate` and `/api/checkins` share a private resolver that returns the event or aborts 401.
- [ ] **Step 4: Build `gate.tsx`.**
  - On load, move `#token=` from the hash to `localStorage['eventpass.gate']`, then `history.replaceState` to drop the hash.
  - Call `/api/gate` with `Authorization: Bearer`. On 401, show "Dispositivo revogado ou inválido".
  - Show the event name, a `qr-scanner` camera view (back camera, pause 2 s after each read), and a manual code input.
  - Show a full-width result banner: green "Entrada liberada — {name}", or red with the API message plus "às HH:mm" for 409.
  - Include a "Voltar ao painel" link.
- [ ] **Step 5: Add a devices section on `events/show`.**
  - A list of name and created date with a "Revogar" button, an "Adicionar dispositivo" form that shows the link once with a copy button, and the "Testar portaria" form button.
- [ ] **Step 6: Run** `php artisan test && npx tsc --noEmit`. Expected: PASS.
- [ ] **Step 7: Commit** `feat: gate devices check tickets in with Sanctum tokens`.

### Task 7: Demo sandbox, Horizon access, scheduler

**Files:**
- Create:
  - Migration `add_is_demo_and_expires_at_to_users` (`is_demo` bool default false, `demo_expires_at` nullable datetime)
  - `app/Actions/CreateDemoSandbox.php`, `app/Http/Controllers/DemoController.php`
  - `app/Console/Commands/PruneDemoUsers.php`
  - `app/Http/Middleware/BlockDemoAccountChanges.php`
- Modify:
  - `routes/web.php`, `routes/console.php` (schedule)
  - `app/Providers/HorizonServiceProvider.php` (gate)
  - `AppServiceProvider` (limiter `demo`)
  - The kit's login page: an "Entrar como demo" button posting to `demo.store`
  - Profile and password pages: hide the email and password forms for demo users
- Test: `tests/Feature/DemoTest.php`

**Interfaces:**
- Consumes: the factories from Tasks 2–3, plus `GenerateTicketPdf`.
- Produces:
  - `CreateDemoSandbox::handle(): User`:
    - Prunes expired demo users first. If there are still ≥ `max_demo_users` live, it deletes the oldest.
    - Creates the user: name "Visitante", email `demo-{ulid}@eventpass.test`, random password, `email_verified_at` now, `is_demo` true, `demo_expires_at` now + 24 h.
    - Creates one event: "Meetup PHP Recife", 7 days ahead, capacity 60, venue "Auditório Central". It has 40 registrations with neutral generated names, 12 of them checked in, all `ready` with `ticket_path` null. The download fallback generates the PDFs on demand.
  - Route `demo.store` → `POST /demo`, with guest and `throttle:demo`. It logs in and redirects to `events.show` of the demo event.
  - Command `demo:prune` deletes expired demo users, their events (cascading registrations), their tokens, and `tickets/{public_id}.pdf` files. It prints "Pruned N demo users".
  - Schedule: `demo:prune` hourly, `horizon:snapshot` every five minutes.
  - Gate `viewHorizon`: `$user->is_demo || in_array($user->email, config('eventpass.horizon_emails'), true)`.

- [ ] **Step 1: Write the failing tests:**
  - `it('creates a private sandbox per click')`: two POSTs from different IPs create two users, each owning exactly one event with 40 registrations and 12 check-ins.
  - `it('prunes expired demo users and their files')`:
    - Travel 25 h, run `demo:prune`, and `expectsOutputToContain('Pruned 1')`.
    - The user, event, registrations and token are gone, along with a seeded fake ticket file.
  - `it('keeps live demo users and real users')`.
  - `it('caps live demo users')`: with config `max_demo_users` set to 2, a 3rd POST leaves 2 live demo users, and the oldest is gone.
  - `it('throttles demo creation to 5 per hour per IP')`.
  - `it('blocks demo email and password changes')`: PATCH profile with a new email and PUT password both give 403. Account deletion is allowed.
  - `it('lets demo users and allowed emails view Horizon')`: GET `/horizon` gives 200 for demo users and for an email in config, and 403 for others.
  - `it('schedules prune and snapshot')`: `Schedule::events()` contains `demo:prune` hourly and `horizon:snapshot` `*/5`.
- [ ] **Step 2: Run** `php artisan test --filter=DemoTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the files listed above.
- [ ] **Step 4: Run** `php artisan test && vendor/bin/phpstan --memory-limit=1G && vendor/bin/pint --test`. Expected: PASS.
- [ ] **Step 5: Commit** `feat: per-visitor demo sandbox and Horizon access`.

### Task 8: Docker, Render Blueprint, CI image build

**Files:**
- Create:
  - `Dockerfile`, `.dockerignore`
  - `docker/supervisord.conf`, `docker/start.sh`, `docker/Caddyfile`
  - `compose.yaml`, `render.yaml`
- Modify:
  - `.github/workflows/ci.yml`: a `docker` job
  - `bootstrap/app.php`: `trustProxies(at: '*')`
  - `.env.example`: production keys documented without values

**Interfaces:**
- Produces:
  - An image that listens on `$PORT` (Render sets 10000).
  - Env read: `APP_KEY`, `APP_URL`, `DB_CONNECTION=pgsql`, `DB_URL`, `REDIS_URL`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=database`, `LOG_CHANNEL=stderr`, `HORIZON_ALLOWED_EMAILS`.

- [ ] **Step 1: Write the Dockerfile.** Use three stages:
  1. `composer:2` installs vendor with `--no-dev --no-scripts`.
  2. An assets stage based on `dunglas/frankenphp:php8.4` with Node 22 added and the vendor copied in. It runs `npm ci && npm run build`. PHP is present because the kit's Vite plugin may run `php artisan` (Wayfinder) during the build.
  3. A final `dunglas/frankenphp:php8.4` stage:
     - `install-php-extensions pdo_pgsql redis gd zip pcntl` and `apt-get install supervisor`.
     - Copy the app, vendor and `public/build`, then `composer dump-autoload --optimize`.
     - `CMD ["/app/docker/start.sh"]`.
- [ ] **Step 2: Write `start.sh` and `supervisord.conf`.**
  - `start.sh`: `php artisan config:cache route:cache view:cache`, then `php artisan migrate --force`, then `exec supervisord -c /app/docker/supervisord.conf`.
  - `supervisord.conf` programs, each with stdout/stderr to `/dev/stdout` and `autorestart=true`:
    - `frankenphp run --config /app/docker/Caddyfile`, where the Caddyfile is `{ auto_https off }`, `:{$PORT}`, `root * /app/public`, `php_server`.
    - `php artisan horizon`.
    - `php artisan schedule:work`.
- [ ] **Step 3: Write `compose.yaml`** with `app` (build ., port 8000:8000, `PORT=8000`), `redis:7-alpine` and `postgres:17-alpine`, and env pointing at them.
- [ ] **Step 4: Write `render.yaml`.**
  - Web service `eventpass`: `runtime: docker`, `plan: free`, `healthCheckPath: /up`.
  - Env: `REDIS_URL` from the Key Value `connectionString`. `APP_KEY`, `DB_URL` and `HORIZON_ALLOWED_EMAILS` are `sync: false`.
  - Key Value `eventpass-redis`: `plan: free`, `ipAllowList: []`.
- [ ] **Step 5: Add a CI job `docker`** that runs `docker build -t eventpass .`.
- [ ] **Step 6: Verify.**
  - Local: `php artisan test` still passes after the `trustProxies` change.
  - The image build is proven by CI after the push in Task 9, since there's no Docker locally. Record that in the ledger.
- [ ] **Step 7: Commit** `build: Docker image, compose file and Render Blueprint`.

### Task 9: README, publish, deploy, profile

**Files:**
- Create: `README.md` (pt-BR), `LICENSE` (MIT, Lucas Accioly), `docs/demo.gif`
- Modify: the profile README (via `gh api`)

- [ ] **Step 1: Write the README.**
  - Title and one-line pitch, plus CI, PHP, Laravel and license badges.
  - The demo link with a note about the ~50 s cold start, and the GIF.
  - "O que mostra": a table mapping each claim (Horizon, dompdf on the queue, SSE, Sanctum, Pest, Docker) to a file.
  - A Mermaid architecture diagram (browser → FrankenPHP → Postgres/Redis → Horizon worker → disk).
  - "Decisões": one container, the 30 s SSE window, the per-visitor demo, the wiped-disk regeneration, and the atomic check-in.
  - "Rodando": locally with SQLite, and with Docker Compose.
  - Tests and the license.
  - Run: `grep -rniEf ~/.eventpass-forbidden --exclude-dir={vendor,node_modules,.git} .` (the pattern list lives outside the repo). Expected: no hits.
- [ ] **Step 2: Ask Lucas before publishing.** Create the public repo `lucasaccioly/eventpass` and push `main`. CI must be green on all three jobs (php, docker, and tsc inside php).
- [ ] **Step 3: Deploy, with Lucas doing logins and pasting secrets.**
  - Create a Neon project `eventpass`. Lucas copies the pooled connection string into Render's `DB_URL`.
  - Run `php artisan key:generate --show` locally. Lucas pastes the key into `APP_KEY`.
  - Apply the Blueprint from the repo.
  - If the Key Value instance can't be reached from the free web service, fall back to `redis-server` as a 4th supervisord program and set `REDIS_URL=redis://127.0.0.1:6379`.
- [ ] **Step 4: Smoke test live.**
  - "Entrar como demo" → dashboard.
  - Open the public page in a private window and register → the ticket flips to ready → download the PDF.
  - "Testar portaria" → type the code → green. The dashboard ticks +1 without a reload.
  - A second scan gives red "já usado".
  - `/horizon` shows the job.
  - Record the GIF of this flow and commit it.
- [ ] **Step 5: Update the profile README.** Add EventPass to the projects section with the demo link, ask Lucas before pinning, and commit `docs: add EventPass`.
