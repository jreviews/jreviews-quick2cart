<?php
/*
 * This file is part of the JReviews Quick2Cart Add-on
 *
 * Copyright (C) ClickFWD LLC 2010-2018 <sales@jreviews.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

defined( 'MVC_FRAMEWORK') or die;

class Quick2cartRoutesHelper extends BaseHelper
{
    var $helpers = ['html','routes'];

    var $routes = [
        'edit'=>'index.php?option=com_jreviews&amp;Itemid=%s&amp;id=%s',
    ];

    function __construct()
    {
        S2App::import('Helper', $this->helpers, 'jreviews');

        foreach($this->helpers AS $helper)
        {
            $method_name = inflector::camelize($helper);

            $class_name = $method_name.'Helper';

            $this->{$method_name} = ClassRegistry::getClass($class_name);
        }
    }

    function edit($listing, $attributes = [])
    {
        $Menu = ClassRegistry::getClass('MenuModel');

        $menu_id = $Menu->getMenuIdByViewParams('quick2cart',['action'=>'edit']);

        $title = '<span class="jrIconCart"></span> <span>' . __t('ADDON_QUICK2CART_EDIT',true) . '</span>';

        $url = sprintf($this->routes['edit'], $menu_id, $listing['Listing']['listing_id']);

        if(!$menu_id)
        {
            $url .= '&amp;url=quick2cart/edit';
        }

        return $this->Html->sefLink($title,$url,$attributes);
    }

    function myDownloadsUrl()
    {
        $app  = JApplication::getInstance('site');

        $JMenu = $app->getMenu();

        $pattern = 'index.php?option=com_quick2cart&view=downloads';

        $menu = $JMenu->getItems(['link'],[$pattern],true);

        if($menu)
        {
            $url = JRoute::_($pattern . '&Itemid=' . $menu->id);
        }
        else {

            $url = JRoute::_($pattern);
        }

        return $url;
    }
}