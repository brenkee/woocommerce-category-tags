<?php
/**
 * Plugin Name: WooCommerce Category Tags Filter
 * Description: Megjeleníti az adott termékkategóriához tartozó címkéket gombokként a kategória archív oldalán, és ezek alapján szűrhetővé teszi a termékeket.
 * Version: 1.0.0
 * Author: OpenAI
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ellenőrzi, hogy jelenleg egy WooCommerce termékkategória archív oldal van-e betöltve.
 *
 * @return bool
 */
function wctags_is_product_category_archive() {
    return function_exists('is_product_category') && is_product_category();
}

/**
 * Lekéri az aktuális termékkategóriában szereplő termékek címkéit.
 *
 * @return WP_Term[]
 */
function wctags_get_category_product_tags() {
    if (!wctags_is_product_category_archive()) {
        return [];
    }

    $category = get_queried_object();

    if (!$category || !isset($category->slug)) {
        return [];
    }

    $product_ids = get_posts(
        [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category->slug,
                ],
            ],
        ]
    );

    if (empty($product_ids)) {
        return [];
    }

    $tags = wp_get_object_terms($product_ids, 'product_tag', [
        'orderby'    => 'name',
        'hide_empty' => true,
    ]);

    if (is_wp_error($tags)) {
        return [];
    }

    return $tags;
}

/**
 * Szűrő felület hozzáadása a kategória oldal tetejéhez.
 */
function wctags_render_tag_filter() {
    if (!wctags_is_product_category_archive()) {
        return;
    }

    $tags = wctags_get_category_product_tags();

    if (empty($tags)) {
        return;
    }

    $current_tag = isset($_GET['product_tag']) ? sanitize_text_field(wp_unslash($_GET['product_tag'])) : '';
    ?>
    <div class="wctags-filter">
        <div class="wctags-filter__buttons">
            <button class="wctags-filter__button<?php echo $current_tag === '' ? ' is-active' : ''; ?>" data-tag="">
                <?php esc_html_e('Összes', 'woocommerce-category-tags'); ?>
            </button>
            <?php foreach ($tags as $tag) : ?>
                <button class="wctags-filter__button<?php echo $current_tag === $tag->slug ? ' is-active' : ''; ?>" data-tag="<?php echo esc_attr($tag->slug); ?>">
                    <?php echo esc_html($tag->name); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <form class="wctags-filter__form" method="get">
            <input type="hidden" name="product_tag" value="<?php echo esc_attr($current_tag); ?>" />
            <?php
            foreach ($_GET as $key => $value) {
                if ('product_tag' === $key) {
                    continue;
                }
                printf('<input type="hidden" name="%s" value="%s" />', esc_attr($key), esc_attr(wp_unslash($value)));
            }
            ?>
        </form>
    </div>
    <?php
}
add_action('woocommerce_before_shop_loop', 'wctags_render_tag_filter', 8);

/**
 * Stílusok és szkriptek hozzáadása a kategória oldalra.
 */
function wctags_enqueue_assets() {
    if (!wctags_is_product_category_archive()) {
        return;
    }

    wp_enqueue_style(
        'wctags-filter',
        plugin_dir_url(__FILE__) . 'assets/wctags-filter.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'wctags-filter',
        plugin_dir_url(__FILE__) . 'assets/js/wctags-filter.js',
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script(
        'wctags-filter',
        'wctagsFilter',
        [
            'formSelector' => '.wctags-filter__form',
            'buttonSelector' => '.wctags-filter__button',
        ]
    );
}
add_action('wp_enqueue_scripts', 'wctags_enqueue_assets');
