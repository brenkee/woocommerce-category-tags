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
 * Eldönti, hogy a szűrő engedélyezve van-e az aktuális termékkategóriában.
 *
 * @return bool
 */
function wctags_is_filter_enabled_for_current_category() {
    if (!wctags_is_product_category_archive()) {
        return false;
    }

    $enabled_categories = get_option('wctags_enabled_categories', 'all');

    if ('all' === $enabled_categories) {
        return true;
    }

    if (!is_array($enabled_categories)) {
        return false;
    }

    $enabled_categories = array_map('absint', $enabled_categories);

    $category = get_queried_object();

    if (!$category || !isset($category->term_id)) {
        return false;
    }

    return in_array((int) $category->term_id, $enabled_categories, true);
}

/**
 * Lekéri az aktuális termékkategóriában szereplő termékek címkéit.
 *
 * @return WP_Term[]
 */
function wctags_get_category_product_tags() {
    if (!wctags_is_filter_enabled_for_current_category()) {
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
    if (!wctags_is_filter_enabled_for_current_category()) {
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
    if (!wctags_is_filter_enabled_for_current_category()) {
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

/**
 * Beállítások oldal regisztrálása a bővítményhez.
 */
function wctags_register_settings_page() {
    add_menu_page(
        __('Termékcímke szűrő', 'woocommerce-category-tags'),
        __('Címke szűrő', 'woocommerce-category-tags'),
        'manage_options',
        'wctags-settings',
        'wctags_render_settings_page',
        'dashicons-filter'
    );
}
add_action('admin_menu', 'wctags_register_settings_page');

/**
 * Beállítások oldal tartalma.
 */
function wctags_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $categories = get_terms(
        [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    $enabled_categories = get_option('wctags_enabled_categories', 'all');

    if (isset($_POST['wctags_settings_submit'])) {
        check_admin_referer('wctags_settings');

        $selected_categories = isset($_POST['wctags_categories']) ? array_map('absint', (array) $_POST['wctags_categories']) : [];

        if (!empty($categories) && !is_wp_error($categories) && count($selected_categories) === count($categories)) {
            $selected_categories = 'all';
        }

        update_option('wctags_enabled_categories', $selected_categories);
        $enabled_categories = $selected_categories;

        add_settings_error('wctags_messages', 'wctags_messages', __('Beállítások mentve.', 'woocommerce-category-tags'), 'updated');
    }

    settings_errors('wctags_messages');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Termékcímke szűrő beállításai', 'woocommerce-category-tags'); ?></h1>
        <p><?php esc_html_e('Válaszd ki, mely termékkategóriákban legyen aktív a címke szűrő.', 'woocommerce-category-tags'); ?></p>
        <form method="post">
            <?php wp_nonce_field('wctags_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Engedélyezett kategóriák', 'woocommerce-category-tags'); ?></th>
                        <td>
                            <?php if (empty($categories) || is_wp_error($categories)) : ?>
                                <p><?php esc_html_e('Nem található termékkategória.', 'woocommerce-category-tags'); ?></p>
                            <?php else : ?>
                                <?php foreach ($categories as $category) : ?>
                                    <?php
                                    $is_enabled_by_default = 'all' === $enabled_categories;
                                    $is_enabled = $is_enabled_by_default || in_array((int) $category->term_id, (array) $enabled_categories, true);
                                    ?>
                                    <label style="display:block; margin-bottom:8px;">
                                        <input type="checkbox" name="wctags_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked($is_enabled); ?> />
                                        <?php echo esc_html($category->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e('Ha minden kategória be van jelölve, a szűrő minden termékkategóriában megjelenik.', 'woocommerce-category-tags'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(__('Mentés', 'woocommerce-category-tags'), 'primary', 'wctags_settings_submit'); ?>
        </form>
    </div>
    <?php
}
