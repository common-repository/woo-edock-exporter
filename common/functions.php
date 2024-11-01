<?php
use CarbonWCE\Carbon;

/**
* wce_get_woocommerce_product_list()
* @return array
*/
if ( !function_exists('wce_get_woocommerce_product_list') ) {
    function wce_get_woocommerce_product_list() {
        global $wpdb;
        global $wcedock_tax;
        global $woocommerce;
        
        $exportlist = array();

        $dt = Carbon::now();
        // echo $dt->toDateTimeString(); echo'<br>';
        $dty = Carbon::yesterday();
        // echo $dty->toDateTimeString(); echo'<br>';

        $limit_query = '';
        if ( WOO_ED_DEBUG ) $limit_query = ' LIMIT 100';
        if ( !isset($main_language) ) $main_language = '';

        $joinSQL = '';
        $whereSQL = '';

        // WPML
        $languages = array();
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            $joinSQL .= " INNER JOIN `" . $wpdb->prefix . "icl_translations` t ON (p.ID = t.element_id AND t.language_code = 'it')";
            $languages = apply_filters( 'wpml_active_languages', NULL, 'orderby=id&order=desc' );
            unset($languages['it']);
        }
        
        $mainSQL = "SELECT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_author, p.post_date_gmt, p.post_type FROM `" . $wpdb->prefix . "posts` p ".$joinSQL." WHERE (p.post_type='product') and p.post_status = 'publish' ".$whereSQL." ORDER BY ID".$limit_query;

        $productlist = $wpdb->get_results($mainSQL);
        $temporary_data = [];
        $children_number = 0;
        $fixed_amount_discount = 0;

        $n = 1;
        if ( count($productlist) ) :
            foreach ( $productlist AS $key => $product ) :

                $export_row = false;

                $product_id = $product->ID;
                $product_type = $product->post_type;
                $product_sku_variant = null;
                $woo_product = wc_get_product( $product->ID );
                
                // brands
                $product_brand = '';
                if (  get_option( 'wce_option_brands' )==1 ) {
                    $brands = wp_get_post_terms( $product_id, 'yith_product_brand' );
                    if ( count($brands)>0 ) {
                        $brand = $brands[0];
                        $product_brand = $brand->name;
                    }
                }

                switch ( $product_type ) :
                    
                    /**
                     * Standard Products
                     */
                    case 'product' :

                        $product_content = str_replace("|", " ", $product->post_content);
                        $product_excerpt = str_replace("|", " ", $product->post_excerpt);
                    
                        // Check if it has child/variations
                        $product_object = get_product( $product_id );
                        $children = $product_object->get_children();
                        $children_number = count($children);

                        $product_sku = get_post_meta($product_id, '_sku', true );
                        $product_name = str_replace("|", " ", $product->post_title);

                        $product_regular_price = floatval( get_post_meta($product_id, '_regular_price', true ) );
                        $product_sale_price = floatval( get_post_meta($product_id, '_sale_price', true ) );                        
                        
                        $product_stock = intval( get_post_meta($product_id, '_stock', true ) );
                        // $product_availability_array = $woo_product->get_availability();
                        // if ( $product_availability_array['class']=='out-of-stock' ) $product_stock = 0;

                        $product_weight = get_post_meta($product_id, '_weight', true );
                        $product_length = get_post_meta($product_id, '_length', true );
                        $product_width = get_post_meta($product_id, '_width', true );
                        $product_height = get_post_meta($product_id, '_height', true );

                        // force stock to 10
                        if ( get_option( 'wce_option_force_qty' )==1 && ($product_stock=='' || $product_stock==0)  ) $product_stock = 10;

                        // product images
                        $product_first_image = null;
                        if ( has_post_thumbnail($product_id) ){
                            $product_first_image = get_the_post_thumbnail_url( $product_id, 'full' );
                        }

                        // product ean
                        $product_ean = wce_get_ean($product_id);

                        $product_images = wce_get_woocommerce_product_images($product_id);

                        if ( get_option( 'woocommerce_calc_taxes' )=='no' ){ // price without taxes included
                            $product_regular_price_without_tax = $product_regular_price;
                            $product_sale_price_without_tax = $product_sale_price;
                            $product_regular_price = $product_regular_price - ( $product_regular_price * $wcedock_tax );
                            $product_sale_price = $product_sale_price - ( $product_sale_price * $wcedock_tax );
                        } else {
                            $product_regular_price_without_tax = $product_regular_price + ( $product_regular_price * $wcedock_tax );
                            $product_sale_price_without_tax = $product_sale_price + ( $product_sale_price * $wcedock_tax ); 
                        }

                        /*
                        if ( get_option( 'woocommerce_calc_taxes' ) ){ // price with taxes included
                            $product_regular_price_without_tax = $product_regular_price - ( $product_regular_price * $wcedock_tax );
                            $product_sale_price_without_tax = $product_sale_price - ( $product_sale_price * $wcedock_tax );
                        } else { // price without taxes
                            $product_regular_price_without_tax = $product_regular_price;
                            $product_sale_price_without_tax = $product_sale_price;
                            $product_regular_price = $product_regular_price + ( $product_regular_price * $wcedock_tax );
                            $product_sale_price = $product_sale_price + ( $product_sale_price * $wcedock_tax );
                        }
                        */

                        // fix measure unit
                        if ( $product_length!='' ) $product_length = $product_length/100;
                        if ( $product_width!='' ) $product_width = $product_width/100;
                        if ( $product_height!='' ) $product_height = $product_height/100;

                        $attributes = wce_get_woocommerce_product_attributes($product_id, $main_language);

                        // product categories
                        $categories = wce_get_woocommerce_product_categories($product_id);

                        // language
                        // $main_language = substr(WPLANG, 0, 2);
                        if ( !defined( 'WPLANG' ) ) $main_language = substr( strtolower( get_option( 'woocommerce_default_country' ) ), 0, 2);
                        if ( $main_language == '' || $main_language == 'WP' ) $main_language = 'it';

                        // Define if export Excerpt or Description
                        $wce_option_export_description = get_option('wce_option_export_description');
                        if ( $wce_option_export_description == 'excerpt' ):
                            $export_content = $product_excerpt;
                        else: 
                            $export_content = $product_content;
                        endif;

                        
                        $translated_data = array();
                        if ( count($languages)>0 ) :
                            foreach ($languages as $language) :

                                $translated_data_language = get_translated_post_data($product_id, $language['code']);
                                if ($translated_data_language) {
                                    $translated_title = $translated_data_language['post_title'];
                                    $translated_content = $translated_data_language['post_content'];
                                    $translated_excerpt = $translated_data_language['post_excerpt'];

                                    $translated_data[ $language['code'] ] = [
                                        'title' => $translated_title,
                                        'content' => ( $wce_option_export_description == 'excerpt' ) ? $translated_excerpt : $translated_content,
                                    ];
                                }

                            endforeach;
                        endif;

                        $secondary_attributes = '';

                        // If has child skip export variant
                        if ( $children_number>0 ) : 

                            $IDs = "";
                            foreach ( $children AS $m => $item ):
                                $IDs .= $item.', ';
                            endforeach;
                            $IDs = rtrim($IDs, ', ');

                            $export_row = true;
                            $SQL_variations = "SELECT ID,post_title,post_content,post_excerpt,post_author,post_date_gmt FROM `" . $wpdb->prefix . "posts` where ID IN (".$IDs.") and post_status = 'publish' ORDER BY ID";
                            $variationlist = $wpdb->get_results( $SQL_variations );
                            
                            foreach( $variationlist AS $x => $item ){

                                $product_regular_price = floatval( get_post_meta($item->ID, '_regular_price', true ) );
                                $product_sale_price = floatval( get_post_meta($item->ID, '_sale_price', true ) );
                                $product_stock = intval( get_post_meta($item->ID, '_stock', true ) );
                                
                                // force stock to 10
                                if ( get_option( 'wce_option_force_qty' )=='on' && ($product_stock=='' || $product_stock==0)  ) $product_stock = 10;

                                if ( get_post_meta($item->ID, '_weight', true )!='' ) $product_weight = get_post_meta($item->ID, '_weight', true );
                                if ( get_post_meta($item->ID, '_length', true )!='' )$product_length = get_post_meta($item->ID, '_length', true );
                                if ( get_post_meta($item->ID, '_width', true )!='' )$product_width = get_post_meta($item->ID, '_width', true );
                                if ( get_post_meta($item->ID, '_height', true )!='' )$product_height = get_post_meta($item->ID, '_height', true );

                                if ( get_option( 'woocommerce_calc_taxes' )=='no' ){ // price without taxes included
                                    $product_regular_price_without_tax = $product_regular_price;
                                    $product_sale_price_without_tax = $product_sale_price;
                                    $product_regular_price = $product_regular_price - ( $product_regular_price * $wcedock_tax );
                                    $product_sale_price = $product_sale_price - ( $product_sale_price * $wcedock_tax );
                                } else {
                                    $product_regular_price_without_tax = $product_regular_price + ( $product_regular_price * $wcedock_tax );
                                    $product_sale_price_without_tax = $product_sale_price + ( $product_sale_price * $wcedock_tax ); 
                                }

                                /*
                                if ( get_option( 'woocommerce_calc_taxes' ) ){ // price with taxes included
                                    $product_regular_price_without_tax = $product_regular_price - ( $product_regular_price * $wcedock_tax );
                                    $product_sale_price_without_tax = $product_sale_price - ( $product_sale_price * $wcedock_tax );
                                } else { // price without taxes
                                    $product_regular_price_without_tax = $product_regular_price;
                                    $product_sale_price_without_tax = $product_sale_price;
                                    $product_regular_price = $product_regular_price + ( $product_regular_price * $wcedock_tax );
                                    $product_sale_price = $product_sale_price + ( $product_sale_price * $wcedock_tax );
                                }
                                */

                                // fix measure unit
                                if ( $product_length!='' ) $product_length = $product_length/100;
                                if ( $product_width!='' ) $product_width = $product_width/100;
                                if ( $product_height!='' ) $product_height = $product_height/100;

                                $product_simple_id = wp_get_post_parent_id($item->ID);
                                $attributes = wce_get_woocommerce_product_attributes($item->ID, $product_simple_id, $main_language);
                                
                                // Set SKU Variant
                                $product_sku_variant = get_post_meta($item->ID, '_sku', true );
                                
                                // Generate SKU variant with attribute value
                                if ( $product_sku_variant=='' ) { 
                                    $product_sku_variant = $product_sku;
                                    if ( count($attributes['data_list'])>0 ) {
                                        foreach ( $attributes['data_list'] AS $name => $value ) {
                                            $product_sku_variant .= '_'.$value;
                                        }
                                    }
                                }

                                // Define if export Excerpt or Description
                                $export_description = get_option('wce_option_export_description');
                                if ( $export_description == 'excerpt' ):
                                    $export_content = $product_excerpt;
                                else: 
                                    $export_content = $product_content;
                                endif;

                                // Discount Percentage
                                $fixed_amount_discount = 0.00;
                                $discount_percentage = 0.00;
                                if ( $product_regular_price!='' && $product_sale_price!='' ) {
                                    $fixed_amount_discount = round( floatval($product_regular_price)-floatval($product_sale_price), 2 );
                                    $discount_percentage = round( ($fixed_amount_discount*100)/$product_regular_price, 2 );
                                }

                                // Add other attributes
                                $attributes_value_for_export = $attributes['data'];
                                $secondary_attributes = wce_get_woocommerce_product_secondary_attributes($item->ID, $product_simple_id, $main_language);
                                if ( $secondary_attributes!='' ) {
                                    $attributes_value_for_export .= '#eDock#'.$secondary_attributes;
                                }

                                $exportlist[ $item->ID ] = array(
                                    'type' => $product_type,
                                    'sku' => $product_sku,
                                    'sku_variant' => $product_sku_variant,
                                    'vat' => str_replace('0.', '', $wcedock_tax),
                                    'name' => $product_name,
                                    'main_lang' => $main_language,
                                    'content' => str_replace( array("\n", "\r", "\t"), "", $export_content ),
                                    'regular_price' => number_format($product_regular_price, 2, '.', ''), 
                                    'regular_price_no_tax' => number_format($product_regular_price_without_tax, 2, '.', ''),
                                    'sale_price' => number_format($product_sale_price, 2, '.', ''),
                                    'sale_price_no_tax' => number_format($product_sale_price_without_tax, 2, '.', ''),
                                    'ean' => $product_ean,
                                    'stock' => $product_stock,
                                    'weight' => $product_weight,
                                    'length' => $product_length,
                                    'width' => $product_width,
                                    'height' => $product_height,
                                    'image_1' => $product_first_image,
                                    'image_2' => $product_images[0],
                                    'image_3' => $product_images[1],
                                    'image_4' => $product_images[2],
                                    'image_5' => $product_images[3],
                                    'condition' => 'nuovo',
                                    'brand' => $product_brand,
                                    'category' => $categories,
                                    'shipping_cost' => 0,
                                    'attributes' => $attributes_value_for_export,
                                    'shortdescription' => '', //str_replace( array("\n", "\r", "\t"), "", strip_tags($product_excerpt) ), // <-- Removed after request from Marco
                                    'shortdescription_en' => '',
                                    'shortdescription_es' => '',
                                    'shortdescription_fr' => '',
                                    'shortdescription_de' => '',
                                    'additionalculture1' => '',
                                    'additionalculturename1' => '',
                                    'additionalcultureshortdescription1' => '',
                                    'additionalculturedescription1' => '',
                                    'additionalculture2' => '',
                                    'additionalcultureName2' => '',
                                    'additionalcultureshortdescription2' => '',
                                    'additionalculturedescription2' => '',
                                    'additionalculture3' => '',
                                    'additionalcultureName3' => '',
                                    'additionalcultureshortdescription3' => '',
                                    'additionalculturedescription3' => '',
                                    'additionalculture4' => '',
                                    'additionalcultureName4' => '',
                                    'additionalcultureshortdescription4' => '',
                                    'additionalculturedescription4' => '',
                                    'additionalculture5' => '',
                                    'additionalcultureName5' => '',
                                    'additionalcultureshortdescription5' => '',
                                    'additionalculturedescription5' => '',
                                    'variationattributesetname' => $attributes['groupname'],
                                    'image6' => '',
                                    'image7' => '',
                                    'image8' => '',
                                    'image9' => '',
                                    'image10' => '',
                                    'image11' => '',
                                    'image12' => '',
                                    'imagevariant4' => '',
                                    'imagevariant5' => '',
                                    'imagevariant6' => '',
                                    'imagevariant7' => '',
                                    'imagevariant8' => '',
                                    'imagevariant9' => '',
                                    'imagevariant10' => '',
                                    'imagevariant11' => '',
                                    'imagevariant12' => '',
                                    'fulfilmentdays' => '',
                                    'fixedamountdiscount' => number_format($fixed_amount_discount, 2, '.', ''),
                                    'percentagediscount' => '',
                                    'grossPrice2' => '',
                                    'fixedamountdiscount2' => '',
                                    'percentagediscount2' => '',
                                    'grossprice3' => '',
                                    'fixedamountdiscount3' => '',
                                    'percentagediscount3' => '',
                                    'grossprice4' => '',
                                    'fixedamountdiscount4' => '',
                                    'percentagediscount4' => '',
                                    'grossprice5' => '',
                                    'fixedamountdiscount5' => '',
                                    'percentagediscount5' => '',
                                    'grossprice6' => '',
                                    'fixedamountdiscount6' => '',
                                    'percentagediscount6' => '',
                                    'grossprice7' => '',
                                    'fixedamountdiscount7' => '',
                                    'percentagediscount7' => '',
                                    'grossprice8' => '',
                                    'fixedamountdiscount8' => '',
                                    'percentagediscount8' => '',
                                    'grossprice9' => '',
                                    'fixedamountdiscount9' => '',
                                    'percentagediscount9' => '',
                                    'grossprice10' => '',
                                    'fixedamountdiscount10' => '',
                                    'percentagediscount10' => '',
                                    'compatibilities' => '',
                                    'name_en' => isset($translated_data['en']) ? $translated_data['en']['title'] : '',
                                    'name_fr' => isset($translated_data['fr']) ? $translated_data['fr']['title'] : '',
                                    'name_es' => isset($translated_data['es']) ? $translated_data['es']['title'] : '',
                                    'name_de' => isset($translated_data['de']) ? $translated_data['de']['title'] : '',
                                    'shortdescription_en' => ( isset($translated_data['en']) ) ? $translated_data['en']['content'] : '',
                                    'shortdescription_fr' => ( isset($translated_data['fr']) ) ? $translated_data['fr']['content'] : '',
                                    'shortdescription_es' => ( isset($translated_data['es']) ) ? $translated_data['es']['content'] : '',
                                    'shortdescription_de' => ( isset($translated_data['de']) ) ? $translated_data['de']['content'] : '',
                                );

                            }

                        else:
                
                            $product_images_0 = ( isset($product_images[0]) ) ? $product_images[0] : '';
                            $product_images_1 = ( isset($product_images[1]) ) ? $product_images[1] : '';
                            $product_images_2 = ( isset($product_images[2]) ) ? $product_images[2] : '';
                            $product_images_3 = ( isset($product_images[3]) ) ? $product_images[3] : '';
                            $product_images_4 = ( isset($product_images[4]) ) ? $product_images[4] : '';

                            // force stock to 10
                            if ( get_option( 'wce_option_force_qty' )=='on' && $product_stock=='' ) $product_stock = 10;

                            // Add attributes
                            $product_simple_id = $product_id;
                            $attributes_value_for_export = wce_get_woocommerce_product_secondary_attributes($item->ID, $product_simple_id, $main_language);

                            $exportlist[ $product_id ] = array(
                                'type' => $product_type,
                                'sku' => $product_sku,
                                'sku_variant' => $product_sku_variant,
                                'vat' => str_replace('0.', '', $wcedock_tax),
                                'name' => $product_name,
                                'main_lang' => $main_language,
                                'content' => str_replace( array("\n", "\r", "\t"), "", $export_content ),
                                'regular_price' => number_format($product_regular_price, 2, '.', ''), 
                                'regular_price_no_tax' => number_format($product_regular_price_without_tax, 2, '.', ''),
                                'sale_price' => number_format($product_sale_price, 2, '.', ''),
                                'sale_price_no_tax' => number_format($product_sale_price_without_tax, 2, '.', ''),
                                'ean' => $product_ean,
                                'stock' => $product_stock,
                                'weight' => $product_weight,
                                'length' => $product_length,
                                'width' => $product_width,
                                'height' => $product_height,
                                'image_1' => $product_first_image,
                                'image_2' => $product_images_0,
                                'image_3' => $product_images_1,
                                'image_4' => $product_images_2,
                                'image_5' => $product_images_3,
                                'condition' => 'nuovo',
                                'brand' => $product_brand,
                                'category' => $categories,
                                'shipping_cost' => 0,
                                'attributes' => $attributes_value_for_export,
                                'shortdescription' => '', //str_replace( array("\n", "\r", "\t"), "", strip_tags($product_excerpt) ), // <-- Removed after request from Marco
                                'shortdescription_en' => '',
                                'shortdescription_es' => '',
                                'shortdescription_fr' => '',
                                'shortdescription_de' => '',
                                'additionalculture1' => '',
                                'additionalculturename1' => '',
                                'additionalcultureshortdescription1' => '',
                                'additionalculturedescription1' => '',
                                'additionalculture2' => '',
                                'additionalcultureName2' => '',
                                'additionalcultureshortdescription2' => '',
                                'additionalculturedescription2' => '',
                                'additionalculture3' => '',
                                'additionalcultureName3' => '',
                                'additionalcultureshortdescription3' => '',
                                'additionalculturedescription3' => '',
                                'additionalculture4' => '',
                                'additionalcultureName4' => '',
                                'additionalcultureshortdescription4' => '',
                                'additionalculturedescription4' => '',
                                'additionalculture5' => '',
                                'additionalcultureName5' => '',
                                'additionalcultureshortdescription5' => '',
                                'additionalculturedescription5' => '',
                                'variationattributesetname' => '',
                                // 'variationattributesetname' => $attributes['groupname'],
                                'image6' => '',
                                'image7' => '',
                                'image8' => '',
                                'image9' => '',
                                'image10' => '',
                                'image11' => '',
                                'image12' => '',
                                'imagevariant4' => '',
                                'imagevariant5' => '',
                                'imagevariant6' => '',
                                'imagevariant7' => '',
                                'imagevariant8' => '',
                                'imagevariant9' => '',
                                'imagevariant10' => '',
                                'imagevariant11' => '',
                                'imagevariant12' => '',
                                'fulfilmentdays' => '',
                                'fixedamountdiscount' => number_format($fixed_amount_discount, 2, '.', ''),
                                'percentagediscount' => '',
                                'grossPrice2' => '',
                                'fixedamountdiscount2' => '',
                                'percentagediscount2' => '',
                                'grossprice3' => '',
                                'fixedamountdiscount3' => '',
                                'percentagediscount3' => '',
                                'grossprice4' => '',
                                'fixedamountdiscount4' => '',
                                'percentagediscount4' => '',
                                'grossprice5' => '',
                                'fixedamountdiscount5' => '',
                                'percentagediscount5' => '',
                                'grossprice6' => '',
                                'fixedamountdiscount6' => '',
                                'percentagediscount6' => '',
                                'grossprice7' => '',
                                'fixedamountdiscount7' => '',
                                'percentagediscount7' => '',
                                'grossprice8' => '',
                                'fixedamountdiscount8' => '',
                                'percentagediscount8' => '',
                                'grossprice9' => '',
                                'fixedamountdiscount9' => '',
                                'percentagediscount9' => '',
                                'grossprice10' => '',
                                'fixedamountdiscount10' => '',
                                'percentagediscount10' => '',
                                'compatibilities' => '',
                                'name_en' => isset($translated_data['en']) ? $translated_data['en']['title'] : '',
                                'name_fr' => isset($translated_data['fr']) ? $translated_data['fr']['title'] : '',
                                'name_es' => isset($translated_data['es']) ? $translated_data['es']['title'] : '',
                                'name_de' => isset($translated_data['de']) ? $translated_data['de']['title'] : '',
                                'shortdescription_en' => ( isset($translated_data['en']) ) ? $translated_data['en']['content'] : '',
                                'shortdescription_fr' => ( isset($translated_data['fr']) ) ? $translated_data['fr']['content'] : '',
                                'shortdescription_es' => ( isset($translated_data['es']) ) ? $translated_data['es']['content'] : '',
                                'shortdescription_de' => ( isset($translated_data['de']) ) ? $translated_data['de']['content'] : '',
                            );

                        endif;

                    break;
                
                endswitch;

            endforeach;
        endif;
          
        return $exportlist;
    }
}

/**
* wce_template_to_csv()
* @param product_id
* @return string
*/
if ( !function_exists('wce_get_ean') ) {
    function wce_get_ean( $id ) {
        $ean = '';

        if ( get_post_meta($id, 'io6_eancode')[0]!='' ) {
            $ean = get_post_meta($id, 'io6_eancode')[0];
        }
        if ( get_post_meta($id, '_woosea_ean')[0]!='' ) {
            $ean = get_post_meta($id, '_woosea_ean')[0];
        }

        return $ean;
    }
}

/**
* wce_template_to_csv()
* @param productlist
* @param fieldlist array
* @return string
*/
if ( !function_exists('wce_template_to_csv') ) {
    function wce_template_to_csv( $productlist, $fieldlist ) {

        $content = '';

        foreach ( $productlist AS $key => $product ):
            if ($product['sku']!=''):
                foreach ( $fieldlist AS $fieldname ):
                    if ( isset($product[$fieldname]) ) {
                        $content .=  preg_replace("/[\n\r]/","",$product[$fieldname]).'|';
                    } else {
                        $content .= '|';
                    }
                endforeach;
                $content .= "\n";
            endif;
        endforeach;

        return $content;
    }
}

/**
* wce_get_woocommerce_product_images()
* @param product_id
* @return array
*/
if ( !function_exists('wce_get_woocommerce_product_images') ) {
    function wce_get_woocommerce_product_images($product_id){
    
        $product = new WC_product($product_id);
        $attachmentIds = $product->get_gallery_attachment_ids();
        $imgUrls = array();
        foreach( $attachmentIds as $attachmentId ) {
            $imgUrls[] = wp_get_attachment_url( $attachmentId );
        }
    
        return $imgUrls;
    }
}

/**
* wce_get_woocommerce_product_categories()
* @param product_id
* @return array
*/
if ( !function_exists('wce_get_woocommerce_product_categories') ) {
    function wce_get_woocommerce_product_categories($product_id){
        $terms = get_the_terms( $product_id, 'product_cat' );
        $list = [];

        // Populate array by key 
        if ( count($terms)>0 ):
            $parent = 0;
            foreach ($terms as $n => $term):
                $list[ $term->parent ] = $term->name;
            endforeach;
        endif;
        ksort($list);

        // Create output
        $category_string = '';
        foreach ( $list AS $z => $category ):
            $category_string .= $category.';'; 
        endforeach;
        $category_string = rtrim( $category_string, ';' );

        return $category_string;  
    }
}

if ( !function_exists('wce_define_attribute_table') ) {
    function wce_define_attribute_table(){
        global $wpdb;
        $data = [];

        $SQL = "SELECT DISTINCT t.term_id, tt.taxonomy, t.name, t.slug FROM ".$wpdb->prefix."term_relationships AS tr INNER JOIN ".$wpdb->prefix."term_taxonomy AS tt ON (tt.term_taxonomy_id=tr.term_taxonomy_id AND taxonomy LIKE \"pa_%\") INNER JOIN ".$wpdb->prefix."terms AS t ON (tt.term_id=t.term_id)";
        $query = $wpdb->get_results( $SQL );

        foreach( $query AS $key => $row ){
            $data[ $row->slug ] = [
                'label' => str_replace('pa_', '', $row->taxonomy),
                'name' => $row->name
            ];
        }

        return $data;
    }
}

/**
* wce_get_woocommerce_product_attributes()
* @param product_id
* @return array
*/
if ( !function_exists('wce_get_woocommerce_product_secondary_attributes') ) {
    function wce_get_woocommerce_product_secondary_attributes($product_id, $product_parent=null, $language='it'){
        global $wpdb;

        $SQL = "SELECT meta_value FROM ".$wpdb->prefix."postmeta WHERE post_id=".$product_parent." AND meta_key='_product_attributes'";
        $product_attributes = $wpdb->get_results($SQL, ARRAY_A);
        $product_attributes = unserialize($product_attributes[0]['meta_value']);

        // Set Name
        $set_value = '';
        if ( is_array($product_attributes) && count($product_attributes)>0 ) {
            foreach( $product_attributes AS $key => $item ) {
                if ( ($item['is_variation']!=1 || !$item['is_variation']) && $item['value']!='' ) {
                    if ( $set_value=='' ) $set_value = '[OthAttribs@'.$language.'] {';
                    $itemName = ucfirst( str_replace('pa_', '', $item['name']) );
                    $set_value .= ' s['.$itemName.']='.str_replace('|', '-', $item['value']).';';
                }
            }
            $set_value = rtrim($set_value, ';');
            if ( $set_value!='' ) $set_value .= ' }';
        }
        
        return $set_value;
    }
}

/**
* wce_get_woocommerce_product_attributes()
* @param product_id
* @return array
*/
if ( !function_exists('wce_get_woocommerce_product_attributes') ) {
    function wce_get_woocommerce_product_attributes($product_id, $product_parent=null, $language='it'){
        global $wpdb;
        global $data_attribute;

        // Get data attribute info specify for Products
        // That's createde because Monpetit have some different attributes
        // with the same slug but different label
        $attribute_table_info = [];
        if ( $product_parent!=null ) {
            $SQL = "SELECT t.term_id, wat.attribute_label, t.name, t.slug FROM wp_term_relationships AS tr INNER JOIN wp_term_taxonomy AS tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.taxonomy LIKE 'pa_%') INNER JOIN wp_terms AS t ON (tt.term_id=t.term_id) INNER JOIN wp_woocommerce_attribute_taxonomies AS wat ON ( wat.attribute_name=REPLACE(tt.taxonomy, 'pa_', '') ) WHERE object_id=".$product_parent;
            $product_attributes = $wpdb->get_results($SQL, ARRAY_A);
            
            foreach ($product_attributes as $n => $item) {
                $attribute_table_info[ $item['slug'] ] = $item;
            }
        }

        $attrib = get_post_meta($product_id);
        $string = "";
        $group_name = "";
        $data_array = [];

        if ( count($attrib) ) :
            foreach ( $attrib AS $key => $attribobj ) :

                if ( substr( $key, 0, 13 ) == 'attribute_pa_' ) :

                    if ( count($attribute_table_info)>0 ) {
                        $meta_value = $attribute_table_info[ $attribobj[0] ]['name'];
                    } else {
                        $meta_value = $data_attribute[ $attribobj[0] ]['name'];
                    }
                    
                    $keyArr = explode("-", str_replace('attribute_pa_', '', $key));
                    $key_string = "";
                    foreach ( $keyArr AS $x => $item ){
                        $key_string .= ucfirst( $item ).' ';
                    }
                    $key_string = trim($key_string);

                    $string .= " s[".str_replace('attribute_pa_', '', $key_string)."]=".$meta_value.";";
                    $data_array[ str_replace('attribute_pa_', '', $key_string) ] = $meta_value;
                    $group_name .= ucfirst( str_replace('attribute_pa_', '', $key_string) . '_' );
                    
                elseif ( substr( $key, 0, 10 ) == 'attribute_' ) :

                    if ( count($attribute_table_info)>0 ) {
                        $meta_value = $attribute_table_info[ $attribobj[0] ]['name'];
                    } else {
                        $meta_value = $data_attribute[ $attribobj[0] ]['name'];
                    }
                    
                    $meta_value = ( wce_get_woocommerce_meta_name($attribobj[0])=='' ? $attribobj[0] : wce_get_woocommerce_meta_name($attribobj[0]) );
                    $keyArr = explode("-", str_replace('attribute_', '', $key));
                    $key_string = "";
                    foreach ( $keyArr AS $x => $item ){
                        $key_string .= ucfirst( $item ).' ';
                    }
                    $key_string = trim($key_string);

                    $string .= " s[".str_replace('attribute_', '', $key_string)."]=".$meta_value.";";
                    $data_array[ str_replace('attribute_', '', $key_string) ] =  $meta_value;
                    $group_name .= ucfirst( str_replace('attribute_', '', $key_string) . '_' );

                elseif ( $key == '_product_attributes' ) :

                    // $attribobj_uns = unserialize( $attribobj[0] );
                    // var_dump($attribobj_uns);
                    // echo '<br>--------------<br>';
                    
                endif;

            endforeach;

            if ( $group_name!='' ) :
                $group_name = rtrim( $group_name, "_" )."@".$language;
                $string = rtrim( $string, ';' );
                $string = "[".$group_name."] {" . $string ." }";
            endif;

        endif;
        return [
            'groupname' => $group_name,
            'data' => $string,
            'data_list' => $data_array,
        ];
    }
}

/**
* wce_get_woocommerce_meta_name()
* @param string slug
* @return float
*/
if ( !function_exists('wce_get_woocommerce_meta_name') ) {
    function wce_get_woocommerce_meta_name($slug){
        global $wpdb;

        $sql = $wpdb->get_results("SELECT name FROM ".$wpdb->prefix."terms WHERE slug='".$slug."'");
        return $sql[0]->name;
    }
}

/**
* wce_get_woocommerce_default_tax()
* @return float
*/
if ( !function_exists('wce_get_woocommerce_default_tax') ) {
    function wce_get_woocommerce_default_tax(){
        global $wpdb;
        
        $taxes = $wpdb->get_results("SELECT tax_rate FROM ".$wpdb->prefix."woocommerce_tax_rates WHERE tax_rate_priority=1");
        if ( is_array($taxes) && isset($taxes[0]) ) {
            return $taxes[0]->tax_rate;
        } else {
            return null;
        }
    }
}

/**
* wce_output_csv()
* @return float
*/
if ( !function_exists('wce_output_csv') ) {
    function wce_output_csv() {
        $productlist = wce_get_woocommerce_product_list();
        $csvcontent = "";

        $fieldlist = array(
            'sku',
            'sku_variant', // empty
            'regular_price',
            'vat',
            'regular_price_no_tax',
            'ean', // empty
            'main_lang',
            'name',
            'content',
            'name_en', // empty
            'content_en', // empty
            'name_fr', // empty
            'content_fr', // empty
            'name_es', // empty
            'content_es', // empty
            'name_de', // empty
            'content_de', // empty
            'size', // empty
            'color', // empty
            'image_1',
            'image_2',
            'image_3',
            'image_4',
            'image_5',
            'image_variant_1', // empty
            'image_variant_2', // empty
            'image_variant_3', // empty
            'stock',
            'height',
            'length',
            'width',
            'weight',
            'condition', // fixed
            'brand', // empty
            'category',
            'shipping_cost', // default 0
            'attributes', // empty
            'shortdescription',
            'shortdescription_en', 
            'shortdescription_es', 
            'shortdescription_fr', 
            'shortdescription_de', 
            'additionalculture1', 
            'additionalculturename1', 
            'additionalcultureshortdescription1', 
            'additionalculturedescription1', 
            'additionalculture2', 
            'additionalcultureName2', 
            'additionalcultureshortdescription2', 
            'additionalculturedescription2', 
            'additionalculture3', 
            'additionalcultureName3', 
            'additionalcultureshortdescription3', 
            'additionalculturedescription3', 
            'additionalculture4', 
            'additionalcultureName4', 
            'additionalcultureshortdescription4', 
            'additionalculturedescription4', 
            'additionalculture5', 
            'additionalcultureName5', 
            'additionalcultureshortdescription5', 
            'additionalculturedescription5', 
            'variationattributesetname',
            'image6', 
            'image7', 
            'image8', 
            'image9', 
            'image10', 
            'image11', 
            'image12', 
            'imagevariant4', 
            'imagevariant5', 
            'imagevariant6', 
            'imagevariant7', 
            'imagevariant8', 
            'imagevariant9', 
            'imagevariant10', 
            'imagevariant11', 
            'imagevariant12', 
            'fulfilmentdays', 
            'fixedamountdiscount', 
            'percentagediscount', 
            'grossPrice2', 
            'fixedamountdiscount2', 
            'percentagediscount2', 
            'grossprice3', 
            'fixedamountdiscount3', 
            'percentagediscount3', 
            'grossprice4', 
            'fixedamountdiscount4', 
            'percentagediscount4', 
            'grossprice5', 
            'fixedamountdiscount5', 
            'percentagediscount5', 
            'grossprice6', 
            'fixedamountdiscount6', 
            'percentagediscount6', 
            'grossprice7', 
            'fixedamountdiscount7', 
            'percentagediscount7', 
            'grossprice8', 
            'fixedamountdiscount8', 
            'percentagediscount8', 
            'grossprice9', 
            'fixedamountdiscount9', 
            'percentagediscount9', 
            'grossprice10', 
            'fixedamountdiscount10', 
            'percentagediscount10', 
            'compatibilities'
        );

        $csvcontent .= "SKU|SKUVariant|NetPrice|VatCode|GrossPrice|EAN|MainLanguage|NameIT|DescriptionIT|NameEN|DescriptionEN|NameFR|DescriptionFR|NameES|DescriptionES|NameDE|DescriptionDE|Size|Color|Image1|Image2|Image3|Image4|Image5|ImageVariant1|ImageVariant2|ImageVariant3|Quantity|Height|Length|Width|Weight|Condition|Brand|Category|ShippingCost|Attributes|ShortDescriptionIT|ShortDescriptionEN|ShortDescriptionES|ShortDescriptionFR|ShortDescriptionDE|AdditionalCulture1|AdditionalCultureName1|AdditionalCultureShortDescription1|AdditionalCultureDescription1|AdditionalCulture2|AdditionalCultureName2|AdditionalCultureShortDescription2|AdditionalCultureDescription2|AdditionalCulture3|AdditionalCultureName3|AdditionalCultureShortDescription3|AdditionalCultureDescription3|AdditionalCulture4|AdditionalCultureName4|AdditionalCultureShortDescription4|AdditionalCultureDescription4|AdditionalCulture5|AdditionalCultureName5|AdditionalCultureShortDescription5|AdditionalCultureDescription5|VariationAttributeSetName|Image6|Image7|Image8|Image9|Image10|Image11|Image12|ImageVariant4|ImageVariant5|ImageVariant6|ImageVariant7|ImageVariant8|ImageVariant9|ImageVariant10|ImageVariant11|ImageVariant12|FulfilmentDays|FixedAmountDiscount|PercentageDiscount|GrossPrice2|FixedAmountDiscount2|PercentageDiscount2|GrossPrice3|FixedAmountDiscount3|PercentageDiscount3|GrossPrice4|FixedAmountDiscount4|PercentageDiscount4|GrossPrice5|FixedAmountDiscount5|PercentageDiscount5|GrossPrice6|FixedAmountDiscount6|PercentageDiscount6|GrossPrice7|FixedAmountDiscount7|PercentageDiscount7|GrossPrice8|FixedAmountDiscount8|PercentageDiscount8|GrossPrice9|FixedAmountDiscount9|PercentageDiscount9|GrossPrice10|FixedAmountDiscount10|PercentageDiscount10|Compatibilities|\n";
        $csvcontent .= wce_template_to_csv($productlist, $fieldlist);

        // Create File on Uploads Directory
        $file = fopen( WOO_ED_UPLOAD_DIR . "/export.csv", "w+") or die("Unable to open file!");
        fwrite($file, $csvcontent);
        fclose($file);
    }
}

/**
 * wce_activation()
 * @return none
 */
if ( !function_exists('wce_activation') ) {
    function wce_activation() {
        // Register plugin options
        register_setting( 'woocommerce-edock-exporter-settings-group', 'wce_option_status' );
        register_setting( 'woocommerce-edock-exporter-settings-group', 'wce_option_ts_latest_update' );
        // Update default options
        add_option('wce_option_status', 'off', 'yes');
        add_option('wce_option_ts_latest_update', '', 'yes');
        add_option('wce_option_email', '', 'yes');
        // Create folder for export
        if ( !file_exists(WOO_ED_UPLOAD_DIR) ) {
             mkdir(WOO_ED_UPLOAD_DIR, 0755, true) or die('Unable to create directory');
             chown(WOO_ED_UPLOAD_DIR, 'www-data') or die('Unable to set owner to directory');
        }
    }
}

/**
* wce_cron_activation()
* @return none
*/
if ( !function_exists('wce_cron_activation') ) {
    function wce_cron_activation() {
        $startday = mktime(0, 10, 0);
        wp_schedule_event($startday,'daily','wce_cron_event');
    }
}

/**
 * wce_deactivation
 * @return none
 */
if ( !function_exists('wce_deactivation') ) {
    function wce_deactivation() {
        delete_option('wce_option_status');
        delete_option('wce_option_ts_latest_update');
    }
}

/**
 * wce_cron_deactivation
 * @return none
 */
if ( !function_exists('wce_cron_deactivation') ) {
    function wce_cron_deactivation() {
        wp_clear_scheduled_hook('wce_cron_event');
    }
}

/**
* wce_cron_exec()
* @return none
*/ 
if ( !function_exists('wce_cron_exec') ) {
    function wce_cron_exec() {
        update_option( 'wce_option_ts_latest_update', time() );
        wce_output_csv();
        
        // Send email to debug
        $wce_option_email = get_option('wce_option_email');
        if ( isset($wce_option_email) && $wce_option_email!='' ) {
            wp_mail($wce_option_email,'Sync eDock '.$_SERVER['HTTP_HOST'],'Files created on '.date('d/m/Y h:i:s', time()));
        }
    }
}

/**
 * Funzione per ottenere il titolo e il contenuto tradotto di un post in base alla lingua specificata.
 *
 * @param int    $post_id ID del post di cui si desidera ottenere la traduzione.
 * @param string $language_slug Lo slug della lingua desiderata (es. 'en' per l'inglese, 'it' per l'italiano).
 *
 * @return array|null Un array contenente 'post_title' e 'post_content' tradotti, oppure null se la traduzione non è disponibile.
 */
function get_translated_post_data($post_id, $language_slug) {
    // Controlla se WPML è attivo
    if (function_exists('icl_object_id')) {
        // Ottieni l'ID del post tradotto
        $translated_post_id = icl_object_id($post_id, 'post', false, $language_slug);

        // Se la traduzione non è disponibile, ritorna null
        if (!$translated_post_id) {
            return null;
        }

        // Ottieni il post tradotto in base all'ID
        $translated_post = get_post($translated_post_id);

        // Se il post tradotto esiste, restituisci il titolo e il contenuto
        if ($translated_post) {
            $translated_data = array(
                'post_title'   => $translated_post->post_title,
                'post_content' => $translated_post->post_content,
                'post_excerpt' => $translated_post->post_excerpt,
            );
            return $translated_data;
        }
    }

    // Se WPML non è attivo o la traduzione non è disponibile, ritorna null
    return null;
}

?>