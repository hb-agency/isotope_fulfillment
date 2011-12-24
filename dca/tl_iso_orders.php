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
* Global Operations
*/
$GLOBALS['TL_DCA']['tl_iso_orders']['list']['global_operations']['new_order'] = array
(
	'label'               => &$GLOBALS['TL_LANG']['tl_iso_orders']['new'],
	'href'                => 'key=new_order',
	'class'               => 'header_new',
	'attributes'          => 'onclick="Backend.getScrollOffset();" accesskey="e"',
);

$GLOBALS['TL_DCA']['tl_iso_orders']['list']['global_operations']['all'] = array
(
	'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
	'href'                => 'act=select',
	'class'               => 'header_edit_all',
	'attributes'          => 'onclick="Backend.getScrollOffset();" accesskey="e"'
);

$GLOBALS['TL_DCA']['tl_iso_orders']['list']['operations']['edit'] = array
(
	'label'               => &$GLOBALS['TL_LANG']['tl_iso_orders']['edit'],
	'href'                => 'key=edit_order',
	'icon'                => 'edit.gif'
);


