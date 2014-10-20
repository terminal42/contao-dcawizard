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
 * Class DcaWizardMultilingual
 *
 * Extends the dcaWizard for DC_Multilingual so translated entries don't get listed multiple times
 *
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DcaWizardMultilingual extends DcaWizard
{
    /**
     * Return SQL WHERE condition for foreign table
     * @return string
     */
    public function getForeignTableCondition()
    {
        $condition = parent::getForeignTableCondition();
        $condition .= " AND language=''";

        return $condition;
    }
}
