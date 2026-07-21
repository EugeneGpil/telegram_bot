# telegram-send

Send a Telegram message from the command line via a bot — immediately or
scheduled for later — with the message text optionally rendered as a
clickable hyperlink. A Laravel 13 console app running in Docker (PHP 8.5),
so nothing is installed on the host.

Everything is an artisan command under the hood:

| Command | Purpose |
|---|---|
| `telegram:send` | Send now, or queue with `--at` |
| `telegram:dispatch` | Deliver due queued messages (run by the scheduler) |
| `telegram:scheduled` | List pending messages |
| `telegram:cancelled` | List messages that were cancelled instead of sent |

The Laravel app lives in `app/`, Docker files in `docker/`;
`docker-compose.yml`, `.env`, and the `Makefile` stay in the repo root
(Laravel is pointed at the root `.env` in `app/bootstrap/app.php`).
Scheduled messages live in SQLite (`app/database/database.sqlite`,
git-ignored) as `App\Models\ScheduledMessage` rows; sending goes through
`App\Services\Telegram`; MarkdownV2 escaping lives in `App\Support\MarkdownV2`.

## Requirements

- Docker with the compose plugin
- `make`
- A Telegram bot (see below)

## Creating the bot

1. In Telegram, open a chat with **@BotFather**.
2. Send `/newbot` and follow the prompts (name, username).
3. BotFather replies with an API token — this is `TELEGRAM_BOT_TOKEN`.

## Finding your chat id

1. Send any message to your new bot (a DM works fine).
2. Open in a browser: `https://api.telegram.org/bot<TOKEN>/getUpdates`
3. Look for `"chat":{"id": ...}` in the response — that's `TELEGRAM_CHAT_ID`.

For a group: add the bot to the group, send a message there, then repeat
step 2 — the group's id is negative.

## Installation

```bash
cd /my_dev/telegram_bot
cp .env.example .env
```

Fill in `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` in `.env`, then:

```bash
make build                          # build the PHP image
make artisan CMD="key:generate"     # on a fresh clone: also composer install first
make artisan CMD="migrate"
make send TEXT="hello"
```

On a fresh clone (no `vendor/`):

```bash
docker compose run --rm telegram composer install
```

### Make it accessible from everywhere (optional)

The repo ships a wrapper at `bin/telegram-send` that finds the repo on its
own, so a symlink from any `PATH` directory is enough:

```bash
ln -s "$(pwd)/bin/telegram-send" ~/.local/bin/telegram-send
```

Then from any directory:

```bash
telegram-send --text "hello"
```

## Usage

```bash
make send TEXT="message" [URL="https://..."] [CHAT_ID="..."] [DRY_RUN=1]
telegram-send --text "message" [--url "https://..."] [--chat-id ID] [--raw] [--dry-run]
```

Examples:

```bash
make send TEXT="deploy finished"
make send TEXT="build failed, see log" URL="https://ci.example.com/run/42"
telegram-send --text "click here" --url "https://example.com"
telegram-send --text "dry run test" --url "https://example.com" --dry-run
```

`--chat-id` / `CHAT_ID=` overrides the default `.env` chat for a one-off
send to a different chat or group.

`--dry-run` / `DRY_RUN=1` prints the request payload instead of calling the
Telegram API — useful to check formatting before a token/chat id exist, or
before sending for real.

## Scheduled messages

`--at` queues the message instead of sending it; a cron-driven dispatcher
delivers it when due:

```bash
telegram-send --text "standup!" --at "10:00"              # today
telegram-send --text "release day" --at "2026-07-25 09:30"
make send TEXT="standup!" AT="10:00"
```

Times are interpreted in `SCHEDULE_TZ` from `.env` (default `+07:00`).

Start the dispatcher — a container running the Laravel scheduler
(`php artisan schedule:work`), which runs `telegram:dispatch` every minute:

```bash
docker compose up -d dispatcher
```

One-time setup — the container restarts on its own after reboots (assuming
the Docker daemon starts at boot: `systemctl is-enabled docker`). Watch it
with `docker compose logs -f dispatcher`.

If the machine was off (or asleep) at the scheduled time, the message is
sent on the first dispatcher run after power-on — but only while it is still
the **same day and before 18:00** (in `SCHEDULE_TZ`). Later than that the message is not
sent; it is marked cancelled with the reason, keeping the original input.
Deliveries within 5 minutes of the scheduled time always count as on time
(so a message scheduled for 19:00 still goes out at 19:00). The grace
period, cutoff hour, and retry limit are in `app/config/telegram.php`.

Inspect the queue and the cancelled log:

```bash
telegram-send --list-scheduled    # or: make scheduled
telegram-send --list-cancelled    # or: make cancelled
```

A failed send (network down right after wake-up, etc.) is retried on the
next dispatcher run, up to 5 attempts, then also lands in the cancelled
list with the last error recorded.

## Link formatting

Two ways to send a link:

1. **`--url` flag** — the whole message becomes one hyperlink:
   `--text "click here" --url "https://example.com"` arrives as a single
   clickable "click here".
2. **Inline `[text](url)` in the message** — any such construct inside
   `--text` is kept as a working link, mixed with normal text:
   `--text "see [the log](https://ci.example.com/42) and fix it"` arrives
   as "see the log and fix it" with "the log" clickable.

Everything else is MarkdownV2-escaped, so literal
`` _ * [ ] ( ) ~ ` > # + - = | { } . ! `` characters in your message show up
as plain text rather than triggering formatting or API errors.

`--raw` skips all escaping and sends the text as-is with
`parse_mode: MarkdownV2` — for callers that build a fully-escaped MarkdownV2
message themselves (bold, multiple links, etc.), e.g. the `daily` skill.

## Configuration (.env)

| Variable | Meaning |
|---|---|
| `TELEGRAM_BOT_TOKEN` | Bot token from @BotFather. |
| `TELEGRAM_CHAT_ID` | Default chat id to send to (overridable with `--chat-id` / `CHAT_ID=`). |
| `SCHEDULE_TZ` | Timezone for `--at` times and the 18:00 late-send cutoff (`+07:00` or `Asia/Bangkok` style). Default: `+07:00`. |
