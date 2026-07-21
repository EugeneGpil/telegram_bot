<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use App\Services\Telegram;
use App\Support\MarkdownV2;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use RuntimeException;

class SendMessage extends Command
{
    protected $signature = 'telegram:send
        {--text= : Message text (inline [text](url) links are preserved)}
        {--url= : Turn the whole message into one link to this URL}
        {--chat-id= : Override the default TELEGRAM_CHAT_ID}
        {--raw : Send text as-is (caller is responsible for MarkdownV2 escaping)}
        {--at= : Schedule for later instead of sending now ("Y-m-d H:i" or "H:i" for today, in SCHEDULE_TZ)}
        {--dry-run : Print the request payload (or the queued row) instead of acting}';

    protected $description = 'Send a Telegram message now, or schedule it with --at';

    public function handle(Telegram $telegram): int
    {
        $text = (string) $this->option('text');

        if ($text === '') {
            $this->error('--text is required');

            return self::FAILURE;
        }

        if ($this->option('at') !== null) {
            return $this->schedule($text);
        }

        return $this->sendNow($telegram, $text);
    }

    private function sendNow(Telegram $telegram, string $text): int
    {
        $chatId = $this->option('chat-id') ?? config('telegram.chat_id');

        if (blank($chatId)) {
            $this->error('no chat id — pass --chat-id or set TELEGRAM_CHAT_ID');

            return self::FAILURE;
        }

        $messageText = $this->option('raw')
            ? $text
            : MarkdownV2::format($text, $this->option('url'));

        if ($this->option('dry-run')) {
            $this->line(json_encode(
                $telegram->payload($chatId, $messageText),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        }

        try {
            $messageId = $telegram->send($chatId, $messageText);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("OK, message_id: {$messageId}");

        return self::SUCCESS;
    }

    private function schedule(string $text): int
    {
        $timezone = config('telegram.schedule_timezone');
        $scheduledFor = $this->parseAt((string) $this->option('at'), $timezone);

        if ($scheduledFor === null) {
            return self::FAILURE;
        }

        $message = new ScheduledMessage([
            'text' => $text,
            'url' => $this->option('url'),
            'chat_id' => $this->option('chat-id'),
            'raw' => (bool) $this->option('raw'),
            // stored in UTC like every other timestamp; Eloquent would otherwise
            // save the +07:00 wall-clock digits as-is and read them back as UTC
            'scheduled_for' => $scheduledFor->clone()->utc(),
        ]);

        if ($this->option('dry-run')) {
            $this->line($message->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $message->save();
        $this->info("queued for {$scheduledFor->format('Y-m-d H:i')} ({$timezone})");

        return self::SUCCESS;
    }

    private function parseAt(string $input, string $timezone): ?Carbon
    {
        try {
            $scheduledFor = Carbon::createFromFormat('!Y-m-d H:i', $input, $timezone);
        } catch (InvalidFormatException) {
            try {
                $scheduledFor = Carbon::createFromFormat('!H:i', $input, $timezone)
                    ->setDateFrom(Carbon::now($timezone));
            } catch (InvalidFormatException) {
                $this->error("cannot parse --at \"{$input}\" (expected \"Y-m-d H:i\" or \"H:i\")");

                return null;
            }
        }

        if ($scheduledFor->isPast()) {
            $this->error("--at time {$scheduledFor->format('Y-m-d H:i')} ({$timezone}) is in the past");

            return null;
        }

        return $scheduledFor;
    }
}
