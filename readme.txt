=== WooCommerce - eDock Exporter ===
Contributors: alessandroalessio
Tags: edock
Requires at least: 5.2.2
Tested up to: 5.6
Requires PHP: 5.2
Stable tag: trunk
License: GPL2
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

This plugin allow to share all your woocommerce products on your edock\'s account

== Installation ==
Just install from your WordPress \"Plugins > Add New\" screen and all will be well. Manual installation is very straightforward as well:

1. Upload the zip file and unzip it in the `/wp-content/plugins/` directory
1. Activate the plugin through the \'Plugins\' menu in WordPress
1. Go to `Export eDock > Save Modify` and get the `URL file` to input on your eDock Accounts.


== Changelog ==
Version 1.4.0
-  Integration for WPML

Version 1.3.7
- Bugfix n.2 for wrong calc on Woocommerce included/excluded taxes on products

Version 1.3.6
- Bugfix for wrong calc on Woocommerce included/excluded taxes on products

Version 1.3.5
- Bugfix for wrong data storage on db for force quantity field

Version 1.3.4
- Bugfix n.2 for prefix table on "other attributes"

Version 1.3.3
- Bugfix for prefix table on "other attributes"

Version 1.3.2
- Implementation for "other attributes" for product simple

Version 1.3.1
- Bugfix for "other attributes" (not variations attributes)
- Add two different options of post meta field for export EAN code

Version 1.2.6
- Bugfix for not saved checkbox in Force Quantity (rev.2)

Version 1.2.5
- Bugfix for not saved checkbox in Force Quantity

Version 1.2.4
- Bugfix for Force Quantity in Product Variations

Version 1.2.3
- Changed ShortDescription heading in ShortDescriptionIT for new version of eDock

Version 1.2.2
- Replacing pipe in Title, Description and Excerpt

Version 1.2.1
- Enable default quantity (10 products) where not set "Enable Storage"

Version 1.2.0
- Resolved some PHP Error for other instance of Carbon PHP Library

Version 1.1.9
- Resolved some PHP Error for other instance of Carbon PHP Library

Version 1.1.8
- Resolved some PHP Notice on PHP 7.2
- Tested on Wordpress 5.3.2 and Woocommerce 3.8.1

Version 1.1.7
- Bugfix in new lines
- Tested on Wordpress 5.2.4

Version 1.1.6
- Bugfix for wrong FixedAmountDiscount
- Tested on Wordpress 5.2.4

Version 1.1.5
- Bugfix for not default WP prefix table
- Removed export for product withous SKU defined
- Tested on Wordpress 5.2.4

Version 1.1.4
- Bugfix for 404 icons on admin
- Tested on Wordpress 5.2.2 

Version 1.1.3
- Export variations of Products
- Enable Brand export with plugin YITH WooCommerce Brands Add-On

Version 1.1.2
- Export variations of Products
- Enable Brand export with plugin YITH WooCommerce Brands Add-On

Version 1.1.1
- If WPLANG is not defined force to \'it\'