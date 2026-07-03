<?php

namespace LsCaptcha;

use LsCaptcha\Provider\CapProvider;
use LsCaptcha\Provider\FriendlyCaptchaProvider;
use LsCaptcha\Provider\HcaptchaProvider;
use LsCaptcha\Provider\McaptchaProvider;
use LsCaptcha\Provider\ProviderInterface;
use LsCaptcha\Provider\RecaptchaProvider;
use LsCaptcha\Provider\TurnstileProvider;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProviderRegistry
{
    /** @var array<string,ProviderInterface> */
    private $providers = [];

    public function __construct(\Module $module)
    {
        foreach (self::classes() as $class) {
            /** @var ProviderInterface $provider */
            $provider = new $class($module);
            $this->providers[$provider->getCode()] = $provider;
        }
    }

    /**
     * The single place to list captcha providers. Add a class here to register a new one.
     *
     * @return string[]
     */
    public static function classes(): array
    {
        return [
            CapProvider::class,
            RecaptchaProvider::class,
            HcaptchaProvider::class,
            TurnstileProvider::class,
            McaptchaProvider::class,
            FriendlyCaptchaProvider::class,
        ];
    }

    /**
     * @return array<string,ProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(?string $code): ?ProviderInterface
    {
        if ($code === null || $code === '') {
            return null;
        }

        return $this->providers[$code] ?? null;
    }

    /** The provider currently selected in the configuration. */
    public function active(): ?ProviderInterface
    {
        return $this->get((string) Settings::get(Settings::PROVIDER, ''));
    }

    /**
     * code => label, for the admin selector.
     *
     * @return array<string,string>
     */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->providers as $code => $provider) {
            $choices[$code] = $provider->getLabel();
        }

        return $choices;
    }
}
