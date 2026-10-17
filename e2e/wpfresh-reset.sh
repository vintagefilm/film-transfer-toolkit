#!/bin/bash
# Reset the fresh local test site to "never had the toolkit" with fixtures.
set -e
cd /workspace/wpfresh
wp plugin deactivate film-transfer-toolkit >/dev/null 2>&1 || true

rm -f wp-content/database/.ht.*/.ht.sqlite* wp-content/debug.log
wp core install --url=http://localhost:8090 --title="Fresh Film Lab" --admin_user=admin --admin_password=admin --admin_email=owner@example.test --skip-email >/dev/null 2>&1
wp plugin activate sqlite-database-integration >/dev/null 2>&1
wp rewrite structure '/%postname%/' >/dev/null 2>&1
wp post create --post_type=page --post_status=publish --post_title="Film Calculator" --post_name=film-calculator --post_content='<!-- wp:paragraph --><p>How long is your film?</p><!-- /wp:paragraph --><!-- wp:shortcode -->[film_calculator]<!-- /wp:shortcode -->' --porcelain >/dev/null 2>&1
wp post create --post_type=page --post_status=publish --post_title="Pricing" --post_name=film-transfer-prices --post_content='<p>Our old price list (no tool here).</p>' --porcelain >/dev/null 2>&1
wp menu create "Main" --porcelain >/dev/null 2>&1
wp eval 'update_option("nav_menu_options", array("auto_add"=>array( (int) wp_get_nav_menu_object("Main")->term_id )));' >/dev/null 2>&1
echo "reset: $(wp post list --post_type=page --format=ids 2>/dev/null) ftt=$(wp option get ftt_modules 2>/dev/null || echo none)"
