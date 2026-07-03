<?php

namespace LsCaptcha;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Central access point to the module configuration.
 *
 * Every key is prefixed with LS_CAPTCHA_ so uninstall can purge them all and so
 * provider/zone keys can be derived deterministically from their code.
 */
class Settings
{
    const PREFIX = 'LS_CAPTCHA_';

    const PROVIDER = 'LS_CAPTCHA_PROVIDER';
    const TIMEOUT = 'LS_CAPTCHA_TIMEOUT';
    const FAIL_OPEN = 'LS_CAPTCHA_FAIL_OPEN';

    /** How the front loads the provider script: immediate | interaction | consent. */
    const LOAD_STRATEGY = 'LS_CAPTCHA_LOAD_STRATEGY';

    /** Optional cookie name whose presence signals consent (CMP integration). */
    const CONSENT_COOKIE = 'LS_CAPTCHA_CONSENT_COOKIE';

    /** Whether to forward the visitor IP to the verification service. */
    const SEND_IP = 'LS_CAPTCHA_SEND_IP';

    /** Name of the hidden field carrying the token in every protected form. */
    const TOKEN_FIELD = 'ls_captcha_token';

    const DEFAULT_TIMEOUT = 5;
    const DEFAULT_LOAD_STRATEGY = 'interaction';

    /** Allowed values for LOAD_STRATEGY. */
    const LOAD_STRATEGIES = ['immediate', 'interaction', 'consent'];

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = \Configuration::get($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function set(string $key, $value): bool
    {
        return (bool) \Configuration::updateValue($key, $value);
    }

    public static function delete(string $key): bool
    {
        return (bool) \Configuration::deleteByName($key);
    }

    public static function zoneKey(string $zoneCode): string
    {
        return self::PREFIX . 'ZONE_' . strtoupper($zoneCode);
    }

    public static function providerFieldKey(string $providerCode, string $fieldName): string
    {
        return self::PREFIX . strtoupper($providerCode) . '_' . strtoupper($fieldName);
    }
}
