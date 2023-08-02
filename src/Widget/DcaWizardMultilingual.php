<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\Widget;

/**
 * Extends the dcaWizard for DC_Multilingual so translated entries don't get listed multiple times.
 *
 * @property string $langColumn
 */
class DcaWizardMultilingual extends DcaWizard
{
    public function getForeignTableCondition(): array
    {
        $langColumn = $this->langColumn ?: 'language';

        $condition = parent::getForeignTableCondition();
        $condition[0] .= " AND {$langColumn}=''";

        return $condition;
    }
}
