<?php /* Template: Parilté Front */ get_header(); ?>
<main id="primary" class="site-main parilte-front">
  <section class="parilte-hero" style="padding:8vh 0;">
    <div class="container" style="max-width:1140px;margin:0 auto;padding:0 16px;">
      <h1 style="margin:0 0 6px 0;">Tüm Kadınlar Parıldasın Diye…</h1>
      <p style="max-width:720px;opacity:.9">Sezonun seçkisi, zamansız parçalar ve günlük stil ipuçları: Parilté Editör’den ilham alın.</p>
    </div>
  </section>
  <section class="parilte-cats" style="padding:24px 0;">
    <div class="container" style="max-width:1140px;margin:0 auto;padding:0 16px; display:grid; gap:16px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
      <?php
        $cats = [
          ['Üst Giyim','/kategori/ust-giyim/'],
          ['Alt Giyim','/kategori/alt-giyim/'],
          ['Aksesuar','/kategori/aksesuar/'],
          ['Dış Giyim','/kategori/dis-giyim/'],
        ];
        foreach ($cats as $c){
          echo '<a class="parilte-cat-card" href="'.esc_url(home_url($c[1])).'" style="display:block;border-radius:14px;border:1px solid #eee;padding:18px;text-decoration:none;">
                  <strong style="display:block;margin-bottom:4px">'.$c[0].'</strong>
                  <span style="opacity:.8">Koleksiyonu keşfet →</span>
                </a>';
        }
      ?>
    </div>
  </section>
  <section class="parilte-blog" style="padding:24px 0 56px;">
    <div class="container" style="max-width:1140px;margin:0 auto;padding:0 16px;">
      <h2 style="margin:0 0 12px 0;">Parilté Editör</h2>
      <div class="parilte-blog-grid" style="display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
        <?php $q = new WP_Query(['post_type'=>'post','posts_per_page'=>6]);
        if ($q->have_posts()): while($q->have_posts()): $q->the_post(); ?>
          <article class="parilte-post" style="border:1px solid #eee;border-radius:14px;overflow:hidden;">
            <a href="<?php the_permalink(); ?>" style="text-decoration:none;color:inherit;">
              <?php if (has_post_thumbnail()) the_post_thumbnail('large'); ?>
              <div style="padding:12px 14px;">
                <h3 style="margin:.2rem 0 0;font-size:1.05rem;"><?php the_title(); ?></h3>
                <time style="opacity:.7;font-size:.9rem;"><?php echo get_the_date(); ?></time>
              </div>
            </a>
          </article>
        <?php endwhile; wp_reset_postdata(); else: echo '<p>Yakında editör yazıları burada.</p>'; endif; ?>
      </div>
    </div>
  </section>
</main>
<?php get_footer();
