<?php

namespace LsCaptcha\Zone;

use LsCaptcha\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Customer registration (account creation), including guest/registration during checkout.
 *
 * - displayCustomerAccountForm   : render the widget inside the form.
 * - additionalCustomerFormFields : add the hidden token field. Also required to
 *   make the core call validateCustomerFormFields for this module.
 * - validateCustomerFormFields   : primary blocking point (works everywhere,
 *   including checkout).
 * - actionSubmitAccountBefore    : complementary block on the standalone page
 *   (shares the memoized verification, so the single-use token is not consumed twice).
 */
class RegistrationZone extends AbstractZone
{
    public function getCode(): string
    {
        return 'registration';
    }

    public function getLabel(): string
    {
        return $this->module->translate('Customer registration', [], 'Modules.Lscaptcha.Admin');
    }

    public function getHooks(): array
    {
        return [
            'displayCustomerAccountForm',
            'additionalCustomerFormFields',
            'validateCustomerFormFields',
            'actionSubmitAccountBefore',
        ];
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookDisplayCustomerAccountForm(array $params): string
    {
        if ($this->isIdentityPage()) {
            return '';
        }

        return $this->renderWidget('registration');
    }

    /**
     * @param array<string,mixed> $params
     * @return \FormField[]
     */
    public function hookAdditionalCustomerFormFields(array $params): array
    {
        if ($this->isIdentityPage()) {
            return [];
        }

        $field = new \FormField();
        $field->setName(Settings::TOKEN_FIELD);
        $field->setType('hidden');
        $field->setRequired(false);
        $field->setValue('');

        return [$field];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<int,\FormField>
     */
    public function hookValidateCustomerFormFields(array $params): array
    {
        if ($this->isIdentityPage() || $this->verifyRequest()) {
            return [];
        }

        $fields = isset($params['fields']) && is_array($params['fields']) ? $params['fields'] : [];
        foreach ($fields as $field) {
            if ($field instanceof \FormField && $field->getName() === Settings::TOKEN_FIELD) {
                $field->addError($this->errorMessage());
            }
        }

        // The token field is hidden, so the classic theme does not render its
        // per-field error. Also push a controller error for visibility (covers
        // the checkout path, where actionSubmitAccountBefore is not called).
        $controller = $this->controller();
        if ($controller !== null) {
            $controller->errors[] = $this->errorMessage();
        }

        return $fields;
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookActionSubmitAccountBefore(array $params): bool
    {
        if ($this->isIdentityPage() || $this->verifyRequest()) {
            return true;
        }

        $controller = $this->controller();
        if ($controller !== null) {
            $controller->errors[] = $this->errorMessage();
        }

        return false;
    }

    private function isIdentityPage(): bool
    {
        return $this->currentPhpSelf() === 'identity';
    }
}
