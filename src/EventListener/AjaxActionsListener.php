<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Exception\BadRequestException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\DataContainer;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Terminal42\DcawizardBundle\Widget\DcaWizard;

#[AsHook('executePostActions')]
class AjaxActionsListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $action, DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || 'reloadDcaWizard' !== $action) {
            return;
        }

        $intId = $request->query->getInt('id');
        $strField = $strFieldName = $request->request->get('name');

        // Handle the keys in "edit multiple" mode
        if ('editAll' === $request->query->get('act')) {
            $intId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
            $strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
        }

        // Validate the request data
        if ('File' === $GLOBALS['TL_DCA'][$dc->table]['config']['dataContainer']) {
            // The field does not exist
            if (!\array_key_exists($strField, $GLOBALS['TL_CONFIG'])) {
                throw new BadRequestException();
            }
        } elseif ($this->connection->createSchemaManager()->tablesExist($dc->table)) {
            // The field does not exist
            if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField])) {
                throw new BadRequestException();
            }

            $id = $this->connection->fetchOne('SELECT id FROM '.$dc->table.' WHERE id=?', [$intId]);

            // The record does not exist
            if (false === $id) {
                throw new BadRequestException();
            }

            $dc->intId = (int) $id;
        }

        /** @var Widget $strClass */
        $strClass = $GLOBALS['BE_FFL']['dcaWizard'];
        $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField];

        // Support classes extending DcaWizard
        if ($ajaxClass = $request->request->get('class')) {
            $ajaxClass = base64_decode($ajaxClass, true);

            if (\in_array($ajaxClass, $GLOBALS['BE_FFL'], true)) {
                try {
                    if ((new \ReflectionClass($ajaxClass))->isSubclassOf(DcaWizard::class)) {
                        $strClass = $ajaxClass;
                    }
                } catch (\Exception) {
                    // silent fallback to default class
                }
            }
        }

        $objWidget = new $strClass(
            $strClass::getAttributesFromDca($arrData, $strFieldName, null, $strField, $dc->table, $dc)
        );

        throw new ResponseException(new Response($objWidget->generate()));
    }
}
