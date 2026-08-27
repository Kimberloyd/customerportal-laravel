<?php

namespace App\Support;

/**
 * Raised when an outbound Semaphore SMS send fails. Mirrors
 * MessengerApiException's role for FacebookMessenger -- caught by the
 * caller (OrderNotifications) and logged rather than allowed to fail the
 * request that triggered it.
 */
class SmsApiException extends \RuntimeException
{
}
