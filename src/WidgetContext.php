<?php

namespace LsCaptcha;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Value object passed to a provider when it renders its widget, so the widget
 * can adapt to the zone it is displayed in (e.g. reCAPTCHA v3 action,
 * Turnstile data-action).
 */
class WidgetContext
{
    /** @var string */
    private $zone;

    public function __construct(string $zone)
    {
        $this->zone = $zone;
    }

    public function getZone(): string
    {
        return $this->zone;
    }
}
