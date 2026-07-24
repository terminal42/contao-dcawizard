<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Exception\ResponseException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Terminal42\DcawizardBundle\Controller\CloseModalController;
use Terminal42\DcawizardBundle\UrlConfig;

#[AsHook('loadDataContainer')]
class CloseModalListener
{
    private UrlConfig|false|null $config = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(string $dcaTable): void
    {
        $table = $this->getConfigFromUrl()?->getForeignTable();

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
        if ($this->getConfigFromUrl()?->isOperation()) {
            unset($buttons['saveNduplicate'], $buttons['saveNcreate']);
        }

        return $buttons;
    }

    private function closeModal(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->request->has('saveNclose') && $this->getConfigFromUrl()?->isOperation()) {
            throw new ResponseException(new RedirectResponse($this->urlGenerator->generate(CloseModalController::class)));
        }
    }

    private function getConfigFromUrl(): UrlConfig|null
    {
        if (false === $this->config) {
            $request = $this->requestStack->getCurrentRequest();

            if (!$request || !$request->query->has('picker')) {
                return null;
            }

            $this->config = UrlConfig::urlDecode($request->query->getString('picker'));
        }

        return $this->config;
    }
}
