<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Client;
use LsCaptcha\Http\Response;
use LsCaptcha\Settings;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * Global callback the provider scripts call once their API is ready
     * (`onload=` query parameter). Defined by ls_captcha.js.
     */
    const ONLOAD_CALLBACK = 'lsCaptchaOnload';

    /** @var \Module */
    protected $module;

    public function __construct(\Module $module)
    {
        $this->module = $module;
    }

    abstract public function getCode(): string;

    abstract public function getConfigFields(): array;

    abstract public function getTokenFieldName(): string;

    /**
     * Build the siteverify request. Returns a Response (network errors flagged).
     */
    abstract protected function callSiteverify(string $token, ?string $remoteIp): Response;

    /**
     * Interpret a successful (decoded) siteverify response as pass/fail.
     *
     * @param array<string,mixed> $data
     */
    abstract protected function interpret(array $data, WidgetContext $context): bool;

    public function isConfigured(): bool
    {
        foreach ($this->getConfigFields() as $field) {
            if (!empty($field['required']) && (string) $this->conf($field['name']) === '') {
                return false;
            }
        }

        return true;
    }

    public function getPublicSiteKey(): string
    {
        return (string) $this->conf('site_key');
    }

    public function getClientBehavior(): string
    {
        return 'auto';
    }

    public function requiresConsent(): bool
    {
        return false;
    }

    public function getCspDirectives(): array
    {
        return [];
    }

    public function getFrontExtra(): array
    {
        return [];
    }

    public function renderWidget(WidgetContext $context): string
    {
        return $this->fetchWidget($context, []);
    }

    public function verify(string $token, ?string $remoteIp, WidgetContext $context): bool
    {
        if (trim($token) === '') {
            return false;
        }

        $response = $this->callSiteverify($token, $remoteIp);

        // Service unreachable: apply the fail policy (fail-open vs fail-closed).
        if ($response->networkError || $response->status >= 500) {
            $this->log(sprintf('verification service unreachable (HTTP status %d).', $response->status));

            return $this->isFailOpen();
        }

        // The service answered, but not with JSON (wrong URL, proxy page...):
        // a configuration problem, never a reason to let the submission through.
        if ($response->data === null) {
            $this->log(sprintf('unexpected non-JSON answer from the verification service (HTTP status %d).', $response->status));

            return false;
        }

        return $this->interpret($response->data, $context);
    }

    /**
     * Render the shared widget wrapper (zone, relocation flag, translated
     * front messages) around the provider template, with variables scoped to
     * this render instead of the global Smarty instance.
     *
     * @param array<string,mixed> $vars
     */
    protected function fetchWidget(WidgetContext $context, array $vars): string
    {
        $wrapper = $this->module->getTemplatePath('widget.tpl');
        $inner = $this->module->getTemplatePath('widget-' . $this->getCode() . '.tpl');
        if ($wrapper === null || $inner === null) {
            return '';
        }

        $smarty = \Context::getContext()->smarty;
        $template = $smarty->createTemplate($wrapper, $smarty);
        $template->assign(array_merge($vars, [
            'ls_provider' => $this->getCode(),
            'ls_zone' => $context->getZone(),
            'ls_relocate' => $context->isRelocated(),
            'ls_inner_template' => $inner,
            'ls_messages' => $this->frontMessages(),
        ]));

        return $template->fetch();
    }

    /**
     * Wordings used by ls_captcha.js, exposed through data- attributes.
     *
     * @return array<string,string>
     */
    protected function frontMessages(): array
    {
        return [
            'consent_notice' => $this->module->translate(
                'This form is protected by %s. Load it to continue.',
                [$this->getLabel()],
                'Modules.Lscaptcha.Shop'
            ),
            'consent_button' => $this->module->translate('Load the captcha', [], 'Modules.Lscaptcha.Shop'),
            'consent_required' => $this->module->translate(
                'Please load the captcha before submitting the form.',
                [],
                'Modules.Lscaptcha.Shop'
            ),
            'unavailable' => $this->module->translate(
                'The captcha could not be loaded. Please try again.',
                [],
                'Modules.Lscaptcha.Shop'
            ),
        ];
    }

    /**
     * Append the onload callback to a provider script URL.
     */
    protected function withOnload(string $url): string
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'onload=' . self::ONLOAD_CALLBACK;
    }

    /**
     * @return mixed
     */
    protected function conf(string $fieldName)
    {
        return Settings::get(Settings::providerFieldKey($this->getCode(), $fieldName));
    }

    protected function client(): Client
    {
        return new Client((int) Settings::get(Settings::TIMEOUT, Settings::DEFAULT_TIMEOUT));
    }

    protected function isFailOpen(): bool
    {
        return (bool) Settings::get(Settings::FAIL_OPEN, 0);
    }

    /**
     * Technical log for the merchant (Advanced parameters > Logs). Identical
     * messages are not duplicated, so an outage does not flood the table.
     */
    protected function log(string $message): void
    {
        \PrestaShopLogger::addLog('ls_captcha [' . $this->getCode() . ']: ' . $message, 2, null, 'ls_captcha');
    }

    /**
     * @param array<string,mixed> $params
     */
    protected function trans(string $id, array $params = []): string
    {
        return $this->module->translate($id, $params, 'Modules.Lscaptcha.Admin');
    }
}
