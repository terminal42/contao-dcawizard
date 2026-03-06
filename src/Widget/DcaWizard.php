<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\Widget;

use Codefog\HasteBundle\Formatter;
use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\String\HtmlAttributes;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides the back end widget "dcaWizard".
 *
 * @property string        $foreignTable
 * @property string        $foreignField
 * @property callable|null $foreignTable_callback
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

            default:
                parent::__set($strKey, $varValue);
                break;
        }
    }

    public function __isset($strKey)
    {
        return match ($strKey) {
            'currentRecord' => Input::get('id') || $this->objDca->id,
            'params' => isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['params']),
            'foreignTable' => isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable']),
            'foreignField' => true,
            'foreignTable_callback' => isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable_callback']),
            default => parent::__get($strKey),
        };
    }

    public function __get($strKey)
    {
        return match ($strKey) {
            'currentRecord' => Input::get('id') ?: $this->objDca->id,
            'params' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['params'] ?? null,
            'foreignTable' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'] ?? null,
            'foreignField' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignField'] ?? 'pid',
            'foreignTable_callback' => $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable_callback'] ?? null,
            default => parent::__get($strKey),
        };
    }

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
        ];

        if (!$this->hideButton) {
            $templateData['edit_button'] = [
                'url' => $this->getButtonUrl(),
                'label' => StringUtil::specialchars($this->editButtonLabel ?: $this->strLabel),
                'jsConfig' => $this->getModalOptions(),
            ];
        }

        return System::getContainer()->get('twig')->render(\sprintf('@Contao/%s.html.twig', $this->customTpl ?: 'backend/widget/dcawizard'), $templateData);
    }

    /**
     * @return array<string>
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

    public function getRecordOperations(array $record): array
    {
        $operations = [];

        foreach ($this->getAvailableRecordOperations() as $operation) {
            $operations[$operation] = $this->getRecordOperation($operation, $record);
        }

        return $operations;
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

    /**
     * @param array<string, mixed> $row
     */
    public function getRecordOperation(string $operation, array $row): string
    {
        // Load the button definition from the subtable
        $def = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations'][$operation] ?? null;

        if (null === $def && 'new' === $operation) {
            if (
                ($GLOBALS['TL_DCA'][$this->foreignTable]['config']['closed'] ?? null)
                || ($GLOBALS['TL_DCA'][$this->foreignTable]['config']['notCreatable'] ?? null)
                || 'sorting' !== ($GLOBALS['TL_DCA'][$this->foreignTable]['list']['sorting']['fields'][0] ?? null)
            ) {
                return '';
            }

            $def = [
                'href' => 'act=create&amp;mode=1&amp;pid='.$row['id'],
                'icon' => 'new.svg',
            ];
        }

        if (null === $def) {
            return '';
        }

        $def = \is_array($def) ? $def : [$def];

        if (class_exists(DataContainerOperation::class)) {
            if (!$this->objDca instanceof DataContainer) {
                throw new \RuntimeException('DcaWizard does not have a DataContainer object');
            }

            $config = new DataContainerOperation($operation, $def, $row, $this->objDca);
        } else {
            $id = StringUtil::specialchars(rawurldecode((string) $row['id']));

            // Dereference pointer to $GLOBALS['TL_LANG']
            $config = StringUtil::resolveReferences($def);

            if (isset($config['label'])) {
                if (\is_array($config['label'])) {
                    $config['title'] = \sprintf($config['label'][1] ?? '', $id);
                    $config['label'] = $config['label'][0] ?? $operation;
                } else {
                    $config['label'] = $config['title'] = \sprintf($config['label'], $id);
                }
            } else {
                $config['label'] = $config['title'] = $operation;
            }

            $attributes = !empty($config['attributes']) ? ' '.ltrim(\sprintf($config['attributes'], $id, $id)) : '';

            // Add the key as CSS class
            if (str_contains($attributes, 'class="')) {
                $attributes = str_replace('class="', 'class="'.$operation.' ', $attributes);
            } else {
                $attributes = ' class="'.$operation.'" '.$attributes;
            }

            $config['attributes'] = $attributes;
        }

        // Call a custom function instead of using the default button
        if (\is_array($config['button_callback'] ?? null)) {
            $callback = System::importStatic($config['button_callback'][0]);
            $ref = new \ReflectionMethod($callback, $config['button_callback'][1]);

            if (1 === $ref->getNumberOfParameters() && ($type = $ref->getParameters()[0]->getType()) && $type instanceof \ReflectionNamedType && DataContainerOperation::class === $type->getName()) {
                $callback->{$config['button_callback'][1]}($config);
            } else {
                return $callback->{$config['button_callback'][1]}($row, $config['href'] ?? null, $config['label'], $config['title'], $config['icon'] ?? null, $config['attributes'], $this->foreignTable, [], null, false, null, null, $this);
            }
        } elseif (\is_callable($config['button_callback'] ?? null)) {
            $ref = new \ReflectionFunction($config['button_callback']);

            if (1 === $ref->getNumberOfParameters() && ($type = $ref->getParameters()[0]->getType()) && $type instanceof \ReflectionNamedType && DataContainerOperation::class === $type->getName()) {
                $config['button_callback']($config);
            } else {
                return $config['button_callback']($row, $config['href'] ?? null, $config['label'], $config['title'], $config['icon'] ?? null, $config['attributes'], $this->foreignTable, [], null, false, null, null, $this);
            }
        }

        if ($config instanceof DataContainerOperation && ($html = $config->getHtml()) !== null) {
            return $html;
        }

        if (!isset($config['href'])) {
            return Image::getHtml($config['icon'], $config['label']).' ';
        }

        $href = $this->getButtonUrl().'&amp;'.$config['href'].'&amp;id='.$row['id'].'&amp;dcawizard_operation=1';

        if ($config['attributes'] instanceof HtmlAttributes) {
            $attributes = $config['attributes'];
        } else {
            $attributes = new HtmlAttributes($config['attributes'] ?? '');
        }

        if ('delete' === $operation || empty($attributes['onclick'])) {
            $baseOptions = $this->getModalOptions();
            $baseOptions['url'] = $href;

            if ('delete' === $operation) {
                $baseOptions['confirm'] = \sprintf($GLOBALS['TL_LANG']['MSC']['deleteConfirm'], $row['id']);
                $attributes->set('data-action', 'click->terminal42--dcawizard#delete:prevent');
                $attributes->unset('onclick');
            } else {
                $attributes->set('data-action', 'click->terminal42--dcawizard#open:prevent');
            }

            $attributes->set('data-dcawizard-options', StringUtil::specialchars(json_encode($baseOptions, JSON_THROW_ON_ERROR)));
        }

        parse_str(StringUtil::decodeEntities($config['href'] ?? ''), $params);

        if (($params['act'] ?? null) === 'toggle' && isset($params['field'])) {
            // Hide the toggle icon if the user does not have access to the field
            if (
                (
                    ($GLOBALS['TL_DCA'][$this->foreignTable]['fields'][$params['field']]['toggle'] ?? false) !== true
                    && ($GLOBALS['TL_DCA'][$this->foreignTable]['fields'][$params['field']]['reverseToggle'] ?? false) !== true
                ) || (
                    DataContainer::isFieldExcluded($this->foreignTable, $params['field'])
                    && !System::getContainer()->get('security.helper')->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, $this->foreignTable.'::'.$params['field'])
                )
            ) {
                return '';
            }

            $icon = $config['icon'];
            $_icon = pathinfo((string) $config['icon'], PATHINFO_FILENAME).'_.'.pathinfo((string) $config['icon'], PATHINFO_EXTENSION);

            if (str_contains((string) $config['icon'], '/')) {
                $_icon = \dirname((string) $config['icon']).'/'.$_icon;
            }

            if ('visible.svg' === $icon) {
                $_icon = 'invisible.svg';
            }

            if (!str_contains((string) $icon, '/')) {
                $icon = 'system/themes/'.Backend::getTheme().'/icons/'.$icon;
                $_icon = 'system/themes/'.Backend::getTheme().'/icons/'.$_icon;
            }

            $state = $row[$params['field']] ? 1 : 0;

            if (($config['reverse'] ?? false) || ($GLOBALS['TL_DCA'][$this->foreignTable]['fields'][$params['field']]['reverseToggle'] ?? false)) {
                $state = $row[$params['field']] ? 0 : 1;
            }

            if (isset($config['titleDisabled'])) {
                $titleDisabled = $config['titleDisabled'];
            } else {
                $titleDisabled = \is_array($config['label']) && isset($config['label'][2]) ? \sprintf($config['label'][2], $row['id']) : $config['title'];
            }

            return \sprintf(
                '<a href="%s" title="%s" data-title="%s" data-title-disabled="%s" onclick="return AjaxRequest.toggleField(this,%s)">%s</a> ',
                $href,
                StringUtil::specialchars($state ? $config['title'] : $titleDisabled),
                StringUtil::specialchars($config['title']),
                StringUtil::specialchars($titleDisabled),
                'visible.svg' === $icon ? 'true' : 'false',
                Image::getHtml($state ? $icon : $_icon, $config['label'], 'data-icon="'.$icon.'" data-icon-disabled="'.$_icon.'" data-state="'.$state.'"'),
            );
        }

        return \sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            $href,
            StringUtil::specialchars($config['title']),
            $config['attributes'],
            Image::getHtml($config['icon'], $config['label']),
        );
    }

    /**
     * @return array<string>
     */
    public function getGlobalOperations(): array
    {
        if (empty($this->global_operations)) {
            return [];
        }

        $globalOperations = [];

        foreach ($this->global_operations as $globalOperation) {
            $globalOperations[$globalOperation] = $this->getGlobalOperation($globalOperation);
        }

        return $globalOperations;
    }

    public function getGlobalOperation(string $operation): string
    {
        $definition = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['global_operations'][$operation] ?? null;

        // Cannot edit all here
        if ('all' === $operation) {
            return '';
        }

        // Special handling for the "new" operation
        if (null === $definition && 'new' === $operation) {
            // The table is closed
            if (($GLOBALS['TL_DCA'][$this->foreignTable]['config']['closed'] ?? null) || ($GLOBALS['TL_DCA'][$this->foreignTable]['config']['notCreatable'] ?? null)) {
                return '';
            }

            $definition = [
                'href' => 'act=create&amp;mode=2&amp;pid='.$this->currentRecord,
                'icon' => 'new.svg',
                'label' => $GLOBALS['TL_LANG'][$this->foreignTable]['new'] ?? $GLOBALS['TL_LANG']['DCA']['new'],
            ];
        }

        if (null === $definition) {
            return '';
        }

        $definition = \is_array($definition) ? $definition : [$definition];
        $title = $label = $operation;

        if (isset($definition['label'])) {
            $label = \is_array($definition['label']) ? $definition['label'][0] : $definition['label'];
            $title = \is_array($definition['label']) ? ($definition['label'][1] ?? null) : $definition['label'];
        }

        $buttonHref = $this->getButtonUrl().'&amp;'.$definition['href'].'&amp;dcawizard_operation=1';

        $attributes = !empty($definition['attributes']) ? ' '.ltrim((string) $definition['attributes']) : '';

        if ($definition['icon'] ?? null) {
            $definition['class'] = trim(($definition['class'] ?? '').' header_icon');

            // Add the theme path if only the file name is given
            if (!str_contains((string) $definition['icon'], '/')) {
                $definition['icon'] = Image::getPath($definition['icon']);
            }

            $attributes = \sprintf(' style="background-image:url(\'%s\')"', Controller::addAssetsUrlTo($definition['icon'])).$attributes;
        }

        // Dca wizard specific
        if (empty($config['attributes']) || !str_contains((string) $config['attributes'], 'onclick="')) {
            $arrBaseOptions = $this->getModalOptions();
            $arrBaseOptions['url'] = $buttonHref;

            $attributes .= ' data-dcawizard-options="'.StringUtil::specialchars(json_encode($arrBaseOptions, JSON_THROW_ON_ERROR)).'"';
            $attributes .= ' data-action="click->terminal42--dcawizard#open:prevent"';
        }

        if (!$label) {
            $label = $operation;
        }

        if (!$title) {
            $title = $label;
        }

        // Call a custom function instead of using the default button
        if (\is_array($definition['button_callback'] ?? null)) {
            $this->import($definition['button_callback'][0]);

            return $this->{$definition['button_callback'][0]}->{$definition['button_callback'][1]}($definition['href'] ?? '', $label, $title, $definition['class'], $attributes, $this->foreignTable);
        }

        if (\is_callable($definition['button_callback'] ?? null)) {
            return $definition['button_callback']($definition['href'] ?? null, $label, $title, $definition['class'] ?? null, $attributes, $this->foreignTable);
        }

        return '<a href="'.$buttonHref.'" class="'.$definition['class'].'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.$label.'</a> ';
    }

    /**
     * @return array<string, int|string>
     */
    public function getModalOptions(): array
    {
        return [
            'class' => base64_encode(static::class),
            'id' => $this->strId,
            'title' => StringUtil::specialchars($this->strLabel),
            'url' => $this->getButtonUrl(),
        ];
    }

    public function getButtonUrl(): string
    {
        return System::getContainer()->get('router')->generate('contao_backend', $this->getButtonParams(), UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @return array<string, string|int>
     */
    public function getButtonParams(): array
    {
        $params = [
            'do' => Input::get('do'),
            'table' => $this->foreignTable,
            'field' => $this->strField,
            'id' => $this->currentRecord,
            'popup' => 1,
            'ref' => Input::get('ref'),
            'rt' => System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(),
            'dcawizard' => $this->foreignTable.':'.$this->currentRecord,
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

        if (isset($GLOBALS['TL_DCA'][$this->foreignTable]['config']['dynamicPtable'])) {
            $where .= ' AND ptable=?';
            $values[] = $this->strTable;
        }

        return [$where, $values];
    }
}
