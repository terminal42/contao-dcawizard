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
        if (is_array($this->foreignTableCallback) && count($this->foreignTableCallback)) {
            $objCallback = \System::importStatic($this->foreignTableCallback[0]);
            $this->foreignTable = $objCallback->{$this->foreignTableCallback[1]}();
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
        $arrHeaderFields = $this->headerFields;
        $strOrderBy = '';
        $orderFields = $GLOBALS['TL_DCA'][$this->foreignTable]['list']['sorting']['fields'];

        if ($this->orderField) {
            $strOrderBy = ' ORDER BY ' . $this->orderField;
        } elseif (!empty($orderFields) && is_array($orderFields)) {
            $strOrderBy = ' ORDER BY ' . implode(',', $orderFields);
        }

        $strReturn = '<div id="ctrl_' . $this->strId . '" class="dcawizard">
<div class="selector_container">';

        // Add assets
        $GLOBALS['TL_JAVASCRIPT']['dcawizard'] = sprintf('system/modules/dcawizard/assets/dcawizard%s.js', (($GLOBALS['TL_CONFIG']['debugMode']) ? '' : '.min'));

        // Get the available records
        $objRecords = \Database::getInstance()->execute("SELECT * FROM {$this->foreignTable} WHERE " . $this->getForeignTableCondition() . " AND tstamp>0" . $strOrderBy);

        // Automatically get the header fields
        if (null === $varCallback && (!is_array($arrHeaderFields) || empty($arrHeaderFields))) {
            foreach ($this->fields as $field) {
                if ($field == 'id') {
                    $arrHeaderFields[] = 'ID';
                    continue;
                }

                $arrHeaderFields[] = Format::dcaLabel($this->foreignTable, $field);
            }
        }

        if ($objRecords->numRows) {
            // Use the callback to generate the list
            if (is_array($varCallback)) {
                $strReturn .= \System::importStatic($varCallback[0])->{$varCallback[1]}($objRecords, $this->strId);
            } elseif (is_callable($varCallback)) {
                $strReturn .= $varCallback($objRecords, $this->strId);

            } else {
                $strReturn .= '<table class="tl_listing showColumns"><thead>';

                // Add header fields
                foreach ($arrHeaderFields as $field) {
                    $strReturn .= '<td class="tl_folder_tlist">' . $field . '</td>';
                }

                $strReturn .='</thead><tbody>';

                // Generate the records
                while ($objRecords->next()) {
                    $strReturn .= '<tr>';

                    foreach ($this->fields as $field) {
                        $strReturn .= '<td class="tl_file_list">' . Format::dcaValue($this->foreignTable, $field, $objRecords->$field) . '</td>';
                    }

                    $strReturn .= '</tr>';
                }

                $strReturn .= '</tbody></table>';
            }
        }

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

        $arrOptions = array
        (
            'width'         => 765,
            'title'         => "'" . specialchars($this->strLabel) . "'",
            'url'           => 'this.href',
            'id'            => "'" . $this->strId . "'",
            'applyLabel'    => "'" . specialchars($this->applyButtonLabel) . "'"
        );

        $strOptions = implode(', ', array_map(function ($v, $k) { return sprintf("'%s':%s", $k, $v); }, $arrOptions, array_keys($arrOptions)));

        return $strReturn . '
<p style="margin-top:9px;">
<a href="contao/main.php?' . ampersand(http_build_query($arrParams)) . '" class="tl_submit" onclick="Backend.getScrollOffset();DcaWizard.openModalWindow({' . $strOptions . '});return false">'.($this->editButtonLabel ? $this->editButtonLabel : $this->strLabel).'</a>
</p>
</div>
</div>';
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

    /**
     * Return SQL WHERE condition for foreign table
     * @return string
     */
    private function getForeignTableCondition()
    {
        $blnDynamicPtable = (bool) $GLOBALS['TL_DCA'][$this->foreignTable]['config']['dynamicPtable'];

        return "{$this->foreignField}={$this->currentRecord}" . ($blnDynamicPtable ? " AND ptable='{$this->strTable}'" : '');
    }
}
