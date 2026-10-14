<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Response;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * FCaptcha (https://github.com/WebDecoy/FCaptcha) — self-hosted bot protection.
 */
class FcaptchaProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'fcaptcha';
    }

    public function getLabel(): string
    {
        return $this->trans('%provider% (self-hosted)', ['%provider%' => 'FCaptcha']);
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'instance_url', 'type' => 'url', 'required' => true, 'label' => $this->trans('Instance URL')],
            [
                'name' => 'site_key',
                'type' => 'text',
                'required' => false,
                'label' => $this->trans('Site key'),
                'help' => $this->trans('Optional. Only needed when the FCaptcha instance restricts site keys (FCAPTCHA_SITE_KEYS) or serves several sites.'),
            ],
            ['name' => 'secret_key', 'type' => 'password', 'required' => true, 'label' => $this->trans('Secret key')],
            [
                'name' => 'theme',
                'type' => 'select',
                'required' => false,
                'label' => $this->trans('Theme'),
                'default' => 'light',
                'options' => ['light' => $this->trans('Light'), 'dark' => $this->trans('Dark')],
            ],
        ];
    }

    public function getTokenFieldName(): string
    {
        // FCaptcha emits its token in an event. ls_captcha.js writes that token
        // directly to the module's normalized field.
        return 'ls_captcha_token';
    }

    public function getFrontScripts(): array
    {
        $instance = $this->instanceUrl();

        return $instance === '' ? [] : [[
            'id' => 'ls-fcaptcha',
            'url' => $instance . '/fcaptcha.js',
        ]];
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
            'ls_instance' => $this->instanceUrl(),
            'ls_site_key' => $this->getPublicSiteKey(),
            'ls_theme' => (string) ($this->conf('theme') ?: 'light'),
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

        return $this->client()->postForm(
            $this->instanceUrl() . '/turnstile/v0/siteverify',
            $fields
        );
    }

    protected function interpret(array $data, WidgetContext $context): bool
    {
        return !empty($data['success']);
    }

    private function instanceUrl(): string
    {
        return rtrim((string) $this->conf('instance_url'), '/');
    }
}
