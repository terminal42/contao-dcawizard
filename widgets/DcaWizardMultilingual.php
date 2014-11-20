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
     *
     * @return string
     */
    public function getForeignTableCondition()
    {
        $langColumn = $this->langColumn ?: 'language';

        $condition = parent::getForeignTableCondition();
        $condition .= " AND {$langColumn}=''";

        return $condition;
    }
}
