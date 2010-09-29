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
 * @copyright  Winans Creative 2009, Intelligent Spark 2010, iserv.ch GmbH 2010
 * @author     Yanick Witschi <yanick.witschi@certo-net.ch>
 * @author     Andreas Schempp <andreas@schempp.ch>
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
		
		return '
<div id="ctrl_' . $this->strId . '" class="dcawizard"><p class="tl_gerror">Your browser does not support javascript. Please use <a href="' . $this->addToUrl('act=&table='.$this->foreignTable) . '">the regular backend</a> to manage data.</div>
<script type="text/javascript">
<!--//--><![CDATA[//><!--
window.addEvent(\'domready\',function(){
	new dcaWizard(\'ctrl_' . $this->strId . '\', {baseURL: \'' . $this->Environment->base . $this->Environment->script . '?do='.$this->Input->get('do').'&table='.$this->foreignTable.'&id='.$this->Input->get('id') . '\', referer: \'' . $this->getReferer() . '\'});
});
//--><!]]>
</script>';
	}
}

