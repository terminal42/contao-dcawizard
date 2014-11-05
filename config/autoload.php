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
 * Register the classes
 */
ClassLoader::addClasses(array
(
    'DcaWizard'             => 'system/modules/dcawizard/widgets/DcaWizard.php',
    'DcaWizardMultilingual' => 'system/modules/dcawizard/widgets/DcaWizardMultilingual.php',
    'DcaWizardHelper'       => 'system/modules/dcawizard/classes/DcaWizardHelper.php',
));

/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
    'be_widget_dcawizard' => 'system/modules/dcawizard/templates'
));