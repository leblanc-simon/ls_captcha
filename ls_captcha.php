<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use LsCaptcha\Admin\ConfigForm;
use LsCaptcha\Installer;
use LsCaptcha\Provider\ProviderInterface;
use LsCaptcha\ProviderRegistry;
use LsCaptcha\Settings;
use LsCaptcha\ZoneRegistry;

// PSR-4 autoloader for the LsCaptcha\ namespace (works without composer install).
spl_autoload_register(function ($class) {
    $prefix = 'LsCaptcha\\';
    $length = strlen($prefix);
    if (strncmp($class, $prefix, $length) !== 0) {
        return;
    }
    $relative = substr($class, $length);
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

class ls_captcha extends Module
{
    const VERSION = '1.0.0';

    /**
     * Optional CSP nonce applied to the dynamically injected provider scripts.
     * A theme or module can set this (e.g. from its own CSP mechanism) before
     * the front controller renders, so strict-CSP shops keep working.
     *
     * @var string
     */
    public static $scriptNonce = '';

    /** @var ProviderRegistry|null */
    private $providerRegistry;

    /** @var ZoneRegistry|null */
    private $zoneRegistry;

    public function __construct()
    {
        $this->name = 'ls_captcha';
        $this->tab = 'front_office_features';
        $this->version = self::VERSION;
        $this->author = 'Simon Leblanc';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.8.11', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('LS Captcha', [], 'Modules.Lscaptcha.Admin');
        $this->description = $this->trans(
            'Protect registration, login, contact and newsletter forms with the captcha provider of your choice.',
            [],
            'Modules.Lscaptcha.Admin'
        );
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        return parent::install() && (new Installer($this))->install();
    }

    /**
     * Public wrapper around the (protected) Module::trans(), so providers and
     * zones — which are not subclasses of Module — can translate their wordings.
     *
     * @param array<int|string,mixed> $parameters
     */
    public function translate(string $id, array $parameters = [], ?string $domain = null): string
    {
        return $this->trans($id, $parameters, $domain ?? 'Modules.Lscaptcha.Admin');
    }

    public function uninstall()
    {
        return (new Installer($this))->uninstall() && parent::uninstall();
    }

    public function getContent()
    {
        return (new ConfigForm($this, $this->getProviderRegistry(), $this->getZoneRegistry()))->handle();
    }

    // --- Zone display hooks -------------------------------------------------

    public function hookDisplayCustomerAccountForm(array $params): string
    {
        return $this->getZoneRegistry()->renderDisplay('displayCustomerAccountForm', $params);
    }

    public function hookDisplayCustomerLoginFormAfter(array $params): string
    {
        return $this->getZoneRegistry()->renderDisplay('displayCustomerLoginFormAfter', $params);
    }

    public function hookDisplayGDPRConsent(array $params): string
    {
        return $this->getZoneRegistry()->renderDisplay('displayGDPRConsent', $params);
    }

    public function hookDisplayNewsletterRegistration(array $params): string
    {
        return $this->getZoneRegistry()->renderDisplay('displayNewsletterRegistration', $params);
    }

    // --- Registration special-return hooks ----------------------------------

    /**
     * @param array<string,mixed> $params
     * @return \FormField[]
     */
    public function hookAdditionalCustomerFormFields(array $params): array
    {
        $fields = [];
        foreach ($this->getZoneRegistry()->enabledForHook('additionalCustomerFormFields') as $zone) {
            if (method_exists($zone, 'hookAdditionalCustomerFormFields')) {
                $result = $zone->hookAdditionalCustomerFormFields($params);
                if (is_array($result)) {
                    $fields = array_merge($fields, $result);
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<int,\FormField>
     */
    public function hookValidateCustomerFormFields(array $params): array
    {
        $fields = isset($params['fields']) && is_array($params['fields']) ? $params['fields'] : [];
        foreach ($this->getZoneRegistry()->enabledForHook('validateCustomerFormFields') as $zone) {
            if (method_exists($zone, 'hookValidateCustomerFormFields')) {
                $zone->hookValidateCustomerFormFields($params);
            }
        }

        return $fields;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookActionSubmitAccountBefore(array $params): bool
    {
        $ok = true;
        foreach ($this->getZoneRegistry()->enabledForHook('actionSubmitAccountBefore') as $zone) {
            if (method_exists($zone, 'hookActionSubmitAccountBefore') && $zone->hookActionSubmitAccountBefore($params) === false) {
                $ok = false;
            }
        }

        return $ok;
    }

    // --- Login / newsletter action hooks ------------------------------------

    public function hookActionAuthenticationBefore(array $params): void
    {
        foreach ($this->getZoneRegistry()->enabledForHook('actionAuthenticationBefore') as $zone) {
            if (method_exists($zone, 'hookActionAuthenticationBefore')) {
                $zone->hookActionAuthenticationBefore($params);
            }
        }
    }

    public function hookActionNewsletterRegistrationBefore(array $params): void
    {
        foreach ($this->getZoneRegistry()->enabledForHook('actionNewsletterRegistrationBefore') as $zone) {
            if (method_exists($zone, 'hookActionNewsletterRegistrationBefore')) {
                // $params carries hookError as a reference member; the copy preserves it.
                $zone->hookActionNewsletterRegistrationBefore($params);
            }
        }
    }

    // --- Transverse: assets + contact validation ----------------------------

    public function hookActionFrontControllerSetMedia(array $params): void
    {
        $this->loadAssets();

        foreach ($this->getZoneRegistry()->enabledForHook('actionFrontControllerSetMedia') as $zone) {
            if (method_exists($zone, 'hookActionFrontControllerSetMedia')) {
                $zone->hookActionFrontControllerSetMedia($params);
            }
        }
    }

    // --- Internals ----------------------------------------------------------

    public function getProviderRegistry(): ProviderRegistry
    {
        if ($this->providerRegistry === null) {
            $this->providerRegistry = new ProviderRegistry($this);
        }

        return $this->providerRegistry;
    }

    public function getZoneRegistry(): ZoneRegistry
    {
        if ($this->zoneRegistry === null) {
            $this->zoneRegistry = new ZoneRegistry($this, $this->getProviderRegistry());
        }

        return $this->zoneRegistry;
    }

    private function loadAssets(): void
    {
        $controller = $this->context->controller;
        if (!($controller instanceof FrontController)) {
            return;
        }

        $provider = $this->getProviderRegistry()->active();
        if ($provider === null || !$provider->isConfigured()) {
            return;
        }

        if (!$this->isAnyZoneRelevant(isset($controller->php_self) ? (string) $controller->php_self : '')) {
            return;
        }

        $controller->registerJavascript(
            'ls-captcha',
            'modules/' . $this->name . '/views/js/ls_captcha.js',
            ['position' => 'bottom', 'priority' => 200]
        );

        Media::addJsDef(['lsCaptcha' => $this->getFrontConfig($provider)]);
    }

    private function isAnyZoneRelevant(string $phpSelf): bool
    {
        $zones = $this->getZoneRegistry();

        $newsletter = $zones->get('newsletter');
        if ($newsletter !== null && $newsletter->isEnabled()) {
            return true; // the newsletter block can appear on any page
        }

        $map = [
            'authentication' => ['registration', 'login'],
            'registration' => ['registration'],
            'order' => ['registration', 'login'],
            'contact' => ['contact'],
        ];
        if (!isset($map[$phpSelf])) {
            return false;
        }

        foreach ($map[$phpSelf] as $code) {
            $zone = $zones->get($code);
            if ($zone !== null && $zone->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function getFrontConfig(ProviderInterface $provider): array
    {
        $enabledZones = [];
        foreach ($this->getZoneRegistry()->all() as $zone) {
            if ($zone->isEnabled()) {
                $enabledZones[] = $zone->getCode();
            }
        }

        // Consent-requiring providers always wait for consent, regardless of the
        // global strategy chosen by the merchant.
        $strategy = $provider->requiresConsent()
            ? 'consent'
            : (string) Settings::get(Settings::LOAD_STRATEGY, Settings::DEFAULT_LOAD_STRATEGY);

        return [
            'tokenField' => Settings::TOKEN_FIELD,
            'provider' => $provider->getCode(),
            'providerLabel' => $provider->getLabel(),
            'providerTokenField' => $provider->getTokenFieldName(),
            'behavior' => $provider->getClientBehavior(),
            'scripts' => array_values($provider->getFrontScripts()),
            'siteKey' => $provider->getPublicSiteKey(),
            'zones' => $enabledZones,
            'extra' => $provider->getFrontExtra(),
            'loadStrategy' => $strategy,
            'requiresConsent' => $provider->requiresConsent(),
            'consentCookie' => (string) Settings::get(Settings::CONSENT_COOKIE, ''),
            'nonce' => (string) self::$scriptNonce,
            'messages' => [
                'invalid' => $this->trans('Captcha verification failed. Please try again.', [], 'Modules.Lscaptcha.Shop'),
                'consentNotice' => $this->trans(
                    'This form is protected by %s. Load it to continue.',
                    [$provider->getLabel()],
                    'Modules.Lscaptcha.Shop'
                ),
                'consentButton' => $this->trans('Load the captcha', [], 'Modules.Lscaptcha.Shop'),
            ],
        ];
    }
}
