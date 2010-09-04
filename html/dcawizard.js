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
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

 var dcaWizard = new Class({

	Implements: [Options, Request.HTML],

	/* attributes */
	currentRow: {el:null,id:null},
	dcaWizardEditBox: null,
	dcaWizardTable: null,
	objData: {},
	table: null,
	tableRows: null,
	tableColumns: null,
	dcaTable: null,
	dcaField: null,
	dcaPalette: null,
	foreignDCA: null,
	addItemButton: null,
	submitButtonLbl: null,
	ajaxIndicatorState: 'hidden',
	baseUrl: '',
	saveLbl: '',
	saveNcloseLbl: '',
	cancelLbl: '',
	saveNcreateLbl: '',
	closeLbl: '',
	theme: '',
	failureMsg: '',
		
	options:
	{
		dcaWizardEditBox: null,
		dcaWizardTable: null,
		dcaTable: '',
		foreignDCA: '',
		dcaField: '',
		dcaPalette: '',
		deleteConfirmMsg: '',
		parentId: null,
		editItemAlt: null,
		dcaWizardEditBoxWrapper: null,
		submitButtonLbl: null,
		baseUrl: '',
		saveLbl: '',
		saveNcloseLbl: '',
		cancelLbl: '',
		saveNcreateLbl: '',
		closeLbl: '',
		theme: '',
		failureMsg: ''
	},
	initialize: function(options)
	{
		this.setOptions(options);
		
		// set vars
		this.dcaTable					= this.options.dcaTable;
		this.dcaField					= this.options.dcaField;
		this.dcaPalette					= this.options.dcaPalette;
		this.foreignDCA					= this.options.foreignDCA;
		this.dcaWizardEditBox			= $(this.options.dcaWizardEditBox);
		this.dcaWizardTable				= $(this.options.dcaWizardTable).getElement('table');
		this.tableRows					= this.dcaWizardTable.getElements('tr.item');
		this.tableColumns				= this.dcaWizardTable.getElement('thead').getElements('td').get('class');
		this.addItemButton				= this.dcaWizardTable.getElement('.add_item');
		this.dcaWizardEditBoxWrapper	= this.dcaWizardEditBox.getParent();
		this.submitButtonLbl			= this.options.submitButtonLbl;
		this.baseUrl					= this.options.baseUrl;
		this.saveLbl					= this.options.saveLbl;
		this.saveNcloseLbl				= this.options.saveNcloseLbl;
		this.cancelLbl					= this.options.cancelLbl;
		this.saveNcreateLbl				= this.options.saveNcreateLbl;
		this.closeLbl					= this.options.closeLbl;
		this.failureMsg					= this.options.failureMsg;
		this.theme						= this.options.theme;

		// add onclick event to the "add item button/icon"
		this.addItemButton.addEvent('click', function(e)
		{
			e.stop();
			this.addItem();
		}.bind(this));		
		
		// add onclick event to operatons.
		this.addOnClickEventsToOperations();
	},
	
	
	/**
	 * Add onclick event to the operations every item row
	 */
	addOnClickEventsToOperations: function()
	{
		this.tableRows.each( function(el)
		{
			el.getElement('.operations').getElement('.edit').addEvent('click', function(e)
			{
				e.stop();
				this.currentRow.el = el;
				this.currentRow.id = el.get('id').replace('itemId_', '');
				this.showWizard();
			}.bind(this));
			el.getElement('.operations').getElement('.delete').addEvent('click', function(e)
			{
				e.stop();
				this.currentRow.el = el;
				this.currentRow.id = el.get('id').replace('itemId_', '');
				this.deleteItem();
			}.bind(this));
		}.bind(this));
	},
	
	
	/**
	 * Show the wizard for a certain element
	 */
	showWizard: function() 
	{
		// if the user clicks on edit of another row and the wizard is still open, we need to clean that first
		if(this.dcaWizardEditBoxWrapper.getStyle('display') == 'block')
		{
			this.hideWizard();
		}

		this.requestForeignDCA();
	},
	

	/**
	 * Hide wizard
	 */
	hideWizard: function() 
	{
		this.dcaWizardEditBoxWrapper.setStyle('display', 'none');
		this.dcaWizardEditBox.set('html','');
	},
	

	/**
	 * Request a foreign DCA
	 */
	requestForeignDCA: function() 
	{
  		new Request.Mixed({
			url: this.baseUrl + '&act=edit&id=' + this.currentRow.id,
			onRequest: function()
			{
				if(this.ajaxIndicatorState == 'hidden')
				{
					AjaxRequest.displayBox('Loading...');
					this.ajaxIndicatorState = 'visible';				
				}
			}.bind(this),
			onSuccess: function(txt, xml, js)
			{
				// prepare the table and edit box
 				this.prepareTableAndEditBox();
				
				// get the <div id="container">
				var container = this.filterContainer(txt);
				
				
				if (this.prepareHTML(container))
				{
					// execute the javascript
					if (js)
					{
						$exec(js);
					}
					
					// add onchange events to all the fields
					this.addOnChangeEventsToFields();
					
					// preset the fields if they have already been edited --> cache
					this.setCachedValues();
				}
				else
				{
					alert('Error1');
				}
				
				AjaxRequest.hideBox();
				this.ajaxIndicatorState = 'hidden';
				
   			}.bind(this),
			onFailure: function()
			{
				this.showErrorBox(this.failureMsg);
			}.bind(this)
		}).send();
	},
	
	
	/**
	 * Filter any html document for the <div id="container"> and return it as element
	 * @param string
	 * @return element
	 */
	filterContainer: function(html) 	
	{
		var container = null;
		var elements = Elements.from(html);
		
 		elements.each( function(el)
		{
			if(el.get('id') == 'container' && el.get('tag') == 'div')
			{
				container = el;
			}
		});
		
		return container;
	},

	/**
	 * Prepare the table and the editbox for the response inject
	 */
	prepareTableAndEditBox: function() 
	{
		// if there is no item yet, we remove the noitem tr
		noitemtr = this.dcaWizardTable.getElement('tr.noitem');
		if(noitemtr)
			noitemtr.destroy();
		
		// set row active, delete the other actives and show wizard
		this.tableRows.each( function(row)
		{
			row.removeClass('active');
		});
		this.currentRow.el.addClass('active');
		
		this.dcaWizardEditBoxWrapper.setStyle('display', 'block');
	},
	
	
	/**
	 * Prepare the HTML received by an ajax call as we only need certain fields and inputs
	 * @param element
	 * @return element
	 */
	prepareHTML: function(container) 
	{
//		var form = container.getElement(('form#' + this.foreignDCA));

		var form = null;
		var self = this;
		
		// search the form
 		container.getElements('form').each( function(el)
		{
			if(el.get('id') == self.foreignDCA)
			{
				form = el;
			}
		});
		
		if (!form)
		{
			return false;
		}
		
		// now we search for all the fields we want to display.
		var fields = form.getElements('div.dcaWizardField');
		
		if (!fields)
		{
			return false;
		}
		
		// We need to dispose them from the DOM as we empty() later on and without disposing we'd kill everything
		fields.dispose();
		
		// now we need to build a hidden input field, as we can not use the given one because our palette may not be the one of the foreign dca
		var formfields = new Element('input', {
			'type': 'hidden',
			'value': this.dcaPalette,
			'name': 'FORM_FIELDS[]'
		});
				
		// form submit can be used as given
		var formsubmit		= form.getElement('input[name=FORM_SUBMIT]').dispose();
		
		// generate the buttons
		var buttons = this.generateButtons(form);		
		
		// now we have everything we needed, so we empty the form and inject the things we need.
		form.empty();
		fields.inject(form, 'bottom');
		formsubmit.inject(form, 'bottom');
		formfields.inject(form, 'bottom');
		buttons.inject(form, 'bottom');
		
		form.inject(this.dcaWizardEditBox);
		
		return true;
	},

	
	/**
	 * Generate buttons
	 * @param element
	 * @return element
	 */
	generateButtons: function(form) 
	{
		var self = this;
		
		var wrapper = new Element('div', {
			'class': 'dcaWizardButtons'
		});
		
		var save = new Element('input', {
			'type': 'submit',
			'value': this.saveLbl,
			'class': 'tl_submit',
			'id': 'save',
			'name': 'save',
		    'events': {
				'click': function(e) {
					e.stop();
					self.save(form);
				}
			}
		});
		
		var saveNclose = new Element('input', {
			'type': 'submit',
			'value': this.saveNcloseLbl,
			'class': 'tl_submit',
			'id': 'saveNclose',
			'name': 'saveNclose',
		    'events': {
				'click': function(e) {
					e.stop();
					self.save(form, 'hideWizard');
				}
			}
		});
		
		var saveNcreate = new Element('input', {
			'type': 'submit',
			'value': this.saveNcreateLbl,
			'class': 'tl_submit',
			'id': 'saveNcreate',
			'name': 'saveNcreate',
		    'events': {
				'click': function(e) {
					e.stop();
					self.save(form, 'addItem');
				}
			}
		});
		
		var abort = new Element('input', {
			'type': 'submit',
			'value': this.cancelLbl,
			'class': 'tl_submit',
			'id': 'abort',
			'name': 'abort',
		    'events': {
				'click': function(e) {
					e.stop();
					self.hideWizard();			
				}
			}
		});
		
		abort.inject(wrapper, 'bottom');
		save.inject(wrapper, 'bottom');
		saveNclose.inject(wrapper, 'bottom');
		saveNcreate.inject(wrapper, 'bottom');
		
		return wrapper;
	},
	
	
	/**
	 * Save event
	 * @param element
	 * @param string
	 * @return boolean
	 */
	save: function(form, callback)
	{
		new Request.Mixed({
			url: this.baseUrl + '&act=edit&id=' + this.currentRow.id,
			onRequest: function()
			{
				AjaxRequest.displayBox('Saving...');
				this.ajaxIndicatorState = 'visible';	
			}.bind(this),
			onSuccess: function(txt, xml, js)
			{
				var container = this.filterContainer(txt);				
				var hasErrors = this.checkForErrors(container, js);
	
				AjaxRequest.hideBox();
				this.ajaxIndicatorState = 'hidden';

				// if there are no errors we call a callback function, if there is any
				if(!hasErrors && callback)
				{
					switch(callback)
					{
						case 'addItem':
							this.addItem();
							break;
						case 'hideWizard':
							this.hideWizard();
							break;
					}
				}
			}.bind(this),
			onFailure: function()
			{
				this.showErrorBox(this.failureMsg);
			}.bind(this)
		}).send(form.toQueryString());
	},	
	

	/**
	 * Check the response for errors
	 * @param element
	 * @param javascript
	 * @return boolean
	 */
	checkForErrors: function(container, js) 
	{
		var errors = container.getElements('p.tl_error');

		// if there are no errors we update the table
		if(errors.length == 0)
		{
			this.updateTable();
			return false;
		}
		
		this.dcaWizardEditBox.set('html', '');
		if (this.prepareHTML(container))
		{
			// execute the javascript
			if (js)
			{
				$exec(js);
			}
			
			this.addOnChangeEventsToFields();
			
			// preset the fields if they have already been edited --> cache
			this.setCachedValues();
			
			return true;
		}
		else
		{
			alert('Error2');
		}
		
		return false;
	},
	

	/**
	 * Add the cached values to the fields
	 */
	setCachedValues: function()
	{
		this.dcaWizardEditBox.getElements('div.dcaWizardField').getElements('input, select, textarea').each( function(el)
		{
			var name = el.get('name');
			//check whether there exists a cached value exists and set the value if so
			if(this.objData[this.currentRow.id])
			{
				el.set('value', this.objData[this.currentRow.id][name]);
			}
		}.bind(this));
	},
	
	
	/**
	 * Add onchange event to every field
	 */
	addOnChangeEventsToFields: function()
	{
		var objFields = {};
	
		this.dcaWizardEditBox.getElements('div.dcaWizardField').getElements('input, select, textarea').each( function(el)
		{
			el.addEvent('change', function()
			{
				var name = el.get('name');
				var value = el.get('value');
				objFields[name] = value;
				
				// save data in this.objData
				this.objData[this.currentRow.id] = objFields;				
			}.bind(this));			
		}.bind(this));
	},

	
	/**
	 * Update the table row after updating data in field
	 */
	updateTable: function()
	{
		var row	= $('itemId_' + this.currentRow.id);
		
		// if the user doesn't change anything, this.objData[id] is undefined
		if(!this.objData[this.currentRow.id])
			return;

		$each(this.objData[this.currentRow.id], function(value, key)
		{
			var tdclass = 'td.' + key;
			
			// get the td element but we are not sure if there even is one as not all fields have to necessarily appear in the table
			var td = row.getElement(tdclass);
			if(td)
			{
				td.set('html', value);		
			}
		});
	},
	
	
	/**
	 * Add a item
	 */
	addItem: function()
	{
		var self = this;
		
		// is the next element odd or even?
		var last = this.dcaWizardTable.getElement('tbody').getLast('tr.item');
		if(last)
		{
			var oddOrEven = (last.hasClass('odd')) ? 'even' : 'odd';
		}
		else
		{
			var oddOrEven = 'odd';
		}
		
		new Request({
			url: window.location.href,
			data: 'isAjax=1&action=dcaWizardAjaxCall&type=addItem&dcaTable=' + this.dcaTable + '&dcaField=' + this.dcaField + '&parentId=' + this.options.parentId,
			onRequest: function()
			{
				AjaxRequest.displayBox('Loading...');
				this.ajaxIndicatorState = 'visible';
			}.bind(this),
			onSuccess: function(response)
			{
				// build <tr> with id from response and odd or even class
				var tr = new Element('tr', {
					'id': 'itemId_' + response,
					'class': 'item ' + oddOrEven
				});
				
				// inject all the <td>'s in the built <tr>
				this.tableColumns.each( function (classname)
				{
					switch(classname)
					{
						case 'id':
							var td = new Element('td', {
								'class': classname,
								'html': response
							});
						break;
						
						case 'operations':
							var editA = new Element('a', {
								'class': 'edit',
								'href': '#',
								'events':
								{
									'click': function(e)
									{
										e.stop();
										self.currentRow.el = tr;
										self.currentRow.id = tr.get('id').replace('itemId_', '');
										self.showWizard();
									}
								}
							});
							var editImg = new Element('img', {
								'height': '16',
								'width': '14',
								'src': 'system/themes/' + this.theme + '/images/edit.gif',
								'alt': this.options.editItemAlt,
								'class': 'tl_listwizard_img'
							});
							editImg.inject(editA);
							
							var deleteA = new Element('a', {
								'class': 'delete',
								'href': '#',
								'events':
								{
									'click': function(e)
									{
										e.stop();
										self.currentRow.el = tr;
										self.currentRow.id = tr.get('id').replace('itemId_', '');
										self.deleteItem();
									}
								}
							});
							var deleteImg = new Element('img', {
								'height': '16',
								'width': '14',
								'src': 'system/themes/' + this.theme + '/images/delete.gif',
								'alt': this.options.deleteItemAlt,
								'class': 'tl_listwizard_img'
							});
							deleteImg.inject(deleteA);
							
							var ops = new Element('div', {
								'class': 'operations'
							});
							var td = new Element('td', {
								'class': classname
							});
							editA.inject(ops, 'bottom');
							deleteA.inject(ops, 'bottom');
							ops.inject(td);
						break;

						default:
							var td = new Element('td', {
								'class': classname
							});							
					}

					td.inject(tr, 'bottom');
				}.bind(this));
				
				// inject the built row in the table, add events and show the wizard
				tr.inject(this.dcaWizardTable.getElement('tbody'), 'bottom');
				this.tableRows.include(tr);
				
				// set new row active
				this.currentRow.el = tr;
				this.currentRow.id = response;
				
				this.showWizard();
   			}.bind(this),
			onFailure: function()
			{
				this.showErrorBox(this.failureMsg);
			}.bind(this)
		}).send();
	},
	
	
	/**
	 * Delete a item
	 */
	deleteItem: function()
	{
		// confirm message
		var msg = confirm(this.options.deleteConfirmMsg);
		if(!msg) return;

		// delete the item
		new Request({
			url: window.location.href,
			data: 'isAjax=1&action=dcaWizardAjaxCall&type=deleteItem&dcaTable=' + this.dcaTable + '&dcaField=' + this.dcaField + '&entryId=' + this.currentRow.id,
			onRequest: function()
			{
				AjaxRequest.displayBox('Loading...');
				this.ajaxIndicatorState = 'visible';
			}.bind(this),
			onSuccess: function(response)
			{
				// add "disabled" CSS class, destroy the operations
				this.currentRow.el.addClass('disabled');
				this.currentRow.el.getElement('div.operations').destroy();
				AjaxRequest.hideBox();
				this.ajaxIndicatorState = 'hidden';
			}.bind(this),
			onFailure: function()
			{
				this.showErrorBox(this.failureMsg);
			}.bind(this)
		}).send();
	},
	
	
	/**
	 * Show error box
	 * @param string
	 */
	showErrorBox: function(msg)
	{
		AjaxRequest.displayBox(msg);
		var self = this;

		var a = new Element('a', {
			'style': 'text-align:right;',
			'text': this.closeLbl,
		    'events': {
				'click': function(e) {
					e.stop();
					AjaxRequest.hideBox();		
				}
			}
		});
		
		var box = $('tl_ajaxBox');
		a.inject(box, 'bottom');
		box.setStyle('background', '#fec7c7');
		box.setStyle('border', '2px solid #fe7c7c');
	}
});