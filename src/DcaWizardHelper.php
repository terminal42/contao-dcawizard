<?php

declare(strict_types=1);

namespace Terminal42\DcaWizardBundle;

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Database;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Input;
use Contao\System;
use Contao\Widget;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Provides helper methods for the DcaWizard widget.
 *
 * @author Yanick Witschi <yanick.witschi@terminal42.ch>
 */
class DcaWizardHelper
{
    /**
     * Handle the AJAX actions.
     */
    public function handleAjaxActions($strAction, DataContainer $dc): void
    {
        if ('reloadDcaWizard' === $strAction) {
            $intId = Input::get('id');
            $strField = $strFieldName = Input::post('name');

            // Handle the keys in "edit multiple" mode
            if ('editAll' === Input::get('act')) {
                $intId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
                $strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
            }

            // Validate the request data
            if ('File' === $GLOBALS['TL_DCA'][$dc->table]['config']['dataContainer']) {
                // The field does not exist
                if (!\array_key_exists($strField, $GLOBALS['TL_CONFIG'])) {
                    throw new ResponseException(new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST));
                }
            } elseif (Database::getInstance()->tableExists($dc->table)) {
                // The field does not exist
                if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField])) {
                    throw new ResponseException(new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST));
                }

                $objRow = Database::getInstance()
                    ->prepare('SELECT id FROM '.$dc->table.' WHERE id=?')
                    ->execute($intId)
                ;

                // The record does not exist
                if (!$objRow->numRows) {
                    throw new ResponseException(new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST));
                }

                $dc->intId = (int) $objRow->id;
            }

            /** @var Widget $strClass */
            $strClass = $GLOBALS['BE_FFL']['dcaWizard'];
            $arrData = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField];

            // Support classes extending DcaWizard
            if ($ajaxClass = Input::post('class', true)) {
                $ajaxClass = base64_decode($ajaxClass, true);

                if (\in_array($ajaxClass, $GLOBALS['BE_FFL'], true)) {
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

            throw new ResponseException(new Response($objWidget->generate()));
        }
    }

    /**
     * On load callback. Provide a fix to the popup referer (see #15).
     */
    public function onLoadCallback(): void
    {
        if (Input::get('dcawizard_operation') && !Input::get('act')) {
            throw new ResponseException(new Response("<script>window.top.postMessage('closeModal', '*')</script>"));
        }

        if (!Input::get('dcawizard') || 'edit' !== Input::get('act')) {
            return;
        }

        /** @var RequestStack $requestStack */
        $requestStack = System::getContainer()->get('request_stack');

        if (($request = $requestStack->getCurrentRequest()) === null) {
            return;
        }

        $session = $request->getSession();
        $referer = $session->get('popupReferer');

        if (!\is_array($referer)) {
            return;
        }

        [, $id] = explode(':', Input::get('dcawizard'));

        /** @var UrlParser $urlParser */
        $urlParser = System::getContainer()->get(UrlParser::class);

        // Use the current URL without (act and id parameters) as referer
        $url = $urlParser->removeQueryString(['act', 'id'], Environment::get('request'));
        $url = $urlParser->addQueryString('id='.$id, $url);

        // Replace the last referer value with the correct link
        end($referer);
        $referer[key($referer)]['current'] = $url;

        $session->set('popupReferer', $referer);
    }

    /**
     * On delete callback. Fix the popup referer when deleting the records directly
     * inside the edit form of the source table.
     */
    public function onDeleteCallback(): void
    {
        if (!Input::get('dcawizard_operation')) {
            return;
        }

        /** @var RequestStack $requestStack */
        $requestStack = System::getContainer()->get('request_stack');

        if (($request = $requestStack->getCurrentRequest()) === null) {
            return;
        }

        $session = $request->getSession();
        $referer = $session->get('popupReferer');

        /** @var Session $sessionBag */
        $sessionBag = $session->getBag('contao_backend');
        $dcaWizardReferer = $sessionBag->get('dcaWizardReferer');

        if (!\is_array($referer) || !$dcaWizardReferer) {
            return;
        }

        // Replace the last referer value with the correct link
        end($referer);
        $referer[key($referer)]['current'] = $dcaWizardReferer;

        $session->set('popupReferer', $referer);
    }

    public function onButtonCallback(array $buttons, DataContainer $dc): array
    {
        if (!Input::get('dcawizard_operation')) {
            return $buttons;
        }

        if (isset($buttons['saveNclose'])) {
            unset($buttons['saveNclose']);
            $buttons['saveNclosemodal'] = '<button type="submit" name="saveNback" id="saveNback" class="tl_submit" accesskey="c">'.$GLOBALS['TL_LANG']['MSC']['saveNclose'].'</button>';
        }

        return $buttons;
    }

    /**
     * Load the data container.
     */
    public function loadDataContainer(string $dcaTable): void
    {
        if (!Input::get('dcawizard')) {
            return;
        }

        [$table] = explode(':', Input::get('dcawizard'));

        // Register a delete callback
        if ($table === $dcaTable) {
            $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = [self::class, 'onLoadCallback'];
            $GLOBALS['TL_DCA'][$table]['config']['ondelete_callback'][] = [self::class, 'onDeleteCallback'];
            $GLOBALS['TL_DCA'][$table]['edit']['buttons_callback'][] = [self::class, 'onButtonCallback'];
        }
    }
}
