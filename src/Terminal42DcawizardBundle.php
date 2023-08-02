<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Terminal42DcawizardBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
