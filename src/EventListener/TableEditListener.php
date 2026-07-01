<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsHook('loadDataContainer', priority: 10)]
class TableEditListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
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
            if (!$request->query->has('act') || 'edit' !== $request->query->get('act')) {
                $ptable = $request->query->get('ptable');
                $field = $request->query->get('field');

                Controller::loadDataContainer($ptable);

                $config = $GLOBALS['TL_DCA'][$ptable]['fields'][$field];

                $useParentTable = $config['useParentTable'] ?? false;
                $useParentField = $config['useParentField'] ?? false;

                $filter = [];

                if ($useParentTable) {
                    $filter[] = ['ptable=?', $ptable];
                }

                if ($useParentField) {
                    $filter[] = ['pfield=?', $field];
                }

                $GLOBALS['TL_DCA'][$table]['list']['sorting']['filter'] = $filter;
            }

            $GLOBALS['TL_DCA'][$table]['config']['onsubmit_callback'][] = $this->onSubmitTable(...);
        }
    }

    private function onSubmitTable(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->query->has('dcawizard')) {
            [$cTable] = explode(':', (string) $request->query->get('dcawizard')) + [null];

            $table = $request->query->get('ptable');
            $field = $request->query->get('field');

            $config = $GLOBALS['TL_DCA'][$table]['fields'][$field];

            $useParentTable = $config['useParentTable'] ?? false;
            $useParentField = $config['useParentField'] ?? false;

            $vars = [];
            if ($useParentTable) {
                $vars['ptable'] = $table;
            }

            if ($useParentField) {
                $vars['pfield'] = $field;
            }

            if (count($vars)) {
                $this->connection->update($cTable, $vars, ['id' => $dc->id]);
            }
        }
    }
}
