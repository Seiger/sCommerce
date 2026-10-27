<?php namespace Seiger\sCommerce\Services;

/**
 * A measured quantity or a quantity setting was refused.
 *
 * @since 1.5.0
 */
final class MeasuredQuantityException extends \InvalidArgumentException
{
    /**
     * @param string $messageKey `sCommerce::global` translation key.
     * @param array<string, string|int> $replace Translation placeholders.
     * @param int $status HTTP status for manager and API endpoints.
     */
    public function __construct(public readonly string $messageKey, public readonly array $replace = [], public readonly int $status = 422)
    {
        parent::__construct($messageKey, $status);
    }

    /**
     * Translated message in the current or the given language.
     *
     * @since 1.5.0
     */
    public function translated(?string $locale = null): string
    {
        $key = 'sCommerce::global.' . $this->messageKey;
        $text = __($key, $this->replace, $locale);

        return is_string($text) && $text !== $key ? $text : $this->messageKey;
    }
}
