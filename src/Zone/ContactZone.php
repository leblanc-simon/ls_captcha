<?php

namespace LsCaptcha\Zone;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Contact page (contactform module).
 *
 * - displayGDPRConsent            : the contactform template calls this hook
 *   inside its <form>; we render the widget there (filtered on contactform's
 *   module id, since other GDPR-aware modules call the same hook).
 * - actionFrontControllerSetMedia : runs before contactform::sendMessage(); on
 *   an invalid token we pre-fill $controller->errors, which stops the mail and
 *   the success message (contactform guards both with a count($errors) check).
 */
class ContactZone extends AbstractZone
{
    public function getCode(): string
    {
        return 'contact';
    }

    public function getLabel(): string
    {
        return $this->module->translate('Contact page', [], 'Modules.Lscaptcha.Admin');
    }

    public function getHooks(): array
    {
        return [
            'displayGDPRConsent',
            'actionFrontControllerSetMedia',
        ];
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookDisplayGDPRConsent(array $params): string
    {
        $contactformId = (int) \Module::getModuleIdByName('contactform');
        if ($contactformId === 0 || (int) ($params['id_module'] ?? 0) !== $contactformId) {
            return '';
        }

        return $this->renderWidget('contact');
    }

    /**
     * @param array<string,mixed> $params
     */
    public function hookActionFrontControllerSetMedia(array $params): void
    {
        if ($this->currentPhpSelf() !== 'contact') {
            return;
        }
        if (!\Tools::isSubmit('submitMessage')) {
            return;
        }
        if ($this->verifyRequest()) {
            return;
        }

        $controller = $this->controller();
        if ($controller !== null) {
            $controller->errors[] = $this->errorMessage();
        }
    }
}
