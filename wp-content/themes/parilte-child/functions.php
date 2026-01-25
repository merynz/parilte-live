<?php
// Child stilini yükle
add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('parilte-child', get_stylesheet_uri(), [], '1.0.0');
}, 20);

/**
 * PARILTE-BOOTSTRAP-SETUP — tek seferlik:
 * - Sayfalar (Anasayfa, Mağaza, Sepet, Ödeme, Hesabım, Hakkımızda, İade & Değişim, Teslimat & Kargo, KVKK, Blog, Beden Rehberi)
 * - Okuma: Statik Anasayfa; Permalink: /%postname%/
 * - Woo sayfa atamaları; Kategori URL kökü: /kategori/
 * - Ürün Kategori ağacı; Nitelikler: Beden (XS–XL), Renk (Mavi, Siyah, Beyaz, Gri, Bej, Haki)
 * - “Ana Menü” (Anasayfa/Mağaza + ana kategoriler) ve konum denemesi
 */
add_action('admin_init', function () {
  if (!current_user_can('manage_options')) return;
  if (get_option('parilte_bootstrap_done')) return;

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

  // 2) Reading
  update_option('show_on_front','page');
  update_option('page_on_front',(int)$p['home']);
  update_option('page_for_posts',(int)$p['blog']);

  // 3) Permalink
  update_option('permalink_structure','/%postname%/');
  if (function_exists('flush_rewrite_rules')) flush_rewrite_rules();

  // 4) Woo pages
  update_option('woocommerce_shop_page_id',(int)$p['shop']);
  update_option('woocommerce_cart_page_id',(int)$p['cart']);
  update_option('woocommerce_checkout_page_id',(int)$p['checkout']);
  update_option('woocommerce_myaccount_page_id',(int)$p['account']);

  // 5) Product category base
  $wcpl = (array)get_option('woocommerce_permalinks',[]);
  $wcpl['category_base']    = 'kategori';
  $wcpl['product_cat_base'] = 'kategori';
  update_option('woocommerce_permalinks',$wcpl);
  if (function_exists('flush_rewrite_rules')) flush_rewrite_rules();

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
    if (function_exists('register_taxonomy')) {
      register_taxonomy('pa_beden','product',['hierarchical'=>false,'show_ui'=>false]);
      register_taxonomy('pa_renk','product',['hierarchical'=>false,'show_ui'=>false]);
    }
    foreach (['XS','S','M','L','XL'] as $s) if (!term_exists($s,'pa_beden')) wp_insert_term($s,'pa_beden',['slug'=>strtolower($s)]);
    foreach (['Mavi','Siyah','Beyaz','Gri','Bej','Haki'] as $c) if (!term_exists($c,'pa_renk')) wp_insert_term($c,'pa_renk',['slug'=>sanitize_title($c)]);
  }

  // 8) Menu “Ana Menü” + konum
  $menu_name = 'Ana Menü';
  $menu_obj  = wp_get_nav_menu_object($menu_name);
  $menu_id   = $menu_obj ? $menu_obj->term_id : wp_create_nav_menu($menu_name);
  $add_item  = function($args) use ($menu_id){ return wp_update_nav_menu_item($menu_id,0,array_merge(['menu-item-status'=>'publish'],$args)); };
  $add_item(['menu-item-title'=>'Anasayfa','menu-item-url'=>home_url('/'),'menu-item-type'=>'custom']);
  $add_item(['menu-item-title'=>'Mağaza','menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-object-id'=>$p['shop']]);
  foreach ([['Üst Giyim','ust-giyim'],['Alt Giyim','alt-giyim'],['Aksesuar','aksesuar']] as $k)
    $add_item(['menu-item-title'=>$k[0],'menu-item-url'=>home_url('/kategori/'.$k[1].'/'),'menu-item-type'=>'custom']);

  $loc = get_theme_mod('nav_menu_locations',[]);
  $reg = get_registered_nav_menus();
  $primary = array_keys(array_filter($reg,function($d,$s){return preg_match('/primary|header|main/i',$s.$d);},ARRAY_FILTER_USE_BOTH));
  $mobile  = array_keys(array_filter($reg,function($d,$s){return preg_match('/mobile/i',$s.$d);},ARRAY_FILTER_USE_BOTH));
  if (!empty($primary)) $loc[$primary[0]] = $menu_id;
  if (!empty($mobile))  $loc[$mobile[0]]  = $menu_id;
  set_theme_mod('nav_menu_locations',$loc);

  update_option('parilte_bootstrap_done',1);
  add_action('admin_notices',function(){
    echo '<div class="notice notice-success"><p><strong>Parilté Bootstrap</strong> tamamlandı.</p></div>';
  });
});

// === UI HOOKS ===

// Header: Hesap + Ürün araması (shortcode)
add_shortcode('parilte_header', function () {
  if (!function_exists('wc_get_page_permalink')) return '';
  $account_url = esc_url( wc_get_page_permalink('myaccount') );
  ob_start(); ?>
  <div class="parilte-header-icons">
    <a class="parilte-account" href="<?php echo $account_url; ?>" aria-label="Hesabım">
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
      <span>Hesap</span>
    </a>
    <div class="parilte-search">
      <?php if (function_exists('get_product_search_form')) { get_product_search_form(); } else { get_search_form(); } ?>
    </div>
  </div>
  <?php return ob_get_clean();
});

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
      <div><strong>XS</strong><span>34</span></div>
      <div><strong>S</strong><span>36</span></div>
      <div><strong>M</strong><span>38</span></div>
      <div><strong>L</strong><span>40</span></div>
      <div><strong>XL</strong><span>42</span></div>
    </div>
    <small>Kalıp: Normal. İki beden arasında kaldıysan bir büyüğünü öneririz.</small>
  </details>
  <?php
}, 26);

// Ürün kart buton sınıfı
add_filter('woocommerce_loop_add_to_cart_args', function($args,$product){
  $args['class'] .= ' button parilte-card-btn';
  return $args;
},10,2);
