<?php

/**
 * dcawizard extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       https://github.com/terminal42/contao-dcawizard
 */

/**
 * Class DcaWizardHelper
 *
 * Provides helper methods for the DcaWizard widget
 *
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DcaWizardHelper
{
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
                    \System::log('Field "' . $strField . '" does not exist in the global configuration', 'Ajax executePostActions()', TL_ERROR);
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

            } elseif (\Database::getInstance()->tableExists($dc->table)) {

                // The field does not exist
                if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField])) {
                    \System::log('Field "' . $strField . '" does not exist in table "' . $dc->table . '"', 'Ajax executePostActions()', TL_ERROR);
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $objRow = \Database::getInstance()->prepare("SELECT id FROM " . $dc->table . " WHERE id=?")
                    ->execute($intId);

                // The record does not exist
                if (!$objRow->numRows) {
                    \System::log('A record with the ID "' . $intId . '" does not exist in table "' . $dc->table . '"', 'Ajax executePostActions()', TL_ERROR);
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }
            }

            $strClass = $GLOBALS['BE_FFL']['dcaWizard'];

            // Support classes extending DcaWizard
            if ($ajaxClass = \Input::post('class')) {
                $ajaxClass = base64_decode($ajaxClass);

                if (in_array($ajaxClass, $GLOBALS['BE_FFL'])) {
                    try {
                        $reflection = new ReflectionClass($ajaxClass);
                        if ($reflection->isSubclassOf('DcaWizard')) {
                            $strClass = $ajaxClass;
                        }
                    } catch (\Exception $e) {
                        // silent fallback to default class
                    }
                }
            }

            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField];
            $objWidget = new $strClass($strClass::getAttributesFromDca($arrData, $strFieldName, null, $strField, $dc->table, $dc));

            header('Content-Type: text/html; charset=' . $GLOBALS['TL_CONFIG']['characterSet']);
            echo $objWidget->generate();
            exit;
        }
    }
}
