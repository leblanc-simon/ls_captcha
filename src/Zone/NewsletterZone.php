<?php

namespace LsCaptcha\Zone;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Newsletter block (ps_emailsubscription >= 2.6.0).
 *
 * - displayNewsletterRegistration  : render the widget inside the form.
 * - actionNewsletterRegistrationBefore : $params['hookError'] is passed by
 *   reference; setting it aborts the subscription (also covers the AJAX path).
 */
class NewsletterZone extends AbstractZone
{
    public function getCode(): string
    {
        return 'newsletter';
    }

    public function getLabel(): string
    {
        return $this->module->translate('Newsletter block', [], 'Modules.Lscaptcha.Admin');
    }

    public function getHooks(): array
    {
        return [
            'displayNewsletterRegistration',
            'actionNewsletterRegistrationBefore',
        ];
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookDisplayNewsletterRegistration(array $params): string
    {
        return $this->renderWidget('newsletter');
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookActionNewsletterRegistrationBefore(array $params): void
    {
        if ($this->verifyRequest()) {
            return;
        }

        // hookError is a reference member of the params array (preserved across
        // the by-value copy); setting it stops the subscription.
        $params['hookError'] = $this->errorMessage();
    }
}
