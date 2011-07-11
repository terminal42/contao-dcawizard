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

var dcaWizard = new Class({

	Implements: [Options],
	Binds: ['sendOperation', 'addURLFragment'],

	options:
	{
		baseURL: '',
		referer: '',
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
		this.element.dcawizard = this;

		this.setOptions(options);

		// Store original accesskey attributes
		document.getElements('.tl_formbody_submit .tl_submit').each( function(button)
		{
			button.set('_accesskey', button.get('accesskey'));
		});


		// UGLY HACK: set referrer
		document.getElements('form.tl_form').each( function(form)
		{
			if (form.getParent('.tl_version_panel'))
				return;

			form.addEvent('submit', function()
			{
				form.removeEvents();

				new Request(
				{
					method:'get',
					url:(this.addURLFragment(this.options.referer)),
					onComplete: function()
					{
						form.submit();
					}
				}).send();

				return false;
			}.bind(this));

			// Add hidden element of the clicked button (Mootools Bug)
			form.getElements('input.tl_submit').each( function(button)
			{
				button.addEvent('click', function()
				{
					new Element('input', {type:'hidden', name:button.get('name'), value:button.get('value')}).inject(form);
				});
			});

		}.bind(this));



		this.request = new Request.DCAWizard(
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
			onFailure: function(xhr)
			{
				alert('failed');
			},
			onSuccess: function(responseTree, responseElements, responseHTML, responseJavaScript)
			{
				$A(responseTree).each( function(el)
				{
					if ($(el) && $(el).get && el.get('id') == 'container')
					{
						// Disable original submit buttons to enable new access keys
						document.getElements('.tl_formbody_submit .tl_submit').each( function(button)
						{
							if (el.getElement('.tl_formbody_submit'))
							{
								button.set('disabled', true).set('accesskey', '');
							}
							else
							{
								button.set('disabled', false).set('accesskey', button.get('_accesskey'));
							}
						});

						if (el.getElement('.tl_panel'))
							el.getElement('.tl_panel').getParent('form').destroy();

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
							// make Backend.autoSubmit work
							form.submit = function() {return this.fireEvent('submit');}

							form.addEvent('submit', function()
							{
								// if we have a tinyMCE, we need to save its value to the text areas before submitting
								try
								{
									if ($defined(tinyMCE))
									{
										form.getElements('textarea').each( function(textarea)
										{
											tinyMCE.execCommand('mceRemoveEditor', false, textarea.get('id'));
										});
									}
								}
								catch(e) {}

								this.request.send({url:(this.addURLFragment(form.action)), data:this.addURLFragment(form.toQueryString(), true), method:'post'});

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

						this.adoptButtons();

						$exec(responseJavaScript.replace(/.*<!--.*|.*-->.*/g, ''));

						// Stupid TinyMCE is relying on window "load" event to initialize. This will never occure if tinyMCE is initialized trough ajax.
						try
						{
							if ($defined(tinyMCE))
							{
								tinyMCE.dom.Event._pageInit(window);
							}
						}
						catch(e) {}

						// make tooltips work
						Backend.addInteractiveHelp();

						// collapse fileTrees
						Backend.hideTreeBody();
					}
				}.bind(this));
			}.bind(this)
		});

		this.request.send({url:(this.addURLFragment(this.options.baseURL)), method:'get'});
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

		// UGLY HACK: set referrer
		new Request(
		{
			method:'get',
			url:(this.addURLFragment(this.options.baseURL)),
			onSuccess: function()
			{
				this.request.send({url: this.addURLFragment(button.get('href')), method:'get'});
			}.bind(this)
		}).send();

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
				buttons = new Element('div', {'class': 'tl_content_right'}).inject(this.element.getPrevious(), 'top');
			}

			buttons.empty();

			var hideBackButton = $defined(this.element.getElement('.tl_listing_container'));

			this.element.getElement('div[id=tl_buttons]').getElements('a').each( function(button)
			{
				if (hideBackButton && button.hasClass('header_back'))
					return;

				if (button.hasClass('header_new') || button.hasClass('header_back') || button.hasClass('header_clipboard'))
				{
					button.dcaclick = button.onclick;
					button.onclick = '';
					button.addEvent('click', this.sendOperation);
				}

				buttons.grab(button);

			}.bind(this));

			if (this.element.getElement('.tl_header .tl_content_right'))
			{
				this.element.getElements('.tl_header .tl_content_right a').each( function(button)
				{
					if (button.href.test(/act=edit/))
						return;

					button.dcaclick = button.onclick;
					button.onclick = '';
					button.addEvent('click', this.sendOperation);

					new Element('span', {text:button.getElement('img').get('alt'), styles:{'padding-left':'5px'}}).inject(button);

					buttons.grab(button);

				}.bind(this));
			}
		}
	},


	/**
	 * Adds "&dcaWizard=1" to an URL string if not already present
	 * @param string
	 * @return string
	 */
	 addURLFragment: function(url, post)
	 {
		if(post && !url.contains('&action=dcaWizard'))
		{
			url += '&action=dcaWizard'
		}
		else if (!url.contains('&dcaWizard=1'))
		{
			url += '&dcaWizard=1'
		}

		return url;
	 }
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

var Group = new Class(
{
	initialize: function()
	{
		this.instances = Array.flatten(arguments);
		this.events = {};
		this.checker = {};
	},

	addEvent: function(type, fn)
	{
		this.checker[type] = this.checker[type] || {};
		this.events[type] = this.events[type] || [];
		if (this.events[type].contains(fn)) return false;
		else this.events[type].push(fn);
		this.instances.each(function(instance, i){
			instance.addEvent(type, this.check.bind(this, [type, instance, i]));
		}, this);
		return this;
	},

	check: function(type, instance, i)
	{
		// Workaround for issue in Chrome: http://code.google.com/p/chromium/issues/detail?id=52842
		if (i == null && instance == null)
		{
			instance = type[1];
			i = type[2];
			type = type[0];
		}

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
Request.DCAWizard = Class.refactor(Request.HTML,
{
	options:
	{
		evalExternalScripts: true,
		evalExternalStyles: true
	},
	
	success: function(text)
	{
		var json;

		try
		{
			json = JSON.decode(text);
			
			// Automatically set the new request token
			if (json.token)
			{
				REQUEST_TOKEN = json.token;

				// Update all forms
				$$('input[type="hidden"]').each(function(el)
				{
					if (el.name == 'REQUEST_TOKEN')
					{
						el.value = REQUEST_TOKEN;
					}
				});
			}
			
			if (json.target)
			{
				this.cancel();
				this.send({url:json.target});
				return;
			}

			text = json.content;
		}
		catch (error){}
		
		if (this.options.evalExternalStyles)
		{
			var regex = /<link.*href=('|")([^>'"\r\n]*)('|")[^>]*>/gi;
			var matches = stylesheets = [];

			while (matches = regex.exec(text))
			{
				if (!/fixes.css/.exec(matches[2]) && !document.getElement(('link[href='+matches[2]+']')))
				{
					Asset.css(matches[2]);
				}
			}
		}

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
					sobjects.push(new Element('script', {type: 'text/javascript', src: script}));
					$(h).grab(sobjects[sobjects.length-1]);
				});

				var fn = this.previous;
				var group = new Group(sobjects);
				group.addEvent('load', function() {fn.apply(this,[text]);}.bind(this)).addEvent('readystatechange', function() {fn.apply(this,[text]);}.bind(this));
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


Backend.previousMakeParentViewSortable = Backend.makeParentViewSortable;
Backend.makeParentViewSortable = function(ul)
{
	ul = $(ul);
	if (ul.getParent('.dcawizard'))
	{
		var list = new Sortables(ul,
		{
			contstrain: true,
			opacity: 0.6
		});

		list.active = false;

		list.addEvent('start', function()
		{
			list.active = true;
		});

		list.addEvent('complete', function(el)
		{
	    	if (!list.active)
	    	{
    			return;
    		}

    		if (el.getPrevious())
    		{
    			var id = el.get('id').replace(/li_/, '');
    			var pid = el.getPrevious().get('id').replace(/li_/, '');
    			var req = new URI(ul.getParent('.dcawizard').dcawizard.options.baseURL).get('query').replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=1&pid=' + pid;
    			new Request({url: ul.getParent('.dcawizard').dcawizard.options.baseURL, method: 'get', data: req}).send();
    		}
    		else if (el.getParent())
    		{
    			var id = el.get('id').replace(/li_/, '');
    			var pid = el.getParent().get('id').replace(/ul_/, '');
    			var req = new URI(ul.getParent('.dcawizard').dcawizard.options.baseURL).get('query').replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=2&pid=' + pid;
				new Request({url: ul.getParent('.dcawizard').dcawizard.options.baseURL, method: 'get', data: req}).send();
    		}
    	});
	}
	else
	{
		Backend.previousMakeParentViewSortable(ul);
	}
}

