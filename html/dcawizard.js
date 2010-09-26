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
		baseURL: '',
		scroll: false
	},
	
	
	/**
	 * Initialize the DCA Wizard
	 *
	 * @param  Element
	 * @param  Object
	 * @return void
	 */
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
				
				if (this.options.scroll)
					new Fx.Scroll(window).toElement(this.element);
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
						this.element.empty().adopt(el.getElement('div[id=main]').getChildren());
						
						// Add AJAX event to listing buttons
						this.element.getElements('.tl_content_right a, .tl_right_nowrap a').each( function(button)
						{
							button.dcaclick = button.onclick;
							button.onclick = '';
							button.addEvent('click', this.sendOperation);
						}.bind(this));
						
						// Add AJAX event to forms
						this.element.getElements('form.tl_form').each( function(form)
						{
							form.addEvent('submit', function()
							{
								// if we have a tinyMCE, we need to save its value to the text areas before submitting
								try
								{
									if ($defined(tinyMCE))
									{
										tinyMCE.triggerSave();
									}
								}
								catch(e) {}
								
								this.request.send({url:form.action, data:form.toQueryString(), method:'post'});
								
								return false;
							}.bind(this));
							
							// Add hidden element of the clicked button (Mootools Bug)
							form.getElements('input.tl_submit').each( function(button)
							{
								button.addEvent('click', function()
								{
									new Element('input', {type:'hidden', name:button.get('name'), value:button.get('value')}).inject(form);
								});
							}.bind(this));
							
						}.bind(this));
						
						// Adopt buttons
						this.adoptButtons(el);
						
						$exec(responseJavaScript);
						
						// Stupid TinyMCE is relying on window "load" event to initialize. This will never occure if tinyMCE is initialized trough ajax.
						try
						{
							if ($defined(tinyMCE) && !tinyMCE.dom.Event.domLoaded)
							{
								tinyMCE.dom.Event._pageInit(window);
							}
						}
						catch(e) {}
					}
				}.bind(this));
			}.bind(this)
		});
		
//		var url = window.location.href.parseQueryString();
//		url.act = '';
//		url.table = this.options.table;
//		url.token = 'dcawizard'; // Supplying a token prevents BackendUser from storing referer. Otherwise we would be redirected to the wrong page on saveNclose.

//		this.baseURL = $H(url).toQueryString();
		
		this.request.send({url:this.options.baseURL, method:'get'});
	},
	
	
	/**
	 * Submit a button (link) using AJAX
	 *
	 * @param  Event
	 * @return bool
	 */
	sendOperation: function(event)
	{
		button = event.target;
		
		if (button.getParent().get('tag') == 'a')
		{
			button = button.getParent();
		}
		
		if (button.dcaclick && button.dcaclick() == false)
			return false;
		
		this.request.send({url:button.get('href'), method:'get'});
		return false;
	},
	
	
	/**
	 * Replace global operations
	 *
	 * @param  Element
	 * @param  book
	 * @return void
	 */
	adoptButtons: function()
	{
		if (this.element.getElement('div[id=tl_buttons]'))
		{
			var buttons = this.element.getPrevious().getElement('.tl_content_right');

			if (!buttons)
			{
				buttons = new Element('div', {'class': 'tl_content_right'}).inject(this.element.getPrevious());
			}
			
			buttons.empty();
			
			var hideBack = $defined(this.element.getElement('.tl_listing_container'));
			
			this.element.getElement('div[id=tl_buttons]').getElements('a').each( function(button)
			{
				if (hideBack && button.hasClass('header_back'))
					return;
					
				if (button.hasClass('header_new') || button.hasClass('header_back'))
				{
					button.dcaclick = button.onclick;
					button.onclick = '';
					button.addEvent('click', this.sendOperation);
				}
				
				buttons.grab(button);
				
			}.bind(this));
		}
	},
});



/*
---

script: Group.js

description: Class for monitoring collections of events

license: MIT-style license

authors:
- Valerio Proietti

requires:
- core:1.2.4/Events
- /MooTools.More

provides: [Group]

...
*/

var Group = new Class({

	initialize: function(){
		this.instances = Array.flatten(arguments);
		this.events = {};
		this.checker = {};
	},

	addEvent: function(type, fn){
		this.checker[type] = this.checker[type] || {};
		this.events[type] = this.events[type] || [];
		if (this.events[type].contains(fn)) return false;
		else this.events[type].push(fn);
		this.instances.each(function(instance, i){
			instance.addEvent(type, this.check.bind(this, [type, instance, i]));
		}, this);
		return this;
	},

	check: function(type, instance, i){
		this.checker[type][i] = true;
		var every = this.instances.every(function(current, j){
			return this.checker[type][j] || false;
		}, this);
		if (!every) return;
		this.checker[type] = {};
		this.events[type].each(function(event){
			event.call(this, this.instances, instance);
		}, this);
	}

});



/**
 * http://mootools.net/forge/p/request_html_with_external_javascripts
 */
Request.HTML = Class.refactor(Request.HTML,
{
	options:
	{
		evalExternalScripts: true
	},
	success: function(text)
	{
		if (this.options.evalExternalScripts)
		{
			var regex = /<script.*src=('|")([^>'"\r\n]*)('|")[^>]*><\/script>/gi;
			var matches = scripts = [];
			
			while (matches = regex.exec(text))
			{
				if (!document.getElement(('script[src='+matches[2]+']')))
				{
					scripts.push(matches[2]);
				}
			}
			
			if (scripts.length > 0)
			{
				var h = document.getElementsByTagName('head')[0];
				var sobjects = [];
				
				scripts.each(function(script)
				{
					// .addEvent('load', function() {fn.apply(this,[text]);}.bind(this))
					sobjects.push(new Element('script', {type: 'text/javascript', src: script}));
					h.grab(sobjects[sobjects.length-1]);
				});
				
				var fn = this.previous;
				var group = new Group(sobjects);
				group.addEvent('load', function() {fn.apply(this,[text]);}.bind(this));
			}
			else
			{
				this.previous(text);
			}
		}
		else
		{
			this.previous(text);
		}
	}
});
