<?php
/**
 * Plugin Name: Parilté Checkout Suite
 * Description: Ücretsiz kargo ilerleme barı, dinamik teslimat (ETA) mesajı ve TR’ye uygun sade checkout alanları.
 * Version: 1.1.1
 * Author: Parilté
 */

if (!defined('ABSPATH')) exit;

/* ==========================================================
 * 0) KODDAN KONTROL (WP’ye girmeden aç/kapat)
 * ========================================================== */
if (!defined('PARILTE_CS_ON'))              define('PARILTE_CS_ON', true);
if (!defined('PARILTE_CS_FREEBAR'))         define('PARILTE_CS_FREEBAR', true);
if (!defined('PARILTE_CS_ETA'))             define('PARILTE_CS_ETA', true);
if (!defined('PARILTE_CS_CHECKOUT_FIELDS')) define('PARILTE_CS_CHECKOUT_FIELDS', true);
if (!defined('PARILTE_CS_PAYBADGES'))       define('PARILTE_CS_PAYBADGES', true);
if (!defined('PARILTE_CS_MINI_SUMMARY'))    define('PARILTE_CS_MINI_SUMMARY', true);
if (!defined('PARILTE_CS_VALIDATION'))      define('PARILTE_CS_VALIDATION', true);
if (!defined('PARILTE_BOOTSTRAP_ON'))       define('PARILTE_BOOTSTRAP_ON', true);
if (!defined('PARILTE_AUTO_HEADER'))        define('PARILTE_AUTO_HEADER', true);
if (!defined('PARILTE_CUSTOM_HEADER'))      define('PARILTE_CUSTOM_HEADER', true);
if (!defined('PARILTE_AUTO_FRONT'))         define('PARILTE_AUTO_FRONT', true);
if (!defined('PARILTE_DEMO_CONTENT'))       define('PARILTE_DEMO_CONTENT', true);
if (!defined('PARILTE_DEMO_IMAGES'))        define('PARILTE_DEMO_IMAGES', true);
if (!defined('PARILTE_DEMO_TUNING'))        define('PARILTE_DEMO_TUNING', false);
if (!defined('PARILTE_DEMO_BLOG'))          define('PARILTE_DEMO_BLOG', false);
if (!defined('PARILTE_WIDGETS_ON'))         define('PARILTE_WIDGETS_ON', true);
if (!defined('PARILTE_FILTERS_ON'))         define('PARILTE_FILTERS_ON', false);
if (!defined('PARILTE_SHIPPING_ON'))        define('PARILTE_SHIPPING_ON', true);
if (!defined('PARILTE_PAYMENTS_ON'))        define('PARILTE_PAYMENTS_ON', true);
if (!defined('PARILTE_BOOTSTRAP_VERSION'))  define('PARILTE_BOOTSTRAP_VERSION', '8');

// Opsiyonları koddan ezmek istersen:
if (!defined('PARILTE_CS_FREE_THRESHOLD')) define('PARILTE_CS_FREE_THRESHOLD', 1500);
if (!defined('PARILTE_CS_FLAT_RATE_COST')) define('PARILTE_CS_FLAT_RATE_COST', 100);
if (!defined('PARILTE_CS_ETA_MIN'))        define('PARILTE_CS_ETA_MIN', 2);
if (!defined('PARILTE_CS_ETA_MAX'))        define('PARILTE_CS_ETA_MAX', 5);
if (!defined('PARILTE_CS_CUTOFF_HOUR'))    define('PARILTE_CS_CUTOFF_HOUR', 15);
if (!defined('PARILTE_CS_WEEKEND'))        define('PARILTE_CS_WEEKEND', 0); // 1: hafta sonu kargo işler
if (!defined('PARILTE_CS_FREE_TEXT'))      define('PARILTE_CS_FREE_TEXT', 'Ücretsiz kargoya sadece {left} kaldı');

/* ==========================================================
 * A) (İsteğe bağlı) Ayarlar sayfası
 * ========================================================== */
add_action('admin_menu', function () {
    add_options_page('Parilté Checkout','Parilté Checkout','manage_options','parilte-checkout', function () {
        ?>
        <div class="wrap">
          <h1>Parilté Checkout Ayarları</h1>
          <form method="post" action="options.php">
            <?php settings_fields('parilte_checkout'); do_settings_sections('parilte_checkout'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row">Ücretsiz kargo eşiği (₺)</th>
                <td><input type="number" step="1" min="0" name="parilte_free_threshold" value="<?php echo esc_attr(get_option('parilte_free_threshold', PARILTE_CS_FREE_THRESHOLD)); ?>" style="width:160px"></td>
              </tr>
              <tr>
                <th scope="row">Teslimat (min–max iş günü)</th>
                <td>
                  <input type="number" step="1" min="0" name="parilte_eta_min" value="<?php echo esc_attr(get_option('parilte_eta_min', PARILTE_CS_ETA_MIN)); ?>" style="width:70px"> –
                  <input type="number" step="1" min="0" name="parilte_eta_max" value="<?php echo esc_attr(get_option('parilte_eta_max', PARILTE_CS_ETA_MAX)); ?>" style="width:70px">
                </td>
              </tr>
              <tr>
                <th scope="row">Kargo kesim saati</th>
                <td><input type="number" step="1" min="0" max="23" name="parilte_cutoff_hour" value="<?php echo esc_attr(get_option('parilte_cutoff_hour', PARILTE_CS_CUTOFF_HOUR)); ?>" style="width:80px">:00</td>
              </tr>
              <tr>
                <th scope="row">Hafta sonu kargo işler mi?</th>
                <td><label><input type="checkbox" name="parilte_weekend" value="1" <?php checked(get_option('parilte_weekend', PARILTE_CS_WEEKEND), 1); ?>> Evet</label></td>
              </tr>
              <tr>
                <th scope="row">Ücretsiz kargo metni</th>
                <td><input type="text" name="parilte_free_text" value="<?php echo esc_attr(get_option('parilte_free_text', PARILTE_CS_FREE_TEXT)); ?>" style="width:420px">
                  <p class="description">{left} → “₺X” olarak doldurulur.</p>
                </td>
              </tr>
            </table>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    });
});
add_action('admin_init', function () {
    register_setting('parilte_checkout','parilte_free_threshold');
    register_setting('parilte_checkout','parilte_eta_min');
    register_setting('parilte_checkout','parilte_eta_max');
    register_setting('parilte_checkout','parilte_cutoff_hour');
    register_setting('parilte_checkout','parilte_weekend');
    register_setting('parilte_checkout','parilte_free_text');
});

/* ==========================================================
 * Yardımcılar
 * ========================================================== */
function parilte_cs_opt($key, $def=null){
    // Koddan override varsa onu döndür.
    $map = [
        'parilte_free_threshold' => 'PARILTE_CS_FREE_THRESHOLD',
        'parilte_eta_min'        => 'PARILTE_CS_ETA_MIN',
        'parilte_eta_max'        => 'PARILTE_CS_ETA_MAX',
        'parilte_cutoff_hour'    => 'PARILTE_CS_CUTOFF_HOUR',
        'parilte_weekend'        => 'PARILTE_CS_WEEKEND',
        'parilte_free_text'      => 'PARILTE_CS_FREE_TEXT',
    ];
    if (isset($map[$key]) && defined($map[$key])) return constant($map[$key]);
    return get_option($key, $def);
}

function parilte_cs_iyzico_env() {
    if (defined('IYZICO_ENV') && IYZICO_ENV) return IYZICO_ENV;
    if (defined('WP_ENV') && WP_ENV === 'production') return 'live';
    return 'sandbox';
}

function parilte_cs_iyzico_endpoint() {
    return (parilte_cs_iyzico_env() === 'live')
        ? 'https://api.iyzipay.com'
        : 'https://sandbox-api.iyzipay.com';
}

function parilte_cs_business_add(\DateTime $dt, $days, $weekend_on=false){
    if ($days <= 0) return $dt;
    while ($days > 0) {
        $dt->modify('+1 day');
        if ($weekend_on) { $days--; continue; }
        $w = (int) $dt->format('N'); // 1=Mon ... 7=Sun
        if ($w <= 5) $days--;
    }
    return $dt;
}

function parilte_cs_eta_range(){
    $tz = wp_timezone();
    $now = new DateTime('now', $tz);
    $min = (int) parilte_cs_opt('parilte_eta_min', PARILTE_CS_ETA_MIN);
    $max = (int) parilte_cs_opt('parilte_eta_max', PARILTE_CS_ETA_MAX);
    $cut = (int) parilte_cs_opt('parilte_cutoff_hour', PARILTE_CS_CUTOFF_HOUR);
    $wkd = (int) parilte_cs_opt('parilte_weekend', PARILTE_CS_WEEKEND) === 1;

    if ((int)$now->format('G') >= $cut) { $min++; $max++; }
    $d1 = parilte_cs_business_add(clone $now, $min, $wkd);
    $d2 = parilte_cs_business_add(clone $now, $max, $wkd);
    $fmt1 = date_i18n('j F', $d1->getTimestamp());
    $fmt2 = date_i18n('j F', $d2->getTimestamp());
    return [$fmt1, $fmt2];
}

function parilte_cs_cart_subtotal(){
    if (!WC()->cart) return 0;
    return (float) WC()->cart->get_subtotal();
}

function parilte_cs_set_page_content_if_empty($page_id, $content){
    $page = get_post($page_id);
    if (!$page) return;
    if (trim((string)$page->post_content) === '') {
        wp_update_post(['ID'=>(int)$page_id, 'post_content'=>$content]);
    }
}

function parilte_cs_add_widget_instance($id_base, $settings){
    $opt = 'widget_' . $id_base;
    $widgets = get_option($opt, []);
    if (!is_array($widgets)) $widgets = [];

    foreach ($widgets as $k => $v) {
        if (!is_numeric($k)) continue;
        if ($v == $settings) return $id_base . '-' . $k;
    }

    $next = 1;
    foreach ($widgets as $k => $_) {
        if (is_numeric($k) && (int)$k >= $next) $next = (int)$k + 1;
    }
    $widgets[$next] = $settings;
    update_option($opt, $widgets);

    return $id_base . '-' . $next;
}

function parilte_cs_add_widget_to_sidebar($sidebar_id, $id_base, $settings){
    $sidebars = wp_get_sidebars_widgets();
    if (!is_array($sidebars)) $sidebars = [];
    if (!isset($sidebars[$sidebar_id]) || !is_array($sidebars[$sidebar_id])) $sidebars[$sidebar_id] = [];

    $widget_id = parilte_cs_add_widget_instance($id_base, $settings);
    if (!in_array($widget_id, $sidebars[$sidebar_id], true)) {
        $sidebars[$sidebar_id][] = $widget_id;
        wp_set_sidebars_widgets($sidebars);
    }
}

function parilte_cs_sidebar_has_product_categories($sidebar_id){
    $sidebars = wp_get_sidebars_widgets();
    if (!is_array($sidebars)) return false;
    if (empty($sidebars[$sidebar_id]) || !is_array($sidebars[$sidebar_id])) return false;

    $block_widgets = get_option('widget_block', []);
    foreach ($sidebars[$sidebar_id] as $widget_id) {
        if (strpos($widget_id, 'woocommerce_product_categories') === 0) return true;
        if (strpos($widget_id, 'wc-block-product-categories') === 0) return true;
        if (strpos($widget_id, 'block-') === 0) {
            $block_id = (int) str_replace('block-', '', $widget_id);
            if (isset($block_widgets[$block_id]['content'])) {
                $content = (string) $block_widgets[$block_id]['content'];
                if (strpos($content, 'woocommerce/product-categories') !== false) return true;
                if (strpos($content, 'woocommerce-product-categories') !== false) return true;
            }
        }
    }
    return false;
}

function parilte_cs_setup_widgets(){
    if (!PARILTE_WIDGETS_ON) return;
    if (!class_exists('WC_Widget_Product_Search')) return;

    $sidebar_id = 'sidebar-woocommerce';
    parilte_cs_add_widget_to_sidebar($sidebar_id, 'woocommerce_product_search', ['title'=>'Ürün Ara']);
    if (!parilte_cs_sidebar_has_product_categories($sidebar_id)) {
        parilte_cs_add_widget_to_sidebar($sidebar_id, 'woocommerce_product_categories', [
            'title'=>'Kategoriler','orderby'=>'name','hierarchical'=>1,'count'=>0,'dropdown'=>0
        ]);
    }
    if (class_exists('WC_Widget_Price_Filter')) {
        parilte_cs_add_widget_to_sidebar($sidebar_id, 'woocommerce_price_filter', ['title'=>'Fiyata Göre Filtrele']);
    }
    if (class_exists('WC_Widget_Layered_Nav')) {
        if (taxonomy_exists('pa_beden')) {
            parilte_cs_add_widget_to_sidebar($sidebar_id, 'woocommerce_layered_nav', [
                'title'=>'Beden','attribute'=>'beden','display_type'=>'list','query_type'=>'and'
            ]);
        }
        if (taxonomy_exists('pa_renk')) {
            parilte_cs_add_widget_to_sidebar($sidebar_id, 'woocommerce_layered_nav', [
                'title'=>'Renk','attribute'=>'renk','display_type'=>'list','query_type'=>'and'
            ]);
        }
    }
}

add_filter('woocommerce_product_categories_widget_args', function ($args) {
    $args['hierarchical'] = 1;
    $args['depth'] = 0;
    $args['show_children_only'] = 0;
    $args['hide_empty'] = 0;
    $args['count'] = 0;
    return $args;
}, 20);

add_filter('sidebars_widgets', function ($sidebars) {
    $sidebar_id = 'sidebar-woocommerce';
    if (empty($sidebars[$sidebar_id]) || !is_array($sidebars[$sidebar_id])) return $sidebars;

    $block_widgets = get_option('widget_block', []);
    $filtered = [];
    $seen_cat = false;

    foreach ($sidebars[$sidebar_id] as $widget_id) {
        $is_cat = false;

        if (strpos($widget_id, 'woocommerce_product_categories') === 0) $is_cat = true;
        if (strpos($widget_id, 'wc-block-product-categories') === 0) $is_cat = true;
        if (strpos($widget_id, 'block-') === 0) {
            $block_id = (int) str_replace('block-', '', $widget_id);
            if (isset($block_widgets[$block_id]['content'])) {
                $content = (string) $block_widgets[$block_id]['content'];
                if (strpos($content, 'woocommerce/product-categories') !== false) $is_cat = true;
                if (strpos($content, 'woocommerce-product-categories') !== false) $is_cat = true;
            }
        }

        if ($is_cat) {
            if ($seen_cat) continue;
            $seen_cat = true;
        }

        $filtered[] = $widget_id;
    }

    $sidebars[$sidebar_id] = $filtered;
    return $sidebars;
}, 20);

function parilte_cs_setup_shipping(){
    if (!PARILTE_SHIPPING_ON) return;
    if (!class_exists('WC_Shipping_Zone')) return;

    $flat_cost = (float) PARILTE_CS_FLAT_RATE_COST;
    $free_min  = (float) parilte_cs_opt('parilte_free_threshold', PARILTE_CS_FREE_THRESHOLD);

    $zone_id = 0;
    $zones = WC_Shipping_Zones::get_zones();
    foreach ($zones as $z) {
        if (empty($z['zone_locations'])) continue;
        foreach ($z['zone_locations'] as $loc) {
            if ($loc->type === 'country' && $loc->code === 'TR') {
                $zone_id = (int)$z['zone_id'];
                break 2;
            }
        }
    }

    if (!$zone_id) {
        $zone = new WC_Shipping_Zone();
        $zone->set_zone_name('Türkiye');
        $zone->add_location('TR','country');
        $zone_id = $zone->save();
    }

    $zone = new WC_Shipping_Zone($zone_id);
    $methods = $zone->get_shipping_methods();
    $has_flat = false;
    $has_free = false;
    foreach ($methods as $m) {
        if ($m->id === 'flat_rate') {
            $has_flat = true;
            $instance_id = $m->get_instance_id();
            $settings = (array) get_option('woocommerce_flat_rate_'.$instance_id.'_settings', []);
            $settings['cost'] = (string) $flat_cost;
            if (empty($settings['title'])) $settings['title'] = 'Standart Kargo';
            $settings['enabled'] = 'yes';
            if (empty($settings['tax_status'])) $settings['tax_status'] = 'taxable';
            update_option('woocommerce_flat_rate_'.$instance_id.'_settings', $settings);
        }
        if ($m->id === 'free_shipping') {
            $has_free = true;
            $instance_id = $m->get_instance_id();
            $settings = (array) get_option('woocommerce_free_shipping_'.$instance_id.'_settings', []);
            $settings['min_amount'] = (string) $free_min;
            $settings['requires'] = 'min_amount';
            if (empty($settings['title'])) $settings['title'] = 'Ücretsiz Kargo';
            $settings['enabled'] = 'yes';
            update_option('woocommerce_free_shipping_'.$instance_id.'_settings', $settings);
        }
    }

    if (!$has_flat) {
        $instance_id = $zone->add_shipping_method('flat_rate');
        update_option('woocommerce_flat_rate_'.$instance_id.'_settings', [
            'title' => 'Standart Kargo',
            'tax_status' => 'taxable',
            'cost' => (string) $flat_cost,
            'enabled' => 'yes',
        ]);
    }

    if (!$has_free) {
        $instance_id = $zone->add_shipping_method('free_shipping');
        update_option('woocommerce_free_shipping_'.$instance_id.'_settings', [
            'title' => 'Ücretsiz Kargo',
            'requires' => 'min_amount',
            'min_amount' => (string) $free_min,
            'enabled' => 'yes',
        ]);
    }
}

function parilte_cs_setup_tax_rate_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_tax_10_set')) return;
    if (!function_exists('WC_Tax')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'woocommerce_tax_rates';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) return;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT tax_rate_id FROM $table WHERE tax_rate_country=%s AND tax_rate_state='' LIMIT 1",
        'TR'
    ));
    if (!$exists) {
        $wpdb->insert($table, [
            'tax_rate_country' => 'TR',
            'tax_rate_state' => '',
            'tax_rate' => '10.0000',
            'tax_rate_name' => 'KDV',
            'tax_rate_priority' => 1,
            'tax_rate_compound' => 0,
            'tax_rate_shipping' => 1,
            'tax_rate_order' => 0,
            'tax_rate_class' => '',
        ]);
    }
    update_option('parilte_tax_10_set', 1);
}

function parilte_cs_setup_payments(){
    if (!PARILTE_PAYMENTS_ON) return;
    if (!function_exists('WC') || !WC()->payment_gateways) return;

    $gateways = WC()->payment_gateways->payment_gateways();
    $enabled_non = [];
    foreach ($gateways as $id => $gw) {
        if (in_array($id, ['cod','bacs'], true)) continue;
        $settings = (array) get_option('woocommerce_'.$id.'_settings', []);
        if (!empty($settings['enabled']) && $settings['enabled'] === 'yes') $enabled_non[] = $id;
    }

    if (!empty($enabled_non)) {
        foreach (['cod','bacs'] as $id) {
            $settings = (array) get_option('woocommerce_'.$id.'_settings', []);
            if (!empty($settings)) {
                $settings['enabled'] = 'no';
                update_option('woocommerce_'.$id.'_settings', $settings);
            }
        }
        update_option('woocommerce_default_gateway', $enabled_non[0]);
    }
}

function parilte_cs_create_simple_product($name, $sku, $price, $cat_ids, $short = '', $desc = ''){
    if (!class_exists('WC_Product_Simple')) return 0;
    if ($sku && wc_get_product_id_by_sku($sku)) return (int) wc_get_product_id_by_sku($sku);

    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_regular_price($price);
    if ($sku) $product->set_sku($sku);
    if ($short) $product->set_short_description($short);
    if ($desc) $product->set_description($desc);
    if (!empty($cat_ids)) $product->set_category_ids(array_map('intval', $cat_ids));
    $product->set_manage_stock(true);
    $product->set_stock_quantity(20);
    $product_id = $product->save();

    if ($product_id) update_post_meta($product_id, '_parilte_seed', '1');
    return (int) $product_id;
}

function parilte_cs_create_variable_product($name, $sku, $price, $cat_ids, $sizes, $colors){
    if (!class_exists('WC_Product_Variable')) return 0;
    if ($sku && wc_get_product_id_by_sku($sku)) return (int) wc_get_product_id_by_sku($sku);

    $product = new WC_Product_Variable();
    $product->set_name($name);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    if ($sku) $product->set_sku($sku);
    if (!empty($cat_ids)) $product->set_category_ids(array_map('intval', $cat_ids));
    $product->set_description('Günlük kullanım için rahat kalıp ve sezonluk renkler.');
    $product->set_short_description('Beden ve renk varyasyonlu ürün.');

    $attrs = [];
    $size_ids = [];
    if (taxonomy_exists('pa_beden')) {
        foreach ($sizes as $s) {
            $term = get_term_by('name', $s, 'pa_beden');
            if (!$term) $term = get_term_by('slug', strtolower($s), 'pa_beden');
            if ($term) $size_ids[] = (int) $term->term_id;
        }
        $attr = new WC_Product_Attribute();
        $attr->set_id(wc_attribute_taxonomy_id_by_name('beden'));
        $attr->set_name('pa_beden');
        $attr->set_options($size_ids);
        $attr->set_visible(true);
        $attr->set_variation(true);
        $attrs[] = $attr;
    }
    $color_ids = [];
    if (taxonomy_exists('pa_renk')) {
        foreach ($colors as $c) {
            $slug = sanitize_title($c);
            $term = get_term_by('name', $c, 'pa_renk');
            if (!$term) $term = get_term_by('slug', $slug, 'pa_renk');
            if ($term) $color_ids[] = (int) $term->term_id;
        }
        $attr = new WC_Product_Attribute();
        $attr->set_id(wc_attribute_taxonomy_id_by_name('renk'));
        $attr->set_name('pa_renk');
        $attr->set_options($color_ids);
        $attr->set_visible(true);
        $attr->set_variation(true);
        $attrs[] = $attr;
    }

    $product->set_attributes($attrs);
    $product_id = $product->save();
    if (!$product_id) return 0;

    $variations = [
        ['beden'=>'s','renk'=>'siyah'],
        ['beden'=>'m','renk'=>'beyaz'],
        ['beden'=>'l','renk'=>'gri'],
        ['beden'=>'xl','renk'=>'haki'],
    ];

    foreach ($variations as $v) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        $variation->set_attributes([
            'pa_beden' => $v['beden'],
            'pa_renk'  => $v['renk'],
        ]);
        $variation->set_regular_price($price);
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity(10);
        $variation->set_status('publish');
        $variation->save();
    }

    update_post_meta($product_id, '_parilte_seed', '1');
    return (int) $product_id;
}

function parilte_cs_seed_demo_content($cat){
    if (!PARILTE_DEMO_CONTENT) return;
    if (!class_exists('WooCommerce')) return;
    if (get_option('parilte_demo_done')) return;

    $p1 = parilte_cs_create_simple_product(
        'Parilté Basic Tişört', 'PAR-TEE-001', '299',
        [$cat['ust']], 'Yumuşak dokulu, günlük kullanım için ideal.',
        'Pamuklu kumaş, rahat kesim.'
    );
    $p2 = parilte_cs_create_simple_product(
        'Parilté Jean Pantolon', 'PAR-JEAN-001', '799',
        [$cat['alt']], 'Klasik kesim, zamansız jean.',
        'Günlük kombinler için vazgeçilmez.'
    );
    $p3 = parilte_cs_create_simple_product(
        'Parilté Triko Kazak', 'PAR-KZK-001', '649',
        [$cat['ust']], 'Mevsim geçişleri için sıcak tutar.',
        'Yumuşak triko dokusu.'
    );
    $p4 = parilte_cs_create_simple_product(
        'Parilté Kaban', 'PAR-KBN-001', '1290',
        [$cat['dis']], 'Soğuk havalar için şık kaban.',
        'Astarlı, rahat kullanım.'
    );
    $p5 = parilte_cs_create_simple_product(
        'Parilté Çanta', 'PAR-CNT-001', '459',
        [$cat['aksesuar']], 'Günlük kullanım için pratik.',
        'Minimal tasarım.'
    );

    $p6 = parilte_cs_create_variable_product(
        'Parilté Oversize Sweatshirt', 'PAR-SWT-001', '899',
        [$cat['ust']], ['S','M','L','XL'], ['Siyah','Beyaz','Gri','Haki']
    );

    if (PARILTE_DEMO_BLOG) {
        $cat_id = 0;
        $cat_term = get_term_by('slug', 'stil', 'category');
        if ($cat_term) $cat_id = (int) $cat_term->term_id;
        if (!$cat_id) {
            $res = wp_insert_term('Stil', 'category', ['slug'=>'stil']);
            if (!is_wp_error($res)) $cat_id = (int) $res['term_id'];
        }

        $posts = [
            ['Kapsül Gardırop: 7 Parça ile 14 Kombin', 'kapsul-gardrop', 'Az parça ile çok kombin fikri için ilham.'],
            ['Yeni Sezon Renkleri: Gri & Bej', 'sezon-renkleri', 'Minimal ve zamansız renklerle güçlü görünüm.'],
            ['Şehirde Rahat Stil', 'sehirde-rahat-stil', 'Günlük koşturmaya uygun, şık ve rahat seçimler.'],
        ];
        foreach ($posts as $p) {
            if (get_page_by_path($p[1], OBJECT, 'post')) continue;
            wp_insert_post([
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_title' => $p[0],
                'post_name' => $p[1],
                'post_content' => $p[2],
                'post_category' => $cat_id ? [$cat_id] : [],
            ]);
        }
    }

    update_option('parilte_demo_done', 1);
}

function parilte_cs_seed_demo_tuning() {
    if (!PARILTE_DEMO_TUNING) return;
    if (!PARILTE_DEMO_CONTENT) return;
    if (!class_exists('WooCommerce')) return;
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    if (!get_option('parilte_demo_done')) return;
    if (get_option('parilte_demo_tuned')) return;

    $sales_map = [
        'PAR-TEE-001'  => ['sale' => '249', 'sales' => 14],
        'PAR-JEAN-001' => ['sale' => '',    'sales' => 9],
        'PAR-KZK-001'  => ['sale' => '549', 'sales' => 11],
        'PAR-KBN-001'  => ['sale' => '',    'sales' => 6],
        'PAR-CNT-001'  => ['sale' => '399', 'sales' => 8],
    ];

    foreach ($sales_map as $sku => $data) {
        $pid = wc_get_product_id_by_sku($sku);
        if (!$pid) continue;
        $p = wc_get_product($pid);
        if (!$p) continue;
        if ($data['sale'] !== '') $p->set_sale_price($data['sale']);
        $p->save();
        update_post_meta($pid, 'total_sales', (int) $data['sales']);
    }

    $var_id = wc_get_product_id_by_sku('PAR-SWT-001');
    if ($var_id) {
        $vp = wc_get_product($var_id);
        if ($vp && $vp->is_type('variable')) {
            foreach ($vp->get_children() as $vid) {
                $v = wc_get_product($vid);
                if (!$v) continue;
                $v->set_sale_price('799');
                $v->save();
            }
            update_post_meta($var_id, 'total_sales', 10);
        }
    }

    update_option('parilte_demo_tuned', 1);
    delete_transient('parilte_placeholder_ids');
}
add_action('admin_init', 'parilte_cs_seed_demo_tuning', 32);
add_action('init', 'parilte_cs_seed_demo_tuning', 32);

function parilte_cs_cleanup_demo_sales() {
    if (!PARILTE_DEMO_CONTENT) return;
    if (!class_exists('WooCommerce')) return;
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    if (get_option('parilte_demo_sale_cleaned')) return;

    $q = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [[ 'key' => '_parilte_seed', 'value' => '1' ]],
    ]);
    foreach ($q->posts as $pid) {
        $p = wc_get_product($pid);
        if (!$p) continue;
        $p->set_sale_price('');
        $p->save();
    }

    update_option('parilte_demo_sale_cleaned', 1);
}
add_action('admin_init', 'parilte_cs_cleanup_demo_sales', 33);
add_action('init', 'parilte_cs_cleanup_demo_sales', 33);

function parilte_cs_cleanup_demo_posts() {
    if (PARILTE_DEMO_BLOG) return;
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    if (get_option('parilte_demo_posts_cleaned')) return;

    $slugs = ['kapsul-gardrop', 'sezon-renkleri', 'sehirde-rahat-stil'];
    foreach ($slugs as $slug) {
        $post = get_page_by_path($slug, OBJECT, 'post');
        if (!$post) continue;
        if (stripos((string) $post->post_content, 'ilham') !== false || stripos((string) $post->post_content, 'zamansız') !== false) {
            wp_trash_post($post->ID);
        }
    }

    update_option('parilte_demo_posts_cleaned', 1);
}
add_action('admin_init', 'parilte_cs_cleanup_demo_posts', 34);
add_action('init', 'parilte_cs_cleanup_demo_posts', 34);

function parilte_cs_seed_demo_images() {
    if (!PARILTE_DEMO_CONTENT || !PARILTE_DEMO_IMAGES) return;
    if (!class_exists('WooCommerce')) return;
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    if (!get_option('parilte_demo_done')) return;

    $image_map = [
        'PAR-TEE-001'  => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=1200&q=80',
        'PAR-JEAN-001' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=1200&q=80',
        'PAR-KZK-001'  => 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=1200&q=80',
        'PAR-KBN-001'  => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=1200&q=80',
        'PAR-CNT-001'  => 'https://images.unsplash.com/photo-1523292562811-8fa7962a78c8?auto=format&fit=crop&w=1200&q=80',
        'PAR-SWT-001'  => 'https://images.unsplash.com/photo-1516762689617-e1cffcef479d?auto=format&fit=crop&w=1200&q=80',
    ];

    $targets = [];
    foreach ($image_map as $sku => $url) {
        $pid = wc_get_product_id_by_sku($sku);
        if (!$pid) continue;
        if (get_post_thumbnail_id($pid)) continue;
        $targets[$pid] = $url;
    }

    if (!$targets) {
        update_option('parilte_demo_images_done', 1);
        return;
    }

    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    foreach ($targets as $pid => $url) {
        $att_id = media_sideload_image($url, (int) $pid, null, 'id');
        if (!is_wp_error($att_id) && $att_id) {
            set_post_thumbnail($pid, $att_id);
        }
    }

    $still_missing = false;
    foreach (array_keys($targets) as $pid) {
        if (!get_post_thumbnail_id($pid)) {
            $still_missing = true;
            break;
        }
    }
    if (!$still_missing) update_option('parilte_demo_images_done', 1);
}
add_action('admin_init', 'parilte_cs_seed_demo_images', 30);
add_action('init', 'parilte_cs_seed_demo_images', 30);

function parilte_cs_cleanup_placeholders_once() {
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    if (get_option('parilte_placeholder_purged')) return;

    $deleted = 0;
    $flag = '_parilte_placeholder';

    $q = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [[ 'key' => $flag, 'value' => '1' ]],
    ]);
    foreach ($q->posts as $pid) {
        wp_trash_post($pid);
        $deleted++;
    }

    $q2 = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        's'              => 'Placeholder',
    ]);
    foreach ($q2->posts as $pid) {
        $title = get_the_title($pid);
        if ($title && stripos($title, 'placeholder') !== false) {
            wp_trash_post($pid);
            $deleted++;
        }
    }

    update_option('parilte_placeholder_purged', $deleted ? $deleted : 1);
    delete_transient('parilte_placeholder_ids');
}
add_action('admin_init', 'parilte_cs_cleanup_placeholders_once', 35);
add_action('init', 'parilte_cs_cleanup_placeholders_once', 35);

/* ==========================================================
 * BOOTSTRAP (sayfalar, kategoriler, nitelikler, menu)
 * ========================================================== */
function parilte_cs_bootstrap(){
    if (!PARILTE_BOOTSTRAP_ON) return;
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    $done = (string) get_option('parilte_bootstrap_done', '');
    if ($done === (string) PARILTE_BOOTSTRAP_VERSION) return;

    $get_or_create_page = function($title, $slug) {
        if ($p = get_page_by_path($slug)) return $p->ID;
        if ($p = get_page_by_title($title)) return $p->ID;
        return wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug]);
    };
    $ensure_term = function($name, $slug, $taxonomy, $parent = 0) {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term) return (int)$term->term_id;
        $res = wp_insert_term($name, $taxonomy, ['slug'=>$slug,'parent'=>(int)$parent]);
        return is_wp_error($res) ? 0 : (int)$res['term_id'];
    };

    // 1) Pages
    $p = [];
    $p['home']     = $get_or_create_page('Anasayfa','anasayfa');
    $p['shop']     = $get_or_create_page('Mağaza','magaza');
    $p['cart']     = $get_or_create_page('Sepet','sepet');
    $p['checkout'] = $get_or_create_page('Ödeme','odeme');
    $p['account']  = $get_or_create_page('Hesabım','hesabim');
    $p['about']    = $get_or_create_page('Hakkımızda','hakkimizda');
    $p['returns']  = $get_or_create_page('İade & Değişim','iade-degisim');
    $p['shipping'] = $get_or_create_page('Teslimat & Kargo','teslimat-kargo');
    $p['privacy']  = $get_or_create_page('KVKK & Gizlilik','kvkk-gizlilik');
    $p['blog']     = $get_or_create_page('Blog','blog');
    $p['size']     = $get_or_create_page('Beden Rehberi','beden-rehberi');
    $p['sale']     = $get_or_create_page('İndirimler','indirimler');
    $p['new']      = $get_or_create_page('Yeni Gelenler','yeni-gelenler');

    $site_name = get_bloginfo('name');
    $contact_email = 'destek@parilte.com';
    $contact_email_display = 'destek@parilte.com';
    parilte_cs_set_page_content_if_empty($p['about'], '<h2>Hakkımızda</h2><p>'.$site_name.' olarak zamansız, rahat ve günlük kombinlenebilir parçalarla güçlü bir stil sunuyoruz. Sezon seçkilerimizi özenle hazırlıyor, müşteri deneyimini her adımda sade ve güvenilir kılmayı amaçlıyoruz.</p><p>Koleksiyonlarımız; kalite, konfor ve şıklık dengesini koruyacak şekilde planlanır.</p>');
    parilte_cs_set_page_content_if_empty($p['returns'], '<h2>İade & Değişim</h2><ul><li>Ürün tesliminden itibaren 14 gün içinde iade/değişim talebi oluşturabilirsiniz.</li><li>Ürünler; kullanılmamış, etiketleri üzerinde ve orijinal ambalajında olmalıdır.</li><li>Hijyenik ürünlerde iade koşulları değişkenlik gösterebilir.</li><li>İade onayı sonrasında ücret iadesi, ödeme yöntemine göre 3-10 iş günü içinde tamamlanır.</li></ul><p>Detaylı bilgi için bizimle iletişime geçebilirsiniz: <a href="mailto:'.$contact_email.'">'.$contact_email_display.'</a></p>');
    parilte_cs_set_page_content_if_empty($p['shipping'], '<h2>Teslimat & Kargo</h2><ul><li>Siparişler 1-3 iş günü içinde kargoya teslim edilir.</li><li>Türkiye içi sabit kargo ücreti 100 TL’dir.</li><li>1500 TL ve üzeri siparişlerde kargo ücretsizdir.</li><li>Kampanya dönemlerinde teslimat süreleri değişiklik gösterebilir.</li></ul>');
    parilte_cs_set_page_content_if_empty($p['privacy'], '<h2>KVKK & Gizlilik</h2><p>Kişisel verileriniz, 6698 sayılı KVKK kapsamında işlenir ve korunur. Sitemiz üzerinden paylaştığınız bilgiler; siparişlerinizi tamamlamak, hizmet kalitesini artırmak ve yasal yükümlülüklerimizi yerine getirmek amacıyla kullanılabilir.</p><h3>Toplanan Veriler</h3><ul><li>Ad, soyad, iletişim bilgileri</li><li>Teslimat ve fatura adresleri</li><li>Sipariş ve ödeme bilgileri (kart bilgileri saklanmaz)</li></ul><h3>Çerezler</h3><p>Sitemizde deneyimi iyileştirmek için çerezler kullanılabilir. Tarayıcı ayarlarınızdan çerez tercihlerinizi yönetebilirsiniz.</p><p>KVKK başvuruları için: <a href="mailto:'.$contact_email.'">'.$contact_email_display.'</a></p>');

    // 2) Reading
    update_option('show_on_front','page');
    update_option('page_on_front',(int)$p['home']);
    update_option('page_for_posts',(int)$p['blog']);

    // 3) Permalink
    update_option('permalink_structure','/%postname%/');

    // 4) Woo pages
    update_option('woocommerce_shop_page_id',(int)$p['shop']);
    update_option('woocommerce_cart_page_id',(int)$p['cart']);
    update_option('woocommerce_checkout_page_id',(int)$p['checkout']);
    update_option('woocommerce_myaccount_page_id',(int)$p['account']);
    update_option('woocommerce_enable_myaccount_registration','yes');
    update_option('woocommerce_enable_signup_and_login_from_checkout','yes');
    update_option('woocommerce_enable_checkout_login_reminder','yes');
    update_option('woocommerce_registration_generate_password','yes');
    update_option('woocommerce_registration_generate_username','yes');
    update_option('users_can_register', 1);
    update_option('woocommerce_email_from_name', get_bloginfo('name'));
    update_option('woocommerce_email_from_address', get_option('admin_email'));
    update_option('woocommerce_terms_page_id',(int)$p['returns']);
    if (!empty($p['privacy'])) update_option('wp_page_for_privacy_policy', (int)$p['privacy']);

    // 4.1) Woo sayfa icerikleri
    parilte_cs_set_page_content_if_empty($p['cart'], '[woocommerce_cart]');
    parilte_cs_set_page_content_if_empty($p['checkout'], '[woocommerce_checkout]');
    parilte_cs_set_page_content_if_empty($p['account'], '[woocommerce_my_account]');
    parilte_cs_set_page_content_if_empty($p['sale'], '[products on_sale="true" columns="4" paginate="true"]');
    parilte_cs_set_page_content_if_empty($p['new'], '[products orderby="date" order="DESC" columns="4" paginate="true"]');

    // 4.2) Woo temel ayarlar
    update_option('woocommerce_currency', 'TRY');
    update_option('woocommerce_default_country', 'TR');
    update_option('woocommerce_weight_unit', 'kg');
    update_option('woocommerce_dimension_unit', 'cm');
    update_option('woocommerce_allowed_countries', 'specific');
    update_option('woocommerce_specific_allowed_countries', ['TR']);
    update_option('woocommerce_ship_to_countries', 'specific');
    update_option('woocommerce_specific_ship_to_countries', ['TR']);
    update_option('woocommerce_enable_guest_checkout', 'yes');
    update_option('woocommerce_enable_myaccount_registration', 'yes');
    update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');
    update_option('woocommerce_enable_coupons', 'yes');
    update_option('woocommerce_catalog_columns', 3);
    update_option('woocommerce_catalog_rows', 4);
    update_option('parilte_free_threshold', PARILTE_CS_FREE_THRESHOLD);
    update_option('woocommerce_coming_soon', 'no');
    update_option('woocommerce_coming_soon_mode', 'no');
    update_option('woocommerce_store_pages_only', 'no');
    update_option('woocommerce_maintenance_mode', 'no');

    // 5) Product category base
    $wcpl = (array)get_option('woocommerce_permalinks',[]);
    $wcpl['category_base']    = 'kategori';
    $wcpl['product_cat_base'] = 'kategori';
    update_option('woocommerce_permalinks',$wcpl);

    // 6) Categories
    $cat = [];
    $cat['ust']      = $ensure_term('Üst Giyim','ust-giyim','product_cat');
    $cat['alt']      = $ensure_term('Alt Giyim','alt-giyim','product_cat');
    $cat['dis']      = $ensure_term('Dış Giyim','dis-giyim','product_cat');
    $cat['aksesuar'] = $ensure_term('Aksesuar','aksesuar','product_cat');

    foreach ([['Pantolon','pantolon'],['Şort','sort'],['Etek','etek'],['Jean','jean'],['Tayt','tayt'],['Eşofman','esofman'],['Tulum','tulum']] as $t)
        $ensure_term($t[0],$t[1],'product_cat',$cat['alt']);

    foreach ([['Gömlek','gomlek'],['Bluz','bluz'],['Tişört','tisort'],['Atlet','atlet'],['Crop Top','crop-top'],['Sweatshirt','sweatshirt'],['Triko','triko'],['Kazak','kazak'],['Bodysuit','bodysuit']] as $t)
        $ensure_term($t[0],$t[1],'product_cat',$cat['ust']);

    // 7) Attributes
    if (function_exists('wc_create_attribute')) {
        $aid = wc_attribute_taxonomy_id_by_name('beden');
        if (!$aid) $aid = wc_create_attribute(['name'=>'Beden','slug'=>'beden','type'=>'select','order_by'=>'menu_order','has_archives'=>true]);
        $rid = wc_attribute_taxonomy_id_by_name('renk');
        if (!$rid) $rid = wc_create_attribute(['name'=>'Renk','slug'=>'renk','type'=>'select','order_by'=>'menu_order','has_archives'=>true]);
        $lid = wc_attribute_taxonomy_id_by_name('boy');
        if (!$lid) $lid = wc_create_attribute(['name'=>'Boy','slug'=>'boy','type'=>'select','order_by'=>'menu_order','has_archives'=>true]);
        if (function_exists('register_taxonomy')) {
            register_taxonomy('pa_beden','product',['hierarchical'=>false,'show_ui'=>false]);
            register_taxonomy('pa_renk','product',['hierarchical'=>false,'show_ui'=>false]);
            register_taxonomy('pa_boy','product',['hierarchical'=>false,'show_ui'=>false]);
        }
        foreach (['34','36','38','40','42','44','46','48','50','XS','S','M','L','XL','2XL','3XL','4XL','5XL'] as $s) {
            if (!term_exists($s,'pa_beden')) wp_insert_term($s,'pa_beden',['slug'=>sanitize_title($s)]);
        }
        foreach (['Siyah','Beyaz','Gri','Antrasit','Lacivert','Mavi','Buz Mavi','Bordo','Kırmızı','Pembe','Pudra','Mor','Lila','Turuncu','Hardal','Sarı','Yeşil','Zümrüt','Haki','Bej','Camel','Vizon','Taba','Kahverengi','Acı Kahve','Krem','Ekru','Taş'] as $c) {
            if (!term_exists($c,'pa_renk')) wp_insert_term($c,'pa_renk',['slug'=>sanitize_title($c)]);
        }
        foreach (['Mini','Kısa','Midi','Maxi','Uzun'] as $b) {
            if (!term_exists($b,'pa_boy')) wp_insert_term($b,'pa_boy',['slug'=>sanitize_title($b)]);
        }
    }

    // 8) Menu “Ana Menü” + konum
    $menu_name = 'Ana Menü';
    $menu_obj  = wp_get_nav_menu_object($menu_name);
    $menu_id   = $menu_obj ? $menu_obj->term_id : wp_create_nav_menu($menu_name);
    $existing  = (array) wp_get_nav_menu_items($menu_id);
    $titles = [];
    $urls   = [];
    $by_url = [];
    foreach ($existing as $item) {
        $titles[strtolower($item->title)] = true;
        $u = rtrim($item->url,'/');
        $urls[$u] = true;
        $by_url[$u] = (int) $item->ID;
    }
    $add_item  = function($args) use ($menu_id, &$titles, &$urls){
        if (!empty($args['menu-item-url'])) {
            $u = rtrim($args['menu-item-url'], '/');
            if (isset($urls[$u])) return 0;
        }
        if (!empty($args['menu-item-title'])) {
            $t = strtolower($args['menu-item-title']);
            if (isset($titles[$t])) return 0;
        }
        $id = wp_update_nav_menu_item($menu_id,0,array_merge(['menu-item-status'=>'publish'],$args));
        if (!is_wp_error($id)) {
            if (!empty($args['menu-item-url'])) $urls[rtrim($args['menu-item-url'], '/')] = true;
            if (!empty($args['menu-item-title'])) $titles[strtolower($args['menu-item-title'])] = true;
        }
        return $id;
    };
    $add_item(['menu-item-title'=>'Anasayfa','menu-item-url'=>home_url('/'),'menu-item-type'=>'custom']);
    $add_item(['menu-item-title'=>'Mağaza','menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-object-id'=>$p['shop']]);

    $parent_ids = [];
    foreach ([['Üst Giyim','ust-giyim','ust'],['Alt Giyim','alt-giyim','alt'],['Aksesuar','aksesuar','aksesuar'],['Dış Giyim','dis-giyim','dis']] as $k) {
        $url = home_url('/kategori/'.$k[1].'/');
        $id = $add_item(['menu-item-title'=>$k[0],'menu-item-url'=>$url,'menu-item-type'=>'custom']);
        if (!$id && isset($by_url[rtrim($url,'/')])) $id = $by_url[rtrim($url,'/')];
        $parent_ids[$k[2]] = $id ? (int) $id : 0;
    }

    $children = [
        'alt' => [['Pantolon','pantolon'],['Şort','sort'],['Etek','etek'],['Jean','jean'],['Tayt','tayt'],['Eşofman','esofman'],['Tulum','tulum']],
        'ust' => [['Gömlek','gomlek'],['Bluz','bluz'],['Tişört','tisort'],['Atlet','atlet'],['Crop Top','crop-top'],['Sweatshirt','sweatshirt'],['Triko','triko'],['Kazak','kazak'],['Bodysuit','bodysuit']],
    ];
    foreach ($children as $parent_key => $items_list) {
        $pid = isset($parent_ids[$parent_key]) ? (int) $parent_ids[$parent_key] : 0;
        if (!$pid) continue;
        foreach ($items_list as $c) {
            $add_item([
                'menu-item-title' => $c[0],
                'menu-item-url' => home_url('/kategori/'.$c[1].'/'),
                'menu-item-type' => 'custom',
                'menu-item-parent-id' => $pid,
            ]);
        }
    }

    $add_item(['menu-item-title'=>'Sepet','menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-object-id'=>$p['cart']]);
    $add_item(['menu-item-title'=>'Hesabım','menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-object-id'=>$p['account']]);

    $loc = get_theme_mod('nav_menu_locations',[]);
    $reg = get_registered_nav_menus();
    $primary = array_keys(array_filter($reg,function($d,$s){return preg_match('/primary|header|main/i',$s.$d);},ARRAY_FILTER_USE_BOTH));
    $mobile  = array_keys(array_filter($reg,function($d,$s){return preg_match('/mobile/i',$s.$d);},ARRAY_FILTER_USE_BOTH));
    if (!empty($primary) && empty($loc[$primary[0]])) $loc[$primary[0]] = $menu_id;
    if (!empty($mobile)  && empty($loc[$mobile[0]]))  $loc[$mobile[0]]  = $menu_id;
    set_theme_mod('nav_menu_locations',$loc);

    // 8.1) Woo sidebar (Blocksy) — full-width shop (filters are drawer-based)
    set_theme_mod('woo_categories_has_sidebar', 'no');
    set_theme_mod('woo_categories_sidebar_position', 'no');

    // 9) Anasayfa icerigi
    if (PARILTE_AUTO_FRONT && !empty($p['home'])) {
        $home = get_post($p['home']);
        if ($home && trim((string)$home->post_content) === '') {
            wp_update_post(['ID'=>$p['home'], 'post_content'=>'[parilte_front]']);
        }
    }

    // 10) Woo yan panel widgetlari + kargo/odeme + demo icerik
    parilte_cs_setup_widgets();
    parilte_cs_setup_shipping();
    parilte_cs_setup_tax_rate_once();
    parilte_cs_setup_payments();
    parilte_cs_seed_demo_content($cat);

    if (function_exists('flush_rewrite_rules')) flush_rewrite_rules(false);

    update_option('parilte_bootstrap_done', (string) PARILTE_BOOTSTRAP_VERSION);
    add_action('admin_notices',function(){
        echo '<div class="notice notice-success"><p><strong>Parilté Bootstrap</strong> tamamlandı.</p></div>';
    });
}
add_action('init', 'parilte_cs_bootstrap', 20);
add_action('admin_init', 'parilte_cs_bootstrap');
register_activation_hook(__FILE__, 'parilte_cs_bootstrap');

// Coming soon modunu zorla kapat
add_filter('pre_option_woocommerce_coming_soon', function () { return 'no'; });
add_filter('pre_option_woocommerce_coming_soon_mode', function () { return 'no'; });
add_filter('pre_option_woocommerce_store_pages_only', function () { return 'no'; });
add_filter('woocommerce_coming_soon_exclude', '__return_true');

/* ==========================================================
 * HEADER / FRONT SHORTCODE
 * ========================================================== */
function parilte_cs_header_markup(){
    if (defined('PARILTE_CUSTOM_HEADER') && PARILTE_CUSTOM_HEADER) return '';
    if (!function_exists('wc_get_page_permalink')) return '';
    $account_url = esc_url( wc_get_page_permalink('myaccount') );
    $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/sepet/');
    $cart_count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
    $account_label = is_user_logged_in() ? 'Hesabım' : 'Giriş';
    ob_start(); ?>
    <div class="parilte-header-icons">
      <span class="parilte-header-spacer" aria-hidden="true"></span>
      <div class="parilte-header-search">
        <form role="search" method="get" class="parilte-search-form" action="<?php echo esc_url(home_url('/')); ?>">
          <label class="screen-reader-text" for="parilte-search-inline">Ara</label>
          <input type="search" id="parilte-search-inline" class="parilte-search-input" placeholder="Burada ara" value="<?php echo esc_attr(get_search_query()); ?>" name="s" />
          <button type="submit" class="parilte-search-button" aria-label="Ara">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 015.9 12.1l3.7 3.7-1.4 1.4-3.7-3.7A7.5 7.5 0 1110.5 3zm0 2a5.5 5.5 0 100 11 5.5 5.5 0 000-11z"/></svg>
          </button>
        </form>
      </div>
      <div class="parilte-header-tools">
        <button type="button" class="parilte-mobile-menu-toggle" aria-controls="parilte-mobile-drawer" aria-expanded="false"
          onclick="document.body.classList.add('parilte-mobile-open');document.getElementById('parilte-mobile-drawer')?.setAttribute('aria-hidden','false');this.setAttribute('aria-expanded','true');">
          <span class="parilte-mobile-menu-icon" aria-hidden="true"><span></span></span>
          <span class="parilte-mobile-menu-text">Menü</span>
        </button>
        <a class="parilte-account" href="<?php echo $account_url; ?>" aria-label="Hesabım">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
            <span class="parilte-label"><?php echo esc_html($account_label); ?></span>
        </a>
        <a class="parilte-cart" href="<?php echo esc_url($cart_url); ?>" aria-label="Sepet">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M7 6h13l-2 9H9L7 6z"/><circle cx="10" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/></svg>
            <span class="parilte-label">Sepet</span>
            <span class="parilte-cart-count"><?php echo (int) $cart_count; ?></span>
        </a>
      </div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('parilte_header', 'parilte_cs_header_markup');

function parilte_cs_custom_header_markup() {
    if (is_admin()) return '';
    $home_url = home_url('/');
    $site_name = get_bloginfo('name');
    $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/hesabim/');
    $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/sepet/');
    $cart_count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
    $account_label = is_user_logged_in() ? 'Hesabım' : 'Giriş';
    ob_start(); ?>
    <header class="parilte-custom-header" role="banner">
      <div class="parilte-custom-inner">
        <div class="parilte-custom-left">
          <a class="parilte-brand" href="<?php echo esc_url($home_url); ?>"><?php echo esc_html($site_name); ?></a>
        </div>
        <div class="parilte-custom-brand"></div>
        <div class="parilte-custom-right">
          <a class="parilte-account" href="<?php echo esc_url($account_url); ?>" aria-label="Hesabım">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
            <span class="parilte-label"><?php echo esc_html($account_label); ?></span>
          </a>
          <a class="parilte-cart" href="<?php echo esc_url($cart_url); ?>" aria-label="Sepet">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M7 6h13l-2 9H9L7 6z"/><circle cx="10" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/></svg>
            <span class="parilte-label">Sepet</span>
            <span class="parilte-cart-count"><?php echo (int) $cart_count; ?></span>
          </a>
        </div>
        <div class="parilte-custom-search">
          <button type="button" class="parilte-mobile-menu-toggle" aria-controls="parilte-mobile-drawer" aria-expanded="false"
            onclick="document.body.classList.add('parilte-mobile-open');document.getElementById('parilte-mobile-drawer')?.setAttribute('aria-hidden','false');this.setAttribute('aria-expanded','true');">
            <span class="parilte-mobile-menu-icon" aria-hidden="true"><span></span></span>
            <span class="parilte-label">Katalog</span>
          </button>
          <form role="search" method="get" class="parilte-search-form" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="screen-reader-text" for="parilte-search-custom">Ara</label>
            <input type="search" id="parilte-search-custom" class="parilte-search-input" placeholder="Ara" value="<?php echo esc_attr(get_search_query()); ?>" name="s" />
            <button type="submit" class="parilte-search-button" aria-label="Ara">
              <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 015.9 12.1l3.7 3.7-1.4 1.4-3.7-3.7A7.5 7.5 0 1110.5 3zm0 2a5.5 5.5 0 100 11 5.5 5.5 0 000-11z"/></svg>
            </button>
          </form>
        </div>
      </div>
    </header>
    <?php return ob_get_clean();
}

add_action('wp_body_open', function () {
    if (!PARILTE_CUSTOM_HEADER) return;
    echo parilte_cs_custom_header_markup();
}, 5);

function parilte_cs_mobile_drawer_markup() {
    if (is_admin()) return;
    $home_url = home_url('/');
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/magaza/');
    ?>
    <div id="parilte-mobile-drawer" class="parilte-mobile-drawer" aria-hidden="true">
      <div class="parilte-mobile-backdrop" role="presentation"
        onclick="document.body.classList.remove('parilte-mobile-open');document.getElementById('parilte-mobile-drawer')?.setAttribute('aria-hidden','true');document.querySelector('.parilte-mobile-menu-toggle')?.setAttribute('aria-expanded','false');"></div>
      <aside class="parilte-mobile-panel" role="dialog" aria-modal="true" aria-label="Mobil Menü">
        <div class="parilte-mobile-header">
          <strong>Menü</strong>
          <button type="button" class="parilte-mobile-close" aria-label="Kapat"
            onclick="document.body.classList.remove('parilte-mobile-open');document.getElementById('parilte-mobile-drawer')?.setAttribute('aria-hidden','true');document.querySelector('.parilte-mobile-menu-toggle')?.setAttribute('aria-expanded','false');">×</button>
        </div>
        <nav class="parilte-mobile-links">
          <a href="<?php echo esc_url($home_url); ?>">Anasayfa</a>
          <a href="<?php echo esc_url($shop_url); ?>">Mağaza</a>
        </nav>
        <div class="parilte-mobile-cats">
          <?php echo parilte_cs_category_tree_block(); ?>
        </div>
      </aside>
    </div>
    <script>
      (function(){
        function openMenu(){
          document.body.classList.add('parilte-mobile-open');
          var drawer = document.getElementById('parilte-mobile-drawer');
          if (drawer) drawer.setAttribute('aria-hidden','false');
          var btn = document.querySelector('.parilte-mobile-menu-toggle');
          if (btn) btn.setAttribute('aria-expanded','true');
          // Default open all categories in the drawer
          var toggles = drawer ? drawer.querySelectorAll('.parilte-cat-toggle') : [];
          toggles.forEach(function(t){
            t.setAttribute('aria-expanded','true');
            var wrap = t.closest('.parilte-cat-tree-item');
            if (!wrap) return;
            var children = wrap.querySelector('.parilte-cat-tree-children');
            if (children) children.style.display = 'block';
          });
        }
        function closeMenu(){
          document.body.classList.remove('parilte-mobile-open');
          var drawer = document.getElementById('parilte-mobile-drawer');
          if (drawer) drawer.setAttribute('aria-hidden','true');
          var btn = document.querySelector('.parilte-mobile-menu-toggle');
          if (btn) btn.setAttribute('aria-expanded','false');
        }
        document.addEventListener('click', function(e){
          if (e.target.closest('.parilte-mobile-menu-toggle')) { e.preventDefault(); openMenu(); }
          if (e.target.closest('.parilte-mobile-close') || e.target.closest('.parilte-mobile-backdrop')) { e.preventDefault(); closeMenu(); }
        });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMenu(); });
      })();
    </script>
    <?php
}
add_action('wp_footer', 'parilte_cs_mobile_drawer_markup', 25);

function parilte_cs_search_drawer_markup() {
    if (is_admin()) return;
    ?>
    <div id="parilte-search-drawer" class="parilte-search-drawer" aria-hidden="true">
      <div class="parilte-search-backdrop" role="presentation"></div>
      <div class="parilte-search-panel" role="dialog" aria-modal="true" aria-label="Arama">
        <div class="parilte-search-head">
          <strong>Ara</strong>
          <button type="button" class="parilte-search-close" aria-label="Kapat">×</button>
        </div>
        <form role="search" method="get" class="parilte-search-form" action="<?php echo esc_url(home_url('/')); ?>">
          <label class="screen-reader-text" for="parilte-search-field">Ara</label>
          <input type="search" id="parilte-search-field" class="parilte-search-input" placeholder="Burada ara" value="<?php echo esc_attr(get_search_query()); ?>" name="s" />
          <input type="hidden" name="post_type" value="product" />
          <button type="submit" class="parilte-search-submit">Ara</button>
        </form>
      </div>
    </div>
    <script>
      (function(){
        function openSearch(){
          document.body.classList.add('parilte-search-open');
          var drawer = document.getElementById('parilte-search-drawer');
          if (drawer) drawer.setAttribute('aria-hidden','false');
          var btn = document.querySelector('.parilte-search-toggle');
          if (btn) btn.setAttribute('aria-expanded','true');
          var field = document.getElementById('parilte-search-field');
          if (field) setTimeout(function(){ field.focus(); }, 30);
        }
        function closeSearch(){
          document.body.classList.remove('parilte-search-open');
          var drawer = document.getElementById('parilte-search-drawer');
          if (drawer) drawer.setAttribute('aria-hidden','true');
          var btn = document.querySelector('.parilte-search-toggle');
          if (btn) btn.setAttribute('aria-expanded','false');
        }
        document.addEventListener('click', function(e){
          if (e.target.closest('.parilte-search-toggle')) { e.preventDefault(); openSearch(); }
          if (e.target.closest('.parilte-search-close') || e.target.closest('.parilte-search-backdrop')) { e.preventDefault(); closeSearch(); }
        });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeSearch(); });
      })();
    </script>
    <?php
}
add_action('wp_footer', 'parilte_cs_search_drawer_markup', 28);

// Woo sidebar: hide default sidebar and render our own filter drawer
add_action('init', function () {
    remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
});

// Blocksy: force full-width shop layout (avoid empty sidebar column)
add_action('after_setup_theme', function () {
    set_theme_mod('woo_categories_has_sidebar', 'no');
    set_theme_mod('woo_categories_sidebar_position', 'no');
});

function parilte_cs_filter_toggle_button() {
    if (!PARILTE_FILTERS_ON) return;
    if (!is_shop() && !is_product_taxonomy() && !is_product_category() && !is_product_tag()) return;
    echo '<button type="button" class="parilte-filter-toggle" aria-controls="parilte-filter-drawer" aria-expanded="false">Filtrele</button>';
}
if (PARILTE_FILTERS_ON) {
    add_action('woocommerce_before_shop_loop', 'parilte_cs_filter_toggle_button', 5);
}

function parilte_cs_filter_drawer_markup() {
    if (!PARILTE_FILTERS_ON) return;
    if (!is_shop() && !is_product_taxonomy() && !is_product_category() && !is_product_tag()) return;
    ?>
    <div id="parilte-filter-drawer" class="parilte-filter-drawer" aria-hidden="true">
      <div class="parilte-filter-backdrop" role="presentation"></div>
      <aside class="parilte-filter-panel" role="dialog" aria-modal="true" aria-label="Filtreler">
        <div class="parilte-filter-head">
          <strong>Filtreler</strong>
          <button type="button" class="parilte-filter-close" aria-label="Kapat">×</button>
        </div>
        <div class="parilte-filter-body">
          <?php
            if (is_active_sidebar('sidebar-woocommerce')) {
                dynamic_sidebar('sidebar-woocommerce');
            } else {
                if (function_exists('get_product_search_form')) {
                    get_product_search_form();
                }
                if (function_exists('the_widget')) {
                    the_widget('WC_Widget_Product_Categories', ['title'=>'Kategoriler','hierarchical'=>1,'count'=>0,'dropdown'=>0]);
                    if (class_exists('WC_Widget_Price_Filter')) {
                        the_widget('WC_Widget_Price_Filter', ['title'=>'Fiyata Göre Filtrele']);
                    }
                    if (class_exists('WC_Widget_Layered_Nav')) {
                        if (taxonomy_exists('pa_beden')) {
                            the_widget('WC_Widget_Layered_Nav', ['title'=>'Beden','attribute'=>'beden','display_type'=>'list','query_type'=>'and']);
                        }
                        if (taxonomy_exists('pa_renk')) {
                            the_widget('WC_Widget_Layered_Nav', ['title'=>'Renk','attribute'=>'renk','display_type'=>'list','query_type'=>'and']);
                        }
                    }
                }
            }
          ?>
        </div>
      </aside>
    </div>
    <script>
      (function(){
        function openFilters(){
          document.body.classList.add('parilte-filters-open');
          var drawer = document.getElementById('parilte-filter-drawer');
          if (drawer) drawer.setAttribute('aria-hidden','false');
          var btn = document.querySelector('.parilte-filter-toggle');
          if (btn) btn.setAttribute('aria-expanded','true');
        }
        function closeFilters(){
          document.body.classList.remove('parilte-filters-open');
          var drawer = document.getElementById('parilte-filter-drawer');
          if (drawer) drawer.setAttribute('aria-hidden','true');
          var btn = document.querySelector('.parilte-filter-toggle');
          if (btn) btn.setAttribute('aria-expanded','false');
        }
        document.addEventListener('click', function(e){
          if (e.target.closest('.parilte-filter-toggle')) { e.preventDefault(); openFilters(); }
          if (e.target.closest('.parilte-filter-close') || e.target.closest('.parilte-filter-backdrop')) { e.preventDefault(); closeFilters(); }
        });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeFilters(); });
      })();
    </script>
    <?php
}
if (PARILTE_FILTERS_ON) {
    add_action('wp_footer', 'parilte_cs_filter_drawer_markup', 26);
}

function parilte_cs_shop_category_bar() {
    if (!is_shop() && !is_product_taxonomy() && !is_product_category() && !is_product_tag()) return;
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/magaza/');
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => false,
        'orderby'    => 'name',
    ]);
    if (empty($terms) || is_wp_error($terms)) return;
    $current = is_product_category() ? (int) get_queried_object_id() : 0;
    echo '<div class="parilte-shop-cats"><div class="parilte-container"><div class="parilte-shop-cats-row">';
    echo '<a class="parilte-shop-cat'.($current ? '' : ' is-active').'" href="'.esc_url($shop_url).'">Tümü</a>';
    foreach ($terms as $term) {
        if ($term->slug === 'tum-urunler') continue;
        $cls = $current === (int)$term->term_id ? ' is-active' : '';
        echo '<a class="parilte-shop-cat'.$cls.'" href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).'</a>';
    }
    echo '</div></div></div>';
}
add_action('woocommerce_before_shop_loop', 'parilte_cs_shop_category_bar', 2);

function parilte_cs_header_placed($set = null){
    static $placed = false;
    if ($set !== null) $placed = (bool) $set;
    return $placed;
}

function parilte_cs_target_menu_id(){
    $menu = wp_get_nav_menu_object('Ana Menü');
    return $menu ? (int) $menu->term_id : 0;
}

function parilte_cs_is_target_menu($args){
    $location = isset($args->theme_location) ? (string) $args->theme_location : '';
    if ($location && !preg_match('/mobile/i', $location) && preg_match('/primary|header|main/i', $location)) return true;

    if (!empty($args->blocksy_mega_menu) || !empty($args->blocksy_advanced_item)) return true;

    $target_id = parilte_cs_target_menu_id();
    if (!$target_id) return false;

    if (!empty($args->menu)) {
        if (is_object($args->menu) && isset($args->menu->term_id)) {
            return (int) $args->menu->term_id === $target_id;
        }
        if (is_numeric($args->menu)) {
            return (int) $args->menu === $target_id;
        }
        if (is_string($args->menu)) {
            $obj = wp_get_nav_menu_object($args->menu);
            if ($obj && (int) $obj->term_id === $target_id) return true;
        }
    }

    if (!empty($args->menu_id) && is_numeric($args->menu_id)) {
        return (int) $args->menu_id === $target_id;
    }

    return false;
}

add_filter('wp_nav_menu_items', function ($items, $args) {
    if (PARILTE_CUSTOM_HEADER) return $items;
    if (!PARILTE_AUTO_HEADER) return $items;
    if (!parilte_cs_is_target_menu($args)) return $items;
    $tools = '<li class="menu-item parilte-menu-tools">' . parilte_cs_header_markup() . '</li>';
    parilte_cs_header_placed(true);
    return $items . $tools;
}, 20, 2);

add_filter('wp_nav_menu_objects', function ($items, $args) {
    if (PARILTE_CUSTOM_HEADER) return $items;
    if (!parilte_cs_is_target_menu($args)) return $items;
    $cart_id = (int) get_option('woocommerce_cart_page_id');
    $account_id = (int) get_option('woocommerce_myaccount_page_id');
    $cart_url = function_exists('wc_get_cart_url') ? rtrim(wc_get_cart_url(), '/') : '';
    $account_url = function_exists('wc_get_page_permalink') ? rtrim(wc_get_page_permalink('myaccount'), '/') : '';

    $remove_titles = ['login','giris','giriş','hesap','hesabim','hesabım','sepet','cart','search','arama','kategori','kategoriler'];
    $filtered = [];
    foreach ($items as $item) {
        $title = function_exists('mb_strtolower') ? mb_strtolower($item->title, 'UTF-8') : strtolower($item->title);
        $url = rtrim((string) $item->url, '/');
        $obj_id = (int) $item->object_id;
        if ($obj_id && ($obj_id === $cart_id || $obj_id === $account_id)) continue;
        if ($url && ($url === $cart_url || $url === $account_url)) continue;
        if ($title && in_array($title, $remove_titles, true)) continue;
        if (!empty($item->menu_item_parent) && strpos($url, '/kategori/') !== false) continue;
        $filtered[] = $item;
    }
    return $filtered;
}, 10, 2);

add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    if (!function_exists('WC') || !WC()->cart) return $fragments;
    $count = (int) WC()->cart->get_cart_contents_count();
    $fragments['span.parilte-cart-count'] = '<span class="parilte-cart-count">'. $count .'</span>';
    return $fragments;
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('jquery');
    wp_add_inline_script('jquery', "jQuery(function($){
      $(document).on('click', '.parilte-cat-tree .parilte-cat-toggle', function(e){
        e.preventDefault();
        var \$btn = $(this);
        var \$wrap = \$btn.closest('.parilte-cat-tree-item');
        var \$children = \$wrap.find('> .parilte-cat-tree-children');
        if (!\$children.length) return;
        var isOpen = \$btn.attr('aria-expanded') === 'true';
        \$btn.attr('aria-expanded', isOpen ? 'false' : 'true');
        \$children.toggle(!isOpen);
      });
    });");
}, 23);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('jquery');
    wp_add_inline_script('jquery', "jQuery(function($){
      $('.ct-header').find('a').filter(function(){
        var t = $.trim($(this).text()).toLowerCase();
        return t === 'kategoriler' || t === 'kategori';
      }).closest('li').remove();
    });");
}, 24);

function parilte_cs_is_placeholder_product($product_id) {
    $title = get_the_title($product_id);
    if ($title && stripos($title, 'placeholder') !== false) return true;
    $flag = get_post_meta($product_id, '_parilte_placeholder', true);
    return ($flag === '1');
}

function parilte_cs_get_product_ids($args, $exclude_ids = []) {
    if (!class_exists('WC_Product')) return [];
    if (function_exists('parilte_cs_get_placeholder_ids')) {
        $exclude_ids = array_merge($exclude_ids, parilte_cs_get_placeholder_ids());
    }
    $meta_query = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
    $meta_query[] = [
        'key'     => '_thumbnail_id',
        'compare' => 'EXISTS',
    ];
    $defaults = [
        'status'  => 'publish',
        'limit'   => 12,
        'return'  => 'ids',
        'orderby' => 'date',
        'order'   => 'DESC',
    ];
    if (!empty($exclude_ids)) {
        $args['exclude'] = array_values(array_unique(array_filter(array_map('intval', (array) $exclude_ids))));
    }
    $args['meta_query'] = $meta_query;
    $query = array_merge($defaults, $args);
    $ids = wc_get_products($query);
    $ids = array_values(array_filter(array_map('intval', (array) $ids)));
    $ids = array_values(array_filter($ids, function($id){
        return !parilte_cs_is_placeholder_product($id);
    }));
    return $ids;
}

function parilte_cs_render_products($ids) {
    if (empty($ids)) return;
    echo '<ul class="products columns-4">';
    global $post;
    foreach ($ids as $pid) {
        $post = get_post($pid);
        if (!$post) continue;
        setup_postdata($post);
        if (function_exists('wc_setup_product_data')) {
            wc_setup_product_data($post);
        }
        wc_get_template_part('content', 'product');
    }
    wp_reset_postdata();
    if (function_exists('wc_reset_product_data')) {
        wc_reset_product_data();
    }
    echo '</ul>';
}

function parilte_cs_asset_url($file) {
    $file = ltrim($file, '/');
    $path = plugin_dir_path(__FILE__) . 'assets/' . $file;
    $url = plugins_url('assets/' . $file, __FILE__);
    if (file_exists($path)) {
        $url = add_query_arg('v', (string) filemtime($path), $url);
    }
    return $url;
}

function parilte_cs_front_markup(){
    ob_start(); ?>
    <main id="primary" class="site-main parilte-front">
      <?php
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/magaza/');
        $assets = [
          'h1' => parilte_cs_asset_url('home-07.jpg'),
          'h2' => parilte_cs_asset_url('home-02.png'),
          'h3' => parilte_cs_asset_url('home-03.png'),
          'h4' => parilte_cs_asset_url('home-04.png'),
          'h5' => parilte_cs_asset_url('home-05.png'),
          'h6' => parilte_cs_asset_url('home-06.png'),
          'h7' => parilte_cs_asset_url('home-01.png'),
        ];
        $sale_page = get_page_by_path('indirimler');
        $new_page  = get_page_by_path('yeni-gelenler');
        $best_page = get_page_by_path('en-cok-satanlar');
        $sale_url = ($sale_page && !is_wp_error($sale_page)) ? get_permalink($sale_page) : add_query_arg('parilte_sale', '1', $shop_url);
        $new_url  = ($new_page && !is_wp_error($new_page)) ? get_permalink($new_page) : add_query_arg('parilte_new', '1', $shop_url);
        $best_url = ($best_page && !is_wp_error($best_page)) ? get_permalink($best_page) : add_query_arg('parilte_best', '1', $shop_url);
        $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/hesabim/');
      ?>
      <section class="parilte-home-hero parilte-bleed">
        <img class="parilte-home-img" src="<?php echo esc_url($assets['h1']); ?>" alt="Parilte" loading="eager" decoding="async" />
        <div class="parilte-home-hero-cta">
          <a class="parilte-home-cta-btn" href="<?php echo esc_url($shop_url); ?>">Mağaza</a>
        </div>
      </section>

      <section class="parilte-home-join parilte-bleed">
        <div class="parilte-home-join-inner">
          <div class="parilte-home-join-copy">
            <small>Üye Ol</small>
            <h3>Yeni koleksiyonlardan ilk sen haberdar ol</h3>
            <p>Favorilerini kaydetmek ve fırsatları kaçırmamak için hesabını oluştur.</p>
          </div>
          <div class="parilte-home-join-action">
            <a class="parilte-home-cta-btn" href="<?php echo esc_url($account_url); ?>">Oturum Aç / Hesap Oluştur</a>
          </div>
        </div>
      </section>

      <section class="parilte-home-banner parilte-bleed">
        <img class="parilte-home-img" src="<?php echo esc_url($assets['h6']); ?>" alt="" loading="lazy" decoding="async" />
        <div class="parilte-home-banner-content">
          <small>İndirimler</small>
          <h2>Seçili ürünlerde fırsatlar</h2>
          <p>Sezona özel indirimleri keşfet, favorilerini kaçırma.</p>
          <a class="parilte-home-cta-btn" href="<?php echo esc_url($sale_url); ?>">İndirimlere Git</a>
        </div>
      </section>

      <section class="parilte-home-hot parilte-bleed">
        <div class="parilte-home-hot-inner">
          <small>En Çok Satanlar</small>
          <h3>Bu hafta öne çıkan parçalar</h3>
          <p>En çok beğenilen ürünlerimizi senin için derledik.</p>
          <a class="parilte-home-cta-btn parilte-home-cta-btn--hot" href="<?php echo esc_url($best_url); ?>">Keşfet</a>
        </div>
      </section>

      <section class="parilte-home-cats parilte-bleed">
        <div class="parilte-home-cats-grid">
          <?php
            $cats = [
              ['slug'=>'dis-giyim','label'=>'Dış Giyim','img'=>$assets['h2'],'pos'=>'50% 20%','class'=>''],
              ['slug'=>'ust-giyim','label'=>'Üst Giyim','img'=>$assets['h4'],'pos'=>'50% 30%','class'=>''],
              ['slug'=>'alt-giyim','label'=>'Alt Giyim','img'=>$assets['h3'],'pos'=>'50% 75%','class'=>''],
              ['slug'=>'aksesuar','label'=>'Aksesuar','img'=>$assets['h5'],'pos'=>'50% 45%','class'=>' is-accessory'],
            ];
            foreach ($cats as $card):
              $term = get_term_by('slug', $card['slug'], 'product_cat');
              $link = ($term && !is_wp_error($term)) ? get_term_link($term) : $shop_url;
          ?>
            <a class="parilte-home-cat<?php echo esc_attr($card['class']); ?>" href="<?php echo esc_url($link); ?>">
              <img class="parilte-home-img" src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['label']); ?>" loading="lazy" decoding="async" style="object-position: <?php echo esc_attr($card['pos']); ?>;" />
              <span class="parilte-home-cat-label"><?php echo esc_html($card['label']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="parilte-home-contact parilte-bleed">
        <div class="parilte-home-contact-inner">
          <small>Bize Ulaşın</small>
          <h3>Soru ve destek için yaz</h3>
          <p>İade, değişim ve genel sorular için bize yazabilirsin.</p>
          <?php
            $contact_status = isset($_GET['parilte_contact']) ? sanitize_text_field(wp_unslash($_GET['parilte_contact'])) : '';
            $contact_open = in_array($contact_status, ['success','error'], true);
            if ($contact_status === 'success') {
              echo '<div class="parilte-contact-note success">Mesajın bize ulaştı. En kısa sürede dönüş yapacağız.</div>';
            } elseif ($contact_status === 'error') {
              echo '<div class="parilte-contact-note error">Mesaj gönderilemedi. Lütfen tekrar dene.</div>';
            }
          ?>
          <div class="parilte-contact-actions">
            <button class="parilte-home-cta-btn parilte-contact-toggle" type="button" data-target="parilte-contact-form">Bize Ulaşın</button>
          </div>
          <form id="parilte-contact-form" class="parilte-contact-form<?php echo $contact_open ? ' is-open' : ''; ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="parilte_contact" />
            <?php wp_nonce_field('parilte_contact', 'parilte_contact_nonce'); ?>
            <div class="parilte-contact-row">
              <label>
                <span>Ad Soyad</span>
                <input type="text" name="parilte_name" required />
              </label>
              <label>
                <span>E-posta</span>
                <input type="email" name="parilte_email" required />
              </label>
            </div>
            <label>
              <span>Konu</span>
              <input type="text" name="parilte_subject" required />
            </label>
            <label>
              <span>Mesaj</span>
              <textarea name="parilte_message" rows="5" required></textarea>
            </label>
            <button class="parilte-home-cta-btn" type="submit">Gönder</button>
          </form>
        </div>
      </section>

      <section class="parilte-home-banner parilte-bleed">
        <img class="parilte-home-img" src="<?php echo esc_url($assets['h7']); ?>" alt="" loading="lazy" decoding="async" />
        <div class="parilte-home-banner-content">
          <small>Yeni Gelenler</small>
          <h2>Son eklenen seçkiler</h2>
          <p>Yeni sezon parçalarıyla görünümünü yenile.</p>
          <a class="parilte-home-cta-btn parilte-home-cta-btn--sm" href="<?php echo esc_url($new_url); ?>">Yeni Gelenler</a>
        </div>
      </section>

    </main>
    <?php return ob_get_clean();
}
add_shortcode('parilte_front', 'parilte_cs_front_markup');

/* ==========================================================
 * Ortak CSS taşıyıcı
 * ========================================================== */
add_action('wp_enqueue_scripts', function () {
    wp_register_style('parilte-checkout-suite', false);
    wp_enqueue_style('parilte-checkout-suite');
}, 20);

function parilte_cs_disable_placeholder_plugin() {
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    if (get_option('parilte_placeholder_plugin_off')) return;
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (function_exists('is_plugin_active') && is_plugin_active('parilte-placeholder/parilte-placeholder.php')) {
        deactivate_plugins('parilte-placeholder/parilte-placeholder.php', true);
    }
    update_option('parilte_placeholder_plugin_off', 1);
}
add_action('admin_init', 'parilte_cs_disable_placeholder_plugin', 15);
add_action('init', 'parilte_cs_disable_placeholder_plugin', 15);

function parilte_cs_get_placeholder_ids() {
    $cached = get_transient('parilte_placeholder_ids');
    if ($cached !== false) return (array) $cached;

    $ids = [];
    $q1 = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 300,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [[ 'key' => '_parilte_placeholder', 'value' => '1' ]],
    ]);
    if (!empty($q1->posts)) $ids = array_merge($ids, $q1->posts);

    $q2 = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 300,
        'post_status'    => 'any',
        'fields'         => 'ids',
        's'              => 'Placeholder',
    ]);
    if (!empty($q2->posts)) $ids = array_merge($ids, $q2->posts);

    $ids = array_values(array_unique(array_map('intval', $ids)));
    set_transient('parilte_placeholder_ids', $ids, DAY_IN_SECONDS);
    return $ids;
}

function parilte_cs_get_all_products_term_id() {
    global $parilte_cs_skip_term_filter;
    static $cached = null;
    if ($cached !== null) return $cached;
    $parilte_cs_skip_term_filter = true;
    $term = get_term_by('slug', 'tum-urunler', 'product_cat');
    if (!$term || is_wp_error($term)) {
        $term = get_term_by('name', 'Tüm Ürünler', 'product_cat');
    }
    $parilte_cs_skip_term_filter = false;
    $cached = ($term && !is_wp_error($term)) ? (int) $term->term_id : 0;
    return $cached;
}

function parilte_cs_exclude_all_products_term($args) {
    if (is_admin()) return $args;
    $tid = parilte_cs_get_all_products_term_id();
    if (!$tid) return $args;
    $exclude = isset($args['exclude']) ? (array) $args['exclude'] : [];
    $exclude[] = $tid;
    $args['exclude'] = array_values(array_unique(array_filter(array_map('intval', $exclude))));
    return $args;
}

add_filter('get_terms_args', function ($args, $taxonomies) {
    global $parilte_cs_skip_term_filter;
    if (!empty($parilte_cs_skip_term_filter)) return $args;
    if (is_admin()) return $args;
    $taxonomies = (array) $taxonomies;
    if (!in_array('product_cat', $taxonomies, true)) return $args;
    $args = parilte_cs_exclude_all_products_term($args);
    $args['hide_empty'] = false;
    return $args;
}, 10, 2);

add_filter('woocommerce_product_categories_widget_args', function ($args) {
    $args = parilte_cs_exclude_all_products_term($args);
    $args['hierarchical'] = true;
    $args['show_children_only'] = false;
    $args['show_count'] = false;
    $args['hide_empty'] = false;
    return $args;
});

add_filter('woocommerce_product_categories_args', function ($args) {
    return parilte_cs_exclude_all_products_term($args);
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'parilte_sale';
    $vars[] = 'parilte_new';
    $vars[] = 'parilte_best';
    return $vars;
});

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) return;
    if (!function_exists('is_shop')) return;

    $is_shop_archive = is_shop() || is_product_category() || is_product_tag();
    if (!$is_shop_archive) return;

    $sale_flag = isset($_GET['parilte_sale']) && $_GET['parilte_sale'] === '1';
    $new_flag  = isset($_GET['parilte_new']) && $_GET['parilte_new'] === '1';
    $best_flag = isset($_GET['parilte_best']) && $_GET['parilte_best'] === '1';

    if ($sale_flag && function_exists('wc_get_product_ids_on_sale')) {
        $sale_ids = wc_get_product_ids_on_sale();
        $sale_ids = !empty($sale_ids) ? $sale_ids : [0];
        $query->set('post__in', $sale_ids);
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
    }

    if ($new_flag) {
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
        $query->set('date_query', [
            [
                'after'     => '7 days ago',
                'inclusive' => true,
            ],
        ]);
    }

    if ($best_flag) {
        $query->set('orderby', 'meta_value_num');
        $query->set('meta_key', 'total_sales');
        $query->set('order', 'DESC');
    }
}, 11);

function parilte_cs_category_tree_markup() {
    if (!function_exists('get_terms')) return '';
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
    ]);
    if (is_wp_error($terms) || empty($terms)) return '';

    $by_parent = [];
    foreach ($terms as $term) {
        $by_parent[(int) $term->parent][] = $term;
    }

    $render = function($parent_id) use (&$render, $by_parent) {
        if (empty($by_parent[$parent_id])) return '';
        $out = '<ul class="parilte-cat-tree-list">';
        foreach ($by_parent[$parent_id] as $term) {
            $children_html = $render((int) $term->term_id);
            $has_children = !empty($children_html);
            $out .= '<li class="parilte-cat-tree-item'.($has_children ? ' has-children' : '').'">';
            $out .= '<a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).'</a>';
            if ($has_children) {
                $out .= '<button type="button" class="parilte-cat-toggle" aria-expanded="false" aria-label="Alt kategorileri aç/kapat"></button>';
                $out .= '<div class="parilte-cat-tree-children" style="display:none">'.$children_html.'</div>';
            }
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    };

    return $render(0);
}

function parilte_cs_category_tree_block() {
    $tree = parilte_cs_category_tree_markup();
    if (!$tree) return '';
    return '<div class="parilte-cat-tree"><div class="parilte-cat-tree-head"><span>Kategoriler</span></div>'.$tree.'</div>';
}

add_action('wp_footer', function () {
    if (!is_shop() && !is_product_taxonomy() && !is_product_category() && !is_product_tag() && !is_front_page()) return;
    $block = parilte_cs_category_tree_block();
    if (!$block) return;
    echo '<div id="parilte-cat-tree-source" style="display:none">'.$block.'</div>';
}, 20);

function parilte_cs_fix_category_hierarchy_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_cat_hierarchy_fixed')) return;
    $map = [
        'Aksesuar' => ['Anahtarlık','Atkı','Bere','Eldiven','Fular','Kemer','Şal','Şapka','Şemsiye'],
        'Alt Giyim' => ['Eşofman','Etek','Jean','Pantolon','Şort','Tayt','Tulum'],
        'Ayakkabı' => ['Babet','Bot','Çizme','Sandalet','Terlik','Topuklu Ayakkabı'],
        'Çanta' => ['Günlük','Şık','Spor'],
        'Dış Giyim' => ['Kaban','Mont','Trençkot'],
        'Elbise' => ['Düğün','Günlük Elbise','Mezuniyet','Şık Elbise'],
        'Takı' => ['Bel Zinciri','Bileklik','Bilezik','Kolye','Küpe'],
        'Üst Giyim' => ['Atlet','Badi','Bluz','Bodysuit','Crop','Gömlek','Kazak','Sweatshirt','Tişört','Triko'],
        'Yeni Sezon' => ['Yeni Ürünler'],
    ];

    foreach ($map as $parent_name => $children) {
        $parent = get_term_by('name', $parent_name, 'product_cat');
        if (!$parent || is_wp_error($parent)) continue;
        foreach ($children as $child_name) {
            $child = get_term_by('name', $child_name, 'product_cat');
            if (!$child || is_wp_error($child)) continue;
            if ((int) $child->parent !== (int) $parent->term_id) {
                wp_update_term($child->term_id, 'product_cat', ['parent' => (int) $parent->term_id]);
            }
        }
    }
    update_option('parilte_cat_hierarchy_fixed', 1);
}
add_action('admin_init', 'parilte_cs_fix_category_hierarchy_once', 30);

function parilte_cs_ensure_season_terms_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_season_terms_v1')) return;
    $parent = get_term_by('slug', 'genel', 'product_cat');
    if (!$parent || is_wp_error($parent)) {
        update_option('parilte_season_terms_v1', 1);
        return;
    }
    $seasons = [
        'ilkbahar-yaz' => 'İlkbahar Yaz',
        'sonbahar-kis' => 'Sonbahar Kış',
    ];
    foreach ($seasons as $slug => $name) {
        $term = get_term_by('slug', $slug, 'product_cat');
        if (!$term || is_wp_error($term)) {
            $term = wp_insert_term($name, 'product_cat', [
                'slug' => $slug,
                'parent' => (int) $parent->term_id,
            ]);
            if (is_wp_error($term)) continue;
            $term_id = is_array($term) ? (int) $term['term_id'] : 0;
        } else {
            $term_id = (int) $term->term_id;
            if ((int) $term->parent !== (int) $parent->term_id) {
                wp_update_term($term_id, 'product_cat', ['parent' => (int) $parent->term_id]);
            }
        }
    }
    update_option('parilte_season_terms_v1', 1);
}
add_action('admin_init', 'parilte_cs_ensure_season_terms_once', 32);
add_action('init', 'parilte_cs_ensure_season_terms_once', 32);

function parilte_cs_guess_primary_category_id($product) {
    if (!$product instanceof WC_Product) return 0;
    $name = $product->get_name();
    $hay = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $map = [
        'taki' => ['takı','taki','kolye','küpe','kupe','bileklik','bilezik','bel zinciri'],
        'canta' => ['çanta','canta','bag'],
        'ayakkabi' => ['ayakkabı','ayakkabi','bot','çizme','cizme','sandalet','terlik','topuklu'],
        'dis-giyim' => ['kaban','mont','trenç','trench','ceket','palt','parka','kürk','kurk'],
        'elbise' => ['elbise','abiye','dress'],
        'alt-giyim' => ['pantolon','jean','etek','şort','sort','tayt','eşofman','esofman','tulum'],
        'ust-giyim' => ['bluz','gömlek','gomlek','tişört','tisort','t-shirt','tshirt','sweatshirt','kazak','triko','bodysuit','crop','atlet','badi','yelek'],
        'aksesuar' => ['aksesuar','kemer','şal','sal','atkı','atki','bere','eldiven','fular','şapka','sapka','şemsiye','semsiye','anahtarlik','anahtarlık'],
    ];
    foreach ($map as $slug => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($hay, $kw) !== false) {
                $term = get_term_by('slug', $slug, 'product_cat');
                if (!$term || is_wp_error($term)) $term = get_term_by('name', $slug, 'product_cat');
                if ($term && !is_wp_error($term)) return (int) $term->term_id;
            }
        }
    }
    return 0;
}

function parilte_cs_cleanup_categories_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_cat_cleanup_done_v3')) return;

    $fallback_parent = get_term_by('slug', 'genel', 'product_cat');
    $fallback_alt = get_term_by('slug', 'yeni-sezon', 'product_cat');
    $fallback_id = 0;
    if ($fallback_alt && !is_wp_error($fallback_alt)) $fallback_id = (int) $fallback_alt->term_id;
    if (!$fallback_id && $fallback_parent && !is_wp_error($fallback_parent)) $fallback_id = (int) $fallback_parent->term_id;

    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);
    $season_terms = [];
    foreach ((array) $terms as $term) {
        if (!$term || is_wp_error($term)) continue;
        $slug = strtolower($term->slug);
        $name = function_exists('mb_strtolower') ? mb_strtolower($term->name, 'UTF-8') : strtolower($term->name);
        $is_season = (strpos($slug, 'ilkbahar') !== false) || (strpos($slug, 'sonbahar') !== false)
            || (strpos($name, 'ilkbahar') !== false) || (strpos($name, 'sonbahar') !== false);
        if (in_array($slug, ['ilkbahar-yaz','sonbahar-kis'], true)) continue;
        if (!$is_season) continue;
        if ($fallback_parent && !is_wp_error($fallback_parent)) {
            if ((int) $term->parent !== (int) $fallback_parent->term_id && strpos($name, 'sezon') === false) {
                continue;
            }
        }
        $season_terms[] = $term;
    }

    foreach ($season_terms as $term) {
        $posts = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [[ 'taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>$term->term_id ]]
        ]);
        foreach ((array) $posts as $pid) {
            $current = wp_get_object_terms($pid, 'product_cat', ['fields' => 'ids']);
            $remaining = array_values(array_diff((array) $current, [(int) $term->term_id]));
            if (!$remaining) {
                $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                $guess = $product ? parilte_cs_guess_primary_category_id($product) : 0;
                if ($guess) $remaining = [$guess];
                elseif ($fallback_id) $remaining = [$fallback_id];
            }
            if ($remaining) {
                wp_set_object_terms($pid, array_values(array_unique($remaining)), 'product_cat', false);
            }
        }
        wp_delete_term($term->term_id, 'product_cat');
    }

    // Split Ceket & Yelek -> Ceket (Dış Giyim) + Yelek (Üst Giyim)
    $parent_dis = get_term_by('slug', 'dis-giyim', 'product_cat');
    $parent_ust = get_term_by('slug', 'ust-giyim', 'product_cat');
    if ($parent_dis && $parent_ust && !is_wp_error($parent_dis) && !is_wp_error($parent_ust)) {
        $ceket_dis = get_term_by('slug', 'ceket', 'product_cat');
        if (!$ceket_dis || is_wp_error($ceket_dis)) {
            $ceket_dis = wp_insert_term('Ceket', 'product_cat', ['slug' => 'ceket', 'parent' => (int) $parent_dis->term_id]);
        } else {
            wp_update_term($ceket_dis->term_id, 'product_cat', ['parent' => (int) $parent_dis->term_id]);
        }
        $ceket_dis_id = is_array($ceket_dis) ? (int) $ceket_dis['term_id'] : (is_object($ceket_dis) ? (int) $ceket_dis->term_id : 0);

        $ceket_ust = get_term_by('slug', 'ceket-ust', 'product_cat');
        if (!$ceket_ust || is_wp_error($ceket_ust)) {
            $ceket_ust = wp_insert_term('Ceket', 'product_cat', ['slug' => 'ceket-ust', 'parent' => (int) $parent_ust->term_id]);
        } else {
            wp_update_term($ceket_ust->term_id, 'product_cat', ['parent' => (int) $parent_ust->term_id]);
        }
        $ceket_ust_id = is_array($ceket_ust) ? (int) $ceket_ust['term_id'] : (is_object($ceket_ust) ? (int) $ceket_ust->term_id : 0);

        $yelek = get_term_by('slug', 'yelek', 'product_cat');
        if (!$yelek || is_wp_error($yelek)) {
            $yelek = wp_insert_term('Yelek', 'product_cat', ['slug' => 'yelek', 'parent' => (int) $parent_ust->term_id]);
        } else {
            wp_update_term($yelek->term_id, 'product_cat', ['parent' => (int) $parent_ust->term_id]);
        }
        $yelek_id = is_array($yelek) ? (int) $yelek['term_id'] : (is_object($yelek) ? (int) $yelek->term_id : 0);

        $old = get_term_by('slug', 'ceket-yelek', 'product_cat');
        if (!$old || is_wp_error($old)) {
            $old = get_term_by('slug', 'ceketyelek', 'product_cat');
        }
        if ($old && !is_wp_error($old)) {
            $posts = get_posts([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [[ 'taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>$old->term_id ]]
            ]);
            foreach ((array) $posts as $pid) {
                $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                $title = $product ? $product->get_name() : '';
                $hay = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
                $terms = wp_get_object_terms($pid, 'product_cat', ['fields' => 'ids']);
                $terms = array_values(array_diff((array) $terms, [(int) $old->term_id]));
                if (strpos($hay, 'yelek') !== false && $yelek_id) {
                    $terms[] = $yelek_id;
                } else {
                    if ($ceket_dis_id) $terms[] = $ceket_dis_id;
                    if ($ceket_ust_id) $terms[] = $ceket_ust_id;
                }
                $terms = array_values(array_unique(array_filter($terms)));
                if ($terms) wp_set_object_terms($pid, $terms, 'product_cat', false);
            }
            wp_delete_term($old->term_id, 'product_cat');
        }
    }

    update_option('parilte_cat_cleanup_done_v3', 1);
}
add_action('admin_init', 'parilte_cs_cleanup_categories_once', 31);

function parilte_cs_merge_tisort_terms_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_tisort_merge_v1')) return;

    $parent = get_term_by('slug', 'ust-giyim', 'product_cat');
    $parent_id = ($parent && !is_wp_error($parent)) ? (int) $parent->term_id : 0;

    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    $normalize = function ($text) {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $map = ['ş'=>'s','ı'=>'i','ö'=>'o','ü'=>'u','ç'=>'c','ğ'=>'g'];
        return strtr($text, $map);
    };

    $keep_term = null;
    $candidates = [];
    foreach ((array) $terms as $term) {
        if (!$term || is_wp_error($term)) continue;
        $name_norm = $normalize($term->name);
        $slug_norm = $normalize($term->slug);
        $match = (strpos($name_norm, 'tisort') !== false)
            || (strpos($slug_norm, 'tisort') !== false)
            || (strpos($slug_norm, 'tshirt') !== false)
            || (strpos($name_norm, 'tshirt') !== false)
            || (strpos($slug_norm, 't-shirt') !== false)
            || (strpos($name_norm, 't-shirt') !== false);
        if (!$match) continue;
        $candidates[] = $term;
        if (strpos($name_norm, 'tisort') !== false || strpos($slug_norm, 'tisort') !== false) {
            $keep_term = $term;
        }
    }

    if (!$keep_term && !empty($candidates)) {
        $keep_term = $candidates[0];
    }

    if (!$keep_term) {
        $inserted = wp_insert_term('Tişört', 'product_cat', array_filter([
            'slug' => 'tisort',
            'parent' => $parent_id ?: null,
        ]));
        if (is_array($inserted)) {
            $keep_term = get_term($inserted['term_id'], 'product_cat');
        }
    } else {
        $keep_term = get_term($keep_term->term_id, 'product_cat');
    }

    if ($keep_term && !is_wp_error($keep_term)) {
        wp_update_term($keep_term->term_id, 'product_cat', array_filter([
            'name' => 'Tişört',
            'slug' => 'tisort',
            'parent' => $parent_id ?: null,
        ]));
    }

    $keep_id = ($keep_term && !is_wp_error($keep_term)) ? (int) $keep_term->term_id : 0;
    if ($keep_id) {
        foreach ($candidates as $term) {
            if ((int) $term->term_id === $keep_id) continue;
            $posts = get_posts([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [[ 'taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>$term->term_id ]]
            ]);
            foreach ((array) $posts as $pid) {
                $current = wp_get_object_terms($pid, 'product_cat', ['fields' => 'ids']);
                $current = array_values(array_diff((array) $current, [(int) $term->term_id]));
                $current[] = $keep_id;
                wp_set_object_terms($pid, array_values(array_unique($current)), 'product_cat', false);
            }
            wp_delete_term($term->term_id, 'product_cat');
        }
    }

    update_option('parilte_tisort_merge_v1', 1);
}
add_action('admin_init', 'parilte_cs_merge_tisort_terms_once', 33);

function parilte_cs_assign_ceket_dual_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_ceket_dual_done_v1')) return;

    $ceket_dis = get_term_by('slug', 'ceket', 'product_cat');
    $ceket_ust = get_term_by('slug', 'ceket-ust', 'product_cat');
    if (!$ceket_dis || !$ceket_ust || is_wp_error($ceket_dis) || is_wp_error($ceket_ust)) return;

    $posts = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [[ 'taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>$ceket_dis->term_id ]]
    ]);
    foreach ((array) $posts as $pid) {
        $terms = wp_get_object_terms($pid, 'product_cat', ['fields' => 'ids']);
        if (!in_array((int) $ceket_ust->term_id, $terms, true)) {
            $terms[] = (int) $ceket_ust->term_id;
            wp_set_object_terms($pid, array_values(array_unique($terms)), 'product_cat', false);
        }
    }

    update_option('parilte_ceket_dual_done_v1', 1);
}
add_action('admin_init', 'parilte_cs_assign_ceket_dual_once', 32);

add_action('template_redirect', function () {
    if (!is_tax('product_cat')) return;
    $term = get_queried_object();
    if (!$term || is_wp_error($term) || empty($term->slug)) return;
    if ($term->slug !== 'tum-urunler') return;
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/magaza/');
    if ($shop_url) {
        wp_safe_redirect($shop_url, 301);
        exit;
    }
});

add_filter('woocommerce_product_query_meta_query', function ($meta_query) {
    if (is_admin()) return $meta_query;
    $meta_query[] = [
        'key'     => '_parilte_placeholder',
        'compare' => 'NOT EXISTS',
    ];
    $meta_query[] = [
        'key'     => '_thumbnail_id',
        'compare' => 'EXISTS',
    ];
    return $meta_query;
}, 10);

add_filter('woocommerce_product_is_visible', function ($visible, $product) {
    if (!$product instanceof WC_Product) return $visible;
    $name = $product->get_name();
    if (stripos($name, 'placeholder') !== false) return false;
    if (get_post_meta($product->get_id(), '_parilte_placeholder', true) === '1') return false;
    if ((is_front_page() || is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag()) && !has_post_thumbnail($product->get_id())) return false;
    return $visible;
}, 10, 2);

add_filter('woocommerce_loop_add_to_cart_link', function ($html, $product) {
    if (is_admin()) return $html;
    return '';
}, 10, 2);

// Mobile sticky add-to-cart bar
add_action('woocommerce_after_add_to_cart_form', function () {
    if (!is_product()) return;
    ?>
    <div class="parilte-sticky-cart" aria-live="polite">
      <div class="parilte-sticky-meta">
        <span class="parilte-sticky-price"></span>
        <span class="parilte-sticky-note">Beden seçimi gerekli olabilir.</span>
      </div>
      <button type="button" class="parilte-sticky-button">Sepete Ekle</button>
    </div>
    <script>
      (function(){
        function syncSticky(){
          var priceEl = document.querySelector('.summary .price');
          var targetPrice = document.querySelector('.parilte-sticky-price');
          if (priceEl && targetPrice) targetPrice.innerHTML = priceEl.innerHTML;
          var addBtn = document.querySelector('form.cart .single_add_to_cart_button');
          var stickyBtn = document.querySelector('.parilte-sticky-button');
          if (!addBtn || !stickyBtn) return;
          var disabled = addBtn.disabled || addBtn.classList.contains('disabled');
          stickyBtn.disabled = disabled;
          stickyBtn.textContent = disabled ? 'Beden seçin' : 'Sepete Ekle';
        }
        document.addEventListener('click', function(e){
          if (e.target.closest('.parilte-sticky-button')) {
            var addBtn = document.querySelector('form.cart .single_add_to_cart_button');
            if (addBtn) addBtn.click();
          }
        });
        document.addEventListener('change', syncSticky);
        if (window.jQuery) {
          jQuery(document.body).on('found_variation reset_data', syncSticky);
        }
        window.addEventListener('load', syncSticky);
      })();
    </script>
    <?php
}, 20);

add_filter('woocommerce_sale_flash', function () {
    return '';
});

add_filter('gettext', function ($translated, $text, $domain) {
    $map = [
        'Sale!' => 'İndirim',
        'Sale' => 'İndirim',
        'Free shipping' => 'Ücretsiz kargo',
        'Coupon code' => 'Kupon kodu',
        'Apply coupon' => 'Kuponu uygula',
        'Update cart' => 'Sepeti güncelle',
        'Cart totals' => 'Sepet toplamı',
        'Subtotal' => 'Ara toplam',
        'Shipping' => 'Kargo',
        'Total' => 'Toplam',
        'Product' => 'Ürün',
        'Product quantity' => 'Ürün adedi',
        'Quantity' => 'Adet',
        'Proceed to checkout' => 'Ödemeye geç',
        'Checkout' => 'Ödeme',
        'Shipping to' => 'Gönderim adresi',
        'Calculate shipping' => 'Kargo hesapla',
        'Coupon:' => 'Kupon:',
        'Have a coupon?' => 'Kuponunuz var mı?',
        'Enter your coupon code' => 'Kupon kodunuzu girin',
        'Cart updated.' => 'Sepet güncellendi.',
        'Your cart is currently empty.' => 'Sepetiniz şu an boş.',
    ];
    if (isset($map[$text])) return $map[$text];
    return $translated;
}, 10, 3);

function parilte_cs_footer_links() {
    if (is_admin()) return;
    $links = [];
    $pages = [
        'Hakkımızda' => 'hakkimizda',
        'KVKK & Gizlilik' => 'kvkk-gizlilik',
        'İade & Değişim' => 'iade-degisim',
        'Teslimat & Kargo' => 'teslimat-kargo',
        'Mesafeli Satış Sözleşmesi' => 'mesafeli-satis-sozlesmesi',
    ];
    foreach ($pages as $label => $slug) {
        $p = get_page_by_path($slug);
        if ($p) $links[] = ['label' => $label, 'url' => get_permalink($p)];
    }
    if (!$links) return;
    $whatsapp_url = 'https://wa.me/905394353913';
    $instagram_url = 'https://www.instagram.com/butik_parilte_/';
    ?>
    <div class="parilte-legal-footer">
      <div class="parilte-container">
        <div class="parilte-legal-left">
          <nav class="parilte-legal-links" aria-label="Yasal">
            <?php foreach ($links as $l) { ?>
              <a href="<?php echo esc_url($l['url']); ?>"><?php echo esc_html($l['label']); ?></a>
            <?php } ?>
          </nav>
          <span class="parilte-legal-copy">© <?php echo date('Y'); ?> Parilté</span>
        </div>
        <div class="parilte-legal-right">
          <div class="parilte-legal-whatsapp">
            <span>WhatsApp Danışma Hattı</span>
            <a class="parilte-home-cta-btn parilte-home-cta-btn--wa" href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener">WhatsApp</a>
            <a class="parilte-legal-instagram" href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener" aria-label="Instagram">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm0 2a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H7zm5 3.2a3.8 3.8 0 1 1 0 7.6 3.8 3.8 0 0 1 0-7.6zm0 2a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6zm5.25-.95a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1z"/></svg>
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php
}
add_action('wp_footer', 'parilte_cs_footer_links', 30);

function parilte_cs_upsert_page_content($slug, $title, $content) {
    $page = get_page_by_path($slug);
    if ($page && !is_wp_error($page)) {
        wp_update_post([
            'ID' => (int) $page->ID,
            'post_title' => $title,
            'post_content' => wp_kses_post($content),
            'post_status' => 'publish',
        ]);
        return (int) $page->ID;
    }
    $new_id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => wp_kses_post($content),
    ]);
    return is_wp_error($new_id) ? 0 : (int) $new_id;
}

function parilte_cs_update_legal_pages_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_legal_pages_v8')) return;

    $mail_href = 'mailto:destek@parilte.com';
    $mail_label = 'destek@parilte.com';
    $whatsapp_url = 'https://wa.me/905394353913';

    $about = '<h2>Hakkımızda</h2>
<p>Parılté Butik, yalnızca bir moda markası değil; kendini değerli, güçlü ve özel hissetmek isteyen kadınların hikâyesidir.</p>
<p>Parılté, ışığını kaybetmeyen, her ortamda zarafetiyle fark yaratan kadınlardan ilham alır.</p>
<p>Her koleksiyonumuz; şıklığı, konforu ve zamansız stili bir araya getirmek için özenle seçilir. Biz modayı bir yarış değil, bir ifade biçimi olarak görüyoruz.</p>
<p>Bir elbise yalnızca bir elbise değildir; bir duruş, bir özgüven ve bir hatıradır.</p>
<p>Parılté Butik’te bulacağınız her parça;</p>
<ul>
  <li>Kendinizi iyi hissetmeniz için,</li>
  <li>Günün her anında şık olmanız için,</li>
  <li>Yıllar sonra bile severek giymeniz için</li>
</ul>
<p>özenle seçilmiştir.</p>
<p>Bizim için her kadın kendi ışığını taşır. Biz sadece onu ortaya çıkarırız.</p>
<p><strong>Parılté – Çünkü her kadın parıldamayı hak eder.</strong></p>
<h3>İletişim</h3>
<ul>
  <li>E-posta: <a href="'.$mail_href.'">'.$mail_label.'</a></li>
  <li>WhatsApp: <a href="'.$whatsapp_url.'" target="_blank" rel="noopener">0539 435 39 13</a></li>
  <li>Adres: Rüstempaşa Mah. Çeşme Sk. No: 17/D, Yalova / Merkez</li>
  <li>Instagram: <a href="https://www.instagram.com/butik_parilte_/" target="_blank" rel="noopener">@butik_parilte_</a></li>
</ul>';

    $kvkk = '<h2>KVKK &amp; Gizlilik Politikası</h2>
<p>Parılté Butik olarak, 6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) kapsamında kişisel verilerinizin güvenliğini önemsiyoruz. Bu politika; parilte.com üzerinden toplanan kişisel verilerin hangi kapsamda işlendiğini açıklar.</p>
<h3>1) Veri Sorumlusu</h3>
<p>Parılté Butik (Şahıs İşletmesi)<br>Vergi Dairesi: Yalova Vergi Dairesi<br>Vergi No: 1020667844<br>Adres: Rüstempaşa Mah. Çeşme Sk. No: 17/D, Yalova / Merkez<br>Telefon/WhatsApp: 0539 435 39 13<br>E-posta: <a href="'.$mail_href.'">'.$mail_label.'</a></p>
<h3>2) İşlenen Kişisel Veriler</h3>
<ul>
  <li>Kimlik: ad, soyad</li>
  <li>İletişim: telefon, e-posta</li>
  <li>Teslimat/Fatura: adres bilgileri</li>
  <li>İşlem güvenliği: IP adresi ve site kullanım kayıtları</li>
  <li>Sipariş: sipariş içeriği, tutar, teslimat ve iade kayıtları</li>
  <li>Ödeme: ödeme işlemine ilişkin kayıtlar (kart bilgileri Parılté tarafından tutulmaz)</li>
</ul>
<h3>3) İşleme Amaçları</h3>
<ul>
  <li>Sipariş süreçlerinin yürütülmesi (satış, ödeme, teslimat, iade/değişim)</li>
  <li>Müşteri ilişkileri ve destek süreçlerinin yürütülmesi</li>
  <li>Hukuki yükümlülüklerin yerine getirilmesi (muhasebe, e-arşiv/e-fatura, resmi merciler)</li>
  <li>İşlem güvenliği ve kötüye kullanımın önlenmesi</li>
</ul>
<h3>4) Veri Paylaşımı / Aktarım</h3>
<p>Kişisel verileriniz; yalnızca hizmetin ifası için gerekli ölçüde ve mevzuata uygun şekilde aşağıdaki alıcı gruplarıyla paylaşılabilir:</p>
<ul>
  <li>Ödeme altyapısı: iyzico (ödeme işleminin yürütülmesi)</li>
  <li>Kargo firması: MNG Kargo (sipariş teslimatı için)</li>
  <li>Yetkili kamu kurumları: yasal yükümlülükler kapsamında</li>
</ul>
<p>Not: Kart bilgileri Parılté tarafından kaydedilmez; ödeme sağlayıcısı altyapısında işlenir.</p>
<h3>5) Saklama Süresi</h3>
<p>Verileriniz; ilgili mevzuatta öngörülen süreler boyunca saklanır ve süre sonunda silinir/anonimleştirilir.</p>
<h3>6) Haklarınız ve Başvuru</h3>
<p>KVKK’nın 11. maddesi kapsamındaki haklarınıza ilişkin taleplerinizi <a href="'.$mail_href.'">'.$mail_label.'</a> üzerinden bize iletebilirsiniz.</p>';

    $shipping = '<h2>Teslimat &amp; Kargo</h2>
<p>Parılté Butik’ten verdiğiniz tüm siparişler özenle hazırlanır ve güvenle kargoya teslim edilir.</p>
<h3>1) Hazırlık Süresi</h3>
<p>Siparişleriniz 1–3 iş günü içerisinde hazırlanarak kargoya verilir. (Kampanya ve yoğun dönemlerde bu süre uzayabilir.)</p>
<h3>2) Kargo ve Gönderim</h3>
<ul>
  <li>Türkiye’nin her yerine gönderim yapılır.</li>
  <li>Kargo firması: MNG Kargo</li>
  <li>Siparişiniz kargoya verildiğinde tarafınıza kargo takip bilgisi iletilir.</li>
</ul>
<h3>3) Kargo Ücreti</h3>
<ul>
  <li>100 TL sabit kargo ücreti uygulanır.</li>
  <li>1500 TL ve üzeri siparişlerde kargo ücretsizdir.</li>
</ul>
<h3>4) Teslimat Süresi</h3>
<p>Kargonuz bulunduğunuz şehre göre genellikle 1–3 iş günü içinde adresinize ulaşır.</p>
<h3>5) Teslimat Kontrolü</h3>
<p>Teslimat sırasında paketinizi kontrol etmenizi öneririz. Hasarlı paketleri teslim almadan kargo görevlisine tutanak tutturarak bizimle iletişime geçiniz.</p>
<h3>6) Adres Sorumluluğu</h3>
<p>Teslimat adresinin eksik/hatalı olması halinde oluşabilecek gecikmelerden alıcı sorumludur.</p>
<p>İletişim: <a href="'.$mail_href.'">'.$mail_label.'</a> | WhatsApp: <a href="'.$whatsapp_url.'" target="_blank" rel="noopener">0539 435 39 13</a></p>';

    $returns = '<h2>İade &amp; Değişim</h2>
<p>Parılté Butik’te müşteri memnuniyeti önceliğimizdir.</p>
<h3>1) Cayma Hakkı (İade)</h3>
<p>Siparişinizi teslim aldığınız tarihten itibaren 14 gün içinde cayma hakkınızı kullanarak iade talebinde bulunabilirsiniz.</p>
<h3>2) İade/Değişim Şartları</h3>
<p>İade/Değişim için ürün:</p>
<ul>
  <li>Kullanılmamış olmalı,</li>
  <li>Etiketleri koparılmamış olmalı,</li>
  <li>Hasar görmemiş olmalı,</li>
  <li>Orijinal ambalajında olmalı,</li>
  <li>Tekrar satılabilir durumda olmalıdır.</li>
</ul>
<h3>3) İade Edilemeyen Ürünler</h3>
<p>Hijyen nedeniyle; iç giyim, mayo/bikini, küpe gibi kişisel kullanım ürünlerinde iade/değişim kabul edilmez. (Not: Ürün kategorilerine göre bu liste güncellenebilir.)</p>
<h3>4) Süreç Nasıl İşler?</h3>
<ol>
  <li>Talebinizi bize iletirsiniz (<a href="'.$mail_href.'">'.$mail_label.'</a> / WhatsApp).</li>
  <li>Ürünü tarafımıza gönderirsiniz.</li>
  <li>Ürün kontrol edilir.</li>
  <li>Şartlara uygunsa iade/değişim işlemi başlatılır.</li>
</ol>
<h3>5) İade Kargo Ücreti</h3>
<p>İade kargo bedeli alıcıya aittir.</p>
<h3>6) Geri Ödeme</h3>
<p>İade onaylandıktan sonra ücretiniz, ödemenin yapıldığı yönteme uygun şekilde iade edilir. Banka süreçlerine bağlı olarak iadenin hesaba yansıma süresi değişebilir.</p>
<h3>7) İade Adresi</h3>
<p>Rüstempaşa Mah. Çeşme Sk. No: 17/D, Yalova / Merkez</p>
<p>İletişim: <a href="'.$mail_href.'">'.$mail_label.'</a> | WhatsApp: <a href="'.$whatsapp_url.'" target="_blank" rel="noopener">0539 435 39 13</a></p>';

    $distance = '<h2>Mesafeli Satış Sözleşmesi</h2>
<p>İşbu Mesafeli Satış Sözleşmesi (“Sözleşme”), aşağıda bilgileri yer alan Satıcı ile, parilte.com üzerinden sipariş oluşturan Alıcı arasında elektronik ortamda kurulmuştur.</p>
<h3>1) Taraflar</h3>
<p><strong>Satıcı</strong><br>Unvan: Parılté Butik (Şahıs İşletmesi)<br>Vergi Dairesi: Yalova Vergi Dairesi<br>Vergi No: 1020667844<br>Adres: Rüstempaşa Mah. Çeşme Sk. No: 17/D, Yalova / Merkez<br>Telefon/WhatsApp: 0539 435 39 13<br>E-posta: <a href="'.$mail_href.'">'.$mail_label.'</a></p>
<p><strong>Alıcı</strong><br>Alıcı; parilte.com üzerinden sipariş oluşturan, ürün/hizmet satın alan kişidir. Sipariş sırasında beyan ettiği ad-soyad, adres ve iletişim bilgileri esas alınır.</p>
<h3>2) Konu</h3>
<p>Sözleşmenin konusu, Alıcı’nın parilte.com üzerinden elektronik ortamda sipariş verdiği ürün/ürünlerin satışı ve teslimine ilişkin tarafların hak ve yükümlülüklerinin belirlenmesidir.</p>
<h3>3) Ürün / Fiyat / Ödeme</h3>
<ul>
  <li>Ürün(ler)in cinsi, adedi, beden/renk seçimi, birim fiyatı, toplam bedeli, kargo bedeli ve ödeme yöntemi; siparişin oluşturulduğu anda sepette ve sipariş özetinde gösterildiği şekildedir.</li>
  <li>Ödeme; kredi/banka kartı ile yapılabilir.</li>
  <li>Ödeme altyapısı: iyzico (iyzipay) aracılığıyla tahsilat yapılır. Kart bilgileri Satıcı tarafından saklanmaz, ödeme sağlayıcısı altyapısında işlenir.</li>
</ul>
<h3>4) Teslimat</h3>
<ul>
  <li>Siparişler 1–3 iş günü içerisinde hazırlanarak kargoya verilir. (Yoğun dönemlerde uzayabilir.)</li>
  <li>Teslimat Türkiye içidir.</li>
  <li>Kargo firması: MNG Kargo</li>
  <li>Kargo ücreti: 100 TL’dir. 1500 TL ve üzeri siparişlerde kargo ücretsizdir.</li>
  <li>Alıcı’nın adres bilgisini eksik/hatalı girmesi halinde doğabilecek gecikmelerden Alıcı sorumludur.</li>
</ul>
<h3>5) Cayma Hakkı (İade)</h3>
<ul>
  <li>Alıcı, ürünü teslim aldığı tarihten itibaren 14 gün içinde cayma hakkını kullanabilir.</li>
  <li>Cayma hakkının kullanılabilmesi için ürünün kullanılmamış, etiketi koparılmamış, hasar görmemiş ve tekrar satılabilir durumda olması gerekir.</li>
  <li>Hijyen nedeniyle; iç giyim, mayo/bikini, küpe gibi kişisel kullanım ürünlerinde iade/değişim kabul edilmez.</li>
</ul>
<h3>6) İade Prosedürü, İade Kargo ve Geri Ödeme</h3>
<ul>
  <li>Alıcı, cayma talebini <a href="'.$mail_href.'">'.$mail_label.'</a> üzerinden iletir.</li>
  <li>Alıcı ürünü Satıcı’nın belirttiği adrese gönderir. İade kargo bedeli Alıcı’ya aittir.</li>
  <li>Ürün Satıcı’ya ulaştıktan sonra kontrol edilir.</li>
  <li>Şartlara uygunsa iade işlemi başlatılır ve bedel, ödemenin yapıldığı yönteme uygun şekilde iade edilir.</li>
  <li>Banka süreçlerine bağlı olarak iadenin hesaba yansıma süresi değişebilir.</li>
</ul>
<h3>7) Uyuşmazlık Çözümü</h3>
<p>İşbu sözleşmeden doğabilecek uyuşmazlıklarda; tüketici hakem heyetleri ve tüketici mahkemeleri yetkilidir.</p>
<h3>8) Yürürlük</h3>
<p>Alıcı, sipariş onayıyla birlikte işbu sözleşme hükümlerini okuduğunu ve kabul ettiğini beyan eder. Sözleşme, siparişin onaylandığı anda yürürlüğe girer.</p>';

    parilte_cs_upsert_page_content('hakkimizda', 'Hakkımızda', $about);
    $kvkk_id = parilte_cs_upsert_page_content('kvkk-gizlilik', 'KVKK & Gizlilik', $kvkk);
    parilte_cs_upsert_page_content('teslimat-kargo', 'Teslimat & Kargo', $shipping);
    parilte_cs_upsert_page_content('iade-degisim', 'İade & Değişim', $returns);
    $terms_id = parilte_cs_upsert_page_content('mesafeli-satis-sozlesmesi', 'Mesafeli Satış Sözleşmesi', $distance);

    if ($terms_id) {
        update_option('woocommerce_terms_page_id', (int) $terms_id);
    }
    if ($kvkk_id) {
        update_option('wp_page_for_privacy_policy', (int) $kvkk_id);
    }

    update_option('parilte_legal_pages_v8', 1);
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules(false);
    }
}
add_action('admin_init', 'parilte_cs_update_legal_pages_once', 36);
add_action('init', 'parilte_cs_update_legal_pages_once', 9);

function parilte_cs_ensure_promo_pages_once() {
    if (!current_user_can('manage_options')) return;
    if (get_option('parilte_promo_pages_v1')) return;

    $pages = [
        'indirimler' => '[products on_sale="true" columns="4" paginate="true"]',
        'yeni-gelenler' => '[products orderby="date" order="DESC" columns="4" paginate="true"]',
        'en-cok-satanlar' => '[products best_selling="true" columns="4" paginate="true"]',
    ];

    foreach ($pages as $slug => $shortcode) {
        $p = get_page_by_path($slug);
        if (!$p || is_wp_error($p)) {
            $title = ($slug === 'indirimler') ? 'İndirimler' : (($slug === 'yeni-gelenler') ? 'Yeni Gelenler' : 'En Çok Satanlar');
            $pid = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$shortcode]);
            if (is_wp_error($pid)) continue;
            $p = get_post($pid);
        }
        if ($p && !is_wp_error($p)) {
            wp_update_post(['ID' => $p->ID, 'post_content' => $shortcode]);
        }
    }

    update_option('parilte_promo_pages_v1', 1);
}
add_action('admin_init', 'parilte_cs_ensure_promo_pages_once', 37);

add_action('woocommerce_product_query', function ($q) {
    if (is_admin()) return;
    $ids = parilte_cs_get_placeholder_ids();
    if (!$ids) return;
    $exclude = (array) $q->get('exclude');
    $q->set('exclude', array_values(array_unique(array_merge($exclude, $ids))));
}, 10);

add_filter('woocommerce_shortcode_products_query', function ($args) {
    $ids = parilte_cs_get_placeholder_ids();
    if (!$ids) return $args;
    $existing = isset($args['post__not_in']) ? (array) $args['post__not_in'] : [];
    $args['post__not_in'] = array_values(array_unique(array_merge($existing, $ids)));
    $mq = isset($args['meta_query']) ? (array) $args['meta_query'] : [];
    $mq[] = [
        'key'     => '_thumbnail_id',
        'compare' => 'EXISTS',
    ];
    $args['meta_query'] = $mq;
    return $args;
}, 10);

add_filter('loop_shop_columns', function ($cols) {
    return 4;
}, 20);

function parilte_cs_rebuild_wc_lookup_tables_once() {
    if (!current_user_can('manage_options')) {
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
        $url = home_url('/');
        $is_local = ($env === 'local') || (stripos($url, 'localhost') !== false) || (stripos($url, '127.0.0.1') !== false);
        if (!$is_local) return;
    }
    if (get_option('parilte_wc_lookup_rebuilt')) return;
    if (function_exists('wc_update_product_lookup_tables') && !wc_update_product_lookup_tables_is_running()) {
        wc_update_product_lookup_tables();
    }
    update_option('parilte_wc_lookup_rebuilt', 1);
}
add_action('admin_init', 'parilte_cs_rebuild_wc_lookup_tables_once', 40);
add_action('init', 'parilte_cs_rebuild_wc_lookup_tables_once', 40);

add_action('wp_enqueue_scripts', function () {
    $wood_new  = esc_url(plugins_url('assets/wood-new.jpg', __FILE__));
    $wood_best = esc_url(plugins_url('assets/wood-best.jpg', __FILE__));
    $wood_sale = esc_url(plugins_url('assets/wood-sale.jpg', __FILE__));
    $css = '
    .parilte-header-wrap{max-width:1140px;margin:0 auto;padding:0 16px}
    .parilte-header-icons{display:flex;gap:12px;align-items:center;font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;width:100%;justify-content:space-between}
    .parilte-header-icons a,
    .parilte-header-icons button{display:inline-flex;gap:6px;align-items:center;text-decoration:none;color:inherit;font:inherit;letter-spacing:inherit;text-transform:uppercase}
    .parilte-header-icons svg{fill:currentColor;opacity:.6;width:14px;height:14px}
    .parilte-search-toggle{border:0;background:transparent;cursor:pointer;padding:0;font-size:.72rem;letter-spacing:.18em}
    .parilte-search-toggle svg{opacity:.6}
    .parilte-search-drawer{position:fixed;inset:0;display:none;z-index:9999}
    body.parilte-search-open .parilte-search-drawer{display:block}
    .parilte-search-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55)}
    .parilte-search-panel{position:absolute;left:50%;top:10%;transform:translateX(-50%);width:min(92vw,520px);background:#fff;border-radius:18px;padding:18px;box-shadow:0 24px 48px rgba(0,0,0,.2)}
    .parilte-search-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
    .parilte-search-close{border:0;background:transparent;font-size:22px;cursor:pointer}
    .parilte-search-form{display:grid;gap:10px}
    .parilte-search-input{width:100%;padding:12px 14px;border:1px solid rgba(0,0,0,.16);border-radius:12px;font:inherit}
    .parilte-search-submit{border:0;background:#111;color:#fff;border-radius:999px;padding:10px 18px;cursor:pointer}
    .parilte-filter-toggle{border:1px solid rgba(0,0,0,.12);background:#fff;color:#111;border-radius:999px;padding:8px 14px;font-size:.75rem;letter-spacing:.16em;text-transform:uppercase;margin-bottom:10px;cursor:pointer}
    .parilte-filter-drawer{position:fixed;inset:0;display:none;z-index:9998}
    body.parilte-filters-open .parilte-filter-drawer{display:block}
    .parilte-filter-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.4)}
    .parilte-filter-panel{position:absolute;left:50%;bottom:0;transform:translateX(-50%);width:min(96vw,520px);max-height:82vh;background:#fff;border-radius:18px 18px 0 0;padding:16px;overflow:auto}
    .parilte-filter-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase}
    .parilte-filter-close{border:0;background:transparent;font-size:22px;cursor:pointer}
    .woocommerce .woocommerce-ordering,
    .woocommerce .woocommerce-result-count{float:none}
    .woocommerce .woocommerce-ordering select{border:1px solid rgba(0,0,0,.12);border-radius:999px;padding:8px 14px}
    .ct-header{position:sticky;top:0;z-index:999;background:#fff}
    .parilte-header-cats{position:relative}
    .parilte-header-cats-toggle{
      border:0;background:transparent;cursor:pointer;padding:0;letter-spacing:.14em;text-transform:uppercase;font:inherit;color:inherit;
      display:inline-flex;align-items:center;gap:6px
    }
    .parilte-header-cats-toggle::after{content:"▾";font-size:.7rem;opacity:.6}
    .parilte-header-cats-panel{
      position:absolute;left:0;top:100%;margin-top:10px;min-width:260px;max-width:360px;
      background:rgba(255,255,255,.98);border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:12px;
      box-shadow:0 18px 36px rgba(0,0,0,.12);display:none;z-index:50;
      max-height:70vh;overflow-y:auto
    }
    .parilte-header-cats:hover .parilte-header-cats-panel,
    .parilte-header-cats:focus-within .parilte-header-cats-panel,
    .parilte-header-cats.is-open .parilte-header-cats-panel{display:block}
    .parilte-header-cats .parilte-cat-tree{margin:0;padding:0;border:0;background:transparent}
    .parilte-header-cats .parilte-cat-tree-head{display:none}
    .parilte-header-cats .parilte-cat-tree-list{gap:4px}
    .parilte-header-cats .parilte-cat-tree-children{margin-left:8px}
    .parilte-mobile-menu-toggle{display:none;border:0;background:transparent;cursor:pointer;align-items:center;gap:8px;font:inherit;letter-spacing:.14em;text-transform:uppercase}
    .parilte-mobile-menu-icon{width:18px;height:12px;position:relative;display:inline-block}
    .parilte-mobile-menu-icon::before,
    .parilte-mobile-menu-icon::after{content:"";position:absolute;left:0;right:0;height:2px;background:currentColor;border-radius:2px}
    .parilte-mobile-menu-icon::before{top:0}
    .parilte-mobile-menu-icon::after{bottom:0}
    .parilte-mobile-menu-text{font-size:.72rem}

    .parilte-mobile-drawer{position:fixed;inset:0;display:none;z-index:9999}
    body.parilte-mobile-open .parilte-mobile-drawer{display:block}
    body.parilte-mobile-open{overflow:hidden}
    .parilte-mobile-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
    .parilte-mobile-panel{position:absolute;left:0;top:0;bottom:0;width:min(86vw,360px);background:#f7f4ef;
      padding:16px;display:flex;flex-direction:column;gap:12px;overflow-y:auto;-webkit-overflow-scrolling:touch}
    .parilte-mobile-header{display:flex;align-items:center;justify-content:space-between;font-weight:600;letter-spacing:.12em;text-transform:uppercase;font-size:.78rem}
    .parilte-mobile-close{border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer}
    .parilte-mobile-links{display:flex;flex-direction:column;gap:8px}
    .parilte-mobile-links a{text-decoration:none;color:var(--parilte-ink);font-weight:600;letter-spacing:.08em}
    .parilte-mobile-cats .parilte-cat-tree{margin:0;padding:0;border:0;background:transparent}
    .parilte-mobile-cats .parilte-cat-tree-head{display:none}
    .parilte-cart-count{min-width:16px;height:16px;display:inline-flex;align-items:center;justify-content:center;
      font-size:.62rem;border-radius:999px;background:currentColor;color:#fff;padding:0 5px;line-height:1}
    .parilte-search-form{display:flex;align-items:center;gap:8px;flex:1;justify-content:center}
    .parilte-search-input{width:clamp(160px,32vw,360px);max-width:60vw;padding:.2rem 0;border:0;border-bottom:1px solid rgba(0,0,0,.25);border-radius:0;background:transparent;font:inherit;text-transform:inherit;letter-spacing:inherit;font-size:inherit;text-align:center}
    .parilte-search-input::placeholder{opacity:.5}
    .parilte-search-button{border:0;background:transparent;padding:0;display:inline-flex;align-items:center;cursor:pointer;color:inherit}
    .parilte-search-button svg{width:14px;height:14px;opacity:.6}
    .ct-header .menu .parilte-menu-tools{display:flex;align-items:center;margin-left:16px;width:100%}
    .ct-header .menu .parilte-menu-tools .parilte-header-icons{gap:14px}
    .ct-header .menu .parilte-menu-tools a{color:inherit;font:inherit;text-transform:uppercase;letter-spacing:.14em;font-size:.72rem}
    .ct-header .menu .parilte-menu-tools span{font-size:.72rem;text-transform:uppercase;letter-spacing:.14em}
    .ct-header{position:relative}
    .ct-header::before{display:none}
    .ct-header .ct-container{position:relative;z-index:1}
      .ct-header-search,.ct-header-cart,.ct-account-item,.ct-header-account{display:none !important}
      body.home .entry-title, body.home .page-title, body.home .ct-hero-title{display:none}
      ul.products li.product .button.parilte-card-btn{width:100%;text-align:center;margin-top:.35rem}
      .term-description{margin-top:28px}
      .parilte-size-box{margin-top:14px;border:1px solid #e6e6e6;border-radius:10px;padding:.6rem .9rem}
      .parilte-size-box summary{cursor:pointer;font-weight:600}
      .parilte-size-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(72px,1fr));gap:8px;margin:.6rem 0}
      .parilte-size-grid > div{border:1px dashed #ddd;border-radius:8px;padding:.4rem .5rem;text-align:center}
    .parilte-front{--parilte-ink:#111;--parilte-muted:#5f646b;--parilte-surface:#fff;--parilte-cream:#f6f1ea;--parilte-border:rgba(0,0,0,.12);--parilte-shadow:0 16px 34px rgba(0,0,0,.12)}
    .parilte-front{font-family:"Montserrat","Helvetica Neue",Arial,sans-serif}
    .parilte-front h1,
    .parilte-front h2,
    .parilte-front h3{font-family:"Bodoni MT","Didot","Playfair Display","Times New Roman",serif;letter-spacing:.04em}
    body{--content-vertical-spacing:0px}
    body .ct-header{margin-bottom:0 !important}
    body .ct-header .ct-container{padding-bottom:0 !important}
    body .ct-content{padding-top:0 !important;margin-top:0 !important}
    body .ct-content .ct-container,
    body .ct-content .ct-container-fluid{padding-top:0 !important;margin-top:0 !important}
    body .site-main{margin-top:0 !important;padding-top:0 !important}
    body .ct-header + .ct-content{margin-top:-1px !important}
    body.home .parilte-home-hero{margin-top:0 !important}
    body.home .ct-hero-section,
    body.home .ct-hero{display:none !important}
    .parilte-bleed{width:100vw;max-width:100vw;margin-left:calc(50% - 50vw)}
    .parilte-container{max-width:1140px;margin:0 auto;padding:0 16px}
    @keyframes parilte-rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    @keyframes parilte-fade{from{opacity:0}to{opacity:1}}
    .parilte-mag-hero{min-height:clamp(320px,70vw,640px);background-size:cover;background-position:50% 12%;background-repeat:no-repeat;position:relative}
    .parilte-mag-hero-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none}
    .parilte-hero-cta{display:inline-flex;align-items:center;justify-content:center;background:#c51d24;color:#fff;border-radius:999px;padding:12px 26px;font-weight:600;letter-spacing:.22em;text-transform:uppercase;text-decoration:none;box-shadow:0 10px 24px rgba(0,0,0,.25);pointer-events:auto}
    .parilte-hero-cta:hover{background:#a8181f}
    .parilte-home-hero{position:relative;background:#f6f1ea;overflow:hidden}
    .parilte-home-img{width:100%;height:auto;display:block}
    .parilte-home-hero .parilte-home-img{
      height:clamp(320px,60vw,560px);
      object-fit:cover;
      object-position:50% 70%;
      background:transparent;
      position:relative;
      z-index:1;
    }
    .parilte-home-hero-cta{
      position:absolute;
      left:50%;
      bottom:clamp(14px,4vw,36px);
      transform:translateX(-50%);
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      justify-content:center;
      z-index:2;
    }
    .parilte-home-cta-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background:#c51d24;
      color:#fff;
      border-radius:999px;
      padding:6px 12px;
      font-size:.58rem;
      letter-spacing:.12em;
      text-transform:uppercase;
      text-decoration:none;
      box-shadow:0 10px 22px rgba(0,0,0,.18);
      white-space:nowrap;
      width:fit-content;
      max-width:100%;
      border:0;
      cursor:pointer;
      appearance:none;
      font:inherit;
      font-family:"Bodoni MT","Didot","Playfair Display","Times New Roman",serif;
      font-weight:500;
    }
    button.parilte-home-cta-btn{
      background:#c51d24 !important;
      color:#fff !important;
      border:0 !important;
      display:inline-flex !important;
    }
    .parilte-home-hero-cta .parilte-home-cta-btn{
      padding:14px 30px;
      font-size:.9rem;
      letter-spacing:.2em;
    }
    .parilte-home-cta-btn--sm{
      padding:6px 10px;
      font-size:.54rem;
      letter-spacing:.12em;
    }
    .parilte-home-banner{
      position:relative;
      overflow:hidden;
      background:#f6f1ea;
    }
    .parilte-home-banner .parilte-home-img{
      width:100%;
      height:clamp(240px,34vw,420px);
      object-fit:cover;
      object-position:50% 20%;
      background:transparent;
      display:block;
      position:relative;
      z-index:1;
    }
    .parilte-home-banner-content{
      position:absolute;
      left:0;
      right:0;
      bottom:0;
      padding:clamp(14px,4vw,30px) clamp(16px,6vw,64px);
      color:#fff;
      display:flex;
      flex-direction:column;
      gap:8px;
      background:linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,.72));
      z-index:2;
    }
    .parilte-home-banner-content small{
      font-size:.7rem;
      letter-spacing:.18em;
      text-transform:uppercase;
      opacity:.75;
    }
    .parilte-home-banner-content h2{
      margin:0;
      font-size:clamp(1.05rem,2.4vw,1.5rem);
      letter-spacing:.12em;
      text-transform:uppercase;
    }
    .parilte-home-banner-content p{
      margin:0;
      opacity:.75;
      font-size:.9rem;
      max-width:560px;
    }
    .parilte-home-hot{
      padding:clamp(18px,4vw,34px) 0;
      background:#fff;
      color:var(--parilte-ink);
      border-top:1px solid rgba(0,0,0,.08);
      border-bottom:1px solid rgba(0,0,0,.08);
    }
    .parilte-home-hot-inner{
      width:min(1080px,100%);
      margin:0 auto;
      padding:0 clamp(16px,6vw,64px);
      display:flex;
      flex-direction:column;
      gap:8px;
    }
    .parilte-home-hot-inner small{
      font-size:.72rem;
      letter-spacing:.16em;
      text-transform:uppercase;
      opacity:.6;
    }
    .parilte-home-hot-inner h3{
      margin:0;
      font-size:clamp(1rem,2.4vw,1.5rem);
      letter-spacing:.12em;
      text-transform:uppercase;
    }
    .parilte-home-hot-inner p{
      margin:0;
      opacity:.7;
      font-size:.95rem;
    }
    .parilte-home-cta-btn--hot{
      background:#0f6c4a !important;
      color:#fff !important;
      box-shadow:0 10px 22px rgba(15,108,74,.25) !important;
    }
    .parilte-home-contact{
      padding:clamp(20px,4vw,36px) 0;
      background:#fff;
    }
    .parilte-home-contact-inner{
      width:min(1080px,100%);
      margin:0 auto;
      padding:0 clamp(16px,6vw,64px);
      display:grid;
      grid-template-columns:1fr auto;
      align-items:center;
      column-gap:24px;
      row-gap:8px;
    }
    .parilte-home-contact-inner > *{grid-column:1}
    .parilte-home-contact-inner .parilte-home-cta-btn{display:inline-flex !important}
    .parilte-contact-actions{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      align-items:center;
      justify-self:end;
      grid-column:2;
      grid-row:1 / -1;
    }
    .parilte-home-cta-btn--mail{background:#1c1c1c !important;color:#fff !important;box-shadow:0 10px 22px rgba(0,0,0,.18) !important}
    .parilte-home-contact-inner small{
      font-size:.72rem;
      letter-spacing:.16em;
      text-transform:uppercase;
      opacity:.65;
    }
    .parilte-home-contact-inner h3{
      margin:0;
      font-size:clamp(1rem,2.2vw,1.4rem);
      letter-spacing:.12em;
      text-transform:uppercase;
    }
    .parilte-home-contact-inner p{
      margin:0;
      opacity:.7;
      font-size:.95rem;
    }
    .parilte-contact-note{
      padding:10px 12px;
      border-radius:12px;
      font-size:.9rem;
      background:#f4f4f5;
      color:#111;
    }
    .parilte-contact-mail{
      font-size:.85rem;
      letter-spacing:.08em;
      text-transform:uppercase;
      color:var(--parilte-muted);
      text-decoration:none;
      display:inline-flex;
    }
    .parilte-contact-note.success{background:#ecfdf3;color:#0f5132}
    .parilte-contact-note.error{background:#fef2f2;color:#7f1d1d}
    .parilte-contact-form{
      margin-top:8px;
      display:none;
      flex-direction:column;
      gap:12px;
    }
    .parilte-contact-form.is-open{display:flex}
    .parilte-contact-toggle{
      display:inline-flex !important;
      visibility:visible !important;
      opacity:1 !important;
    }
    .parilte-home-contact-inner .parilte-contact-toggle{margin-top:6px}
    .parilte-home-contact-inner button.parilte-home-cta-btn{
      padding:6px 12px !important;
      border-radius:999px !important;
      box-shadow:0 10px 22px rgba(0,0,0,.18) !important;
      letter-spacing:.12em !important;
      text-transform:uppercase !important;
      font-family:"Bodoni MT","Didot","Playfair Display","Times New Roman",serif !important;
      font-weight:500 !important;
    }
    .parilte-contact-form label{
      display:flex;
      flex-direction:column;
      gap:6px;
      font-size:.72rem;
      letter-spacing:.14em;
      text-transform:uppercase;
      color:var(--parilte-muted);
    }
    .parilte-contact-form input,
    .parilte-contact-form textarea{
      font:inherit;
      letter-spacing:.02em;
      text-transform:none;
      border:1px solid rgba(0,0,0,.12);
      border-radius:14px;
      padding:10px 12px;
      background:#fff;
      color:#111;
    }
    .parilte-contact-form textarea{resize:vertical;min-height:140px}
    .parilte-contact-row{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:12px;
    }
    .parilte-home-join{
      padding:clamp(18px,3.6vw,30px) 0;
      background:#fff;
    }
    .parilte-home-join-inner{
      width:min(1080px,100%);
      margin:0 auto;
      padding:0 clamp(16px,6vw,64px);
      display:grid;
      grid-template-columns:1fr auto;
      gap:18px;
      align-items:center;
    }
    .parilte-home-join-copy{display:flex;flex-direction:column;gap:8px}
    .parilte-home-join-action{display:flex;justify-content:flex-end}
    .parilte-home-join-inner small{
      font-size:.72rem;
      letter-spacing:.16em;
      text-transform:uppercase;
      opacity:.65;
    }
    .parilte-home-join-inner h3{
      margin:0;
      font-size:clamp(1rem,2.2vw,1.4rem);
      letter-spacing:.12em;
      text-transform:uppercase;
    }
    .parilte-home-join-inner p{
      margin:0;
      opacity:.7;
      font-size:.95rem;
    }
    /* Home layout overrides: full-bleed sections, smaller cards */
    .parilte-home-cats{padding:24px 0;background:#fff}
    .parilte-home-cats .parilte-home-cats-grid{
      gap:16px;
      border:0;
      padding:0 clamp(12px,4vw,24px);
    }
    .parilte-home-cats .parilte-home-cat{
      border:0;
      border-radius:16px;
      animation:none;
      transform:none;
    }
    .parilte-home-cats .parilte-home-cat img{
      height:clamp(200px,28vw,320px);
      object-fit:cover;
    }
    .parilte-home-cats .parilte-home-cat::after{height:38%}
    .parilte-home-modules{padding:24px 0;background:#fff}
    .parilte-home-modules-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:16px;
      padding:0 clamp(12px,4vw,24px);
    }
    .parilte-home-module{
      background:#fff;
      border:1px solid rgba(0,0,0,.08);
      border-radius:18px;
      padding:16px;
      display:flex;
      flex-direction:column;
      gap:10px;
      min-height:clamp(220px,28vw,340px);
    }
    .parilte-home-module img{border-radius:14px}
    .parilte-home-module small{opacity:.6;font-size:.72rem;text-transform:uppercase;letter-spacing:.12em}
    .parilte-home-module h3{margin:0;font-size:clamp(1rem,2.2vw,1.25rem);letter-spacing:.06em;text-transform:uppercase}
    .parilte-home-module p{margin:0;opacity:.7;font-size:.9rem}
    .parilte-home-module .parilte-home-cta-btn{margin-top:6px}
    .parilte-home-module.module-sale .parilte-home-cta-btn{align-self:flex-end;margin-top:8px}
    .parilte-home-module.module-new .parilte-home-cta-btn{align-self:flex-start;margin-top:22px}
    .parilte-home-module.module-account .parilte-home-cta-btn{align-self:center;margin-top:14px}
    .parilte-home-module.module-contact .parilte-home-cta-btn{align-self:flex-end;margin-top:30px}
    .parilte-home-promo{
      display:flex;
      flex-direction:column;
      gap:0;
      background:#fff;
    }
    .parilte-home-promo-row{
      display:grid;
      grid-template-columns:1.2fr 1fr;
      gap:0;
      border-top:1px solid rgba(0,0,0,.08);
    }
    .parilte-home-promo-row.reverse{grid-template-columns:1fr 1.2fr}
    .parilte-home-promo-copy{
      padding:clamp(18px,4.2vw,36px);
      display:flex;
      flex-direction:column;
      gap:10px;
      justify-content:center;
      text-transform:uppercase;
      letter-spacing:.08em;
    }
    .parilte-home-promo-copy small{opacity:.6;font-size:.72rem}
    .parilte-home-promo-copy h2{margin:0;font-size:clamp(1.1rem,2.6vw,1.6rem);letter-spacing:.12em}
    .parilte-home-promo-copy p{margin:0;opacity:.7;font-size:.9rem;text-transform:none;letter-spacing:.02em}
    .parilte-home-actions{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:0;
      border-top:1px solid rgba(0,0,0,.08);
      border-bottom:1px solid rgba(0,0,0,.08);
      background:#fff;
    }
    .parilte-home-action-card{
      padding:clamp(18px,4.2vw,32px);
      border-right:1px solid rgba(0,0,0,.08);
      display:flex;
      flex-direction:column;
      gap:10px;
      text-transform:uppercase;
      letter-spacing:.08em;
    }
    .parilte-home-action-card:last-child{border-right:0}
    .parilte-home-action-card h3{margin:0;font-size:clamp(1rem,2.2vw,1.3rem)}
    .parilte-home-action-card p{margin:0;opacity:.7;font-size:.9rem;text-transform:none;letter-spacing:.02em}
    .parilte-home-cats-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:0;
    }
    .parilte-home-cat{
      position:relative;
      display:block;
      text-decoration:none;
      color:inherit;
      overflow:hidden;
    }
    .parilte-home-cat-label{
      position:absolute;
      left:16px;
      bottom:14px;
      font-size:1rem;
      letter-spacing:.14em;
      text-transform:uppercase;
      color:#fff;
      z-index:3;
    }
    .parilte-home-strip{
      display:flex;
      flex-direction:column;
      gap:0;
    }
    .parilte-home-cats{padding:0;background:#fff}
    .parilte-home-cats .parilte-home-cats-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:0;
      padding:0;
      margin:0;
    }
    .parilte-home-cats .parilte-home-cats-grid.is-single{grid-template-columns:1fr}
    .parilte-home-cats .parilte-home-cat{
      position:relative;
      display:block;
      text-decoration:none;
      color:inherit;
      overflow:hidden;
      background:#f6f1ea;
      border:0;
      border-radius:0;
      animation:none;
      transform:none;
    }
    .parilte-home-cats .parilte-home-cat img{
      width:100%;
      height:clamp(220px,30vw,340px);
      object-fit:cover;
      object-position:50% 12%;
      background:transparent;
      display:block;
      position:relative;
      z-index:1;
    }
    .parilte-home-cat::after{
      content:"";
      position:absolute;
      inset:auto 0 0 0;
      height:32%;
      background:linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,.28));
      pointer-events:none;
      z-index:2;
    }
    .parilte-home-cat-label{
      position:absolute;
      left:16px;
      bottom:14px;
      font-size:1rem;
      letter-spacing:.14em;
      text-transform:uppercase;
      color:#fff;
      z-index:1;
    }
    .parilte-home-cta{padding:0;background:#fff}
    .parilte-home-cta-inner{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:0;
      border-bottom:1px solid rgba(0,0,0,.08);
    }
    .parilte-home-cta-card{
      padding:18px clamp(16px,4vw,32px);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      border-right:1px solid rgba(0,0,0,.08);
      font-size:.9rem;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
    .parilte-home-cta-card:last-child{border-right:0}
    .parilte-home-cta-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background:#c51d24;
      color:#fff;
      border-radius:999px;
      padding:6px 12px;
      font-size:.58rem;
      letter-spacing:.12em;
      text-transform:uppercase;
      text-decoration:none;
      box-shadow:0 10px 22px rgba(0,0,0,.18);
      white-space:nowrap;
      border:0;
      cursor:pointer;
      appearance:none;
      font:inherit;
      font-family:"Bodoni MT","Didot","Playfair Display","Times New Roman",serif;
      font-weight:500;
    }
    @media (max-width: 900px){
      .parilte-home-cats .parilte-home-cats-grid{grid-template-columns:1fr}
      .parilte-home-cats .parilte-home-cat{border-right:0}
      .parilte-home-cat-label{font-size:.9rem}
      .parilte-home-cats .parilte-home-cat img{height:clamp(220px,60vw,360px)}
      .parilte-home-banner .parilte-home-img{height:clamp(220px,60vw,360px)}
      .parilte-home-banner-content{padding:16px 18px}
      .parilte-home-contact-inner{padding:0 18px;grid-template-columns:1fr}
      .parilte-contact-actions{grid-column:1;grid-row:auto;justify-self:flex-start}
      .parilte-home-join-inner{padding:0 18px;grid-template-columns:1fr}
      .parilte-home-join-action{justify-content:flex-start}
      .parilte-home-cta-btn--sm{font-size:.6rem;padding:7px 12px}
      .parilte-home-cta-inner{grid-template-columns:1fr}
      .parilte-home-cta-card{border-right:0;border-top:1px solid rgba(0,0,0,.08)}
      .parilte-home-hero-cta .parilte-home-cta-btn{padding:14px 26px;font-size:.88rem;letter-spacing:.18em}
      .parilte-home-cta-btn{font-size:.66rem;padding:8px 14px}
      .parilte-contact-row{grid-template-columns:1fr}
      .parilte-home-hot-inner{padding:0 18px}
      .parilte-home-cats .parilte-home-cats-grid{grid-template-columns:1fr}
      .parilte-home-promo-row,
      .parilte-home-promo-row.reverse{grid-template-columns:1fr}
      .parilte-home-actions{grid-template-columns:1fr}
      .parilte-home-action-card{border-right:0;border-top:1px solid rgba(0,0,0,.08)}
      .parilte-home-modules-grid{grid-template-columns:1fr}
    }
    @media (prefers-reduced-motion: reduce){
      .parilte-home-cat{animation:none}
    }
    .parilte-mag-lookbook{min-height:clamp(320px,70vw,620px);background-size:cover;background-position:50% 12%;background-repeat:no-repeat;position:relative}
    .parilte-mag-strip{min-height:clamp(240px,60vw,520px);background-size:cover;background-position:50% 12%;background-repeat:no-repeat;position:relative}
    .parilte-mag-link{position:absolute;inset:0;display:block}
    :root{--ct-color-primary:#1c1c1c;--ct-color-primary-hover:#2b2b2b;--ct-link-color:#1c1c1c;--ct-link-hover-color:#2b2b2b}
    .parilte-front h1{font-weight:500;letter-spacing:.02em}
    .parilte-front h2{font-weight:500;letter-spacing:.14em;text-transform:uppercase;font-size:clamp(1.05rem,1.5vw,1.4rem)}
    .parilte-front h3{font-weight:500}
    .parilte-mag-story{padding:clamp(18px,4vw,42px) 0;background:#fff}
    .parilte-mag-story-grid{display:grid;gap:0;grid-template-columns:1fr}
    .parilte-mag-card{position:relative;border-radius:0;overflow:hidden;min-height:clamp(220px,52vw,520px);background-size:cover;background-position:50% 12%;background-repeat:no-repeat}
    .parilte-mag-stack{display:grid;gap:0}
    .parilte-mag-journal{padding:clamp(20px,5vw,52px) 0;background:#fff}
    .parilte-mag-journal-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
    .parilte-mag-journal-card{border-radius:16px;overflow:hidden;border:1px solid var(--parilte-border);background:#fff}
    .parilte-mag-journal-card img{width:100%;height:auto;display:block}
    .parilte-mag-rail{background:#fff;padding:8px 0}
    .parilte-mag-rail .products{display:flex;gap:10px;overflow-x:auto;padding:6px 10px 14px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch}
    .parilte-mag-rail .products::-webkit-scrollbar{height:0}
    .parilte-mag-rail .products li.product{flex:0 0 100%;scroll-snap-align:start;background:transparent;margin:0}
    .parilte-mag-rail .products li.product a{color:inherit;text-decoration:none}
    .parilte-mag-rail .products li.product img{border-radius:14px}
    .parilte-mag-rail .products li.product .woocommerce-loop-product__title,
    .parilte-mag-rail .products li.product .price{display:none}
    .parilte-mag-cat-media{padding:0;background:#fff}
    .parilte-mag-cat-media-grid{display:grid;gap:0;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
    .parilte-mag-cat-media-card{position:relative;min-height:clamp(200px,45vw,360px);border-radius:0;overflow:hidden;background-size:cover;background-position:center;background-repeat:no-repeat;box-shadow:none;text-decoration:none;color:#fff}
    .parilte-mag-cat-media-card::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.05),rgba(0,0,0,.55))}
    .parilte-mag-cat-media-card span{position:absolute;left:14px;bottom:12px;font-size:.9rem;letter-spacing:.12em;text-transform:uppercase}
    @media (min-width: 900px){
      .parilte-mag-hero-grid{grid-template-columns:1.05fr .95fr}
      .parilte-mag-story-grid{grid-template-columns:1.25fr .75fr}
      .parilte-mag-lookbook-grid{grid-template-columns:.9fr 1.1fr;align-items:center}
      .parilte-mag-hero-media{min-height:460px}
      .parilte-mag-rail .products li.product{flex:0 0 calc(50% - 6px)}
      .parilte-mag-cat-media-grid{grid-template-columns:repeat(4,1fr)}
    }
    .parilte-section-head h2{margin:0}
    .parilte-section-head a{font-size:.78rem;letter-spacing:.18em;text-transform:uppercase;color:var(--parilte-ink)}
    .woocommerce .woocommerce-products-header__title,
    .woocommerce .page-title,
    .woocommerce .ct-hero-title{
      font-family:var(--ct-heading-font-family, inherit);
      font-weight:500;
      letter-spacing:.28em;
      text-transform:uppercase;
      font-size:clamp(1.4rem,2.2vw,2.1rem);
    }
    .parilte-front .ct-button{background:var(--parilte-ink);color:#fff;border-radius:999px;padding:.75rem 1.6rem}
    .parilte-front .ct-button:hover{background:#2b2b2b}
    .woocommerce a.button, .woocommerce button.button, .woocommerce input.button{background:#1c1c1c;border-color:#1c1c1c;color:#fff}
    .woocommerce a.button:hover, .woocommerce button.button:hover, .woocommerce input.button:hover{background:#2b2b2b;border-color:#2b2b2b}
    .woocommerce span.onsale{display:none !important}
    .parilte-legal-footer{margin-top:28px;padding:18px 0;border-top:1px solid rgba(0,0,0,.08);background:var(--parilte-cream)}
    .parilte-legal-footer .parilte-container{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .parilte-legal-left{display:flex;flex-direction:column;gap:8px;min-width:0}
    .parilte-legal-right{display:flex;align-items:center;justify-content:flex-end;gap:12px;min-width:0;margin-left:auto}
    .parilte-legal-links{display:flex;flex-wrap:wrap;gap:14px;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase}
    .parilte-legal-links a{text-decoration:none;color:var(--parilte-ink);opacity:.8}
    .parilte-legal-links a:hover{opacity:1}
    .parilte-legal-whatsapp{display:flex;align-items:center;gap:10px;font-size:.76rem;letter-spacing:.12em;text-transform:uppercase}
    .parilte-legal-instagram{display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:#fff;color:#e1306c;text-decoration:none;box-shadow:0 8px 18px rgba(0,0,0,.08)}
    .parilte-legal-instagram svg{width:26px;height:26px;fill:currentColor}
    @media (min-width: 901px){
      .parilte-legal-footer .parilte-container{flex-wrap:nowrap}
      .parilte-legal-left{flex:1 1 auto}
    }
    @media (max-width: 900px){
      .parilte-legal-footer .parilte-container{flex-direction:column;align-items:flex-start}
      .parilte-legal-right{width:100%;justify-content:flex-start;margin-left:0}
    }
    .ct-footer-socials{display:none !important}
    .parilte-home-cta-btn--wa{background:#1fa855 !important;box-shadow:0 10px 22px rgba(31,168,85,.22) !important}
    .parilte-legal-contact{display:flex;flex-direction:column;gap:6px;font-size:.78rem;opacity:.85;max-width:420px}
    .parilte-legal-contact a{text-decoration:none;color:var(--parilte-ink)}
    .parilte-legal-copy{font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;opacity:.6}
    .parilte-text-link{display:inline-flex;align-items:center;gap:6px;text-decoration:none;color:var(--parilte-ink);opacity:.8}
    .parilte-text-link:hover{opacity:1}
    .parilte-hero-notes{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
    .parilte-note{border:1px solid var(--parilte-border);border-radius:14px;padding:10px 12px;background:#fff}
    .parilte-note strong{display:block;font-size:.85rem;color:var(--parilte-ink);margin-bottom:2px}
    .parilte-note span{font-size:.82rem;color:var(--parilte-muted)}
    .parilte-hero-visual{display:grid;gap:14px}
    .parilte-hero-photo{min-height:420px;border-radius:26px;background-size:cover;background-position:center;box-shadow:var(--parilte-shadow);border:1px solid var(--parilte-border)}
    .parilte-hero-stack{display:grid;gap:12px;grid-template-columns:1fr 1fr;margin-top:-60px}
    .parilte-hero-card{position:relative;min-height:140px;border-radius:18px;overflow:hidden;background-size:cover;background-position:center;display:flex;align-items:flex-end;padding:12px;border:1px solid rgba(255,255,255,.3);box-shadow:0 12px 28px rgba(0,0,0,.18)}
    .parilte-hero-card::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(0,0,0,.6) 100%)}
    .parilte-hero-card span{position:relative;color:#fff;font-size:.78rem;letter-spacing:.18em;text-transform:uppercase}
    .parilte-hero-card.small{min-height:120px;transform:translateY(8px)}
    .parilte-strip{padding:14px 0 22px}
      .parilte-strip-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
      .parilte-strip-grid div{border:1px solid var(--parilte-border);border-radius:14px;padding:12px;background:#fff}
      .parilte-strip-grid span{display:block;opacity:.7;font-size:.92rem;margin-top:4px;color:var(--parilte-muted)}
      .parilte-strip-grid div{animation:parilte-rise .6s ease both}
      .parilte-strip-grid div:nth-child(2){animation-delay:.06s}
      .parilte-strip-grid div:nth-child(3){animation-delay:.12s}
      .parilte-sticky-cart{position:fixed;left:0;right:0;bottom:0;display:none;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background:#fff;border-top:1px solid rgba(0,0,0,.12);z-index:9997}
      .parilte-sticky-meta{display:flex;flex-direction:column;gap:2px;font-size:.85rem}
      .parilte-sticky-price{font-weight:600}
      .parilte-sticky-note{opacity:.6;font-size:.75rem}
      .parilte-sticky-button{border:0;background:#111;color:#fff;border-radius:999px;padding:10px 16px;cursor:pointer}
      .parilte-sticky-button:disabled{opacity:.45;cursor:not-allowed}
      .parilte-editorial{padding:22px 0 10px;position:relative;overflow:hidden}
      .parilte-editorial-grid{display:grid;gap:18px;grid-template-columns:repeat(6,1fr);grid-auto-rows:160px}
      .parilte-editorial-card{position:relative;border-radius:22px;overflow:hidden;background-size:cover;background-position:center;min-height:160px;border:1px solid rgba(255,255,255,.2);box-shadow:0 18px 40px rgba(0,0,0,.12)}
      .parilte-editorial-card::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 35%,rgba(0,0,0,.65) 100%)}
      .parilte-editorial-card.large{grid-column:1/4;grid-row:1/3}
      .parilte-editorial-card.tall{grid-column:4/7;grid-row:1/4}
      .parilte-editorial-card.wide{grid-column:1/4;grid-row:3/4}
      .parilte-editorial-body{position:relative;z-index:2;color:#fff;padding:18px}
      .parilte-editorial-body h3{margin:6px 0 6px;font-size:1.15rem;font-weight:500;letter-spacing:.04em}
      .parilte-editorial-body p{margin:0;opacity:.85}
      .parilte-editorial .parilte-eyebrow{color:rgba(255,255,255,.75)}
      .parilte-feature{padding:18px 0 6px}
      .parilte-feature-grid{display:grid;gap:20px;grid-template-columns:1.1fr .9fr;align-items:center}
      .parilte-feature-copy{animation:parilte-rise .7s ease both}
      .parilte-feature-cards{animation:parilte-rise .7s ease .08s both}
    .parilte-feature-copy h2{margin:0 0 8px;color:var(--parilte-ink)}
    .parilte-feature-copy p{margin:0 0 12px;color:var(--parilte-muted)}
    .parilte-feature-cards{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
    .parilte-mini-card{border:1px solid var(--parilte-border);border-radius:14px;padding:12px;background:#fff}
    .parilte-mini-card strong{display:block;margin-bottom:4px;color:var(--parilte-ink)}
    .parilte-mini-card span{color:var(--parilte-muted);font-size:.9rem}
    .parilte-cats{padding:18px 0}
    .parilte-cats-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .parilte-cat-card{display:block;border-radius:18px;border:1px solid var(--parilte-border);padding:18px;text-decoration:none;background:#fff;transition:transform .2s ease, box-shadow .2s ease}
    .parilte-cat-card:hover{transform:translateY(-2px);box-shadow:0 16px 36px rgba(23,32,44,.1)}
    .parilte-lookbook{padding:20px 0 12px;position:relative;overflow:hidden}
    .parilte-cats{position:relative;overflow:hidden}
    .parilte-lookbook-grid{display:grid;gap:22px;grid-template-columns:1.05fr .95fr;align-items:center}
    .parilte-lookbook-copy h2{margin:0 0 8px;color:var(--parilte-ink)}
    .parilte-lookbook-copy p{margin:0 0 12px;color:var(--parilte-muted)}
      .parilte-lookbook-tiles{display:grid;gap:12px;grid-template-columns:1fr;grid-auto-rows:220px}
      .parilte-lookbook-photo{border-radius:20px;border:1px solid var(--parilte-border);background-size:cover;background-position:center;box-shadow:var(--parilte-shadow)}
      .parilte-lookbook-note{border-radius:18px;border:1px solid var(--parilte-border);padding:14px;background:#fff}
      .parilte-lookbook-note strong{display:block;margin-bottom:4px}
      .parilte-lookbook-note span{display:block;color:var(--parilte-muted)}
      .parilte-lookbook-tiles{animation:parilte-rise .8s ease both}
    .woocommerce .sidebar-woocommerce .count,
    .woocommerce .ct-sidebar .count,
    .woocommerce .woocommerce-sidebar .count,
    .woocommerce .widget-area .count{display:none}
    .woocommerce .sidebar-woocommerce .parilte-widget-toggle,
    .woocommerce .ct-sidebar .parilte-widget-toggle,
    .woocommerce .woocommerce-sidebar .parilte-widget-toggle,
    .woocommerce .widget-area .parilte-widget-toggle{cursor:pointer;display:flex;align-items:center;justify-content:space-between}
    .woocommerce .sidebar-woocommerce .parilte-widget-toggle::after,
    .woocommerce .ct-sidebar .parilte-widget-toggle::after,
    .woocommerce .woocommerce-sidebar .parilte-widget-toggle::after,
    .woocommerce .widget-area .parilte-widget-toggle::after{content:"+";font-size:.9rem;opacity:.6}
    .woocommerce .sidebar-woocommerce .parilte-widget-open .parilte-widget-toggle::after,
    .woocommerce .ct-sidebar .parilte-widget-open .parilte-widget-toggle::after,
    .woocommerce .woocommerce-sidebar .parilte-widget-open .parilte-widget-toggle::after,
    .woocommerce .widget-area .parilte-widget-open .parilte-widget-toggle::after{content:"–"}
    .woocommerce .sidebar-woocommerce .parilte-widget-body,
    .woocommerce .ct-sidebar .parilte-widget-body,
    .woocommerce .woocommerce-sidebar .parilte-widget-body,
    .woocommerce .widget-area .parilte-widget-body{margin-top:6px}
    .woocommerce .sidebar-woocommerce .parilte-widget-collapsed .parilte-widget-body,
    .woocommerce .ct-sidebar .parilte-widget-collapsed .parilte-widget-body,
    .woocommerce .woocommerce-sidebar .parilte-widget-collapsed .parilte-widget-body,
    .woocommerce .widget-area .parilte-widget-collapsed .parilte-widget-body{display:none}
    .woocommerce .product-categories,
    .woocommerce .wc-block-product-categories-list{list-style:none;margin:0;padding:0}
    .woocommerce .product-categories li,
    .woocommerce .wc-block-product-categories-list li{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid rgba(0,0,0,.06)}
    .woocommerce .product-categories li:last-child,
    .woocommerce .wc-block-product-categories-list li:last-child{border-bottom:0}
    .woocommerce .product-categories li a,
    .woocommerce .wc-block-product-categories-list li a{flex:1;text-decoration:none;color:inherit}
    .woocommerce .product-categories li > a,
    .woocommerce .wc-block-product-categories-list li > a{
      font-family:var(--ct-heading-font-family, inherit);
      font-weight:500;
      letter-spacing:.06em;
      font-size:.86rem;
    }
    .woocommerce .product-categories li ul li > a,
    .woocommerce .wc-block-product-categories-list li ul li > a{
      font-family:inherit;
      font-weight:400;
      letter-spacing:.02em;
      font-size:.82rem;
    }
    .woocommerce .product-categories li ul,
    .woocommerce .wc-block-product-categories-list li ul{flex-basis:100%;margin:6px 0 0 10px;padding-left:12px;border-left:1px solid rgba(0,0,0,.08)}
    .woocommerce .product-categories li ul li,
    .woocommerce .wc-block-product-categories-list li ul li{border:0;padding:4px 0}
    .woocommerce .widget_product_categories .widget-title,
    .woocommerce .wc-block-product-categories .widget-title,
    .woocommerce .wc-block-product-categories-list__label{display:none}
    .woocommerce .parilte-cat-toggle{width:18px;height:18px;border:1px solid rgba(0,0,0,.2);border-radius:999px;background:transparent;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
    .woocommerce .parilte-cat-toggle::before{content:"+";font-size:.7rem;opacity:.7}
    .woocommerce .parilte-cat-open > .parilte-cat-toggle::before{content:"–"}
    .woocommerce .wc-block-product-categories-list__item-count,
    .woocommerce .wc-block-product-categories-list-item-count,
    .woocommerce .product-categories .count,
    .woocommerce .product-categories .cat-item-count{display:none}
    .woocommerce .product-categories .parilte-cat-hidden,
    .woocommerce .wc-block-product-categories-list .parilte-cat-hidden{display:none}
    .woocommerce .product-categories.parilte-cat-expanded .parilte-cat-hidden,
    .woocommerce .wc-block-product-categories-list.parilte-cat-expanded .parilte-cat-hidden{display:flex}
    .parilte-sidebar-quick{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
    .parilte-cat-tree{margin-bottom:18px;padding:14px 12px;border:1px solid rgba(0,0,0,.08);border-radius:16px;background:rgba(255,255,255,.6)}
    .parilte-cat-tree-head{font-size:.78rem;letter-spacing:.22em;text-transform:uppercase;opacity:.9;margin-bottom:8px}
    .parilte-cat-tree-list{list-style:none;margin:0;padding:0;display:grid;gap:6px}
    .parilte-cat-tree-item{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
    .parilte-cat-tree-item > a{flex:1;text-decoration:none;color:inherit;font-family:var(--ct-heading-font-family, inherit);font-weight:600;letter-spacing:.08em;font-size:.9rem}
    .parilte-cat-tree-item.has-children > a{font-weight:650}
    .parilte-cat-tree-children{flex-basis:100%;margin:2px 0 6px 8px;padding-left:10px;border-left:1px solid rgba(0,0,0,.08)}
    .parilte-cat-tree-children .parilte-cat-tree-item > a{font-family:inherit;font-weight:500;letter-spacing:.04em;font-size:.84rem;opacity:.9}
    .parilte-cat-more{margin-top:10px;border:1px solid rgba(0,0,0,.12);border-radius:999px;padding:6px 12px;background:#fff;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}
    .parilte-offcanvas-cats{padding:14px 14px 6px}
    .parilte-offcanvas-cats .parilte-cat-tree{margin-bottom:12px;background:#fff}
    .parilte-offcanvas-cats .parilte-cat-tree-list{max-height:none}
    .parilte-offcanvas-cats .parilte-cat-tree-item{align-items:flex-start}
    .parilte-offcanvas-cats .parilte-cat-toggle{margin-left:auto}
    .ct-panel .ct-header-account,
    .ct-panel .ct-header-cart,
    .ct-panel .ct-account-item,
    .ct-panel .ct-cart-item{display:none !important}
    .woocommerce .widget_price_filter .price_slider_wrapper{margin-top:10px}
    .woocommerce .widget_price_filter .price_slider{height:6px;border-radius:999px;background:rgba(0,0,0,.12);position:relative}
    .woocommerce .widget_price_filter .ui-slider-range{background:var(--parilte-ink);border-radius:999px}
    .woocommerce .widget_price_filter .ui-slider-handle{
      width:16px;height:16px;top:-5px;border-radius:999px;background:#fff;border:1px solid rgba(0,0,0,.2);
      box-shadow:0 4px 10px rgba(0,0,0,.12);cursor:ew-resize
    }
    .woocommerce .widget_price_filter .price_slider_amount{display:flex;flex-direction:column;align-items:flex-start;gap:6px;margin-top:8px}
    .woocommerce .widget_price_filter .price_slider_amount .button{
      background:#1c1c1c;border-color:#1c1c1c;color:#fff;border-radius:999px;padding:6px 14px;font-size:.75rem;letter-spacing:.12em;text-transform:uppercase
    }
    .woocommerce .widget_price_filter .price_label{font-size:.82rem;letter-spacing:.06em;order:1}
    .woocommerce .widget_price_filter .price_slider_amount .button{order:2}
    .parilte-cat-card strong{display:block;margin-bottom:4px;color:var(--parilte-ink);font-family:var(--ct-heading-font-family, inherit);letter-spacing:.1em;text-transform:uppercase;font-size:.82rem}
    .parilte-cat-card span{opacity:.7;color:var(--parilte-muted)}
    .parilte-carousel{padding:18px 0 8px}
    .parilte-showcase-card{
      position:relative;border-radius:26px;padding:28px;border:1px solid rgba(0,0,0,.10);overflow:hidden;
      background:#f7f3ee;
      background-image:
        linear-gradient(90deg, rgba(0,0,0,.03) 1px, transparent 1px),
        linear-gradient(180deg, rgba(0,0,0,.03) 1px, transparent 1px);
      background-size:36px 36px;
      box-shadow:0 18px 32px rgba(0,0,0,.06)}
    .parilte-showcase-card::before{
      content:"";position:absolute;left:18px;right:18px;top:16px;height:1px;
      background:rgba(0,0,0,.06);pointer-events:none
    }
    .parilte-showcase-card::after{
      content:"";position:absolute;left:18px;top:16px;bottom:18px;width:1px;
      background:rgba(0,0,0,.06);pointer-events:none
    }
    .parilte-showcase-new{display:grid;grid-template-columns:minmax(240px,320px) 1fr;gap:24px;align-items:center}
    .parilte-showcase-best{display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:24px;align-items:stretch;
      background:#f5f1ec}
    .parilte-showcase-sale{display:grid;grid-template-columns:1fr;gap:16px;background:#f6f2ee}
    .parilte-showcase-copy h2{margin:6px 0 8px;letter-spacing:.12em;text-transform:uppercase;font-weight:500}
    .parilte-showcase-copy p{margin:0 0 16px;color:var(--parilte-muted);max-width:100%}
    .parilte-showcase-actions{display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
    .parilte-showcase-tags{display:flex;gap:8px;flex-wrap:wrap}
    .parilte-showcase-tags span{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;opacity:.6}
    .parilte-showcase-hero{border-radius:20px;min-height:260px;background-size:cover;background-position:center;position:relative;overflow:hidden}
    .parilte-showcase-hero::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.12),rgba(0,0,0,0) 45%,rgba(0,0,0,.25))}
    .parilte-showcase-hero-body{position:relative;z-index:1;color:#fff;padding:18px;display:flex;flex-direction:column;gap:8px}
    .parilte-showcase-hero-body h2{margin:0;color:#fff}
    .parilte-showcase-hero-body p{margin:0;color:rgba(255,255,255,.85)}
    .parilte-showcase-rail{position:relative}
    .parilte-chip-link{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;
      border:1px solid rgba(0,0,0,.15);background:rgba(255,255,255,.9);text-decoration:none;letter-spacing:.16em;text-transform:uppercase;
      font-size:.72rem;color:var(--parilte-ink)}
    .parilte-carousel-track{position:relative;overflow:hidden}
    .parilte-carousel-controls{position:absolute;top:10px;right:10px;display:flex;gap:8px;z-index:2}
    .parilte-carousel-controls button{width:34px;height:34px;border-radius:999px;border:1px solid rgba(0,0,0,.12);
      background:rgba(255,255,255,.9);cursor:pointer;display:flex;align-items:center;justify-content:center;
      font-size:18px;line-height:1;color:var(--parilte-ink);box-shadow:0 6px 16px rgba(0,0,0,.08);transition:transform .15s ease, box-shadow .15s ease}
    .parilte-carousel-controls button:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(0,0,0,.1)}
    .parilte-carousel-static .parilte-carousel-controls{display:none}
    .parilte-carousel-track::before,
    .parilte-carousel-track::after{display:none}
    .parilte-carousel-track .products{display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;margin:0;padding:8px 0 18px;list-style:none}
    .parilte-carousel-track .products::-webkit-scrollbar{height:6px}
    .parilte-carousel-track .products::-webkit-scrollbar-thumb{background:rgba(0,0,0,.12);border-radius:999px}
    .parilte-carousel-track .products li.product{flex:0 0 calc((100% - 60px)/4);scroll-snap-align:start;margin:0 !important;background:#fff;border-radius:18px;padding:10px 10px 12px;box-shadow:0 10px 24px rgba(0,0,0,.06)}
    .parilte-carousel-track .products li.product:nth-child(3n){margin-top:14px !important}
    .parilte-carousel-track .products li.product:nth-child(4n){margin-top:6px !important}
    .parilte-carousel-track .products li.product .woocommerce-LoopProduct-link{display:block}
    .parilte-carousel-track .products li.product img{border-radius:14px;aspect-ratio:3/4;object-fit:cover;background:#f1ede7}
    .parilte-carousel-track .products li.product .woocommerce-loop-product__title{
      margin-top:10px;font-size:.95rem;letter-spacing:.04em;font-weight:500;
      display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
      overflow-wrap:anywhere;word-break:break-word;line-height:1.35;min-height:2.7em;
    }
    .parilte-carousel-track .products li.product .price{display:block;margin-top:4px;font-weight:600;font-size:.95rem}
    /* Product cards: consistent image ratio + text clamp */
    .woocommerce ul.products li.product .woocommerce-LoopProduct-link img,
    .woocommerce ul.products li.product img{
      width:100%;
      aspect-ratio:3/4;
      object-fit:cover;
      background:#f1ede7;
    }
    .woocommerce ul.products li.product .woocommerce-loop-product__title{
      display:-webkit-box;
      -webkit-line-clamp:2;
      -webkit-box-orient:vertical;
      overflow:hidden;
      overflow-wrap:anywhere;
      word-break:break-word;
      line-height:1.35;
      min-height:2.7em;
    }
    .woocommerce ul.products li.product .star-rating{display:none}
    .woocommerce ul.products li.product .button,
    .woocommerce ul.products li.product .add_to_cart_button,
    .woocommerce ul.products li.product .ct-cart-button{display:none !important}
    .parilte-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
    .parilte-section-head a{text-decoration:none;opacity:.8}
    .parilte-blog{padding:18px 0 56px}
    .parilte-blog-grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
    .parilte-post{border:1px solid var(--parilte-border);border-radius:16px;overflow:hidden;background:#fff}
    .parilte-post a{text-decoration:none;color:inherit;display:block}
    .parilte-post-body{padding:12px 14px}
      .parilte-post-body h3{margin:.2rem 0 0;font-size:1.05rem}
      .parilte-post-body time{opacity:.7;font-size:.9rem}
      .parilte-single-opts{margin:14px 0 18px;display:grid;gap:10px}
      .parilte-opt-row{display:grid;gap:8px}
      .parilte-opt-label{font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;opacity:.65}
      .parilte-opt-list{display:flex;flex-wrap:wrap;gap:8px}
      .parilte-opt{border:1px solid rgba(0,0,0,.12);border-radius:999px;padding:4px 10px;font-size:.85rem;line-height:1.2}
      .parilte-opt.on{background:#111;color:#fff;border-color:#111}
      .parilte-opt.off{opacity:.35;text-decoration:line-through}
      .parilte-opt.color{display:inline-flex;align-items:center;gap:6px}
      .parilte-opt.color .dot{width:12px;height:12px;border-radius:999px;display:inline-block;background:var(--chip-color,#ddd);border:1px solid rgba(0,0,0,.08)}
      @media (max-width: 1100px){
        .parilte-carousel-track .products li.product{flex-basis:calc((100% - 40px)/3)}
      }
      @media (max-width: 900px){
        .parilte-hero-grid,
        .parilte-feature-grid,
        .parilte-lookbook-grid{grid-template-columns:1fr}
        .parilte-hero-stack{grid-template-columns:1fr}
        .parilte-editorial-grid{grid-template-columns:1fr;grid-auto-rows:auto}
        .parilte-editorial-card.large,
        .parilte-editorial-card.tall,
        .parilte-editorial-card.wide{grid-column:auto;grid-row:auto;min-height:220px}
        .parilte-showcase-new,
        .parilte-showcase-best{grid-template-columns:1fr}
        .parilte-showcase-card{padding:clamp(14px,3.5vw,22px);background-size:28px 28px}
        .parilte-showcase-copy h2{font-size:clamp(1.1rem,4.5vw,1.35rem)}
        .parilte-showcase-copy p{font-size:clamp(.88rem,3.4vw,1rem)}
        .parilte-showcase-hero{min-height:clamp(180px,60vw,260px)}
        .parilte-showcase-actions{gap:10px}
        .parilte-showcase-tags{gap:6px}
        .parilte-carousel-track .products li.product{flex-basis:calc(100% - 12px)}
        .parilte-header-icons{flex-wrap:wrap;gap:10px}
        .parilte-search-form{width:100%}
        .parilte-search-input{width:100%;max-width:none}
        .ct-header .menu .parilte-menu-tools{margin-left:0}
        .woocommerce .woocommerce-ordering,
        .woocommerce .woocommerce-result-count{float:none;width:100%}
        .woocommerce .woocommerce-ordering{margin:6px 0 12px}
        .parilte-sidebar-quick{gap:6px}
        .parilte-cat-tree{padding:10px}
        .parilte-header-cats{display:none}
        .parilte-mobile-menu-toggle{display:inline-flex}
        .parilte-showcase-tags{display:none}
        .woocommerce ul.products li.product .product-category,
        .woocommerce ul.products li.product .product-categories,
        .woocommerce ul.products li.product .posted_in,
        .woocommerce ul.products li.product .woocommerce-loop-product__category{display:none}
      }
      @media (max-width: 768px){
        body{overflow-x:hidden}
        .parilte-container,
        .ct-container{padding-left:clamp(12px,4vw,20px);padding-right:clamp(12px,4vw,20px)}
        .parilte-sticky-cart{display:flex}
        .single-product .site-main{padding-bottom:90px}
        .ct-header .ct-container,
        .ct-header .ct-row{flex-wrap:wrap;flex-direction:column;align-items:center;gap:clamp(6px,2.5vw,10px)}
        .ct-header .ct-container > *{flex:1 1 100%}
        .ct-header .site-branding{width:100%;text-align:center}
        .site-title,
        .site-title a{letter-spacing:clamp(.12rem,.9vw,.22rem);font-size:clamp(1.3rem,6.2vw,1.9rem);white-space:nowrap}
        .site-branding .site-title,
        .site-branding .site-title a{white-space:nowrap}
        .ct-header .menu{width:100%;justify-content:center;flex-wrap:wrap;gap:8px}
        .ct-header .menu > li{flex:0 0 auto}
        .ct-header .menu .parilte-menu-tools{width:100%;justify-content:center;margin-left:0}
        .parilte-hero{padding:clamp(24px,6vh,48px) 0}
        .parilte-hero-actions{flex-direction:column;align-items:flex-start}
        .parilte-hero-visual{margin-top:16px}
        .parilte-hero-photo{min-height:clamp(220px,60vw,320px)}
        .parilte-strip-grid{grid-template-columns:1fr;gap:10px}
        .parilte-strip-grid div{padding:clamp(10px,3.2vw,16px)}
        .parilte-cats-grid{grid-template-columns:1fr}
        .parilte-lookbook-grid{grid-template-columns:1fr}
        .parilte-showcase-rail .products{padding-bottom:12px}
        .woocommerce .sidebar-woocommerce,
        .woocommerce .ct-sidebar,
        .woocommerce .woocommerce-sidebar{width:100%;margin-bottom:16px}
        .woocommerce ul.products{margin:0}
        .woocommerce ul.products li.product{float:none;width:100% !important;margin:0 0 18px !important}
        .woocommerce .woocommerce-ordering select{width:100%}
        .parilte-cat-tree{margin-bottom:12px}
        .parilte-carousel-track .products{gap:12px}
        .parilte-carousel-track .products li.product{max-width:calc(100vw - 2 * clamp(12px,4vw,20px));margin-left:auto;margin-right:auto}
      .ct-offcanvas-container{background:rgba(245,241,236,.98)}
      .ct-offcanvas-overlay{background:rgba(0,0,0,.35)}
      .ct-offcanvas-container,
      .ct-panel{height:100vh}
      .ct-panel{max-width:100vw;width:100vw;overflow:hidden}
      #offcanvas.ct-panel{height:100vh}
      #offcanvas .ct-panel-inner{height:100%}
      #offcanvas .ct-panel-content,
      #offcanvas .ct-panel-content[data-device="mobile"],
      #offcanvas .ct-panel-content-inner{
        padding:16px;
        height:100%;
        max-height:100vh;
        overflow-y:auto;
        -webkit-overflow-scrolling:touch;
        overscroll-behavior:contain;
      }
      .ct-panel{overflow:hidden}
      }
      @media (max-width: 560px){
        .parilte-carousel-track .products li.product{flex-basis:100%}
        .parilte-header-icons{font-size:clamp(.58rem,2.6vw,.7rem);letter-spacing:.1em;justify-content:center}
        .parilte-cart-count{min-width:14px;height:14px;font-size:.58rem}
        .parilte-showcase-card{padding:clamp(12px,4vw,20px);border-radius:22px;background-size:26px 26px}
        .parilte-showcase-hero{min-height:clamp(160px,60vw,240px);border-radius:18px}
        .parilte-showcase-copy h2{font-size:clamp(1rem,4.8vw,1.25rem)}
        .parilte-showcase-copy p{font-size:clamp(.86rem,3.6vw,.98rem)}
        .parilte-showcase-copy p{max-width:none}
        .parilte-chip-link{padding:clamp(6px,2.6vw,10px) clamp(10px,4vw,16px);font-size:clamp(.62rem,2.4vw,.78rem)}
        .parilte-cat-tree-item > a{font-size:.86rem}
        .parilte-cat-tree-children .parilte-cat-tree-item > a{font-size:.82rem}
        .woocommerce ul.products[class*="columns-"] li.product{width:100%}
        .parilte-carousel-track .products li.product img{aspect-ratio:4/5;max-height:clamp(260px,60vh,420px)}
      }
      @media (prefers-reduced-motion: reduce){
        .parilte-hero-copy,
        .parilte-hero-visual,
        .parilte-hero-notes,
        .parilte-strip-grid div,
        .parilte-feature-copy,
        .parilte-feature-cards,
        .parilte-lookbook-tiles{animation:none}
      }

      /* Mobile-first overrides (desktop is secondary) */
      .parilte-container{max-width:100%;padding-left:clamp(12px,4vw,20px);padding-right:clamp(12px,4vw,20px)}
      .parilte-hero-grid,
      .parilte-feature-grid,
      .parilte-lookbook-grid,
      .parilte-editorial-grid,
      .parilte-showcase-new,
      .parilte-showcase-best{grid-template-columns:1fr !important}
      .parilte-hero-copy h1{font-size:clamp(1.45rem,6vw,2.1rem)}
      .parilte-hero-copy p{max-width:100%;font-size:clamp(.92rem,3.8vw,1.05rem)}
      .parilte-hero-photo{min-height:clamp(220px,62vw,380px)}
      .parilte-hero-stack{grid-template-columns:1fr;margin-top:12px}
      .parilte-hero-card{min-height:clamp(120px,34vw,160px)}
      .parilte-strip-grid{grid-template-columns:1fr}
      .parilte-editorial-card.large,
      .parilte-editorial-card.tall,
      .parilte-editorial-card.wide{grid-column:auto;grid-row:auto;min-height:clamp(180px,52vw,240px)}
      .parilte-showcase-card{padding:clamp(14px,4.4vw,22px);border-radius:22px}
      .parilte-showcase-copy h2{font-size:clamp(1.05rem,4.6vw,1.4rem);letter-spacing:.08em}
      .parilte-showcase-copy p{font-size:clamp(.9rem,3.6vw,1rem)}
      .parilte-showcase-actions{gap:10px}
      .parilte-showcase-hero{min-height:clamp(180px,58vw,280px);border-radius:18px}
      .parilte-showcase-hero-body{padding:14px}
      .parilte-carousel-track .products{gap:12px;padding-bottom:16px}
      .parilte-carousel-track .products li.product{
        flex:0 0 86%;
        max-width:86%;
        margin-left:auto;
        margin-right:auto;
      }
      .parilte-carousel-track .products li.product img{aspect-ratio:4/5;max-height:clamp(240px,60vh,420px)}
      .parilte-carousel-track .products li.product .woocommerce-loop-product__title{font-size:clamp(.84rem,3.6vw,.95rem)}
      .parilte-lookbook-tiles{grid-auto-rows:clamp(180px,46vw,240px)}
      .parilte-cats-grid{grid-template-columns:1fr}
      .parilte-cat-card{padding:16px}
      .parilte-section-head{flex-direction:column;align-items:flex-start}
      .parilte-section-head h2{font-size:clamp(1rem,4.4vw,1.3rem)}

      .ct-header .ct-container,
      .ct-header .ct-row{flex-wrap:wrap;flex-direction:column;align-items:center;gap:clamp(6px,2.5vw,10px)}
      .ct-header .site-branding{text-align:center}
      .site-title,
      .site-title a{font-size:clamp(1.1rem,6.2vw,1.7rem);letter-spacing:.08em;white-space:normal}
      .parilte-header-icons{flex-wrap:wrap;justify-content:center;gap:10px}
      .parilte-search-form{width:100%}
      .parilte-search-input{width:100%;max-width:none}

      /* Showcase panels: force mobile-safe layout */
      .parilte-showcase-card{overflow:hidden}
      .parilte-showcase-new,
      .parilte-showcase-best,
      .parilte-showcase-sale{display:flex;flex-direction:column;gap:16px}
      .parilte-showcase-copy,
      .parilte-showcase-rail,
      .parilte-showcase-hero{min-width:0;max-width:100%}
      .parilte-showcase-copy{overflow-wrap:anywhere;word-break:break-word;hyphens:auto}
      .parilte-showcase-hero{width:100%}
      .parilte-showcase-hero-body{max-width:100%}
      .parilte-carousel-track .products{padding-left:2px;padding-right:2px}
      .parilte-carousel-track .products li.product{
        flex:0 0 86vw !important;
        max-width:86vw !important;
      }
      .parilte-carousel-track .products li.product:nth-child(3n),
      .parilte-carousel-track .products li.product:nth-child(4n){margin-top:0 !important}

      /* Desktop overrides (restore multi-column layout) */
      @media (min-width: 1024px){
        .parilte-container{max-width:1140px}
        .site-title,
        .site-title a{
          font-size:clamp(2.2rem,2.6vw,2.9rem);
          letter-spacing:.38rem;
          white-space:nowrap;
        }
        .parilte-hero-grid{grid-template-columns:1.1fr .9fr}
        .parilte-hero-copy h1{font-size:clamp(2rem,2.6vw,2.8rem)}
        .parilte-hero-photo{min-height:420px}
        .parilte-hero-stack{grid-template-columns:1fr 1fr;margin-top:-60px}
        .parilte-mag-hero,
        .parilte-mag-lookbook,
        .parilte-mag-strip,
        .parilte-mag-card{background-position:50% 22%}
        .parilte-strip-grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
        .parilte-editorial-grid{grid-template-columns:repeat(6,1fr);grid-auto-rows:160px}
        .parilte-editorial-card.large{grid-column:1/4;grid-row:1/3}
        .parilte-editorial-card.tall{grid-column:4/7;grid-row:1/4}
        .parilte-editorial-card.wide{grid-column:1/4;grid-row:3/4}
        .parilte-feature-grid{grid-template-columns:1.1fr .9fr}
        .parilte-lookbook-grid{grid-template-columns:1.05fr .95fr}
        .parilte-showcase-new{display:grid;grid-template-columns:minmax(240px,320px) 1fr}
        .parilte-showcase-best{display:grid;grid-template-columns:minmax(260px,360px) 1fr}
        .parilte-showcase-sale{display:grid;grid-template-columns:1fr}
        .parilte-section-head{flex-direction:row;align-items:center}
        .parilte-cats-grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
        .parilte-carousel-track .products{gap:20px;padding:8px 0 18px}
        .parilte-carousel-track .products li.product{
          flex:0 0 calc((100% - 60px)/4) !important;
          max-width:calc((100% - 60px)/4) !important;
        }
        .parilte-carousel-track .products li.product img{aspect-ratio:3/4;max-height:none}
        .parilte-showcase-card{padding:26px;border-radius:30px}
      }
      ';
    wp_add_inline_style('parilte-checkout-suite', $css);
}, 22);

// Shop + header overrides (mobile-first)
add_action('wp_enqueue_scripts', function () {
    $css = '
    body,
    .site{
      background-color:#fbfbf9;
      background-image:
        radial-gradient(rgba(0,0,0,.018) 1px, transparent 1px),
        radial-gradient(rgba(0,0,0,.012) 1px, transparent 1px);
      background-size:24px 24px, 36px 36px;
      background-position:0 0, 12px 12px;
    }
    .ct-header{display:none !important}
    #offcanvas,
    .ct-drawer-canvas,
    .ct-panel.ct-header{display:none !important}
    .parilte-custom-header{
      position:sticky;
      top:0;
      z-index:10000;
      background:#fff;
      border-bottom:1px solid rgba(0,0,0,.08);
    }
    .parilte-custom-header{--parilte-header-h:64px}
    body.admin-bar .parilte-custom-header{top:32px}
    @media (max-width: 782px){
      body.admin-bar .parilte-custom-header{top:46px}
    }
    .parilte-custom-inner{
      display:grid;
      grid-template-columns:auto minmax(160px,320px) auto;
      grid-template-areas:"brand search tools";
      align-items:center;
      gap:12px;
      padding:12px clamp(12px,2.5vw,24px);
    }
    .parilte-custom-left{grid-area:brand;justify-self:start;align-self:center;display:flex;align-items:center;gap:10px}
    .parilte-custom-brand{display:none}
    .parilte-custom-search{grid-area:search;display:flex;align-items:center;justify-content:center;justify-self:center;gap:10px}
    .parilte-custom-right{grid-area:tools;justify-self:end;align-self:center;display:flex;align-items:center;gap:18px;white-space:nowrap}
    .parilte-brand{
      text-decoration:none;
      color:inherit;
      font-family:var(--ct-heading-font-family, inherit);
      letter-spacing:.22em;
      font-size:clamp(3.0rem,4.4vw,4.8rem);
      font-weight:600;
      white-space:nowrap;
    }
    .parilte-custom-header button,
    .parilte-custom-right a,
    .parilte-custom-right button{
      display:inline-flex;
      align-items:center;
      gap:6px;
      text-decoration:none;
      color:inherit;
      font:inherit;
      letter-spacing:.14em;
      text-transform:uppercase;
      font-size:.78rem;
      background:transparent;
      border:0;
      cursor:pointer;
    }
    .parilte-custom-header svg{fill:currentColor;opacity:.75;width:16px;height:16px}
    .parilte-custom-right svg{width:18px;height:18px;opacity:.85}
    .parilte-custom-right .parilte-label{display:inline}
    .parilte-custom-header .parilte-search-form{
      width:min(240px, 100%);
      padding:.35rem .6rem;
      background:rgba(255,255,255,.92);
      border:1px solid rgba(0,0,0,.12);
      border-radius:999px;
      box-shadow:0 1px 3px rgba(0,0,0,.04);
      display:flex;
      align-items:center;
      gap:8px;
    }
    .parilte-custom-header .parilte-search-input{
      width:100%;
      padding:.4rem .5rem;
      border:0;
      background:transparent;
      font:inherit;
      text-align:left;
      font-size:.72rem;
    }
    .parilte-custom-header .parilte-search-button{padding:.2rem .4rem}
    .parilte-custom-header .parilte-mobile-menu-toggle{display:inline-flex}
    .parilte-custom-left .parilte-mobile-menu-toggle{font-size:.8rem}
    body.parilte-mobile-open .parilte-mobile-drawer{display:block !important}
    @media (max-width: 1200px){
      .parilte-custom-right{gap:12px}
      .parilte-custom-right .parilte-label{display:inline}
      .parilte-custom-inner{grid-template-columns:auto minmax(140px,260px) auto}
      .parilte-custom-header .parilte-search-form{width:min(220px, 100%)}
    }
    @media (min-width: 901px){
      .parilte-mobile-menu-toggle{display:none}
    }
    @media (max-width: 900px){
      .parilte-custom-inner{
        grid-template-columns:auto 1fr auto;
        grid-template-areas:
          "brand brand tools"
          "search search search";
        align-items:center;
        padding-left:clamp(12px,2.5vw,24px);
      }
      .parilte-custom-left{justify-self:start}
      .parilte-custom-search{justify-content:center}
      .parilte-custom-right{justify-content:flex-end;gap:12px}
      .parilte-custom-header .parilte-search-form{max-width:100%}
      .parilte-label{display:none}
    }
    @media (max-width: 600px){
      .parilte-brand{font-size:clamp(2.2rem,7.4vw,2.8rem)}
      .parilte-custom-header .parilte-search-form{width:100%}
    }
    .parilte-mobile-drawer{z-index:10050}
    .parilte-mobile-panel{padding-top:calc(12px + var(--parilte-header-h,64px))}
    .parilte-mobile-drawer{
      font-family:var(--ct-text-font-family, inherit);
      color:var(--ct-text-color, #111);
    }
    .parilte-mobile-backdrop{background:rgba(0,0,0,.35)}
    .parilte-mobile-panel{
      background:#fbfbf9;
      border-right:1px solid rgba(0,0,0,.08);
      box-shadow:0 18px 40px rgba(0,0,0,.12);
    }
    .parilte-mobile-header{
      font-weight:600;
      letter-spacing:.18em;
      text-transform:uppercase;
    }
    .parilte-mobile-links a,
    .parilte-mobile-cats a{
      text-decoration:none;
      color:inherit;
      letter-spacing:.12em;
      text-transform:uppercase;
      font-size:.78rem;
    }
    .parilte-mobile-cats .parilte-cat-tree-list{gap:6px}
    .parilte-mobile-cats .parilte-cat-tree-item{padding:4px 0}
    .parilte-mobile-cats .parilte-cat-tree-children{margin-left:10px}
    .parilte-mobile-close{font-size:20px}
    .ct-header .ct-container{position:relative;overflow:visible}
    /* Hero CTA placement (avoid face overlap) */
    .parilte-mag-hero-overlay{
      align-items:flex-end;
      justify-content:flex-start;
      padding:clamp(16px,4vw,36px);
    }
    .single-product .summary{
      --parilte-single-font: clamp(.96rem, 2.6vw, 1.05rem);
      font-size:var(--parilte-single-font);
      line-height:1.55;
    }
    .single-product .summary .product_title{
      font-size:clamp(1.6rem, 4.6vw, 2.3rem);
      letter-spacing:.04em;
    }
    .single-product .summary .price{
      font-size:clamp(1.2rem, 3.4vw, 1.6rem);
      margin-top:6px;
    }
    .single-product .summary .woocommerce-product-details__short-description,
    .single-product .summary .product_meta,
    .single-product .summary .variations,
    .single-product .summary .variations label,
    .single-product .summary .woocommerce-variation,
    .single-product .summary .woocommerce-variation-price,
    .single-product .summary .woocommerce-variation-availability,
    .single-product .summary .stock,
    .single-product .summary .parilte-single-opts,
    .single-product .summary .parilte-size-box,
    .single-product .summary .parilte-size-box summary{
      font-size:var(--parilte-single-font);
    }
    .single-product .summary .parilte-single-opts{
      display:grid !important;
      opacity:1;
      visibility:visible;
    }
    .single-product .summary .parilte-opt{font-size:var(--parilte-single-font)}

    .woocommerce #secondary,
    .woocommerce .ct-sidebar,
    .woocommerce .woocommerce-sidebar{display:none !important}
    .woocommerce #primary{width:100%}

    .parilte-shop-cats{padding:10px 0;background:#fff;border-bottom:1px solid rgba(0,0,0,.06)}
    .parilte-shop-cats-row{display:flex;gap:10px;flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch}
    .parilte-shop-cats-row::-webkit-scrollbar{height:0}
    .parilte-shop-cat{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:999px;border:1px solid rgba(0,0,0,.12);text-decoration:none;color:#111;letter-spacing:.14em;text-transform:uppercase;font-size:.68rem;white-space:nowrap}
    .parilte-shop-cat.is-active{background:#111;color:#fff;border-color:#111}

    .woocommerce.archive .ct-container,
    .woocommerce.archive .ct-content,
    .woocommerce.archive .ct-row,
    .woocommerce.archive .content-area,
    .woocommerce.archive #primary,
    .woocommerce.archive .site-main{max-width:none !important;width:100% !important}
    .woocommerce.archive .ct-container{
      padding-left:clamp(12px,2.5vw,24px) !important;
      padding-right:clamp(12px,2.5vw,24px) !important;
      margin-left:0 !important;
      margin-right:0 !important;
    }
    .woocommerce.archive .ct-content{padding-top:0 !important;margin:0 !important}
    .woocommerce.archive .content-area{margin:0 !important}
    .woocommerce.archive .ct-content,
    .woocommerce.archive .ct-row{
      grid-template-columns:minmax(0,1fr) !important;
    }
    .woocommerce.archive .ct-hero-section,
    .woocommerce.archive .ct-hero,
    .woocommerce.archive .ct-page-title,
    .woocommerce.archive .ct-hero-title,
    .woocommerce.archive .page-title,
    .woocommerce.archive .woocommerce-products-header{display:none}
    .woocommerce.archive .ct-content{padding-top:0}

    .woocommerce ul.products{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:clamp(10px,1.4vw,18px);
      margin:0;
      padding:0;
      width:100%;
    }
    .woocommerce ul.products li.product{float:none;width:100% !important;margin:0 !important;position:relative;background:transparent;box-shadow:none}
    .woocommerce ul.products li.product img{width:100%;height:auto;aspect-ratio:4/5;object-fit:cover}
    .woocommerce ul.products li.product .button,
    .woocommerce ul.products li.product .add_to_cart_button,
    .woocommerce ul.products li.product .ct-cart-button,
    .woocommerce ul.products li.product .star-rating{display:none !important}
    .woocommerce ul.products li.product .woocommerce-loop-product__title{font-weight:600;letter-spacing:.02em}
    .woocommerce ul.products li.product .price{margin-top:6px}
    .woocommerce ul.products li.product .price ins{color:#c51d24}
    .woocommerce ul.products li.product .price del{color:#6b7280}
    .parilte-discount{
      position:absolute;
      top:10px;
      right:10px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:4px 8px;
      border-radius:999px;
      background:#c51d24;
      color:#fff;
      font-size:.72rem;
      letter-spacing:.08em;
      box-shadow:0 8px 16px rgba(0,0,0,.18);
      z-index:6;
    }
    .woocommerce ul.products li.product .woocommerce-LoopProduct-link{position:relative;display:block}
    .parilte-loop-media{position:relative;overflow:hidden;display:block}
    .parilte-loop-attrs{position:absolute;left:0;right:0;bottom:0;background:#fff;
      padding:10px 12px;border-top:1px solid rgba(0,0,0,.12);
      display:flex !important;flex-direction:column;gap:4px;opacity:0;transform:translateY(6px);
      transition:all .2s ease;pointer-events:none;z-index:2}
    .parilte-loop-attrs span{font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;opacity:.6}
    .parilte-loop-attrs strong{font-size:.78rem;font-weight:600;letter-spacing:.04em}
    .woocommerce ul.products li.product:hover .parilte-loop-attrs,
    .woocommerce ul.products li.product:focus-within .parilte-loop-attrs,
    .woocommerce ul.products li.product .woocommerce-LoopProduct-link:hover .parilte-loop-attrs,
    .parilte-loop-media:hover .parilte-loop-attrs{
      opacity:1 !important;
      transform:translateY(0) !important;
    }
    .parilte-fav-btn{
      margin-top:8px;
      border:1px solid rgba(0,0,0,.12);
      border-radius:999px;
      background:#fff;
      color:#111;
      padding:6px 12px;
      font-size:.7rem;
      letter-spacing:.14em;
      text-transform:uppercase;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      text-decoration:none;
    }
    .parilte-fav-btn.is-active{
      background:#111;
      color:#fff;
      border-color:#111;
    }
    .parilte-fav-single{margin-top:12px}
    @media (max-width: 600px){
      .parilte-fav-single{width:100%;justify-content:center}
    }

    .woocommerce-account .woocommerce-MyAccount-navigation a,
    .woocommerce-account .woocommerce-MyAccount-content,
    .woocommerce-account .woocommerce-MyAccount-content p,
    .woocommerce-account .woocommerce-MyAccount-content li{
      font-family:"Montserrat","Helvetica Neue",Arial,sans-serif;
      font-size:.92rem;
      letter-spacing:.02em;
    }
    .woocommerce-account .woocommerce-MyAccount-navigation a{
      text-transform:uppercase;
      letter-spacing:.14em;
      font-size:.72rem;
    }
    .woocommerce-account .woocommerce-MyAccount-content h2,
    .woocommerce-account .woocommerce-MyAccount-content h3{
      font-family:"Bodoni MT","Didot","Playfair Display","Times New Roman",serif;
      letter-spacing:.08em;
      text-transform:uppercase;
    }
    @media (min-width: 700px){
      .woocommerce ul.products{grid-template-columns:repeat(3,minmax(0,1fr))}
    }
    @media (min-width: 1024px){
      .woocommerce ul.products{grid-template-columns:repeat(4,minmax(0,1fr))}
    }
    @media (min-width: 1400px){
      .woocommerce ul.products{grid-template-columns:repeat(4,minmax(0,1fr))}
    }
    @media (max-width: 600px){
      .woocommerce ul.products{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
      .woocommerce ul.products li.product img{aspect-ratio:3/4}
      .parilte-loop-attrs{display:none}
    }
    ';
    wp_add_inline_style('parilte-checkout-suite', $css);
}, 23);

add_action('wp_enqueue_scripts', function () {
    if (!is_shop() && !is_product_taxonomy() && !is_product_category() && !is_product_tag()) return;
    wp_enqueue_script('jquery-ui-slider');
    wp_enqueue_script('wc-price-slider');
}, 25);

add_action('wp_enqueue_scripts', function () {
    if (!is_shop() && !is_product() && !is_product_taxonomy() && !is_product_category() && !is_product_tag()) return;
    wp_register_script('parilte-favorites', '', [], null, true);
    wp_enqueue_script('parilte-favorites');
    $ajax_url = admin_url('admin-ajax.php');
    $login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/hesabim/');
    $script = "window.parilteFav={ajaxUrl:'".esc_url_raw($ajax_url)."',loginUrl:'".esc_url_raw($login_url)."'};\n".
    "document.addEventListener('click',function(e){\n".
    "  var btn=e.target.closest('.parilte-fav-btn');\n".
    "  if(!btn||!btn.dataset.product){return;}\n".
    "  e.preventDefault();\n".
    "  var fd=new FormData();\n".
    "  fd.append('action','parilte_toggle_favorite');\n".
    "  fd.append('product_id',btn.dataset.product);\n".
    "  fd.append('nonce',btn.dataset.nonce);\n".
    "  fetch(window.parilteFav.ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(data){\n".
    "    if(!data||!data.success){\n".
    "      if(data&&data.data&&data.data.login){window.location=window.parilteFav.loginUrl;}\n".
    "      return;\n".
    "    }\n".
    "    if(data.data&&data.data.active){btn.classList.add('is-active');btn.textContent='Favoride';}\n".
    "    else{btn.classList.remove('is-active');btn.textContent='Favorilere Ekle';}\n".
    "  });\n".
    "});";
    wp_add_inline_script('parilte-favorites', $script);
}, 26);

add_action('wp_enqueue_scripts', function () {
    if (!is_front_page()) return;
    wp_register_script('parilte-contact-toggle', '', [], null, true);
    wp_enqueue_script('parilte-contact-toggle');
    $script = "document.addEventListener('click',function(e){\n".
    "  var btn=e.target.closest('.parilte-contact-toggle');\n".
    "  if(!btn){return;}\n".
    "  var target=btn.getAttribute('data-target');\n".
    "  var form=target?document.getElementById(target):null;\n".
    "  if(!form){return;}\n".
    "  form.classList.toggle('is-open');\n".
    "  if(form.classList.contains('is-open')){form.scrollIntoView({behavior:'smooth',block:'nearest'});} \n".
    "});";
    wp_add_inline_script('parilte-contact-toggle', $script);
}, 27);

// Kategori açıklamasını ızgara altına al
add_action('after_setup_theme', function () {
    remove_action('woocommerce_archive_description','woocommerce_taxonomy_archive_description',10);
    add_action('woocommerce_after_shop_loop','woocommerce_taxonomy_archive_description',5);
});

// Tek ürün: Beden tablosu kutusu
add_action('woocommerce_single_product_summary', function () {
  ?>
  <details class="parilte-size-box">
    <summary>Beden Tablosu</summary>
    <div class="parilte-size-grid">
      <div><strong>34</strong><span>XS</span></div>
      <div><strong>36</strong><span>S</span></div>
      <div><strong>38</strong><span>M</span></div>
      <div><strong>40</strong><span>L</span></div>
      <div><strong>42</strong><span>XL</span></div>
      <div><strong>44</strong><span>2XL</span></div>
      <div><strong>46</strong><span>3XL</span></div>
      <div><strong>48</strong><span>4XL</span></div>
      <div><strong>50</strong><span>5XL</span></div>
    </div>
    <small>Kalıp: Normal. Beden dönüşümleri markaya göre değişebilir.</small>
  </details>
  <?php
}, 26);

function parilte_cs_attr_labels($attr, $taxonomy){
    $labels = [];
    foreach ((array) $attr->get_options() as $opt) {
        $label = $opt;
        if (taxonomy_exists($taxonomy)) {
            if (is_numeric($opt)) {
                $t = get_term((int) $opt, $taxonomy);
            } else {
                $t = get_term_by('slug', $opt, $taxonomy);
                if (!$t || is_wp_error($t)) $t = get_term_by('name', $opt, $taxonomy);
            }
            if ($t && !is_wp_error($t)) $label = $t->name;
        }
        $labels[] = $label;
    }
    return array_values(array_filter($labels));
}

function parilte_cs_term_label($taxonomy, $value){
    if (!$value) return '';
    if (is_numeric($value)) $t = get_term((int) $value, $taxonomy);
    else $t = get_term_by('slug', $value, $taxonomy);
    if ($t && !is_wp_error($t)) return $t->name;
    return (string) $value;
}

function parilte_cs_color_hex($label){
    $slug = sanitize_title($label);
    $map = [
        'siyah'=>'#000000','beyaz'=>'#ffffff','gri'=>'#9ca3af','antrasit'=>'#444444','lacivert'=>'#0b2e59',
        'kahverengi'=>'#6b4f3a','bej'=>'#e7d7b5','krem'=>'#f5f0e6','ekru'=>'#f2efe6','tas'=>'#e4ded5',
        'kirmizi'=>'#d0021b','kırmızı'=>'#d0021b','mavi'=>'#2a6fdb','buz-mavi'=>'#cfe8f7',
        'yesil'=>'#2e8b57','yeşil'=>'#2e8b57','zumrut'=>'#0f7d4a','zümrüt'=>'#0f7d4a','haki'=>'#6b8e23',
        'bordo'=>'#5b1215','pembe'=>'#f3a6c8','pudra'=>'#f1cfd2','mor'=>'#7e57c2','lila'=>'#b59bd9',
        'turuncu'=>'#f39c12','hardal'=>'#d6a21f','sari'=>'#f1c40f','sarı'=>'#f1c40f',
        'camel'=>'#c19a6b','vizon'=>'#b8a089','taba'=>'#9a6b3f','aci-kahve'=>'#4a2f24','acı-kahve'=>'#4a2f24'
    ];
    return $map[$slug] ?? '#e5e5e5';
}

// Tek ürün: Beden/Renk secenekleri (gorsel)
add_action('woocommerce_single_product_summary', function () {
    global $product;
    if (!$product instanceof WC_Product) return;

    $sizes_all = ['34','36','38','40','42','44','46','48','50','XS','S','M','L','XL','2XL','3XL','4XL','5XL'];
    $selected_sizes = [];
    $in_stock_sizes = [];
    $selected_colors = [];
    $in_stock_colors = [];
    $selected_lengths = [];
    $in_stock_lengths = [];

    if ($product->is_type('variable')) {
        foreach ($product->get_children() as $vid) {
            $v = wc_get_product($vid);
            if (!$v) continue;
            $in = $v->is_in_stock();
            $attrs = $v->get_attributes();
            if (!empty($attrs['pa_beden'])) {
                $label = parilte_cs_term_label('pa_beden', $attrs['pa_beden']);
                $selected_sizes[$label] = true;
                if ($in) $in_stock_sizes[$label] = true;
            }
            if (!empty($attrs['pa_renk'])) {
                $label = parilte_cs_term_label('pa_renk', $attrs['pa_renk']);
                $selected_colors[$label] = true;
                if ($in) $in_stock_colors[$label] = true;
            }
            if (!empty($attrs['pa_boy'])) {
                $label = parilte_cs_term_label('pa_boy', $attrs['pa_boy']);
                $selected_lengths[$label] = true;
                if ($in) $in_stock_lengths[$label] = true;
            }
        }
    } else {
        foreach ($product->get_attributes() as $attr) {
            if ($attr->get_name()==='pa_beden') {
                foreach (parilte_cs_attr_labels($attr, 'pa_beden') as $label) {
                    $selected_sizes[$label] = true;
                    if ($product->is_in_stock()) $in_stock_sizes[$label] = true;
                }
            }
            if ($attr->get_name()==='pa_renk') {
                foreach (parilte_cs_attr_labels($attr, 'pa_renk') as $label) {
                    $selected_colors[$label] = true;
                    if ($product->is_in_stock()) $in_stock_colors[$label] = true;
                }
            }
            if ($attr->get_name()==='pa_boy') {
                foreach (parilte_cs_attr_labels($attr, 'pa_boy') as $label) {
                    $selected_lengths[$label] = true;
                    if ($product->is_in_stock()) $in_stock_lengths[$label] = true;
                }
            }
        }
    }

    echo '<div class="parilte-single-opts">';
    if (!empty($selected_sizes)) {
        echo '<div class="parilte-opt-row"><span class="parilte-opt-label">Beden</span><div class="parilte-opt-list">';
        foreach ($sizes_all as $s) {
            if (!isset($selected_sizes[$s])) continue;
            $is_on = !empty($in_stock_sizes[$s]);
            $cls = 'parilte-opt size ' . ($is_on ? 'on' : 'off');
            echo '<span class="'.esc_attr($cls).'">'.esc_html($s).'</span>';
        }
        echo '</div></div>';
    }

    if (!empty($selected_colors)) {
        echo '<div class="parilte-opt-row"><span class="parilte-opt-label">Renk</span><div class="parilte-opt-list">';
        foreach (array_keys($selected_colors) as $c) {
            $is_on = !empty($in_stock_colors[$c]);
            $cls = 'parilte-opt color ' . ($is_on ? 'on' : 'off');
            $hex = parilte_cs_color_hex($c);
            echo '<span class="'.esc_attr($cls).'" style="--chip-color: '.esc_attr($hex).';"><span class="dot"></span>'.esc_html($c).'</span>';
        }
        echo '</div></div>';
    }
    if (!empty($selected_lengths)) {
        echo '<div class="parilte-opt-row"><span class="parilte-opt-label">Boy</span><div class="parilte-opt-list">';
        foreach (array_keys($selected_lengths) as $b) {
            $is_on = !empty($in_stock_lengths[$b]);
            $cls = 'parilte-opt ' . ($is_on ? 'on' : 'off');
            echo '<span class="'.esc_attr($cls).'">'.esc_html($b).'</span>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}, 24);

// Ürün kart buton sınıfı
add_filter('woocommerce_loop_add_to_cart_args', function($args,$product){
  $args['class'] .= ' button parilte-card-btn';
  return $args;
},10,2);

// Ürün listesinde "Sepete ekle" gizle
add_action('init', function () {
    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
});

function parilte_cs_loop_discount_percent($product){
    if (!$product instanceof WC_Product) return 0;
    if ($product->is_type('variable')) {
        $regular = (float) $product->get_variation_regular_price('max');
        $sale    = (float) $product->get_variation_sale_price('max');
    } else {
        $regular = (float) $product->get_regular_price();
        $sale    = (float) $product->get_sale_price();
    }
    if (!$regular || !$sale || $sale >= $regular) return 0;
    return (int) round((($regular - $sale) / $regular) * 100);
}

add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    if (!$product instanceof WC_Product) return;
    $pct = parilte_cs_loop_discount_percent($product);
    if ($pct <= 0) return;
    echo '<span class="parilte-discount">-%'.(int)$pct.'</span>';
}, 12);

function parilte_cs_collect_loop_attrs($product){
    $sizes = []; $colors = []; $lengths = [];
    if ($product->is_type('variable')) {
        foreach ($product->get_children() as $vid) {
            $v = wc_get_product($vid);
            if (!$v) continue;
            $attrs = $v->get_attributes();
            if (!empty($attrs['pa_beden'])) $sizes[parilte_cs_term_label('pa_beden', $attrs['pa_beden'])] = true;
            if (!empty($attrs['pa_renk']))  $colors[parilte_cs_term_label('pa_renk', $attrs['pa_renk'])] = true;
            if (!empty($attrs['pa_boy']))   $lengths[parilte_cs_term_label('pa_boy', $attrs['pa_boy'])] = true;
        }
    } else {
        foreach ($product->get_attributes() as $attr) {
            if ($attr->get_name()==='pa_beden') {
                foreach (parilte_cs_attr_labels($attr, 'pa_beden') as $label) $sizes[$label] = true;
            }
            if ($attr->get_name()==='pa_renk') {
                foreach (parilte_cs_attr_labels($attr, 'pa_renk') as $label) $colors[$label] = true;
            }
            if ($attr->get_name()==='pa_boy') {
                foreach (parilte_cs_attr_labels($attr, 'pa_boy') as $label) $lengths[$label] = true;
            }
        }
    }
    return [
        'sizes' => array_slice(array_keys($sizes), 0, 8),
        'colors' => array_slice(array_keys($colors), 0, 6),
        'lengths' => array_slice(array_keys($lengths), 0, 6),
    ];
}

function parilte_cs_contact_form_redirect($status) {
    $ref = wp_get_referer();
    if (!$ref) $ref = home_url('/');
    $ref = remove_query_arg('parilte_contact', $ref);
    return add_query_arg('parilte_contact', $status, $ref);
}

function parilte_cs_handle_contact_form() {
    if (!isset($_POST['parilte_contact_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['parilte_contact_nonce'])), 'parilte_contact')) {
        wp_safe_redirect(parilte_cs_contact_form_redirect('error'));
        exit;
    }
    $name = isset($_POST['parilte_name']) ? sanitize_text_field(wp_unslash($_POST['parilte_name'])) : '';
    $email = isset($_POST['parilte_email']) ? sanitize_email(wp_unslash($_POST['parilte_email'])) : '';
    $subject = isset($_POST['parilte_subject']) ? sanitize_text_field(wp_unslash($_POST['parilte_subject'])) : '';
    $message = isset($_POST['parilte_message']) ? sanitize_textarea_field(wp_unslash($_POST['parilte_message'])) : '';
    if (!$name || !$email || !$subject || !$message) {
        wp_safe_redirect(parilte_cs_contact_form_redirect('error'));
        exit;
    }
    $to = 'destek@parilte.com';
    $body = "Ad Soyad: {$name}\nE-posta: {$email}\n\n{$message}";
    $headers = ['Reply-To: '.$email];
    $sent = wp_mail($to, $subject, $body, $headers);
    wp_safe_redirect(parilte_cs_contact_form_redirect($sent ? 'success' : 'error'));
    exit;
}

add_action('admin_post_nopriv_parilte_contact', 'parilte_cs_handle_contact_form');
add_action('admin_post_parilte_contact', 'parilte_cs_handle_contact_form');

add_filter('woocommerce_get_product_thumbnail', function ($html, $size = 'woocommerce_thumbnail', $deprecated = null, $attr = [], $product = null) {
    if (!($product instanceof WC_Product)) return $html;
    if (!is_shop() && !is_product_taxonomy() && !is_product_category() && !is_product_tag()) return $html;
    $data = parilte_cs_collect_loop_attrs($product);
    $panel = '';
    if (!empty($data['sizes']) || !empty($data['colors']) || !empty($data['lengths'])) {
        $panel .= '<div class="parilte-loop-attrs">';
        if (!empty($data['sizes'])) {
            $panel .= '<div><span>Beden</span><strong>'.esc_html(implode(' ', $data['sizes'])).'</strong></div>';
        }
        if (!empty($data['colors'])) {
            $panel .= '<div><span>Renk</span><strong>'.esc_html(implode(' ', $data['colors'])).'</strong></div>';
        }
        if (!empty($data['lengths'])) {
            $panel .= '<div><span>Boy</span><strong>'.esc_html(implode(' ', $data['lengths'])).'</strong></div>';
        }
        $panel .= '</div>';
    }
    return '<div class="parilte-loop-media">'.$html.$panel.'</div>';
}, 20, 5);

function parilte_cs_get_favorites($user_id = 0) {
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!$user_id) return [];
    $list = get_user_meta($user_id, 'parilte_favorites', true);
    if (!is_array($list)) $list = [];
    $list = array_values(array_unique(array_filter(array_map('intval', $list))));
    return $list;
}

function parilte_cs_is_favorite($product_id, $user_id = 0) {
    $product_id = (int) $product_id;
    if (!$product_id) return false;
    $list = parilte_cs_get_favorites($user_id);
    return in_array($product_id, $list, true);
}

function parilte_cs_toggle_favorite($product_id, $user_id = 0) {
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!$user_id) return [false, []];
    $product_id = (int) $product_id;
    if (!$product_id) return [false, parilte_cs_get_favorites($user_id)];
    $list = parilte_cs_get_favorites($user_id);
    $active = false;
    if (in_array($product_id, $list, true)) {
        $list = array_values(array_diff($list, [$product_id]));
    } else {
        $list[] = $product_id;
        $active = true;
    }
    update_user_meta($user_id, 'parilte_favorites', $list);
    return [$active, $list];
}

add_action('wp_ajax_parilte_toggle_favorite', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['login' => true, 'url' => wc_get_page_permalink('myaccount')]);
    }
    check_ajax_referer('parilte_fav', 'nonce');
    $pid = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    [$active, $list] = parilte_cs_toggle_favorite($pid);
    wp_send_json_success(['active' => $active, 'count' => count($list)]);
});

add_action('woocommerce_single_product_summary', function () {
    global $product;
    if (!$product instanceof WC_Product) return;
    $pid = $product->get_id();
    $login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/hesabim/');
    if (!is_user_logged_in()) {
        echo '<a class="parilte-fav-btn parilte-fav-single" href="'.esc_url($login_url).'">Favorilere eklemek için giriş yap</a>';
        return;
    }
    $is_active = parilte_cs_is_favorite($pid);
    $label = $is_active ? 'Favoride' : 'Favorilere Ekle';
    $cls = $is_active ? 'is-active' : '';
    echo '<button class="parilte-fav-btn parilte-fav-single '.$cls.'" type="button" data-product="'.esc_attr($pid).'" data-nonce="'.esc_attr(wp_create_nonce('parilte_fav')).'">'.$label.'</button>';
}, 35);

add_action('init', function () {
    add_rewrite_endpoint('favoriler', EP_ROOT | EP_PAGES);
    if (!get_option('parilte_favorites_endpoint_flushed')) {
        flush_rewrite_rules(false);
        update_option('parilte_favorites_endpoint_flushed', 1);
    }
});

add_filter('woocommerce_account_menu_items', function ($items) {
    $new = [];
    foreach ($items as $key => $label) {
        $new[$key] = $label;
        if ($key === 'dashboard') {
            $new['favoriler'] = 'Favoriler';
        }
    }
    return $new;
});

add_action('woocommerce_account_favoriler_endpoint', function () {
    if (!is_user_logged_in()) {
        echo '<p>Favorileri görmek için lütfen giriş yap.</p>';
        return;
    }
    $list = parilte_cs_get_favorites(get_current_user_id());
    if (!$list) {
        echo '<p>Henüz favorilere eklenmiş ürünün yok.</p>';
        return;
    }
    echo do_shortcode('[products ids="'.esc_attr(implode(',', $list)).'" columns="4" paginate="false"]');
});

/* ==========================================================
 * 1) ÜCRETSİZ KARGO BAR
 * ========================================================== */
if (PARILTE_CS_ON && PARILTE_CS_FREEBAR) {
    add_action('wp_enqueue_scripts', function () {
        $css = '
        .parilte-freebar{border:1px solid #eee;border-radius:12px;padding:10px;margin:10px 0;background:#fff}
        .parilte-freebar .row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
        .parilte-freebar .msg{font-size:14px}
        .parilte-freebar .track{position:relative;height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden;flex:1;min-width:160px}
        .parilte-freebar .fill{position:absolute;left:0;top:0;bottom:0;width:0;background:#16a34a}
        .parilte-freebar .ok{color:#16a34a;font-weight:600}
        ';
        wp_add_inline_style('parilte-checkout-suite', $css);
    }, 21);

    function parilte_cs_freebar_markup(){
        $threshold = (float) parilte_cs_opt('parilte_free_threshold', PARILTE_CS_FREE_THRESHOLD);
        if ($threshold <= 0) return '';
        $subtotal = parilte_cs_cart_subtotal();
        $left = max(0, $threshold - $subtotal);
        $pct  = min(100, max(0, ($subtotal / $threshold) * 100));
        $msg  = $left <= 0
            ? '<span class="ok">Ücretsiz kargo aktif!</span>'
            : str_replace('{left}', wc_price($left), (string) parilte_cs_opt('parilte_free_text', PARILTE_CS_FREE_TEXT));
        ob_start(); ?>
          <div class="parilte-freebar" role="status" aria-live="polite">
            <div class="row">
              <div class="msg"><?php echo wp_kses_post($msg); ?></div>
              <div class="track"><span class="fill" style="width: <?php echo esc_attr(number_format($pct,2)); ?>%"></span></div>
            </div>
          </div>
        <?php return ob_get_clean();
    }

    add_action('woocommerce_single_product_summary', function () { echo parilte_cs_freebar_markup(); }, 11);
    add_action('woocommerce_before_cart_totals',      function () { echo parilte_cs_freebar_markup(); }, 5);
    add_action('woocommerce_before_checkout_form',    function () { echo parilte_cs_freebar_markup(); }, 5);
}

/* ==========================================================
 * 2) ETA MESAJI
 * ========================================================== */
if (PARILTE_CS_ON && PARILTE_CS_ETA) {
    function parilte_cs_eta_markup(){
        [$a,$b] = parilte_cs_eta_range();
        return '<div class="parilte-freebar" style="padding:8px 10px"><span>⏱️ Tahmini teslimat: <strong>'.$a.' — '.$b.'</strong></span></div>';
    }
    add_action('woocommerce_single_product_summary',      function () { echo parilte_cs_eta_markup(); }, 12);
    add_action('woocommerce_cart_totals_before_shipping', function () { echo parilte_cs_eta_markup(); }, 5);
    add_action('woocommerce_review_order_before_payment', function () { echo parilte_cs_eta_markup(); }, 5);
}

/* ==========================================================
 * 3) CHECKOUT ALANLARI (TR sade)
 * ========================================================== */
if (PARILTE_CS_ON && PARILTE_CS_CHECKOUT_FIELDS) {
    add_filter('woocommerce_enable_order_notes_field','__return_false');

    add_filter('woocommerce_checkout_fields', function ($fields) {
        $lbl = [
            'first_name'=>'Ad','last_name'=>'Soyad','company'=>'Firma','address_1'=>'Adres',
            'address_2'=>'Adres Devam','postcode'=>'Posta Kodu','city'=>'İl','state'=>'İlçe',
            'phone'=>'Telefon','email'=>'E-posta'
        ];
        foreach ($lbl as $k=>$v){
            if(isset($fields['billing']['billing_'.$k])) $fields['billing']['billing_'.$k]['label']=$v;
            if(isset($fields['shipping']['shipping_'.$k])) $fields['shipping']['shipping_'.$k]['label']=$v;
        }
        $ph = [
            'first_name'=>'Adınız','last_name'=>'Soyadınız','address_1'=>'Mahalle, Cadde, No, Daire',
            'postcode'=>'34000','city'=>'İl (Örn: İstanbul)','state'=>'İlçe (Örn: Kadıköy)',
            'phone'=>'5XXXXXXXXX','email'=>'ornek@site.com'
        ];
        foreach ($ph as $k=>$v){
            if(isset($fields['billing']['billing_'.$k])) $fields['billing']['billing_'.$k]['placeholder']=$v;
        }
        if(isset($fields['billing']['billing_company']))  $fields['billing']['billing_company']['required']=false;
        if(isset($fields['billing']['billing_address_2']))$fields['billing']['billing_address_2']['required']=false;

        $prio = [
            'billing_first_name'=>10,'billing_last_name'=>20,'billing_phone'=>30,'billing_email'=>40,
            'billing_address_1'=>50,'billing_postcode'=>60,'billing_city'=>70,'billing_state'=>80
        ];
        foreach ($prio as $key=>$p){ if(isset($fields['billing'][$key])) $fields['billing'][$key]['priority']=$p; }

        if(isset($fields['billing']['billing_phone'])) $fields['billing']['billing_phone']['required']=true;

        return $fields;
    }, 10, 1);

    add_filter('woocommerce_get_terms_and_conditions_checkbox_text', function ($text) {
        return 'Siparişimi veriyorum ve <a href="'.esc_url(wc_get_page_permalink('terms')).'" target="_blank" rel="nofollow">Mesafeli Satış Sözleşmesi</a> ile <a href="'.esc_url(get_privacy_policy_url()).'" target="_blank" rel="nofollow">KVKK</a>’yı kabul ediyorum.';
    });
}

/* ==========================================================
 * 4) ÖDEME ROZETLERİ
 * ========================================================== */
if (PARILTE_CS_ON && PARILTE_CS_PAYBADGES) {
    function parilte_cs_svg($key){
        $icons = [
          'visa' => '<svg viewBox="0 0 48 16" width="48" height="16" aria-hidden="true"><rect width="48" height="16" rx="3" fill="#1a1f36"/><path fill="#fff" d="M6.7 11.7L8.2 4.3h2L8.7 11.7H6.7zM14.8 4.2c.4 0 .7.1 1 .3.2.2.4.5.4.9 0 .5-.3 1-.8 1.3.7.2 1.2.7 1.2 1.5 0 .5-.2 1-.6 1.3-.4.3-.9.5-1.6.5H10.9L11.4 4.3h3.4zm-1.6 2.2h.9c.6 0 .9-.3.9-.6s-.2-.5-.7-.5H13l-.2 1.1zm-.5 2.6h1c.6 0 1-.3 1-.8s-.4-.7-1-.7h-1l-.2 1.5zM17.4 11.7L18.9 4.3h1.8l-1.5 7.4h-1.8zM26.9 4.3l-2.2 7.4h-1.9l-1.1-5.5-1.1 5.5h-1.8l1.8-7.4h2l1.1 5.1 1-5.1h2.2zM29.2 11.7L30.7 4.3h1.8l-1.5 7.4h-1.8zM40.6 6.1c-.3-.1-.8-.3-1.3-.3-.7 0-1.2.3-1.2.7 0 .3.4.5.9.7l.6.2c1 .4 1.6 1 1.6 1.8 0 1.3-1.2 2.2-3 2.2-.8 0-1.6-.1-2.2-.4l.4-1.4c.5.2 1.2.4 1.8.4.8 0 1.3-.3 1.3-.7 0-.3-.2-.6-.9-.8l-.6-.2c-1-.4-1.6-.9-1.6-1.8 0-1.2 1.1-2.2 2.9-2.2.8 0 1.5.1 2 .4l-.4 1.4z"/></svg>',
          'mc'   => '<svg viewBox="0 0 48 16" width="48" height="16"><rect width="48" height="16" rx="3" fill="#1a1f36"/><circle cx="20" cy="8" r="4.2" fill="#eb001b"/><circle cx="28" cy="8" r="4.2" fill="#f79e1b"/><path d="M24 4.2a4.2 4.2 0 0 0 0 7.6 4.2 4.2 0 0 0 0-7.6z" fill="#ff5f00"/></svg>',
          'amex' => '<svg viewBox="0 0 48 16" width="48" height="16"><rect width="48" height="16" rx="3" fill="#2e77bc"/><text x="8" y="11" fill="#fff" font-size="7" font-family="Arial,Helvetica,sans-serif" font-weight="700">AMEX</text></svg>',
          'troy' => '<svg viewBox="0 0 48 16" width="48" height="16"><rect width="48" height="16" rx="3" fill="#0ea5b7"/><text x="9" y="11" fill="#fff" font-size="8" font-family="Arial,Helvetica,sans-serif" font-weight="700">TROY</text></svg>',
          'iyzico'=>'<svg viewBox="0 0 48 16" width="48" height="16"><rect width="48" height="16" rx="3" fill="#27a7df"/><text x="8" y="11" fill="#fff" font-size="8" font-family="Arial" font-weight="700">iyzico</text></svg>',
          'iyzico_pay'=>'<svg class="parilte-paybadge-iyzico" viewBox="0 0 96 16" width="96" height="16" aria-hidden="true"><rect width="96" height="16" rx="3" fill="#27a7df"/><text x="6" y="11" fill="#fff" font-size="8" font-family="Arial" font-weight="700">iyzico ile Öde</text></svg>',
          'paytr'=>'<svg viewBox="0 0 48 16" width="48" height="16"><rect width="48" height="16" rx="3" fill="#1e7dd3"/><text x="10" y="11" fill="#fff" font-size="8" font-family="Arial" font-weight="700">PayTR</text></svg>',
          'stripe'=>'<svg viewBox="0 0 48 16" width="48" height="16"><rect width="48" height="16" rx="3" fill="#635bff"/><text x="8" y="11" fill="#fff" font-size="8" font-family="Arial" font-weight="700">Stripe</text></svg>',
        ];
        return $icons[$key] ?? '';
    }

    add_action('woocommerce_review_order_before_payment', function () {
        if (!WC()->payment_gateways) return;
        $gws = WC()->payment_gateways->get_available_payment_gateways();
        if (!$gws) return;

        $brands = ['visa','mc','troy','amex'];
        $prov   = [];
        foreach ($gws as $id=>$gw) {
            $sid = strtolower($id);
            if (strpos($sid,'iyz')!==false)   $prov['iyzico_pay']=true;
            if (strpos($sid,'paytr')!==false) $prov['paytr']=true;
            if (strpos($sid,'stripe')!==false)$prov['stripe']=true;
        }

        echo '<div class="parilte-paybadges" aria-label="Ödeme seçenekleri">';
        foreach ($prov as $k=>$_) echo parilte_cs_svg($k);
        foreach ($brands as $b)   echo parilte_cs_svg($b);
        echo '</div>';
    }, 1);

    add_action('wp_enqueue_scripts', function () {
        $css = '
        .parilte-paybadges{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0 14px}
        .parilte-paybadges svg{filter:drop-shadow(0 1px 0 rgba(0,0,0,.04))}
        .parilte-paybadges .parilte-paybadge-iyzico{width:96px;height:16px}
        ';
        wp_add_inline_style('parilte-checkout-suite', $css);
    }, 25);
}

/* ==========================================================
 * 5) CHECKOUT MİNİ-ÖZET (sticky)
 * ========================================================== */
if (PARILTE_CS_ON && PARILTE_CS_MINI_SUMMARY) {
    function parilte_cs_mini_summary_markup(){
        if (!WC()->cart) return '';
        $cnt = WC()->cart->get_cart_contents_count();
        $sub = WC()->cart->get_subtotal();
        return '<div class="parilte-mini-summary" role="region" aria-label="Sipariş özeti">'.
                 '<span>Ürün: <strong>'.$cnt.'</strong></span>'.
                 '<span>Ara toplam: <strong>'.wc_price($sub).'</strong></span>'.
               '</div>';
               
    }
    add_action('woocommerce_checkout_before_customer_details', function () {
        echo parilte_cs_mini_summary_markup();
    }, 4);

    add_action('wp_enqueue_scripts', function () {
        $css = '
        .parilte-mini-summary{position:sticky;top:12px;z-index:5;display:flex;gap:12px;align-items:center;background:#fff;
            border:1px solid #eee;border-radius:12px;padding:8px 10px;margin-bottom:10px}
        .woocommerce-checkout .col2-set{row-gap:12px}
        ';
        wp_add_inline_style('parilte-checkout-suite', $css);
    }, 26);
}

/* ==========================================================
 * 6) TEST/CANLI UYARILARI (yalnız yöneticiye)
 * ========================================================== */
add_action('woocommerce_before_checkout_form', function () {
    if (!current_user_can('manage_woocommerce')) return;
    $msg = '';

    // Stripe
    $st = (array) get_option('woocommerce_stripe_settings');
    if (!empty($st['enabled']) && $st['enabled']==='yes') {
        if (!empty($st['testmode']) && $st['testmode']==='yes') $msg = 'Stripe test modu';
        if (!$msg && !empty($st['publishable_key']) && strpos($st['publishable_key'],'pk_test_')===0) $msg = 'Stripe test anahtarı';
    }
    // iyzico
    if (!$msg) {
        $iy = (array) get_option('woocommerce_iyzico_settings');
        if (!empty($iy['enabled']) && $iy['enabled']==='yes') {
            if (!empty($iy['testmode']) && $iy['testmode']==='yes') $msg = 'iyzico test modu';
            if (!$msg && !empty($iy['sandbox']) && $iy['sandbox']==='yes') $msg = 'iyzico sandbox';
        }
    }
    // PayTR
    if (!$msg) {
        $pt = (array) get_option('woocommerce_paytr_settings');
        if (!empty($pt['enabled']) && $pt['enabled']==='yes') {
            if (!empty($pt['testmode']) && $pt['testmode']==='yes') $msg = 'PayTR test modu';
            if (!$msg && !empty($pt['mode']) && strtolower($pt['mode'])==='test') $msg = 'PayTR test modu';
        }
    }

    if (!$msg) return;
    echo '<div class="notice notice-warning parilte-testnote" style="margin:10px 0;padding:10px;border-left:4px solid #f59e0b;background:#fff8e1">
            <strong>Uyarı:</strong> Ödeme <em>'.$msg.'</em> ile çalışıyor. Canlıya almadan önce anahtarları değiştirin.
          </div>';
}, 3);

/* ==========================================================
 * 7) TR ADRES DOĞRULAMA (posta kodu/telefon)
 * ========================================================== */
if (PARILTE_CS_ON && PARILTE_CS_VALIDATION) {
    add_action('wp_enqueue_scripts', function () {
        if (!is_checkout() || is_order_received_page()) return;
        // jQuery’yi garantiye al
        wp_enqueue_script('jquery');

        wp_add_inline_script('jquery', "
          jQuery(function($){
            var \$pc = $('#billing_postcode'), \$ph = $('#billing_phone');
            if(\$pc.length){
              var \$hint = $('<div class=\"parilte-hint\"></div>').insertAfter(\$pc);
              function pcOK(v){ return /^[0-9]{5}$/.test(v); }
              function updatePC(){ var v=\$pc.val().trim(); if(!v){\$hint.text('');return;}
                if(pcOK(v)){ \$hint.text('✓ Geçerli posta kodu').css('color','#16a34a'); }
                else{ \$hint.text('5 haneli posta kodu girin (örn: 34000)').css('color','#dc2626'); }
              }
              \$pc.on('input blur', updatePC); updatePC();
            }
            if(\$ph.length){
              var \$hint2 = $('<div class=\"parilte-hint\"></div>').insertAfter(\$ph);
              function digits(s){ return (s+'').replace(/\\D+/g,''); }
              function formatTR(d){
                if(d.length<3) return d;
                return d.replace(/^(\\d{3})(\\d{0,3})(\\d{0,2})(\\d{0,2}).*/, function(_,a,b,c,e){
                  return a + (b?' '+b:'') + (c?' '+c:'') + (e?' '+e:'' );
                });
              }
              \$ph.on('input', function(){
                var d = digits(\$ph.val());
                if(d.length>11) d=d.slice(0,11);
                \$ph.val(formatTR(d));
                if(d.length<10 || d[0]!=='5'){ \$hint2.text('Cep telefonu 5XX XXX XX XX').css('color','#dc2626'); }
                else { \$hint2.text('✓ Uygun format').css('color','#16a34a'); }
              });
            }
          });
        ");
        wp_add_inline_style('parilte-checkout-suite', '.parilte-hint{font-size:12px;margin-top:4px;opacity:.85}');
    }, 27);
}

/* ==========================================================
 * 8) Checkout görsel sıkıştırma CSS
 * ========================================================== */
add_action('wp_enqueue_scripts', function () {
    $css = '
    .woocommerce-checkout .col2-set .col-1, .woocommerce-checkout .col2-set .col-2{padding:8px;border:1px solid #eee;border-radius:12px;background:#fff}
    .woocommerce-checkout h3{font-size:16px;margin:8px 0}
    .woocommerce form .form-row{margin-bottom:10px}
    ';
    wp_add_inline_style('parilte-checkout-suite', $css);
}, 28);
