<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Exception\ResponseException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Terminal42\DcawizardBundle\Controller\CloseModalController;

#[AsHook('loadDataContainer')]
class CloseModalListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(string $dcaTable): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->query->has('dcawizard')) {
            return;
        }

        [$table] = explode(':', (string) $request->query->get('dcawizard')) + [null];

        if ($table === $dcaTable) {
            $GLOBALS['TL_DCA'][$table]['edit']['buttons_callback'][] = $this->replaceCloseButton(...);
            $GLOBALS['TL_DCA'][$table]['config']['onsubmit_callback'][] = $this->closeModal(...);
        }
    }

    /**
     * @param array<string, string> $buttons
     *
     * @return array<string, string>
     */
    private function replaceCloseButton(array $buttons): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->query->has('dcawizard_operation')) {
            return $buttons;
        }

        unset($buttons['saveNduplicate'], $buttons['saveNcreate']);

        return $buttons;
    }

    private function closeModal(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->query->has('dcawizard_operation') && $request->request->has('saveNclose')) {
            throw new ResponseException(new RedirectResponse($this->urlGenerator->generate(CloseModalController::class)));
        }
    }
}
