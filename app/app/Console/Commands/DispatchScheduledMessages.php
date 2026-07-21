<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use App\Services\Telegram;
use App\Support\MarkdownV2;
use Illuminate\Console\Command;
use RuntimeException;

class DispatchScheduledMessages extends Command
{
    protected $signature = 'telegram:dispatch';

    protected $description = 'Deliver due scheduled messages; cancel the ones that are too late';

    public function handle(Telegram $telegram): int
    {
        $now = now(config('telegram.schedule_timezone'));

        foreach (ScheduledMessage::due($now)->get() as $message) {
            $label = "#{$message->id} \"{$message->text}\"";

            if (! $message->isOnTimeAt($now) && ! $message->isStillSendableAt($now)) {
                $cutoff = config('telegram.late_cutoff_hour');
                $message->markCancelled("too late: not same day before {$cutoff}:00 ".config('telegram.schedule_timezone'));
                $this->warn("cancelled (too late) {$label}");

                continue;
            }

            $chatId = $message->chat_id ?? config('telegram.chat_id');

            if (blank($chatId)) {
                $message->markCancelled('no chat id: message has none and TELEGRAM_CHAT_ID is not set');
                $this->warn("cancelled (no chat id) {$label}");

                continue;
            }

            $text = $message->raw
                ? $message->text
                : MarkdownV2::format($message->text, $message->url);

            try {
                $message->markSent($telegram->send($chatId, $text));
                $this->info("sent {$label}, message_id: {$message->telegram_message_id}");
            } catch (RuntimeException $e) {
                $this->handleFailure($message, $label, $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function handleFailure(ScheduledMessage $message, string $label, string $error): void
    {
        $message->recordFailedAttempt($error);

        if ($message->attempts >= config('telegram.max_send_attempts')) {
            $message->markCancelled("failed {$message->attempts} times, last: {$error}");
            $this->warn("cancelled (send kept failing) {$label}: {$error}");

            return;
        }

        $this->warn("will retry {$label} (attempt {$message->attempts}): {$error}");
    }
}
