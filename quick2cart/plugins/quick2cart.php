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

/**
 * The class name matches the file name with the first letter capitalized
 */

class Quick2cartComponent extends S2Component {

    var $plugin_order = 0;

    var $name = 'quick2cart';

    var $published = false;

    /**
    * Limit plugin to run only in specific controller actions
    */
    var $controllerActions = [
        'admin_listings'=>['_save','_delete'],
        'listings'=>['_save','_delete'],
        'com_content'=>'com_content_view',
        'categories'=>['all'],
        'community_listings'=>['all'],
        'module_listings'=>'index'
    ];

    // Holds the array info used to render the e-downloads on the detail page

    var $output_downloads;

    var $q2cParams;

    function startup(& $controller)
    {
        $this->c = & $controller;

        $this->config = $controller->config;

        if ( defined('MVC_FRAMEWORK_ADMIN') )
        {
            $controller->asset_manager->add('admin/addon_quick2cart.js','addon');
        }

        $path = PATH_ROOT . 'components' . DS . 'com_quick2cart' . DS . 'helper.php';

        if(!file_exists($path))
        {
            return false;
        }

        if(!class_exists('comquick2cartHelper'))
        {
            require_once($path);
        }

        if(!class_exists('Quick2cartModelcart'))
        {
            JLoader::import('cart', JPATH_SITE . '/components/com_quick2cart/models');
        }

        // Alternatively you can run your own checks for controller and actions inside each event

        if(!$this->runPlugin($controller))
        {
            return false;
        }

        if ( !defined('MVC_FRAMEWORK_ADMIN') )
        {
            if ( in_array($controller->name,['com_content','categories']) )
            {
                $controller->asset_manager->add('quick2cart.css','addon');
            }
        }

        $this->published = true;

        // Make the controller properties available in other methods inside this class

        $this->q2cParams = JComponentHelper::getParams('com_quick2cart');
    }

    function runPlugin(&$controller)
    {
        // Check if running in desired controller/actions

        if(!isset($this->controllerActions[$controller->name]))
        {
            return false;
        }

        $actions = !is_array($this->controllerActions[$controller->name])
                        ?
                        [$this->controllerActions[$controller->name]]
                        :
                        $this->controllerActions[$controller->name];

        if(!in_array('all',$actions) && !in_array($controller->action,$actions))
        {
            return false;
        }

        return true;
    }

    /**
    * Delete product data when listing is deleted
    */
    function plgBeforeDelete(&$model, $data)
    {
        $listing_id = Sanitize::getInt($model->data['Listing'],'id');

        if($listing_id)
        {
            $Model = new S2Model;

            $Model->query('DELETE FROM #__kart_items WHERE product_id = ' . $listing_id . ' AND  parent = "com_content"' );
        }
    }

    /**
    * Event triggered after the Model::delete method
    * Posted data including the id of the record to be deleted can be found in the $model->data array
    */
    function plgAfterDelete(&$model, $data) {}

    /**
    * Event triggered before the theme is rendered
    * All variables sent to theme are available via $this->c->viewVars array
    */
    function plgBeforeRender()
    {
        $listingTypes = $this->config->get('quick2cart-listingtypes',[]);

        $listingTypeId = isset($this->c->data['ListingType']) ? Sanitize::getInt($this->c->data['ListingType'], 'listing_type_id') : null;

        S2App::import('Helper','quick2cart_routes','jreviews');

        $Quick2cartRoutes = ClassRegistry::getClass('Quick2cartRoutesHelper');

        // Read Quick2Cart settings

        $multivendor = $this->q2cParams->get('multivendor');

        if(!$this->config->get('quick2cart-buy-showprice',0))
        {
            cmsFramework::addCustomTag('
                <style>
                .jrCustomFields .jrAddonQuick2cart .jrFieldValue .control-group:nth-of-type(1) {
                  display:none;
                }
                </style>');
        }

        // Listings post submit

        if($this->c->name == 'listings'
            && $this->c->action == '_save'
            && isset($this->c->viewVars['listing'])
            && Sanitize::getBool($this->c->viewVars, 'isNew')
        )
        {
            if(empty($listingTypes) || !$listingTypeId || !in_array($listingTypeId, $listingTypes)) return;

            $listing_submit_actions = Sanitize::getVar($this->c->viewVars, 'listing_submit_actions', []);

            $bottom = Sanitize::getVar($listing_submit_actions, 'bottom', []);

            $bottom[] = $this->c->partialRender('quick2cart', 'listing_submit_actions');

            $listing_submit_actions['bottom'] = $bottom;

            $this->c->set('listing_submit_actions', $listing_submit_actions);
        }

        // Listings manager dropdown

        if($this->c->name == 'categories' && isset($this->c->viewVars['listings']))
        {
            $listings = & $this->c->viewVars['listings'];

            $listingManager = Configure::read('widget_listing_manager', []);

            foreach ( $listings AS $listing )
            {
                if ( !in_array($listing['ListingType']['listing_type_id'], $listingTypes) ) continue;

                $listing_id = (int) $listing['Listing']['listing_id'];

                $canEditListing = $this->c->perm->__('listing')->setListing($listing)->canUpdate();

                $edit_product_groups = $this->config->get('quick2cart-edit-product-access',[7,8]);

                $canEditProduct = $this->c->auth->belongsToGroups($edit_product_groups);

                if($canEditListing && $canEditProduct)
                {
                    if($multivendor || (!$multivendor && $this->c->auth->admin))
                    {
                        $link = $Quick2cartRoutes->edit($listing, ['rel'=>'nofollow']);

                        $listingManager[$listing['Listing']['listing_id']][2][] = $link;
                    }
                }
            }

            Configure::write('widget_listing_manager', $listingManager);
        }

        // Listing detail page buttons

        if($this->c->name == 'com_content' && isset($this->c->viewVars['listing']))
        {
            $listing = & $this->c->viewVars['listing'];

            if ( !in_array($listing['ListingType']['listing_type_id'],$listingTypes) ) return false;

            $listing_id = (int) $listing['Listing']['listing_id'];

            $canEditListing = $this->c->perm->__('listing')->setListing($listing)->canUpdate();

            $edit_product_groups = $this->config->get('quick2cart-edit-product-access',[7,8]);

            $canEditProduct = $this->c->auth->belongsToGroups($edit_product_groups);

            if($canEditListing && $canEditProduct)
            {
                if($multivendor || (!$multivendor && $this->c->auth->admin))
                {
                    $button = $Quick2cartRoutes->edit($listing, ['rel'=>'nofollow','class'=>'jrButton jrSmall']);

                    $listingButtons = Configure::read('widget_listing_buttons_detail', []);

                    $listingButtons[$listing['Listing']['listing_id']][3][] = $button;

                    Configure::write('widget_listing_buttons_detail', $listingButtons);
                }
            }

            // Downloads table

            $downloads_position = $this->config->get('quick2cart-detailposition','below-fields');

            if($this->output_downloads)
            {
                $this->c->viewVars['addonPosition'][$downloads_position][] = $this->output_downloads;
            }
        }
    }

   /**
     * Event triggered after a Model query is run and before the Model AfterFind event
     * @param  Object $model this is the current model used to store the form data
     * @param  Array $results this is the result array from the query
     * @return Array $results array is returned after being modified
     */
    function plgAfterFind(&$model, $results)
    {
        return $results;
    }

   /**
     * Event triggered after a Model query is run and after the Model AfterFind event
     * @param  Object $model this is the current model used to store the form data
     * @param  Array $results this is the result array from the query
     * @return Array $results array is returned after being modified
     */
    function plgAfterAfterFind(&$model, $results)
    {
        // Only run in the desired controller actions

        if(empty($results)
            || !in_array($this->c->name,['com_content','categories','community_listings','module_listings']))
        {
            return $results;
        }

        $listingTypes = $this->config->get('quick2cart-listingtypes',[]);

        if ( empty($listingTypes) ) return $results;

        // Add the Buy now button in the list and detail pages

        $Q2cartHelper = new comquick2cartHelper();

        $Q2CProductHelper = $Q2cartHelper->loadqtcClass(JPATH_SITE . "/components/com_quick2cart/helpers/product.php", "productHelper");

        // Load language file - once

        $app = JFactory::getApplication();

        $lang = JFactory::getLanguage();

        $lang->load('com_quick2cart');

        foreach($results AS $key=>$result)
        {
            if ( !in_array($result['ListingType']['listing_type_id'], $listingTypes))
            {
                continue;
            }

            $results[$key] = $this->injectField($result);

            // Add the list of available e-downloads to render them in the detail apges

/* TO DO */

            if($this->c->name == 'com_content' && $this->c->action == 'com_content_view')
            {
                $Model = new S2Model;

                $files = $purchasedFiles = [];

                // First get list of all e-downloads for this listing

                $query = '
                    SELECT
                        File.file_id,
                        File.file_display_name AS title,
                        File.purchase_required,
                        File.filePath AS filename,
                        SUM(FileOrder.download_count) AS views
                    FROM
                        #__kart_items AS Product
                    LEFT JOIN
                        #__kart_itemfiles AS File ON File.item_id = Product.item_id
                    LEFT JOIN
                        #__kart_orderItemFiles AS FileOrder ON FileOrder.product_file_id = File.file_id
                    WHERE
                        File.state = 1
                        AND Product.product_id = ' . (int) $result['Listing']['listing_id'] . '
                        AND Product.parent = "com_content"
                    GROUP BY
                        File.file_id
                ';

                $files = $Model->query($query, 'loadAssocList');

                // If it's a logged in user, check if the user already purchased the listing so we can enable the pay downloads

                if($files)
                {
                    $expiry_mode = $this->q2cParams->get('eProdUExpiryMode');

                    if($this->auth->id)
                    {
                        $query = '
                        SELECT
                            OrderItem.order_id,
                            FileOrder.order_item_id,
                            File.file_id,
                            FileOrder.download_count,
                            FileOrder.download_limit,
                            FileOrder.cdate,
                            FileOrder.expirary_date AS expire_date
                        FROM
                            #__kart_items AS Product
                        LEFT JOIN
                            #__kart_itemfiles AS File ON File.item_id = Product.item_id
                        INNER JOIN
                            #__kart_orderItemFiles AS FileOrder ON FileOrder.product_file_id = File.file_id
                        INNER JOIN
                            #__kart_order_item AS OrderItem ON OrderItem.item_id = Product.item_id
                        INNER JOIN
                            #__kart_orders AS `Order` ON `Order`.id = OrderItem.order_id
                        WHERE
                            File.state = 1
                            AND Product.product_id = ' . (int) $result['Listing']['listing_id'] . '
                            AND Product.parent = "com_content"
                            AND `Order`.user_info_id = ' . (int) $this->auth->id
                        ;

                        $purchasedFiles = $Model->query($query, 'loadAssocList', 'file_id');
                    }

                    foreach($files AS $key=>$file)
                    {
                        $extraParams = '';

                        $file_id = $file['file_id'];

                        $files[$key]['download_allowed'] = $file['purchase_required'] ? 0 : 1;

                        // It was purchased. Now check expiration and limits

                        if($file['purchase_required'] && isset($purchasedFiles[$file_id]) && $file['purchase_required'])
                        {
                            $files[$key]['purchased'] = 1;

                            $extraParams = '&orderid=' . $purchasedFiles[$file_id]['order_id'] . '&order_item_id=' . $purchasedFiles[$file_id]['order_item_id'];

                            // Check to see if user has exceeded the download limit
                            if($expiry_mode == 'epMaxDownload' || $expiry_mode == 'epboth')
                            {
                                if($purchasedFiles[$file_id]['download_limit'] < $purchasedFiles[$file_id]['download_count'])
                                {
                                    $files[$key]['download_allowed'] = 1;
                                }
                            }

                            // Check to see if user access to downloads expired

                            if($expiry_mode == 'epDateExpiry' || $expiry_mode == 'epboth')
                            {
                                if($purchasedFiles[$file_id]['expire_date'] == '0000-00-00 00:00:00' || strtotime($purchasedFiles[$file_id]['expire_date']) < _CURRENT_SERVER_TIME)
                                {
                                    $files[$key]['download_allowed'] = 1;
                                }
                            }
                        }
                        else {

                            $files[$key]['purchased'] = 0;
                        }

                        $files[$key]['download_link'] = $Q2CProductHelper->getMediaDownloadLinkHref($file_id, $extraParams);
                    }
                }

                if($files)
                {
                    $this->c->helpers[] = 'quick2cart_routes';

                    $this->c->set(['q2cDownloads'=>$files]);

                    $this->c->layout = 'module';

                    $this->output_downloads = $this->c->render('quick2cart','downloads');

                    $this->c->layout = 'detail';
                }
            }
        }

        return $results;
    }

    /**
     * Synchronize listing fields with Q2C product fields when the listing is saved
     * Based on the add-on settings for fields that should be synced
     */

    function plgBeforeSave(&$model,$data)
    {
        if ( !class_exists('comquick2cartHelper') )
        {
            JLoader::import('attributes', PATH_ROOT . DS . 'components' . DS . 'com_quick2cart' . DS . 'helper.php');
        }

        if ( !class_exists('quick2cartModelAttributes') )
        {
            JLoader::import('attributes', JPATH_SITE.DS.'components'.DS.'com_quick2cart'.DS.'models');
        }

        $isNew = Sanitize::getBool($data,'isNew');

        $Q2cartHelper = new comquick2cartHelper();

        $Q2CartModelAttributes =  new quick2cartModelAttributes();

        $Q2CStoreHelper= $Q2cartHelper->loadqtcClass(JPATH_SITE . "/components/com_quick2cart/helpers/storeHelper.php", "storeHelper");

        $Q2CProductHelper= $Q2cartHelper->loadqtcClass(JPATH_SITE . "/components/com_quick2cart/helpers/product.php", "productHelper");

        // Grab the add-on configuration values

        $listingTypes = $this->config->get('quick2cart-listingtypes',[]);

        $sku_field = $this->c->config->{'quick2cart-sku-field'};

        if ( !in_array($data['ListingType']['listing_type_id'], $listingTypes)  )
        {
            return $data;
        }

        // Get data arrays

        $listing = Sanitize::getVar($data,'Listing');

        $fields = Sanitize::getVar($data,'Field');

        // Check if there are any custom fields to process

        if ( empty($fields) || $model->name != 'Listing' )
        {
            return $data;
        }

        // LISTING logic

        $listingFields = Sanitize::getVar($fields,'Listing',[]);

        $pid = Sanitize::getInt($listing,'id');

        $user = $this->c->Listing->getListingOwner($pid);

        // Load field config settings

        $priceArray = $discountPriceArray = [];

        $priceArrayConfig = $this->config->get('quick2cart-price',[]);

        $discountPriceArrayConfig = $this->config->get('quick2cart-discount-price',[]);

        foreach ( $priceArrayConfig AS $priceRow )
        {
            $price_field = $priceRow['field'];

            $currency = $priceRow['currency'];

            if($price_field != '' && $currency != '' && isset($listingFields[$price_field]))
            {
                $priceArray[$currency] = Sanitize::getString($listingFields, $price_field);
            }
        }

        foreach ( $discountPriceArrayConfig AS $priceRow )
        {
            $price_field = $priceRow['field'];

            $currency = $priceRow['currency'];

            if($price_field != '' && $currency != '' && isset($listingFields[$price_field]))
            {
                $discountPriceArray[$currency] = Sanitize::getString($listingFields, $price_field);
            }
        }

        $priceArray = array_filter($priceArray,'strlen');

        $discountPriceArray = array_filter($discountPriceArray,'strlen');

        $sku = Sanitize::getString($listingFields, $sku_field);

        // Abort if there isn't any price information to sync. We don't need to create the Q2C product at this time

        if ( empty($priceArray) && empty($discountPriceArray) ) return $data;

        // Abort if new and the prices are zero

        if ( $isNew && (array_sum($priceArray) + array_sum($discountPriceArray)) == 0 ) return $data;

        if ( $pid > 0)
        {
            // Get Q2C item_id

            $Model = new S2Model;

            $query = "SELECT item_id FROM #__kart_items WHERE product_id = " . $pid . " AND parent = 'com_content'";

            $item_id = $Model->query($query, 'loadResult');

            if(!$item_id)
            {
                $product = ['state' => 1, 'name' => '', 'store_id' => 0, 'prodPriceDetails' => []];

                // If the Quick2Cart product doesn't exist, then we don't need to create it even when editing if the prices are zero

                if(array_sum($priceArray) + array_sum($discountPriceArray) == 0) return $data;
            }
            else {

                $product = (array) $Q2CProductHelper->getItemCompleteDetail($item_id, 'com_content');
            }

            // Complete the pricing array with prices in currencies in the product, but not the listing

            foreach($product['prodPriceDetails'] AS $curr=>$priceRow)
            {
                if(!isset($priceArray[$curr]))
                {
                    $priceArray[$curr] = $priceRow->price;
                }

                if(!isset($discountPriceArray[$curr]))
                {
                    $discountPriceArray[$curr] = $priceRow->discount_price;
                }
            }

            $state = Sanitize::getInt($product,'state',1);

            $title = Sanitize::getString($product,'name');

            $store_id = Sanitize::getInt($product,'store_id');

            $item_id = Sanitize::getInt($product,'item_id');

            if(!$item_id && !$store_id)
            {
                if($storeList = (array) $Q2CStoreHelper->getUserStore($user['user_id']))
                {
                    $store_id = $storeList[0]['id'];
                }
            }

            // If a store for this user was not found, then we shouldn't create the Q2C product without a store

            if(!$store_id)
            {
                return $data;
            }

            $input = JFactory::getApplication()->input;

            $post_data  = $input->post;

            $post_data->set('item_id', $item_id);

            $post_data->set('store_id', $store_id);

            $post_data->set('state',$state);

            $post_data->set('item_name', Sanitize::getString($listing,'title'));

            $post_data->set('pid', $pid);

            $post_data->set('client', 'com_content');

            if(!empty($priceArray))
            {
                $post_data->set('multi_cur',$priceArray);
            }

            if(!empty($discountPriceArray))
            {
                $post_data->set('multi_dis_cur',$discountPriceArray);
            }

            if($sku_field && isset($listingFields[$sku_field]))
            {
                $post_data->set('sku', $sku);
            }

            // $post_data->set('stock', $itemstock);

            // $post_data->set('min_item', $min_item);

            // $post_data->set('max_item', $max_item);

            $item_id = $Q2cartHelper->saveProduct($post_data);
        }

        return $data;
    }

    /**
     * Event triggered after the Model data is stored to the database
     * Posted data can be found in the $model->data array
     * $mode->data['isNew'] is a boolean allow you to run specific actions for new or edited records
     */

    function plgAfterSave(&$model) {}

    /**
     * Event triggered after the controller action is run. Output is available in $this->c->output var
     * @return [type] [description]
     */
    function plgAfterFilter()
    {
        // $output = & $this->c->output;

        // if(Sanitize::getString($this->c,'name') == 'com_content')
        // {
        //     $output['row']->text = $output['row']->text;
        // }
    }

    /**
     * Utility methods
     */

    function injectField($listing)
    {
        $contentview = $this->config->get('quick2cart-buy-detailview',1);

        $listview = $this->config->get('quick2cart-buy-listview',0);

        $Q2cartHelper = new comquick2cartHelper();

        $Q2cartModel = new Quick2cartModelcart;

        $output = $Q2cartHelper->getBuynow($listing['Listing']['listing_id'],'com_content',['hideFreeDdownloads'=>true]);

        $bannerFieldName = $this->getQ2cBannerFieldName($listing);

        if(!$output)
        {
            $listing = $this->removeQ2CBannerField($listing, $bannerFieldName);

            return $listing;
        }

        /*
        $Q2cartProductHelper= $Q2cartHelper->loadqtcClass(JPATH_SITE . "/components/com_quick2cart/helpers/product.php", "productHelper");

        $itemidAndState = $Q2cartModel->getitemidAndState($listing['Listing']['listing_id'], 'com_content');

        $itemId = $itemidAndState['item_id'];

        // $price = $Q2cartModel->getPrice($item_id, 1);

        $priceArray = $Q2cartProductHelper->getProdPriceWithDefltAttributePrice($itemId);

        $price = $Q2cartHelper->getFromattedPrice($priceArray['itemdetail']['price']);
        */

        if(isset($listing['Field']['pairs']) && !empty($listing['Field']['pairs']))
        {
            foreach($listing['Field']['pairs'] AS $key=>$row)
            {
                if($row['type'] == 'banner')
                {
                    $listing['Field']['pairs'][$key]['description'] = str_ireplace('{jr_addon_quick2cart}', $output, $row['description']);
                }
            }
        }

        $field = ['jr_addon_quick2cart'=>[
                'group_id' => 'quick2cart',
                'name' => 'jr_addon_quick2cart',
                'type' => 'banner',
                'title' => '',
                'description' => $output,
                'value' => ['banner'],
                'text' => ['banner'],
                'image' => [],
                'properties' => [
                    'show_title' => 0,
                    'location' => 'content',
                    'contentview' => $contentview,
                    'listview' => $listview,
                    'access_view' => implode(',',$this->c->auth->getGroupIdsFor('guest')),
                    'click2searchlink' => '',
                    'output_format' => '{fieldtext}',
                    'click2search' => 0
                ]
            ]
        ];

        $group = ['quick2cart'=>[
            'Group'=>[
                'group_id'=>'quick2cart',
                'title'=>'Quick2cart',
                'name'=> 'quick2cart',
                'show_title'=>0],
            'Fields'=>$field
        ]];

        // Insert in existing group

        // $listing['Field']['groups']['location']['Fields'] = array_merge($listing['Field']['groups']['location']['Fields'], $field);

        // Insert as new group

        if(!empty($listing['Field']['groups']))
        {
            if (!$bannerFieldName)
            {
                $listing['Field']['groups'] = array_merge($group,$listing['Field']['groups']);
            }

            $listing['Field']['pairs'] = array_merge($field,$listing['Field']['pairs']);
        }
        else {

            if (!$bannerFieldName)
            {
                $listing['Field']['groups'] = $group;
            }

            $listing['Field']['pairs'] = $field;
        }

        return $listing;
    }

    function getQ2cBannerFieldName($listing)
    {
        if ( isset($listing['Field']['pairs']) && !empty($listing['Field']['pairs']) )
        {
            foreach($listing['Field']['pairs'] AS $key=>$row)
            {
                if($row['type'] == 'banner')
                {
                    if(strstr($listing['Field']['pairs'][$key]['description'] , '{jr_addon_quick2cart}'))
                    {
                        return $key;
                    }
                }
            }
        }

        return null;
    }

    function removeQ2CBannerField($listing, $fname)
    {
        if ( $fname )
        {
            $groupName = $listing['Field']['pairs'][$fname]['group_name'];

            unset($listing['Field']['groups'][$groupName]['Fields'][$fname]);

            unset($listing['Field']['pairs'][$fname]);
        }

        return $listing;
    }
}