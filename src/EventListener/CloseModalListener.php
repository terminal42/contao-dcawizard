<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Exception\ResponseException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsHook('loadDataContainer')]
class CloseModalListener
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(string $dcaTable): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->query->has('dcawizard')) {
            return;
        }

        [$table] = explode(':', $request->query->get('dcawizard')) + [null];

        if ($table === $dcaTable) {
            $GLOBALS['TL_DCA'][$table]['edit']['buttons_callback'][] = $this->replaceCloseButton(...);
            $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = $this->closeModal(...);
        }
    }

    private function replaceCloseButton(array $buttons): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->query->has('dcawizard_operation')) {
            return $buttons;
        }

        if (isset($buttons['saveNclose'])) {
            $buttons['saveNclose'] = '<button type="submit" name="saveNback" id="saveNback" class="tl_submit" accesskey="c">'.$GLOBALS['TL_LANG']['MSC']['saveNclose'].'</button>';
        }

        return $buttons;
    }

    private function closeModal(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->query->has('dcawizard_operation') && !$request->query->has('act')) {
            throw new ResponseException(new Response("<script>window.top.postMessage('closeModal', '*')</script>"));
        }
    }
}
