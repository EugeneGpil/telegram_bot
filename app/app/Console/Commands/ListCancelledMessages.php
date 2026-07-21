<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use Illuminate\Console\Command;

class ListCancelledMessages extends Command
{
    protected $signature = 'telegram:cancelled';

    protected $description = 'List scheduled messages that were cancelled instead of sent';

    public function handle(): int
    {
        $timezone = config('telegram.schedule_timezone');
        $messages = ScheduledMessage::cancelled()->orderBy('cancelled_at')->get();

        if ($messages->isEmpty()) {
            $this->info('no cancelled messages');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', "Scheduled for ({$timezone})", "Cancelled at ({$timezone})", 'Reason', 'Text', 'Url', 'Chat id'],
            $messages->map(fn (ScheduledMessage $message) => [
                $message->id,
                $message->scheduled_for->setTimezone($timezone)->format('Y-m-d H:i'),
                $message->cancelled_at->setTimezone($timezone)->format('Y-m-d H:i'),
                $message->cancel_reason,
                $message->text,
                $message->url,
                $message->chat_id,
            ]),
        );

        return self::SUCCESS;
    }
}
