<?php

/**
 * dcawizard extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       https://github.com/terminal42/contao-dcawizard
 */

/**
 * Backend form fields
 */
$GLOBALS['BE_FFL']['dcaWizard']             = 'DcaWizard';
$GLOBALS['BE_FFL']['dcaWizardMultilingual'] = 'DcaWizardMultilingual';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePostActions'][] = array('DcaWizardHelper', 'handleAjaxActions');
$GLOBALS['TL_HOOKS']['loadDataContainer'][] = array('DcaWizardHelper', 'loadDataContainer');
