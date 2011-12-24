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
 * @copyright  Isotope eCommerce Workgroup 2009-2011
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class IsotopeBackendOrder extends IsotopeOrder
{

	/**
	 * Lock the record
	 * 
	 * @return void
	 */
	public function lock()
	{
		$this->blnLocked = true;
	}
	
	/**
	 * Unlock the record temporarily so that you can retrieve things like subTotals like a Cart would
	 * 
	 * @return void
	 */
	public function unlock()
	{
		//Call getProducts() to set $this->arrProducts array with existing locked data
		$this->getProducts();
		$this->blnLocked = false;
	}
	

	/**
	 * Override parent function to act more like a cart in the backend
	 *
	 * @access	public
	 * @param	array
	 * @return	array
	 */
	public function getSurcharges()
	{
		//If locked, we return the locked values
		if($this->blnLocked)
		{
			$arrSurcharges = deserialize($this->surcharges);
			return is_array($arrSurcharges) ? $arrSurcharges : array();
		}
		else
		{
		
			if (isset($this->arrCache['surcharges']))
				return $this->arrCache['surcharges'];
	
			$this->import('Isotope');
	
			$arrPreTax = $arrPostTax = $arrTaxes = array();
	
			$arrSurcharges = array();
			if (isset($GLOBALS['ISO_HOOKS']['checkoutSurcharge']) && is_array($GLOBALS['ISO_HOOKS']['checkoutSurcharge']))
			{
				foreach ($GLOBALS['ISO_HOOKS']['checkoutSurcharge'] as $callback)
				{
					if ($callback[0] == 'IsotopeBackendOrder')
					{
						$arrSurcharges = $this->{$callback[1]}($arrSurcharges);
					}
					else
					{
						$this->import($callback[0]);
						$arrSurcharges = $this->{$callback[0]}->{$callback[1]}($arrSurcharges);
					}
				}
			}
	
			foreach( $arrSurcharges as $arrSurcharge )
			{
				if ($arrSurcharge['before_tax'])
				{
					$arrPreTax[] = $arrSurcharge;
				}
				else
				{
					$arrPostTax[] = $arrSurcharge;
				}
			}
	
			$arrProducts = $this->getProducts();
			foreach( $arrProducts as $pid => $objProduct )
			{
				$fltPrice = $objProduct->tax_free_total_price;
				foreach( $arrPreTax as $tax )
				{
					if (isset($tax['products'][$objProduct->cart_id]))
					{
						$fltPrice += $tax['products'][$objProduct->cart_id];
					}
				}
	
				$arrTaxIds = array();
				$arrTax = $this->Isotope->calculateTax($objProduct->tax_class, $fltPrice);
	
				if (is_array($arrTax))
				{
					foreach ($arrTax as $k => $tax)
					{
						if (array_key_exists($k, $arrTaxes))
						{
							$arrTaxes[$k]['total_price'] += $tax['total_price'];
	
							if (is_numeric($arrTaxes[$k]['price']) && is_numeric($tax['price']))
							{
								$arrTaxes[$k]['price'] += $tax['price'];
							}
						}
						else
						{
							$arrTaxes[$k] = $tax;
						}
	
						$taxId = array_search($k, array_keys($arrTaxes)) + 1;
						$arrTaxes[$k]['tax_id'] = $taxId;
						$arrTaxIds[] = $taxId;
					}
				}
	
	
				$strTaxId = implode(',', $arrTaxIds);
				if ($objProduct->tax_id != $strTaxId)
				{
					$this->updateProduct($objProduct, array('tax_id'=>$strTaxId));
				}
			}
	
	
			foreach( $arrPreTax as $i => $arrSurcharge )
			{
				if (!$arrSurcharge['tax_class'])
					continue;
	
				$arrTaxIds = array();
				$arrTax = $this->Isotope->calculateTax($arrSurcharge['tax_class'], $arrSurcharge['total_price'], $arrSurcharge['before_tax']);
	
				if (is_array($arrTax))
				{
					foreach ($arrTax as $k => $tax)
					{
						if (array_key_exists($k, $arrTaxes))
						{
							$arrTaxes[$k]['total_price'] += $tax['total_price'];
	
							if (is_numeric($arrTaxes[$k]['price']) && is_numeric($tax['price']))
							{
								$arrTaxes[$k]['price'] += $tax['price'];
							}
						}
						else
						{
							$arrTaxes[$k] = $tax;
						}
	
						$taxId = array_search($k, array_keys($arrTaxes)) + 1;
						$arrTaxes[$k]['tax_id'] = $taxId;
						$arrTaxIds[] = $taxId;
					}
				}
	
				$arrPreTax[$i]['tax_id'] = implode(',', $arrTaxIds);
			}
	
			$this->arrCache['surcharges'] = array_merge($arrPreTax, $arrTaxes, $arrPostTax);
			return $this->arrCache['surcharges'];
		}
	}
	

	/**
	 * Override parent function for use in backend
	 *
	 * @access	public
	 * @param	object
	 * @return	boolean
	 */
	public function checkout()
	{
		if ($this->checkout_complete)
		{
			return true;
		}

		$this->import('Isotope');

		// This is the case when not using ModuleIsotopeCheckout
		/*if (!is_object($objCart))
		{
			$objCart = new IsotopeCart();
			if (!$objCart->findBy('id', $this->cart_id))
			{
				$this->log('Cound not find Cart ID '.$this->cart_id.' for Order ID '.$this->id, __METHOD__, TL_ERROR);
				return false;
			}

			// Set the current system to the language when the user placed the order.
			// This will result in correct e-mails and payment description.
			$GLOBALS['TL_LANGUAGE'] = $this->language;
			$this->loadLanguageFile('default');

			// Initialize system
			$this->Isotope->overrideConfig($this->config_id);
			$this->Isotope->Cart = $objCart;
		}*/

		// HOOK: process checkout
		if (isset($GLOBALS['ISO_HOOKS']['preCheckout']) && is_array($GLOBALS['ISO_HOOKS']['preCheckout']))
		{
			foreach ($GLOBALS['ISO_HOOKS']['preCheckout'] as $callback)
			{
				$this->import($callback[0]);

				if ($this->$callback[0]->$callback[1]($this, $this) === false)
				{
					$this->log('Callback "'.$callback[0].':'.$callback[1].'" cancelled checkout for Order ID '.$this->id, __METHOD__, TL_ERROR);
					return false;
				}
			}
		}

		$arrItemIds = $this->Database->prepare("SELECT id FROM tl_iso_order_items WHERE pid=?")->execute($this->id)->fetchEach('id');	// Changed from parent
		//$objCart->delete();

		$this->checkout_complete = true;
		$this->status = $this->new_order_status;
		$arrData = $this->email_data;
		$arrData['order_id'] = $this->generateOrderId();

		foreach( $this->billing_address as $k => $v )
		{
			$arrData['billing_'.$k] = $this->Isotope->formatValue('tl_iso_addresses', $k, $v);
		}

		foreach( $this->shipping_address as $k => $v )
		{
			$arrData['shipping_'.$k] = $this->Isotope->formatValue('tl_iso_addresses', $k, $v);
		}

		if ($this->pid > 0)
		{
			$objUser = $this->Database->execute("SELECT * FROM tl_member WHERE id=".(int)$this->pid);
			foreach( $objUser->row() as $k => $v )
			{
				$arrData['member_'.$k] = $this->Isotope->formatValue('tl_member', $k, $v);
			}
		}

		$this->log('Order ID ' . $this->id . ' has been confirmed', __METHOD__, TL_ACCESS);	// Changed from parent

		if ($this->iso_mail_admin && $this->iso_sales_email != '')
		{
			$this->Isotope->sendMail($this->iso_mail_admin, $this->iso_sales_email, $this->language, $arrData, $this->iso_customer_email, $this);
		}

		if ($this->iso_mail_customer && $this->iso_customer_email != '')
		{
			$this->Isotope->sendMail($this->iso_mail_customer, $this->iso_customer_email, $this->language, $arrData, '', $this);
		}
		else
		{
			$this->log('Unable to send customer confirmation for order ID '.$this->id, 'IsotopeOrder checkout()', TL_ERROR);
		}

		// Store address in address book
		if ($this->iso_addToAddressbook && $this->pid > 0)
		{
			$time = time();

			foreach( array('billing', 'shipping') as $address )
			{
				$arrData = deserialize($this->arrData[$address.'_address'], true);

				if ($arrData['id'] == 0)
				{
					$arrAddress = array_intersect_key($arrData, array_flip($this->Isotope->Config->{$address.'_fields_raw'}));
					$arrAddress['pid'] = $this->pid;
					$arrAddress['tstamp'] = $time;
					$arrAddress['store_id'] = $this->Isotope->Config->store_id;

					$this->Database->prepare("INSERT INTO tl_iso_addresses %s")->set($arrAddress)->execute();
				}
			}
		}

		// HOOK: process checkout
		if (isset($GLOBALS['ISO_HOOKS']['postCheckout']) && is_array($GLOBALS['ISO_HOOKS']['postCheckout']))
		{
			foreach ($GLOBALS['ISO_HOOKS']['postCheckout'] as $callback)
			{
				$this->import($callback[0]);
				$this->$callback[0]->$callback[1]($this, $arrItemIds);
			}
		}

		$this->save();

		return true;
	}


	/**
	 * Generate the next higher Order-ID based on config prefix, order number digits and existing records
	 * Override parent function because parent's is private
	 */
	protected function generateOrderId($blnNewOrder=false)
	{
		if ($this->strOrderId != '' && !$blnNewOrder)
			return $this->strOrderId;
			
		$this->import('Isotope');

		$strPrefix = $this->Isotope->Config->orderPrefix;
		$arrConfigIds = $this->Database->execute("SELECT id FROM tl_iso_config WHERE store_id=" . $this->Isotope->Config->store_id)->fetchEach('id');

		// Lock tables so no other order can get the same ID
		$this->Database->lockTables(array('tl_iso_orders'));

		// Retrieve the highest available order ID
		$objMax = $this->Database->prepare("SELECT order_id FROM tl_iso_orders WHERE order_id LIKE '$strPrefix%' AND config_id IN (" . implode(',', $arrConfigIds) . ") ORDER BY order_id DESC")->limit(1)->executeUncached();
		
		$intMax = (int)substr($objMax->order_id, strlen($strPrefix));
		$this->strOrderId = $strPrefix . str_pad($intMax+1, $this->Isotope->Config->orderDigits, '0', STR_PAD_LEFT);

		if (!$blnNewOrder)
		{
			$this->Database->query("UPDATE tl_iso_orders SET order_id='{$this->strOrderId}' WHERE id={$this->id}");
		}
		
		$this->Database->unlockTables();

		return $this->strOrderId;
	}
	
	
	
	public function cancelCopy()
	{
		$this->unlock();
		
		// Get all of the data that needs to be copied
		$arrData = $this->arrData;
		$arrData['id'] = null;
		$arrData['tstamp'] = time();
		$arrData['order_id'] = $this->generateOrderId(true);
		$arrData['uniqid'] = uniqid($this->Isotope->Config->orderPrefix, true);
		$arrData['payment_id'] = 0;
		$arrData['shipping_id'] = 0;
		$arrData['payment_data'] = null;
		$arrData['shippingTotal'] = 0.00;
		$arrData['grandTotal'] = floatval($arrData['subTotal']) + floatval($arrData['taxTotal']);
		$arrData['cc_num'] = '';
		$arrData['cc_type'] = '';
		$arrData['cc_exp'] = '';
		$arrData['cc_cvv'] = '';
		$arrData['ups_tracking_number'] = '';
		$arrData['ups_label'] = '';
		$arrData['shipping_status'] = '';
		
		
		// Create new order
		$intNewOrderId = $this->Database->prepare("INSERT INTO " . $this->strTable . " %s")
								  ->set($arrData)
								  ->executeUncached()
								  ->insertId;
		
		
		// Get all of the fields that need to be copied (and remove unwanted fields)
		$arrFields = $this->Database->getFieldNames($this->ctable);
		$arrRemoveFields = array('id', 'pid', 'tstamp', 'PRIMARY');
		
		foreach ($arrFields as $key=>$field)
		{
			if (in_array($field, $arrRemoveFields))
				unset($arrFields[$key]);
		}
		
		
		// Copy items to new order
		$this->Database->prepare("INSERT INTO " . $this->ctable . " SELECT NULL, ?, UNIX_TIMESTAMP(NOW()), " . implode(",", $arrFields) . " FROM " . $this->ctable . " WHERE pid=?")
								  ->executeUncached($intNewOrderId, $this->id);
		
		
		// Cancel this order
		$this->status = 'cancelled';
		$this->save();
		
		return $intNewOrderId;
	}
	
	
	
	
	public function reloadPaymentData()
	{
		$this->arrData['payment_data'] = deserialize($this->Database->prepare("SELECT payment_data FROM tl_iso_orders WHERE id=?")->limit(1)->executeUncached($this->id)->payment_data);
	}
	
}

