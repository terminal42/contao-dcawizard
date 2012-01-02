<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Yanick Witschi <yanick.witschi@certo-net.ch>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @author     Christian de la Haye <service@delahaye.de>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class dcaWizard extends Widget
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
	public function __construct($arrAttributes=false)
	{
		parent::__construct($arrAttributes);

		if (is_array($this->foreignTableCallback) && count($this->foreignTableCallback))
		{
			$this->import($this->foreignTableCallback[0]);
			$this->foreignTable = $this->{$this->foreignTableCallback[0]}->{$this->foreignTableCallback[1]}();
		}

		if ($this->foreignTable != '')
		{
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
		switch($strKey)
		{
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
		switch($strKey)
		{
			case 'foreignTable':
				return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTable'];
				break;

			case 'foreignTableCallback':
				return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['foreignTableCallback'];
				break;

			default:
				return parent::__get($strKey);
				break;
		}
	}


	/**
	 * Validate input
	 */
	public function validate()
	{
		if ($this->mandatory)
		{
			$this->import('Database');
			$objRecords = $this->Database->execute("SELECT * FROM {$this->foreignTable} WHERE pid={$this->currentRecord}");

			if (!$objRecords->numRows && $this->strLabel == '')
			{
				$this->addError($GLOBALS['TL_LANG']['ERR']['mdtryNoLabel']);
			}
			elseif (!$objRecords->numRows)
			{
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
		// add JS and CSS
		$GLOBALS['TL_JAVASCRIPT']['dcaWizard']	= 'system/modules/dcawizard/html/dcawizard.js';
		$GLOBALS['TL_CSS']['dcaWizard']			= 'system/modules/dcawizard/html/dcawizard.css|screen';

		// inject JS in HTML5 style from Contao 2.10
		$strScriptBegin = (version_compare(VERSION, '2.9', '>') ? '<script>' : '<script type="text/javascript">
<!--//--><![CDATA[//><!--');
		$strScriptEnd = (version_compare(VERSION, '2.9', '>') ? '</script>' : '//--><!]]>
</script>');

		return '
<div id="ctrl_' . $this->strId . '" class="dcawizard"><p class="tl_gerror">Your browser does not support javascript. Please use <a href="' . ampersand($this->addToUrl('act=&table='.$this->foreignTable)) . '">the regular backend</a> to manage data.</div>
'.$strScriptBegin.'
window.addEvent(\'domready\',function(){
	new dcaWizard(\'ctrl_' . $this->strId . '\', {baseURL: \'' . $this->Environment->base . $this->Environment->script . '?do='.$this->Input->get('do').'&table='.$this->foreignTable.'&id='.$this->Input->get('id') . '\', referer: \'' . $this->getReferer() . '\'});
});
'.$strScriptEnd;
	}


	/**
	 * Stop Contao from deleting new records if it's an ajax request
	 */
	public function doNotReviseTable($table, &$new_records, $parent_table, $child_tables)
	{
		if ($this->Input->get('dcaWizard'))
		{
			$new_records = array();
		}

		return false;
	}


	/**
	 * We need to disable ajax actions for DC_Table
	 */
	public function preventAjaxActions($strAction)
	{
		if ($strAction == 'dcaWizard')
		{
			$this->Environment->isAjaxRequest = false;
		}
	}
}