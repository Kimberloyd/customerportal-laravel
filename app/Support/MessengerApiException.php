<?php

namespace App\Support;

/**
 * Ports Flask's MessengerAPIError -- raised when an outbound Facebook
 * reply fails, caught by the caller and surfaced as a flash error
 * rather than a 500.
 */
class MessengerApiException extends \RuntimeException
{
}
