# EventPass — design

Status: approved by Lucas on 2026-11-16.

## Intent

Portfolio project 6 for Lucas's job hunt. Lucas's GitHub profile now claims Laravel/PHP, Redis queues with Horizon, background PDF generation, SSE, Sanctum, Pest and Docker. None of that has public code yet: Lucas's Laravel experience is at work and can't be shown. EventPass is a public Laravel app that backs every one of those claims with code a recruiter can read, run, and click through in a live demo.

The domain is event tickets: an organizer creates an event, people sign up on a public page, a queued job renders their ticket as a PDF with a QR code, and a gate person scans tickets at the door while the organizer's dashboard updates live.

## Goals

- **Organizer:**
  - Signs up and logs in (session auth from the official starter kit).
  - Creates, edits and deletes their events: name, description, starts_at, venue, capacity (1–500).
  - Sees each event's dashboard: registered count, checked-in count, capacity, and the latest 10 check-ins. All of it updates live over SSE.
  - Creates gate devices for an event. Each device is a Sanctum personal access token shown once as a link. The organizer can list and revoke devices.
- **Participant (no account):**
  - Opens the event's public page `/e/{slug}` and registers with name and email.
  - Lands on the ticket page `/t/{public_id}`. It shows "Gerando seu ingresso…" and switches to a download button when the queued job finishes, pushed over SSE.
  - Downloads the PDF: event name, date and venue, participant name, a QR code, and the code in text below it.
- **Gate person (no account):**
  - Opens the device link on a phone. The page stores the token in `localStorage`.
  - Scans the QR with the camera, or types the code by hand.
  - The page calls the check-in API with the bearer token. It shows a big green "Entrada liberada — {name}" or a red reason: already used (with the time), not found, or another event's ticket.
- **Demo (Claude's call: one private sandbox per visitor, so no visitor can break the demo for the next):**
  - The login page has an "Entrar como demo" button.
  - Each click creates a fresh demo user (`is_demo`, expires in 24 h) with one seeded event of about 40 registrations, some already checked in. Then it logs the visitor in.
  - The event dashboard has a "Testar portaria" button. It creates a device token for that event and opens the gate page in a new tab, so a recruiter can type or scan a code on the same computer within the one-minute budget.
  - Demo users can open `/horizon`.
- **Quality bar:**
  - Pest feature tests for every flow above, plus Pest arch tests.
  - Larastan, Pint, and the TypeScript type check.
  - GitHub Actions CI: PHP checks, frontend build, and a Docker image build.
- **README (pt-BR):** what it is, demo link, a GIF, the architecture diagram, the design decisions, and how to run it.

## Non-goals

- Email delivery. It needs a paid or signup-gated provider. The ticket page says to keep the link.
- Payments, paid tickets, seat maps, waitlists.
- Multiple organizers per event, teams, roles.
- Laravel Sail. A plain `compose.yaml` covers "run with Docker", and Sail can't be verified on this machine.
- Mobile app. The gate page is a responsive web page.
- English UI or i18n. The UI is pt-BR only.

## Anti-goals

These would make the project a failure even if it works:

- Anything taken from Lucas's employer's codebase, or any mention of the employer, its portal, or its name, in code, commits, docs or paths. EventPass shares no code, names or domain with it.
- The demo being broken or slow to explain. A recruiter must see the whole flow (sign up → PDF → scan → dashboard ticks) in under a minute after the cold start.
- Claims in the README that the code doesn't back.
- Secrets committed to the repo.

## Constraints

- **Versions (latest stable on 2026-11-16):** PHP 8.4, Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Horizon 5, Sanctum 4, Pest 5, Larastan 3.
- **Local machine:**
  - PHP 8.4 with gd, redis, pdo_sqlite and zip. No pdo_pgsql.
  - Node 25.
  - Composer is not installed yet; installing it from getcomposer.org is step one.
  - No Docker and no Redis locally.
  - Local dev and tests use SQLite. Queues run with the `sync` or `database` driver locally, and `Queue::fake()` in tests.
- **Hosting is free tier only:**
  - Render free web service (Docker). It sleeps after 15 minutes idle, and the first request takes about 50 s.
  - Render free Key Value (Redis-compatible, ephemeral) for queues and cache. Fallback if it can't be reached from the free web service: `redis-server` inside the same container.
  - Neon Postgres for the database.
- **Ephemeral disk.** Render's disk is wiped on every deploy and restart. Generated PDFs live on the local disk as a cache. A missing file is regenerated, never treated as an error.
- **One container.** Render free has no background workers, so one container runs the web server, Horizon and the scheduler under a process supervisor.
- **Memory:** 512 MB. One Horizon worker process.
- **Language:** code, identifiers and commits in English. UI and README in pt-BR.
- **Commit attribution:** same as the sibling repos. The author is Lucas's existing git identity, with the `Co-Authored-By: Claude` trailer. No college name anywhere.

## Approaches

Three ways were considered. **Lucas picked 1.**

1. **Laravel + Inertia + React, one app (picked).**
   - This is the official starter kit's path, so it reads as modern full-stack Laravel.
   - One deploy, with session auth and SSE on the same origin.
   - Sanctum is used where it fits: per-device tokens for the gate page.
   - Cost: the whole app sleeps on Render free, so the first visit waits about 50 s.
2. **Laravel API + separate React SPA on Vercel** (the FinTrack/FlowBoard shape).
   - The page loads instantly while the API sleeps.
   - But it needs two deploys, and cross-origin SSE complicates auth, because `EventSource` can't send an `Authorization` header.
   - It also has the same shape as the employer's app.
3. **Livewire.** Rejected:
   - Livewire brings its own realtime, so SSE would disappear from the project.
   - React would disappear too.

## Decided design

### Stack and libraries

- **Scaffold:** the official Laravel React starter kit (Inertia + React + TypeScript + Tailwind + shadcn/ui, with built-in auth). Trim it to what EventPass uses: drop the settings pages the app doesn't need, and keep the profile and password pages.
- **Queue:** Redis queue driver, managed by Horizon. One supervisor, queue `default`, `maxProcesses` 1, tries 3, backoff 5 s.
- **PDF:** `barryvdh/laravel-dompdf`. The ticket is a Blade view rendered to PDF.
- **QR codes:** `chillerlan/php-qrcode` renders a PNG data URI embedded in the Blade view.
- **SSE, server side:** Laravel's `response()->eventStream()`.
- **SSE, client side:** `@laravel/stream-react`'s `useEventStream`.
- **Camera scanning:** `qr-scanner` on the gate page.
- **Web server:** FrankenPHP (classic mode, `dunglas/frankenphp` PHP 8.4 image). It streams responses without the Nginx buffering config, and its thread count is configurable.

### Data model

- **`users`:** the starter kit's table, plus `is_demo` (bool).
- **`events`:**
  - Columns: id, user_id, name, slug (unique), description (nullable), venue, starts_at, capacity, timestamps.
  - The slug is the name slug plus a short random suffix.
- **`registrations`:**
  - Columns: id, event_id, public_id (ULID, unique, used in URLs), name, email, code, ticket_status (`pending|ready|failed`), ticket_path (nullable), checked_in_at (nullable), timestamps.
  - Unique on (event_id, email).
  - `code` is unique: 10 characters from an unambiguous alphabet (no 0/O/1/I), generated with a CSPRNG. It is what the QR encodes.
- **Gate devices** are Sanctum `personal_access_tokens` on the organizer's user:
  - name: the device name the organizer typed.
  - abilities: `["checkin:{event_id}"]`.
  - Revoking a device deletes its token.

### Flows

- **Register:**
  1. `POST /e/{slug}/registrations`, throttled to 10 per minute per IP.
  2. In one transaction, lock the event row (`lockForUpdate`) and count its registrations.
  3. If the event is full, reject with "Inscrições esgotadas". If the email is already registered for this event, reject with "Este e-mail já está inscrito".
  4. Otherwise insert the registration with `pending`.
  5. After commit, dispatch `GenerateTicketPdf` and redirect to `/t/{public_id}`.
- **`GenerateTicketPdf` job:**
  1. Render the Blade view to PDF.
  2. Store it at `tickets/{public_id}.pdf` on the local disk.
  3. Set `ready` and `ticket_path`.
  - On final failure, set `failed`.
  - The job is idempotent: if the registration is already `ready` and the file exists, it does nothing.
- **Ticket page `/t/{public_id}`:**
  - The ULID is the access key.
  - The page subscribes to `/t/{public_id}/stream` while the ticket is `pending`.
  - The stream sends `status` events and ends as soon as the status is `ready` or `failed`, or after 30 s. `EventSource` reconnects on its own.
- **Download `/t/{public_id}/pdf`:**
  - If the ticket is ready and the file exists, stream it.
  - If the file is missing, because a restart wiped the disk, set `pending`, re-dispatch the job, and redirect to the ticket page.
- **Check-in API `POST /api/checkins {code}`:**
  - Auth: `auth:sanctum`, and the token must have `checkin:{event_id}` for the event the code belongs to.
  - Responses:
    - 200 `{name, checked_in_at}`.
    - 404: unknown code.
    - 403: the code belongs to another event.
    - 409 `{checked_in_at}`: already used.
    - 401: missing or revoked token.
  - The check-in is an atomic conditional update (`where checked_in_at is null`). Two simultaneous scans of the same code can't both pass.
  - Throttled to 60 per minute per token.
- **Gate page `/portaria`:**
  - A standalone page with no session auth.
  - The device link is `/portaria#token=…`. The page moves the token from the fragment into `localStorage` and clears the hash, so the token never reaches server logs.
- **Dashboard stream `/events/{event}/stream`:**
  - Session auth plus the event policy (owner only).
  - Every 1 s, read the counts and the latest check-in id. Send a `stats` event only when something changed, plus a comment heartbeat every 15 s.
  - Close after 30 s, so a connection never holds a server thread for longer than that.
  - The client replaces its local state with each `stats` payload.
- **Demo cleanup:**
  - The command `demo:prune` deletes expired demo users, their events, registrations, tokens and ticket files.
  - Render free sleeps, so a nightly schedule would rarely fire (Claude's call). `demo:prune` therefore runs in two places: hourly from the scheduler while the container is awake, and at the start of every "Entrar como demo".
  - The scheduler also runs `horizon:snapshot` every 5 minutes, so Horizon's metrics charts have data.
  - The scheduler runs as `php artisan schedule:work` in the container.
- **Horizon access:**
  - A gate allows users whose email is in `HORIZON_ALLOWED_EMAILS`, plus demo users.
  - Horizon has no read-only mode, so a demo visitor can retry or delete jobs (Claude's call, accepted). Job payloads hold only model ids (`SerializesModels`), so no participant email shows there. A ticket stuck by a deleted job recovers through the download fallback, which re-dispatches the job.

### Security and limits

- **Ownership:** a policy on events, so an organizer only sees and edits their own.
- **Validation:** Form Requests on every write.
- **Per-account limits:**
  - At most 20 events per organizer.
  - At most 10 devices per event.
- **Demo accounts:** can't change their email or password. They can delete their own events and the account itself, which only affects their own sandbox.
- **Demo creation:** throttled to 5 per hour per IP, with at most 200 live demo users. Past that cap, the oldest one is pruned first.
- **Public endpoints:** throttled. Registration emails are validated, never echoed into other pages, and never exposed by the check-in API beyond the participant's name.

### Deployment

- **Dockerfile:**
  - Multi-stage: Node builds the assets, Composer installs the vendor directory without dev packages, and the final stage is FrankenPHP with `pdo_pgsql`, `redis`, `gd` and `zip`.
  - Supervisord runs three programs: FrankenPHP, `php artisan horizon`, and `php artisan schedule:work`.
  - On start: `migrate --force`. Demo data is created per visitor, so there is no boot seeder.
- **`compose.yaml`** for anyone with Docker: app, redis and postgres.
- **`render.yaml` Blueprint:** a web service on the free plan, plus a Key Value instance on the free plan. Secrets (`APP_KEY`, `DB_URL`) are set by Lucas in the dashboard.
- **CI (GitHub Actions):**
  - Pint `--test`, Larastan, and Pest (SQLite) on PHP 8.4.
  - `npm run build` and `tsc --noEmit`.
  - `docker build`.

## Left to the builder

- The visual design of the pages, within the starter kit's shadcn components. Tickets and gate feedback should be readable at arm's length on a phone.
- The exact PDF layout.
- How to structure the code into actions and services, following Laravel conventions.
- The exact Horizon and Larastan settings beyond those named above.
- The order of the work, as long as each step is tested.
