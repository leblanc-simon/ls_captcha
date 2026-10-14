<?php

namespace LsCaptcha;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Value object passed to a provider when it renders or verifies a widget, so
 * it can adapt to the zone it serves (e.g. reCAPTCHA v3 / Turnstile action).
 */
class WidgetContext
{
    /** @var string */
    private $zone;

    /** @var bool */
    private $relocate;

    /**
     * @param bool $relocate Whether the front script must move the widget into
     *                       the zone form (the hook renders it elsewhere).
     */
    public function __construct(string $zone, bool $relocate = false)
    {
        $this->zone = $zone;
        $this->relocate = $relocate;
    }

    public function getZone(): string
    {
        return $this->zone;
    }

    public function isRelocated(): bool
    {
        return $this->relocate;
    }
}
