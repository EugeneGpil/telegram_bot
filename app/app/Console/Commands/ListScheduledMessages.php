<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use Illuminate\Console\Command;

class ListScheduledMessages extends Command
{
    protected $signature = 'telegram:scheduled';

    protected $description = 'List pending scheduled messages';

    public function handle(): int
    {
        $timezone = config('telegram.schedule_timezone');
        $messages = ScheduledMessage::pending()->orderBy('scheduled_for')->get();

        if ($messages->isEmpty()) {
            $this->info('no scheduled messages');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', "Scheduled for ({$timezone})", 'Text', 'Url', 'Chat id', 'Raw', 'Attempts'],
            $messages->map(fn (ScheduledMessage $message) => [
                $message->id,
                $message->scheduled_for->setTimezone($timezone)->format('Y-m-d H:i'),
                $message->text,
                $message->url,
                $message->chat_id,
                $message->raw ? 'yes' : '',
                $message->attempts ?: '',
            ]),
        );

        return self::SUCCESS;
    }
}
