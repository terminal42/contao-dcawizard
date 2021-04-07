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
 * Provides helper methods for the DcaWizard widget
 *
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DcaWizardHelper
{
    /**
     * Handle the AJAX actions
     *
     * @param string         $strAction
     * @param \DataContainer $dc
     */
    public function handleAjaxActions($strAction, \DataContainer $dc)
    {
        if ('reloadDcaWizard' === $strAction) {
            $intId    = \Input::get('id');
            $strField = $strFieldName = \Input::post('name');

            // Handle the keys in "edit multiple" mode
            if ('editAll' === \Input::get('act')) {
                $intId    = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
                $strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
            }

            // Validate the request data
            if ('File' === $GLOBALS['TL_DCA'][$dc->table]['config']['dataContainer']) {
                // The field does not exist
                if (!array_key_exists($strField, $GLOBALS['TL_CONFIG'])) {
                    \System::log(
                        'Field "' . $strField . '" does not exist in the global configuration',
                        __METHOD__,
                        TL_ERROR
                    );

                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

            } elseif (\Database::getInstance()->tableExists($dc->table)) {
                // The field does not exist
                if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField])) {
                    \System::log(
                        'Field "' . $strField . '" does not exist in table "' . $dc->table . '"',
                        __METHOD__,
                        TL_ERROR
                    );

                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $objRow = \Database::getInstance()
                    ->prepare('SELECT id FROM ' . $dc->table . ' WHERE id=?')
                    ->execute($intId)
                ;

                // The record does not exist
                if (!$objRow->numRows) {
                    \System::log(
                        'A record with the ID "' . $intId . '" does not exist in table "' . $dc->table . '"',
                        __METHOD__,
                        TL_ERROR
                    );

                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $dc->intId = (int) $objRow->id;
            }

            /** @var \Widget $strClass */
            $strClass = $GLOBALS['BE_FFL']['dcaWizard'];
            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField];

            // Support classes extending DcaWizard
            if ($ajaxClass = \Input::post('class')) {
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

            /** @var \Widget $objWidget */
            $objWidget = new $strClass(
                $strClass::getAttributesFromDca($arrData, $strFieldName, null, $strField, $dc->table, $dc)
            );

            header('Content-Type: text/html; charset=' . $GLOBALS['TL_CONFIG']['characterSet']);
            echo $objWidget->generate();
            exit;
        }
    }

    /**
     * On load callback. Provide a fix to the popup referer (see #15)
     */
    public function onLoadCallback()
    {
        if (!\Input::get('dcawizard') || 'edit' !== \Input::get('act')) {
            return;
        }

        if (version_compare(VERSION, '4.0', '>')) {
            $session = \System::getContainer()->get('session')->getBag('contao_backend')->get('popupReferer');
        } else {
            $session = \Session::getInstance()->get('popupReferer');
        }

        if (!is_array($session)) {
            return;
        }

        list($table, $id) = explode(':', \Input::get('dcawizard'));

        // Use the current URL without (act and id parameters) as referefer
        $url = \Haste\Util\Url::removeQueryString(['act', 'id'], \Environment::get('request'));
        $url = \Haste\Util\Url::addQueryString('id=' . $id, $url);

        // Replace the last referer value with the correct link
        end($session);
        $session[key($session)]['current'] = $url;

        \Session::getInstance()->set('popupReferer', $session);
    }

    /**
     * On delete callback. Fix the popup referer when deleting the records directly
     * inside the edit form of the source table.
     */
    public function onDeleteCallback()
    {
        if (!\Input::get('dcawizard_operation')) {
            return;
        }

        if (version_compare(VERSION, '4.0', '>')) {
            $session = \System::getContainer()->get('session')->getBag('contao_backend')->get('popupReferer');
        } else {
            $session = \Session::getInstance()->get('popupReferer');
        }

        $referer = \Session::getInstance()->get('dcaWizardReferer');

        if (!is_array($session) || !$referer) {
            return;
        }

        // Replace the last referer value with the correct link
        end($session);
        $session[key($session)]['current'] = $referer;

        \Session::getInstance()->set('popupReferer', $session);
    }

    /**
     * Load the data container
     *
     * @param string $dcaTable
     */
    public function loadDataContainer($dcaTable)
    {
        if (!\Input::get('dcawizard')) {
            return;
        }

        list($table) = explode(':', \Input::get('dcawizard'));

        // Register a delete callback
        if ($table === $dcaTable) {
            $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = ['DcaWizardHelper', 'onLoadCallback'];
            $GLOBALS['TL_DCA'][$table]['config']['ondelete_callback'][] = ['DcaWizardHelper', 'onDeleteCallback'];
        }
    }
}
