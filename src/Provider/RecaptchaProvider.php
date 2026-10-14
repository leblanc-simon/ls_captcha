<?php

namespace LsCaptcha\Provider;

use LsCaptcha\Http\Response;
use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Google reCAPTCHA v2 (checkbox / invisible) and v3.
 */
class RecaptchaProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'recaptcha';
    }

    public function getLabel(): string
    {
        return 'Google reCAPTCHA';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'site_key', 'type' => 'text', 'required' => true, 'label' => $this->trans('Site key')],
            ['name' => 'secret_key', 'type' => 'password', 'required' => true, 'label' => $this->trans('Secret key')],
            [
                'name' => 'variant',
                'type' => 'select',
                'required' => true,
                'label' => $this->trans('Version'),
                'default' => 'v2_checkbox',
                'options' => [
                    'v2_checkbox' => $this->trans('v2 - Checkbox'),
                    'v2_invisible' => $this->trans('v2 - Invisible'),
                    'v3' => $this->trans('v3 - Score'),
                ],
            ],
            [
                'name' => 'score_threshold',
                'type' => 'decimal',
                'min' => 0,
                'max' => 1,
                'required' => false,
                'label' => $this->trans('v3 score threshold'),
                'help' => $this->trans('Between 0.0 and 1.0. Submissions below this score are rejected (default 0.5).'),
                'default' => '0.5',
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
                'name' => 'use_recaptcha_net',
                'type' => 'switch',
                'required' => false,
                'label' => $this->trans('Use recaptcha.net'),
                'help' => $this->trans('Load scripts and verify from recaptcha.net instead of google.com (for regions blocking google.com).'),
                'default' => 0,
            ],
        ];
    }

    public function getTokenFieldName(): string
    {
        return 'g-recaptcha-response';
    }

    public function getClientBehavior(): string
    {
        $variant = (string) $this->conf('variant');

        return ($variant === 'v3' || $variant === 'v2_invisible') ? 'execute' : 'auto';
    }

    public function getFrontScripts(): array
    {
        // v3 renders itself from the site key; v2 widgets are rendered
        // explicitly by ls_captcha.js so each one is bound to its own form.
        $render = $this->isV3() ? rawurlencode($this->getPublicSiteKey()) : 'explicit';

        return [[
            'id' => 'ls-recaptcha',
            'url' => $this->withOnload('https://' . $this->host() . '/recaptcha/api.js?render=' . $render),
        ]];
    }

    public function getFrontExtra(): array
    {
        return [
            'variant' => (string) $this->conf('variant'),
            'api' => 'grecaptcha',
            'waitOnload' => true,
        ];
    }

    public function requiresConsent(): bool
    {
        // reCAPTCHA sets cookies and reuses data for Google's own purposes.
        return true;
    }

    public function getCspDirectives(): array
    {
        $host = 'https://' . $this->host();

        return [
            'script-src' => [$host, 'https://www.gstatic.com'],
            'frame-src' => [$host],
        ];
    }

    public function renderWidget(WidgetContext $context): string
    {
        return $this->fetchWidget($context, [
            'ls_site_key' => $this->getPublicSiteKey(),
            'ls_variant' => (string) $this->conf('variant'),
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

        return $this->client()->postForm('https://' . $this->host() . '/recaptcha/api/siteverify', $fields);
    }

    protected function interpret(array $data, WidgetContext $context): bool
    {
        if (empty($data['success'])) {
            return false;
        }

        if ($this->isV3()) {
            // A v3 token is bound to the action it was requested for: one
            // obtained on another zone must not be replayed here.
            if ((string) ($data['action'] ?? '') !== $context->getZone()) {
                return false;
            }

            $score = isset($data['score']) ? (float) $data['score'] : 0.0;
            if ($score < $this->scoreThreshold()) {
                return false;
            }
        }

        return true;
    }

    private function isV3(): bool
    {
        return (string) $this->conf('variant') === 'v3';
    }

    /** Stored threshold, tolerant of a decimal comma and clamped to [0, 1]. */
    private function scoreThreshold(): float
    {
        $raw = str_replace(',', '.', trim((string) $this->conf('score_threshold')));
        if (!is_numeric($raw)) {
            return 0.5;
        }

        return max(0.0, min(1.0, (float) $raw));
    }

    private function host(): string
    {
        return ((int) $this->conf('use_recaptcha_net') === 1) ? 'www.recaptcha.net' : 'www.google.com';
    }
}
