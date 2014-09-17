<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2012 Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://www.isotopeecommerce.com
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 */

use \Haste\Util\Format;


/**
 * Class DcaWizard
 *
 * Back end widget "dca wizard".
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @author     Christian de la Haye <service@delahaye.de>
 * @author     Kamil Kuzminski <kamil.kuzminski@codefog.pl>
 */
class DcaWizard extends \Widget
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'be_widget';


    /**
     * Initialize the object
     * @param array
     */
    public function __construct($arrAttributes = false)
    {
        parent::__construct($arrAttributes);

        // Load the table from callback
        $varCallback = $this->foreignTableCallback;
        if (is_array($varCallback) && !empty($varCallback)) {
            $this->foreignTable = \System::importStatic($varCallback[0])->{$varCallback[1]}();
        } elseif (is_callable($varCallback)) {
            $this->foreignTable = $varCallback();
        }

        if ($this->foreignTable != '') {
            $this->loadDataContainer($this->foreignTable);
            \System::loadLanguageFile($this->foreignTable);
        }
    }


    /**
     * Add specific attributes
     * @param string
     * @param mixed
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
     * @return string
     * @throws Exception
     */
    public function __get($strKey)
    {
        switch($strKey) {
            case 'currentRecord':
                return \Input::get('id');

            case 'params':
                return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['params'];

            case 'foreignTable':
                return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'];

            case 'foreignField':
                $foreignField = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignField'];

                if (empty($foreignField)) {
                    $foreignField = 'pid';
                }

                return $foreignField;

            case 'foreignTableCallback':
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
            $objRecords = \Database::getInstance()->execute("SELECT id FROM {$this->foreignTable} WHERE " . $this->getForeignTableCondition());

            if (!$objRecords->numRows && $this->strLabel == '') {
                $this->addError($GLOBALS['TL_LANG']['ERR']['mdtryNoLabel']);
            } elseif (!$objRecords->numRows) {
                $this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
            }
        }
    }


    /**
     * Generate the widget
     * @return string
     */
    public function generate()
    {
        $varCallback = $this->listCallback;
        $blnShowOperations = $this->showOperations;
        $widget = $this;

        // Add assets
        $GLOBALS['TL_JAVASCRIPT']['dcawizard'] = sprintf('system/modules/dcawizard/assets/dcawizard%s.js', (($GLOBALS['TL_CONFIG']['debugMode']) ? '' : '.min'));


        $objTemplate = new BackendTemplate('be_widget_dcawizard');
        $objTemplate->strId = $this->strId;

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

            if ($blnShowOperations) {
                $objTemplate->operations = $this->getActiveRowOperations();
            }

            $objTemplate->generateOperation = function($operation, $row) use ($widget) {
                return $widget->generateRowOperation($operation, $row);
            };

        } else {
            $strCallback = '';
            if (is_array($varCallback)) {
                $strCallback = \System::importStatic($varCallback[0])->{$varCallback[1]}($objRecords, $this->strId, $this);
            } elseif (is_callable($varCallback)) {
                $strCallback = $varCallback($objRecords, $this->strId, $this);
            }

            $objTemplate->hasListCallback = true;
            $objTemplate->listCallbackContent = $strCallback;
        }

        $objTemplate->buttonHref        = ampersand($this->getButtonHref());
        $objTemplate->dcaWizardOptions  = specialchars(json_encode($this->getDcaWizardOptions()));
        $objTemplate->buttonLabel       = $this->getButtonLabel();

        return $objTemplate->parse();
    }


    /**
     * Generate a row operation
     * @param   string operation name
     * @param   array Db row
     * @return  string
     */
    public function generateRowOperation($operation, $row)
    {
        // Load the button definition from the subtable
        $def = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations'][$operation];

        $id = specialchars(rawurldecode($row['id']));
        $buttonHref = $this->getButtonHref() . '&amp;' . $def['href'] . '&amp;id='.$row['id'];

        $label = $def['label'][0] ?: $operation;
        $title = sprintf($def['label'][1] ?: $operation, $id);
        $attributes = ($def['attributes'] != '') ? ' ' . ltrim(sprintf($def['attributes'], $id, $id)) : '';

        // Dca wizard specific
        $arrBaseOptions = $this->getDcaWizardOptions();
        $arrBaseOptions['url'] = $buttonHref;
        $attributes .= ' data-options="' . specialchars(json_encode($arrBaseOptions)) . '"';
        $attributes .= ' onclick="Backend.getScrollOffset();DcaWizard.openModalWindow(JSON.parse(this.getAttribute(\'data-options\')));return false"';

        // Add the key as CSS class
        if (strpos($attributes, 'class="') !== false) {
            $attributes = str_replace('class="', 'class="' . $operation . ' ', $attributes);
        } else {
            $attributes = ' class="' . $operation . '"' . $attributes;
        }

        // Call a custom function instead of using the default button
        if (is_array($def['button_callback']))  {
            return \System::importStatic($def['button_callback'][0])->{$def['button_callback'][1]}($row, $def['href'], $label, $title, $def['icon'], $attributes);
        } elseif (is_callable($def['button_callback'])) {
            return $def['button_callback']($row, $def['href'], $label, $title, $def['icon'], $attributes);
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            $buttonHref,
            specialchars($title),
            $attributes,
            \Image::getHtml($def['icon'], $label)
        );
    }


    /**
     * Get active row operations
     * @return  array
     */
    public function getActiveRowOperations()
    {
        return (array) ($this->operations ?: array_keys($GLOBALS['TL_DCA'][$this->foreignTable]['list']['operations']));
    }


    /**
     * Get rows
     * @param \Database_Result
     * @return array
     */
    public function getRows($objRecords)
    {
        if (!$objRecords->numRows) {
            return array();
        }

        $arrRows = array();
        $objRecords->reset();

        while ($objRecords->next()) {
            $arrField = $objRecords->row();

            foreach ($this->fields as $field) {
                $arrField[$field] = Format::dcaValue($this->foreignTable, $field, $objRecords->$field);
            }

            $arrRows[] = $arrField;
        }

        return $arrRows;
    }


    /**
     * Get dca wizard javascript options
     * @return array
     */
    public function getDcaWizardOptions()
    {
        return array
        (
            'width'         => 765,
            'title'         => specialchars($this->strLabel),
            'url'           => $this->getButtonHref(),
            'id'            => $this->strId,
            'applyLabel'    => specialchars($this->applyButtonLabel)
        );
    }


    /**
     * Get button href
     * @return string
     */
    public function getButtonHref()
    {
        return \Environment::get('base')
            . \Environment::get('script')
            . '?'
            . http_build_query($this->getButtonParams());
    }


    /**
     * Get button params
     * @return array
     */
    public function getButtonParams()
    {
        $arrParams = array
        (
            'do'        => \Input::get('do'),
            'table'     => $this->foreignTable,
            'field'     => $this->strField,
            'id'        => $this->currentRecord,
            'popup'     => 1,
            'rt'        => REQUEST_TOKEN,
        );

        // Merge params
        if (!empty($this->params) && is_array($this->params)) {
            $arrParams = array_merge($arrParams, $this->params);
        }

        return $arrParams;
    }


    /**
     * Get button label
     * @return string
     */
    public function getButtonLabel()
    {
        return specialchars($this->editButtonLabel ? $this->editButtonLabel : $this->strLabel);
    }


    /**
     * Get records
     * @return \Database_Result
     */
    public function getRecords()
    {
        return \Database::getInstance()->execute("SELECT * FROM {$this->foreignTable} WHERE " . $this->getForeignTableCondition() . " AND tstamp>0" . $this->getOrderBy());
    }


    /**
     * Get header fields
     * @return array()
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

                $arrHeaderFields[] = Format::dcaLabel($this->foreignTable, $field);
            }
        }

        return $arrHeaderFields;
    }


    /**
     * Get ORDER BY statement
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
     * @return string
     */
    public function getForeignTableCondition()
    {
        $blnDynamicPtable = (bool) $GLOBALS['TL_DCA'][$this->foreignTable]['config']['dynamicPtable'];

        return "{$this->foreignField}={$this->currentRecord}" . ($blnDynamicPtable ? " AND ptable='{$this->strTable}'" : '');
    }


    /**
     * Handle the AJAX actions
     * @param string
     * @param \DataContainer
     */
    public function handleAjaxActions($strAction, \DataContainer $dc)
    {
        if ($strAction == 'reloadDcaWizard') {
            $intId = \Input::get('id');
            $strField = $strFieldName = \Input::post('name');

            // Handle the keys in "edit multiple" mode
            if (\Input::get('act') == 'editAll') {
                $intId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
                $strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
            }

            // Validate the request data
            if ($GLOBALS['TL_DCA'][$dc->table]['config']['dataContainer'] == 'File') {

                // The field does not exist
                if (!array_key_exists($strField, $GLOBALS['TL_CONFIG'])) {
                    $this->log('Field "' . $strField . '" does not exist in the global configuration', 'Ajax executePostActions()', TL_ERROR);
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

            } elseif (\Database::getInstance()->tableExists($dc->table)) {

                // The field does not exist
                if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField])) {
                    $this->log('Field "' . $strField . '" does not exist in table "' . $dc->table . '"', 'Ajax executePostActions()', TL_ERROR);
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $objRow = \Database::getInstance()->prepare("SELECT id FROM " . $dc->table . " WHERE id=?")
                    ->execute($intId);

                // The record does not exist
                if ($objRow->numRows < 1) {
                    $this->log('A record with the ID "' . $intId . '" does not exist in table "' . $dc->table . '"', 'Ajax executePostActions()', TL_ERROR);
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }
            }

            $strClass = $GLOBALS['BE_FFL']['dcaWizard'];
            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField];
            $objWidget = new $strClass($strClass::getAttributesFromDca($arrData, $strFieldName, null, $strField, $dc->table, $dc));

            header('Content-Type: text/html; charset=' . $GLOBALS['TL_CONFIG']['characterSet']);
            echo $objWidget->generate();
            exit;
        }
    }
}
