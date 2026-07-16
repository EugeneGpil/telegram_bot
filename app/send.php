<?php

declare(strict_types=1);

/**
 * telegram-send — send a Telegram message via a bot, with an optional Markdown link.
 *
 *   make send TEXT="..." [URL="..."] [CHAT_ID="..."] [DRY_RUN=1]
 *   bin/telegram-send --text "..." [--url "..."] [--chat-id ID] [--dry-run]
 *
 * Requires TELEGRAM_BOT_TOKEN, and (unless --chat-id is given) TELEGRAM_CHAT_ID,
 * in the environment (.env, loaded by docker compose).
 */

function usageAndExit(): never
{
    fwrite(STDERR, <<<EOF
    usage: send.php --text "message" [--url "https://..."] [--chat-id ID] [--dry-run]

    env:   TELEGRAM_BOT_TOKEN   bot token from @BotFather
           TELEGRAM_CHAT_ID     default chat id (overridable with --chat-id)

    --dry-run prints the request payload instead of calling the Telegram API.

    EOF);
    exit(1);
}

function escapeMarkdownV2(string $text): string
{
    $reserved = '_*[]()~`>#+-=|{}.!\\';

    return (string) preg_replace('/([' . preg_quote($reserved, '/') . '])/', '\\\\$1', $text);
}

function escapeMarkdownV2Url(string $url): string
{
    return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
}

function buildMessageText(string $text, ?string $url): string
{
    if ($url === null) {
        return escapeMarkdownV2($text);
    }

    return '[' . escapeMarkdownV2($text) . '](' . escapeMarkdownV2Url($url) . ')';
}

function sendMessage(string $token, string $chatId, string $text): array
{
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'MarkdownV2',
    ];

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        fwrite(STDERR, "telegram-send: curl request failed\n");
        exit(1);
    }

    $response = json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR);
    if ($httpCode !== 200 || $response['ok'] !== true) {
        $description = $response['description'] ?? 'unknown error';
        fwrite(STDERR, "telegram-send: API error ({$httpCode}): {$description}\n");
        exit(1);
    }

    return $response['result'];
}

// ---------------------------------------------------------------- arguments

array_shift($argv);
$text = null;
$url = null;
$chatId = null;
$dryRun = false;

$i = 0;
$count = count($argv);
while ($i < $count) {
    $arg = $argv[$i];
    match ($arg) {
        '--text' => $text = $argv[++$i] ?? usageAndExit(),
        '--url' => $url = $argv[++$i] ?? usageAndExit(),
        '--chat-id' => $chatId = $argv[++$i] ?? usageAndExit(),
        '--dry-run' => $dryRun = true,
        '-h', '--help' => usageAndExit(),
        default => usageAndExit(),
    };
    $i++;
}

if ($text === null || $text === '') {
    usageAndExit();
}

$chatId ??= (string) getenv('TELEGRAM_CHAT_ID');
$token = (string) getenv('TELEGRAM_BOT_TOKEN');

if ($chatId === '') {
    fwrite(STDERR, "telegram-send: no chat id — pass --chat-id or set TELEGRAM_CHAT_ID\n");
    exit(1);
}

$messageText = buildMessageText($text, $url);

if ($dryRun) {
    echo json_encode([
        'chat_id' => $chatId,
        'text' => $messageText,
        'parse_mode' => 'MarkdownV2',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

if ($token === '') {
    fwrite(STDERR, "telegram-send: TELEGRAM_BOT_TOKEN is not set\n");
    exit(1);
}

$result = sendMessage($token, $chatId, $messageText);
echo "OK, message_id: {$result['message_id']}\n";
