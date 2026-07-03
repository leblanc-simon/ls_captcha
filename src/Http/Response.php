<?php

namespace LsCaptcha\Http;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Response
{
    /** True when the call could not complete (timeout, DNS, TLS...). */
    public bool $networkError = false;

    /** HTTP status code (0 when the call did not complete). */
    public int $status = 0;

    /** Decoded JSON body, or null when the body was not valid JSON. */
    public ?array $data = null;

    /** Raw response body. */
    public ?string $raw = null;
}
