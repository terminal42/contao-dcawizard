<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\Widget;

use Codefog\HasteBundle\Formatter;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\DataContainerGlobalOperationsBuilder;
use Contao\CoreBundle\DataContainer\DataContainerOperationsBuilder;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\String\HtmlAttributes;
use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Terminal42\DcawizardBundle\UrlConfig;

/**
 * Provides the back end widget "dcaWizard".
 *
 * @property string        $foreignTable
 * @property string        $foreignField
 * @property callable|null $foreignTable_callback
 * @property array         $parentColumns
 * @property array         $headerFields
 * @property array         $fields
 * @property string        $editButtonLabel
 * @property string        $emptyLabel
 * @property string|null   $whereCondition
 * @property string|null   $orderField
 * @property bool          $showOperations
 * @property bool          $hideButton
 * @property array|null    $operations
 * @property array|null    $global_operations
 * @property array|null    $params
 */
class DcaWizard extends Widget
{
    /**
     * Placeholder for the table the dcaWizard field is defined on.
     *
     * @see self::resolveParentColumns()
     */
    public const string PARENT_TABLE = '##dcawizard.parentTable##';

    /**
     * Placeholder for the name of the dcaWizard field.
     *
     * @see self::resolveParentColumns()
     */
    public const string PARENT_FIELD = '##dcawizard.parentField##';

    protected $strTemplate = 'be_widget';

    /**
     * @param array<string, mixed> $arrAttributes
     */
    public function __construct($arrAttributes = null)
    {
        parent::__construct($arrAttributes);

        $foreignTableCallback = $this->foreignTable_callback;

        // Load the table from callback
        if (!empty($foreignTableCallback) && \is_array($foreignTableCallback)) {
            $this->foreignTable = System::importStatic($foreignTableCallback[0])->{$foreignTableCallback[1]}($this);
        } elseif (\is_callable($foreignTableCallback)) {
            $this->foreignTable = $foreignTableCallback($this);
        }

        if (!empty($this->foreignTable)) {
            Controller::loadDataContainer($this->foreignTable);
            System::loadLanguageFile($this->foreignTable);
        }
    }

    #[\Override]
    public function __set($strKey, $varValue): void
    {
        switch ($strKey) {
            case 'value':
                $this->varValue = $varValue;
                break;

            case 'mandatory':
                $this->arrConfiguration[$strKey] = (bool) $varValue;
                break;

            case 'foreignTable':
                $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'] = $varValue;
                break;

            case 'foreignField':
                $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignField'] = $varValue;
                break;

            case 'parentColumns':
                $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['parentColumns'] = $varValue;
                break;

            default:
                parent::__set($strKey, $varValue);
                break;
        }
    }

    #[\Override]
    public function __isset($strKey)
    {
        return match ($strKey) {
            'currentRecord' => Input::get('id') || $this->objDca->id,
            'params' => isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['params']),
            'foreignTable' => isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable']),
            'foreignField' => true,
            'foreignTable_callback' => isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable_callback']),
            'parentColumns' => isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['parentColumns']),
            default => parent::__get($strKey),
        };
    }

    #[\Override]
    public function __get($strKey)
    {
        return match ($strKey) {
            'currentRecord' => Input::get('id') ?: $this->objDca->id,
            'params' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['params'] ?? null,
            'foreignTable' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'] ?? null,
            'foreignField' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignField'] ?? 'pid',
            'foreignTable_callback' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable_callback'] ?? null,
            'parentColumns' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['parentColumns'] ?? [],
            default => parent::__get($strKey),
        };
    }

    #[\Override]
    public function validate(): void
    {
        if ($this->mandatory) {
            /** @var Connection $connection */
            $connection = System::getContainer()->get('database_connection');
            [$where, $values] = $this->getForeignTableCondition();

            $id = $connection->fetchOne("SELECT id FROM {$this->foreignTable} WHERE ".$where, $values);

            if (false === $id) {
                $this->addError('' === $this->strLabel ? $GLOBALS['TL_LANG']['ERR']['mdtryNoLabel'] : \sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
            }
        }
    }

    public function generate(): string
    {
        $templateData = [
            'id' => $this->strId,
            'css_class' => $this->strClass,
            'global_operations' => $this->getGlobalOperations(),
            'header_fields' => $this->getHeaderFields(),
            'has_record_operations' => [] !== $this->getAvailableRecordOperations(),
            'records' => $this->getRecords(),
            'empty_label' => $this->emptyLabel,
            'edit_button' => null,
            'modal' => [
                'id' => $this->strId,
                'class' => base64_encode(static::class),
                'title' => $this->strLabel,
            ],
        ];

        if (!$this->hideButton) {
            $templateData['edit_button'] = [
                'url' => System::getContainer()->get('router')->generate('contao_backend', $this->getButtonParams(['nb' => 1]), UrlGeneratorInterface::ABSOLUTE_URL),
                'label' => $this->editButtonLabel ?: $this->strLabel,
            ];
        }

        return System::getContainer()->get('twig')->render(\sprintf('@Contao/%s.html.twig', $this->customTpl ?: 'backend/widget/dcawizard'), $templateData);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getRecords(): array
    {
        if (($rawRecords = $this->fetchRecords()) === []) {
            return [];
        }

        $records = [];
        $dataContainer = null;

        // Prepare the data container for formatter
        if ($this->objDca instanceof DataContainer) {
            $dataContainer = $this->objDca;
        }

        /** @var Formatter $formatter */
        $formatter = System::getContainer()->get(Formatter::class);

        foreach ($rawRecords as $rawRecord) {
            // Generate the record fields defined in the widget settings
            if (!empty($this->fields) && \is_array($this->fields)) {
                $fields = array_map(fn (string $field) => $formatter->dcaValue($this->foreignTable, $field, $rawRecord[$field] ?? null, $dataContainer), $this->fields);
            } else {
                // Generate the record default label
                if (!$this->objDca instanceof DataContainer) {
                    throw new \RuntimeException('DcaWizard does not have a DataContainer object');
                }

                $fields = [$this->objDca->generateRecordLabel($rawRecord, $this->foreignTable)];
            }

            $records[] = [
                'draft' => 0 === (int) ($rawRecord['tstamp'] ?? 0),
                'fields' => $fields,
                'operations' => $this->getRecordOperations($rawRecord),
                'raw' => $rawRecord,
            ];
        }

        return $records;
    }

    public function getRecordOperations(array $record): DataContainerOperationsBuilder
    {
        $builder = System::getContainer()->get('contao.data_container.operations_builder')->initialize($this->foreignTable, $record['id']);
        $generateOperation = new \ReflectionMethod($builder, 'generateOperation');

        foreach ($this->getAvailableRecordOperations() as $name) {
            if ('new' === $name) {
                if (
                    DataContainer::MODE_PARENT !== ($GLOBALS['TL_DCA'][$this->foreignTable]['list']['sorting']['mode'] ?? null)
                    || 'sorting' !== ($GLOBALS['TL_DCA'][$this->foreignTable]['list']['sorting']['fields'][0] ?? null)
                    || !System::getContainer()->get('security.helper')->isGranted(
                        ContaoCorePermissions::DC_PREFIX.$this->foreignTable,
                        $this->getCreateAction($record, $record['sorting'] + 1),
                    )
                ) {
                    continue;
                }

                $builder->addSeparator();

                $operation = [
                    'label' => $GLOBALS['TL_LANG'][$this->foreignTable]['pastenewafter'] ?? $GLOBALS['TL_LANG']['DCA']['pastenewafter'],
                    'attributes' => $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']['new']['attributes'] ?? null,
                    'icon' => $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']['new']['icon'] ?? 'new.svg',
                    'href' => 'act=create&amp;mode=1&amp;pid='.$record['id'],
                    'method' => $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']['new']['method'] ?? 'POST',
                    'primary' => $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']['new']['primary'] ?? false,
                ];
            } elseif (!isset($GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations'][$name])) {
                continue;
            } else {
                $operation = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations'][$name];
            }

            if ('-' === $operation) {
                $builder->addSeparator();
                continue;
            }

            $operation = \is_array($operation) ? $operation : [$operation];

            parse_str(StringUtil::decodeEntities($operation['href'] ?? ''), $params);
            $params += ['table' => $this->foreignTable, 'id' => $record['id']];

            $operation['href'] = http_build_query($this->getButtonParams($params, ['operation' => true]));

            $config = $generateOperation->invoke($builder, $name, $operation, $record, $this->objDca);

            if (null === $config) {
                continue;
            }

            /** @var HtmlAttributes $attributes */
            $attributes = $config['attributes'];

            if ('delete' === $name) {
                $attributes->set('data-terminal42--dcawizard-confirm-param', \sprintf($GLOBALS['TL_LANG']['MSC']['deleteConfirm'], $record['id']));
                $attributes->set('data-action', 'click->terminal42--dcawizard#request:prevent');
                $attributes->unset('onclick');
            } elseif (empty($attributes['onclick'])) {
                $attributes->set('data-action', 'click->terminal42--dcawizard#open:prevent');
            }

            $builder->append($config);
        }

        return $builder;
    }

    /**
     * @return array<string>
     */
    public function getAvailableRecordOperations(): array
    {
        if (!$this->showOperations) {
            return [];
        }

        if (\is_array($this->operations) && [] !== $this->operations) {
            return $this->operations;
        }

        return array_keys((array) $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']);
    }

    public function getGlobalOperations(): DataContainerGlobalOperationsBuilder|null
    {
        if (empty($this->global_operations)) {
            return null;
        }

        $builder = System::getContainer()->get('contao.data_container.global_operations_builder')->initialize($this->foreignTable);
        $generateOperation = new \ReflectionMethod($builder, 'generateOperation');

        foreach ($this->global_operations as $name) {
            $operation = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['global_operations'][$name] ?? null;

            // Cannot edit all here
            if ('all' === $name) {
                continue;
            }

            // Special handling for the "new" operation
            if (null === $operation && 'new' === $name) {
                if (
                    !System::getContainer()->get('security.helper')->isGranted(
                        ContaoCorePermissions::DC_PREFIX.$this->foreignTable,
                        $this->getCreateAction(['pid' => $this->currentRecord]),
                    )
                ) {
                    continue;
                }

                $operation = [
                    'label' => $GLOBALS['TL_LANG'][$this->foreignTable]['new'] ?? $GLOBALS['TL_LANG']['DCA']['new'],
                    'href' => 'act=create&amp;mode=2&amp;pid='.$this->currentRecord,
                    'icon' => $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']['new']['icon'] ?? 'new.svg',
                    'attributes' => (new HtmlAttributes($GLOBALS['TL_DCA'][$this->foreignTable]['list']['global_operations']['new']['attributes'] ?? null))->addClass($GLOBALS['TL_DCA'][$this->foreignTable]['list']['global_operations']['new']['class'] ?? 'header_new'),
                    'method' => 'POST',
                    'primary' => true,
                ];
            }

            if (null === $operation) {
                continue;
            }

            parse_str(StringUtil::decodeEntities($operation['href'] ?? ''), $params);
            $params += ['table' => $this->foreignTable];

            $operation['href'] = http_build_query($this->getButtonParams($params));

            $config = $generateOperation->invoke($builder, $name, $operation, $this->objDca);

            if (null === $config) {
                continue;
            }

            /** @var HtmlAttributes $attributes */
            $attributes = $config['attributes'];

            if (empty($attributes['onclick'])) {
                $attributes->set('data-action', 'click->terminal42--dcawizard#open:prevent');
            }

            $builder->append($config);
        }

        return $builder;
    }

    /**
     * @return array<string, string|int>
     */
    public function getButtonParams(array $params = [], array $data = []): array
    {
        $data += [
            'foreignTable' => $this->foreignTable,
            'field' => $this->strField,
            'currentRecord' => $this->currentRecord,
            'parentTable' => $this->strTable,
        ];

        $params += [
            'do' => Input::get('do'),
            'table' => $this->foreignTable,
            'id' => $this->currentRecord,
            'picker' => (new UrlConfig($data))->urlEncode(),
        ];

        if (\is_array($this->params)) {
            $params = array_merge($params, $this->params);
        }

        return $params;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRecords(): array
    {
        /** @var Connection $connection */
        $connection = System::getContainer()->get('database_connection');

        [$where, $values] = $this->getWhereCondition();

        return $connection->fetchAllAssociative(
            'SELECT * FROM '.$this->foreignTable.$where.$this->getOrderByStatement(),
            $values,
        );
    }

    /**
     * @return array<string>
     */
    public function getHeaderFields(): array|null
    {
        // Return the custom header fields defined in the widget settings
        if (!empty($this->headerFields) && \is_array($this->headerFields)) {
            return $this->headerFields;
        }

        // Return null, if there are no fields defined at all
        if (empty($this->fields) || !\is_array($this->fields)) {
            return null;
        }

        $headerFields = [];

        /** @var Formatter $formatter */
        $formatter = System::getContainer()->get(Formatter::class);

        foreach ($this->fields as $field) {
            if ('id' === $field) {
                $headerFields[$field] = 'ID';
                continue;
            }

            $headerFields[$field] = $formatter->dcaLabel($this->foreignTable, $field);
        }

        return $headerFields;
    }

    /**
     * Get WHERE statement.
     *
     * @return array{0: string, 1: array<string|int>}
     */
    public function getWhereCondition(): array
    {
        [$foreignTableCondition, $values] = $this->getForeignTableCondition();

        $strWhere = ' WHERE '.$foreignTableCondition;

        if ($this->whereCondition) {
            $strWhere .= ' AND '.$this->whereCondition;
        }

        return [$strWhere, $values];
    }

    /**
     * Get ORDER BY statement.
     */
    public function getOrderByStatement(): string
    {
        $strOrderBy = '';
        $orderFields = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['sorting']['fields'] ?? null;

        if ($this->orderField) {
            $strOrderBy = ' ORDER BY '.$this->orderField;
        } elseif (!empty($orderFields) && \is_array($orderFields)) {
            $strOrderBy = ' ORDER BY '.implode(',', $orderFields);
        }

        return $strOrderBy;
    }

    /**
     * Return SQL WHERE condition for foreign table.
     *
     * @return array{0: string, 1: array<string|int>}
     */
    public function getForeignTableCondition(): array
    {
        $where = "$this->foreignField=?";
        $values = [$this->currentRecord];

        $parentColumns = self::resolveParentColumns($this->parentColumns, $this->strTable, $this->strField);

        if (isset($GLOBALS['TL_DCA'][$this->foreignTable]['config']['dynamicPtable']) && !isset($parentColumns['ptable'])) {
            $parentColumns['ptable'] = $this->strTable;
        }

        foreach ($parentColumns as $column => $value) {
            $where .= ' AND '.$column.'=?';
            $values[] = $value;
        }

        return [$where, $values];
    }

    /**
     * Resolves the "parentColumns" configuration to column => value pairs.
     *
     * Columns listed here tie a record of the foreign table to one dcaWizard
     * field. They are used twice: to select the records to list, and to stamp
     * records created through the wizard. Without the second part a new record
     * would not match the condition and disappear right after saving.
     *
     * Values are taken literally, so any column can be bound to any value. The
     * two placeholders are filled with the table the field is defined on and
     * with the field name.
     *
     * @param array<string, scalar> $config
     *
     * @return array<string, scalar>
     */
    public static function resolveParentColumns(array $config, string $table, string $field): array
    {
        $resolved = [];

        foreach ($config as $column => $value) {
            $resolved[$column] = match ($value) {
                self::PARENT_TABLE => $table,
                self::PARENT_FIELD => $field,
                default => $value,
            };
        }

        return $resolved;
    }

    private function getCreateAction(array $record, int $sorting = 0): CreateAction
    {
        $data = [];

        if (DataContainer::MODE_PARENT === ($GLOBALS['TL_DCA'][$this->foreignTable]['list']['sorting']['mode'] ?? null)) {
            $data['pid'] = $record['pid'] ?? null;
            $data['sorting'] = $sorting;
        }

        if ($GLOBALS['TL_DCA'][$this->foreignTable]['config']['dynamicPtable'] ?? false) {
            $data['ptable'] = $record['ptable'] ?? null;
        }

        return new CreateAction($this->foreignTable, $data ?: null);
    }
}
