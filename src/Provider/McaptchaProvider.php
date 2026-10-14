<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Response;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * mCaptcha (https://mcaptcha.org) — self-hosted, proof-of-work.
 */
class McaptchaProvider extends AbstractProvider
{
    /** @var bool */
    private $rendered = false;

    public function getCode(): string
    {
        return 'mcaptcha';
    }

    public function getLabel(): string
    {
        return $this->trans('%provider% (self-hosted)', ['%provider%' => 'mCaptcha']);
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'instance_url', 'type' => 'url', 'required' => true, 'label' => $this->trans('Instance URL')],
            ['name' => 'site_key', 'type' => 'text', 'required' => true, 'label' => $this->trans('Site key')],
            ['name' => 'secret', 'type' => 'password', 'required' => true, 'label' => $this->trans('Account secret')],
        ];
    }

    public function getTokenFieldName(): string
    {
        return 'mcaptcha__token';
    }

    public function getFrontScripts(): array
    {
        // Glue code served from unpkg (pinned). Serve it locally for stricter CSP.
        return [['id' => 'ls-mcaptcha', 'url' => 'https://unpkg.com/@mcaptcha/vanilla-glue@0.1.0-rc2/dist/index.js']];
    }

    public function getCspDirectives(): array
    {
        $instance = $this->instanceUrl();
        $directives = ['script-src' => ['https://unpkg.com']];
        if ($instance !== '') {
            $directives['frame-src'] = [$instance];
            $directives['connect-src'] = [$instance];
        }

        return $directives;
    }

    public function getFrontExtra(): array
    {
        // The glue script drives a single widget (fixed element ids): its token
        // is mirrored into whichever protected form is submitted.
        return ['singleWidget' => true];
    }

    public function renderWidget(WidgetContext $context): string
    {
        // Render once per page: the glue script only supports one widget.
        if ($this->rendered) {
            return '';
        }
        $this->rendered = true;

        $instance = $this->instanceUrl();
        $site = $this->getPublicSiteKey();

        return $this->fetchWidget($context, [
            'ls_instance' => $instance,
            'ls_site_key' => $site,
            'ls_widget_url' => $instance === '' ? '' : $instance . '/widget?sitekey=' . rawurlencode($site),
        ]);
    }

    protected function callSiteverify(string $token, ?string $remoteIp): Response
    {
        $url = $this->instanceUrl() . '/api/v1/pow/siteverify';

        return $this->client()->postJson($url, [
            'token' => $token,
            'key' => $this->getPublicSiteKey(),
            'secret' => (string) $this->conf('secret'),
        ]);
    }

    protected function interpret(array $data, WidgetContext $context): bool
    {
        return !empty($data['valid']);
    }

    private function instanceUrl(): string
    {
        return rtrim((string) $this->conf('instance_url'), '/');
    }
}
