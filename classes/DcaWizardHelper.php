<?php

/**
 * dcawizard extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       https://github.com/terminal42/contao-dcawizard
 */

use Contao\Database;
use Contao\DataContainer;
use Contao\Input;
use Contao\Widget;

/**
 * Provides helper methods for the DcaWizard widget
 *
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DcaWizardHelper
{
    /**
     * Handle the AJAX actions
     */
    public function handleAjaxActions($strAction, DataContainer $dc)
    {
        if ('reloadDcaWizard' === $strAction) {
            $intId    = Input::get('id');
            $strField = $strFieldName = Input::post('name');

            // Handle the keys in "edit multiple" mode
            if ('editAll' === Input::get('act')) {
                $intId    = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
                $strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
            }

            // Validate the request data
            if ('File' === $GLOBALS['TL_DCA'][$dc->table]['config']['dataContainer']) {
                // The field does not exist
                if (!array_key_exists($strField, $GLOBALS['TL_CONFIG'])) {
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

            } elseif (Database::getInstance()->tableExists($dc->table)) {
                // The field does not exist
                if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField])) {
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $objRow = Database::getInstance()
                    ->prepare('SELECT id FROM ' . $dc->table . ' WHERE id=?')
                    ->execute($intId)
                ;

                // The record does not exist
                if (!$objRow->numRows) {
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $dc->intId = (int) $objRow->id;
            }

            /** @var Widget $strClass */
            $strClass = $GLOBALS['BE_FFL']['dcaWizard'];
            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField];

            // Support classes extending DcaWizard
            if ($ajaxClass = Input::post('class', true)) {
                $ajaxClass = base64_decode($ajaxClass);

                if (in_array($ajaxClass, $GLOBALS['BE_FFL'], true)) {
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

            /** @var Widget $objWidget */
            $objWidget = new $strClass(
                $strClass::getAttributesFromDca($arrData, $strFieldName, null, $strField, $dc->table, $dc)
            );

            header('Content-Type: text/html; charset=' . \Contao\Config::get('characterSet'));
            echo $objWidget->generate();
            exit;
        }
    }
}
