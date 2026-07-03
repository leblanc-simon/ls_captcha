<?php

namespace LsCaptcha;

use LsCaptcha\Zone\ContactZone;
use LsCaptcha\Zone\LoginZone;
use LsCaptcha\Zone\NewsletterZone;
use LsCaptcha\Zone\RegistrationZone;
use LsCaptcha\Zone\ZoneInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ZoneRegistry
{
    /** @var array<string,ZoneInterface> */
    private $zones = [];

    public function __construct(\Module $module, ProviderRegistry $providers)
    {
        foreach (self::classes() as $class) {
            /** @var ZoneInterface $zone */
            $zone = new $class($module, $providers);
            $this->zones[$zone->getCode()] = $zone;
        }
    }

    /**
     * The single place to list protected zones. Add a class here to register a new one.
     *
     * @return string[]
     */
    public static function classes(): array
    {
        return [
            RegistrationZone::class,
            LoginZone::class,
            ContactZone::class,
            NewsletterZone::class,
        ];
    }

    /**
     * @return array<string,ZoneInterface>
     */
    public function all(): array
    {
        return $this->zones;
    }

    public function get(string $code): ?ZoneInterface
    {
        return $this->zones[$code] ?? null;
    }

    /**
     * Union of every zone's hooks (used at install time).
     *
     * @return string[]
     */
    public function allHooks(): array
    {
        $hooks = [];
        foreach ($this->zones as $zone) {
            $hooks = array_merge($hooks, $zone->getHooks());
        }

        return array_values(array_unique($hooks));
    }

    /**
     * Enabled zones that declare a given hook.
     *
     * @return ZoneInterface[]
     */
    public function enabledForHook(string $hookName): array
    {
        $matches = [];
        foreach ($this->zones as $zone) {
            if ($zone->isEnabled() && in_array($hookName, $zone->getHooks(), true)) {
                $matches[] = $zone;
            }
        }

        return $matches;
    }

    /**
     * Route a display hook to the enabled zones and concatenate their output.
     *
     * @param array<string,mixed> $params
     */
    public function renderDisplay(string $hookName, array $params): string
    {
        $method = 'hook' . ucfirst($hookName);
        $output = '';
        foreach ($this->enabledForHook($hookName) as $zone) {
            if (method_exists($zone, $method)) {
                $result = $zone->{$method}($params);
                if (is_string($result)) {
                    $output .= $result;
                }
            }
        }

        return $output;
    }
}
