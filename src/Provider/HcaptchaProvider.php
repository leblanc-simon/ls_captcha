<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Response;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * hCaptcha (checkbox / invisible).
 */
class HcaptchaProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'hcaptcha';
    }

    public function getLabel(): string
    {
        return 'hCaptcha';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'site_key', 'type' => 'text', 'required' => true, 'label' => $this->trans('Site key')],
            ['name' => 'secret_key', 'type' => 'password', 'required' => true, 'label' => $this->trans('Secret key')],
            [
                'name' => 'mode',
                'type' => 'select',
                'required' => false,
                'label' => $this->trans('Mode'),
                'default' => 'checkbox',
                'options' => ['checkbox' => $this->trans('Checkbox'), 'invisible' => $this->trans('Invisible')],
            ],
            [
                'name' => 'theme',
                'type' => 'select',
                'required' => false,
                'label' => $this->trans('Theme'),
                'default' => 'light',
                'options' => ['light' => $this->trans('Light'), 'dark' => $this->trans('Dark')],
            ],
            [
                'name' => 'size',
                'type' => 'select',
                'required' => false,
                'label' => $this->trans('Size'),
                'default' => 'normal',
                'options' => ['normal' => $this->trans('Normal'), 'compact' => $this->trans('Compact')],
            ],
        ];
    }

    public function getTokenFieldName(): string
    {
        return 'h-captcha-response';
    }

    public function getClientBehavior(): string
    {
        return ((string) $this->conf('mode') === 'invisible') ? 'execute' : 'auto';
    }

    public function getFrontScripts(): array
    {
        return [['id' => 'ls-hcaptcha', 'url' => 'https://js.hcaptcha.com/1/api.js']];
    }

    public function requiresConsent(): bool
    {
        // hCaptcha sets cookies and processes data on US infrastructure.
        return true;
    }

    public function getCspDirectives(): array
    {
        return [
            'script-src' => ['https://js.hcaptcha.com', 'https://*.hcaptcha.com'],
            'frame-src' => ['https://*.hcaptcha.com', 'https://hcaptcha.com'],
            'style-src' => ['https://*.hcaptcha.com'],
            'connect-src' => ['https://*.hcaptcha.com'],
        ];
    }

    public function renderWidget(WidgetContext $context): string
    {
        $mode = (string) $this->conf('mode');

        return $this->fetchWidget([
            'ls_zone' => $context->getZone(),
            'ls_site_key' => $this->getPublicSiteKey(),
            'ls_theme' => (string) ($this->conf('theme') ?: 'light'),
            'ls_size' => $mode === 'invisible' ? 'invisible' : (string) ($this->conf('size') ?: 'normal'),
        ]);
    }

    protected function callSiteverify(string $token, ?string $remoteIp): Response
    {
        $fields = [
            'secret' => (string) $this->conf('secret_key'),
            'response' => $token,
            'sitekey' => $this->getPublicSiteKey(),
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $fields['remoteip'] = $remoteIp;
        }

        return $this->client()->postForm('https://api.hcaptcha.com/siteverify', $fields);
    }

    protected function interpret(array $data): bool
    {
        return !empty($data['success']);
    }
}
