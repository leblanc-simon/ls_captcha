<?php

namespace LsCaptcha\Zone;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Customer login.
 *
 * - displayCustomerLoginFormAfter : render the widget on the authentication
 *   page (OUTSIDE the form — ls_captcha.js writes the token into the form).
 * - displayPersonalInformationTop : render the widget in the checkout, whose
 *   login form has no hook of its own; ls_captcha.js moves it into that form.
 * - actionAuthenticationBefore    : fires inside CustomerLoginForm::submit()
 *   with no params and an ignored return value, so the only way to block is to
 *   push an error and redirect (which exits) before credentials are checked.
 *   Also covers the checkout login form, which goes through the same submit().
 */
class LoginZone extends AbstractZone
{
    public function getCode(): string
    {
        return 'login';
    }

    public function getLabel(): string
    {
        return $this->module->translate('Customer login', [], 'Modules.Lscaptcha.Admin');
    }

    public function getHooks(): array
    {
        return [
            'displayCustomerLoginFormAfter',
            'displayPersonalInformationTop',
            'actionAuthenticationBefore',
        ];
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookDisplayCustomerLoginFormAfter(array $params): string
    {
        return $this->renderWidget('login');
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookDisplayPersonalInformationTop(array $params): string
    {
        $customer = \Context::getContext()->customer;
        if ($this->currentPhpSelf() !== 'order' || ($customer !== null && $customer->isLogged())) {
            return '';
        }

        return $this->renderWidget('login', true);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookActionAuthenticationBefore(array $params): void
    {
        if ($this->verifyRequest()) {
            return;
        }

        $controller = $this->controller();
        if ($controller === null) {
            return;
        }

        $context = \Context::getContext();
        $phpSelf = $this->currentPhpSelf();
        $page = ($phpSelf === 'order') ? 'order' : 'authentication';

        $controller->errors[] = $this->errorMessage();
        $controller->redirectWithNotifications($context->link->getPageLink($page, true));
    }
}
