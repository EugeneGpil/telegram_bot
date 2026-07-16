# telegram-send

Send a Telegram message from the command line via a bot, with the message
text optionally rendered as a clickable hyperlink. Runs in Docker (PHP 8.5),
so nothing is installed on the host.

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
make build
make send TEXT="hello"
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
telegram-send --text "message" [--url "https://..."] [--chat-id ID] [--dry-run]
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

## Link formatting

When `--url` is given, the message is sent with `parse_mode: MarkdownV2` and
the text formatted as `[text](url)`, which Telegram renders as a hyperlink
showing only the text — the recipient never sees the brackets/parentheses.
Without `--url`, the text is still sent through MarkdownV2 (escaped), so
literal `` _ * [ ] ( ) ~ ` > # + - = | { } . ! `` characters in your message
show up as plain text rather than triggering formatting.

## Configuration (.env)

| Variable | Meaning |
|---|---|
| `TELEGRAM_BOT_TOKEN` | Bot token from @BotFather. |
| `TELEGRAM_CHAT_ID` | Default chat id to send to (overridable with `--chat-id` / `CHAT_ID=`). |
