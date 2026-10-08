<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\DcawizardBundle\UrlConfig;
use Terminal42\DcawizardBundle\Widget\DcaWizard;

/**
 * Limits the records of the foreign table to the parent table and field.
 *
 * Without this, two dcaWizard fields on the same table would show and edit the
 * same records, because the foreign table is only related by the parent id.
 *
 * The list view is filtered and newly created records are stamped, so a record
 * created through the wizard stays visible in it.
 */
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
        $config = $this->getConfigFromUrl();

        if (null === $config || $dcaTable !== $config->getForeignTable()) {
            return;
        }

        $values = $this->getParentValues($config);

        if ([] === $values) {
            return;
        }

        // Filter the list view, but leave the edit mask of a single record alone.
        if ('edit' !== $this->requestStack->getCurrentRequest()?->query->get('act')) {
            $GLOBALS['TL_DCA'][$dcaTable]['list']['sorting']['filter'] = array_map(
                static fn (string $column, string $value): array => [$column.'=?', $value],
                array_keys($values),
                $values,
            );
        }

        // Stamp new records, otherwise they would not match the filter above.
        $GLOBALS['TL_DCA'][$dcaTable]['config']['onsubmit_callback'][] = function (DataContainer $dc) use ($dcaTable, $values): void {
            if ($dc->id) {
                $this->connection->update($dcaTable, $values, ['id' => $dc->id]);
            }
        };
    }

    /**
     * Columns of the foreign table that tie a record to the parent field.
     *
     * @return array<string, string>
     */
    private function getParentValues(UrlConfig $config): array
    {
        $parentTable = $config->getParentTable();
        $field = $config->getField();

        if (null === $parentTable || null === $field) {
            return [];
        }

        Controller::loadDataContainer($parentTable);

        $fieldConfig = $GLOBALS['TL_DCA'][$parentTable]['fields'][$field] ?? [];

        return DcaWizard::resolveParentColumns(
            (array) ($fieldConfig['parentColumns'] ?? []),
            $parentTable,
            $field,
        );
    }

    private function getConfigFromUrl(): UrlConfig|null
    {
        $picker = $this->requestStack->getCurrentRequest()?->query->getString('picker');

        return $picker ? UrlConfig::urlDecode($picker) : null;
    }
}
