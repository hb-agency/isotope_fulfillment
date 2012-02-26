/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
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
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


var IsotopeFulfillment =
{
	toggleAddressFields: function(el, elid)
	{
		if (el.value==0 && document.id(elid).getStyle('display')=='none')
		{
			document.id(elid).setStyle('display', 'block');
		}
		else
		{
			document.id(elid).setStyle('display', 'none');
		}
	},
	
	toggleMemberFields: function(el)
	{
		
		var billingradios = $$('#billing_address input[type=radio]');
		var shippingradios = $$('#shipping_address input[type=radio]');
		var display = el.value=='existing' ? 'block' : 'none';
		document.id('lookup').setStyle('display',display);
		billingradios.each( function(radio) { if(radio.value>0) { radio.getParent('span').setStyle('display',display); radio.checked = false; }});
		shippingradios.each( function(radio) { if(radio.value>0) { radio.getParent('span').setStyle('display',display); radio.checked = false; }});
		if(billingradios[0]) { billingradios[0].checked = true; }
		shippingradios[0].checked = true;
	},
	
	/**
	 * Tie into the tableLookup results and set toggle on address radios
	 */
	initialize: function()
	{
		$$('#lookup input[type=text]').each( function(el) 
		{
			el.addEvent('change', function(event) {
				this.update();
			}.bind(this));
		}.bind(this));
		
		$$('#addressfields input[type=radio]').each( function(el) 
		{
			el.addEvent('click', function(event) {
				this.toggleAddressFields(el, el.name+'_new');
			}.bind(this));
		}.bind(this));
		
		$$('#addresstype input[type=radio]').each( function(el) 
		{
			el.addEvent('click', function(event) {
				this.toggleMemberFields(el);
			}.bind(this));
		}.bind(this));
		
	},
	
	/**
	 * Update onclick events for the radios in member lookup
	 */
	update: function()
	{
		$$('#lookup input[type=radio]').each( function(el) 
		{
			el.addEvent('click', function(event) {
				var data = el.checked ? el.value : 0;
				new Request.Contao(
				{
					field: el,
					url: window.location.href,
					data: 'isAjax=1&action=resetAddresses&data='+data+'&REQUEST_TOKEN='+REQUEST_TOKEN,
					onRequest: AjaxRequest.displayBox('Loading data ...'),
						
					onSuccess: function(txt)
					{
						document.id('addressfields').set('html', txt);
						window.fireEvent('domready');
						AjaxRequest.hideBox();
					}
				}).send();
			});
		});
	}	 
};


