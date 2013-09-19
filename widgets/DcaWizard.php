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

namespace Contao;


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
            $this->import($this->foreignTableCallback[0]);
            $this->foreignTable = $this->{$this->foreignTableCallback[0]}->{$this->foreignTableCallback[1]}();
        }

        if ($this->foreignTable != '') {
            $this->loadDataContainer($this->foreignTable);
            $this->loadLanguageFile($this->foreignTable);
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
            // very special case: these classes are imported and must not be added to arrData
            case 'Isotope':
            case 'dcaWizard':
                $this->$strKey = $varValue;
                break;
            case 'value':
                $this->varValue = $varValue;
                break;
            case 'mandatory':
                $this->arrConfiguration[$strKey] = $varValue ? true : false;
                break;
            case 'foreignTable':
                $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'] = $varValue;
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

            case 'foreignTable':
                return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'];

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
            $this->import('Database');
            $objRecords = $this->Database->execute("SELECT * FROM {$this->foreignTable} WHERE pid={$this->currentRecord}");

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
        $strReturn = '<div id="ctrl_' . $this->strId . '" class="dcawizard">
<div class="selector_container">';

        // Add assets
        $GLOBALS['TL_JAVASCRIPT']['dcawizard'] = 'system/modules/dcawizard/assets/dcawizard.min.js';

        // Get the available records
        $objRecords = \Database::getInstance()->execute("SELECT * FROM {$this->foreignTable} WHERE pid={$this->currentRecord}" . ($this->listField ? " ORDER BY {$this->listField}" : ""));

        if ($objRecords->numRows) {
            // Use the callback to generate the list
            if (is_array($this->listCallback) && count($this->listCallback)) {
                $this->import($this->listCallback[0]);
                $strReturn .= $this->{$this->listCallback[0]}->{$this->listCallback[1]}($objRecords, $this->strId);
            } else {
                $strReturn .= '<ul id="sort_' . $this->strId . '">';

                // Generate the records
                while ($objRecords->next()) {
                    $strReturn .= $this->generateListRow($objRecords);
                }

                $strReturn .= '</ul>';
            }
        }

        return $strReturn . '
<p><a href="contao/main.php?do='.\Input::get('do').'&amp;table='.$this->foreignTable.'&amp;field='.$this->strField.'&amp;id='.$this->currentRecord.'&amp;popup=1&amp;rt='.REQUEST_TOKEN.'" class="tl_submit" onclick="Backend.getScrollOffset();DcaWizard.openModalWindow({\'width\':765,\'title\':\''.specialchars($this->strLabel).'\',\'url\':this.href,\'id\':\''.$this->strId.'\'});return false">'.$GLOBALS['TL_LANG']['MSC']['changeSelection'].'</a></p>
</div>
</div>';
    }


    /**
     * Generate the list row and return it as HTML string
     * @param object
     * @return string
     */
    protected function generateListRow($objRecords)
    {
        return '<li>' . ($this->listField ? ($objRecords->{$this->listField} . ' ') : '') . '(ID: ' . $objRecords->id . ')</li>';
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

                $objRow = \Database::getInstance()->prepare("SELECT * FROM " . $dc->table . " WHERE id=?")
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

            echo $objWidget->generate();
            exit;
        }
    }
}
