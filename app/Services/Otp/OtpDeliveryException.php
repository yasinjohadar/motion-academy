<?php

namespace App\Services\Otp;

use RuntimeException;

/**
 * No transport is configured to carry a one-time code.
 *
 * Separate from OtpThrottledException because the causes are unrelated: throttling is
 * a normal, expected refusal shown to the user, while this is a deployment mistake
 * that needs an operator.
 */
class OtpDeliveryException extends RuntimeException {}
