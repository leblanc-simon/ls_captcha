<?php

namespace LsCaptcha\Provider;

use LsCaptcha\WidgetContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A captcha provider. Adding a new captcha type means implementing this
 * interface (usually by extending AbstractProvider), adding a widget template
 * and registering the class in ProviderRegistry — nothing else.
 */
interface ProviderInterface
{
    /** Short machine code, e.g. 'recaptcha'. Used as config prefix and template suffix. */
    public function getCode(): string;

    /** Human label shown in the provider selector. */
    public function getLabel(): string;

    /**
     * Declarative description of the configuration fields. The admin form is
     * generated from this. Each entry:
     * ['name'=>, 'type'=>text|password|url|switch|select, 'label'=>, 'help'=>?,
     *  'required'=>bool, 'options'=>[value=>label], 'default'=>?].
     *
     * @return array<int,array<string,mixed>>
     */
    public function getConfigFields(): array;

    /** Name of the native POST field the provider populates with its token. */
    public function getTokenFieldName(): string;

    /** Whether the required configuration fields are all filled. */
    public function isConfigured(): bool;

    /**
     * Whether this provider processes personal data in a way that requires
     * prior visitor consent (e.g. cookies, profiling, third-country transfer
     * beyond what is strictly necessary for the security check). When true, the
     * front loads its script only after consent, whatever the global strategy.
     */
    public function requiresConsent(): bool;

    /**
     * Content Security Policy sources this provider needs, keyed by directive
     * (e.g. ['script-src' => ['https://...'], 'frame-src' => [...]]). Used to
     * document the CSP to add; the module does not enforce a site-wide CSP.
     *
     * @return array<string,string[]>
     */
    public function getCspDirectives(): array;

    /** Public site key (safe to expose to the front). */
    public function getPublicSiteKey(): string;

    /**
     * Front scripts to load: [['id'=>, 'url'=>, 'module'=>bool], ...].
     *
     * @return array<int,array<string,mixed>>
     */
    public function getFrontScripts(): array;

    /**
     * How the front should obtain the token: 'auto' (provider fills its native
     * field on solve) or 'execute' (JS must trigger the provider on submit).
     */
    public function getClientBehavior(): string;

    /**
     * Extra data made available to the front JS (variant, wasm url...). Never a secret.
     *
     * @return array<string,mixed>
     */
    public function getFrontExtra(): array;

    /** Rendered HTML of the widget for a given zone. */
    public function renderWidget(WidgetContext $context): string;

    /** Server-side verification of the token. Returns true when the challenge passed. */
    public function verify(string $token, ?string $remoteIp): bool;
}
