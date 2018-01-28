<?php
/*
 * This file is part of the JReviews Quick2Cart Add-on
 *
 * Copyright (C) ClickFWD LLC 2010-2018 <sales@jreviews.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

defined('MVC_FRAMEWORK') or die;

/**
 * The class name matches the file name with the first letter capitalized
 */

class Quick2cartController extends MyController
{
	var $uses = array('menu','user','Field');

	var $helpers = array('html','assets');

	var $components = array('config','everywhere');

    var $autoRender = false;

    var $autoLayout = false;

	function beforeFilter()
	{
		parent::beforeFilter();
	}

	function edit()
	{
        $listing_id = Sanitize::getInt($this->params,'id');

        $User = cmsFramework::getUser();

        if(!$listing_id)
        {
            return JError::raiseError(404, JText::_('JERROR_LAYOUT_PAGE_NOT_FOUND'));
        }

        if(!$User->id)
        {
            echo cmsFramework::noAccess();

            return;
        }

        $this->assets['js'][] = 'jreviews';

        $this->assets['css'][] = 'theme';

        $this->assets['css'][] = 'form';

        $this->assets['css'][] = 'quick2cart';

        // Load quick2cart libraries

        $this->loadDependencies();

        // Sync listing fields to the cart fields if empty

        $Q2cartHelper = new comquick2cartHelper();

        // Get the listing info

        $this->Listing->addStopAfterFindModel(array('Community','Favorite','Media','PaidOrder'));

        $listing = $this->Listing->findRow(array('conditions'=>array('Listing.id = ' . $listing_id)));

        $storeList = array();

        $this->syncListingToCart($listing, $storeList);

        // Set variables used inside the Q2C form

        $client = 'com_content';

        $pid = $listing_id;

        $_REQUEST['showQtcSvbtn'] = 1; // Ensure the save button shows up

        // $itemDetail['name'] = $listing['Listing']['title'];

        ob_start();

        include($Q2cartHelper->getViewpath('attributes','','SITE','SITE'));

        $form = ob_get_contents();

        ob_end_clean();

        $this->set(array(
            'form'=>$form,
            'listing'=>$listing,
            'storeList'=>$storeList,
            'pid'=>$pid,
            'client'=>$client
            ));

        return $this->render('quick2cart','edit');
	}

    function syncCartToListing()
    {
        $post = Sanitize::getVar($this->params,'form');

        $listing_id = Sanitize::getInt($post,'listing_id');

        if(!$listing_id)
        {
            return false;
        }

        $sku_field = Sanitize::getVar($this->Config,'quick2cart-sku-field');

        $sku = Sanitize::getString($post,'sku');

        $priceArray = Sanitize::getVar($post,'multi_cur');

        $discountPriceArray = Sanitize::getVar($post,'multi_dis_cur');

        // Now add the synched info to the JReviews listing fields data array

        $data = array();

        if($sku_field)
        {
            $data['Field'][$sku_field] = $sku;
        }

        // Load field config settings

        $priceArrayConfig = Sanitize::getVar($this->Config,'quick2cart-price',array());

        $discountPriceArrayConfig = Sanitize::getVar($this->Config,'quick2cart-discount-price',array());

        foreach($priceArrayConfig AS $priceRow)
        {
            $price_field = $priceRow['field'];

            $currency = $priceRow['currency'];

            if($price_field != '' && $currency != '' && isset($priceArray[$currency]))
            {
                $data['Field'][$price_field] = $priceArray[$currency];
            }
        }

        foreach($discountPriceArrayConfig AS $priceRow)
        {
            $price_field = $priceRow['field'];

            $currency = $priceRow['currency'];

            if($price_field != '' && $currency != '' && isset($discountPriceArray[$currency]))
            {
                $data['Field'][$price_field] = $discountPriceArray[$currency];
            }
        }

        if(!empty($data))
        {
            $data['Field']['contentid'] = $listing_id;
        }

        $this->Field->update('#__jreviews_content', 'Field', $data, 'contentid');
    }

    protected function syncListingToCart($listing, & $storeList)
    {
        $listing_id = Sanitize::getInt($listing['Listing'],'listing_id');

        $User = cmsFramework::getUser();

        // Force all prices to be prefilled at least with zero so the e-download and attrbute buttons are not disabled

        $q2cParams = JComponentHelper::getParams('com_quick2cart');

        $currencies = explode(',',Sanitize::stripWhiteSpace($q2cParams->get('addcurrency')));

        $priceArray = $discountPriceArray = array_fill_keys(array_values($currencies), 0);

        $curr = $q2cParams->get('addcurrency');// used to store in kart_item table

        $curr = explode(',',  $curr);

        $def_curr = $curr[0];

        // Get the product cart info

        $Q2CartModelAttributes =  new quick2cartModelAttributes();

        $Q2cartHelper = new comquick2cartHelper();

        $Q2CStoreHelper= $Q2cartHelper->loadqtcClass(JPATH_SITE . "/components/com_quick2cart/helpers/storeHelper.php", "storeHelper");

        $Q2CProductHelper= $Q2cartHelper->loadqtcClass(JPATH_SITE . "/components/com_quick2cart/helpers/product.php", "productHelper");

        $product_tmp = $Q2CartModelAttributes->getItemDetail($listing_id, 'com_content');

        // The product array that is returned is missing the retail and discount pricing arrays so we need to run a different method after we get the internal Q2C item_id
        // It's all a bit redundant, but Q2C doesn't offer a direct method to do it

        $productPriceArray = $productDiscountPriceArray = array();

        if(!empty($product_tmp))
        {
            $product = $Q2CProductHelper->getItemCompleteDetail($product_tmp['item_id']);

            // Build the price and discount price arrays

            foreach($product->prodPriceDetails AS $currency=>$productPriceRow)
            {
                $productPriceArray[$currency] = $productPriceRow->price;

                $productDiscountPriceArray[$currency] = $productPriceRow->discount_price;
            }
        }
        else {

            $product = array();

            $productPriceArray = array($def_curr=>Sanitize::getFloat($product, 'price'));
        }

        $storeList = (array) $Q2CStoreHelper->getUserStore($User->id);

        // Load field config settings

        $priceArrayConfig = Sanitize::getVar($this->Config,'quick2cart-price',array());

        $discountPriceArrayConfig = Sanitize::getVar($this->Config,'quick2cart-discount-price',array());

        // Retail price

        foreach($priceArrayConfig AS $priceRow)
        {
            $price_field = $priceRow['field'];

            $currency = $priceRow['currency'];

            if($price_field != '' && $currency != '' && isset($listing['Field']['pairs'][$price_field]))
            {
                $priceArray[$currency] = Sanitize::getVar($listing['Field']['pairs'][$price_field]['value'],0);
            }
            // Prevent overwriting the price with zero if the custom field selected in the configuration doesn't exist in this listing type
            elseif($price_field != '' && $currency != '' && !isset($listing['Field']['pairs'][$price_field])) {

                unset($priceArray[$currency]);
            }
        }

        // Discount price

        foreach($discountPriceArrayConfig AS $discountRow)
        {
            $price_field = $discountRow['field'];

            $currency = $discountRow['currency'];

            if($price_field != '' && $currency != '' && isset($listing['Field']['pairs'][$price_field]))
            {
                $discountPriceArray[$currency] = Sanitize::getVar($listing['Field']['pairs'][$price_field]['value'],0);
            }
            // Prevent overwriting the price with zero if the custom field selected in the configuration doesn't exist in this listing type
            elseif($price_field != '' && $currency != '' && !isset($listing['Field']['pairs'][$price_field])) {

                unset($discountPriceArray[$currency]);
            }
        }

        $sku_field = Sanitize::getVar($this->Config,'quick2cart-sku-field');

        // Sync title

        $listing_title = $listing['Listing']['title'];

        $product_title = Sanitize::getString($product,'name');

        $title = $product_title != '' ? $product_title : $listing_title;

        // Sync price

        $priceArray = array_filter($priceArray);

        $discountPriceArray = array_filter($discountPriceArray);

        $priceArray = array_merge($productPriceArray, $priceArray);

        $discountPriceArray = array_merge($productDiscountPriceArray, $discountPriceArray);

        // Sync sku

        $listing_sku = isset($listing['Field']['pairs'][$sku_field]) ? Sanitize::getVar($listing['Field']['pairs'][$sku_field]['value'],0) : '';

        $product_sku = Sanitize::getVar($product, 'sku');

        $sku = $product_sku != '' ? $product_sku : $listing_sku;

        $input = new JInput;

        $post_data  = $input->post;

        $item_id    = Sanitize::getInt($product,'item_id');

        $state      = Sanitize::getInt($product,'state');

        $store_id   = Sanitize::getInt($product,'store_id');

        // Pre-assign the first store found for this user to the product

        if(!$store_id && $storeList)
        {
            $store_id = $storeList[0]['id'];
        }

        $state = Sanitize::getInt($product,'state',1);

        if($sku_field)
        {
            $post_data->set('sku',$sku);
        }

        if(!empty($priceArray))
        {
            $post_data->set('multi_cur',$priceArray);
        }

        if(!empty($discountPriceArray))
        {
            $post_data->set('multi_dis_cur',$discountPriceArray);
        }

        $post_data->set('item_id',$item_id);

        $post_data->set('store_id',$store_id);

        $post_data->set('state',$state);

        $post_data->set('item_name', $title);

        $post_data->set('pid', $listing_id);

        $post_data->set('client', 'com_content');

        $post_data->set('sku', $sku);

        $post_data->set('stock', Sanitize::getInt($product,'stock'));

        // $post_data->set('min_quantity', Sanitize::getInt($product,'min_quantity'));

        // $post_data->set('max_quantity', Sanitize::getInt($product,'max_quantity'));

        // Need to pass all of these so they are not reset during the 'sync' period prior to displaying the product form
        // For some reason some of the input names are inconsistent with the actual column names in Q2C

        $post_data->set('min_item', Sanitize::getInt($product,'min_quantity'));

        $post_data->set('max_item', Sanitize::getInt($product,'max_quantity'));

        $post_data->set('qtc_item_length', Sanitize::getFloat($product,'item_length'));

        $post_data->set('qtc_item_width', Sanitize::getFloat($product,'item_width'));

        $post_data->set('qtc_item_height', Sanitize::getFloat($product,'item_height'));

        $post_data->set('length_class_id', Sanitize::getInt($product,'item_length_class_id'));

        $post_data->set('qtc_item_weight', Sanitize::getFloat($product,'item_weight'));

        $post_data->set('weigth_class_id', Sanitize::getInt($product,'item_weight_class_id'));

        $post_data->set('taxprofile_id', Sanitize::getInt($product,'taxprofile_id'));

        $post_data->set('qtc_shipProfile', Sanitize::getInt($product,'shipProfileId'));

        $item_id = $Q2cartHelper->saveProduct($post_data);

        return $listing;
    }

    protected function loadDependencies()
    {
        // $doc = JFactory::getDocument();

        // $doc->addStyleSheet(JURI::base().'components'.DS.'com_quick2cart'.DS.'css'.DS.'quick2cart.css');

        JHTML::_('behavior.modal', 'a.modal');

        $lang = JFactory::getLanguage();

        $lang->load('com_quick2cart', JPATH_ADMINISTRATOR);

        if(!class_exists('comquick2cartHelper'))
        {
            JLoader::import('attributes', PATH_ROOT . DS . 'components' . DS . 'com_quick2cart' . DS . 'helper.php');
        }

        if(!class_exists('quick2cartModelAttributes'))
        {
            JLoader::import('attributes', JPATH_SITE.DS.'components'.DS.'com_quick2cart'.DS.'models');
        }
    }
}