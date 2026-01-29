# Parilte Child (Blocksy)

## Kurulum
- `wp-content/themes/parilte-child` klasorunu tema dizinine kopyalayin.
- WP Admin -> Gorunum -> Temalar'dan **Parilte Child** temasini etkinlestirin.
- Admin yenilendiginde "Parilte Bootstrap tamamlandi" bildirimi gorunmelidir.

## Header: Hesap + Arama
- Varsayilan: **Parilte Checkout Suite** aktifse ve `PARILTE_AUTO_HEADER` true ise header ikonlari otomatik eklenir.
- Manuel kurulum isterseniz: Gorunum -> Ozellestir -> Header
  - HTML ogesi ekleyin ve icerik olarak `\[parilte_header]` yazin.
  - Ogeleri kategori menusunun sagina tasiyip yayinlayin.

## Katalog yonetimi
- Urunler: Urun ekle, kategori ata, gorsel ekle.
- Nitelikler: Beden (XS-XL) ve Renk (Mavi, Siyah, Beyaz, Gri, Bej, Haki).
- Degisken urun: Beden/Renk varyasyonlari olusturun.

## Tasima
- AIO WP Migration ile export/import.
- Domain degisikligi icin search/replace uygulayin.

## Onerilen eklentiler
- Local Google Fonts/OMGF (ya da sistem fontu)
- Cache eklentisi
- SMTP (WP Mail SMTP)
