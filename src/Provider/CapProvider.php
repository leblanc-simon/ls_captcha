<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Response;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Cap (https://capjs.js.org) — self-hosted, proof-of-work.
 */
class CapProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'cap';
    }

    public function getLabel(): string
    {
        return $this->trans('%provider% (self-hosted)', ['%provider%' => 'Cap']);
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'instance_url', 'type' => 'url', 'required' => true, 'label' => $this->trans('Instance URL')],
            ['name' => 'site_key', 'type' => 'text', 'required' => true, 'label' => $this->trans('Site key')],
            ['name' => 'secret_key', 'type' => 'password', 'required' => true, 'label' => $this->trans('Secret key')],
        ];
    }

    public function getTokenFieldName(): string
    {
        return 'cap-token';
    }

    public function getFrontScripts(): array
    {
        $instance = $this->instanceUrl();
        if ($instance === '') {
            return [];
        }

        return [
            ['id' => 'ls-cap-widget', 'url' => $instance . '/assets/widget.js'],
            ['id' => 'ls-cap-floating', 'url' => $instance . '/assets/floating.js'],
        ];
    }

    public function getFrontExtra(): array
    {
        $instance = $this->instanceUrl();

        return $instance === '' ? [] : ['capWasmUrl' => $instance . '/assets/cap_wasm.js'];
    }

    public function getCspDirectives(): array
    {
        $instance = $this->instanceUrl();
        if ($instance === '') {
            return [];
        }

        return [
            'script-src' => [$instance],
            'connect-src' => [$instance],
        ];
    }

    public function renderWidget(WidgetContext $context): string
    {
        return $this->fetchWidget($context, [
            'ls_api_endpoint' => $this->apiEndpoint(),
        ]);
    }

    protected function callSiteverify(string $token, ?string $remoteIp): Response
    {
        return $this->client()->postJson($this->apiEndpoint() . 'siteverify', [
            'secret' => (string) $this->conf('secret_key'),
            'response' => $token,
        ]);
    }

    protected function interpret(array $data, WidgetContext $context): bool
    {
        return !empty($data['success']);
    }

    /** Per-site API base: <instance>/<site key>/ (the key is path-encoded). */
    private function apiEndpoint(): string
    {
        return $this->instanceUrl() . '/' . rawurlencode($this->getPublicSiteKey()) . '/';
    }

    private function instanceUrl(): string
    {
        return rtrim((string) $this->conf('instance_url'), '/');
    }
}
