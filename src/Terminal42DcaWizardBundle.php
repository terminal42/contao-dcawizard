<?php

declare(strict_types=1);

namespace Terminal42\DcaWizardBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Terminal42DcaWizardBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
