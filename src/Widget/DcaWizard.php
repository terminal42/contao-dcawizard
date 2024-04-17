<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle\Widget;

use Codefog\HasteBundle\Formatter;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Haste\Util\Format;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides the back end widget "dcaWizard".
 *
 * @property string        $foreignTable
 * @property string        $foreignField
 * @property callable|null $foreignTable_callback
 * @property array         $headerFields
 * @property array         $fields
 * @property callable|null $list_callback
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

    public function __construct($arrAttributes = null)
    {
        parent::__construct($arrAttributes);

        // Load the table from callback
        $varCallback = $this->foreignTable_callback;
        if (!empty($varCallback) && \is_array($varCallback)) {
            $this->foreignTable = System::importStatic($varCallback[0])->{$varCallback[1]}($this);
        } elseif (\is_callable($varCallback)) {
            $this->foreignTable = $varCallback($this);
        }

        if (!empty($this->foreignTable)) {
            Controller::loadDataContainer($this->foreignTable);
            System::loadLanguageFile($this->foreignTable);
        }

        /** @var Request|null $request */
        $request = System::getContainer()->get('request_stack')?->getCurrentRequest();
        $request?->getSession()->getBag('contao_backend')->set('dcaWizardReferer', $request->getRequestUri());
    }

    /**
     * Add specific attributes.
     *
     * @param string $strKey
     * @param mixed  $varValue
     */
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

    /**
     * Return a parameter.
     *
     * @return string
     */
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
                $this->addError('' === $this->strLabel ? $GLOBALS['TL_LANG']['ERR']['mdtryNoLabel'] : sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
            }
        }
    }

    public function generate(): string
    {
        $varCallback = $this->list_callback;
        $blnShowOperations = $this->showOperations;

        /** @var BackendTemplate&\stdClass $objTemplate */
        $objTemplate = new BackendTemplate($this->customTpl ?: 'be_widget_dcawizard');
        $objTemplate->strId = $this->strId;
        $objTemplate->hideButton = $this->hideButton;

        $objTemplate->dcaLabel = function ($field) {
            if (class_exists(Formatter::class)) {
                return System::getContainer()
                    ->get(Formatter::class)
                    ?->dcaLabel($this->foreignTable, $field)
                ;
            }

            return Format::dcaLabel($this->foreignTable, $field);
        };

        $objTemplate->dcaValue = function ($field, $value) {
            if (class_exists(Formatter::class)) {
                return System::getContainer()
                    ->get(Formatter::class)
                    ?->dcaValue($this->foreignTable, $field, $value, $this->dataContainer)
                ;
            }

            return Format::dcaValue($this->foreignTable, $field, $value, $this->dataContainer);
        };

        $objTemplate->generateGlobalOperation = $this->generateGlobalOperation(...);

        // Get the available records
        $arrRows = $this->getRecords();

        if (null === $varCallback) {
            $objTemplate->hasListCallback = false;
            $objTemplate->headerFields = $this->getHeaderFields();
            $objTemplate->hasRows = !empty($arrRows);
            $objTemplate->rows = $arrRows;
            $objTemplate->fields = $this->fields;
            $objTemplate->showOperations = $blnShowOperations;
            $objTemplate->emptyLabel = $this->emptyLabel;

            if ($blnShowOperations) {
                $objTemplate->operations = $this->getActiveRowOperations();
            }

            $objTemplate->generateOperation = $this->generateRowOperation(...);

        } else {
            $strCallback = '';
            if (\is_array($varCallback)) {
                $strCallback = System::importStatic($varCallback[0])->{$varCallback[1]}($arrRows, $this->strId, $this);
            } elseif (\is_callable($varCallback)) {
                $strCallback = $varCallback($arrRows, $this->strId, $this);
            }

            $objTemplate->hasListCallback = true;
            $objTemplate->listCallbackContent = $strCallback;
        }

        $objTemplate->globalOperations = $this->global_operations;
        $objTemplate->buttonHref = $this->getButtonHref();
        $objTemplate->dcaWizardOptions = StringUtil::specialchars(json_encode($this->getDcaWizardOptions(), JSON_THROW_ON_ERROR));
        $objTemplate->buttonLabel = $this->getButtonLabel();

        return $objTemplate->parse();
    }

    public function generateGlobalOperation($operation): string
    {
        $def = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['global_operations'][$operation] ?? null;

        // Cannot edit all in DcaWizard
        if ('all' === $operation) {
            return '';
        }

        if (null === $def && 'new' === $operation) {
            if (
                ($GLOBALS['TL_DCA'][$this->foreignTable]['config']['closed'] ?? null)
                || ($GLOBALS['TL_DCA'][$this->foreignTable]['config']['notCreatable'] ?? null)
            ) {
                return '';
            }

            $def = [
                'href' => 'act=create&amp;mode=2&amp;pid='.$this->currentRecord,
                'icon' => 'new.svg',
                'label' => $GLOBALS['TL_LANG'][$this->foreignTable]['new'] ?? $GLOBALS['TL_LANG']['DCA']['new'],
            ];
        }

        if (null === $def) {
            return '';
        }

        $def = \is_array($def) ? $def : [$def];
        $title = $label = $operation;

        if (isset($def['label'])) {
            $label = \is_array($def['label']) ? $def['label'][0] : $def['label'];
            $title = \is_array($def['label']) ? ($def['label'][1] ?? null) : $def['label'];
        }

        $buttonHref = $this->getButtonHref().'&amp;'.$def['href'].'&amp;dcawizard_operation=1';

        $attributes = !empty($def['attributes']) ? ' '.ltrim((string) $def['attributes']) : '';

        if ($def['icon'] ?? null) {
            $def['class'] = trim(($def['class'] ?? '').' header_icon');

            // Add the theme path if only the file name is given
            if (!str_contains((string) $def['icon'], '/')) {
                $def['icon'] = Image::getPath($def['icon']);
            }

            $attributes = sprintf(' style="background-image:url(\'%s\')"', Controller::addAssetsUrlTo($def['icon'])).$attributes;
        }

        // Dca wizard specific
        $arrBaseOptions = $this->getDcaWizardOptions();
        $arrBaseOptions['url'] = $buttonHref;
        $attributes .= ' data-options="'.StringUtil::specialchars(json_encode($arrBaseOptions, JSON_THROW_ON_ERROR)).'"';
        $attributes .= ' onclick="Backend.getScrollOffset();DcaWizard.openModalWindow(JSON.parse(this.getAttribute(\'data-options\')));return false"';

        if (!$label) {
            $label = $operation;
        }

        if (!$title) {
            $title = $label;
        }

        // Call a custom function instead of using the default button
        if (\is_array($def['button_callback'] ?? null)) {
            $this->import($def['button_callback'][0]);

            return $this->{$def['button_callback'][0]}->{$def['button_callback'][1]}($def['href'] ?? '', $label, $title, $def['class'], $attributes, $this->foreignTable);
        }

        if (\is_callable($def['button_callback'] ?? null)) {
            return $def['button_callback']($def['href'] ?? null, $label, $title, $def['class'] ?? null, $attributes, $this->foreignTable);
        }

        return '<a href="'.$buttonHref.'" class="'.$def['class'].'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.$label.'</a> ';
    }

    public function generateRowOperation(string $operation, array $row): string
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

        $def = \is_array($def) ? $def : array($def);

        if (\class_exists(DataContainerOperation::class)) {
            if (!$this->objDca instanceof DataContainer) {
                throw new \RuntimeException('DcaWizard does not have a DataContainer object');
            }

            $config = new DataContainerOperation($operation, $def, $row, $this->objDca);
        } else {
            $id = StringUtil::specialchars(rawurldecode((string) $row['id']));

            // Dereference pointer to $GLOBALS['TL_LANG']
            $config = \method_exists(StringUtil::class, 'resolveReferences') ? StringUtil::resolveReferences($def) : $def;

            if (isset($config['label'])) {
                if (\is_array($config['label'])) {
                    $config['title'] = sprintf($config['label'][1] ?? '', $id);
                    $config['label'] = $config['label'][0] ?? $operation;
                } else {
                    $config['label'] = $config['title'] = sprintf($config['label'], $id);
                }
            } else {
                $config['label'] = $config['title'] = $operation;
            }

            $attributes = !empty($config['attributes']) ? ' '.ltrim(sprintf($config['attributes'], $id, $id)) : '';

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

            if ($ref->getNumberOfParameters() === 1 && ($type = $ref->getParameters()[0]->getType()) && $type->getName() === DataContainerOperation::class) {
                $callback->{$config['button_callback'][1]}($config);
            } else {
                return $callback->{$config['button_callback'][1]}($row, $config['href'] ?? null, $config['label'], $config['title'], $config['icon'] ?? null, $config['attributes'], $this->foreignTable, [], null, false, null, null, $this);
            }
        } elseif (\is_callable($config['button_callback'] ?? null)) {
            $ref = new \ReflectionFunction($config['button_callback']);

            if ($ref->getNumberOfParameters() === 1 && ($type = $ref->getParameters()[0]->getType()) && $type->getName() === DataContainerOperation::class) {
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

        // Dca wizard specific
        $href = $this->getButtonHref().'&amp;'.$config['href'].'&amp;id='.$row['id'].'&amp;dcawizard_operation=1';
        $arrBaseOptions = $this->getDcaWizardOptions();
        $arrBaseOptions['url'] = $href;
        $config['attributes'] .= ' data-options="'.StringUtil::specialchars(json_encode($arrBaseOptions, JSON_THROW_ON_ERROR)).'"';
        $config['attributes'] .= ' onclick="Backend.getScrollOffset();DcaWizard.openModalWindow(JSON.parse(this.getAttribute(\'data-options\')));return false"';

        parse_str(StringUtil::decodeEntities($config['href'] ?? ''), $params);

        if (($params['act'] ?? null) === 'toggle' && isset($params['field'])) {
            // Hide the toggle icon if the user does not have access to the field
            if (
                (
                    ($GLOBALS['TL_DCA'][$this->foreignTable]['fields'][$params['field']]['toggle'] ?? false) !== true
                    && ($GLOBALS['TL_DCA'][$this->foreignTable]['fields'][$params['field']]['reverseToggle'] ?? false) !== true
                ) || (
                    (!\method_exists(DataContainer::class, 'isFieldExcluded') || DataContainer::isFieldExcluded($this->foreignTable, $params['field']))
                    && !System::getContainer()->get('security.helper')->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, $this->foreignTable.'::'.$params['field'])
                )
            ) {
                return '';
            }

            $icon = $config['icon'];
            $_icon = pathinfo($config['icon'], PATHINFO_FILENAME).'_.'.pathinfo($config['icon'], PATHINFO_EXTENSION);

            if (str_contains($config['icon'], '/')) {
                $_icon = \dirname($config['icon']).'/'.$_icon;
            }

            if ($icon === 'visible.svg') {
                $_icon = 'invisible.svg';
            }

            if (!str_contains($icon, '/')) {
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
                $titleDisabled = (\is_array($config['label']) && isset($config['label'][2])) ? sprintf($config['label'][2], $row['id']) : $config['title'];
            }

            return sprintf(
                '<a href="%s" title="%s" data-title="%s" data-title-disabled="%s" onclick="return AjaxRequest.toggleField(this,%s)">%s</a> ',
                $href,
                StringUtil::specialchars($state ? $config['title'] : $titleDisabled),
                StringUtil::specialchars($config['title']),
                StringUtil::specialchars($titleDisabled),
                $icon === 'visible.svg' ? 'true' : 'false',
                Image::getHtml($state ? $icon : $_icon, $config['label'], 'data-icon="'.$icon.'" data-icon-disabled="'.$_icon.'" data-state="'.$state.'"')
            );
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            $href,
            StringUtil::specialchars($config['title']),
            $config['attributes'],
            Image::getHtml($config['icon'], $config['label'])
        );
    }

    public function getActiveRowOperations(): array
    {
        return $this->operations ?: array_keys($GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']);
    }

    /**
     * Get dca wizard javascript options.
     */
    public function getDcaWizardOptions(): array
    {
        return [
            'title' => StringUtil::specialchars($this->strLabel),
            'url' => $this->getButtonHref(),
            'id' => $this->strId,
            'class' => base64_encode(static::class),
        ];
    }

    public function getButtonHref(): string
    {
        return System::getContainer()->get('router')?->generate('contao_backend', $this->getButtonParams(), UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getButtonParams(): array
    {
        $arrParams = [
            'do' => Input::get('do'),
            'table' => $this->foreignTable,
            'field' => $this->strField,
            'id' => $this->currentRecord,
            'popup' => 1,
            'nb' => 1,
            'ref' => Input::get('ref'),
            'rt' => System::getContainer()->get('contao.csrf.token_manager')?->getDefaultTokenValue(),
            'dcawizard' => $this->foreignTable.':'.$this->currentRecord,
        ];

        // Merge
        if (\is_array($this->params)) {
            $arrParams = array_merge($arrParams, $this->params);
        }

        return $arrParams;
    }

    public function getButtonLabel(): string
    {
        return StringUtil::specialchars($this->editButtonLabel ?: $this->strLabel);
    }

    public function getRecords(): array
    {
        /** @var Connection $connection */
        $connection = System::getContainer()->get('database_connection');

        [$where, $values] = $this->getWhereCondition();

        return $connection->fetchAllAssociative(
            'SELECT * FROM '.$this->foreignTable.$where.$this->getOrderBy(),
            $values
        );
    }

    public function getHeaderFields(): array
    {
        $arrHeaderFields = $this->headerFields;

        if (empty($arrHeaderFields) || !\is_array($arrHeaderFields)) {
            foreach ($this->fields as $field) {
                if ('id' === $field) {
                    $arrHeaderFields[] = 'ID';
                    continue;
                }

                $arrHeaderFields[] = $field;
            }
        }

        return $arrHeaderFields;
    }

    /**
     * Get WHERE statement.
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
    public function getOrderBy(): string
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
