<?php

namespace LsCaptcha;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Installer
{
    /** @var \Module */
    private $module;

    public function __construct(\Module $module)
    {
        $this->module = $module;
    }

    public function install(): bool
    {
        $providers = new ProviderRegistry($this->module);
        $zones = new ZoneRegistry($this->module, $providers);

        $hooks = array_merge($zones->allHooks(), ['actionFrontControllerSetMedia']);
        foreach (array_values(array_unique($hooks)) as $hook) {
            if (!$this->module->registerHook($hook)) {
                return false;
            }
        }

        Settings::set(Settings::PROVIDER, '');
        Settings::set(Settings::TIMEOUT, Settings::DEFAULT_TIMEOUT);
        Settings::set(Settings::FAIL_OPEN, 0);
        Settings::set(Settings::LOAD_STRATEGY, Settings::DEFAULT_LOAD_STRATEGY);
        Settings::set(Settings::CONSENT_COOKIE, '');
        Settings::set(Settings::SEND_IP, 0);
        foreach ($zones->all() as $zone) {
            Settings::set(Settings::zoneKey($zone->getCode()), 0);
        }

        return true;
    }

    public function uninstall(): bool
    {
        foreach ($this->allConfigKeys() as $key) {
            Settings::delete($key);
        }

        return true;
    }

    /**
     * Every configuration key the module may have written.
     *
     * @return string[]
     */
    private function allConfigKeys(): array
    {
        $keys = [
            Settings::PROVIDER,
            Settings::TIMEOUT,
            Settings::FAIL_OPEN,
            Settings::LOAD_STRATEGY,
            Settings::CONSENT_COOKIE,
            Settings::SEND_IP,
        ];

        $providers = new ProviderRegistry($this->module);
        foreach ($providers->all() as $provider) {
            foreach ($provider->getConfigFields() as $field) {
                $keys[] = Settings::providerFieldKey($provider->getCode(), $field['name']);
            }
        }

        $zones = new ZoneRegistry($this->module, $providers);
        foreach ($zones->all() as $zone) {
            $keys[] = Settings::zoneKey($zone->getCode());
        }

        return array_values(array_unique($keys));
    }
}
