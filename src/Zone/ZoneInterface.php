<?php

namespace LsCaptcha\Zone;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A protected location. Adding a new zone means implementing this interface
 * (usually by extending AbstractZone) with one method per hook it handles,
 * registering the class in ZoneRegistry and adding the matching hook<Name>()
 * delegation in the main module class for any brand-new hook.
 */
interface ZoneInterface
{
    /** Short machine code, e.g. 'registration'. */
    public function getCode(): string;

    /** Human label shown next to the enable switch. */
    public function getLabel(): string;

    /**
     * Hooks this zone needs registered.
     *
     * @return string[]
     */
    public function getHooks(): array;

    /** Whether the merchant enabled this zone. */
    public function isEnabled(): bool;
}
