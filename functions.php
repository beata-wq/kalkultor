<?php
/**
 * ========= KONFIGURACJA DODATKÓW PRODUKTU + LOGIKA KALKULATORA =========
 * - Drzwi szklane przesuwne
 * - Cena bazowa produktu w Woo = "cena minimalna" z Excela
 * - Doliczanie:
 *    - metraż powyżej min. (941 PLN/m2)
 *    - typ szkła (150 PLN/m2 dla wariantów premium)
 *    - uchwyt/klamka (0 / 80 / 100 / 200 PLN)
 *    - profil stalowy Retro (+5%)
 *    - liczba szyb (wg wariantu `pa_liczba-szyb`)
 * - Wymiary:
 *    - domyślnie z meta: _bm_wysokosc_mm, _bm_szerokosc_mm (lub fallback)
 *    - user może zmienić je w inputach
 */

add_filter('woocommerce_get_price_including_tax', 'round_price_no_cents', 10, 1);
add_filter('woocommerce_get_price_excluding_tax', 'round_price_no_cents', 10, 1);
add_filter('raw_woocommerce_price', 'round_price_no_cents', 10, 1);

function round_price_no_cents($price) {
    return round($price, 0);
}
add_filter('woocommerce_calculated_total', 'round_cart_total', 10, 2);

function round_cart_total($total, $cart) {
    return round($total, 0);
}
add_filter( 'gettext', 'bm_translate_woo_strings', 999, 3 );
function bm_translate_woo_strings( $translated, $text, $domain ) {

    if ( $domain === 'woocommerce' ) {
        if ( $text === 'Create an account?' ) {
            return 'Zarejestruj się?';
        }
        if ( $text === 'Create an account' ) {
            return 'Zarejestruj się';
        }
    }

    return $translated;
}
add_action( 'wp_footer', function() {
    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        function translateWooBlocks() {

            /* --- Tłumaczenie "Estimated total" --- */
            var labels = document.querySelectorAll('.wc-block-components-totals-item__label');
            labels.forEach(function (el) {
                if (el.textContent.trim() === 'Estimated total') {
                    el.textContent = 'Szacowana suma';
                }
            });

            /* --- Tłumaczenie "Add coupons" --- */
            var couponButtons = document.querySelectorAll('.wc-block-components-panel__button-icon, .wc-block-components-panel__button');
            couponButtons.forEach(function (el) {
                var text = el.textContent.trim();
                if (text === 'Add coupons') {
                    el.textContent = 'Dodaj kupon';
                }
            });

        }

        // Pierwsze odpalenie
        translateWooBlocks();

        // Woo Blocks często przebudowują DOM – obserwujemy zmiany
        var observer = new MutationObserver(function () {
            translateWooBlocks();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    <?php
});

// Wymuś kolejność termów dla atrybutu pa_liczba-szyb
add_filter( 'woocommerce_get_product_terms', 'bm_order_liczba_szyb_terms', 10, 4 );
function bm_order_liczba_szyb_terms( $terms, $object_ids, $taxonomies, $args ) {
    // Interesuje nas tylko front (nie panel)
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $terms;
    }

    // Jeśli to nie jest nasz atrybut, zostaw jak jest
    if ( ! in_array( 'pa_liczba-szyb', (array) $taxonomies, true ) ) {
        return $terms;
    }

    if ( empty( $terms ) ) {
        return $terms;
    }

    // Kolejność wg sluga (takie slugi mają termy liczba-szyb)
    $order_map = array(
        '1-jedna'                             => 10,
        'dwie'                                => 20,
        'trzy'                                => 30,
        'trzy-styl-nowoczesny'                => 35,

        'cztery'                              => 40,
        'cztery-asymetrycznie'                => 45,
        'cztery-z-dlugimi-panelami'           => 50,
        'cztery-podzielone-przez-luk'         => 55,
        'cztery-z-krotkimi-panelami'          => 60,
        'cztery-z-krotkimi-panelami-gornymi'  => 65,

        'szesc'                               => 70,
        'szesc-asymetrycznie'                 => 75,
        'szesc-podzielonych-przez-luk'        => 80,

        'osiem'                               => 90,
        'rama-offsetowa'                      => 100,
    );

    usort( $terms, function( $a, $b ) use ( $order_map ) {
        $a_slug = $a->slug;
        $b_slug = $b->slug;

        $a_order = isset( $order_map[ $a_slug ] ) ? $order_map[ $a_slug ] : 9999;
        $b_order = isset( $order_map[ $b_slug ] ) ? $order_map[ $b_slug ] : 9999;

        if ( $a_order === $b_order ) {
            return 0;
        }

        return ( $a_order < $b_order ) ? -1 : 1;
    } );

    return $terms;
}


// Zmiana tekstu przycisku "Dodaj do koszyka" → "Złóż zapytanie ofertowe"
// tylko dla produktów z kategorii: drzwi, drzwi-przesuwne
add_filter( 'woocommerce_product_single_add_to_cart_text', 'bm_custom_add_to_cart_text', 10, 2 );
add_filter( 'woocommerce_product_add_to_cart_text',        'bm_custom_add_to_cart_text', 10, 2 );

function bm_custom_add_to_cart_text( $text, $product ) {

    if ( ! $product instanceof WC_Product ) {
        return $text;
    }

    // kategorie, dla których zmieniamy tekst
    $eligible_categories = array( 'drzwi', 'drzwi-przesuwne', 'nieruchome-segmenty' );

    if ( has_term( $eligible_categories, 'product_cat', $product->get_id() ) ) {
        return 'Złóż zapytanie ofertowe';
    }

    return $text;
}
// 1. Zmiana "Proceed to checkout" na koszyku
add_filter( 'woocommerce_proceed_to_checkout', 'bm_custom_proceed_to_checkout_text' );
function bm_custom_proceed_to_checkout_text( $text ) {
    return 'Potwierdź zapytanie';
}

// 2. Zmiana "Place order" na przycisku na checkout
add_filter( 'woocommerce_order_button_text', 'bm_custom_order_button_text' );
function bm_custom_order_button_text( $text ) {
    return 'Potwierdź zapytanie';
}
// Zmiana tekstu "Return to cart" na "Wróć do koszyka"
add_filter( 'woocommerce_return_to_shop_text', 'bm_return_to_cart_text' );
add_filter( 'woocommerce_return_to_cart_text', 'bm_return_to_cart_text' );

function bm_return_to_cart_text( $text ) {
    return 'Wróć do koszyka';
}

// Zmiana tekstu "Place order" na "Złóż zapytanie"
add_filter( 'woocommerce_order_button_text', 'bm_custom_place_order_text' );
function bm_custom_place_order_text( $text ) {
    return 'Złóż zapytanie';
}
// Globalna podmiana tekstów WooCommerce (klasyczne i blokowe checkout/koszyk)
add_filter( 'gettext', 'bm_change_woocommerce_strings', 10, 3 );
add_filter( 'ngettext', 'bm_change_woocommerce_strings', 10, 3 );

function bm_change_woocommerce_strings( $translated, $text, $domain ) {

    // Interesuje nas tylko WooCommerce
    if ( $domain !== 'woocommerce' ) {
        return $translated;
    }

    // === Place order → Złóż zapytanie ===
    // EN (klasyczny / blokowy)
    if ( $text === 'Place order' ) {
        return 'Złóż zapytanie';
    }
    // PL (tłumaczenie Woo może być takie)
    if ( $text === 'Zamawiam i płacę' ) {
        return 'Złóż zapytanie';
    }

    // === Proceed to checkout → Potwierdź zapytanie ===
    if ( $text === 'Proceed to checkout' ) {
        return 'Potwierdź zapytanie';
    }
    if ( $text === 'Przejdź do kasy' ) {
        return 'Potwierdź zapytanie';
    }

    // === Return to cart → Wróć do koszyka ===
    if ( $text === 'Return to cart' ) {
        return 'Wróć do koszyka';
    }
    if ( $text === 'Powrót do koszyka' ) {
        return 'Wróć do koszyka';
    }

    return $translated;
}

if ( ! defined( 'BM_DOOR_MIN_AREA' ) ) {
    define( 'BM_DOOR_MIN_AREA', 0.75 );      // min [m2]
}
if ( ! defined( 'BM_DOOR_MAX_AREA' ) ) {
    define( 'BM_DOOR_MAX_AREA', 2.75 );      // max [m2] – zabezpieczenie
}
if ( ! defined( 'BM_DOOR_PRICE_PER_M2_OVER_MIN' ) ) {
    define( 'BM_DOOR_PRICE_PER_M2_OVER_MIN', 1314 ); // PLN/m2 za powyżej min
}
if ( ! defined( 'BM_DOOR_PROFILE_RETRO_PERCENT' ) ) {
    define( 'BM_DOOR_PROFILE_RETRO_PERCENT', 0.01 ); // 1% dla profilu Retro
}

function bm_get_product_setting_float( $product_id, $meta_key, $default ) {
    if ( ! $product_id ) {
        return $default;
    }

    $value = get_post_meta( $product_id, $meta_key, true );
    if ( $value === '' || $value === null ) {
        return $default;
    }

    return (float) $value;
}

function bm_get_door_min_area( $product_id ) {
    if ( ! $product_id ) {
        return BM_DOOR_MIN_AREA;
    }

    $raw_min_area = get_post_meta( $product_id, 'bm_door_min_area', true );
    if ( $raw_min_area !== '' && $raw_min_area !== null ) {
        return (float) $raw_min_area;
    }

    $height_mm = (float) get_post_meta( $product_id, 'bm_wysokosc_mm', true );
    $width_mm  = (float) get_post_meta( $product_id, 'bm_szerokosc_mm', true );
    if ( $height_mm <= 0 ) {
        $height_mm = (float) get_post_meta( $product_id, '_bm_wysokosc_mm', true );
    }
    if ( $width_mm <= 0 ) {
        $width_mm = (float) get_post_meta( $product_id, '_bm_szerokosc_mm', true );
    }

    if ( $height_mm > 0 && $width_mm > 0 ) {
        return ( $height_mm * $width_mm ) / 1000000;
    }

    return BM_DOOR_MIN_AREA;
}

function bm_get_door_max_area( $product_id ) {
    $max_area = bm_get_product_setting_float( $product_id, 'bm_door_max_area', BM_DOOR_MAX_AREA );
    $min_area = bm_get_door_min_area( $product_id );

    if ( $max_area < $min_area ) {
        return $min_area;
    }

    return $max_area;
}

function bm_get_door_price_per_m2_over_min( $product_id ) {
    return bm_get_product_setting_float( $product_id, 'bm_door_price_per_m2_over_min', BM_DOOR_PRICE_PER_M2_OVER_MIN );
}

function bm_get_door_profile_retro_percent( $product_id ) {
    return bm_get_product_setting_float( $product_id, 'bm_door_profile_retro_percent', BM_DOOR_PROFILE_RETRO_PERCENT );
}

function bm_product_has_no_quote( $product_id ) {
    if ( ! $product_id ) {
        return false;
    }

    $value = get_post_meta( $product_id, 'bez_wyceny', true );
    return is_string( $value ) && strtolower( trim( $value ) ) === 'tak';
}

add_filter( 'woocommerce_get_price_html', 'bm_hide_price_when_no_quote', 10, 2 );
function bm_hide_price_when_no_quote( $price_html, $product ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $price_html;
    }

    if ( ! $product instanceof WC_Product ) {
        return $price_html;
    }

    if ( ! is_product() ) {
        return $price_html;
    }

    if ( bm_product_has_no_quote( $product->get_id() ) ) {
        return '';
    }

    return $price_html;
}

add_filter( 'woocommerce_variation_price_html', 'bm_hide_variation_price_when_no_quote', 10, 2 );
function bm_hide_variation_price_when_no_quote( $price_html, $variation ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $price_html;
    }

    if ( ! $variation instanceof WC_Product_Variation ) {
        return $price_html;
    }

    if ( ! is_product() ) {
        return $price_html;
    }

    if ( bm_product_has_no_quote( $variation->get_parent_id() ) ) {
        return '';
    }

    return $price_html;
}

add_filter( 'woocommerce_show_variation_price', 'bm_hide_variation_price_output_when_no_quote', 10, 3 );
function bm_hide_variation_price_output_when_no_quote( $show, $product, $variation ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $show;
    }

    if ( ! $product instanceof WC_Product ) {
        return $show;
    }

    if ( ! is_product() ) {
        return $show;
    }

    if ( bm_product_has_no_quote( $product->get_id() ) ) {
        return false;
    }

    return $show;
}



/**
 * Mapowanie produktów do dostępnych opcji
 * Bazując na tabeli "Dozwolone kombinacje"
 */
function get_available_options_for_product($product_id) {
    
    // Mapowanie ID produktu do dostępnych klamek
$product_handles_map = array(
    241 => array('styl_1', 'styl_2', 'styl_3', 'styl_4', 'styl_5', 'styl_6'), // Drzwi szklane (kol.1)
    251 => array('styl_1', 'styl_2', 'styl_3', 'styl_4', 'styl_6'), // Podwójne drzwi na zawiasach (kol.2) - BRAK styl_5
    246 => array('styl_1', 'styl_2', 'styl_3', 'styl_5', 'styl_6'), // Pojedyncze drzwi obrotowe (kol.3)
    245 => array('styl_1', 'styl_2', 'styl_3', 'styl_5', 'styl_6'), // Podwójne drzwi obrotowe (kol.4)
    250 => array('styl_1', 'styl_5', 'styl_6'), // Pojedyncze drzwi przesuwne – szyna (kol.5)
    249 => array('styl_1', 'styl_5', 'styl_6'), // Podwójne drzwi przesuwne – szyna (kol.6)
    248 => array('styl_1', 'styl_5', 'styl_6'), // Pojedyncze drzwi przesuwne – szyna stodołowa (kol.7)
    247 => array('styl_1', 'styl_5', 'styl_6'), // Podwójne drzwi przesuwne – szyna stodołowa (kol.8)
    244 => array('styl_1', 'styl_5', 'styl_6'), // Pojedyncze drzwi przesuwne – kieszeń (kol.9)
    243 => array('styl_1', 'styl_5', 'styl_6'), // Podwójne drzwi przesuwne – kieszeń (kol.10)
    242 => array(), // Element stały/przegroda (kol.11) - BRAK klamek
);
    
    // Mapowanie ID produktu do dostępnych kierunków
    $product_sides_map = array(
        241 => array('left', 'right'),
        242 => array(), // Stały segment - brak kierunku
        243 => array(), // Podwójne - brak kierunku
        244 => array('left', 'right'),
        245 => array(), // Podwójne - brak kierunku
        246 => array('left', 'right'),
        247 => array(), // Podwójne - brak kierunku
        248 => array('left', 'right'),
        249 => array(), // Podwójne - brak kierunku
        250 => array('left', 'right'),
        251 => array('left', 'right'),
    );
    
    // Mapowanie ID produktu do dostępnych szkieł
    $product_glass_map = array(
        241 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        242 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        243 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        244 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        245 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        246 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        247 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        248 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        249 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        250 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
        251 => array('glass_1', 'glass_2', 'glass_3', 'glass_4'), // wszystkie
    );
    
    // Mapowanie ID produktu do dostępnych profili
    $product_profiles_map = array(
        241 => array('standard', 'retro'),
        242 => array('standard', 'retro'),
        243 => array('standard', 'retro'),
        244 => array('standard', 'retro'),
        245 => array('standard', 'retro'),
        246 => array('standard', 'retro'),
        247 => array('standard', 'retro'),
        248 => array('standard', 'retro'),
        249 => array('standard', 'retro'),
        250 => array('standard', 'retro'),
        251 => array('standard', 'retro'),
    );
    
    return array(
        'handles'  => isset($product_handles_map[$product_id]) ? $product_handles_map[$product_id] : array(),
        'sides'    => isset($product_sides_map[$product_id]) ? $product_sides_map[$product_id] : array(),
        'glass'    => isset($product_glass_map[$product_id]) ? $product_glass_map[$product_id] : array(),
        'profiles' => isset($product_profiles_map[$product_id]) ? $product_profiles_map[$product_id] : array(),
    );
}

/**
 * Filtruje tablicę opcji na podstawie dozwolonych kluczy
 */
function filter_options($all_options, $allowed_keys) {
    if (empty($allowed_keys)) {
        return array();
    }
    
    return array_intersect_key($all_options, array_flip($allowed_keys));
}


/**
 * MAPA DOPŁATY ZA LICZBĘ SZYB (wg wariantu `liczba-szyb`)
 * Klucze = dokładne nazwy opcji w WooCommerce
 * Wartości = dopłata w PLN
 */
function bm_get_panes_price_map() {
    return array(
        'Jedna'                                 => 0,
        'Dwie'                                  => 20,
        'Trzy'                                  => 30,
        'Trzy - styl nowoczesny'                => 30,
        'Cztery'                                => 40,
        'Cztery - asymetrycznie'                => 40,
        'Cztery - z długimi panelami'           => 40,
        'Cztery - podzielone przez łuk'         => 40,
        'Cztery - z krótkimi panelami'          => 40,
        'Cztery - z krótkimi panelami górnymi'  => 40,
        'Sześć'                                 => 60,
        'Sześć - asymetrycznie'                 => 60,
        'Sześć - podzielonych przez łuk'        => 150,
        'Osiem'                                 => 80,
        'Rama offsetowa'                        => 100,
		
        // SLUGI (takie jak w data-value / atrybutach wariantu)
        '1-jedna'                               => 0,
        'dwie'                                  => 20,
        'trzy'                                  => 30,
        'trzy-styl-nowoczesny'                  => 30,
        'cztery'                                => 40,
        'cztery-asymetrycznie'                  => 40,
        'cztery-z-dlugimi-panelami'             => 40,
        'cztery-podzielone-przez-luk'           => 40,
        'cztery-z-krotkimi-panelami'            => 40,
        'cztery-z-krotkimi-panelami-gornymi'    => 40,
        'szesc'                                 => 60,
        'szesc-asymetrycznie'                   => 60,
        'szesc-podzielonych-przez-luk'          => 150,
        'osiem'                                 => 80,
        'rama-offsetowa'                        => 100,
    );
}

function bm_get_panes_price_by_label( $label ) {
    $map = bm_get_panes_price_map();
    $label = trim( wp_strip_all_tags( (string) $label ) );

    // 1) najpierw po labelu (np. "Cztery - z długimi panelami")
    if ( isset( $map[ $label ] ) ) {
        return (float) $map[ $label ];
    }

    // 2) fallback: spróbuj po slugu z nazwy
    $slug = sanitize_title( $label ); // "Cztery - z długimi panelami" → "cztery-z-dlugimi-panelami"
    if ( isset( $map[ $slug ] ) ) {
        return (float) $map[ $slug ];
    }

    return 0.0;
}
/**
 * Pola na stronie produktu:
 * - szerokość / wysokość (mm)
 * - klamka
 * - szkło
 * - profil stalowy
 * - podgląd ceny
 */
add_action( 'woocommerce_before_add_to_cart_button', 'bm_custom_product_addons' );
function bm_custom_product_addons() {
    global $product;
	$idd = $product->get_id();
	$product_id =  $product->get_id();
    if ( ! $product || ! is_product() ) return;

    $min_area             = bm_get_door_min_area( $product_id );
    $max_area             = bm_get_door_max_area( $product_id );
    $price_per_m2_over    = bm_get_door_price_per_m2_over_min( $product_id );
    $profile_retro_percent = bm_get_door_profile_retro_percent( $product_id );
    $no_quote             = bm_product_has_no_quote( $product_id );

    // Kategorie, dla których dodatki mają się pokazywać
    $eligible_categories = array( 'drzwi', 'drzwi-przesuwne', 'nieruchome-segmenty' );

    if ( ! has_term( $eligible_categories, 'product_cat', $product->get_id() ) ) return;

    // ===== DOMYŚLNE WYMIARY Z META (mm) =====
    //$default_height_mm = (float) get_post_meta( $product->get_id(), '_bm_wysokosc_mm', true );
    //$default_width_mm  = (float) get_post_meta( $product->get_id(), '_bm_szerokosc_mm', true );
$default_height_mm = (float) get_post_meta($product_id, 'bm_wysokosc_mm', true);
$default_width_mm  = (float) get_post_meta($product_id, 'bm_szerokosc_mm', true);
    // Fallback jeśli nie ma meta – ustaw typowe drzwi
    if ( $default_height_mm <= 0 ) {
        $default_height_mm = 2100;
    }
    if ( $default_width_mm <= 0 ) {
        $default_width_mm = 900;
    }
// Oryginalne pełne tablice opcji
$all_handles = array(
    'styl_1' => array(
        'label' => '240 mm kątownik (w cenie)',
        'price' => 0,
        'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_profil-240-1.svg',
    ),
    'styl_2' => array(
        'label' => '240 mm pręt kwadratowy',
        'price' => 80,
        'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_trekk-3-240.svg',
    ),
    'styl_3' => array(
        'label' => '200 mm półkulisty',
        'price' => 100,
        'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_profil-3.svg',
    ),
    'styl_4' => array(
        'label' => 'Klamka Corona Fokus',
        'price' => 200,
        'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_time-r-sl.svg',
    ),
    'styl_5' => array(
        'label' => 'Klamka – wariant 5',
        'price' => 200,
        'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_trekk-1-600.svg',
    ),
    'styl_6' => array(
        'label' => 'Klamka – wariant 6',
        'price' => 200,
        'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_aegis-q-sl.svg',
    ),
);

$all_sides = array(
    'left'  => 'Drzwi lewe',
    'right' => 'Drzwi prawe',
);

$all_glass = array(
    'glass_1' => array(
        'label'       => 'Szkło poddymione',
        'mode'        => 'per_m2',
        'amount'      => 150,
        'is_base'     => false,
        'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/poddymiane.png',
    ),
    'glass_2' => array(
        'label'       => 'Szkło ryflowane',
        'mode'        => 'per_m2',
        'amount'      => 150,
        'is_base'     => false,
        'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/ryflowe.png',
    ),
    'glass_3' => array(
        'label'       => 'Szkło przeźroczyste (w cenie)',
        'mode'        => 'per_m2',
        'amount'      => 0,
        'is_base'     => true,
        'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/przezroczysty.png',
    ),
    'glass_4' => array(
        'label'       => 'Szkło szczotkowane',
        'mode'        => 'per_m2',
        'amount'      => 150,
        'is_base'     => false,
        'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/szczotkowane.png',
    ),
);

$all_profiles = array(
    'standard' => array(
        'label'   => 'Profil stalowy standard',
        'percent' => 0,
    ),
    'retro' => array(
        'label'   => 'Profil stalowy Retro',
        'percent' => $profile_retro_percent,
    ),
);

$all_colors = array(
    'ral' => array(
        'label' => 'Kolor z palety RAL',
        'price' => 200,
    ),
    'czarny' => array(
        'label' => 'Czarny',
        'price' => 0,
    ),
    'inny' => array(
        'label' => 'Inny kolor (na zamówienie)',
        'price' => 0,
        'note'  => 'wycena koloru indywidualna',
    ),
);

    /* =====================  KONFIGURACJA KLAMEK  ===================== */
    $handles = array(
        'styl_1' => array(
            'label' => '240 mm kątownik (w cenie)',
            'price' => 0,
            'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_profil-240-1.svg',
        ),
        'styl_2' => array(
            'label' => '240 mm pręt kwadratowy',
            'price' => 80,
            'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_trekk-3-240.svg',
        ),
        'styl_3' => array(
            'label' => '200 mm półkulisty',
            'price' => 100,
            'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_profil-3.svg',
        ),
        'styl_4' => array(
            'label' => 'Klamka Corona Fokus',
            'price' => 200,
            'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_time-r-sl.svg',
        ),
        'styl_5' => array(
            'label' => 'Klamka – wariant 5',
            'price' => 200,
            'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_trekk-1-600.svg',
        ),
        'styl_6' => array(
            'label' => 'Klamka – wariant 6',
            'price' => 200,
            'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_aegis-q-sl.svg',
        ),
        'styl_7' => array(
            'label' => 'Klamka – wariant 7',
            'price' => 200,
            'img'   => 'https://lofthouse.store/wp-content/uploads/2025/11/handtak_focus-q-sl.svg',
        ),
    );
	
	
	
$colors = array(
    'ral' => array(
        'label' => 'Kolor z palety RAL',
        'price' => 200,
    ),
    'czarny' => array(
        'label' => 'Czarny',
        'price' => 0,
    ),
    'inny' => array(
        'label' => 'Inny kolor (na zamówienie)',
        'price' => 0, // brak automatycznej dopłaty w kalkulatorze
        'note'  => 'wycena koloru indywidualna',
    ),
);

	    $sides = array(
        'left'  => 'Drzwi lewe',
        'right' => 'Drzwi prawe',
    );
	
    /* =====================  KONFIGURACJA SZKŁA  ===================== */
    $glass = array(
        'glass_1' => array(
            'label'       => 'Szkło poddymione',
            'mode'        => 'per_m2',
            'amount'      => 150,
            'is_base'     => false,
            'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/poddymiane.png',
        ),
        'glass_2' => array(
            'label'       => 'Szkło ryflowane',
            'mode'        => 'per_m2',
            'amount'      => 150,
            'is_base'     => false,
            'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/ryflowe.png',
        ),
        'glass_3' => array(
            'label'       => 'Szkło przeźroczyste (w cenie)',
            'mode'        => 'per_m2',
            'amount'      => 0,
            'is_base'     => true,
            'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/przezroczysty.png',
        ),
        'glass_4' => array(
            'label'       => 'Szkło szczotkowane',
            'mode'        => 'per_m2',
            'amount'      => 150,
            'is_base'     => false,
            'img'         => 'https://lofthouse.store/wp-content/uploads/2025/12/szczotkowane.png',
        ),
    );

    /* =====================  KONFIGURACJA PROFILU STALOWEGO  ===================== */
    $profiles = array(
        'standard' => array(
            'label'   => 'Profil stalowy standard',
            'percent' => 0,
        ),
        'retro' => array(
            'label'   => 'Profil stalowy Retro',
            'percent' => $profile_retro_percent,
        ),
    );
// Pobierz dozwolone opcje dla produktu
$available = get_available_options_for_product($product_id);

// Filtruj tablice do tylko dostępnych opcji
$handles  = filter_options($all_handles, $available['handles']);
$sides    = filter_options($all_sides, $available['sides']);
$glass    = filter_options($all_glass, $available['glass']);
$profiles = filter_options($all_profiles, $available['profiles']);

// Kolory są dostępne dla wszystkich produktów
$colors = $all_colors;
    $base_price   = (float) $product->get_price();
    $currency     = get_woocommerce_currency_symbol();
    $panes_prices = bm_get_panes_price_map();

    /* =====================  POLE WYMIARÓW ===================== */
    ?>
    <div class="bm-addon-block bm-dimensions-block" style="margin:20px 0;padding:10px;border:1px solid #eee;">
        <h4>Podaj wymiary drzwi (mm)</h4>
        <p style="font-size:0.9em;color:#555;">
           
        </p>
        <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;">
            <div>
                <label for="bm_width_mm">Szerokość [mm]</label><br>
                <input type="number"
                       id="bm_width_mm"
                       name="bm_width_mm"
                       min="500"
                       max="1400"
                       step="1"
                       value="<?php echo esc_attr( (int) $default_width_mm ); ?>"
                       style="width:120px;">
            </div>
            <div>
                <label for="bm_height_mm">Wysokość [mm]</label><br>
                <input type="number"
                       id="bm_height_mm"
                       name="bm_height_mm"
                       min="1800"
                       max="2600"
                       step="1"
                       value="<?php echo esc_attr( (int) $default_height_mm ); ?>"
                       style="width:120px;">
            </div>
        </div>
    </div>
    <?php
if($idd !== 242) {
    /* =====================  POLE 1: KLAMKI ===================== */
    echo '<div class="bm-addon-block" style="margin:20px 0;">';
    echo '<h4>Wybierz styl klamki <span style="color:red">*</span></h4>';
    echo '<div style="display:flex;gap:20px;flex-wrap:wrap;">';

    $first_handle = true;
    foreach ( $handles as $key => $h ) {
        echo '<label style="text-align:center;cursor:pointer;">';

        if ( $h['img'] ) {
            echo '<img src="' . esc_url( $h['img'] ) . '" style="max-width:120px;margin-bottom:6px;border:1px solid #ddd;padding:4px;">';
        }

        echo '<br><input type="radio"
                         class="bm-handle-radio"
                         name="bm_handle_choice"
                         value="' . esc_attr( $key ) . '"
                         data-price="' . esc_attr( $h['price'] ) . '"
                         ' . ( $first_handle ? 'checked="checked"' : '' ) . '
                         required>';
        $first_handle = false;

        echo '<div>' . esc_html( $h['label'] ) . ' (+' . wc_price( $h['price'] ) . ')</div>';
        echo '</label>';
    }

    echo '</div>';
    echo '</div>';
}
    /* =====================  POLE 2: SZKŁO ===================== */
    echo '<div class="bm-addon-block" style="margin:20px 0;">';
    echo '<h4>Wybierz typ szkła <span style="color:red">*</span></h4>';
    echo '<div style="display:flex;gap:20px;flex-wrap:wrap;">';

    $first_glass = true;
    foreach ( $glass as $key => $g ) {
        echo '<label style="text-align:center;cursor:pointer; width: calc(50% - 10px)">';

        if ( $g['img'] ) {
            echo '<img src="' . esc_url( $g['img'] ) . '" style="max-width:100%;margin-bottom:6px;border:1px solid #ddd;padding:4px;">';
        }

        echo '<br><input type="radio"
                         class="bm-glass-radio"
                         name="bm_glass_choice"
                         value="' . esc_attr( $key ) . '"
                         data-amount="' . esc_attr( $g['amount'] ) . '"
                         ' . ( $first_glass ? 'checked="checked"' : '' ) . '
                         required>';
        $first_glass = false;

        $glass_info = $g['amount'] > 0
            ? '(+' . $g['amount'] . ' PLN/m²)'
            : '(bez dopłaty)';
        echo '<div>' . esc_html( $g['label'] ) . ' ' . $glass_info . '</div>';
        echo '</label>';
    }

    echo '</div>';
    echo '</div>';

    /* =====================  POLE 3: PROFIL STALOWY ===================== */
    echo '<div class="bm-addon-block" style="margin:20px 0;">';
    echo '<h4>Wybierz profil stalowy <span style="color:red">*</span></h4>';
    echo '<div style="display:flex;gap:20px;flex-wrap:wrap;">';

    $first_profile = true;
    foreach ( $profiles as $key => $p ) {
        echo '<label style="text-align:left;cursor:pointer;margin-right:20px;">';
        echo '<input type="radio"
                     class="bm-profile-radio"
                     name="bm_profile"
                     value="' . esc_attr( $key ) . '"
                     data-percent="' . esc_attr( $p['percent'] ) . '"
                     ' . ( $first_profile ? 'checked="checked"' : '' ) . '
                     required> ';
        $first_profile = false;

        $percent_info = $p['percent'] > 0
            ? '(+' . ( $p['percent'] * 100 ) . '%)'
            : '(bez dopłaty)';

        echo esc_html( $p['label'] . ' ' . $percent_info );
        echo '</label>';
    }
	

	

    echo '</div>';
    echo '</div>';
    /* =====================  POLE 4: KOLOR ===================== */
    echo '<div class="bm-addon-block" style="margin:20px 0;">';
    echo '<h4>Wybierz kolor <span style="color:red">*</span></h4>';
    echo '<div style="display:flex;gap:20px;flex-wrap:wrap;">';

    $first_color = true;
    foreach ( $colors as $key => $c ) {
        echo '<label style="text-align:left;cursor:pointer;margin-right:20px;">';
        echo '<input type="radio"
                     class="bm-color-radio"
                     name="bm_color"
                     value="' . esc_attr( $key ) . '"
                     data-price="' . esc_attr( $c['price'] ) . '"
                     ' . ( $first_color ? 'checked="checked"' : '' ) . '
                     required> ';
        $first_color = false;

if ( $key === 'inny' ) {
    $info = '(+ wycena koloru indywidualna)';
} elseif ( $c['price'] > 0 ) {
    $info = '(+' . wc_price( $c['price'] ) . ') - <a href="https://lofthouse.store/wp-content/uploads/2025/12/RAL-color-chart.pdf" target="_blank">Sprawdź kolory RAL</a>';
	    // POLE TEKSTOWE DLA RAL
    $info .= '<div id="bm_color_ral_wrap" style="margin:10px 0; display:none;">';
     $info .= '<label for="bm_color_ral_custom">Podaj numer koloru RAL:</label><br>';
     $info .= '<input type="text" name="bm_color_ral_custom" id="bm_color_ral_custom" style="max-width:200px;" value="">';
     $info .= '</div>';
} else {
    $info = '(bez dopłaty)';
}
echo $c['label'] . ' ' . $info;
        echo '</label>';
    }

    echo '</div>';
    echo '</div>';	

	//249
    /* =====================  POLE 4: STRONA OTWIERANIA (LEWE / PRAWE) ===================== */
	
	if($idd !== 249 and $idd !== 251 and $idd !== 245 and $idd !== 243 and $idd !== 242) {
	
    echo '<div class="bm-addon-block" style="margin:20px 0;">';
    echo '<h4>Strona otwierania drzwi <span style="color:red">*</span></h4>';
    echo '<div style="display:flex;gap:20px;flex-wrap:wrap;">';

    $first_side = true;
    foreach ( $sides as $key => $label ) {
        echo '<label style="text-align:left;cursor:pointer;margin-right:20px;">';
        echo '<input type="radio"
                     class="bm-side-radio"
                     name="bm_side"
                     value="' . esc_attr( $key ) . '"
                     ' . ( $first_side ? 'checked="checked"' : '' ) . '
                     required> ';
        $first_side = false;

        echo esc_html( $label );
        echo '</label>';
    }

    echo '</div>';
    echo '</div>';
	}
	
    /* =====================  PODGLĄD CENY ===================== */
    ?>
    <div id="bm-price-preview"
         style="margin:20px 0;padding:10px;background:#f8f8f8;border:1px solid #eee;"
         data-base-price="<?php echo esc_attr( $base_price ); ?>"
         data-min-area="<?php echo esc_attr( $min_area ); ?>"
         data-max-area="<?php echo esc_attr( $max_area ); ?>"
         data-price-per-over="<?php echo esc_attr( $price_per_m2_over ); ?>"
         data-no-quote="<?php echo esc_attr( $no_quote ? '1' : '0' ); ?>"
         data-currency="<?php echo esc_attr( $currency ); ?>">
        <strong>Szacunkowa cena drzwi:</strong>
        <span class="bm-price-value" style="color: #DC9814; font-weight: bold">
           <b>
            <?php
            echo $no_quote
                ? esc_html__( 'Wycena indywidualna przez pracownika', 'nm-framework' )
                : wc_price( $base_price );
            ?>
           </b> 
        </span>
		<br />Cena ostateczna zostanie podana w podsumowaniu zamówienia przygotowanym przez naszego pracownika.
    </div>
    <?php if ( $no_quote ) : ?>
        <style>
            .single-product .woocommerce-variation-price,
            .single-product .woocommerce-variation-price .price {
                display: none !important;
            }
        </style>
    <?php endif; ?>

    <script>
        window.bmPanesPrices = <?php echo wp_json_encode( $panes_prices ); ?>;
        (function() {
function bmGetPanesExtraPrice() {
    var panesExtra = 0;
    if (!window.bmPanesPrices) return 0;

    var label = '';
    var key   = '';

    // 1) próbujemy wybrać aktualnie zaznaczony kafelek (Woo Variation Swatches)
    var selectedLi = document.querySelector(
        'ul[data-attribute_name="attribute_pa_liczba-szyb"] li.variable-item.selected,' +
        'ul[data-attribute_name="attribute_pa_liczba-szyb"] li.variable-item[aria-checked="true"]'
    );

    if (selectedLi) {
        // label – do mapy po etykiecie
        label =
            selectedLi.getAttribute('data-wvstooltip') ||
            selectedLi.getAttribute('data-title') ||
            selectedLi.getAttribute('title') ||
            (selectedLi.textContent || '');

        label = (label || '').trim();

        // slug – do mapy po slugu
        key = selectedLi.getAttribute('data-value') || '';
        key = (key || '').trim();
    } else {
        // 2) fallback – hidden select (gdyby plugin go używał)
        var panesSelect = document.querySelector(
            'form.variations_form select[name^="attribute_pa_liczba-szyb"],' +
            'form.cart select[name^="attribute_pa_liczba-szyb"]'
        );
        if (panesSelect && panesSelect.options.length) {
            var opt = panesSelect.options[panesSelect.selectedIndex];
            if (opt) {
                label = (opt.textContent || opt.innerText || '').trim();
                key   = (opt.value || '').trim();
            }
        }
    }

    // Najpierw próbujemy label (np. "Cztery - z długimi panelami")
    if (label && typeof window.bmPanesPrices[label] !== 'undefined') {
        panesExtra = parseFloat(window.bmPanesPrices[label]) || 0;
        return panesExtra;
    }

    // Potem slug (np. "cztery-z-dlugimi-panelami", "szesc", "rama-offsetowa")
    if (key && typeof window.bmPanesPrices[key] !== 'undefined') {
        panesExtra = parseFloat(window.bmPanesPrices[key]) || 0;
        return panesExtra;
    }

    return 0;
}
			
			            function bmToggleRalField() {
                var ralWrap = document.getElementById('bm_color_ral_wrap');
                if (!ralWrap) return;

                var colorRadios = document.querySelectorAll('.bm-color-radio');
                var selected = null;
                colorRadios.forEach(function(r) {
                    if (r.checked) {
                        selected = r.value;
                    }
                });

                if (selected === 'ral') {
                    ralWrap.style.display = 'block';
                } else {
                    ralWrap.style.display = 'none';
                    // opcjonalnie: czyścić pole przy zmianie na inny kolor
                    // var input = document.getElementById('bm_color_ral_custom');
                    // if (input) input.value = '';
                }
            }


            function bmRecalcPrice() {
                var preview = document.getElementById('bm-price-preview');
                if (!preview) return;

                var basePrice     = parseFloat(preview.dataset.basePrice || '0');
                var minArea       = parseFloat(preview.dataset.minArea || '0.75');
                var maxArea       = parseFloat(preview.dataset.maxArea || '2.75');
                var pricePerOver  = parseFloat(preview.dataset.pricePerOver || '941');
                var currency      = preview.dataset.currency || 'zł';
                var priceSpan     = preview.querySelector('.bm-price-value');

                if (preview.dataset.noQuote === '1') {
                    if (priceSpan) {
                        priceSpan.textContent = 'Wycena indywidualna przez pracownika';
                    }
                    var wooPriceBdi = document.querySelector('.summary .price .woocommerce-Price-amount bdi');
                    if (wooPriceBdi) {
                        wooPriceBdi.textContent = '';
                    }
                    var variationPrice = document.querySelector('.woocommerce-variation-price');
                    if (variationPrice) {
                        variationPrice.textContent = '';
                    }
                    return;
                }

                var widthInput  = document.getElementById('bm_width_mm');
                var heightInput = document.getElementById('bm_height_mm');

                var width  = widthInput ? parseFloat(widthInput.value || '0') : 0;
                var height = heightInput ? parseFloat(heightInput.value || '0') : 0;

                // Powierzchnia w m2
                var area = (width * height) / 1000000;
                if (!isFinite(area) || area <= 0) {
                    area = minArea;
                }
                if (area < minArea) area = minArea;
                if (area > maxArea) area = maxArea;

                // Dopłata za metraż powyżej min
                var extraArea      = Math.max(0, area - minArea);
                var areaExtraPrice = extraArea * pricePerOver;

                // Dopłata za szkło (PLN/m2 * m2)
                var glassRadios = document.querySelectorAll('.bm-glass-radio');
                var glassAmount = 0;
                glassRadios.forEach(function(r) {
                    if (r.checked) {
                        glassAmount = parseFloat(r.dataset.amount || '0');
                    }
                });
                var glassExtraPrice = glassAmount * area;

                // Dopłata za klamkę (PLN)
                var handleRadios = document.querySelectorAll('.bm-handle-radio');
                var handlePrice  = 0;
                handleRadios.forEach(function(r) {
                    if (r.checked) {
                        handlePrice = parseFloat(r.dataset.price || '0');
                    }
                });
                // Dopłata za kolor (RAL/Czarny/Inny)
                var colorRadios = document.querySelectorAll('.bm-color-radio');
                var colorPrice  = 0;
                colorRadios.forEach(function(r) {
                    if (r.checked) {
                        colorPrice = parseFloat(r.dataset.price || '0');
                    }
                });
                // Dopłata za liczbę szyb
                var panesExtraPrice = bmGetPanesExtraPrice();

                var subtotal = basePrice + areaExtraPrice + glassExtraPrice + handlePrice + panesExtraPrice + colorPrice;

                // Profil stalowy (np. Retro +5%)
                var profileRadios = document.querySelectorAll('.bm-profile-radio');
                var profilePercent = 0;
                profileRadios.forEach(function(r) {
                    if (r.checked) {
                        profilePercent = parseFloat(r.dataset.percent || '0');
                    }
                });

                if (profilePercent > 0) {
                    subtotal = subtotal * (1 + profilePercent);
                }

                // Zaokrąglij do 2 miejsc
                subtotal = Math.round(subtotal * 100) / 100;

                if (priceSpan) {
                    priceSpan.textContent = subtotal.toLocaleString('pl-PL', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }) + ' ' + currency;
                }

                // Podmiana ceny Woo na froncie (tylko wizualnie)
                var wooPriceBdi = document.querySelector('.summary .price .woocommerce-Price-amount bdi');
                if (wooPriceBdi) {
                    wooPriceBdi.textContent = subtotal.toLocaleString('pl-PL', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }) + ' ' + currency;
                }
            }

            document.addEventListener('input', function(e) {
                if (e.target && (e.target.id === 'bm_width_mm' || e.target.id === 'bm_height_mm')) {
                    bmRecalcPrice();
                }
            });

            document.addEventListener('change', function(e) {
                if (
                    (e.target && e.target.classList.contains('bm-handle-radio')) ||
                    (e.target && e.target.classList.contains('bm-glass-radio')) ||
                    (e.target && e.target.classList.contains('bm-profile-radio')) ||
                    (e.target && e.target.classList.contains('bm-color-radio')) ||
                    (e.target && e.target.name && e.target.name.indexOf('attribute_pa_liczba-szyb') !== -1)
                ) {
                    bmRecalcPrice();
                    if (e.target && e.target.classList.contains('bm-color-radio')) {
                        bmToggleRalField();
                    }
                }

            });


			// Kliknięcie w kafelek liczby szyb (Woo Variation Swatches)
document.addEventListener('click', function(e) {
    var li = e.target.closest('ul[data-attribute_name="attribute_pa_liczba-szyb"] li.variable-item');
    if (li) {
        // Plugin dopiero za chwilę ustawia .selected / aria-checked
        setTimeout(bmRecalcPrice, 30);
    }
});
// Reakcja na zmianę wariantu (m.in. kliknięcie w kafelek liczby szyb)
if (window.jQuery) {
    jQuery(function($) {
        $('form.variations_form').on(
            'woocommerce_variation_has_changed found_variation reset_data',
            function() {
                // małe opóźnienie, żeby select zdążył się zaktualizować
                setTimeout(bmRecalcPrice, 30);
            }
        );
    });
}


                 document.addEventListener('DOMContentLoaded', function() {
                bmRecalcPrice();
                bmToggleRalField();
            });
            window.addEventListener('load', function() {
                bmRecalcPrice();
                bmToggleRalField();
            });
        })();
    </script>
    <?php
}

/* ----------------- WALIDACJA – pola obowiązkowe ----------------- */

add_filter( 'woocommerce_add_to_cart_validation', 'bm_validate_addons', 10, 3 );
function bm_validate_addons( $passed, $product_id, $quantity ) {

    $eligible_categories = array( 'drzwi', 'drzwi-przesuwne', 'nieruchome-segmenty' );

    // --- Kolor (radio) ---
    if ( empty( $_POST['bm_color'] ) ) {
        wc_add_notice( 'Wybierz kolor.', 'error' );
        return false;
    }

    $color = sanitize_text_field( wp_unslash( $_POST['bm_color'] ) );

    // --- Jeśli wybrano RAL → pole tekstowe jest OBOWIĄZKOWE ---
    if ( $color === 'ral' ) {
        $ral_value = '';

        if ( isset( $_POST['bm_color_ral_custom'] ) ) {
            $ral_value = trim( wp_unslash( $_POST['bm_color_ral_custom'] ) );
        }

        if ( $ral_value === '' ) {
            wc_add_notice( 'Podaj numer koloru RAL.', 'error' );
            return false;
        }
    }
	
	     
	
	        if ( empty( $_POST['bm_side'] ) ) {
            wc_add_notice( 'Wybierz stronę otwierania drzwi (lewe/prawe).', 'error' );
            return false;
        }

	
    if ( has_term( $eligible_categories, 'product_cat', $product_id ) ) {

        if ( empty( $_POST['bm_handle_choice'] ) ) {
            wc_add_notice( 'Wybierz styl klamki.', 'error' );
            return false;
        }

        if ( empty( $_POST['bm_glass_choice'] ) ) {
            wc_add_notice( 'Wybierz typ szkła.', 'error' );
            return false;
        }

        if ( empty( $_POST['bm_profile'] ) ) {
            wc_add_notice( 'Wybierz profil stalowy.', 'error' );
            return false;
        }
    }

    return $passed;
}

/* ----------------- ZAPIS DODATKÓW + WYMIARÓW DO KOSZYKA ----------------- */

add_filter( 'woocommerce_add_cart_item_data', 'bm_add_addons_cart_item_data', 10, 3 );
function bm_add_addons_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
$profile_retro_percent = bm_get_door_profile_retro_percent( $product_id );
$colors = array(
    'ral'    => array( 'label' => 'Kolor z palety RAL',         'price' => 200 ),
    'czarny' => array( 'label' => 'Czarny',                     'price' => 0 ),
    'inny'   => array(
        'label' => 'Inny kolor (na zamówienie)',
        'price' => 0,
        'note'  => 'wycena koloru indywidualna',
    ),
);
	
	
	
    $sides = array(
        'left'  => 'Drzwi lewe',
        'right' => 'Drzwi prawe',
    );

    $handles = array(
        'styl_1' => array('label' => '240 mm kątownik (w cenie)', 'price' => 0),
        'styl_2' => array('label' => '240 mm pręt kwadratowy',    'price' => 80),
        'styl_3' => array('label' => '200 mm półkulisty',         'price' => 100),
        'styl_4' => array('label' => 'Klamka Corona Fokus',       'price' => 200),
        'styl_5' => array('label' => 'Klamka – wariant 5',        'price' => 200),
        'styl_6' => array('label' => 'Klamka – wariant 6',        'price' => 200),
        'styl_7' => array('label' => 'Klamka – wariant 7',        'price' => 200),
    );

    $glass = array(
        'glass_1' => array('label'=>'Szkło poddymione',     'mode'=>'per_m2', 'amount'=>150, 'is_base'=>false),
        'glass_2' => array('label'=>'Szkło ryflowane',      'mode'=>'per_m2', 'amount'=>150, 'is_base'=>false),
        'glass_3' => array('label'=>'Szkło przeźroczyste',  'mode'=>'per_m2', 'amount'=>0,   'is_base'=>true),
        'glass_4' => array('label'=>'Szkło szczotkowane',   'mode'=>'per_m2', 'amount'=>150, 'is_base'=>false),
    );

    $profiles = array(
        'standard' => array('label'=>'Profil stalowy standard', 'percent'=>0),
        'retro'    => array('label'=>'Profil stalowy Retro',    'percent'=>$profile_retro_percent),
    );

    // Wymiary z formularza
    if ( isset( $_POST['bm_width_mm'] ) ) {
        $cart_item_data['bm_width_mm'] = (int) $_POST['bm_width_mm'];
    }
    if ( isset( $_POST['bm_height_mm'] ) ) {
        $cart_item_data['bm_height_mm'] = (int) $_POST['bm_height_mm'];
    }

    // Klamka
    if ( ! empty( $_POST['bm_handle_choice'] ) ) {
        $key = sanitize_text_field( $_POST['bm_handle_choice'] );
        if ( isset( $handles[ $key ] ) ) {
            $cart_item_data['bm_handle_choice'] = $handles[ $key ];
            $cart_item_data['bm_handle_choice']['key'] = $key;
        }
    }

    // Szkło
    if ( ! empty( $_POST['bm_glass_choice'] ) ) {
        $key = sanitize_text_field( $_POST['bm_glass_choice'] );
        if ( isset( $glass[ $key ] ) ) {
            $cart_item_data['bm_glass_choice'] = $glass[ $key ];
            $cart_item_data['bm_glass_choice']['key'] = $key;
        }
    }

    // Profil
    if ( ! empty( $_POST['bm_profile'] ) ) {
        $key = sanitize_text_field( $_POST['bm_profile'] );
        if ( isset( $profiles[ $key ] ) ) {
            $cart_item_data['bm_profile'] = $profiles[ $key ];
            $cart_item_data['bm_profile']['key'] = $key;
        }
    }
    // Kolor
 
    if ( ! empty( $_POST['bm_color'] ) ) {
        $key = sanitize_text_field( $_POST['bm_color'] );
        if ( isset( $colors[ $key ] ) ) {
            $cart_item_data['bm_color'] = $colors[ $key ];
            $cart_item_data['bm_color']['key'] = $key;

            // Jeśli wybrano RAL – zapisz wpisany kod
            if ( $key === 'ral' && isset( $_POST['bm_color_ral_custom'] ) ) {
                $cart_item_data['bm_color']['ral_custom'] = sanitize_text_field( $_POST['bm_color_ral_custom'] );
            }
        }
    }

    // Liczba szyb z wariantu
    if ( $variation_id ) {
        $variation_product = wc_get_product( $variation_id );
        if ( $variation_product ) {
            $panes_label = $variation_product->get_attribute( 'pa_liczba-szyb' );
            if ( $panes_label ) {
                $cart_item_data['bm_panes_label'] = $panes_label;
                $cart_item_data['bm_panes_extra'] = bm_get_panes_price_by_label( $panes_label );
            }
        }
    }
    // Strona otwierania (lewe / prawe)
    if ( ! empty( $_POST['bm_side'] ) ) {
        $key = sanitize_text_field( $_POST['bm_side'] );
        if ( isset( $sides[ $key ] ) ) {
            $cart_item_data['bm_side'] = array(
                'key'   => $key,
                'label' => $sides[ $key ],
            );
        }
    }
    return $cart_item_data;
}

/* ----------------- WYŚWIETLANIE W KOSZYKU ----------------- */

add_filter( 'woocommerce_get_item_data', 'bm_display_addons_cart', 10, 2 );
function bm_display_addons_cart( $item_data, $cart_item ) {
	
    if ( isset( $cart_item['bm_color'] ) ) {
        $c   = $cart_item['bm_color'];
        $key = isset( $c['key'] ) ? $c['key'] : '';

        if ( $key === 'inny' ) {
            $info = '(+ wycena koloru indywidualna)';
        } elseif ( $key === 'ral' && ! empty( $c['ral_custom'] ) ) {
            // RAL z podanym numerem
            $info = '(RAL: ' . esc_html( $c['ral_custom'] ) . ')';
        } elseif ( $c['price'] > 0 ) {
            $info = '(+' . wc_price( $c['price'] ) . ')';
        } else {
            $info = '(bez dopłaty)';
        }

        $item_data[] = array(
            'name'  => 'Kolor',
            'value' => $c['label'] . ' ' . $info,
        );
    }

	
	
	
	

    if ( isset( $cart_item['bm_width_mm'] ) && isset( $cart_item['bm_height_mm'] ) ) {
        $item_data[] = array(
            'name'  => 'Wymiary drzwi',
            'value' => intval( $cart_item['bm_width_mm'] ) . ' x ' . intval( $cart_item['bm_height_mm'] ) . ' mm',
        );
    }

    if ( isset( $cart_item['bm_panes_label'] ) ) {
        $label = $cart_item['bm_panes_label'];
        $extra = isset( $cart_item['bm_panes_extra'] ) ? (float) $cart_item['bm_panes_extra'] : 0;
        $value = $label;
        if ( $extra > 0 ) {
            $value .= ' (+' . wc_price( $extra ) . ')';
        }
        $item_data[] = array(
            'name'  => 'Liczba szyb',
            'value' => $value,
        );
    }

    if ( isset( $cart_item['bm_handle_choice'] ) ) {
        $h = $cart_item['bm_handle_choice'];
        $item_data[] = array(
            'name'  => 'Klamka',
            'value' => $h['label'] . ' (+' . wc_price( $h['price'] ) . ')'
        );
    }

    if ( isset( $cart_item['bm_glass_choice'] ) ) {
        $g = $cart_item['bm_glass_choice'];
        $info = $g['amount'] > 0
            ? '(+' . $g['amount'] . ' PLN/m²)'
            : '(bez dopłaty)';
        $item_data[] = array(
            'name'  => 'Typ szkła',
            'value' => $g['label'] . ' ' . $info
        );
    }
    if ( isset( $cart_item['bm_color'] ) ) {
        $c   = $cart_item['bm_color'];
        $key = isset( $c['key'] ) ? $c['key'] : '';

        if ( $key === 'inny' ) {
            $info = '(+ wycena koloru indywidualna)';
        } elseif ( $c['price'] > 0 ) {
            $info = '(+' . wc_price( $c['price'] ) . ')';
        } else {
            $info = '(bez dopłaty)';
        }

        $item_data[] = array(
            'name'  => 'Kolor',
            'value' => $c['label'] . ' ' . $info,
        );
    }
    if ( isset( $cart_item['bm_profile'] ) ) {
        $p = $cart_item['bm_profile'];
        $info = $p['percent'] > 0
            ? '(+' . ( $p['percent'] * 100 ) . '%)'
            : '(bez dopłaty)';
        $item_data[] = array(
            'name'  => 'Profil stalowy',
            'value' => $p['label'] . ' ' . $info,
        );
    }
    if ( isset( $cart_item['bm_side'] ) ) {
        $s = $cart_item['bm_side'];
        $item_data[] = array(
            'name'  => 'Strona otwierania',
            'value' => $s['label'],
        );
    }
    return $item_data;
}

/* ----------------- ZAPIS DO ZAMÓWIENIA ----------------- */

add_action( 'woocommerce_checkout_create_order_line_item', 'bm_save_addons_to_order', 10, 4 );
function bm_save_addons_to_order( $item, $cart_item_key, $values, $order ) {

    if ( isset( $values['bm_width_mm'] ) && isset( $values['bm_height_mm'] ) ) {
        $item->add_meta_data(
            'Wymiary drzwi',
            intval( $values['bm_width_mm'] ) . ' x ' . intval( $values['bm_height_mm'] ) . ' mm',
            true
        );
    }
	
	    if ( isset( $values['bm_side'] ) ) {
        $s = $values['bm_side'];
        $item->add_meta_data( 'Strona otwierania', $s['label'], true );
    }
    if ( isset( $values['bm_color'] ) ) {
        $c   = $values['bm_color'];
        $key = isset( $c['key'] ) ? $c['key'] : '';

        if ( $key === 'inny' ) {
            $info = '(+ wycena koloru indywidualna)';
        } elseif ( $key === 'ral' && ! empty( $c['ral_custom'] ) ) {
            $info = '(RAL: ' . esc_html( $c['ral_custom'] ) . ')';
        } elseif ( $c['price'] > 0 ) {
            $info = '(+' . wc_price( $c['price'] ) . ')';
        } else {
            $info = '(bez dopłaty)';
        }

        $item->add_meta_data( 'Kolor', $c['label'] . ' ' . $info, true );
    }

    if ( isset( $values['bm_panes_label'] ) ) {
        $label = $values['bm_panes_label'];
        $extra = isset( $values['bm_panes_extra'] ) ? (float) $values['bm_panes_extra'] : 0;
        $value = $label;
        if ( $extra > 0 ) {
            $value .= ' (+' . wc_price( $extra ) . ')';
        }
        $item->add_meta_data( 'Liczba szyb', $value, true );
    }

    if ( isset( $values['bm_handle_choice'] ) ) {
        $h = $values['bm_handle_choice'];
        $item->add_meta_data( 'Klamka', $h['label'] . ' (+' . wc_price( $h['price'] ) . ')', true );
    }

    if ( isset( $values['bm_glass_choice'] ) ) {
        $g = $values['bm_glass_choice'];
        $info = $g['amount'] > 0
            ? '(+' . $g['amount'] . ' PLN/m²)'
            : '(bez dopłaty)';
        $item->add_meta_data( 'Typ szkła', $g['label'] . ' ' . $info, true );
    }

    if ( isset( $values['bm_profile'] ) ) {
        $p = $values['bm_profile'];
        $info = $p['percent'] > 0
            ? '(+' . ( $p['percent'] * 100 ) . '%)'
            : '(bez dopłaty)';
        $item->add_meta_data( 'Profil stalowy', $p['label'] . ' ' . $info, true );
    }
}

/* ----------------- POWIERZCHNIA Z KOSZYKA / META ----------------- */

function bm_get_door_area_m2_from_cart_item( $cart_item, WC_Product $product ) {
    $min_area = bm_get_door_min_area( $product->get_id() );
    $max_area = bm_get_door_max_area( $product->get_id() );

    // 1. Preferuj wymiary z koszyka (podane przez usera)
    $height_mm = isset( $cart_item['bm_height_mm'] ) ? (float) $cart_item['bm_height_mm'] : 0;
    $width_mm  = isset( $cart_item['bm_width_mm'] )  ? (float) $cart_item['bm_width_mm']  : 0;

    // 2. Jeśli brak – spróbuj z meta
    if ( $height_mm <= 0 ) {
        $height_mm = (float) get_post_meta( $product->get_id(), '_bm_wysokosc_mm', true );
    }
    if ( $width_mm <= 0 ) {
        $width_mm = (float) get_post_meta( $product->get_id(), '_bm_szerokosc_mm', true );
    }

    // 3. Jeśli nadal brak – przyjmij min. powierzchnię
    if ( $height_mm <= 0 || $width_mm <= 0 ) {
        return $min_area;
    }

    $area_m2 = ( $height_mm * $width_mm ) / 1000000; // mm*mm -> m2

    if ( $area_m2 < $min_area ) {
        $area_m2 = $min_area;
    } elseif ( $area_m2 > $max_area ) {
        $area_m2 = $max_area;
    }

    return $area_m2;
}

/* ----------------- MODYFIKACJA CENY W KOSZYKU ----------------- */

add_action( 'woocommerce_before_calculate_totals', 'bm_add_addons_price', 20 );
function bm_add_addons_price( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    foreach ( $cart->get_cart() as $cart_item ) {

        if ( ! isset( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
            continue;
        }

        /** @var WC_Product $product */
        $product = $cart_item['data'];

        // Zapamiętaj cenę bazową (cena minimalna z kalkulatora)
        if ( ! isset( $cart_item['bm_base_price'] ) ) {
            $cart_item['bm_base_price'] = (float) $product->get_price();
        }

        $base_price = (float) $cart_item['bm_base_price'];

        // 1) Powierzchnia drzwi
        $area_m2 = bm_get_door_area_m2_from_cart_item( $cart_item, $product );
        $min_area = bm_get_door_min_area( $product->get_id() );
        $price_per_m2_over = bm_get_door_price_per_m2_over_min( $product->get_id() );

        // dopłata za metraż ponad min
        $extra_area        = max( 0, $area_m2 - $min_area );
        $area_extra_price  = $extra_area * $price_per_m2_over;

        // 2) Dopłata za typ szkła (PLN/m2)
        $glass_extra_price = 0;
        if ( isset( $cart_item['bm_glass_choice'] ) ) {
            $g = $cart_item['bm_glass_choice'];
            $amount = isset( $g['amount'] ) ? (float) $g['amount'] : 0;
            if ( $amount > 0 ) {
                $glass_extra_price = $amount * $area_m2;
            }
        }

        // 3) Dopłata za klamkę (PLN)
        $handle_extra_price = 0;
        if ( isset( $cart_item['bm_handle_choice'] ) ) {
            $h = $cart_item['bm_handle_choice'];
            $handle_extra_price = isset( $h['price'] ) ? (float) $h['price'] : 0;
        }

        // 4) Dopłata za liczbę szyb (PLN)
        $panes_extra_price = isset( $cart_item['bm_panes_extra'] ) ? (float) $cart_item['bm_panes_extra'] : 0;
  // 5) Dopłata za kolor (PLN) – "inny" ma price = 0, tylko info
        $color_extra_price = 0;
        if ( isset( $cart_item['bm_color'] ) ) {
            $c = $cart_item['bm_color'];
            $color_extra_price = isset( $c['price'] ) ? (float) $c['price'] : 0;
        }
        $subtotal = $base_price + $area_extra_price + $glass_extra_price + $handle_extra_price + $panes_extra_price + $color_extra_price;

        // 5) Profil stalowy (np. Retro +5%)
        if ( isset( $cart_item['bm_profile'] ) ) {
            $p = $cart_item['bm_profile'];
            $percent = isset( $p['percent'] ) ? (float) $p['percent'] : 0;
            if ( $percent > 0 ) {
                $subtotal = $subtotal * ( 1 + $percent );
            }
        }

        $product->set_price( $subtotal );
    }
}


	/* Constants & Globals
	==================================================================================================== */
    
	// Uncomment to include un-minified JavaScript files
	//define( 'NM_DEBUG_MODE', TRUE );
	
	// Constants: Folder directories/uri's
	define( 'NM_THEME_DIR', get_template_directory() );
	define( 'NM_DIR', get_template_directory() . '/includes' );
	define( 'NM_THEME_URI', get_template_directory_uri() );
	define( 'NM_URI', get_template_directory_uri() . '/includes' );
	
	// Constant: Framework namespace
	define( 'NM_NAMESPACE', 'nm-framework' );
	
	// Constant: Theme version
    $theme = wp_get_theme();
    $theme_parent = $theme->parent();
    $theme_version = ( $theme_parent ) ? $theme_parent->get( 'Version' ) : $theme->get( 'Version' );
    define( 'NM_THEME_VERSION', $theme_version );

	// Global: Theme options
	global $nm_theme_options;
	
	// Global: Page includes
	global $nm_page_includes;
	$nm_page_includes = array();
	
	// Global: <body> class
	global $nm_body_class;
	$nm_body_class = array();
	
	// Global: Theme globals
	global $nm_globals;
	$nm_globals = array();
	
    // Globals: WooCommerce - Cart panel quantity throttle
    $nm_globals['cart_panel_qty_throttle'] = intval( apply_filters( 'nm_cart_panel_qty_throttle', 0 ) );

    // Globals: WooCommerce - Shop search
    $nm_globals['shop_search_enabled']  = false;
    $nm_globals['shop_search']          = false;
    $nm_globals['shop_search_header']   = false;
    $nm_globals['shop_search_popup']    = false;
    
    // Globals: WooCommerce - Search suggestions
    $nm_globals['shop_search_suggestions_max_results'] = 6;

    // Globals: WooCommerce - Shop header
    $nm_globals['shop_header_centered'] = false;

	// Global: WooCommerce - "Product Slider" shortcode loop
	$nm_globals['product_slider_loop'] = false;
	
	// Global: WooCommerce - Shop image lazy-loading
	$nm_globals['shop_image_lazy_loading'] = false;
	
    // Globals: WooCommerce - Custom variation controls
    $nm_globals['pa_color_slug'] = sanitize_title( apply_filters( 'nm_color_attribute_slug', 'color' ) );
    $nm_globals['pa_variation_controls'] = array(
        'color' => esc_html__( 'Color', 'nm-framework-admin' ),
        'image' => esc_html__( 'Image', 'nm-framework-admin' ),
        'size'  => esc_html__( 'Label', 'nm-framework-admin' )
    );
    $nm_globals['pa_cache'] = array();
    
    // Globals: WooCommerce - Wishlist
    $nm_globals['wishlist_enabled'] = false;
    
    
    /* Admin localisation (must be placed before admin includes)
    ==================================================================================================== */
    
    if ( defined( 'NM_ADMIN_LOCALISATION' ) && is_admin() ) {
        $language_dir = apply_filters( 'nm_admin_languages_dir', NM_THEME_DIR . '/languages/admin' );
        
        load_theme_textdomain( 'nm-framework-admin', $language_dir );
        load_theme_textdomain( 'redux-framework', $language_dir );
    }
    
    
    /* WP Rocket: Deactivate WooCommerce refresh cart fragments cache: https://docs.wp-rocket.me/article/1100-optimize-woocommerce-get-refreshed-fragments
	==================================================================================================== */
    
    $wpr_cart_fragments_cache = apply_filters( 'nm_wpr_cart_fragments_cache', false );
    if ( ! $wpr_cart_fragments_cache ) {
        add_filter( 'rocket_cache_wc_empty_cart', '__return_false' );
    }
    
    
    /* Redux theme options framework
	==================================================================================================== */
	
    if ( ! isset( $redux_demo ) ) {
        require( NM_DIR . '/options/options-config.php' );
        
        // Include: "Custom Code" section class
        if ( ! class_exists( 'NM_Custom_Code' ) ) { // Make sure the class isn't defined from an older version of the "Savoy Theme - Content Element" plugin
            include( NM_DIR . '/options/custom-code.php' );
        }
        // Add "Custom Code" section
        if ( class_exists( 'NM_Custom_Code' ) ) {
            NM_Custom_Code::add_settings_section();
        }
    }

    // Get theme options
    $nm_theme_options = get_option( 'nm_theme_options' );

    // Is the theme options array saved?
    if ( ! $nm_theme_options ) {
        // Save default options array
        require( NM_DIR . '/options/default-options.php' );
    }
    
    do_action( 'nm_theme_options_set' );
    
    
	/* Includes
	==================================================================================================== */        	
    
    if ( file_exists( NM_DIR . '/tgmpa/tp.php' ) ) {
        include( NM_DIR . '/tgmpa/tp.php' );
    }

    // Custom CSS
    require( NM_DIR . '/custom-styles.php' );

	// Helper functions
	require( NM_DIR . '/helpers.php' );
	
	// Admin meta
	require( NM_DIR . '/admin-meta.php' );
	
    // Block editor (Gutenberg)
    require( NM_DIR . '/block-editor/block-editor.php' );
    
	// Visual composer
	require( NM_DIR . '/visual-composer/init.php' );
	
	if ( nm_woocommerce_activated() ) {
        // Globals: WooCommerce - Custom variation controls
        $nm_globals['custom_variation_controls'] = ( $nm_theme_options['product_display_attributes'] || $nm_theme_options['shop_filters_custom_controls'] || $nm_theme_options['product_custom_controls'] ) ? true : false;
        
        // WooCommerce: Wishlist
		$nm_globals['wishlist_enabled'] = class_exists( 'NM_Wishlist' );
        
		// WooCommerce: Functions
		include( NM_DIR . '/woocommerce/woocommerce-functions.php' );
        // WooCommerce: Template functions
		include( NM_DIR . '/woocommerce/woocommerce-template-functions.php' );
        // WooCommerce: Attribute functions
		if ( $nm_globals['custom_variation_controls'] ) {
            include( NM_DIR . '/woocommerce/woocommerce-attribute-functions.php' );
        }
		
		// WooCommerce: Quick view
		if ( $nm_theme_options['product_quickview'] ) {
			$nm_page_includes['quickview'] = true;
			include( NM_DIR . '/woocommerce/quickview.php' );
		}
		
		// WooCommerce: Shop search
        if ( $nm_theme_options['shop_search'] !== '0' ) {
            // Globals: Shop search
			$nm_globals['shop_search_enabled'] = true;
            if ( $nm_theme_options['shop_search'] === 'header' ) {
                $nm_globals['shop_search_header'] = true;
            }
            
            include( NM_DIR . '/woocommerce/search.php' );
            
            // WooCommerce: Search suggestions
            if ( ( $nm_globals['shop_search_header'] && $nm_theme_options['shop_search_suggestions'] ) || defined( 'NM_SUGGESTIONS_INCLUDE' ) ) {
                $nm_globals['shop_search_suggestions_max_results'] = intval( apply_filters( 'nm_shop_search_suggestions_max_results', $nm_theme_options['shop_search_suggestions_max_results'] ) );
                
                include( NM_DIR . '/woocommerce/class-search-suggestions.php' );
            }
		}
        
        // WooCommerce: Cart - Shipping meter
        if ( $nm_theme_options['cart_shipping_meter'] ) {
            include( NM_DIR . '/woocommerce/class-cart-free-shipping-meter.php' );
        }
	}
    
    
    /* Admin includes
	==================================================================================================== */
    
	if ( is_admin() ) {
        // TGM plugin activation
		require( NM_DIR . '/tgmpa/config.php' );
        
        // Theme setup wizard
        require_once( NM_DIR . '/setup/class-nm-setup.php' );
        
        if ( nm_woocommerce_activated() ) {
			// WooCommerce: Product details
			include( NM_DIR . '/woocommerce/admin/admin-product-details.php' );
			// WooCommerce: Product categories
			include( NM_DIR . '/woocommerce/admin/class-admin-product-categories.php' );
            // WooCommerce: Product attributes
			if ( $nm_globals['custom_variation_controls'] ) {
                include( NM_DIR . '/woocommerce/admin/class-admin-product-attributes.php' );
                include( NM_DIR . '/woocommerce/admin/class-admin-product-data.php' );
            }
            
            // WooCommerce: Product editor blocks
			//include( NM_DIR . '/woocommerce/admin/admin-product-editor-blocks.php' );
		}
	}
    
    
	/* Globals (requires includes)
	==================================================================================================== */
    
    // Globals: Login link
    $nm_globals['login_popup'] = false;
    
    // Globals: Cart link/panel
	$nm_globals['cart_link']   = false;
	$nm_globals['cart_panel']  = false;

    // Globals: Shop filters popup
    $nm_globals['shop_filters_popup'] = false;

	// Globals: Shop filters scrollbar
	$nm_globals['shop_filters_scrollbar'] = false;
    
    // Globals: Infinite load - Snapback cache
    $nm_globals['snapback_cache'] = 0;
    $nm_globals['snapback_cache_links'] = '';

	if ( nm_woocommerce_activated() ) {
		// Global: Shop page id
		$nm_globals['shop_page_id'] = ( ! empty( $_GET['shop_page'] ) ) ? intval( $_GET['shop_page'] ) : wc_get_page_id( 'shop' );
		
		// Globals: Login link
		$nm_globals['login_popup'] = ( $nm_theme_options['menu_login_popup'] ) ? true : false;
        
		// Global: Cart link/panel
		if ( $nm_theme_options['menu_cart'] != '0' && ! $nm_theme_options['shop_catalog_mode'] ) {
			$nm_globals['cart_link'] = true;
			
			// Is mini cart panel enabled?
			if ( $nm_theme_options['menu_cart'] != 'link' ) {
				$nm_globals['cart_panel'] = true;
			}
		}
		
        // Globals: Shop filters popup
        if ( $nm_theme_options['shop_filters'] == 'popup' ) {
            $nm_globals['shop_filters_popup'] = true;
        }
        
		// Globals: Shop filters scrollbar
        if ( $nm_theme_options['shop_filters_scrollbar'] ) {
			$nm_globals['shop_filters_scrollbar'] = true;
		}
        
        // Globals: Shop search
        if ( $nm_globals['shop_search_enabled'] && ! $nm_globals['shop_search_header'] ) {
            if ( $nm_globals['shop_filters_popup'] ) {
                $nm_globals['shop_search_popup'] = true; // Show search in filters pop-up
            } else {
                $nm_globals['shop_search'] = true; // Show search in shop header
            }
        }
        
        // Globals: Infinite load - Snapback cache
        if ( $nm_theme_options['shop_infinite_load'] !== '0' ) {
            $nm_globals['snapback_cache'] = apply_filters( 'nm_infload_snapback_cache', 0 );
            
            if ( $nm_globals['snapback_cache'] ) {
                // Shop links that can be used to generate cache
                $snapback_cache_links = array(
                    '.nm-shop-loop-attribute-link',
                    '.product_type_variable',
                    '.product_type_grouped',
                );
                if ( $nm_theme_options['product_quickview_link_actions']['link'] !== '1' ) {
                    $snapback_cache_links[] = '.nm-quickview-btn';
                }
                if ( $nm_theme_options['product_quickview_link_actions']['thumb'] !== '1' ) {
                    $snapback_cache_links[] = '.nm-shop-loop-thumbnail-link';
                }
                if ( $nm_theme_options['product_quickview_link_actions']['title'] !== '1' ) {
                    $snapback_cache_links[] = '.nm-shop-loop-title-link';
                }

                $snapback_cache_links = apply_filters( 'nm_infload_snapback_cache_links', $snapback_cache_links );

                $nm_globals['snapback_cache_links'] = implode ( ', ', $snapback_cache_links );
            }
        }
        
        // Globals: Product gallery zoom
        $nm_globals['product_image_hover_zoom'] = ( $nm_theme_options['product_image_hover_zoom'] ) ? true : false;
	}
	
	
	/* Theme Support
	==================================================================================================== */

	if ( ! function_exists( 'nm_theme_support' ) ) {
		function nm_theme_support() {
			global $nm_theme_options;
            
            // Let WordPress manage the document title (no hard-coded <title> tag in the document head)
            add_theme_support( 'title-tag' );
			
			// Enables post and comment RSS feed links to head
			add_theme_support( 'automatic-feed-links' );
			
			// Add thumbnail theme support
			add_theme_support( 'post-thumbnails' );
            
            // WooCommerce
			add_theme_support( 'woocommerce' );
            add_theme_support( 'wc-product-gallery-slider' );
            if ( $nm_theme_options['product_image_zoom'] ) {
                add_theme_support( 'wc-product-gallery-lightbox' );
            }
            
            // Localisation
            // Child theme language directory: wp-content/themes/child-theme-name/languages/xx_XX.mo
            $textdomain_loaded = load_theme_textdomain( 'nm-framework', get_stylesheet_directory() . '/languages' );
            // Theme language directory: wp-content/themes/theme-name/languages/xx_XX.mo
            if ( ! $textdomain_loaded ) {
                $textdomain_loaded = load_theme_textdomain( 'nm-framework', NM_THEME_DIR . '/languages' );
            }
			// WordPress language directory: wp-content/languages/theme-name/xx_XX.mo
			if ( ! $textdomain_loaded ) {
                load_theme_textdomain( 'nm-framework', trailingslashit( WP_LANG_DIR ) . 'nm-framework' );
            }
		}
	}
	add_action( 'after_setup_theme', 'nm_theme_support' );
	
	// Maximum width for media
	if ( ! isset( $content_width ) ) {
		$content_width = 1220; // Pixels
	}
	
	
	/* Styles
	==================================================================================================== */
	
	function nm_styles() {
		global $nm_theme_options, $nm_globals, $nm_page_includes;
        
        if ( defined( 'NM_DEBUG_MODE' ) && NM_DEBUG_MODE ) {
            $suffix = '';
        } else {
            $suffix = '.min';
        }
        
        // Deregister "WPZoom Instagram" widget styles (if widget isn't added)
        if ( defined( 'WPZOOM_INSTAGRAM_VERSION' ) ) {
            $deregister_wpzoom_styles = apply_filters( 'nm_deregister_wpzoom_styles', true );
            if ( $deregister_wpzoom_styles && ! is_active_widget( false, false, 'wpzoom_instagram_widget', true ) ) {
                wp_deregister_style( 'magnific-popup' );
                wp_deregister_style( 'zoom-instagram-widget' );
            }
        }
        
		// Enqueue third-party styles
		wp_enqueue_style( 'normalize', NM_THEME_URI . '/assets/css/third-party/normalize' . $suffix . '.css', array(), '3.0.2', 'all' );
		wp_enqueue_style( 'slick-slider', NM_THEME_URI . '/assets/css/third-party/slick' . $suffix . '.css', array(), '1.5.5', 'all' );
		wp_enqueue_style( 'slick-slider-theme', NM_THEME_URI . '/assets/css/third-party/slick-theme' . $suffix . '.css', array(), '1.5.5', 'all' );
        wp_enqueue_style( 'magnific-popup', NM_THEME_URI . '/assets/css/third-party/magnific-popup' . $suffix . '.css', array(), false, 'all' );
		if ( $nm_theme_options['font_awesome'] ) {
            if ( $nm_theme_options['font_awesome_version'] == '4' ) {
                wp_enqueue_style( 'font-awesome', '//stackpath.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css', array(), false, 'all' );
            } else {
                $font_awesome_cdn_url = apply_filters( 'nm_font_awesome_cdn_url', 'https://kit-free.fontawesome.com/releases/latest/css/free.min.css' );
                wp_enqueue_style( 'font-awesome', $font_awesome_cdn_url, array(), '5.x', 'all' );
            }
		}
		
		// Theme styles: Grid (enqueue before shop styles)
		wp_enqueue_style( 'nm-grid', NM_THEME_URI . '/assets/css/grid.css', array(), NM_THEME_VERSION, 'all' );
		
		// WooCommerce styles		
		if ( nm_woocommerce_activated() ) {
            if ( is_cart() ) {
                // Cart panel: Disable on "Cart" page
                $nm_globals['cart_panel'] = false;
            } else if ( is_checkout() ) {
                // Cart panel: Disable on "Checkout" page
                $nm_globals['cart_panel'] = false;
            }
            
            if ( $nm_theme_options['product_custom_select'] ) {
                wp_enqueue_style( 'selectod', NM_THEME_URI . '/assets/css/third-party/selectod' . $suffix . '.css', array(), '3.8.1', 'all' );
            }
			wp_enqueue_style( 'nm-shop', NM_THEME_URI . '/assets/css/shop.css', array(), NM_THEME_VERSION, 'all' );
		}
		
		// Theme styles
		wp_enqueue_style( 'nm-icons', NM_THEME_URI . '/assets/css/font-icons/theme-icons/theme-icons' . $suffix . '.css', array(), NM_THEME_VERSION, 'all' );
		wp_enqueue_style( 'nm-core', NM_THEME_URI . '/style.css', array(), NM_THEME_VERSION, 'all' );
		wp_enqueue_style( 'nm-elements', NM_THEME_URI . '/assets/css/elements.css', array(), NM_THEME_VERSION, 'all' );
	}
	add_action( 'wp_enqueue_scripts', 'nm_styles', 99 );
	
	
	/* Scripts
	==================================================================================================== */
	
    /* Scripts: Get Path and Suffix presets (includes un-minified scripts in "debug mode") */
    function nm_scripts_get_presets() {
        $presets = array();
        
        if ( defined( 'NM_DEBUG_MODE' ) && NM_DEBUG_MODE ) {
            $presets['path'] = NM_THEME_URI . '/assets/js/dev/';
            $presets['suffix'] = '';
        } else {
            $presets['path'] = NM_THEME_URI . '/assets/js/';
            $presets['suffix'] = '.min';
        }
        
        return $presets;
    }
    
    /* Scripts: Product page  */
    function nm_scripts_product_page( $presets ) {
        global $nm_globals;
        
        if ( $nm_globals['product_image_hover_zoom'] ) {
            wp_enqueue_script( 'easyzoom', NM_THEME_URI . '/assets/js/plugins/easyzoom.min.js', array( 'jquery' ), '2.5.2', true );
        }
        wp_enqueue_script( 'selectod' );
        wp_enqueue_script( 'nm-shop-add-to-cart' );
        wp_enqueue_script( 'nm-shop-single-product', $presets['path'] . 'nm-shop-single-product' . $presets['suffix'] . '.js', array( 'jquery', 'nm-shop' ), NM_THEME_VERSION, true );
    }
    
    /* Scripts: Enqueue */
	function nm_scripts() {
		if ( ! is_admin() ) {
			global $nm_theme_options, $nm_globals, $nm_page_includes;
			
			// Script path and suffix setup (debug mode loads un-minified scripts)
            $presets = nm_scripts_get_presets();
            
            // Register scripts
            wp_register_script( 'nm-masonry', NM_THEME_URI . '/assets/js/plugins/masonry.pkgd.min.js', array(), '4.2.2', true ); // Note: Using "nm-" prefix so the included WP version isn't used (it doesn't support the "horizontalOrder" option)
            wp_register_script( 'smartscroll', NM_THEME_URI . '/assets/js/plugins/jquery.smartscroll.min.js', array( 'jquery' ), '1.0', true );
            
			// Enqueue scripts
			wp_enqueue_script( 'modernizr', NM_THEME_URI . '/assets/js/plugins/modernizr.min.js', array( 'jquery' ), '2.8.3', true );
            if ( $nm_globals['snapback_cache'] ) {
                wp_enqueue_script( 'snapback-cache', NM_THEME_URI . '/assets/js/plugins/snapback-cache.min.js', array( 'jquery' ), NM_THEME_VERSION, true );
            }
            wp_enqueue_script( 'slick-slider', NM_THEME_URI . '/assets/js/plugins/slick.min.js', array( 'jquery' ), '1.5.5', true );
			wp_enqueue_script( 'magnific-popup', NM_THEME_URI . '/assets/js/plugins/jquery.magnific-popup.min.js', array( 'jquery' ), '1.2.0', true );
            wp_enqueue_script( 'nm-core', $presets['path'] . 'nm-core' . $presets['suffix'] . '.js', array( 'jquery' ), NM_THEME_VERSION, true );
			
			// Enqueue blog scripts
            wp_enqueue_script( 'nm-blog', $presets['path'] . 'nm-blog' . $presets['suffix'] . '.js', array( 'jquery' ), NM_THEME_VERSION, true );
			if ( $nm_theme_options['blog_infinite_load'] === 'scroll' ) {
                wp_enqueue_script( 'smartscroll' );
            }
			
			// WP comments script
			if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}
			
			if ( nm_woocommerce_activated() ) {
				// Register shop/product scripts
				if ( $nm_theme_options['product_custom_select'] ) {
                    wp_register_script( 'selectod', NM_THEME_URI . '/assets/js/plugins/selectod.custom.min.js', array( 'jquery' ), '3.8.1', true );
                }
				if ( $nm_theme_options['product_ajax_atc'] && get_option( 'woocommerce_cart_redirect_after_add' ) == 'no' ) {
                    wp_register_script( 'nm-shop-add-to-cart', $presets['path'] . 'nm-shop-add-to-cart' . $presets['suffix'] . '.js', array( 'jquery', 'nm-shop' ), NM_THEME_VERSION, true );
                }
				wp_register_script( 'nm-shop', $presets['path'] . 'nm-shop' . $presets['suffix'] . '.js', array( 'jquery', 'nm-core'/*, 'selectod'*/ ), NM_THEME_VERSION, true );
				wp_register_script( 'nm-shop-quickview', $presets['path'] . 'nm-shop-quickview' . $presets['suffix'] . '.js', array( 'jquery', 'nm-shop', 'wc-add-to-cart-variation' ), NM_THEME_VERSION, true );
				wp_register_script( 'nm-shop-login', $presets['path'] . 'nm-shop-login' . $presets['suffix'] . '.js', array( 'jquery' ), NM_THEME_VERSION, true );
                wp_register_script( 'nm-shop-infload', $presets['path'] . 'nm-shop-infload' . $presets['suffix'] . '.js', array( 'jquery', 'nm-shop' ), NM_THEME_VERSION, true );
				wp_register_script( 'nm-shop-filters', $presets['path'] . 'nm-shop-filters' . $presets['suffix'] . '.js', array( 'jquery', 'nm-shop' ), NM_THEME_VERSION, true );
                
				// Login popup
				if ( $nm_globals['login_popup'] ) {
					wp_enqueue_script( 'nm-shop-login' );
                    
                    // Enqueue "password strength meter" script
                    // Note: The code below is from the "../plugins/woocommerce/includes/class-wc-frontend-scripts.php" file
                    if ( ! is_cart() || ! is_checkout() || ! is_account_page() ) {
                        if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) && ! is_user_logged_in() ) {
                            wp_enqueue_script( 'wc-password-strength-meter' );
                            wp_localize_script( 'wc-password-strength-meter', 'wc_password_strength_meter_params', apply_filters( 'wc_password_strength_meter_params', array(
                                'min_password_strength' => apply_filters( 'woocommerce_min_password_strength', 3 ),
                                'i18n_password_error'   => esc_attr__( 'Please enter a stronger password.', 'woocommerce' ),
                                'i18n_password_hint'    => esc_attr( wp_get_password_hint() ),
                            ) ) );
                        }
                    }
				}
                
                // Cart panel - Quantity arrows: Make sure WooCommerce cart fragments script is enqueued
                if ( $nm_theme_options['cart_panel_quantity_arrows'] ) {
                    wp_enqueue_script( 'wc-cart-fragments' );
                }
                
                // Product search
                if ( $nm_globals['shop_search_enabled'] ) {
                    wp_enqueue_script( 'nm-shop-search', $presets['path'] . 'nm-shop-search' . $presets['suffix'] . '.js', array( 'jquery' ), NM_THEME_VERSION, true );
                }
                
				// WooCommerce page - Note: Does not include the Cart, Checkout or Account pages
				if ( is_woocommerce() ) {
					// Single product page
					if ( is_product() ) {
                        nm_scripts_product_page( $presets );
					} 
					// Shop page (except Single product, Cart and Checkout)
					else {
                        if ( $nm_theme_options['shop_infinite_load'] !== '0' ) {
                            wp_enqueue_script( 'smartscroll' );
                            wp_enqueue_script( 'nm-shop-infload' );
                        }
						wp_enqueue_script( 'nm-shop-filters' );
					}
				} else {
					// Cart page
					if ( is_cart() ) {
						wp_enqueue_script( 'nm-shop-cart', $presets['path'] . 'nm-shop-cart' . $presets['suffix'] . '.js', array( 'jquery', 'nm-shop' ), NM_THEME_VERSION, true );
					} 
					// Checkout page
					else if ( is_checkout() ) {
						wp_enqueue_script( 'nm-shop-checkout', $presets['path'] . 'nm-shop-checkout' . $presets['suffix'] . '.js', array( 'jquery', 'nm-shop' ), NM_THEME_VERSION, true );
					}
					// Account page
					else if ( is_account_page() ) {
						wp_enqueue_script( 'nm-shop-login' );
					}
				}
			}
			
			// Add local Javascript variables
            $local_js_vars = array(
                'themeUri' 				        => NM_THEME_URI,
                'ajaxUrl' 				        => admin_url( 'admin-ajax.php', 'relative' ),
                'woocommerceAjaxUrl'            => ( class_exists( 'WC_AJAX' ) ) ? WC_AJAX::get_endpoint( "%%endpoint%%" ) : '',
				'searchUrl'				        => esc_url_raw( add_query_arg( 's', '%%nmsearchkey%%', home_url( '/' ) ) ), // Code from "WC_AJAX->get_endpoint()" WooCommerce function
                'pageLoadTransition'            => intval( $nm_theme_options['page_load_transition'] ),
                'topBarCycleInterval'           => intval( apply_filters( 'nm_top_bar_cycle_interval', 5000 ) ),
                'headerPlaceholderSetHeight'    => intval( apply_filters( 'nm_header_placeholder_set_height', 1 ) ),
                'cartPanelQtyArrows'            => intval( $nm_theme_options['cart_panel_quantity_arrows'] ),
                'cartPanelQtyThrottleTimeout'   => $nm_globals['cart_panel_qty_throttle'],
                'cartPanelShowOnAtc'            => intval( $nm_theme_options['widget_panel_show_on_atc'] ),
                'cartPanelHideOnAtcScroll'      => ( ! defined( 'NM_ATC_SCROLL' ) ) ? 1 : 0,
                'cartShippingMeter'             => intval( $nm_theme_options['cart_shipping_meter'] ),
                'shopFiltersAjax'		        => esc_attr( $nm_theme_options['shop_filters_enable_ajax'] ),
                'shopFiltersMobileAutoClose'    => intval( apply_filters( 'nm_shop_filters_mobile_auto_close', 1 ) ),
                'shopFiltersPopupAutoClose'     => intval( apply_filters( 'nm_shop_filters_popup_auto_close', 1 ) ),
				'shopAjaxUpdateTitle'	        => intval( $nm_theme_options['shop_ajax_update_title'] ),
				'shopImageLazyLoad'		        => intval( $nm_theme_options['product_image_lazy_loading'] ),
                'shopAttsSwapImage'             => intval( $nm_theme_options['product_attributes_swap_image'] ),
                'shopAttsSwapImageRevert'       => intval( apply_filters( 'nm_product_attributes_swap_image_revert', 1 ) ),
                'shopAttsSwapImageOnTouch'      => intval( apply_filters( 'nm_product_attributes_swap_image_ontouch', 1 ) ),
                'shopScrollOffset' 		        => intval( $nm_theme_options['shop_scroll_offset'] ),
				'shopScrollOffsetTablet'        => intval( $nm_theme_options['shop_scroll_offset_tablet'] ),
                'shopScrollOffsetMobile'        => intval( $nm_theme_options['shop_scroll_offset_mobile'] ),
                'shopSearch'                    => ( $nm_globals['shop_search_enabled']  ) ? 1 : 0,
                'shopSearchHeader'			    => ( $nm_globals['shop_search_header'] ) ? 1 : 0,
                'shopSearchUrl'                 => esc_url_raw( apply_filters( 'nm_shop_search_url', add_query_arg( array( 'post_type' => 'product', 's' => '%%nmsearchkey%%' ), home_url( '/' ) ) ) ),
                'shopSearchMinChar'		        => intval( $nm_theme_options['shop_search_min_char'] ),
				'shopSearchAutoClose'           => 0,//intval( $nm_theme_options['shop_search_auto_close'] ),
                'searchSuggestions'             => intval( $nm_theme_options['shop_search_suggestions'] ),
                'searchSuggestionsInstant'      => intval( $nm_theme_options['shop_search_suggestions_instant'] ),
                'searchSuggestionsMax'          => $nm_globals['shop_search_suggestions_max_results'],
                'shopAjaxAddToCart'		        => ( $nm_theme_options['product_ajax_atc'] && get_option( 'woocommerce_cart_redirect_after_add' ) == 'no' ) ? 1 : 0,
                'shopRedirectScroll'            => intval( $nm_theme_options['product_redirect_scroll'] ),
                'shopCustomSelect'              => intval( $nm_theme_options['product_custom_select'] ),
                'quickviewLinks'                => $nm_theme_options['product_quickview_link_actions'],
                'quickViewGalleryInfinite'      => intval( apply_filters( 'nm_quickview_gallery_infinite', 0 ) ), // Note: Not compatible with variation images (since first image is cloned)
                'galleryZoom'                   => intval( $nm_theme_options['product_image_zoom'] ),
                'galleryThumbnailsSlider'       => intval( $nm_theme_options['product_thumbnails_slider'] ),
                'shopYouTubeRelated'            => ( ! defined( 'NM_SHOP_YOUTUBE_RELATED' ) ) ? 1 : 0,
                'productPinDetailsOffset'       => intval( apply_filters( 'nm_product_pin_details_offset', 30 ) ),
                'productAccordionCloseOpen'     => intval( apply_filters( 'nm_product_accordion_close_open', 1 ) ),
                'checkoutTacLightbox'           => intval( $nm_theme_options['checkout_tac_lightbox'] ),
                'rowVideoOnTouch'               => ( ! defined( 'NM_ROW_VIDEO_ON_TOUCH' ) ) ? 0 : 1,
                'wpGalleryPopup'                => intval( $nm_theme_options['wp_gallery_popup'] ),
                'touchHover'		            => intval( apply_filters( 'nm_touch_hover', 0 ) ), // Note: Set to "0" in v3.0.6
                'pushStateMobile'               => intval( apply_filters( 'nm_push_state_mobile', 1 ) ), // Note: Set to "1" in v2.7.5
                'infloadBuffer'                 => intval( apply_filters( 'nm_infload_scroll_buffer', 0 ) ),
                'infloadBufferBlog'             => intval( apply_filters( 'nm_blog_infload_scroll_buffer', 0 ) ),
                'infloadPreserveScrollPos'      => intval( apply_filters( 'nm_infload_preserve_scroll_position', 1 ) ),
                'infloadSnapbackCache'          => intval( $nm_globals['snapback_cache'] ),
                'infloadSnapbackCacheLinks'     => esc_attr( $nm_globals['snapback_cache_links'] ),
			);
    		wp_localize_script( 'nm-core', 'nm_wp_vars', $local_js_vars );
		}
	}
	add_action( 'wp_enqueue_scripts', 'nm_scripts' );
	
    
    /* Scripts - Content dependent: Uses globals to check for included content */
	function nm_scripts_content_dependent() {
		if ( ! is_admin() ) {
			global $nm_theme_options, $nm_globals, $nm_page_includes;
			
			// Blog
			if ( isset( $nm_page_includes['blog-masonry'] ) ) {
                wp_enqueue_script( 'nm-masonry' );
            }
			
			if ( nm_woocommerce_activated() ) {
                // Product categories
                if ( isset( $nm_page_includes['product_categories_masonry'] ) ) {
                    wp_enqueue_script( 'nm-masonry' );
                }
                
				// Shop/products
				if ( isset( $nm_page_includes['products'] ) ) {
					if ( $nm_theme_options['product_image_lazy_loading'] ) {
                        wp_enqueue_script( 'lazysizes', NM_THEME_URI . '/assets/js/plugins/lazysizes.min.js', array(), '4.0.1', true );
                    }
                    wp_enqueue_script( 'selectod' );
					wp_enqueue_script( 'nm-shop-add-to-cart' );
					if ( $nm_theme_options['product_quickview'] ) {
						wp_enqueue_script( 'nm-shop-quickview' );
					}
				} else if ( isset( $nm_page_includes['wishlist-home'] ) ) {
					wp_enqueue_script( 'nm-shop-add-to-cart' );
				}
                
                // Single product: Product page shortcode
                if ( ! is_product() && isset( $nm_globals['is_product'] ) ) {
                    $presets = nm_scripts_get_presets();
                    nm_scripts_product_page( $presets );
                }
				// Single product: Scroll gallery
                if ( isset( $nm_page_includes['product-layout-scroll'] ) ) {
                    wp_enqueue_script( 'pin', NM_THEME_URI . '/assets/js/plugins/jquery.pin.min.js', array( 'jquery' ), '1.0.3', true );
				}
			}
		}
	}
	add_action( 'wp_footer', 'nm_scripts_content_dependent' );
	
    
	/* Admin Assets
	==================================================================================================== */
	
	function nm_admin_assets( $hook ) {
		// Styles
		wp_enqueue_style( 'nm-admin-styles', NM_URI . '/assets/css/nm-wp-admin.css', array(), NM_THEME_VERSION, 'all' );
		
        // Menus page
		if ( 'nav-menus.php' == $hook ) {
            // Init assets for the WP media manager - https://codex.wordpress.org/Javascript_Reference/wp.media
            wp_enqueue_media();
            
            wp_enqueue_script( 'nm-admin-menus', NM_URI . '/assets/js/nm-wp-admin-menus.js', array( 'jquery' ), NM_THEME_VERSION );
        }
	}
	add_action( 'admin_enqueue_scripts', 'nm_admin_assets' );
	
	
	/* Web fonts
	==================================================================================================== */
	
	/* Adobe Fonts (formerly Typekit) */
	function nm_adobe_fonts() {
		global $nm_theme_options;
		
        $adobe_fonts_stylesheets = array();
        
        // Main/body font
        if ( $nm_theme_options['main_font_source'] === '2' && isset( $nm_theme_options['main_font_adobefonts_project_id'] ) ) {
            $adobe_fonts_stylesheets[] = $nm_theme_options['main_font_adobefonts_project_id'];
            wp_enqueue_style( 'nm-adobefonts-main', '//use.typekit.net/' . esc_attr( $nm_theme_options['main_font_adobefonts_project_id'] ) . '.css' );
        }
        
        // Header font
        if ( $nm_theme_options['header_font_source'] === '2' && isset( $nm_theme_options['header_font_adobefonts_project_id'] ) ) {
            // Make sure stylesheet name is unique (avoid multiple includes)
            if ( ! in_array( $nm_theme_options['header_font_adobefonts_project_id'], $adobe_fonts_stylesheets ) ) {
                $adobe_fonts_stylesheets[] = $nm_theme_options['header_font_adobefonts_project_id'];
                wp_enqueue_style( 'nm-adobefonts-header', '//use.typekit.net/' . esc_attr( $nm_theme_options['header_font_adobefonts_project_id'] ) . '.css' );
            }
        }
        
        // Headings font
        if ( $nm_theme_options['secondary_font_source'] === '2' && isset( $nm_theme_options['secondary_font_adobefonts_project_id'] ) ) {
            // Make sure stylesheet name is unique (avoid multiple includes)
            if ( ! in_array( $nm_theme_options['secondary_font_adobefonts_project_id'], $adobe_fonts_stylesheets ) ) {
                $adobe_fonts_stylesheets[] = $nm_theme_options['secondary_font_adobefonts_project_id'];
                wp_enqueue_style( 'nm-adobefonts-secondary', '//use.typekit.net/' . esc_attr( $nm_theme_options['secondary_font_adobefonts_project_id'] ) . '.css' );
            }
        }
	};
	add_action( 'wp_enqueue_scripts', 'nm_adobe_fonts' );
	
    
    /* WP Customizer - Notice
	==================================================================================================== */

    function nm_wpcustomizer_notice() {
        $handle = 'nm-wpcustomizer-notice';
        
        wp_register_script( $handle, NM_URI . '/assets/js/nm-wpcustomizer-notice.js', array( 'customize-controls' ), NM_THEME_VERSION );
        
        // Get theme name (name changes when child-theme is activated)
        $theme_info = wp_get_theme();
        $theme_name = $theme_info->get('Name');
        $theme_name_nospaces = ( $theme_name ) ? preg_replace( '/\s+/', '', $theme_name ) : 'Savoy'; // Remove whitespace from theme name
        
        // Create URL for Typography settings page
        $typography_settings_url = admin_url( 'admin.php?page=' . $theme_name_nospaces . '&tab=6' );
        
        $notice = array(
            'notice' => sprintf(
                esc_html( '%sNote:%s Font settings are available on: <a href="%s">Theme Settings > Typography</a>', 'nm-framework-admin' ),
                '<strong>',
                '</strong>',
                $typography_settings_url
            )
        );
        
        wp_localize_script( $handle, 'nm_wpcustomizer_notice', $notice );
        wp_enqueue_script( $handle );
    }
    add_action( 'customize_controls_enqueue_scripts', 'nm_wpcustomizer_notice' );
	
    
	/* Redux Framework
	==================================================================================================== */
	
	/* Remove redux sub-menu from "Tools" admin menu */
	function nm_remove_redux_menu() {
		remove_submenu_page( 'tools.php', 'redux-about' );
	}
	add_action( 'admin_menu', 'nm_remove_redux_menu', 12 );
	
	
	/* Theme Setup
	==================================================================================================== */
    
    /* Video embeds: Wrap video element in "div" container (to make them responsive) */
    function nm_wrap_oembed( $html, $url, $attr ) {
        if ( false !== strpos( $url, 'vimeo.com' ) ) {
            return '<div class="nm-wp-video-wrap nm-wp-video-wrap-vimeo">' . $html . '</div>';
        }
        if ( false !== strpos( $url, 'youtube.com' ) ) {
            return '<div class="nm-wp-video-wrap nm-wp-video-wrap-youtube">' . $html . '</div>';
        }
        
        return $html;
    }
    add_filter( 'embed_oembed_html', 'nm_wrap_oembed', 10, 3 );
    
    function nm_wrap_video_embeds( $html ) {
        return '<div class="nm-wp-video-wrap">' . $html . '</div>';
    }
    add_filter( 'video_embed_html', 'nm_wrap_video_embeds' ); // Jetpack
    
    
    /* Body classes
	==================================================================================================== */
    
    function nm_body_classes( $classes ) {
        global $nm_theme_options, $nm_body_class, $nm_globals;
        $woocommerce_activated = nm_woocommerce_activated();
        
        // Make sure $nm_body_class is an array
        $nm_body_class = ( is_array( $nm_body_class ) ) ? $nm_body_class : array();
        
        // Page load transition class
        $nm_body_class[] = 'nm-page-load-transition-' . $nm_theme_options['page_load_transition'];

        // CSS animations preload class
        $nm_body_class[] = 'nm-preload';

        // Top bar class
        if ( $nm_theme_options['top_bar'] ) {
            $nm_body_class[] = 'has-top-bar top-bar-mobile-' . $nm_theme_options['top_bar_mobile'];
        }
        
        // Header: Classes - Fixed
        $header_checkout_allow_fixed = ( $woocommerce_activated && is_checkout() ) ? apply_filters( 'nm_header_checkout_allow_fixed', false ) : true;
        $nm_body_class[] = ( $nm_theme_options['header_fixed'] && $header_checkout_allow_fixed ) ? 'header-fixed' : '';
        
        // Header: Classes - Mobile layout
        //$nm_body_class[] = 'header-mobile-' . $nm_theme_options['header_layout_mobile'];
        $nm_body_class[] = apply_filters( 'nm_body_class_header_mobile', 'header-mobile-default' );
        
        // Header: Classes - Transparency
        global $post;
        $page_header_transparency = ( $post ) ? get_post_meta( $post->ID, 'nm_page_header_transparency', true ) : array();
        if ( ! empty( $page_header_transparency ) ) {
            $nm_body_class[] = 'header-transparency header-transparency-' . $page_header_transparency;
        } else if ( $nm_theme_options['header_transparency'] ) {
            if ( is_front_page() ) {
                $nm_body_class[] = ( $nm_theme_options['header_transparency_homepage'] !== '0' ) ? 'header-transparency header-transparency-' . $nm_theme_options['header_transparency_homepage'] : '';
            } else if ( is_home() ) { // Note: This is the blog/posts page, not the homepage
                $nm_body_class[] = ( $nm_theme_options['header_transparency_blog'] !== '0' ) ? 'header-transparency header-transparency-' . $nm_theme_options['header_transparency_blog'] : '';
            } else if ( is_singular( 'post' ) ) {
                $nm_body_class[] = ( $nm_theme_options['header_transparency_blog_post'] !== '0' ) ? 'header-transparency header-transparency-' . $nm_theme_options['header_transparency_blog_post'] : '';
            } else if ( $woocommerce_activated ) {
                if ( is_shop() ) {
                    $nm_body_class[] = ( $nm_theme_options['header_transparency_shop'] !== '0' ) ? 'header-transparency header-transparency-' . $nm_theme_options['header_transparency_shop'] : '';
                } else if ( is_product_taxonomy() ) {
                    $nm_body_class[] = ( $nm_theme_options['header_transparency_shop_categories'] !== '0' ) ? 'header-transparency header-transparency-' . $nm_theme_options['header_transparency_shop_categories'] : '';
                } else if ( is_product() ) {
                    $nm_body_class[] = ( $nm_theme_options['header_transparency_product'] !== '0' ) ? 'header-transparency header-transparency-' . $nm_theme_options['header_transparency_product'] : '';
                }
            }
        }

        // Header: Classes - Border
        if ( is_front_page() ) {
            $nm_body_class[] = 'header-border-' . $nm_theme_options['home_header_border'];
        } elseif ( $woocommerce_activated && ( is_shop() || is_product_taxonomy() ) ) {
            $nm_body_class[] = 'header-border-' . $nm_theme_options['shop_header_border'];
        } else {
            $nm_body_class[] = 'header-border-' . $nm_theme_options['header_border'];
        }
        
        // Header: Mobile menu
        $nm_body_class[] = 'mobile-menu-layout-' . $nm_theme_options['menu_mobile_layout'];
        if ( $nm_theme_options['menu_mobile_layout'] === 'side' ) {
            $nm_body_class[] = apply_filters( 'nm_mobile_menu_side_panels_class', 'mobile-menu-panels' );
        }
        if ( $nm_theme_options['menu_mobile_desktop'] ) {
            $nm_body_class[] = 'mobile-menu-desktop';
        }
        
        // Cart panel classes
        $nm_body_class[] = 'cart-panel-' . $nm_theme_options['widget_panel_color'];
        if ( $nm_globals['cart_panel_qty_throttle'] > 0 ) {
            $nm_body_class[] = 'cart-panel-qty-throttle';
        }

        // WooCommerce: login
        if ( $woocommerce_activated && ! is_user_logged_in() && is_account_page() ) {
            $nm_body_class[] = 'nm-woocommerce-account-login';
        }
        
        // WooCommerce: Catalog mode
        if ( $nm_theme_options['shop_catalog_mode'] ) {
            $nm_body_class[] = 'nm-catalog-mode';
        }
        
        // WooCommerce: Shop preloading
        //$nm_body_class[] = 'nm-shop-preloader-' . $nm_theme_options['shop_ajax_preloader_style'];
        $nm_globals['preloader_style'] = apply_filters( 'nm_shop_ajax_preloader_style', 'spinner' );
        $nm_body_class[] = 'nm-shop-preloader-' . $nm_globals['preloader_style'];
        
        // WooCommerce: Shop filters scroll
        $shop_scroll_options = apply_filters( 'nm_shop_scroll_options', array(
            'header'    => false,
            'default'   => true,
            'popup'     => true,
        ) );
        if ( isset( $shop_scroll_options[$nm_theme_options['shop_filters']] ) && $shop_scroll_options[$nm_theme_options['shop_filters']] == true ) {
            $nm_body_class[] = 'nm-shop-scroll-enabled';
        }
        
        $body_class = array_merge( $classes, $nm_body_class );
        
        return $body_class;
    }
    add_filter( 'body_class', 'nm_body_classes' );
    
    
    /* Header
	==================================================================================================== */
    
    /* Header: Get classes */
    function nm_header_get_classes() {
        global $nm_globals, $nm_theme_options;
        
        // Layout class
        $header_classes = $nm_theme_options['header_layout'];
        
        // Scroll class
        $header_scroll_class = apply_filters( 'nm_header_on_scroll_class', 'resize-on-scroll' );
        $header_classes .= ( strlen( $header_scroll_class ) > 0 ) ? ' ' . $header_scroll_class : '';

        // Alternative logo class
        if ( $nm_theme_options['alt_logo'] && isset( $nm_theme_options['alt_logo_visibility'] ) ) {
            $alt_logo_class = '';
            foreach( $nm_theme_options['alt_logo_visibility'] as $key => $val ) {
                if ( $val === '1' ) {
                    $alt_logo_class .= ' ' . $key;
                }
            }
            $header_classes .= $alt_logo_class;
        }
        
        // Mobile menu class
        $mobile_menu_icon_bold = apply_filters( 'header_mobile_menu_icon_bold', true );
        $header_classes .= ( $mobile_menu_icon_bold ) ? ' mobile-menu-icon-bold' : ' mobile-menu-icon-thin';
        
        return $header_classes;
    }
    
    /* Logo: Get logo */
    function nm_logo() {
        global $nm_theme_options;
        
        if ( isset( $nm_theme_options['logo'] ) && strlen( $nm_theme_options['logo']['url'] ) > 0 ) {
            $logo = array(
                'id'        => $nm_theme_options['logo']['id'],
                'url'       => ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['logo']['url'] ) : $nm_theme_options['logo']['url'],
                'width'     => $nm_theme_options['logo']['width'],
                'height'    => $nm_theme_options['logo']['height']
            );
        } else {
            $logo = array(
                'id'        => '',
                'url'       => NM_THEME_URI . '/assets/img/logo@2x.png',
                'width'     => '232',
                'height'    => '33'
            );
        }
        
        return apply_filters( 'nm_logo', $logo );
    }
    
    /* (Included for backwards compatibility) Logo: Get URL */
    function nm_logo_get_url() {
        global $nm_theme_options;
        
        if ( isset( $nm_theme_options['logo'] ) && strlen( $nm_theme_options['logo']['url'] ) > 0 ) {
            $logo_url = ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['logo']['url'] ) : $nm_theme_options['logo']['url'];
        } else {
            $logo_url = NM_THEME_URI . '/assets/img/logo@2x.png';
        }
        
        return $logo_url;
    }
    
    /* Alternative logo: Get logo */
    function nm_alt_logo() {
        global $nm_theme_options;
        
        $logo = null;
        
        if ( $nm_theme_options['alt_logo'] ) {
            // Logo URL
            if ( isset( $nm_theme_options['alt_logo_image'] ) && strlen( $nm_theme_options['alt_logo_image']['url'] ) > 0 ) {
                $logo = array(
                    'id'        => $nm_theme_options['alt_logo_image']['id'],
                    'url'       => ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['alt_logo_image']['url'] ) : $nm_theme_options['alt_logo_image']['url'],
                    'width'     => $nm_theme_options['alt_logo_image']['width'],
                    'height'    => $nm_theme_options['alt_logo_image']['height']
                );
            } else {
                $logo = array(
                    'id'        => '',
                    'url'       => NM_THEME_URI . '/assets/img/logo-light@2x.png',
                    'width'     => '232',
                    'height'    => '33'
                );
            }
        }
        
        return apply_filters( 'nm_alt_logo', $logo );
    }
    
    /* (Included for backwards compatibility) Alternative logo: Get URL */
    function nm_alt_logo_get_url() {
        global $nm_theme_options;
        
        $logo_url = null;
        
        if ( $nm_theme_options['alt_logo'] ) {
            // Logo URL
            if ( isset( $nm_theme_options['alt_logo_image'] ) && strlen( $nm_theme_options['alt_logo_image']['url'] ) > 0 ) {
                $logo_url = ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['alt_logo_image']['url'] ) : $nm_theme_options['alt_logo_image']['url'];
            } else {
                $logo_url = NM_THEME_URI . '/assets/img/logo-light@2x.png';
            }
        }
        
        return $logo_url;
    }
    
    /* Header: Mobile menu button */
    function nm_header_mobile_menu_button() {
        ?>
        <li class="nm-menu-offscreen menu-item-default">
            <?php //if ( nm_woocommerce_activated() ) { echo nm_get_cart_contents_count(); } ?>
            <a href="#" class="nm-mobile-menu-button clicked">
                <span class="nm-menu-icon">
                    <span class="line-1"></span>
                    <span class="line-2"></span>
                    <span class="line-3"></span>
                </span>
            </a>
        </li>
        <?php
    }
    
    
    /* Menus
	==================================================================================================== */
    
	if ( ! function_exists( 'nm_register_menus' ) ) {
		function nm_register_menus() {
			register_nav_menus(
                array(
                    'top-bar-menu'          => esc_html__( 'Top Bar', 'nm-framework' ),
                    'main-menu'             => esc_html__( 'Header Main', 'nm-framework' ),
                    'right-menu'            => esc_html__( 'Header Secondary', 'nm-framework' ),
                    'mobile-menu'           => esc_html__( 'Mobile', 'nm-framework-admin' ),
                    'mobile-menu-secondary' => esc_html__( 'Mobile Secondary', 'nm-framework-admin' ),
                    'footer-menu'           => esc_html__( 'Footer Bar', 'nm-framework' ),
                )
            );
		}
	}
	add_action( 'init', 'nm_register_menus' );
    
    // Menus: Include custom functions
    require( NM_DIR . '/menus/menus.php' );
    if ( is_admin() ) {
        require( NM_DIR . '/menus/menus-admin.php' );
    }
    
    
	/* Blog
	==================================================================================================== */
	
    /* AJAX: Get blog content */
	function nm_blog_get_ajax_content() {
        // Is content requested via AJAX?
        if ( isset( $_REQUEST['blog_load'] ) && nm_is_ajax_request() ) {
            // Include blog content only (no header or footer)
            get_template_part( 'template-parts/blog/content' );
            exit;
        }
    }
    
    /* Get static content */
    function nm_blog_get_static_content() {
        global $nm_theme_options;
        
        $blog_page = null;
        
        if ( isset( $nm_theme_options['blog_static_page'] ) && ! empty( $nm_theme_options['blog_static_page'] ) ) {
            if ( ! empty( $nm_theme_options['blog_static_page_id'] ) ) {
                if ( function_exists( 'nm_blog_index_vc_styles' ) ) {
                    // WPBakery: Include custom styles, if they exists
                    add_action( 'wp_head', 'nm_blog_index_vc_styles', 1000 );
                }
                
                // Using "nm_shop_get_page_content()" function for Elementor support: $blog_page = get_page( $nm_theme_options['blog_static_page_id'] );
                $blog_page = nm_shop_get_page_content( $nm_theme_options['blog_static_page_id'] );
            }
        }
            
        return $blog_page;
    }
    
	/* Post excerpt brackets - [...] */
	function nm_excerpt_read_more( $excerpt ) {
		$excerpt_more = '&hellip;';
		$trans = array(
			'[&hellip;]' => $excerpt_more // WordPress >= v3.6
		);
		
		return strtr( $excerpt, $trans );
	}
	add_filter( 'wp_trim_excerpt', 'nm_excerpt_read_more' );
	
	/* Blog categories menu */
	function nm_blog_category_menu() {
		global $wp_query, $nm_theme_options;

		$current_cat = ( is_category() ) ? $wp_query->queried_object->cat_ID : '';
		
		// Categories order
		$orderby = 'slug';
		$order = 'asc';
		if ( isset( $nm_theme_options['blog_categories_orderby'] ) ) {
			$orderby = $nm_theme_options['blog_categories_orderby'];
			$order = $nm_theme_options['blog_categories_order'];
		}
		
		$args = array(
			'type'			=> 'post',
			'orderby'		=> $orderby,
			'order'			=> $order,
			'hide_empty'	=> ( $nm_theme_options['blog_categories_hide_empty'] ) ? 1 : 0,
			'hierarchical'	=> 1,
			'taxonomy'		=> 'category'
		); 
		
		$categories = get_categories( $args );
		
		$current_class_set = false;
		$categories_output = '';
		
		// Categories menu divider
		$categories_menu_divider = apply_filters( 'nm_blog_categories_divider', '<span>&frasl;</span>' );
		
		foreach ( $categories as $category ) {
			if ( $current_cat == $category->cat_ID ) {
				$current_class_set = true;
				$current_class = ' class="current-cat"';
			} else {
				$current_class = '';
			}
			$category_link = get_category_link( $category->cat_ID );
			
			$categories_output .= '<li' . $current_class . '>' . $categories_menu_divider . '<a href="' . esc_url( $category_link ) . '">' . esc_attr( $category->name ) . '</a></li>';
		}
		
		$categories_count = count( $categories );
		
		// Categories layout classes
		$categories_class = ' toggle-' . $nm_theme_options['blog_categories_toggle'];
		if ( $nm_theme_options['blog_categories_layout'] === 'columns' ) {
			$column_small = ( intval( $nm_theme_options['blog_categories_columns'] ) > 4 ) ? '3' : '2';
			$categories_ul_class = 'columns small-block-grid-' . $column_small . ' medium-block-grid-' . $nm_theme_options['blog_categories_columns'];
		} else {
			$categories_ul_class = $nm_theme_options['blog_categories_layout'];
		}
		
		// "All" category class attr
		$current_class = ( $current_class_set ) ? '' : ' class="current-cat"';
		
		$output = '<div class="nm-blog-categories-wrap ' . esc_attr( $categories_class ) . '">';
		$output .= '<ul class="nm-blog-categories-toggle"><li><a href="#" id="nm-blog-categories-toggle-link">' . esc_html__( 'Categories', 'nm-framework' ) . '</a> <em class="count">' . $categories_count . '</em></li></ul>';
		$output .= '<ul id="nm-blog-categories-list" class="nm-blog-categories-list ' . esc_attr( $categories_ul_class ) . '"><li' . $current_class . '><a href="' . esc_url( get_post_type_archive_link( 'post' ) ) . '">' . esc_html__( 'All', 'nm-framework' ) . '</a></li>' . $categories_output . '</ul>';
        $output .= '</div>';
		
		return $output;
	}
    
	/* WP gallery */
    add_filter( 'use_default_gallery_style', '__return_false' );
    if ( $nm_theme_options['wp_gallery_popup'] ) {
        /* WP gallery popup: Set page include value */
        function nm_wp_gallery_set_include() {
            nm_add_page_include( 'wp-gallery' );
            return ''; // Returning an empty string will output the default WP gallery
        }
		add_filter( 'post_gallery', 'nm_wp_gallery_set_include' );
	}
    
    
	/* Comments
	==================================================================================================== */
    
    /* Comments callback */
	function nm_comments( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		
		switch ( $comment->comment_type ) :
			case 'pingback' :
			case 'trackback' :
		?>
		<li class="post pingback">
			<p><?php esc_html_e( 'Pingback:', 'nm-framework' ); ?> <?php comment_author_link(); ?><?php edit_comment_link( esc_html__( 'Edit', 'nm-framework' ), ' ' ); ?></p>
		<?php
			break;
			default :
		?>
		<li id="comment-<?php esc_attr( comment_ID() ); ?>" <?php comment_class(); ?>>
            <div class="comment-inner-wrap">
            	<?php if ( function_exists( 'get_avatar' ) ) { echo get_avatar( $comment, '60' ); } ?>
                
				<div class="comment-text">
                    <p class="meta">
                        <strong itemprop="author"><?php printf( '%1$s', get_comment_author_link() ); ?></strong>
                        <time itemprop="datePublished" datetime="<?php echo get_comment_date( 'c' ); ?>"><?php printf( esc_html__( '%1$s at %2$s', 'nm-framework' ), get_comment_date(), get_comment_time() ); ?></time>
                    </p>
                
                    <div itemprop="description" class="description entry-content">
                        <?php if ( $comment->comment_approved == '0' ) : ?>
                            <p class="moderating"><em><?php esc_html_e( 'Your comment is awaiting moderation', 'nm-framework' ); ?></em></p>
                        <?php endif; ?>
                        
                        <?php comment_text(); ?>
                    </div>
                    
                    <?php
                        $thread_comments = get_option( 'thread_comments' );
                        $user_can_edit_comment = ( current_user_can( 'edit_comment', $comment->comment_ID ) ) ? true : false;
                        
                        if ( $user_can_edit_comment || '1' === $thread_comments ) :
                    ?>
                    <div class="reply">
                        <?php 
                            edit_comment_link( esc_html__( 'Edit', 'nm-framework' ), '<span class="edit-link">', '</span><span> &nbsp;-&nbsp; </span>' );
                            
                            comment_reply_link( array_merge( $args, array(
                                'depth' 	=> $depth,
                                'max_depth'	=> $args['max_depth']
                            ) ) );
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
		<?php
			break;
		endswitch;
	}
    
    
	/* Sidebars & Widgets
	==================================================================================================== */
	
    /* Classic widgets: Enable the classic widgets settings screens */
    $classic_widgets = apply_filters( 'nm_classic_widgets', true );
    if ( $classic_widgets ) {
        add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' ); // Disables the block editor from managing widgets in the Gutenberg plugin.
        add_filter( 'use_widgets_block_editor', '__return_false' ); // Disables the block editor from managing widgets.
    }
    
	/* Register/include sidebars & widgets */
	function nm_widgets_init() {
		global $nm_globals, $nm_theme_options;
		
        // Sidebar: Page
		register_sidebar( array(
			'name' 				=> esc_html__( 'Page', 'nm-framework' ),
			'id' 				=> 'page',
			'before_widget'		=> '<div id="%1$s" class="widget %2$s">',
			'after_widget' 		=> '</div>',
			'before_title' 		=> '<h3 class="nm-widget-title">',
			'after_title' 		=> '</h3>'
		) );
        
		// Sidebar: Blog
		register_sidebar( array(
			'name' 				=> esc_html__( 'Blog', 'nm-framework' ),
			'id' 				=> 'sidebar',
			'before_widget'		=> '<div id="%1$s" class="widget %2$s">',
			'after_widget' 		=> '</div>',
			'before_title' 		=> '<h3 class="nm-widget-title">',
			'after_title' 		=> '</h3>'
		) );
        
		// Sidebar: Shop
		if ( $nm_globals['shop_filters_scrollbar'] ) {
            register_sidebar( array(
				'name' 				=> esc_html__( 'Shop', 'nm-framework' ),
				'id' 				=> 'widgets-shop',
				'before_widget'		=> '<li id="%1$s" class="scroll-enabled scroll-type-default widget %2$s"><div class="nm-shop-widget-col">',
				'after_widget' 		=> '</div></div></li>',
				'before_title' 		=> '<h3 class="nm-widget-title">',
				'after_title' 		=> '</h3></div><div class="nm-shop-widget-col"><div class="nm-shop-widget-scroll">'
			));
            
            // Prevent empty widget-titles so the scrollbar container is included
            function nm_widget_title( $title ) {
                if ( strlen( $title ) == 0 ) {
                    $title = '&nbsp;';
                }
                return $title;
            }
            add_filter( 'widget_title', 'nm_widget_title' );
		} else {
            register_sidebar( array(
				'name' 				=> esc_html__( 'Shop', 'nm-framework' ),
				'id' 				=> 'widgets-shop',
				'before_widget'		=> '<li id="%1$s" class="widget %2$s"><div class="nm-shop-widget-col">',
				'after_widget' 		=> '</div></li>',
				'before_title' 		=> '<h3 class="nm-widget-title">',
				'after_title' 		=> '</h3></div><div class="nm-shop-widget-col">'
			) );
		}
		
		// Sidebar: Footer
		register_sidebar( array(
			'name' 				=> esc_html__( 'Footer', 'nm-framework' ),
			'id' 				=> 'footer',
			'before_widget'		=> '<li id="%1$s" class="widget %2$s">',
			'after_widget' 		=> '</li>',
			'before_title' 		=> '<h3 class="nm-widget-title">',
			'after_title' 		=> '</h3>'
		) );
		
		// Sidebar: Visual Composer - Widgetised Sidebar
		register_sidebar( array(
			'name' 				=> esc_html__( '"Widgetised Sidebar" Element', 'nm-framework' ),
			'id' 				=> 'vc-sidebar',
			'before_widget'		=> '<div id="%1$s" class="widget %2$s">',
			'after_widget' 		=> '</div>',
			'before_title' 		=> '<h3 class="nm-widget-title">',
			'after_title' 		=> '</h3>'
		) );
		
		// WooCommerce: Unregister widgets
		unregister_widget( 'WC_Widget_Cart' );
	}
	add_action( 'widgets_init', 'nm_widgets_init' ); // Register widget sidebars
	
    
    /* Footer includes
	==================================================================================================== */  
    
    function nm_footer_includes() {
        global $nm_globals, $nm_page_includes;
        
        // Mobile menu
        get_template_part( 'template-parts/navigation/navigation', 'mobile' );
        
        // Cart panel
        if ( $nm_globals['cart_panel'] ) {
            get_template_part( 'template-parts/woocommerce/cart-panel' );
        }
        
        // Login panel
        if ( $nm_globals['login_popup'] && ! is_user_logged_in() && ! is_account_page() ) {
            get_template_part( 'template-parts/woocommerce/login' );
        }

        echo '<div id="nm-page-overlay"></div>';

        echo '<div id="nm-quickview" class="clearfix"></div>';
        
        // Page includes element
		$page_includes_classes = array();
		foreach ( $nm_page_includes as $class => $value ) {
			$page_includes_classes[] = $class;
        }
        $page_includes_classes = implode( ' ', $page_includes_classes );
		echo '<div id="nm-page-includes" class="' . esc_attr( $page_includes_classes ) . '" style="display:none;">&nbsp;</div>' . "\n\n";
    }
    add_action( 'wp_footer', 'nm_footer_includes' );
	
    
	/* Contact Form 7
	==================================================================================================== */
	
    // Disable default CF7 CSS
    add_filter( 'wpcf7_load_css', '__return_false' );
    
