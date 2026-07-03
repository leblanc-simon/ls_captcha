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
    abstract protected function interpret(array $data): bool;

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
        return $this->module->display(
            $this->moduleFile(),
            'views/templates/hook/widget-' . $this->getCode() . '.tpl'
        );
    }

    public function verify(string $token, ?string $remoteIp): bool
    {
        if (trim($token) === '') {
            return false;
        }

        $response = $this->callSiteverify($token, $remoteIp);

        // Technical failure: apply the fail policy (fail-open vs fail-closed).
        if ($response->networkError || $response->status >= 500 || $response->data === null) {
            return $this->isFailOpen();
        }

        return $this->interpret($response->data);
    }

    /**
     * Assign the widget variables then delegate rendering to Module::display.
     * Concrete providers assign their own variables by overriding renderWidget
     * and calling this helper.
     *
     * @param array<string,mixed> $vars
     */
    protected function fetchWidget(array $vars): string
    {
        \Context::getContext()->smarty->assign($vars);

        return $this->module->display(
            $this->moduleFile(),
            'views/templates/hook/widget-' . $this->getCode() . '.tpl'
        );
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
     * @param array<string,mixed> $params
     */
    protected function trans(string $id, array $params = []): string
    {
        return $this->module->translate($id, $params, 'Modules.Lscaptcha.Admin');
    }

    private function moduleFile(): string
    {
        return rtrim(_PS_MODULE_DIR_, '/') . '/' . $this->module->name . '/' . $this->module->name . '.php';
    }
}
