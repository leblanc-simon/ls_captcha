<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Response;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Friendly Captcha v2.
 */
class FriendlyCaptchaProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'friendlycaptcha';
    }

    public function getLabel(): string
    {
        return 'Friendly Captcha';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'site_key', 'type' => 'text', 'required' => true, 'label' => $this->trans('Site key')],
            ['name' => 'api_key', 'type' => 'password', 'required' => true, 'label' => $this->trans('API key')],
            [
                'name' => 'endpoint',
                'type' => 'select',
                'required' => false,
                'label' => $this->trans('Endpoint'),
                'default' => 'eu',
                'options' => ['eu' => $this->trans('EU (Germany)'), 'global' => $this->trans('Global')],
            ],
        ];
    }

    public function getTokenFieldName(): string
    {
        return 'frc-captcha-response';
    }

    public function getFrontScripts(): array
    {
        return [[
            'id' => 'ls-frc',
            'url' => 'https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.2.0/site.min.js',
            'module' => true,
        ]];
    }

    public function getFrontExtra(): array
    {
        return ['endpoint' => $this->endpoint()];
    }

    public function getCspDirectives(): array
    {
        return [
            'script-src' => ['https://cdn.jsdelivr.net'],
            'connect-src' => ['https://' . $this->endpoint() . '.frcapi.com'],
        ];
    }

    public function renderWidget(WidgetContext $context): string
    {
        return $this->fetchWidget([
            'ls_zone' => $context->getZone(),
            'ls_site_key' => $this->getPublicSiteKey(),
            'ls_endpoint' => $this->endpoint(),
        ]);
    }

    protected function callSiteverify(string $token, ?string $remoteIp): Response
    {
        $url = 'https://' . $this->endpoint() . '.frcapi.com/api/v2/captcha/siteverify';

        return $this->client()->postJson(
            $url,
            ['response' => $token, 'sitekey' => $this->getPublicSiteKey()],
            ['X-API-Key: ' . (string) $this->conf('api_key')]
        );
    }

    protected function interpret(array $data): bool
    {
        return !empty($data['success']);
    }

    private function endpoint(): string
    {
        // EU by default (data residency in Germany); only 'global' opts out.
        return ((string) $this->conf('endpoint') === 'global') ? 'global' : 'eu';
    }
}
