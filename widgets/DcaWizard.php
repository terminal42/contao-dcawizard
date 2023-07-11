<?php

/**
 * dcawizard extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       https://github.com/terminal42/contao-dcawizard
 */

use Contao\BackendTemplate;
use Contao\Database;
use Contao\Environment;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides the back end widget "dcaWizard"
 *
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DcaWizard extends Widget
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'be_widget';


    /**
     * Initialize the object
     *
     * @param array $arrAttributes
     */
    public function __construct($arrAttributes = false)
    {
        parent::__construct($arrAttributes);

        // Load the table from callback
        $varCallback = $this->foreignTableCallback;
        if (is_array($varCallback) && !empty($varCallback)) {
            $this->foreignTable = System::importStatic($varCallback[0])->{$varCallback[1]}($this);
        } elseif (is_callable($varCallback)) {
            $this->foreignTable = $varCallback($this);
        }

        if ($this->foreignTable != '') {
            $this->loadDataContainer($this->foreignTable);
            System::loadLanguageFile($this->foreignTable);
        }

        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        // Set the referer
        if ($request !== null) {
            $request->getSession()->getBag('contao_backend')->set('dcaWizardReferer', Environment::get('request'));
        }
    }

    /**
     * Add specific attributes
     *
     * @param string $strKey
     * @param mixed  $varValue
     */
    public function __set($strKey, $varValue)
    {
        switch($strKey) {
            case 'value':
                $this->varValue = $varValue;
                break;
            case 'mandatory':
                $this->arrConfiguration[$strKey] = $varValue ? true : false;
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

    /**
     * Return a parameter
     *
     * @return string $strKey
     */
    public function __get($strKey)
    {
        switch($strKey) {
            case 'currentRecord':
                return Input::get('id') ?: $this->objDca->id;

            case 'params':
                if (!isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['params'])) {
                    return null;
                }
                return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['params'];

            case 'foreignTable':
                return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'];

            case 'foreignField':
                if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignField'])) {
                    $foreignField = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignField'];
                }

                if (empty($foreignField)) {
                    $foreignField = 'pid';
                }

                return $foreignField;

            case 'foreignTableCallback':
                if (!isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTableCallback'])) {
                    return null;
                }
                return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTableCallback'];

            default:
                return parent::__get($strKey);
        }
    }

    /**
     * Validate input
     */
    public function validate()
    {
        if ($this->mandatory) {
            $where = $this->getForeignTableCondition();
            $values = [];

            if (\is_array($where)) {
                $values = $where[1];
                $where = $where[0];
            }

            $objRecords = Database::getInstance()->prepare("SELECT id FROM {$this->foreignTable} WHERE " . $where)->execute(...$values);

            if (!$objRecords->numRows && $this->strLabel == '') {
                $this->addError($GLOBALS['TL_LANG']['ERR']['mdtryNoLabel']);
            } elseif (!$objRecords->numRows) {
                $this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
            }
        }
    }

    /**
     * Generate the widget
     *
     * @return string
     */
    public function generate()
    {
        $varCallback = $this->listCallback;
        $blnShowOperations = $this->showOperations;

        $objTemplate = new BackendTemplate($this->customTpl ?: 'be_widget_dcawizard');
        $objTemplate->strId = $this->strId;
        $objTemplate->hideButton = $this->hideButton;

        $objTemplate->dcaLabel = function ($field) {
            if (\class_exists(\Codefog\HasteBundle\Formatter::class)) {
                return \Contao\System::getContainer()
                    ->get(\Codefog\HasteBundle\Formatter::class)
                    ->dcaLabel($this->foreignTable, $field)
                ;
            }

            return \Haste\Util\Format::dcaLabel($this->foreignTable, $field);
        };

        $objTemplate->dcaValue = function ($field, $value) {
            if (\class_exists(\Codefog\HasteBundle\Formatter::class)) {
                return \Contao\System::getContainer()
                    ->get(\Codefog\HasteBundle\Formatter::class)
                    ->dcaValue($this->foreignTable, $field, $value, $this->dataContainer)
                ;
            }

            return \Haste\Util\Format::dcaValue($this->foreignTable, $field, $value, $this->dataContainer);
        };

        // Get the available records
        $objRecords = $this->getRecords();

        if ($varCallback === null) {
            $arrRows = $this->getRows($objRecords);

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

            $objTemplate->generateOperation = function ($operation, $row) {
                return $this->generateRowOperation($operation, $row);
            };

        } else {
            $strCallback = '';
            if (is_array($varCallback)) {
                $strCallback = System::importStatic($varCallback[0])->{$varCallback[1]}($objRecords, $this->strId, $this);
            } elseif (is_callable($varCallback)) {
                $strCallback = $varCallback($objRecords, $this->strId, $this);
            }

            $objTemplate->hasListCallback = true;
            $objTemplate->listCallbackContent = $strCallback;
        }

        $objTemplate->buttonHref        = $this->getButtonHref();
        $objTemplate->dcaWizardOptions  = StringUtil::specialchars(json_encode($this->getDcaWizardOptions()));
        $objTemplate->buttonLabel       = $this->getButtonLabel();

        return $objTemplate->parse();
    }

    /**
     * Generate a row operation
     *
     * @param string $operation operation name
     * @param array  $row       Db row
     *
     * @return string
     */
    public function generateRowOperation($operation, $row)
    {
        // Load the button definition from the subtable
        $def = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations'][$operation];

        $id = StringUtil::specialchars(rawurldecode($row['id']));
        $buttonHref = $this->getButtonHref() . '&amp;' . $def['href'] . '&amp;id='.$row['id'] . '&amp;dcawizard_operation=1';

        if (is_array($def['label'])) {
            $label = $def['label'][0] ?: $operation;
            $title = sprintf($def['label'][1] ?: $operation, $id);
        } else {
            $label = $title = sprintf($def['label'] ?: $operation, $id);
        }
        $attributes = (isset($def['attributes']) && $def['attributes'] != '') ? ' ' . ltrim(sprintf($def['attributes'], $id, $id)) : '';

        // Dca wizard specific
        $arrBaseOptions = $this->getDcaWizardOptions();
        $arrBaseOptions['url'] = $buttonHref;
        $attributes .= ' data-options="' . StringUtil::specialchars(json_encode($arrBaseOptions)) . '"';
        $attributes .= ' onclick="Backend.getScrollOffset();DcaWizard.openModalWindow(JSON.parse(this.getAttribute(\'data-options\')));return false"';

        // Add the key as CSS class
        if (strpos($attributes, 'class="') !== false) {
            $attributes = str_replace('class="', 'class="' . $operation . ' ', $attributes);
        } else {
            $attributes = ' class="' . $operation . '"' . $attributes;
        }

        // Call a custom function instead of using the default button
        if (isset($def['button_callback']) && is_array($def['button_callback']))  {
            return System::importStatic($def['button_callback'][0])->{$def['button_callback'][1]}($row, $def['href'] . '&amp;' . http_build_query($this->getButtonParams(), '', '&amp;'), $label, $title, $def['icon'], $attributes, $this->foreignTable);
        } elseif (isset($def['button_callback']) && is_callable($def['button_callback'])) {
            return $def['button_callback']($row, $def['href'] . '&amp;' . http_build_query($this->getButtonParams(), '', '&amp;'), $label, $title, $def['icon'], $attributes, $this->foreignTable);
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            $buttonHref,
            StringUtil::specialchars($title),
            $attributes,
            Image::getHtml($def['icon'], $label)
        );
    }


    /**
     * Get active row operations
     *
     * @return array
     */
    public function getActiveRowOperations()
    {
        return (array) ($this->operations ?: array_keys($GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']));
    }

    /**
     * Get rows
     */
    public function getRows($objRecords)
    {
        return $objRecords->fetchAllAssoc();
    }

    /**
     * Get dca wizard javascript options
     *
     * @return array
     */
    public function getDcaWizardOptions()
    {
        return [
            'title'         => StringUtil::specialchars($this->strLabel),
            'url'           => $this->getButtonHref(),
            'id'            => $this->strId,
            'applyLabel'    => StringUtil::specialchars($this->applyButtonLabel),
            'class'         => base64_encode(get_class($this))
        ];
    }

    /**
     * Get button href
     *
     * @return string
     */
    public function getButtonHref()
    {
        return System::getContainer()->get('router')->generate('contao_backend', $this->getButtonParams(), RouterInterface::ABSOLUTE_URL);
    }

    /**
     * Get button params
     *
     * @return array
     */
    public function getButtonParams()
    {
        $arrParams = array
        (
            'do'        => Input::get('do'),
            'table'     => $this->foreignTable,
            'field'     => $this->strField,
            'id'        => $this->currentRecord,
            'popup'     => 1,
            'nb'        => 1,
            'rt'        => Input::get('rt'),
            'dcawizard' => $this->foreignTable . ':' . $this->currentRecord,
        );

        // Merge
        if (is_array($this->params)) {
            $arrParams = array_merge($arrParams, $this->params);
        }

        return $arrParams;
    }

    /**
     * Get button label
     *
     * @return string
     */
    public function getButtonLabel()
    {
        return StringUtil::specialchars($this->editButtonLabel ?: $this->strLabel);
    }

    /**
     * Get records
     */
    public function getRecords()
    {
        $where = $this->getWhereCondition();
        $values = [];

        if (\is_array($where)) {
            $values = $where[1];
            $where = $where[0];
        }

        return Database::getInstance()->prepare(
            "SELECT * FROM {$this->foreignTable}" .
            $where .
            $this->getOrderBy()
        )->execute(...$values);
    }

    /**
     * Get header fields
     *
     * @return array
     */
    public function getHeaderFields()
    {
        $arrHeaderFields = $this->headerFields;

        if (!is_array($arrHeaderFields) || empty($arrHeaderFields)) {
            foreach ($this->fields as $field) {
                if ($field == 'id') {
                    $arrHeaderFields[] = 'ID';
                    continue;
                }

                $arrHeaderFields[] = $field;
            }
        }

        return $arrHeaderFields;
    }

    /**
     * Get WHERE statement
     *
     * @return array|string
     */
    public function getWhereCondition()
    {
        $foreignTableCondition = $this->getForeignTableCondition();
        $values = [];

        if (\is_array($foreignTableCondition)) {
            $values = $foreignTableCondition[1];
            $foreignTableCondition = $foreignTableCondition[0];
        }

        $strWhere = ' WHERE tstamp>0 AND ' . $foreignTableCondition;

        if ($this->whereCondition) {
            $strWhere .= ' AND ' . $this->whereCondition;
        }

        return [$strWhere, $values];
    }

    /**
     * Get ORDER BY statement
     *
     * @return string
     */
    public function getOrderBy()
    {
        $strOrderBy = '';
        $orderFields = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['sorting']['fields'];

        if ($this->orderField) {
            $strOrderBy = ' ORDER BY ' . $this->orderField;
        } elseif (!empty($orderFields) && is_array($orderFields)) {
            $strOrderBy = ' ORDER BY ' . implode(',', $orderFields);
        }

        return $strOrderBy;
    }

    /**
     * Return SQL WHERE condition for foreign table
     *
     * @return array
     */
    public function getForeignTableCondition()
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
