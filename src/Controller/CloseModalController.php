<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('%contao.backend.route_prefix%/close-modal')]
class CloseModalController
{
    public function __invoke(): Response
    {
        return new Response("<html><body><script>window.top.postMessage('closeModal', '*')</script></body></html>");
    }
}
