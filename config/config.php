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


/**
 * Backend modules
 */
 
$GLOBALS['BE_MOD']['isotope']['iso_orders']['tables'][] = 'tl_member';
$GLOBALS['BE_MOD']['isotope']['iso_orders']['tables'][] = 'tl_iso_addresses';
$GLOBALS['BE_MOD']['isotope']['iso_orders']['edit_items'] = array('tl_iso_order_edit','editOrderItems');
$GLOBALS['BE_MOD']['isotope']['iso_orders']['new_order'] = array('ModuleIsotopeOrderBackend','compile');
$GLOBALS['BE_MOD']['isotope']['iso_orders']['edit_order'] = array('ModuleIsotopeOrderBackend','compile');
$GLOBALS['BE_MOD']['isotope']['iso_orders']['cancel_copy'] = array('ModuleIsotopeOrderBackend','compile');

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePreActions'][] = array('ModuleIsotopeOrderBackend', 'resetAddresses');

/**
 * Step callbacks for order-edit module
 */
$GLOBALS['ISO_ORDER_STEPS'] = array
(
	'status' => array
	(
		array('ModuleIsotopeOrderBackend', 'getStatusInterface'),
	),
	'products' => array
	(
		array('ModuleIsotopeOrderBackend', 'getProductInterface'),
	),
	'address' => array
	(
		array('ModuleIsotopeOrderBackend', 'getAddressInterface'),
	),
	'shipping' => array
	(
		array('ModuleIsotopeOrderBackend', 'getShippingModulesInterface'),
	),
	'payment' => array
	(
		array('ModuleIsotopeOrderBackend', 'getPaymentModulesInterface'),
	),
	'messages' => array
	(
		array('ModuleIsotopeOrderBackend', 'getMessagesInterface'),
	),
);
