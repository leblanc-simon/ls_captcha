<?php

namespace LsCaptcha\Admin;

use LsCaptcha\ProviderRegistry;
use LsCaptcha\Settings;
use LsCaptcha\ZoneRegistry;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Builds and processes the single configuration screen (getContent + HelperForm).
 * The provider fields are generated from each provider's getConfigFields(), so a
 * new provider needs no admin code.
 */
class ConfigForm
{
    const SUBMIT = 'submitLsCaptcha';

    /** @var \Module */
    private $module;

    /** @var ProviderRegistry */
    private $providers;

    /** @var ZoneRegistry */
    private $zones;

    public function __construct(\Module $module, ProviderRegistry $providers, ZoneRegistry $zones)
    {
        $this->module = $module;
        $this->providers = $providers;
        $this->zones = $zones;
    }

    public function handle(): string
    {
        $output = '';
        if (\Tools::isSubmit(self::SUBMIT)) {
            $output .= $this->process();
        }
        $output .= $this->renderDiagnostics();
        $output .= $this->renderForm();
        $output .= $this->renderToggleScript();

        return $output;
    }

    private function process(): string
    {
        $providerCode = (string) \Tools::getValue(Settings::PROVIDER);
        $provider = $this->providers->get($providerCode);

        $errors = [];
        if ($provider !== null) {
            foreach ($provider->getConfigFields() as $field) {
                if (empty($field['required'])) {
                    continue;
                }
                $key = Settings::providerFieldKey($providerCode, $field['name']);
                $submitted = (string) \Tools::getValue($key);
                $isPassword = ($field['type'] ?? '') === 'password';
                if ($isPassword && $submitted === '') {
                    // Keep existing secret; only an error if none exists yet.
                    if ((string) Settings::get($key) === '') {
                        $errors[] = $field['label'];
                    }
                    continue;
                }
                if ($submitted === '') {
                    $errors[] = $field['label'];
                }
            }
        }

        if (!empty($errors)) {
            return $this->module->displayError(
                $this->module->translate('Please fill the required fields: %s', [implode(', ', $errors)], 'Modules.Lscaptcha.Admin')
            );
        }

        Settings::set(Settings::PROVIDER, $providerCode);

        $timeout = (int) \Tools::getValue(Settings::TIMEOUT);
        if ($timeout < 1) {
            $timeout = Settings::DEFAULT_TIMEOUT;
        } elseif ($timeout > 60) {
            $timeout = 60;
        }
        Settings::set(Settings::TIMEOUT, $timeout);
        Settings::set(Settings::FAIL_OPEN, (int) \Tools::getValue(Settings::FAIL_OPEN) ? 1 : 0);

        $strategy = (string) \Tools::getValue(Settings::LOAD_STRATEGY);
        if (!in_array($strategy, Settings::LOAD_STRATEGIES, true)) {
            $strategy = Settings::DEFAULT_LOAD_STRATEGY;
        }
        Settings::set(Settings::LOAD_STRATEGY, $strategy);
        Settings::set(Settings::CONSENT_COOKIE, trim((string) \Tools::getValue(Settings::CONSENT_COOKIE)));
        Settings::set(Settings::SEND_IP, (int) \Tools::getValue(Settings::SEND_IP) ? 1 : 0);

        foreach ($this->zones->all() as $zone) {
            Settings::set(Settings::zoneKey($zone->getCode()), (int) \Tools::getValue(Settings::zoneKey($zone->getCode())) ? 1 : 0);
        }

        foreach ($this->providers->all() as $p) {
            foreach ($p->getConfigFields() as $field) {
                $key = Settings::providerFieldKey($p->getCode(), $field['name']);
                $value = (string) \Tools::getValue($key);
                if (($field['type'] ?? '') === 'password' && $value === '') {
                    continue; // do not erase an existing secret
                }
                Settings::set($key, $value);
            }
        }

        return $this->module->displayConfirmation(
            $this->module->translate('Settings updated.', [], 'Modules.Lscaptcha.Admin')
        );
    }

    private function renderForm(): string
    {
        $helper = new \HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = \AdminController::$currentIndex . '&configure=' . $this->module->name;
        $helper->submit_action = self::SUBMIT;
        $helper->default_form_language = (int) \Context::getContext()->language->id;
        $helper->fields_value = $this->fieldsValue();

        return $helper->generateForm($this->buildForms());
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildForms(): array
    {
        $forms = [];

        // Section 1: provider selector.
        $providerOptions = [];
        $providerOptions[] = ['id' => '', 'name' => $this->trans('— None —')];
        foreach ($this->providers->choices() as $code => $label) {
            $providerOptions[] = ['id' => $code, 'name' => $label];
        }
        $forms[] = ['form' => [
            'legend' => ['title' => $this->trans('Captcha provider'), 'icon' => 'icon-shield'],
            'input' => [[
                'type' => 'select',
                'label' => $this->trans('Active provider'),
                'name' => Settings::PROVIDER,
                'options' => ['query' => $providerOptions, 'id' => 'id', 'name' => 'name'],
            ]],
        ]];

        // Section 2: zones.
        $zoneInputs = [];
        foreach ($this->zones->all() as $zone) {
            $zoneInputs[] = $this->switchInput(Settings::zoneKey($zone->getCode()), $zone->getLabel());
        }
        $forms[] = ['form' => [
            'legend' => ['title' => $this->trans('Protected zones'), 'icon' => 'icon-map-marker'],
            'input' => $zoneInputs,
        ]];

        // Section 3: provider fields (all rendered, toggled by JS).
        $providerInputs = [];
        foreach ($this->providers->all() as $provider) {
            foreach ($provider->getConfigFields() as $field) {
                $providerInputs[] = $this->providerInput($provider->getCode(), $provider->getLabel(), $field);
            }
        }
        $forms[] = ['form' => [
            'legend' => ['title' => $this->trans('Provider settings'), 'icon' => 'icon-cogs'],
            'input' => $providerInputs,
        ]];

        // Section 4: network.
        $forms[] = ['form' => [
            'legend' => ['title' => $this->trans('Network'), 'icon' => 'icon-globe'],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('HTTP timeout (seconds)'),
                    'name' => Settings::TIMEOUT,
                    'class' => 'fixed-width-sm',
                ],
                $this->switchInput(
                    Settings::FAIL_OPEN,
                    $this->trans('Fail open on network error'),
                    $this->trans('If the verification service is unreachable, accept the submission instead of blocking it.')
                ),
            ],
        ]];

        // Section 5: privacy & loading + submit.
        $forms[] = ['form' => [
            'legend' => ['title' => $this->trans('Privacy & loading'), 'icon' => 'icon-user-secret'],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->trans('Script loading strategy'),
                    'name' => Settings::LOAD_STRATEGY,
                    'desc' => $this->trans('Controls when the third-party captcha script is loaded. Providers requiring consent always wait for consent.'),
                    'options' => ['query' => [
                        ['id' => 'immediate', 'name' => $this->trans('Immediate (on page load)')],
                        ['id' => 'interaction', 'name' => $this->trans('On form interaction (recommended)')],
                        ['id' => 'consent', 'name' => $this->trans('After consent')],
                    ], 'id' => 'id', 'name' => 'name'],
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Consent cookie name'),
                    'name' => Settings::CONSENT_COOKIE,
                    'desc' => $this->trans('Optional. Load the captcha once a cookie with this name exists (to plug in an existing consent banner).'),
                ],
                $this->switchInput(
                    Settings::SEND_IP,
                    $this->trans('Send visitor IP to the provider'),
                    $this->trans('Off by default (data minimization). When on, the visitor IP is forwarded to the verification service.')
                ),
            ],
            'submit' => ['title' => $this->trans('Save')],
        ]];

        return $forms;
    }

    /**
     * @param array<string,mixed> $field
     * @return array<string,mixed>
     */
    private function providerInput(string $providerCode, string $providerLabel, array $field): array
    {
        $key = Settings::providerFieldKey($providerCode, $field['name']);
        $type = $field['type'] ?? 'text';
        $input = [
            'name' => $key,
            'label' => $field['label'],
            'form_group_class' => 'ls-cap-field ls-cap-' . $providerCode,
            'desc' => sprintf('[%s]', $providerLabel) . (isset($field['help']) ? ' ' . $field['help'] : ''),
        ];

        if ($type === 'switch') {
            return array_merge($input, $this->switchValues($key));
        }
        if ($type === 'select') {
            $options = [];
            foreach (($field['options'] ?? []) as $value => $label) {
                $options[] = ['id' => $value, 'name' => $label];
            }
            $input['type'] = 'select';
            $input['options'] = ['query' => $options, 'id' => 'id', 'name' => 'name'];

            return $input;
        }
        if ($type === 'password') {
            $input['type'] = 'password';
            $input['desc'] .= ' ' . $this->trans('Leave blank to keep the current value.');

            return $input;
        }

        // text, url
        $input['type'] = 'text';

        return $input;
    }

    /**
     * @return array<string,mixed>
     */
    private function switchInput(string $name, string $label, string $help = ''): array
    {
        $input = array_merge(['label' => $label], $this->switchValues($name));
        if ($help !== '') {
            $input['desc'] = $help;
        }

        return $input;
    }

    /**
     * @return array<string,mixed>
     */
    private function switchValues(string $name): array
    {
        return [
            'type' => 'switch',
            'name' => $name,
            'is_bool' => true,
            'values' => [
                ['id' => $name . '_on', 'value' => 1, 'label' => $this->trans('Yes')],
                ['id' => $name . '_off', 'value' => 0, 'label' => $this->trans('No')],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fieldsValue(): array
    {
        $values = [
            Settings::PROVIDER => (string) Settings::get(Settings::PROVIDER, ''),
            Settings::TIMEOUT => (int) Settings::get(Settings::TIMEOUT, Settings::DEFAULT_TIMEOUT),
            Settings::FAIL_OPEN => (int) Settings::get(Settings::FAIL_OPEN, 0),
            Settings::LOAD_STRATEGY => (string) Settings::get(Settings::LOAD_STRATEGY, Settings::DEFAULT_LOAD_STRATEGY),
            Settings::CONSENT_COOKIE => (string) Settings::get(Settings::CONSENT_COOKIE, ''),
            Settings::SEND_IP => (int) Settings::get(Settings::SEND_IP, 0),
        ];

        foreach ($this->zones->all() as $zone) {
            $key = Settings::zoneKey($zone->getCode());
            $values[$key] = (int) Settings::get($key, 0);
        }

        foreach ($this->providers->all() as $provider) {
            foreach ($provider->getConfigFields() as $field) {
                $key = Settings::providerFieldKey($provider->getCode(), $field['name']);
                if (($field['type'] ?? '') === 'password') {
                    $values[$key] = ''; // never expose secrets in the form
                    continue;
                }
                $values[$key] = Settings::get($key, $field['default'] ?? '');
            }
        }

        return $values;
    }

    private function renderDiagnostics(): string
    {
        $warnings = [];

        $provider = $this->providers->active();
        $anyZoneEnabled = false;
        foreach ($this->zones->all() as $zone) {
            if ($zone->isEnabled()) {
                $anyZoneEnabled = true;
                break;
            }
        }

        if ($anyZoneEnabled && $provider === null) {
            $warnings[] = ['type' => 'warning', 'message' => $this->trans('A zone is enabled but no captcha provider is selected.')];
        } elseif ($provider !== null && !$provider->isConfigured()) {
            $warnings[] = ['type' => 'warning', 'message' => $this->trans('The selected provider is not fully configured; the captcha is inactive until required fields are filled.')];
        }

        $newsletter = $this->zones->get('newsletter');
        if ($newsletter !== null && $newsletter->isEnabled()) {
            if (!$this->moduleAtLeast('ps_emailsubscription', '2.6.0')) {
                $warnings[] = ['type' => 'warning', 'message' => $this->trans('The newsletter zone requires the module ps_emailsubscription 2.6.0 or newer.')];
            }
        }

        $contact = $this->zones->get('contact');
        if ($contact !== null && $contact->isEnabled()) {
            if (!$this->moduleAtLeast('contactform', '4.1.0')) {
                $warnings[] = ['type' => 'info', 'message' => $this->trans('The contact form module is older than 4.1; the widget will be injected by JavaScript.')];
            }
        }

        if ($provider !== null) {
            // reCAPTCHA v3 + newsletter loads Google on every page (footer block).
            if ($provider->getCode() === 'recaptcha'
                && (string) Settings::get(Settings::providerFieldKey('recaptcha', 'variant')) === 'v3'
                && $newsletter !== null && $newsletter->isEnabled()
            ) {
                $warnings[] = ['type' => 'warning', 'message' => $this->trans('reCAPTCHA v3 with the newsletter zone loads Google on every page (the newsletter block is in the footer). Consider another provider or disabling that zone.')];
            }

            // Consent gating notice.
            if ($provider->requiresConsent()) {
                $warnings[] = ['type' => 'info', 'message' => $this->trans('The selected provider requires visitor consent: its script loads only after consent.')];
            }

            // CSP directives to add for the active provider.
            $csp = $this->formatCsp($provider->getCspDirectives());
            if ($csp !== '') {
                $warnings[] = ['type' => 'info', 'message' => $this->trans('Content Security Policy — if your shop enforces a CSP, allow these sources: %s', [$csp])];
            }
        }

        if (empty($warnings)) {
            return '';
        }

        \Context::getContext()->smarty->assign(['ls_warnings' => $warnings]);

        return $this->module->display(
            rtrim(_PS_MODULE_DIR_, '/') . '/' . $this->module->name . '/' . $this->module->name . '.php',
            'views/templates/admin/diagnostics.tpl'
        );
    }

    private function renderToggleScript(): string
    {
        $selectId = Settings::PROVIDER;

        return '<script>(function(){'
            . 'var sel=document.getElementById("' . $selectId . '");'
            . 'function toggle(){var v=sel?sel.value:"";'
            . 'var rows=document.querySelectorAll(".ls-cap-field");'
            . 'for(var i=0;i<rows.length;i++){'
            . 'rows[i].style.display=rows[i].className.indexOf("ls-cap-"+v)>-1&&v!==""?"":"none";}}'
            . 'if(sel){sel.addEventListener("change",toggle);toggle();}'
            . '})();</script>';
    }

    /**
     * Flatten CSP directives into a readable "directive host host; ..." string.
     *
     * @param array<string,string[]> $directives
     */
    private function formatCsp(array $directives): string
    {
        $parts = [];
        foreach ($directives as $directive => $sources) {
            if (!empty($sources)) {
                $parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode(' ; ', $parts);
    }

    private function moduleAtLeast(string $name, string $version): bool
    {
        $instance = \Module::getInstanceByName($name);
        if (!($instance instanceof \Module)) {
            return false;
        }

        return version_compare((string) $instance->version, $version, '>=');
    }

    /**
     * @param array<int,mixed> $params
     */
    private function trans(string $id, array $params = []): string
    {
        return $this->module->translate($id, $params, 'Modules.Lscaptcha.Admin');
    }
}
