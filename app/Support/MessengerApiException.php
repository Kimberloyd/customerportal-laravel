<?php

namespace App\Support;

/**
 * Ports Flask's MessengerAPIError -- raised when an outbound Facebook
 * reply fails, caught by the caller and surfaced as a flash error
 * rather than a 500.
 */
class MessengerApiException extends \RuntimeException
{
    /** Graph API error_subcode for "sent outside of allowed window". */
    private const OUTSIDE_WINDOW_SUBCODE = 2018278;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?int $subcode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Meta only lets a Page message someone within 24 hours of that person's
     * last message to the Page. Order summaries sent to agents are automated,
     * so the HUMAN_AGENT tag (human replies only) can't be used to stretch it.
     */
    public function isOutsideMessagingWindow(): bool
    {
        return $this->getCode() === 10
            && ($this->subcode === self::OUTSIDE_WINDOW_SUBCODE
                || str_contains($this->getMessage(), 'outside of allowed window'));
    }
}
