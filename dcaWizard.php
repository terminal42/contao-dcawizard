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
	* Current DCA record id
	* @var string 
	*/
	protected $intId;

	/**
	* Submit user input
	* @var boolean 
	*/
	protected $blnSubmitInput = true;

	/**
	* Template
	* @var string 
	*/
	protected $strTemplate = 'be_widget';
	
	/**
	* Current table
	* @var string 
	*/
	protected $strCurTable = '';
	
	/**
	* Current field
	* @var string 
	*/
	protected $strCurField = '';
	
 
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
			case 'tableColumns':
				$config = $GLOBALS['TL_DCA'][$this->strCurTable]['fields'][$this->strCurField]['tableColumns'];
				if(isset($config) && is_array($config))
				{
					return $config;
				}
				break;
				
			case 'foreignDCA':
				$config = $GLOBALS['TL_DCA'][$this->strCurTable]['fields'][$this->strCurField]['foreignTable'];
				if(isset($config))
				{
					return $config;
				}
				break;
				
			case 'setPID':
				$config = $GLOBALS['TL_DCA'][$this->strCurTable]['fields'][$this->strCurField]['setPID'];
				if(isset($config))
				{
					return $config;
				}
				break;

			default:
				parent::__get($strKey);
				break;
		}
	}


	/**
	* Generate the widget, add a hidden input field and return it as string
	* @return string 
	*/
	public function generate()
	{
		$this->intId		= $this->Input->get('id');
		$this->strCurTable	= $this->arrConfiguration['strTable'];
		$this->strCurField	= $this->arrConfiguration['strField'];
		$pid				= ($this->setPID) ? $this->setPID : $this->intId;
		
		// add JS and CSS
		$GLOBALS['TL_JAVASCRIPT']['dcaWizard']	= 'system/modules/dcawizard/html/dcawizard.js';
		$GLOBALS['TL_CSS']['dcaWizard']			= 'system/modules/dcawizard/html/dcawizard.css|screen';
		
		
		$return  = $this->generateWidget();
		$return .= sprintf('<input type="hidden" name="%s" id="ctrl_%s" value="%s" />',$this->strName,$this->strId,$pid);
		return $return;
	}
	

	/**
	* Generate the widget and return it as string
	* @return string 
	*/
	protected function generateWidget()
	{
		$this->import('Database');
		$objTemplate = new BackendTemplate('dcaWizard');

		// table and field
		$objTemplate->dcaTable			= $this->strCurTable;
		$objTemplate->dcaField			= $this->strCurField;
		
		$this->loadDataContainer($this->foreignDCA);
		$this->loadLanguageFile($this->foreignDCA);
		
		$objTemplate->foreignDCA		= $this->foreignDCA;
		$objTemplate->dcaPalette		= $GLOBALS['TL_DCA'][$this->foreignDCA]['palettes']['dcawizard'];
		
		// add base url
		$objTemplate->baseUrl			= $this->Environment->script . '?do=' . $this->Input->get('do') . '&table=' . $this->foreignDCA;
		
		// theme
		$objTemplate->theme				= $this->getTheme();
		
		// language
		$objTemplate->failureMsg		= $GLOBALS['TL_LANG']['dcaWizard']['failureMsg'];
		$objTemplate->deleteConfirmMsg	= $GLOBALS['TL_LANG']['dcaWizard']['deleteConfirmMsg'];
		$objTemplate->closeLbl			= $GLOBALS['TL_LANG']['dcaWizard']['closeLbl'];
		$objTemplate->noItemsYetMsg		= $GLOBALS['TL_LANG']['MSC']['noResult'];
		$objTemplate->addItemMsg		= $GLOBALS['TL_LANG'][$this->foreignDCA]['new'][0];
		$objTemplate->deleteItemAlt		= $GLOBALS['TL_LANG'][$this->foreignDCA]['delete'][0];
		$objTemplate->editItemAlt		= $GLOBALS['TL_LANG'][$this->foreignDCA]['edit'][0];
		$objTemplate->saveLbl			= $GLOBALS['TL_LANG']['MSC']['save'];
		$objTemplate->saveNcloseLbl		= $GLOBALS['TL_LANG']['MSC']['saveNclose'];
		$objTemplate->cancelLbl			= $GLOBALS['TL_LANG']['MSC']['cancelBT'];
		$objTemplate->saveNcreateLbl	= $GLOBALS['TL_LANG']['MSC']['saveNcreate'];
		
		// parent id
		$pid = ($this->setPID) ? $this->setPID : $this->intId;
		$objTemplate->parentId = $pid;
		
		$query = "SELECT * FROM ".$this->foreignDCA." WHERE pid=?";
		
		// sorting
		if(is_array($GLOBALS['TL_DCA'][$this->foreignDCA]['list']['sorting']['fields']))
		{
			$query .= ' ORDER BY ' . implode(', ', $GLOBALS['TL_DCA'][$this->foreignDCA]['list']['sorting']['fields']);
		}
		// entries
		$objEntries   = $this->Database->prepare($query)
									   ->execute($pid);
		
		$objTemplate->noItemsYet = ($objEntries->numRows < 1) ? true : false;
		
		// prepare the data
		$arrItemsOld = array();
		$arrEntries = $objEntries->fetchAllAssoc();
		
		foreach($arrEntries as $entry)
		{
			$arrItemsOld[] = $this->prepareForOutput($entry);
		}

		// set items to template
		$arrItemsNew = array();
		$currentDbId = 0;
		foreach($arrItemsOld as $k => $arrColumns)
		{
			foreach($arrColumns as $column => $value)
			{
				if($column == 'id')
				{
					$currentDbId = $value;
				}
				
				if(in_array($column, $this->tableColumns))
				{
					$arrItemsNew[$currentDbId][$column] = $value;
				}
			}
		}
		$objTemplate->arrItems = $arrItemsNew;
		
		// set tablecolumns
		$arrTableColumns = array();
		foreach($this->tableColumns as $name)
		{
			$arrTableColumns[$name] = $GLOBALS['TL_LANG'][$this->foreignDCA][$name][0];
		}
		
		// add an empty value add the bottom for the empty td for the operations column
		$arrTableColumns['operations'] = '';
		
		$objTemplate->tableColumns = $arrTableColumns;
		
		// set colspan (+1 because of the operations)
		$objTemplate->colspan = count($arrTableColumns) + 1;
		
		return $objTemplate->parse();
	}
	
	
	/**
	* Convert the data stored in the array in the desired output format
	* @param array
	* @return array	
	*/
	protected function prepareForOutput($row)
	{
		// Get all fields
		$fields = array_keys($row);
		
		$allowedFields = array('id', 'pid', 'sorting', 'tstamp');

		if (is_array($GLOBALS['TL_DCA'][$this->foreignDCA]['fields']))
		{
			$allowedFields = array_unique(array_merge($allowedFields, array_keys($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'])));
		}

		// Use the field order of the DCA file
		$fields = array_intersect($allowedFields, $fields);
		
		
		// Show all allowed fields
		foreach ($fields as $i)
		{
			if (!in_array($i, $allowedFields) || $GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['inputType'] == 'password' || $GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['doNotShow'] || $GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['hideInput'])
			{
				continue;
			}

			$value = deserialize($row[$i]);

			// Get field value
			if (strlen($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['foreignKey']))
			{
				$temp = array();
				$chunks = explode('.', $GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['foreignKey']);

				foreach ((array) $value as $v)
				{
					$objKey = $this->Database->prepare("SELECT " . $chunks[1] . " FROM " . $chunks[0] . " WHERE id=?")
											 ->limit(1)
											 ->execute($v);

					if ($objKey->numRows)
					{
						$temp[] = $objKey->$chunks[1];
					}
				}

				$row[$i] = implode(', ', $temp);
			}

			elseif (is_array($value))
			{
				foreach ($value as $kk=>$vv)
				{
					if (is_array($vv))
					{
						$vals = array_values($vv);
						$value[$kk] = $vals[0].' ('.$vals[1].')';
					}
				}

				$row[$i] = implode(', ', $value);
			}

			elseif ($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['rgxp'] == 'date')
			{
				$row[$i] = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $value);
			}

			elseif ($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['rgxp'] == 'time')
			{
				$row[$i] = $this->parseDate($GLOBALS['TL_CONFIG']['timeFormat'], $value);
			}

			elseif ($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['rgxp'] == 'datim' || in_array($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['flag'], array(5, 6, 7, 8, 9, 10)) || $i == 'tstamp')
			{
				$row[$i] = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $value);
			}

			elseif ($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['multiple'])
			{
				$row[$i] = strlen($value) ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
			}

			elseif ($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['inputType'] == 'textarea' && ($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['allowHtml'] || $GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['preserveTags']))
			{
				$row[$i] = specialchars($value);
			}

			elseif (is_array($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['reference']))
			{
				$row[$i] = isset($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['reference'][$row[$i]]) ? ((is_array($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['reference'][$row[$i]])) ? $GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['reference'][$row[$i]][0] : $GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['reference'][$row[$i]]) : $row[$i];
			}
			
			elseif ($GLOBALS['TL_DCA'][$this->foreignDCA]['fields'][$i]['eval']['encrypt'])
			{
				$this->import('Encryption');
				$row[$i] = $this->Encryption->decrypt($value);
			}
			
			// HOOK: Call a hook to allow third-party developers to prepare their content for the dcaWizard output
			// params: DCA array (array), value (string)
			if (isset($GLOBALS['TL_HOOKS']['dcaWizard']['prepareForOutput']) && is_array($GLOBALS['TL_HOOKS']['dcaWizard']['prepareForOutput']))
			{
				foreach ($GLOBALS['TL_HOOKS']['dcaWizard']['prepareForOutput'] as $callback)
				{
					$this->import($callback[0]);
					$row[$i] = $this->$callback[0]->$callback[1]($row, $value);
				}
			}
		}
		
		return $row;
	}
	
	
	/**
	* Ajax calls
	* @param string 
	* @param object 
	*/
	public function generateAjax($strAction, $dc)
	{
		if($strAction == 'dcaWizardAjaxCall')
		{
			$this->strCurTable = $this->Input->post('dcaTable');
			$this->strCurField = $this->Input->post('dcaField');
			$strType = $this->Input->post('type');
			
			switch($strType)
			{			
				case 'addItem':
					echo $this->addItem();
					break;
				case 'deleteItem':
					echo $this->deleteItem();
			}	
		}
	}
	

	/**
	* Add the CSS class to the fields from the dcawizard-palette so we can select them with JS later on.
	* Thanks to Isotope and Andreas Schempp for his request for the loadDataContainer hook! That's the only way this wizard can work.
	*
	* @todo do not always inject the css class, especially when it has already been added (second call to loadDataContainer)
	* @param string
	*/	
	public function injectCssClass($strName)
	{
//		if($this->Input->get('do') == 'dcaWizard')
//		{
			$table = $this->Input->get('table');
			// Before we generate any DCA file we need to add the CSS class "dcaWizardField" to all the fields we need (defined in the DCA palette called "dcawizard")
			$palette = $GLOBALS['TL_DCA'][$table]['palettes']['dcawizard'];
			$arrFields = trimsplit(',', $palette);
			foreach($arrFields as $field)
			{
				$GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['tl_class'] .= ' dcaWizardField';
			}
//		}
	}
	

	/**
	* Add a new item --> called by Ajax
	* @return integer
	*/
	protected function addItem()
	{
		$this->import('Database');
		$pid = $this->Input->post('parentId');
		
		// Adding a new row and get the id
		// TODO: bad design - better idea?
		$this->Database->prepare("INSERT INTO ".$this->foreignDCA." (pid) VALUES(?)")
					   ->execute($pid);
		$objNewRow = $this->Database->execute("SELECT max(id) AS id FROM ".$this->foreignDCA);
		return $objNewRow->id;
	}
	
	
	/**
	* Delete a item --> called by Ajax
	* @return integer
	*/
	protected function deleteItem()
	{
		$this->import('Database');
		$id = $this->Input->post('entryId');
		
		$this->Database->prepare("DELETE FROM ".$this->foreignDCA." WHERE id=?")
					   ->execute($id);
	}
}

