<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\EventListener;

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

#[AsHook('loadDataContainer')]
class FixRefererListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlParser $urlParser,
    ) {
    }

    public function __invoke(string $dcaTable): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->query->has('dcawizard')) {
            return;
        }

        [$table] = explode(':', $request->query->get('dcawizard')) + [null];

        // Register a delete callback
        if ($table === $dcaTable) {
            $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = $this->onLoadCallback(...);
            $GLOBALS['TL_DCA'][$table]['config']['ondelete_callback'][] = $this->onDeleteCallback(...);
        }
    }

    /**
     * Provide a fix to the popup referer (see #15).
     */
    private function onLoadCallback(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->query->has('dcawizard') || 'edit' !== $request->query->get('act')) {
            return;
        }

        $session = $this->requestStack->getSession();
        $referer = $session->get('popupReferer');

        if (!\is_array($referer)) {
            $referer = [[]];
        }

        [, $id] = explode(':', $request->query->get('dcawizard')) + [null, null];

        // Use the current URL without (act and id parameters) as referer
        $url = $this->urlParser->removeQueryString(['act', 'id'], $request->getRequestUri());
        $url = $this->urlParser->addQueryString('id='.$id, $url);

        // Replace the last referer value with the correct link
        $referer[array_key_last($referer)]['current'] = $url;

        $session->set('popupReferer', $referer);
    }

    /**
     * Fix the popup referer when deleting the records directly
     * inside the edit form of the source table.
     */
    private function onDeleteCallback(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->query->has('dcawizard_operation')) {
            return;
        }

        $session = $this->requestStack->getSession();
        $referer = $session->get('popupReferer');

        /** @var Session $sessionBag */
        $sessionBag = $session->getBag('contao_backend');
        $dcaWizardReferer = $sessionBag->get('dcaWizardReferer');

        if (!\is_array($referer) || !$dcaWizardReferer) {
            return;
        }

        // Replace the last referer value with the correct link
        $referer[array_key_last($referer)]['current'] = $dcaWizardReferer;

        $session->set('popupReferer', $referer);
    }
}
