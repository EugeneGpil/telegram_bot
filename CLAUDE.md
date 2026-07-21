# Project rules

## Reference artisan commands by class name

When referencing an artisan command in code (scheduler, `Artisan::call`,
tests), use the command class, never the signature string:

```php
Schedule::command(DispatchScheduledMessages::class)->everyMinute(); // correct
Schedule::command('telegram:dispatch')->everyMinute();              // forbidden
```

Class names survive renames and are clickable/refactorable in the IDE.
