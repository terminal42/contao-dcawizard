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
 * Register the classes
 */
ClassLoader::addClasses(array
(
    'DcaWizard' => 'system/modules/dcawizard/widgets/DcaWizard.php'
));

/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
    'be_widget_dcawizard' => 'system/modules/dcawizard/templates'
));