<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Response;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Cloudflare Turnstile. The mode (managed / non-interactive / invisible) is
 * chosen in the Cloudflare dashboard, not here.
 */
class TurnstileProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'turnstile';
    }

    public function getLabel(): string
    {
        return 'Cloudflare Turnstile';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'site_key', 'type' => 'text', 'required' => true, 'label' => $this->trans('Site key')],
            ['name' => 'secret_key', 'type' => 'password', 'required' => true, 'label' => $this->trans('Secret key')],
            [
                'name' => 'theme',
                'type' => 'select',
                'required' => false,
                'label' => $this->trans('Theme'),
                'default' => 'auto',
                'options' => ['auto' => $this->trans('Auto'), 'light' => $this->trans('Light'), 'dark' => $this->trans('Dark')],
            ],
            [
                'name' => 'size',
                'type' => 'select',
                'required' => false,
                'label' => $this->trans('Size'),
                'default' => 'normal',
                'options' => ['normal' => $this->trans('Normal'), 'flexible' => $this->trans('Flexible'), 'compact' => $this->trans('Compact')],
            ],
        ];
    }

    public function getTokenFieldName(): string
    {
        return 'cf-turnstile-response';
    }

    public function getFrontScripts(): array
    {
        // Rendered explicitly by ls_captcha.js so each widget is bound to its form.
        return [[
            'id' => 'ls-turnstile',
            'url' => $this->withOnload('https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'),
        ]];
    }

    public function getFrontExtra(): array
    {
        return ['api' => 'turnstile', 'waitOnload' => true];
    }

    public function getCspDirectives(): array
    {
        return [
            'script-src' => ['https://challenges.cloudflare.com'],
            'frame-src' => ['https://challenges.cloudflare.com'],
        ];
    }

    public function renderWidget(WidgetContext $context): string
    {
        return $this->fetchWidget($context, [
            'ls_site_key' => $this->getPublicSiteKey(),
            'ls_theme' => (string) ($this->conf('theme') ?: 'auto'),
            'ls_size' => (string) ($this->conf('size') ?: 'normal'),
        ]);
    }

    protected function callSiteverify(string $token, ?string $remoteIp): Response
    {
        $fields = [
            'secret' => (string) $this->conf('secret_key'),
            'response' => $token,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $fields['remoteip'] = $remoteIp;
        }

        return $this->client()->postForm('https://challenges.cloudflare.com/turnstile/v0/siteverify', $fields);
    }

    protected function interpret(array $data, WidgetContext $context): bool
    {
        if (empty($data['success'])) {
            return false;
        }

        // The widget is rendered with the zone as action: reject a token
        // obtained on another zone. Cloudflare test keys return no action.
        $action = (string) ($data['action'] ?? '');

        return $action === '' || $action === $context->getZone();
    }
}
