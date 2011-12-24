<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

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


class ModuleIsotopeOrderBackend extends BackendModule
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_iso_orderedit';

	/**
	 * Save input
	 * @var boolean
	 */
	protected $blnSave = true;

	/**
	 * Advanced mode
	 * @var boolean
	 */
	protected $blnAdvanced = true;
	
	/**
	 * Order data. Each checkout step can provide key-value (string) data for the order email.
	 * @var array
	 */
	public $arrOrderData = array();
	
	/**
	 * Do not submit form
	 * @var bool
	 */
	public $doNotSubmit = false;
	
	/**
	 * Display fields as read-only
	 * @var bool
	 */
	public $blnReadOnly = false;
	
	/**
	 * Form ID - Needs to be set to this to trigger payment modules
	 * @var string
	 */
	protected $strFormId = 'iso_mod_checkout_payment';
	

	/**
	 * Generate the module
	 */
	public function compile()
	{		
		$this->import('Isotope');
		$this->import('BackendUser', 'User');
		$this->loadLanguageFile('tl_iso_orders');
		$this->loadLanguageFile('tl_iso_messaging');
		$this->loadLanguageFile('default');
		
		$this->Template = new IsotopeTemplate('be_iso_order');
		
		switch ($this->Input->get('key'))
		{
			case 'new_order':
				$this->createOrder();
				break;

			case 'edit_order':
				$this->editOrder();
				break;

			case 'cancel_copy':
				$this->cancelCopy();
				break;

			case 'unlock_order':
				$this->unlockOrder();
				break;
				
			default:
				break;

		}

		$this->Template->request = ampersand($this->Environment->request, true);
				
		// Load scripts
		$GLOBALS['TL_CSS'][] = 'system/modules/isotope_fulfillment/html/fulfillment.css';
		$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/isotope_fulfillment/html/fulfillment.js';
		
		return $this->Template->parse();
	}



	/**
	 * Create an order
	 */
	protected function createOrder()
	{		
		$objOrder = new IsotopeBackendOrder();
		
		if (!$objOrder->findBy('id',$this->Input->get('id')))
		{
			$objOrder->uniqid		= uniqid($this->Isotope->Config->orderPrefix, true);
			$objOrder->cart_id		= 0; //Need to set this first
			$objOrder->findBy('id', $objOrder->save());
			$objOrder->cart_id = $objOrder->id;
			$this->Isotope->Order = $objOrder;
			$this->Isotope->Cart = $this->Isotope->Order;
			$this->redirect('contao/main.php?do=iso_orders&key=new_order&id='. $objOrder->id);
		}
		
		$this->Isotope->Order = $objOrder;
		$this->Isotope->Cart = 	$this->Isotope->Order; //Need to set this for Rules validation and Shipping/Payment			
		
		//Unset Shipping and Payment and Messaging as we load those in the edit view once we can load them based on products/address
		unset($GLOBALS['ISO_ORDER_STEPS']['shipping']);
		unset($GLOBALS['ISO_ORDER_STEPS']['payment']);
		unset($GLOBALS['ISO_ORDER_STEPS']['messages']);
		$this->Template->fields = $this->buildSteps();
		
		//Collect Data and Write Order, then Redirect to Edit
		if(!$this->doNotSubmit && $this->Input->post('FORM_SUBMIT') == $this->strFormId)
		{
			$objOrderSaved = $this->writeOrder();
			$this->redirect('contao/main.php?do=iso_orders&key=edit_order&id='. $objOrderSaved->id);
		}
		
		$this->Template->cancelCopy 		= $GLOBALS['TL_LANG']['MSC']['cancelCopy'];
		$this->Template->cancelCopyHref 	= 'contao/main.php?do=iso_orders&key=cancel_copy&id='. $objOrder->id;
		$this->Template->cancelCopyCssClass = 'invisible';
		
		$this->Template->formId	= $this->strFormId;			
		$this->Template->headline = $GLOBALS['TL_LANG']['tl_iso_orders']['createOrder'];
		$this->Template->goBack = $GLOBALS['TL_LANG']['tl_iso_orders']['goBack'];
		$this->Template->submit = $GLOBALS['TL_LANG']['tl_iso_orders']['createSubmit'];
	}


	/**
	 * Edit an order
	 */
	protected function editOrder()
	{
		$objOrder = new IsotopeBackendOrder();
		
		if (!$objOrder->findBy('id',$this->Input->get('id')))
		{
			$this->log('Invalid order ID! "' . $this->Input->get('id') . '"', 'ModuleIsotopeOrderBackend editOrder()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		
		$this->Isotope->Order = $objOrder;
		$this->Isotope->Order->unlock();
		$this->Isotope->Cart = 	$this->Isotope->Order; //Need to set this for Rules validation and Shipping/Payment
		
		if($this->Isotope->Order->status=='complete' || $this->Isotope->Order->status=='cancelled')
		{
			$this->blnReadOnly = true;
			$this->Isotope->Order->lock();
		}
								
		$this->Template->fields = $this->buildSteps();
						
		//Collect Data and Write Order
		if(!$this->doNotSubmit && $this->Input->post('FORM_SUBMIT') == $this->strFormId && !$this->isProcessSubmit() && !$this->blnReadOnly)
		{			
			$this->writeOrder();
			$this->reload();
		}
		
		$this->Isotope->Order->save();
						
		//Order totals and taxes
		$this->Template->showTotals 		= true;
		$this->Template->subTotal 			= $this->getTextInput('subTotal', $this->Isotope->Order->subTotal, $GLOBALS['TL_LANG']['tl_iso_orders']['subTotal']);
		$this->Template->taxTotal 			= $this->getTextInput('taxTotal', $this->Isotope->Order->taxTotal, $GLOBALS['TL_LANG']['tl_iso_orders']['taxTotal']);
		$this->Template->shippingTotal 		= $this->getTextInput('shippingTotal', $this->Isotope->Order->shippingTotal, $GLOBALS['TL_LANG']['tl_iso_orders']['shippingTotal']);
		$this->Template->grandTotal 		= $this->getTextInput('grandTotal', $this->Isotope->Order->grandTotal, $GLOBALS['TL_LANG']['tl_iso_orders']['grandTotal']);
		
		$this->Template->cancelCopy 		= $GLOBALS['TL_LANG']['MSC']['cancelCopy'];
		$this->Template->cancelCopyHref 	= 'contao/main.php?do=iso_orders&key=cancel_copy&id='. $objOrder->id;
		$this->Template->cancelCopyCssClass = $this->blnReadOnly === true ? ' invisible' : '';
		$this->Template->submitCssClass 	= $this->blnReadOnly === true ? ' invisible' : '';
				
		$this->Template->formId				= $this->strFormId;			
		$this->Template->headline 			= sprintf($GLOBALS['TL_LANG']['tl_iso_orders']['editOrder'],$this->Isotope->Order->order_id, $GLOBALS['TL_LANG']['ORDER'][$this->Isotope->Order->status]);
		$this->Template->goBack 			= $GLOBALS['TL_LANG']['tl_iso_orders']['goBack'];
		$this->Template->submit 			= $GLOBALS['TL_LANG']['tl_iso_orders']['editSubmit'];
		
	}



	/**
	 * Cancel this order and copy to new
	 */
	protected function cancelCopy()
	{
		$objOrder = new IsotopeBackendOrder();
		
		if (!$objOrder->findBy('id',$this->Input->get('id')))
		{
			$this->log('Invalid order ID! "' . $this->Input->get('id') . '"', 'ModuleIsotopeOrderBackend cancelCopy()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		
		$this->Isotope->Order = $objOrder;
		$intNewOrderId = $this->Isotope->Order->cancelCopy();
		
		$this->redirect('contao/main.php?do=iso_orders&key=edit_order&id='. $intNewOrderId);
	}
	
	
	/**
	 * Build and parse the order steps
	 *
	 * @return string
	 */
	protected function buildSteps()
	{
		$strReturn = '';
		
		foreach( $GLOBALS['ISO_ORDER_STEPS'] as $step => $arrCallbacks )
		{						
			// Step could be removed while looping
			if (!isset($GLOBALS['ISO_ORDER_STEPS'][$step]))
				continue;
				
			//Reset callbacks ******************************** ADDED TO CHECK FOR CALLBACK RESET
			$arrCallbacks = $GLOBALS['ISO_ORDER_STEPS'][$step];
			
			$objTemplate = new IsotopeTemplate('be_iso_fieldset');
			
			// Default template settings. Must be set at beginning so they can be overwritten later (eg. through callback)
			$objTemplate->stepLabel = strlen($GLOBALS['TL_LANG']['ISO']['checkout_'. $step]) ? $GLOBALS['TL_LANG']['ISO']['checkout_'. $step] : $step;
			$objTemplate->stepID = $step;
			
			if(($step=='products' || $step=='address') && $this->Input->get('key')=='edit_order')
			{
				$objTemplate->stepClosed = true;
			}
			
			$strBuffer = '';
			
			foreach( $arrCallbacks as $callback )
			{
				
				if($callback[0] == 'ModuleIsotopeOrderBackend')
				{
					$strBuffer .= $this->{$callback[1]}();
				}
				else
				{
					$this->import($callback[0]);
					$strBuffer .= $this->{$callback[0]}->{$callback[1]}($this);
				}
														
			}
			
			$objTemplate->step = $strBuffer;
			$strParsed = $objTemplate->parse();			
			$strReturn .= $strParsed;

		}
		
		return $strReturn;
	
	}
	
	
	/**
	 * Generate the status interface step
	 *
	 * @return string
	 */
	protected function getStatusInterface()
	{
		$objTemplate = new IsotopeTemplate('be_iso_order_status');
		
		$objWidget = new SelectMenu();

		$objWidget->id = 'status';
		$objWidget->name = 'status';
		$objWidget->label = 'Order Status';
		
		$arrOptions = array();
		foreach($GLOBALS['ISO_ORDER'] as $order)
		{
			$arrOptions[] = array('value'=>$order, 'label'=>$GLOBALS['TL_LANG']['ORDER'][$order]);
		}
		
		$objWidget->options = $arrOptions;
		
		// Valiate input
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$this->isProcessSubmit() && !$this->blnReadOnly)
		{
			$objWidget->validate();

			if ($objWidget->hasErrors())
			{
				$this->doNotSubmit = true;
			}
			
			$this->Input->setPost($objWidget->name, $objWidget->value);
			
			$this->Isotope->Order->status = $objWidget->value;
			
		}
				
		$objWidget->value = $this->Isotope->Order->status;
		$objWidget->disabled = $this->blnReadOnly;
		
		
		//Need to reset value for widget after we store the data
		$objTemplate->status = $objWidget->parse();
		$objTemplate->headline = $GLOBALS['TL_LANG']['tl_iso_orders']['status'];
		
		return $objTemplate->parse();
	}
	
	
	/**
	 * Generate the product interface step
	 *
	 * @return string
	 */
	protected function getProductInterface()
	{
		$objTemplate = new IsotopeTemplate('be_iso_order_products');
		
		$objWidget = new ProductCollectionWizard();

		$objWidget->id = 'products';
		$objWidget->name = 'products';
		$objWidget->collectionId = $this->Isotope->Order->id ? $this->Isotope->Order->id : 0;
		$objWidget->collectionType = 'IsotopeOrder';
		$objWidget->type = 'collectionWizard';
		$objWidget->searchFields = array('name', 'alias', 'sku', 'description');
		$objWidget->listFields = array('name', 'sku');
		$objWidget->foreignTable = 'tl_iso_products';
		$objWidget->label = 'Products';

		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$this->isProcessSubmit() && !$this->blnReadOnly)
		{
			$objWidget->validate();

			if ($objWidget->hasErrors())
			{
				$this->doNotSubmit = true;
			}
			
			$this->Input->setPost($objWidget->name, $objWidget->value);
						
			$this->arrOrderData['products'] = $objWidget->value;
			
		}
		//Need to reset value for widget after we store the data
		$objWidget->value = $this->getProductArray($this->Isotope->Order);

		$objTemplate->products =  $objWidget->parse();
		$objTemplate->headline = $GLOBALS['TL_LANG']['tl_iso_orders']['products'];
		
		return $objTemplate->parse();
	}
	
	
	/**
	 * Generate the messaging interface
	 */
	protected function getMessagesInterface()
	{
		$objTemplate = new IsotopeTemplate('be_iso_order_messages');
		
		$objWidget = new dcaWizard();

		$objWidget->id = 'messages';
		$objWidget->name = 'messages';
		$objWidget->foreignTable = 'tl_iso_messaging';
		$objWidget->label = $GLOBALS['TL_LANG']['tl_iso_messaging']['messages'];


		$objTemplate->messages =  $objWidget->parse();
		$objTemplate->headline = $GLOBALS['TL_LANG']['tl_iso_messaging']['messages'];
		
		return $objTemplate->parse();
	}
	
	
	/**
	 * Reset the address widget
	 *
	 * @param string
	 * @return string
	 */
	public function resetAddresses($strAction)
	{
		if($strAction=='resetAddresses')
		{
			$this->import('Isotope');
			$varValue = $this->replaceInsertTags( $this->getAddressInterface() );
			echo json_encode( array('token'=>REQUEST_TOKEN, 'content'=>$this->replaceInsertTags($varValue) ));
			exit;
		}
	}
	
	
	/**
	 * Generate the address interface
	 */
	protected function getAddressInterface()
	{
	
		$blnRequiresPayment = $this->Isotope->Order->requiresPayment;

		$objTemplate = new IsotopeTemplate('be_iso_order_address');
		
		$strBuffer = '';
		
		//Add in the Member table Lookup
		$arrLookup = array
		(
				'id'			=>	'member_lookup',
				'name'			=>	'member_lookup',
				'label'			=>  $GLOBALS['TL_LANG']['tl_iso_orders']['member_lookup'],
				'sqlWhere'		=> 'disable!=1',
				'searchLabel'	=> 'Search Members',
				'fieldType'		=> 'radio',
				'foreignTable'	=> 'tl_member',
				'searchFields'	=> array('firstname', 'lastname'),
				'listFields'	=> array('firstname', 'lastname'),
		);
			
		$objLookupWidget = new TableLookupWizard($arrLookup);
		$objLookupWidget->value = $this->Isotope->Order->pid ? $this->Isotope->Order->pid : '';
		
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$this->isProcessSubmit() && !$this->blnReadOnly)
		{
			$this->arrOrderData['user'] = $this->Input->post('member_lookup');
		}
		
		$strBuffer .= '<div id="member_lookup">' . $objLookupWidget->parse() . '</div>';	
		
		$objTemplate->headline = $blnRequiresPayment ? $GLOBALS['TL_LANG']['ISO']['billing_address'] : $GLOBALS['TL_LANG']['ISO']['customer_address'];
		$objTemplate->message = (FE_USER_LOGGED_IN ? $GLOBALS['TL_LANG']['ISO'][($blnRequiresPayment ? 'billing' : 'customer') . '_address_message'] : $GLOBALS['TL_LANG']['ISO'][($blnRequiresPayment ? 'billing' : 'customer') . '_address_guest_message']);
		$objTemplate->addressfields = $this->generateAddressWidget('billing_address') . $this->generateAddressWidget('shipping_address');
		$objTemplate->lookup = $strBuffer;
		
		$strBillingAddress = $this->Isotope->generateAddressString($this->Isotope->Order->billingAddress, $this->Isotope->Config->billing_fields);
		
		$strShippingAddress = $this->Isotope->Order->shippingAddress['id'] == -1 ? ($this->Isotope->Order->requiresPayment ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress']) : $this->Isotope->generateAddressString($this->Isotope->Order->shippingAddress, $this->Isotope->Config->shipping_fields);

		$this->arrOrderData['billing_address_html'] 			= $strBillingAddress;
		$this->arrOrderData['billing_address_text']		= str_replace('<br />', "\n", $strBillingAddress);
		$this->arrOrderData['shipping_address_html']			= $strShippingAddress;
		$this->arrOrderData['shipping_address_text']	= str_replace('<br />', "\n", $strShippingAddress);
		
		return $this->Input->post('isAjax') ? $objTemplate->addressfields : $objTemplate->parse();
	}
	

	/**
	 * Generate the shipping modules interface
	 */
	protected function getShippingModulesInterface()
	{
		
		$arrModules = array();
		$objModules = $this->Database->execute("SELECT * FROM tl_iso_shipping_modules WHERE enabled='1'");
		$arrData = $this->Input->post('shipping') ? $this->Input->post('shipping') : array('module'=>$this->Isotope->Order->shipping_id);
		
		while ( $objModules->next() )
		{
			$strClass = $GLOBALS['ISO_SHIP'][$objModules->type];

			if (!strlen($strClass) || !$this->classFileExists($strClass))
				continue;

			$objModule = new $strClass($objModules->row());

			if (!$objModule->available)
				continue;

			if (is_array($arrData) && $arrData['module'] == $objModule->id)
 			{
 				$this->arrOrderData['shipping'] = $arrData;
 			}

 			if (is_array($this->arrOrderData['shipping']) && $this->arrOrderData['shipping']['module'] == $objModule->id)
 			{
 				$this->Isotope->Order->Shipping = $objModule;
 			}
 			
 			$fltPrice = $objModule->price; 			
 			$strSurcharge = $objModule->surcharge;
 			$strPrice = $fltPrice != 0 ? (($strSurcharge == '' ? '' : ' ('.$strSurcharge.')') . ': '.$this->Isotope->formatPriceWithCurrency($fltPrice)) : '';
 			 			
 			$arrModules[] = array
 			(
 				'id'		=> $objModule->id,
 				'label'		=> $objModule->label,
 				'price'		=> $strPrice,
 				'checked'	=> (($this->Isotope->Order->Shipping->id == $objModule->id || $objModules->numRows==1) ? ' checked="checked"' : ''),
 				'note'		=> $objModule->note,
 				'options'	=> $objModule->getShippingOptions($this),
 			);

 			$objLastModule = $objModule;
		}

		$objTemplate = new IsotopeTemplate('be_iso_order_shipping_method');
		if(!count($arrModules))
		{
			$this->doNotSubmit = true;
			$this->Template->showNext = false;

			$objTemplate = new FrontendTemplate('mod_message');
			$objTemplate->class = 'shipping_method';
			$objTemplate->hl = 'h2';
			$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['shipping_method'];
			$objTemplate->type = 'error';
			$objTemplate->message = $GLOBALS['TL_LANG']['MSC']['noShippingModules'];
			return $objTemplate->parse();
		}
		elseif (!$this->Isotope->Order->hasShipping && !strlen($this->arrOrderData['shipping']['module']) && count($arrModules) == 1)
		{
			$this->Isotope->Order->Shipping = $objLastModule;
			$this->arrOrderData['shipping']['module'] = $this->Isotope->Order->Shipping->id;
		}
		elseif (!$this->Isotope->Order->hasShipping)
		{
			if ($this->Input->post('FORM_SUBMIT') != '')
			{
				$objTemplate->error = $GLOBALS['TL_LANG']['ISO']['shipping_method_missing'];
			}

			$this->doNotSubmit = true;
		}

		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['shipping_method'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['shipping_method_message'];
		$objTemplate->shippingMethods = $arrModules;

		if (!$this->doNotSubmit)
		{
			$this->arrOrderData['shipping_method_id']	= $this->Isotope->Order->Shipping->id;
			$this->arrOrderData['shipping_method']		= $this->Isotope->Order->Shipping->label;
			$this->arrOrderData['shipping_note']		= $this->Isotope->Order->Shipping->note;
			$this->arrOrderData['shipping_note_text']	= strip_tags($this->Isotope->Order->Shipping->note);
		}

		return $objTemplate->parse();
	}

	
	
	/**
	 * Generate the payment module interface
	 */
	protected function getPaymentModulesInterface()
	{
		$arrData = $this->Input->post('payment');
		if(!isset($_POST['processPayment']))
		{
			$this->Input->setPost('payment', null);
		}
		unset($_SESSION['CHECKOUT_DATA']['payment']['request_lockout']);
		
		$this->Isotope->Order->unlock();
		
		$blnHideForm = false;
		
		if($this->blnReadOnly || $this->Isotope->Order->payment_data['transaction-id'])
		{			
			//$strBuffer = $this->Isotope->Order->hasPayment ? $this->Isotope->Cart->Order->processPayment() : true;

			//if ($strBuffer === true)
			//{
				$this->blnReadOnly = true;
				$blnHideForm = true;
			//}
		}
		
		$arrModules = array();

		$objModules = $this->Database->execute("SELECT * FROM tl_iso_payment_modules WHERE enabled='1'");

		while( $objModules->next() )
		{
			$strClass = $GLOBALS['ISO_PAY'][$objModules->type];

			if (!strlen($strClass) || !$this->classFileExists($strClass))
				continue;

			$objModule = new $strClass($objModules->row());

			if (!$objModule->available)
				continue;

			if (is_array($arrData) && $arrData['module'] == $objModule->id)
 			{
 				$this->arrOrderData['payment'] = $arrData;
 			}

 			if (is_array($this->arrOrderData['payment']) && $this->arrOrderData['payment']['module'] == $objModule->id)
 			{
 				$this->Isotope->Order->Payment = $objModule;
 			}

 			$fltPrice = $objModule->price;
 			$strSurcharge = $objModule->surcharge;
 			$strPrice = $fltPrice != 0 ? (($strSurcharge == '' ? '' : ' ('.$strSurcharge.')') . ': '.$this->Isotope->formatPriceWithCurrency($fltPrice)) : '';

 			$arrModules[] = array
 			(
 				'id'		=> $objModule->id,
 				'label'		=> $objModule->label,
 				'price'		=> $strPrice,
 				'checked'	=> (($this->Isotope->Order->Payment->id == $objModule->id || $objModules->numRows==1) ? ' checked="checked"' : ''),
 				'note'		=> $objModule->note,
 				'form'		=> ($blnHideForm ? $this->paymentData($objModule) : ($objModule->checkoutForm() ? $objModule->checkoutForm() : $objModule->paymentForm($this))),
 			);

 			$objLastModule = $objModule;
		}
		
		$this->Isotope->Order->reloadPaymentData();

		$objTemplate = new IsotopeTemplate('be_iso_order_payment_method');

		if(!count($arrModules))
		{
			$this->doNotSubmit = true;
			$this->Template->showNext = false;

			$objTemplate = new FrontendTemplate('mod_message');
			$objTemplate->class = 'payment_method';
			$objTemplate->hl = 'h2';
			$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['payment_method'];
			$objTemplate->type = 'error';
			$objTemplate->message = $GLOBALS['TL_LANG']['MSC']['noPaymentModules'];
			return $objTemplate->parse();
		}
		elseif (!$this->Isotope->Order->hasPayment && !strlen($this->arrOrderData['payment']['module']) && count($arrModules) == 1)
		{
			$this->Isotope->Order->Payment = $objLastModule;
			$this->arrOrderData['payment']['module'] = $this->Isotope->Order->Payment->id;
		}
		elseif (!$this->Isotope->Order->hasPayment)
		{
			if ($this->Input->post('FORM_SUBMIT') != '')
			{
				$objTemplate->error = $GLOBALS['TL_LANG']['ISO']['payment_method_missing'];
			}

			$this->doNotSubmit = true;
		}

		$objTemplate->headline = $GLOBALS['TL_LANG']['ISO']['payment_method'];
		$objTemplate->message = $GLOBALS['TL_LANG']['ISO']['payment_method_message'];
		$objTemplate->paymentMethods = $arrModules;
		$objTemplate->hideForm = $this->blnReadOnly;
		$objTemplate->sLabel = $GLOBALS['TL_LANG']['MSC']['processPayment'];

		if (!$this->doNotSubmit)
		{
			$this->processOrder();
			
			$this->arrOrderData['payment_method_id']	= $this->Isotope->Order->Payment->id;
			$this->arrOrderData['payment_method']		= $this->Isotope->Order->Payment->label;
			$this->arrOrderData['payment_note']			= $this->Isotope->Order->Payment->note;
			$this->arrOrderData['payment_note_text']	= strip_tags($this->Isotope->Order->Payment->note);
		}
		
		$this->Isotope->Order->lock();

		return $objTemplate->parse();
	}
	
	
	protected function processOrder()
	{
		if ($this->Isotope->Order->hasPayment && $this->isProcessSubmit() && !$this->blnReadOnly)
		{
			$this->log('AF-1', __METHOD__, TL_GENERAL);
			
			if ($this->Isotope->Order->Payment->processPayment())
			{
				$this->log('AF-2', __METHOD__, TL_GENERAL);
				
				$this->blnReadOnly = true;
				
				if ($this->Isotope->Order->checkout())
				{
					$this->Isotope->Order->status = 'complete';	
				}
			}				
		}	
	}


	protected function writeOrder()
	{
		$objOrder = new IsotopeBackendOrder();
		
		if (!$objOrder->findBy('id', $this->Isotope->Order->id))
		{
			$objOrder->uniqid		= uniqid($this->Isotope->Config->orderPrefix, true);
			$objOrder->cart_id		= 0; //Need to set this first
			$objOrder->findBy('id', $objOrder->save());
			$this->Isotope->Order = $objOrder;
			$this->Isotope->Cart = $this->Isotope->Order;
		}
		
		$objOrder->cart_id = $objOrder->id;
		
		//Add products
		$arrProducts = $this->arrOrderData['products'];

		foreach($arrProducts as $product)
		{
			$intProductId = $product['options'] ? $product['options'] : $product['product'];
			
			$objProductData = $this->Database->prepare("SELECT *, (SELECT class FROM tl_iso_producttypes WHERE tl_iso_products.type=tl_iso_producttypes.id) AS product_class FROM tl_iso_products WHERE id={$intProductId}")->limit(1)->execute();

			$strClass = $GLOBALS['ISO_PRODUCT'][$objProductData->product_class]['class'];

			try
			{
				$objProduct = new $strClass($objProductData->row());
			}
			catch (Exception $e)
			{
				$objProduct = new IsotopeProduct(array('id'=>$intProductId));
			}
						
			//Product exists - update it
			if (in_array($objProduct->id, $this->getProductArray($this->Isotope->Order)))
			{
				$objItem = $this->Database->prepare("SELECT id FROM tl_iso_order_items WHERE pid={$this->Isotope->Order->id} AND product_id={$objProduct->id}")->limit(1)->execute();
				$objProduct->cart_id = $objItem->id;
				$arrSet = array('product_quantity'=>$product['qty'], 'price'=>$product['price'], 'href_reader'=>'');
				$blnInsert = $this->Isotope->Order->updateProduct($objProduct, $arrSet);
			}
			//Add new product
			else
			{
				$objProduct->price = $product['price'];
				$objProduct->reader_jumpTo = '';
				$blnInsert = $this->Isotope->Order->addProduct($objProduct, $product['qty']);
			}

		}
		
		$objOrder->pid				= $this->arrOrderData['user'];
		$objOrder->date				= time();
		$objOrder->config_id		= (int)$this->Isotope->Config->id;
		$objOrder->shipping_id		= ($this->Isotope->Order->hasShipping ? $this->Isotope->Order->Shipping->id : 0);
		$objOrder->payment_id		= ($this->Isotope->Order->hasPayment ? $this->Isotope->Order->Payment->id : 0);
		
		//Temporarily unlock the order to retrieve/set values like a cart
		$this->Isotope->Order->unlock();
		$objOrder->subTotal			= $this->Isotope->Order->subTotal;
		$objOrder->taxTotal			= $this->Isotope->Order->taxTotal;
		$objOrder->shippingTotal	= $this->Isotope->Order->shippingTotal;
		$objOrder->grandTotal		= $this->Isotope->Order->grandTotal;
		$objOrder->surcharges		= $this->Isotope->Order->getSurcharges();
		$this->Isotope->Order->lock();
		//Relock
		
		$objOrder->checkout_info	= $this->getCheckoutInfo();
		$objOrder->status			= 'pending';
		$objOrder->language			= $GLOBALS['TL_LANGUAGE'];
		$objOrder->billing_address	= $this->Isotope->Order->billingAddress;
		$objOrder->shipping_address	= $this->Isotope->Order->shippingAddress;
		$objOrder->currency			= $this->Isotope->Config->currency;

		$objOrder->iso_customer_email	= '';
		$objOrder->iso_sales_email		= $this->iso_sales_email ? $this->iso_sales_email : ($GLOBALS['TL_ADMIN_NAME'] != '' ? sprintf('%s <%s>', $GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']) : $GLOBALS['TL_ADMIN_EMAIL']);
		$objOrder->iso_mail_admin		= $this->iso_mail_admin;
		$objOrder->iso_mail_customer	= $this->iso_mail_customer;
		$objOrder->iso_addToAddressbook	= $this->iso_addToAddressbook;
		$objOrder->new_order_status		= ($this->Isotope->Order->hasPayment ? $this->Isotope->Order->Payment->new_order_status : 'pending');

		$arrData = array_merge($this->arrOrderData, array
		(
			'cart_id'					=> $this->Isotope->Order->id,
			'items'						=> $this->Isotope->Order->items,
			'products'					=> $this->Isotope->Order->products,
			'subTotal'					=> $this->Isotope->formatPriceWithCurrency($this->Isotope->Order->subTotal, false),
			'taxTotal'					=> $this->Isotope->formatPriceWithCurrency($this->Isotope->Order->taxTotal, false),
			'shippingPrice'				=> $this->Isotope->formatPriceWithCurrency($this->Isotope->Order->Shipping->price, false),
			'paymentPrice'				=> $this->Isotope->formatPriceWithCurrency($this->Isotope->Order->Payment->price, false),
			'grandTotal'				=> $this->Isotope->formatPriceWithCurrency($this->Isotope->Order->grandTotal, false),
			'cart_text'					=> $this->replaceInsertTags($this->Isotope->Order->getProducts('iso_products_text')),
			'cart_html'					=> $this->replaceInsertTags($this->Isotope->Order->getProducts('iso_products_html')),
		));

		foreach( $this->Isotope->Order->billingAddress as $k => $v )
		{
			$arrData['billing_'.$k] = $this->Isotope->formatValue('tl_iso_addresses', $k, $v);
		}

		foreach( $this->Isotope->Order->shippingAddress as $k => $v )
		{
			$arrData['shipping_'.$k] = $this->Isotope->formatValue('tl_iso_addresses', $k, $v);
		}

		$objOrder->email_data = $arrData;

		$objOrder->save();
		
		//Need to manually set the order_id, since it is not 
		if(!$this->Isotope->Order->order_id)
		{
			$this->generateOrderId();
		}
				
		return $objOrder;
	}


	protected function generateAddressWidget($field)
	{
		$strBuffer = '<div id="'. $field . '">';
		$strBuffer .= '<h2>'. $GLOBALS['TL_LANG']['ISO'][$field] .'</h2>';
		$arrOptions['normal'] = array();
		$arrOptions['ajax'] = array();
		$this->import('Isotope');
		$intMember = 0;
				
		if ($this->Input->post('isAjax') && $this->Input->post('data') && $this->Input->post('action')=='resetAddresses')
		{
			$intMember = $this->Input->post('data');
		}
		else
		{
			$intMember =  $this->Isotope->Order->pid;
		}
		
		if($intMember > 0)
		{
			$objAddress = $this->Database->execute("SELECT * FROM tl_iso_addresses WHERE pid={$intMember} AND store_id={$this->Isotope->Config->store_id} ORDER BY isDefaultBilling DESC, isDefaultShipping DESC");
						
			while( $objAddress->next() )
			{
				$arrData = $objAddress->row();
				$arrData['country'] = strtolower($arrData['country']);
				$arrOptions['ajax'][] = array
				(
					'value'		=> $objAddress->id,
					'label'		=> $this->Isotope->generateAddressString($arrData, ($field == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields)),
				);
			}
						
		}
		
		switch($field)
		{
			case 'shipping_address':
				$arrAddress = $this->arrOrderData[$field] ? $this->arrOrderData[$field] : $this->Isotope->Order->shippingAddress;
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : -1;

				array_insert($arrOptions['normal'], 0, array(array
				(
					'value'	=> -1,
					'label' => $GLOBALS['TL_LANG']['MSC']['useBillingAddress'],
				)));

				$arrOptions['normal'][] = array
				(
					'value'	=> 0,
					'label' => $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'],
				);
				break;

			case 'billing_address':
			default:
				$arrAddress = $this->arrOrderData[$field] ? $this->arrOrderData[$field] : $this->Isotope->Order->billingAddress;
				$intDefaultValue = strlen($arrAddress['id']) ? $arrAddress['id'] : 0;
				if(count($arrOptions['ajax']))
				{
					$arrOptions['normal'][] = array
					(
						'value'	=> 0,
						'label' => $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'],
					);
				}
				
				
				break;
		}
		
		if (count($arrOptions['ajax']) || count($arrOptions['normal']) || $this->Input->post($field) > 0) //Special check for addresses loaded via AJAX
		{		
			$strClass = $GLOBALS['TL_FFL']['radio'];

			$arrData = array('id'=>$field, 'name'=>$field, 'mandatory'=>true);

			$objWidget = new $strClass($arrData);
			$objWidget->options = array_merge($arrOptions['normal'], $arrOptions['ajax']);
			$objWidget->value = $intDefaultValue;
			$objWidget->storeValues = true;
			$objWidget->tableless = true;

			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$this->blnReadOnly)
			{
				$objWidget->validate();

				if ($objWidget->hasErrors())
				{
					$this->doNotSubmit = true;
				}
				else
				{
					$this->arrOrderData[$field]['id'] = $objWidget->value;
					
					//Replacement for lack of address set/get in IsotopeOrder
					if($objWidget->value>0)
					{
						$arrAddress = array();
						$objAddress = $this->Database->prepare("SELECT * FROM tl_iso_addresses WHERE id=?")->limit(1)->execute($objWidget->value);
						if ($objAddress->numRows)
						{
							$arrAddress =  $objAddress->fetchAssoc();
						}
						elseif($this->Input->post('member_lookup'))
						{
							//get default user data
							$arrMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id=?")->limit(1)->execute($this->Input->post('member_lookup'))->fetchAssoc();
							$arrAddress = array_intersect_key(array_merge($arrMember, array('id'=>0, 'street_1'=>$arrMember['street'], 'subdivision'=>strtoupper($arrMember['country'] . '-' . $arrMember['state']))), array_flip($this->Isotope->Config->billing_fields_raw));
							
						}
						
						if(count($arrAddress))
						{
							$this->Isotope->Order->$field = $arrAddress;
						}
					}
					elseif($objWidget->value==-1)
					{
						//Shipping Address
						$this->Isotope->Order->$field = array_merge($this->Isotope->Order->billingAddress, array('id' => -1));
					
					}
				}
			}
			elseif ($objWidget->value != '')
			{
				$this->Input->setPost($objWidget->name, $objWidget->value);

				$objValidator = clone $objWidget;
				$objValidator->validate();

				if ($objValidator->hasErrors())
				{
					$this->doNotSubmit = true;
				}
			}

			$strBuffer .= $objWidget->parse();
		}


		$strBuffer .= '<div id="' . $field . '_new" class="address_new"' . (((!FE_USER_LOGGED_IN && $field == 'billing_address') || $objWidget->value == 0) ? '>' : ' style="display:none">');
		$strBuffer .= '<span>' . $this->generateAddressWidgets($field, count($arrOptions)) . '</span>';
		$strBuffer .= '</div>';
		
		$strBuffer .= '</div>';

		return $strBuffer;
	}


	/**
	 * Generate the current step widgets.
	 * strResourceTable is used either to load a DCA or else to gather settings related to a given DCA.
	 *
	 * @todo <table...> was in a template, but I don't get why we need to define the table here?
	 */
	protected function generateAddressWidgets($strAddressType, $intOptions)
	{
		$arrBuffer = array();

		$this->loadLanguageFile('tl_iso_addresses');
		$this->loadDataContainer('tl_iso_addresses');

		$arrFields = ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_fields : $this->Isotope->Config->shipping_fields);
		$arrDefault = $this->Isotope->Order->$strAddressType;

		if ($arrDefault['id'] == -1)
			$arrDefault = array();

		foreach( $arrFields as $field )
		{
			$arrData = $GLOBALS['TL_DCA']['tl_iso_addresses']['fields'][$field['value']];

			if (!is_array($arrData) || !$arrData['eval']['feEditable'] || !$field['enabled'] || ($arrData['eval']['membersOnly'] && !FE_USER_LOGGED_IN))
				continue;

			$strClass = $GLOBALS['TL_FFL'][$arrData['inputType']];

			// Continue if the class is not defined
			if (!$this->classFileExists($strClass))
				continue;

			// Special field "country"
			if ($field['value'] == 'country')
			{
				$arrCountries = ($strAddressType == 'billing_address' ? $this->Isotope->Config->billing_countries : $this->Isotope->Config->shipping_countries);

				$arrData['options'] = array_values(array_intersect($arrData['options'], $arrCountries));
				$arrData['default'] = $this->Isotope->Config->country;
			}

			// Special field type "conditionalselect"
			elseif (strlen($arrData['eval']['conditionField']))
			{
				$arrData['eval']['conditionField'] = $strAddressType . '_' . $arrData['eval']['conditionField'];
			}

			// Special fields "isDefaultBilling" & "isDefaultShipping"
			elseif (($field['value'] == 'isDefaultBilling' && $strAddressType == 'billing_address' && $intOptions < 2) || ($field['value'] == 'isDefaultShipping' && $strAddressType == 'shippping_address' && $intOptions < 3))
			{
				$arrDefault[$field['value']] = '1';
			}

			$i = count($arrBuffer);

			$objWidget = new $strClass($this->prepareForWidget($arrData, $strAddressType . '_' . $field['value'], (strlen($this->arrOrderData[$strAddressType][$field['value']]) ? $this->arrOrderData[$strAddressType][$field['value']] : $arrDefault[$field['value']])));

			$objWidget->mandatory = false;
			$objWidget->required = $objWidget->mandatory;
			$objWidget->tableless = $this->tableless;
			$objWidget->label = $field['label'] ? $this->Isotope->translate($field['label']) : $objWidget->label;
			$objWidget->storeValues = true;
			$objWidget->rowClass = 'row_'.$i . (($i == 0) ? ' row_first' : '') . ((($i % 2) == 0) ? ' even' : ' odd');

			// Validate input
			if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && ($this->Input->post($strAddressType) === '0' || $this->Input->post($strAddressType) == '') && !$this->blnReadOnly)
			{
				$objWidget->validate();

				$varValue = $objWidget->value;

				// Convert date formats into timestamps
				if (strlen($varValue) && in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')))
				{
					$objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$arrData['eval']['rgxp'] . 'Format']);
					$varValue = $objDate->tstamp;
				}

				// Do not submit if there are errors
				if ($objWidget->hasErrors())
				{
					$this->doNotSubmit = true;
				}

				// Store current value
				elseif ($objWidget->submitInput())
				{
					$arrAddress[$field['value']] = $varValue;
				}
			}
			elseif ($this->Input->post($strAddressType) === '0' || $this->Input->post($strAddressType) == '')
			{
				$this->Input->setPost($objWidget->name, $objWidget->value);

				$objValidator = clone $objWidget;
				$objValidator->validate();

				if ($objValidator->hasErrors())
				{
					$this->doNotSubmit = true;
				}
			}

			$arrBuffer[] = $objWidget->parse();
		}

		// Add row_last class to the last widget
		array_pop($arrBuffer);
		$objWidget->rowClass = 'row_'.$i . (($i == 0) ? ' row_first' : '') . ' row_last' . ((($i % 2) == 0) ? ' even' : ' odd');
		$arrBuffer[] = $objWidget->parse();

		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId && !$this->doNotSubmit && is_array($arrAddress) && count($arrAddress) && !$this->blnReadOnly)
		{
			$arrAddress['id'] = 0;
			$this->arrOrderData[$strAddressType] = $arrAddress;
		}

		if (is_array($this->arrOrderData[$strAddressType]) && $this->arrOrderData[$strAddressType]['id'] === 0)
		{
			$this->Isotope->Order->$strAddressType = $this->arrOrderData[$strAddressType];
		}

		if ($this->tableless)
		{
			return implode('', $arrBuffer);
		}
		
		return '<table cellspacing="0" cellpadding="0" summary="Form fields">
' . implode('', $arrBuffer) . '
</table>';
	}


	protected function getCheckoutInfo()
	{
		if (!is_array($this->arrCheckoutInfo))
		{
			// Run trough all steps to collect checkout information
			$arrCheckoutInfo = array();
			foreach( $GLOBALS['ISO_ORDER_STEPS'] as $step => $arrCallbacks )
			{
				foreach( $arrCallbacks as $callback )
				{
					if ($callback[0] == 'ModuleIsotopeOrderBackend')
					{
						$arrInfo = $this->{$callback[1]}(true);
					}
					else
					{
						$this->import($callback[0]);
						$arrInfo = $this->{$callback[0]}->{$callback[1]}($this, true);
					}

					if (is_array($arrInfo) && count($arrInfo))
					{
						$arrCheckoutInfo += $arrInfo;
					}
				}
			}

			reset($arrCheckoutInfo);
			$arrCheckoutInfo[key($arrCheckoutInfo)]['class'] .= ' first';
			end($arrCheckoutInfo);
			$arrCheckoutInfo[key($arrCheckoutInfo)]['class'] .= ' last';

			$this->arrCheckoutInfo = $arrCheckoutInfo;
		}

		return $this->arrCheckoutInfo;
	}


	/**
	 * Return an array of IDs for the order's products
	 * @param object
	 * @return array
	 */
	protected function getProductArray($objOrder)
	{
		if(!$objOrder->id)
			return array();
		
		$arrProducts = $objOrder->getProducts();
		$arrIDs = array();
		foreach($arrProducts as $objProduct)
		{
			$arrIDs[] = $objProduct->id;
		}

		return $arrIDs;
	}
	
	/**
	 * Generate the next higher Order-ID based on config prefix, order number digits and existing records
	 */
	private function generateOrderId()
	{
		if ($this->Isotope->Order->order_id != '')
			return $this->Isotope->Order->order_id;

		$strPrefix = $this->Isotope->Config->orderPrefix;
		$arrConfigIds = $this->Database->execute("SELECT id FROM tl_iso_config WHERE store_id=" . $this->Isotope->Config->store_id)->fetchEach('id');
		
		// Lock tables so no other order can get the same ID
		$this->Database->lockTables(array('tl_iso_orders'));
		
		// Retrieve the highest available order ID
		$objMax = $this->Database->prepare("SELECT order_id FROM tl_iso_orders WHERE order_id LIKE '$strPrefix%' AND config_id IN (" . implode(',', $arrConfigIds) . ") ORDER BY order_id DESC")->limit(1)->executeUncached();
		$intMax = (int)substr($objMax->order_id, strlen($strPrefix));
		$strOrderID = $strPrefix . str_pad($intMax+1, $this->Isotope->Config->orderDigits, '0', STR_PAD_LEFT);
		
		$this->Database->query("UPDATE tl_iso_orders SET order_id='{$strOrderID}' WHERE id={$this->Isotope->Order->id}");
		$this->Database->unlockTables();
		
		return $strOrderID;
	}
	
	
	/**
	 * Return a textfield widget
	 * @param string
	 * @param string
	 * @param string
	 * @return string
	 */
	protected function getTextInput($strName, $varValue=null, $strLabel)
	{
		$widget = new TextField();

		$widget->id = $strName;
		$widget->name = $strName;
		$widget->decodeEntities = true;
		$widget->value = $this->Isotope->formatPrice($varValue);
		$widget->tableless = true;
		$widget->label = $strLabel;
		$strClass = 'iso_input';
		
		if($this->blnReadOnly)
		{
			$widget->readonly = true;
			$widget->disabled = true;
			$strClass .= ' readonly';
		}
		
		$widget->class = $strClass;

		// Validate input
		if ($this->Input->post('FORM_SUBMIT') == $this->strFormId)
		{
			$widget->validate();

			if ($widget->hasErrors())
			{
				$this->doNotSubmit = true;
			}
			
			$this->Isotope->Order->$strName = !$this->blnReadOnly ? $this->Isotope->formatPrice($this->Input->post($strName)) : $this->Isotope->Order->$strName;
			
		}
		
		

		return $widget->parse();
	}
	
	
	
	/**
	 * Return payment data
	 * @param object
	 * @return string
	 */
	protected function paymentData($objModule)
	{
		$strBuffer = '<p>';
		
		$arrData = $this->Isotope->Order->payment_data;	
		
		foreach($arrData as $k=>$v)
		{
			$strBuffer .= $k .': ' . $v . '<br />';
		}
		
		$strBuffer .= '</p>';

		return $strBuffer;
	}
	
	
	protected function isProcessSubmit()
	{
		return (isset($_POST) && isset($_POST['processPayment'])) ? true : false;
	}

}

