<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Telegram MarkdownV2 formatting: escapes reserved characters while keeping
 * inline [text](url) constructs as working links.
 */
class MarkdownV2
{
    private const string RESERVED = '_*[]()~`>#+-=|{}.!\\';

    /** Builds the final message text: either the whole text as one link, or escaped text with inline links preserved. */
    public static function format(string $text, ?string $url): string
    {
        if ($url === null) {
            return self::escapePreservingLinks($text);
        }

        return '['.self::escape($text).']('.self::escapeUrl($url).')';
    }

    public static function escape(string $text): string
    {
        return (string) preg_replace('/(['.preg_quote(self::RESERVED, '/').'])/', '\\\\$1', $text);
    }

    public static function escapeUrl(string $url): string
    {
        return Str::replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }

    private static function escapePreservingLinks(string $text): string
    {
        $parts = preg_split(
            '/\[([^\[\]]+)\]\(([^()\s]+)\)/',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        if ($parts === false) {
            return self::escape($text);
        }

        // parts repeat as: plain, link text, link url, plain, ...
        return collect($parts)
            ->chunk(3)
            ->map(function ($chunk) {
                [$plain, $linkText, $linkUrl] = array_pad($chunk->values()->all(), 3, null);

                $result = self::escape($plain);
                if ($linkText !== null && $linkUrl !== null) {
                    $result .= '['.self::escape($linkText).']('.self::escapeUrl($linkUrl).')';
                }

                return $result;
            })
            ->implode('');
    }
}
