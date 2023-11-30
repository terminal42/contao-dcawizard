<?php

namespace Terminal42\DcaWizardBundle\Widget;

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
     * @inheritDoc
     */
    public function getForeignTableCondition()
    {
        $langColumn = $this->langColumn ?: 'language';

        $condition = parent::getForeignTableCondition();
        $condition[0] .= " AND {$langColumn}=''";

        return $condition;
    }
}
