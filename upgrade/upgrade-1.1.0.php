<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.1.0: the login zone also renders its widget in the checkout, through
 * displayPersonalInformationTop (the checkout login form has no hook).
 *
 * @param ls_captcha $module
 */
function upgrade_module_1_1_0($module)
{
    return $module->registerHook('displayPersonalInformationTop');
}
