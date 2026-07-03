<?php

namespace LsCaptcha\Zone;

use LsCaptcha\Provider\ProviderInterface;
use LsCaptcha\ProviderRegistry;
use LsCaptcha\Settings;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractZone implements ZoneInterface
{
    /** @var \Module */
    protected $module;

    /** @var ProviderRegistry */
    protected $providers;

    /**
     * Request-scoped cache: a captcha token is single-use, so complementary
     * hooks (e.g. actionSubmitAccountBefore + validateCustomerFormFields) must
     * share one verification instead of consuming the token twice.
     *
     * @var array<string,bool>
     */
    private static $verifyCache = [];

    public function __construct(\Module $module, ProviderRegistry $providers)
    {
        $this->module = $module;
        $this->providers = $providers;
    }

    abstract public function getCode(): string;

    public function isEnabled(): bool
    {
        return (bool) Settings::get(Settings::zoneKey($this->getCode()), 0);
    }

    /** The active provider, only if it is fully configured. */
    protected function provider(): ?ProviderInterface
    {
        $provider = $this->providers->active();

        return ($provider !== null && $provider->isConfigured()) ? $provider : null;
    }

    protected function renderWidget(string $zoneCode): string
    {
        $provider = $this->provider();
        if ($provider === null) {
            return '';
        }

        return $provider->renderWidget(new WidgetContext($zoneCode));
    }

    /**
     * Verify the token submitted with the current request.
     *
     * Returns true (pass) when no configured provider is available, so a
     * misconfigured module never locks customers out of the shop.
     */
    protected function verifyRequest(): bool
    {
        $provider = $this->provider();
        if ($provider === null) {
            return true;
        }

        $token = (string) \Tools::getValue(Settings::TOKEN_FIELD);
        $cacheKey = $provider->getCode() . '|' . $token;

        if (!array_key_exists($cacheKey, self::$verifyCache)) {
            // Data minimization: only forward the visitor IP when explicitly enabled.
            $remoteIp = Settings::get(Settings::SEND_IP, 0) ? \Tools::getRemoteAddr() : null;
            self::$verifyCache[$cacheKey] = $provider->verify($token, $remoteIp);
        }

        return self::$verifyCache[$cacheKey];
    }

    protected function errorMessage(): string
    {
        return $this->module->translate(
            'Captcha verification failed. Please try again.',
            [],
            'Modules.Lscaptcha.Shop'
        );
    }

    protected function controller(): ?\FrontController
    {
        $context = \Context::getContext();
        if ($context === null || !($context->controller instanceof \FrontController)) {
            return null;
        }

        return $context->controller;
    }

    protected function currentPhpSelf(): string
    {
        $controller = $this->controller();

        return ($controller !== null && isset($controller->php_self)) ? (string) $controller->php_self : '';
    }
}
