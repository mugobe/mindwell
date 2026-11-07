<?php

namespace App\Services;

use RuntimeException;

/**
 * A MarzPay refusal or a failed call. `userMessage` is safe to show a client; the
 * technical message goes to the log only.
 */
class MarzPayException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0, public readonly ?string $errorCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(int $http, array $body): self
    {
        $msg = (string) ($body['message'] ?? 'MarzPay refused the request.');
        if (! empty($body['errors']) && is_array($body['errors'])) {
            $msg .= ' '.json_encode($body['errors']);
        }

        $code = $body['error_code'] ?? null;
        // Some refusals come without an error_code; recognise the common ones by their words.
        if (! $code && preg_match('/not\s+subscribed|subscribe/i', $msg)) {
            $code = 'SERVICE_NOT_SUBSCRIBED';
        } elseif (! $code && $http === 401) {
            $code = 'UNAUTHORIZED';
        }

        return new self($msg, $http, $code);
    }

    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'INVALID_PHONE_NUMBER' => 'That Mobile Money number could not be used. Please check it and try again.',
            'VALIDATION_ERROR' => 'The payment details were not accepted. Please check your number and try again.',
            'SERVICE_NOT_SUBSCRIBED', 'SERVICE_NOT_AVAILABLE', 'ACCOUNT_FROZEN', 'UNAUTHORIZED', 'FORBIDDEN', 'NOT_CONFIGURED' => 'Mobile Money payments are not available just now, so nothing was charged. Please try again later or contact us.',
            default => "We couldn't reach the payment service just now, so nothing was charged. Please check your connection and try again in a moment.",
        };
    }
}
