<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2012 Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://www.isotopeecommerce.com
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Backend form fields
 */
$GLOBALS['BE_FFL']['dcaWizard']             = 'DcaWizard';
$GLOBALS['BE_FFL']['dcaWizardMultilingual'] = 'DcaWizardMultilingual';


/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePostActions'][] = array('DcaWizard', 'handleAjaxActions');

