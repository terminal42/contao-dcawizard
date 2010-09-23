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

	Implements: [Options],
	Binds: ['sendOperation'],
	
	options:
	{
		table: '',
		scroll: false
	},

	initialize: function(element, options)
	{
		this.element = document.id(element);
		
		this.setOptions(options);
		
		this.request = new Request.HTML(
		{
			link: 'abort',
			evalScripts: false,
			onRequest: function()
			{
				AjaxRequest.displayBox('Loading data …');
			},
			onComplete: function()
			{
				AjaxRequest.hideBox();
			},
			onCancel: function()
			{
				AjaxRequest.hideBox();
			},
			onFailure: function()
			{
				alert('failed');
			},
			onSuccess: function(responseTree, responseElements, responseHTML, responseJavaScript)
			{
				$A(responseTree).each( function(el)
				{
					if ($(el).get && el.get('id') == 'container')
					{
						console.log(el);
						if (this.options.scroll)
							new Fx.Scroll(window).toElement(this.element);
						
						if (el.getElement('.tl_formbody_edit'))
						{
							this.edit(el);
						}
						else if (el.getElement('table.tl_show'))
						{
							this.show(el);
						}
						else if (el.getElement('.tl_listing_container'))
						{
							this.list(el);
						}
						
						eval(responseJavaScript);
					}
				}.bind(this));
			}.bind(this)
		});
		
		var url = window.location.href.parseQueryString();
		url.act = '';
		url.table = this.options.table;
		
		this.request.send({url:$H(url).toQueryString(), method:'get'});
	},
	
	sendOperation: function(event)
	{
		button = event.target;
		
		if (button.getParent().get('tag') == 'a')
		{
			button = button.getParent();
		}
		
		if (button.myclick && button.myclick() == false)
			return false;
		
		this.request.send({url:button.get('href'), method:'get'});
		return false;
	},
	
	edit: function(container)
	{
		container.getElements('form.tl_form').each( function(form)
		{
			if (form.get('id') != 'tl_version')
			{
				this.element.empty().adopt(form);
		
				form.addEvent('submit', function()
				{
					this.request.send({url:form.action, data:form.toQueryString(), method:'post'});
					return false;
				}.bind(this));
			}
		}.bind(this));
		
		this.adoptButtons(container);
	},
	
	show: function(container)
	{
		this.element.empty().adopt(container.getElement('table.tl_show'));
		
		this.adoptButtons(container);
	},
	
	list: function(container)
	{
		this.element.empty().adopt(container.getElements('.tl_content, .tl_empty_parent_view, .tl_listing'));
		
		this.element.getElements('.tl_content_right a, .tl_right_nowrap a').each( function(button)
		{
			button.myclick = button.onclick;
			button.onclick = '';
			button.addEvent('click', this.sendOperation);
		}.bind(this));
		
		this.adoptButtons(container, true);
	},
	
	adoptButtons: function(container, hideBackButton)
	{
		if (container.getElement('div[id=tl_buttons]'))
		{
			var buttons = this.element.getPrevious().getElement('.tl_content_right');

			if (!buttons)
			{
				buttons = new Element('div', {'class': 'tl_content_right'}).inject(this.element.getPrevious());
			}
			
			buttons.empty();
			
			container.getElement('div[id=tl_buttons]').getElements('a').each( function(button)
			{
				if (hideBackButton && button.hasClass('header_back'))
					return;
					
				if (button.hasClass('header_new') || button.hasClass('header_back'))
				{
					button.myclick = button.onclick;
					button.onclick = '';
					button.addEvent('click', this.sendOperation);
				}
				
				buttons.grab(button);
				
			}.bind(this));
		}
	}
});

