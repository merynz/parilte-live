<?php
/**
 * Plugin Name: Parilté Placeholder & Ürün Sihirbazı
 * Description: Kategorilere dinamik placeholder ekler/tamamlar; Hızlı Güncelle ve Ürün Sihirbazı ile ad/fiyat/stok/kapak/galeri/beden-renk-varyasyon yönetimi yapar. Mağaza gridinde "Devamını Oku" gizlenir; yalnız mevcut beden/renk rozetleri gösterilir ve stok durumuna göre (tıklanabilir/sönük) davranır; renk rozetleri gerçek renk çipidir.
 * Version:     1.5.2
 * Author:      Parilté
 */

if (!defined('ABSPATH')) exit;

// Ürün sayfası galerisi (tema kapatsa bile aç)
add_action('after_setup_theme', function () {
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}, 11);

// Placeholder sistemi kapalı (gerçek ürünlerle ilerle)
if (!defined('PARILTE_PLACEHOLDER_ENABLED')) define('PARILTE_PLACEHOLDER_ENABLED', false);
if (!defined('PARILTE_PLACEHOLDER_META_FLAG')) define('PARILTE_PLACEHOLDER_META_FLAG', '_parilte_placeholder');

function parilte_placeholder_cleanup() {
    if (!class_exists('WC_Product')) return 0;
    $deleted = 0;

    $q = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [[ 'key'=>PARILTE_PLACEHOLDER_META_FLAG, 'value'=>'1' ]]
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
        's'              => 'Placeholder'
    ]);
    foreach ($q2->posts as $pid) {
        $title = get_the_title($pid);
        if ($title && stripos($title, 'placeholder') !== false) {
            wp_trash_post($pid);
            $deleted++;
        }
    }

    return $deleted;
}

add_action('init', function () {
    if (PARILTE_PLACEHOLDER_ENABLED) return;

    $has_flagged = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [[ 'key'=>PARILTE_PLACEHOLDER_META_FLAG, 'value'=>'1' ]]
    ]);
    $has_title = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        's'              => 'Placeholder'
    ]);

    if (!$has_flagged->have_posts() && !$has_title->have_posts()) return;

    $deleted = parilte_placeholder_cleanup();
    update_option('parilte_placeholder_cleanup_done', (int) $deleted);
}, 5);

if (!PARILTE_PLACEHOLDER_ENABLED) {
    return;
}

if (!class_exists('Parilte_Placeholder_Seeder')):

class Parilte_Placeholder_Seeder {
    const META_FLAG   = '_parilte_placeholder';
    const DEFAULT_PER = 9;   // Varsayılan hedef (3×3)
    const DEFAULT_COL = 3;   // Varsayılan sütun
    const PRICE       = 999;

    const ATTR_SIZE   = 'pa_beden';
    const ATTR_COLOR  = 'pa_renk';

    // Referans setleri (üründe yoksa çıkmaz)
    const SIZES       = ['XS','S','M','L','XL'];
    const COLORS      = ['Siyah','Beyaz','Gri','Lacivert','Kahverengi','Bej','Krem','Ekru','Kırmızı','Mavi','Yeşil','Bordo','Pembe','Mor','Turuncu','Sarı','Haki','Acı Kahve'];

    // Renk paleti (slug => hex)
    private $palette = [
        'siyah'=>'#000000','beyaz'=>'#FFFFFF','gri'=>'#8C8C8C','lacivert'=>'#0B2E59',
        'kahverengi'=>'#6B4F3A','bej'=>'#E7D7B5','krem'=>'#F5F0E6','ekru'=>'#F2EFE6',
        'kirmizi'=>'#D0021B','kırmızı'=>'#D0021B','mavi'=>'#2A6FDB','yesil'=>'#2E8B57','yeşil'=>'#2E8B57',
        'bordo'=>'#5B1215','pembe'=>'#F3A6C8','mor'=>'#7E57C2','turuncu'=>'#F39C12',
        'sari'=>'#F1C40F','sarı'=>'#F1C40F','haki'=>'#6B8E23','aci-kahve'=>'#4A2F24','acı-kahve'=>'#4A2F24'
    ];

    // Hedeflenecek kategori adları (adıyla eşleşir; yoksa atlanır)
    private $category_names = [
        'Genel','Aksesuar','Anahtarlık','Atkı','Bere','Eldiven','Fular','Kemer','Şal','Şapka','Şemsiye',
        'Alt Giyim','Eşofman','Etek','Jean','Pantolon','Şort','Tayt','Tulum',
        'Ayakkabı','Babet','Bot','Çizme','Sandalet','Terlik','Topuklu Ayakkabı',
        'Çanta','Günlük','Şık','Spor',
        'Dış Giyim','Ceket&Yelek','Kaban','Mont','Trençkot',
        'Elbise','Düğün','Günlük Elbise','Mezuniyet','Şık Elbise',
        'İlkbahar - Yaz Sezonu','Sonbahar - Kış Sezonu',
        'Takı','Bel Zinciri','Bileklik','Bilezik','Kolye','Küpe',
        'Tüm Ürünler','Üst Giyim','Atlet','Badi','Bluz','Bodysuit','Crop','Gömlek','Kazak','Sweatshirt','Tişört','Triko','Tshirt',
        'Yeni Sezon','Yeni Ürünler'
    ];

    function __construct() {
        add_action('admin_menu',               [$this,'menu']);
        add_action('admin_post_parilte_seed',  [$this,'handle_seed']);
        add_action('admin_post_parilte_wipe',  [$this,'handle_wipe']);
        add_action('admin_post_parilte_repair',[$this,'handle_repair']); // FLAG ONAR
        add_action('admin_notices',            [$this,'admin_notice']);
        add_action('save_post_product',        [$this,'auto_unflag_when_real'], 10, 3);
    }

    /** Araçlar menüsü */
    function menu() {
        add_management_page('Parilté Placeholder','Parilté Placeholder','manage_options','parilte-placeholder',[$this,'page']);
    }

    /** Yönetim sayfası */
    function page() {
        if (!current_user_can('manage_options')) return;

        $per     = isset($_GET['per'])     ? max(0, (int)$_GET['per']) : self::DEFAULT_PER;
        $columns = isset($_GET['columns']) ? max(1, (int)$_GET['columns']) : self::DEFAULT_COL;
        $pad     = isset($_GET['pad'])     ? (int)$_GET['pad'] : 0;
        $leaf    = isset($_GET['leaf'])    ? (int)$_GET['leaf'] : 0; ?>
        <div class="wrap">
            <h1>Parilté Placeholder</h1>
            <p>“Hedef ürün adedi”ni doldurur. <em>Çokluya yuvarla</em> seçilirse toplam ürün sayısı sütun sayısına tamamlanır (ör. 11 → 12).</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;grid-template-columns:repeat(6,auto);gap:10px;align-items:center;margin-bottom:10px;">
                <?php wp_nonce_field('parilte_seed'); ?>
                <input type="hidden" name="action" value="parilte_seed">
                <label>Hedef (adet):
                    <input type="number" name="per" value="<?php echo esc_attr($per); ?>" min="0" step="1" style="width:90px">
                </label>
                <label>Sütun:
                    <input type="number" name="columns" value="<?php echo esc_attr($columns); ?>" min="1" step="1" style="width:90px">
                </label>
                <label><input type="checkbox" name="pad" value="1" <?php checked($pad,1); ?>> Çokluya yuvarla</label>
                <label><input type="checkbox" name="leaf" value="1" <?php checked($leaf,1); ?>> Sadece yaprak kategoriler</label>
                <button class="button button-primary">Placeholder ÜRET / TAMAMLA</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                <?php wp_nonce_field('parilte_wipe'); ?>
                <input type="hidden" name="action" value="parilte_wipe">
                <button class="button">Placeholder TEMİZLE</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                <?php wp_nonce_field('parilte_repair'); ?>
                <input type="hidden" name="action" value="parilte_repair">
                <button class="button">FLAG ONAR (eski placeholder’ları işaretle & görünür yap)</button>
            </form>

            <p style="margin-top:12px">
                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=parilte-quickedit')); ?>">Hızlı Güncelle (kategori bazlı)</a>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('tools.php?page=parilte-wizard')); ?>">Ürün Sihirbazı</a>
            </p>
            <hr>
            <p><strong>Not:</strong> “Stokta olmayanları gizle” açıksa placeholder’lar görünmezdi; bu sürüm placeholder’ları <em>instock</em> yapar ve satın almayı devre dışı bırakır.</p>
        </div>
        <?php
    }

    /** Seed tetikleyici */
    function handle_seed() {
        if (!current_user_can('manage_options') || !check_admin_referer('parilte_seed')) wp_die('Yetki yok.');
        $per     = isset($_POST['per'])     ? max(0, (int)$_POST['per']) : self::DEFAULT_PER;
        $columns = isset($_POST['columns']) ? max(1, (int)$_POST['columns']) : self::DEFAULT_COL;
        $pad     = !empty($_POST['pad']);
        $leaf    = !empty($_POST['leaf']);
        $summary = $this->seed_all($per, $columns, $pad, $leaf);
        wp_redirect(add_query_arg(['page'=>'parilte-placeholder','parilte_notice'=>urlencode($summary),'per'=>$per,'columns'=>$columns,'pad'=>$pad?1:0,'leaf'=>$leaf?1:0], admin_url('tools.php')));
        exit;
    }

    /** Wipe tetikleyici */
    function handle_wipe() {
        if (!current_user_can('manage_options') || !check_admin_referer('parilte_wipe')) wp_die('Yetki yok.');
        $deleted = $this->wipe_placeholders();
        wp_redirect(add_query_arg(['page'=>'parilte-placeholder','parilte_notice'=>urlencode("Silinen placeholder: $deleted")], admin_url('tools.php')));
        exit;
    }

    /** FLAG ONAR: Eski placeholder’ları işaretle + görünür hale getir */
    function handle_repair() {
        if (!current_user_can('manage_options') || !check_admin_referer('parilte_repair')) wp_die('Yetki yok.');
        $fixed = 0;
        // Meta bayrağı olmayan ama başlığında "Placeholder" geçenler
        $q = new WP_Query([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            's'              => 'Placeholder',
            'fields'         => 'ids',
            'meta_query'     => [[ 'key'=>self::META_FLAG, 'compare'=>'NOT EXISTS' ]]
        ]);
        foreach ($q->posts as $pid) {
            update_post_meta($pid, self::META_FLAG, '1');
            $p = wc_get_product($pid);
            if ($p) { $p->set_stock_status('instock'); $p->set_catalog_visibility('visible'); $p->save(); }
            $fixed++;
        }
        wp_redirect(add_query_arg(['page'=>'parilte-placeholder','parilte_notice'=>urlencode("Onarılan/işaretlenen: $fixed")], admin_url('tools.php')));
        exit;
    }

    /** Admin bilgi */
    function admin_notice() {
        if (!isset($_GET['parilte_notice']) || !current_user_can('manage_options')) return;
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($_GET['parilte_notice']).'</p></div>';
    }

    /** Tüm kategorilere üret / tamamla */
    private function seed_all($per, $columns, $pad, $leaf_only) {
        if (!class_exists('WC_Product')) return 'WooCommerce aktif değil.';

        $this->ensure_attribute_taxonomy('Beden', self::ATTR_SIZE);
        $this->ensure_attribute_taxonomy('Renk',  self::ATTR_COLOR);
        $this->ensure_attribute_terms(self::ATTR_SIZE, self::SIZES);
        $this->ensure_attribute_terms(self::ATTR_COLOR, self::COLORS);

        $created_total = 0; $skipped = [];

        foreach ($this->category_names as $name) {
            $term = get_term_by('name', $name, 'product_cat');
            if (!$term || is_wp_error($term)) { $skipped[] = $name; continue; }
            if ($leaf_only && $this->has_children($term->term_id)) continue;

            $total = $this->count_products_in_term($term->term_id); // gerçek + placeholder
            $goal  = max($per, $total);
            if ($pad && $columns > 0) $goal = (int) (ceil($goal / $columns) * $columns);
            $need = max(0, $goal - $total);

            for ($i=0; $i<$need; $i++) {
                $sku = $this->next_sku_for($term);
                $this->create_product($term->term_id, $name, $sku);
                $created_total++;
            }
        }
        return "Oluşturulan: $created_total. Atlanan (bulunamadı): ".implode(', ', $skipped ?: ['—']);
    }

    /** Placeholder temizle */
    private function wipe_placeholders() {
        $q = new WP_Query([
            'post_type'=>'product','posts_per_page'=>-1,'fields'=>'ids',
            'post_status'=>'any',
            'meta_query'=>[['key'=>self::META_FLAG,'value'=>'1']]
        ]);
        $n=0; foreach ($q->posts as $pid){ wp_trash_post($pid); $n++; } return $n;
    }

    /** Terimde toplam ürün sayısı */
    private function count_products_in_term($term_id) {
        $q = new WP_Query([
            'post_type'=>'product','posts_per_page'=>-1,'fields'=>'ids',
            'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$term_id]],
            'post_status'=>'publish'
        ]);
        return (int)$q->found_posts;
    }

    private function has_children($term_id) {
        $children = get_terms(['taxonomy'=>'product_cat','parent'=>$term_id,'hide_empty'=>false,'fields'=>'ids']);
        return !empty($children);
    }

    /** Sıradaki benzersiz SKU */
    private function next_sku_for($term) {
        $prefix = strtoupper(str_replace(['-',' '],'', sanitize_title($term->name)));
        for ($n=1; $n<=9999; $n++) {
            $sku = sprintf('%s-%04d', $prefix, $n);
            if (!wc_get_product_id_by_sku($sku)) return $sku;
        }
        return $prefix.'-X'.time();
    }

    /** Placeholder ürün oluştur */
    private function create_product($term_id, $cat_name, $sku) {
        $p = new WC_Product_Simple();
        $p->set_name($cat_name.' Placeholder');
        $p->set_status('publish');
        $p->set_catalog_visibility('visible');
        $p->set_price(self::PRICE);
        $p->set_regular_price(self::PRICE);
        $p->set_sku($sku);
        $p->set_category_ids([$term_id]);
        $p->set_stock_status('instock'); // katalogda görünmesi için

        // Ek Bilgi sekmesi için görünür attribute’lar
        $attrs = [];
        $attr_size  = $this->build_tax_attribute(self::ATTR_SIZE,  self::SIZES);
        $attr_color = $this->build_tax_attribute(self::ATTR_COLOR, self::COLORS);
        if ($attr_size)  $attrs[] = $attr_size;
        if ($attr_color) $attrs[] = $attr_color;
        $p->set_attributes($attrs);

        $pid = $p->save();
        if ($pid) {
            update_post_meta($pid, self::META_FLAG, '1');
            wp_update_post(['ID'=>$pid,'post_excerpt'=>'Görseller ve ürün detayları yakında.']);
        }
        return $pid;
    }

    /** Attribute taksonomilerini hazırla (public: sihirbazdan da çağrılır) */
    public function ensure_attribute_taxonomy($label, $tax) {
        if (taxonomy_exists($tax)) return;
        if (!function_exists('wc_create_attribute')) return;
        $slug = str_replace('pa_', '', $tax);
        $id = wc_create_attribute([
            'name'=>$label,'slug'=>$slug,'type'=>'select','order_by'=>'menu_order','has_archives'=>false,
        ]);
        if (!is_wp_error($id)) {
            register_taxonomy($tax, 'product', [
                'labels'=>['name'=>$label],'hierarchical'=>false,'show_ui'=>false,'query_var'=>true,'rewrite'=>false,
            ]);
        }
    }

    /** Global attribute terimlerini ekle (public: sihirbazdan da çağrılabilir) */
    public function ensure_attribute_terms($taxonomy, $labels) {
        if (!taxonomy_exists($taxonomy)) return;
        foreach ($labels as $label) {
            if (!term_exists($label, $taxonomy)) wp_insert_term($label, $taxonomy);
        }
    }

    /** Woo attribute nesnesi kur */
    private function build_tax_attribute($taxonomy, $labels) {
        $attr = new WC_Product_Attribute();
        $attr_id = 0;

        if (taxonomy_exists($taxonomy) && function_exists('wc_attribute_taxonomy_id_by_name')) {
            $attr_id = (int) wc_attribute_taxonomy_id_by_name(str_replace('pa_', '', $taxonomy));
        }

        if (!taxonomy_exists($taxonomy)) {
            $attr->set_id(0);
            $attr->set_name($taxonomy);
            $attr->set_options($labels);
            $attr->set_visible(true);
            $attr->set_variation(false);
            return $attr;
        }

        $term_ids = [];
        foreach ($labels as $label) {
            $t = get_term_by('name', $label, $taxonomy);
            if ($t && !is_wp_error($t)) $term_ids[] = (int) $t->term_id;
        }

        if (!$term_ids) return null;
        $attr->set_id($attr_id);
        $attr->set_name($taxonomy);
        $attr->set_options($term_ids);
        $attr->set_visible(true);
        $attr->set_variation(false);
        return $attr;
    }

    /** Gerçek veriye geçince placeholder bayrağını temizle */
    function auto_unflag_when_real($post_id, $post, $update) {
        if (wp_is_post_revision($post_id)) return;
        if (get_post_meta($post_id, self::META_FLAG, true) !== '1') return;
        $p = wc_get_product($post_id); if (!$p) return;
        $has_image = (int) $p->get_image_id() > 0;
        $instock   = $p->get_stock_status() === 'instock';
        $has_desc  = strlen(trim($p->get_description())) > 0 || strlen(trim($p->get_short_description())) > 0;
        if ($has_image || $instock || $has_desc) delete_post_meta($post_id, self::META_FLAG);
    }

    /** Renk adına göre hex */
    function color_hex($label) {
        $slug = sanitize_title($label);
        return $this->palette[$slug] ?? '#E5E5E5';
    }
}
endif;

// === SINIFI BAŞLAT ===
if (class_exists('Parilte_Placeholder_Seeder')) {
    $GLOBALS['parilte_placeholder_seeder'] = new Parilte_Placeholder_Seeder();
}

// === HIZLI GÜNCELLE (Medya Kütüphanesi destekli) ===
add_action('admin_menu', function () {
    add_submenu_page(null,'Parilté Hızlı Güncelle','Parilté Hızlı Güncelle','manage_woocommerce','parilte-quickedit','parilte_quickedit_page');
    // Ürün Sihirbazı da Araçlar menüsüne
    add_management_page('Parilté Ürün Sihirbazı','Parilté Ürün Sihirbazı','manage_woocommerce','parilte-wizard','parilte_wizard_page');
});
add_action('admin_footer-tools_page_parilte-placeholder', function () {
    if (!current_user_can('manage_woocommerce')) return;
    echo '<p><a class="button button-secondary" href="'.esc_url(admin_url('admin.php?page=parilte-quickedit')).'">Hızlı Güncelle (kategori bazlı)</a> ';
    echo '<a class="button button-primary" href="'.esc_url(admin_url('tools.php?page=parilte-wizard')).'">Ürün Sihirbazı</a></p>';
});

/** Hızlı Güncelle sayfası */
function parilte_quickedit_page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Yetki yok.');
    wp_enqueue_media();
    $cat  = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
    $only_placeholders = isset($_GET['ph']) ? (int) $_GET['ph'] : 1; ?>
    <div class="wrap">
      <h1>Parilté Hızlı Güncelle</h1>
      <form method="get" style="margin:8px 0 16px;">
        <input type="hidden" name="page" value="parilte-quickedit">
        <label>Kategori:
          <select name="cat">
            <option value="0"<?php selected($cat,0); ?>>— Tümü —</option>
            <?php foreach (get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t)
                echo '<option value="'.(int)$t->term_id.'" '.selected($cat,$t->term_id,false).'>'.esc_html($t->name).'</option>'; ?>
          </select>
        </label>
        <label style="margin-left:12px;">
          <input type="checkbox" name="ph" value="1" <?php checked($only_placeholders,1); ?>> Yalnızca placeholder ürünleri göster
        </label>
        <button class="button">Listele</button>
      </form>
      <?php
      $taxq  = $cat ? [['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$cat]] : [];
      $args = [
        'post_type'=>'product','posts_per_page'=>500,'tax_query'=>$taxq,
        'orderby'=>'ID','order'=>'ASC','post_status'=>'any'
      ];
      if ($only_placeholders) $args['meta_query'] = [[ 'key'=>Parilte_Placeholder_Seeder::META_FLAG,'value'=>'1' ]];
      $q = new WP_Query($args);

      // Fallback: meta ile bulunamadıysa başlık aramasıyla çek
      if ($only_placeholders && !$q->have_posts()) {
          unset($args['meta_query']); $args['s'] = 'Placeholder';
          $q = new WP_Query($args);
      }

      if (!$q->have_posts()) { echo '<p>Ürün bulunamadı. (Placeholder üretin, FLAG ONAR yapın veya filtreyi değiştirin.)</p>'; return; } ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('parilte_quick_update'); ?>
        <input type="hidden" name="action" value="parilte_quick_update">
        <table class="widefat striped">
          <thead><tr>
            <th>ID</th><th>SKU</th><th>Ad</th><th>Fiyat</th><th>Stok</th>
            <th>Görsel</th><th>Galeri</th><th>veya URL</th>
            <th>Kısa Açıklama</th><th>Açıklama</th><th>Güncelle</th>
          </tr></thead>
          <tbody>
          <?php while ($q->have_posts()): $q->the_post();
              $pid = get_the_ID(); $p = wc_get_product($pid); $thumb = get_the_post_thumbnail_url($pid, 'thumbnail'); ?>
            <tr>
              <td><?php echo (int)$pid; ?><input type="hidden" name="ids[]" value="<?php echo (int)$pid; ?>"></td>
              <td><?php echo esc_html($p ? $p->get_sku() : ''); ?></td>
              <td><input type="text" name="name[<?php echo (int)$pid; ?>]"  value="<?php echo esc_attr($p ? $p->get_name() : ''); ?>" style="width:180px"></td>
              <td><input type="text" name="price[<?php echo (int)$pid; ?>]" value="<?php echo esc_attr($p ? $p->get_regular_price() : ''); ?>" style="width:80px"></td>
              <td>
                <select name="stock[<?php echo (int)$pid; ?>]">
                  <option value="instock"  <?php selected($p && $p->get_stock_status()==='instock');  ?>>Stokta</option>
                  <option value="outofstock"<?php selected($p && $p->get_stock_status()==='outofstock');?>>Stokta değil</option>
                </select>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <button class="button pick-image" data-pid="<?php echo (int)$pid; ?>">Görsel Seç</button>
                  <input type="hidden" name="image_id[<?php echo (int)$pid; ?>]" value="">
                  <img class="preview-<?php echo (int)$pid; ?>" src="<?php echo esc_url($thumb ?: ''); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #ddd" />
                </div>
              </td>
              <td>
                <?php $gids = $p ? (array) $p->get_gallery_image_ids() : []; ?>
                <div style="display:flex;align-items:center;gap:8px;">
                  <button class="button pick-gallery" data-pid="<?php echo (int)$pid; ?>">Galeri Seç</button>
                  <input type="hidden" name="gallery_ids[<?php echo (int)$pid; ?>]" value="<?php echo esc_attr(implode(',', $gids)); ?>">
                  <div class="gallery-preview-<?php echo (int)$pid; ?>" style="display:flex;gap:4px;flex-wrap:wrap;max-width:140px;">
                    <?php foreach ($gids as $gid): $u = wp_get_attachment_image_url($gid, 'thumbnail'); ?>
                      <img src="<?php echo esc_url($u); ?>" style="width:28px;height:28px;object-fit:cover;border:1px solid #ddd;border-radius:4px">
                    <?php endforeach; ?>
                  </div>
                </div>
              </td>
              <td><input type="url" name="image_url[<?php echo (int)$pid; ?>]" placeholder="https://.../uploads/foto.jpg" style="width:220px"></td>
              <td><textarea name="short[<?php echo (int)$pid; ?>]" rows="2" style="width:200px"><?php echo esc_textarea($p ? $p->get_short_description() : ''); ?></textarea></td>
              <td><textarea name="desc[<?php echo (int)$pid; ?>]"  rows="2" style="width:220px"><?php echo esc_textarea($p ? $p->get_description() : ''); ?></textarea></td>
              <td><label><input type="checkbox" name="do_update[]" value="<?php echo (int)$pid; ?>"> Seç</label></td>
            </tr>
          <?php endwhile; wp_reset_postdata(); ?>
          </tbody>
        </table>
        <p style="margin-top:12px"><button class="button button-primary">Seçili Satırları Kaydet</button></p>
      </form>
    </div>
    <script>
    jQuery(function($){
      let singleFrame;
      $(document).on('click', '.pick-image', function(e){
        e.preventDefault();
        const pid = $(this).data('pid');
        if (!singleFrame) singleFrame = wp.media({ title: 'Görsel Seç', multiple: false, library: { type: 'image' } });
        singleFrame.off('select').on('select', function(){
          const att = singleFrame.state().get('selection').first().toJSON();
          $('input[name="image_id['+pid+']"]').val(att.id);
          $('.preview-'+pid).attr('src', (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url));
        });
        singleFrame.open();
      });

      $(document).on('click', '.pick-gallery', function(e){
        e.preventDefault();
        const pid = $(this).data('pid');
        const frame = wp.media({ title:'Galeri Seç', multiple:true, library:{ type:'image' } });
        frame.on('select', function(){
          const sel = frame.state().get('selection').toJSON();
          const ids = sel.map(x => x.id);
          const $input = $('input[name="gallery_ids['+pid+']"]');
          const $prev  = $('.gallery-preview-'+pid);
          $input.val(ids.join(','));
          $prev.empty();
          sel.slice(0,8).forEach(function(att){
            const url = (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
            $prev.append('<img src="'+url+'" style="width:28px;height:28px;object-fit:cover;border:1px solid #ddd;border-radius:4px">');
          });
          if (sel.length > 8) $prev.append('<span style="font-size:11px;opacity:.7">+'+(sel.length-8)+'</span>');
        });
        frame.open();
      });
    });
    </script>
    <?php
}

add_action('admin_post_parilte_quick_update', function () {
    if (!current_user_can('manage_woocommerce') || !check_admin_referer('parilte_quick_update')) wp_die('Yetki yok.');
    $ids = isset($_POST['do_update']) ? array_map('intval', (array)$_POST['do_update']) : [];
    foreach ($ids as $pid) {
        $p = wc_get_product($pid); if (!$p) continue;
        $name  = sanitize_text_field($_POST['name'][$pid]  ?? '');
        $price = wc_clean($_POST['price'][$pid]            ?? '');
        $stock = sanitize_text_field($_POST['stock'][$pid] ?? 'instock');
        $short = wp_kses_post($_POST['short'][$pid]        ?? '');
        $desc  = wp_kses_post($_POST['desc'][$pid]         ?? '');
        $imgId = isset($_POST['image_id'][$pid]) ? (int)$_POST['image_id'][$pid] : 0;
        $img   = esc_url_raw($_POST['image_url'][$pid]     ?? '');
        $graw  = $_POST['gallery_ids'][$pid] ?? '';
        $gids  = array_filter(array_map('intval', explode(',', (string)$graw)));

        if ($name !== '')  $p->set_name($name);
        if ($price !== '') $p->set_regular_price($price);
        if ($stock)        $p->set_stock_status($stock);
        $p->set_catalog_visibility('visible');

        // Galeri (çoklu)
        if (!empty($gids) || $graw==='') $p->set_gallery_image_ids($gids);

        $p->save();
        wp_update_post(['ID'=>$pid,'post_excerpt'=>$short,'post_content'=>$desc]);

        if ($imgId) {
            set_post_thumbnail($pid, $imgId);
        } elseif ($img) {
            $aid = attachment_url_to_postid($img);
            if ($aid) set_post_thumbnail($pid, $aid);
        }

        if (($name !== '') || ($price !== '') || $short || $desc || get_post_thumbnail_id($pid) || !empty($gids)) {
            delete_post_meta($pid, Parilte_Placeholder_Seeder::META_FLAG);
        }
    }
    wp_redirect(add_query_arg(['page'=>'parilte-quickedit','updated'=>'1'], admin_url('admin.php')));
    exit;
});

// === ÜRÜN SİHİRBAZI ===
function parilte_wizard_page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Yetki yok.');
    wp_enqueue_media();

    // Taksonomiler
    $cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
    $sizes = taxonomy_exists('pa_beden') ? get_terms(['taxonomy'=>'pa_beden','hide_empty'=>false]) : [];
    $colors= taxonomy_exists('pa_renk')  ? get_terms(['taxonomy'=>'pa_renk','hide_empty'=>false])  : [];

    // Placeholder listesi (+fallback)
    $ph_args = [
        'post_type'      => 'product',
        'posts_per_page' => 200,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'post_status'    => 'any',
        'meta_query'     => [
            [ 'key'=>Parilte_Placeholder_Seeder::META_FLAG, 'value'=>'1' ],
        ],
    ];
    $ph_q = new WP_Query($ph_args);

    // Fallback: meta yoksa başlığında "Placeholder" geçen ürünleri topla
    if (!$ph_q->have_posts()) {
        $ph_q = new WP_Query([
            'post_type'      => 'product',
            'posts_per_page' => 200,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'post_status'    => 'any',
            's'              => 'Placeholder',
        ]);
    }
    ?>
    <div class="wrap">
      <h1>Parilté Ürün Sihirbazı</h1>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('parilte_wizard_save'); ?>
        <input type="hidden" name="action" value="parilte_wizard_save">

        <h2 style="margin-top:12px">Mod</h2>
        <label><input type="radio" name="mode" value="create" checked> Yeni ürün oluştur</label>
        <label style="margin-left:16px"><input type="radio" name="mode" value="convert"> Placeholder’ı dönüştür</label>

        <div id="convertBox" style="margin:10px 0 16px; display:none">
          <label>Placeholder seç:
            <select name="convert_id">
              <option value="">— Seçin —</option>
              <?php if ($ph_q->have_posts()): while ($ph_q->have_posts()): $ph_q->the_post(); $pid=get_the_ID(); ?>
                <option value="<?php echo (int)$pid; ?>">#<?php echo (int)$pid; ?> — <?php echo esc_html(get_the_title()); ?></option>
              <?php endwhile; else: ?>
                <option value="" disabled>Placeholder bulunamadı — Araçlar → Parilté Placeholder’dan üretin veya FLAG ONAR yapın.</option>
              <?php endif; wp_reset_postdata(); ?>
            </select>
          </label>
        </div>

        <h2>Temel</h2>
        <p style="display:grid;grid-template-columns:repeat(4, minmax(180px, 1fr));gap:12px;">
          <label>Ad <input type="text" name="name" required></label>
          <label>Kategori
            <select name="cat" required>
              <option value="">— Seçin —</option>
              <?php foreach ($cats as $t) echo '<option value="'.(int)$t->term_id.'">'.esc_html($t->name).'</option>'; ?>
            </select>
          </label>
          <label>Tür
            <select name="ptype" id="ptype">
              <option value="simple">Basit (simple)</option>
              <option value="variable">Varyasyonlu (variable)</option>
            </select>
          </label>
          <label>Fiyat (₺) <input type="text" name="price" placeholder="999"></label>
        </p>

        <h2>Görseller</h2>
        <div style="display:flex;gap:24px;align-items:center;margin-bottom:8px">
          <div>
            <button class="button" id="pickCover">Kapak Görseli Seç</button>
            <input type="hidden" name="image_id" id="image_id" value="">
            <div><img id="coverPreview" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;border-radius:6px;margin-top:6px"></div>
          </div>
          <div>
            <button class="button" id="pickGallery">Galeri Seç (çoklu)</button>
            <input type="hidden" name="gallery_ids" id="gallery_ids" value="">
            <div id="galleryPreview" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px"></div>
          </div>
        </div>

        <div id="varBox" style="display:none">
          <h2>Varyasyon Seçimleri</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div><strong>Beden</strong><br>
              <?php if ($sizes) {
                  foreach ($sizes as $s) echo '<label style="display:inline-block;margin:4px 8px 0 0;"><input type="checkbox" name="sizes[]" value="'.esc_attr($s->term_id).'"> '.esc_html($s->name).'</label>';
              } else {
                  echo '<em>pa_beden taksonomisi yok/boş.</em>';
              } ?>
            </div>
            <div><strong>Renk</strong><br>
              <?php if ($colors) {
                  foreach ($colors as $c) echo '<label style="display:inline-block;margin:4px 8px 0 0;"><input type="checkbox" name="colors[]" value="'.esc_attr($c->term_id).'"> '.esc_html($c->name).'</label>';
              } else {
                  echo '<em>pa_renk taksonomisi yok/boş.</em>';
              } ?>
            </div>
          </div>
          <p style="margin-top:8px;display:grid;grid-template-columns:repeat(3, minmax(180px,1fr));gap:12px;">
            <label>Varyasyon fiyatı (₺) <input type="text" name="var_price" placeholder="999"></label>
            <label>Varsayılan stok durumu
              <select name="var_stock">
                <option value="instock">Stokta</option>
                <option value="outofstock">Stokta değil</option>
              </select>
            </label>
            <label>Seçili kombinasyonlar oluşturulacak; var olan korunur.</label>
          </p>
        </div>

        <p style="margin-top:12px">
          <button class="button button-primary">Kaydet</button>
        </p>
      </form>
    </div>
    <script>
      jQuery(function($){
        function toggleBoxes(){
          const ptype = $('#ptype').val();
          $('#varBox').toggle(ptype==='variable');
          const mode = $('input[name="mode"]:checked').val();
          $('#convertBox').toggle(mode==='convert');
        }
        $('input[name="mode"]').on('change', toggleBoxes);
        $('#ptype').on('change', toggleBoxes);
        toggleBoxes();

        let coverFrame, galleryFrame;
        $('#pickCover').on('click', function(e){
          e.preventDefault();
          if (!coverFrame) coverFrame = wp.media({ title:'Kapak Görseli', multiple:false, library:{type:'image'} });
          coverFrame.off('select').on('select', function(){
            const att = coverFrame.state().get('selection').first().toJSON();
            $('#image_id').val(att.id);
            $('#coverPreview').attr('src', (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url));
          });
          coverFrame.open();
        });
        $('#pickGallery').on('click', function(e){
          e.preventDefault();
          galleryFrame = wp.media({ title:'Galeri', multiple:true, library:{type:'image'} });
          galleryFrame.on('select', function(){
            const sel = galleryFrame.state().get('selection').toJSON();
            const ids = sel.map(x => x.id);
            $('#gallery_ids').val(ids.join(','));
            $('#galleryPreview').empty();
            sel.slice(0,10).forEach(function(att){
                const url = (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
                $('#galleryPreview').append('<img src="'+url+'" style="width:40px;height:40px;object-fit:cover;border:1px solid #ddd;border-radius:6px">');
            });
          });
          galleryFrame.open();
        });
      });
    </script>
<?php }

add_action('admin_post_parilte_wizard_save', 'parilte_wizard_save');

/** Ürün Sihirbazı kaydet */
function parilte_wizard_save() {
    if (!current_user_can('manage_woocommerce') || !check_admin_referer('parilte_wizard_save')) wp_die('Yetki yok.');

    $mode   = sanitize_text_field($_POST['mode'] ?? 'create');
    $name   = sanitize_text_field($_POST['name'] ?? '');
    $cat_id = (int)($_POST['cat'] ?? 0);
    $ptype  = sanitize_text_field($_POST['ptype'] ?? 'simple');
    $price  = wc_clean($_POST['price'] ?? '');
    $image_id = (int)($_POST['image_id'] ?? 0);
    $graw     = (string)($_POST['gallery_ids'] ?? '');
    $gids     = array_filter(array_map('intval', explode(',', $graw)));

    $sizes  = array_map('intval', (array)($_POST['sizes']  ?? []));
    $colors = array_map('intval', (array)$_POST['colors'] ?? []);
    $var_price = wc_clean($_POST['var_price'] ?? '');
    $var_stock = sanitize_text_field($_POST['var_stock'] ?? 'instock');

    // Taksonomileri hazırla
    if (isset($GLOBALS['parilte_placeholder_seeder'])) {
        $GLOBALS['parilte_placeholder_seeder']->ensure_attribute_taxonomy('Beden', 'pa_beden');
        $GLOBALS['parilte_placeholder_seeder']->ensure_attribute_taxonomy('Renk',  'pa_renk');
    }

    $pid = 0;

    if ($mode === 'convert') {
        $pid = (int)($_POST['convert_id'] ?? 0);
        if (!$pid) {
            wp_redirect(add_query_arg(['page'=>'parilte-wizard','err'=>'select_placeholder'], admin_url('tools.php')));
            exit;
        }
    }

    if ($mode === 'create') {
        // Boş ürün oluştur
        $pid = wp_insert_post([
            'post_type'=>'product','post_status'=>'publish','post_title'=>$name ?: 'Yeni Ürün'
        ]);
    }

    if ($pid) {
        // Temel alanlar
        if ($name) wp_update_post(['ID'=>$pid,'post_title'=>$name]);
        if ($cat_id) wp_set_object_terms($pid, (int)$cat_id, 'product_cat', false);

        // Tür
        $ptype_term = ($ptype==='variable') ? 'variable' : 'simple';
        wp_set_object_terms($pid, $ptype_term, 'product_type', false);

        $p = wc_get_product($pid);
        if ($p) {
            if ($price !== '' && $ptype!=='variable') { // variable fiyatı varyasyonlarda
                $p->set_regular_price($price);
            }
            $p->set_catalog_visibility('visible');
            if ($image_id) set_post_thumbnail($pid, $image_id);
            if (!empty($gids) || $graw==='') $p->set_gallery_image_ids($gids);
            $p->save();
        }

        // Varyasyon kurulum (gerekirse)
        if ($ptype==='variable') {
            parilte_assign_attrs_and_generate_vars($pid, $sizes, $colors, $var_price, $var_stock);
        }

        // Placeholder bayrağını bırak
        delete_post_meta($pid, Parilte_Placeholder_Seeder::META_FLAG);

        wp_redirect(add_query_arg(['page'=>'parilte-wizard','done'=>'1','pid'=>$pid], admin_url('tools.php')));
        exit;
    }

    wp_redirect(add_query_arg(['page'=>'parilte-wizard','err'=>'unknown'], admin_url('tools.php')));
    exit;
}

/** Varyasyon: attribute ata ve eksik kombinasyonları oluştur */
function parilte_assign_attrs_and_generate_vars($parent_id, $size_term_ids, $color_term_ids, $price, $stock_status='instock') {
    $parent = wc_get_product($parent_id);
    if (!$parent) return;

    // Ebeveyni variable olarak işaretle
    wp_set_object_terms($parent_id, 'variable', 'product_type', false);

    // Attribute nesneleri (variation=true)
    $attrs = [];

    if ($size_term_ids) {
        $attr = new WC_Product_Attribute();
        $attr->set_id(0);
        $attr->set_name('pa_beden');
        $attr->set_options(array_map('intval', $size_term_ids));
        $attr->set_visible(true);
        $attr->set_variation(true);
        $attrs[] = $attr;
    }
    if ($color_term_ids) {
        $attr = new WC_Product_Attribute();
        $attr->set_id(0);
        $attr->set_name('pa_renk');
        $attr->set_options(array_map('intval', $color_term_ids));
        $attr->set_visible(true);
        $attr->set_variation(true);
        $attrs[] = $attr;
    }

    $parent->set_attributes($attrs);
    $parent->save();

    // Mevcut varyasyonlar ve attribute eşleşmesi
    $existing = [];
    foreach ($parent->get_children() as $vid) {
        $v = wc_get_product($vid);
        if (!$v) continue;
        $atts = $v->get_attributes(); // ['pa_beden'=>'m','pa_renk'=>'siyah']
        $key  = json_encode($atts);
        $existing[$key] = $vid;
    }

    // Kombinasyon kümesi (tek eksenli de olabilir)
    $size_terms  = array_map(function($id){ $t=get_term($id,'pa_beden'); return $t?$t->slug:null; }, $size_term_ids);
    $color_terms = array_map(function($id){ $t=get_term($id,'pa_renk');  return $t?$t->slug:null; }, $color_term_ids);
    $size_terms  = array_values(array_filter($size_terms));
    $color_terms = array_values(array_filter($color_terms));

    if (!$size_terms && !$color_terms) return;

    if (!$size_terms)  $size_terms = [ null ];
    if (!$color_terms) $color_terms= [ null ];

    foreach ($size_terms as $s) {
        foreach ($color_terms as $c) {
            $att = [];
            if ($s !== null) $att['pa_beden'] = $s;
            if ($c !== null) $att['pa_renk']  = $c;
            $key = json_encode($att);
            if (isset($existing[$key])) continue; // var

            $v = new WC_Product_Variation();
            $v->set_parent_id($parent_id);
            $v->set_attributes($att);
            if ($price !== '') $v->set_regular_price($price);
            $v->set_stock_status($stock_status);
            $v_id = $v->save();
        }
    }

    if (class_exists('WC_Product_Variable')) {
        WC_Product_Variable::sync($parent_id); // fiyat/stock aralığı senk
    }
}

// === MAĞAZA/KATEGORİ GRID UI ===

// 1) "Add to Cart / Read More" gizle (görsel/başlık linki kalsın)
add_filter('woocommerce_loop_add_to_cart_link', function ($html, $product, $args) {
    if (is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag()) return '';
    return $html;
}, 10, 3);

// 1.5) Placeholder’lar satın alınamaz (tekil ürün sayfasında da)
add_filter('woocommerce_is_purchasable', function ($purchasable, $product) {
    if (!$product instanceof WC_Product) return $purchasable;
    if (get_post_meta($product->get_id(), Parilte_Placeholder_Seeder::META_FLAG, true)==='1') return false;
    return $purchasable;
}, 10, 2);

// Placeholder urunleri katalogdan gizle
add_filter('woocommerce_product_query_meta_query', function ($meta_query) {
    $meta_query[] = [
        'key' => Parilte_Placeholder_Seeder::META_FLAG,
        'compare' => 'NOT EXISTS',
    ];
    return $meta_query;
});

add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if ($q->get('post_type') !== 'product' && !$q->is_post_type_archive('product') && !$q->is_tax('product_cat') && !$q->is_tax('product_tag')) return;
    $mq = (array) $q->get('meta_query');
    $mq[] = [
        'key' => Parilte_Placeholder_Seeder::META_FLAG,
        'compare' => 'NOT EXISTS',
    ];
    $q->set('meta_query', $mq);
});

function parilte_resolve_attr_labels($attr, $taxonomy) {
    $labels = [];
    foreach ((array) $attr->get_options() as $opt) {
        $label = $opt;
        if (taxonomy_exists($taxonomy)) {
            if (is_numeric($opt)) {
                $t = get_term((int) $opt, $taxonomy);
            } else {
                $t = get_term_by('name', $opt, $taxonomy);
            }
            if ($t && !is_wp_error($t)) $label = $t->name;
        }
        $labels[] = $label;
    }
    return array_values(array_filter($labels));
}

// 2) Beden/Renk rozetleri (yalnız mevcut varyasyonlar + stok duyarlı)
add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    if (!$product instanceof WC_Product) return;
    if (!is_product()) return;

    $pid = $product->get_id();
    $permalink = get_permalink($pid);

    $sizes_present  = [];  // label => true
    $colors_present = [];
    $size_stock_on  = [];  // label => bool
    $color_stock_on = [];

    if ($product->is_type('variable')) {
        foreach ($product->get_children() as $vid) {
            $v = wc_get_product($vid);
            if (!$v) continue;
            $in = $v->is_in_stock();
            $attrs = $v->get_attributes(); // ['pa_beden'=>'m','pa_renk'=>'siyah']

            if (!empty($attrs['pa_beden'])) {
                $t = get_term_by('slug', $attrs['pa_beden'], 'pa_beden');
                if ($t && !is_wp_error($t)) {
                    $label = $t->name;
                    $sizes_present[$label] = true;
                    $size_stock_on[$label] = !empty($size_stock_on[$label]) || $in;
                }
            }
            if (!empty($attrs['pa_renk'])) {
                $t = get_term_by('slug', $attrs['pa_renk'], 'pa_renk');
                if ($t && !is_wp_error($t)) {
                    $label = $t->name;
                    $colors_present[$label] = true;
                    $color_stock_on[$label] = !empty($color_stock_on[$label]) || $in;
                }
            }
        }
    } else {
        // Simple ürün: ürün üzerindeki attribute değerlerini baz al
        foreach ($product->get_attributes() as $attr) {
            if ($attr->get_name()==='pa_beden') {
                if ($attr->is_taxonomy()) {
                    foreach ($attr->get_options() as $term_id) {
                        $t = get_term($term_id, 'pa_beden');
                        if ($t && !is_wp_error($t)) { $sizes_present[$t->name] = true; $size_stock_on[$t->name] = $product->is_in_stock(); }
                    }
                } else {
                    foreach (parilte_resolve_attr_labels($attr, 'pa_beden') as $label) {
                        $sizes_present[$label] = true;
                        $size_stock_on[$label] = $product->is_in_stock();
                    }
                }
            }
            if ($attr->get_name()==='pa_renk') {
                if ($attr->is_taxonomy()) {
                    foreach ($attr->get_options() as $term_id) {
                        $t = get_term($term_id, 'pa_renk');
                        if ($t && !is_wp_error($t)) { $colors_present[$t->name] = true; $color_stock_on[$t->name] = $product->is_in_stock(); }
                    }
                } else {
                    foreach (parilte_resolve_attr_labels($attr, 'pa_renk') as $label) {
                        $colors_present[$label] = true;
                        $color_stock_on[$label] = $product->is_in_stock();
                    }
                }
            }
        }
    }

    echo '<div class="parilte-card-badges" aria-label="Ürün seçenekleri">';

    // Beden (yalnız mevcutlar)
    if (!empty($sizes_present)) {
        echo '<div class="parilte-badge-row">';
        foreach (array_keys($sizes_present) as $s) {
            $is_on = !empty($size_stock_on[$s]);
            $href  = esc_url(add_query_arg(['attribute_pa_beden'=>sanitize_title($s)], $permalink));
            if ($is_on) {
                echo '<a class="parilte-badge on size" href="'.$href.'" title="Beden: '.esc_attr($s).'">'.esc_html($s).'</a>';
            } else {
                echo '<span class="parilte-badge off size" title="Stokta yok">'.esc_html($s).'</span>';
            }
        }
        echo '</div>';
    }

    // Renk (yalnız mevcutlar + gerçek renk çipi)
    if (!empty($colors_present)) {
        echo '<div class="parilte-badge-row">';
        foreach (array_keys($colors_present) as $c) {
            $is_on = !empty($color_stock_on[$c]);
            $href  = esc_url(add_query_arg(['attribute_pa_renk'=>sanitize_title($c)], $permalink));
            $hex   = isset($GLOBALS['parilte_placeholder_seeder']) ? $GLOBALS['parilte_placeholder_seeder']->color_hex($c) : '#E5E5E5';
            $style = 'style="--chip-color: '.$hex.';"';
            if ($is_on) {
                echo '<a class="parilte-badge on color" '.$style.' href="'.$href.'" title="Renk: '.esc_attr($c).'"><span class="dot"></span>'.esc_html($c).'</a>';
            } else {
                echo '<span class="parilte-badge off color" '.$style.' title="Stokta yok"><span class="dot"></span>'.esc_html($c).'</span>';
            }
        }
        echo '</div>';
    }

    echo '</div>';
}, 12);

// 3) Kart stilleri
add_action('wp_enqueue_scripts', function () {
    $css = '
    .woocommerce ul.products li.product a.button,
    .woocommerce ul.products li.product .add_to_cart_button,
    .woocommerce ul.products li.product .ct-cart-button,
    .wc-block-grid__product-add-to-cart { display:none !important; }

    .parilte-card-badges{ margin-top:6px; display:flex; flex-direction:column; gap:6px; }
    .parilte-badge-row{ display:flex; flex-wrap:wrap; gap:6px; }

    .parilte-badge{
        display:inline-flex; align-items:center; justify-content:center;
        min-width:36px; height:30px; padding:0 10px;
        border:1px solid #e6e6e6; border-radius:8px;
        font-size:12px; line-height:28px; letter-spacing:.02em; text-decoration:none;
        transition:transform .12s ease, box-shadow .12s ease, opacity .12s ease;
        user-select:none; background:#fff; color:#111;
    }
    .parilte-badge.size{ font-weight:600; }
    .parilte-badge.on{ opacity:1; cursor:pointer; box-shadow:0 0 0 0 rgba(0,0,0,0); }
    .parilte-badge.on:hover{ transform:translateY(-1px); box-shadow:0 2px 8px rgba(0,0,0,.06); }
    .parilte-badge.off{ opacity:.35; cursor:not-allowed; pointer-events:none; }

    .parilte-badge.color{ position:relative; padding-left:8px; }
    .parilte-badge.color .dot{
        width:12px; height:12px; border-radius:999px; margin-right:6px;
        background: var(--chip-color, #ddd); display:inline-block; border:1px solid rgba(0,0,0,.08);
    }
    ';
    wp_register_style('parilte-placeholder-front', false);
    wp_enqueue_style('parilte-placeholder-front');
    wp_add_inline_style('parilte-placeholder-front', $css);
}, 20);
