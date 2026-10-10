<?php
/**
 * Bamero Theme — SEO / structured data
 *
 * Schema.org entity graph (Organization, LocalBusiness, WebSite,
 * Product, BreadcrumbList), meta robots for thin pages and the dynamic
 * robots.txt.
 *
 * @package Bamero
 */

defined('ABSPATH') || exit;

// =============================================================================
// SEO + GEO (2026 production) – entity, local, AI-overview friendly JSON-LD
// =============================================================================

/**
 * Organization + LocalBusiness + WebSite entity graph (single @graph).
 * Optimized for Google AI Overviews / entity resolution (H2 2025–2026).
 */
function bamero_output_entity_graph() {
    if (is_admin()) {
        return;
    }
    // SEO-03: Rank Math owns structured data when active
    if (defined('RANK_MATH_VERSION')) {
        return;
    }

    $site_name = get_bloginfo('name') ?: 'بامرو';
    $site_url  = home_url('/');
    $logo      = BAMERO_THEME_DIR . '/images/logo.svg';
    $phone     = bamero_phone_e164();
    $address   = bamero_address_display();

    $graph = array(
        '@context' => 'https://schema.org',
        '@graph'   => array(
            array(
                '@type' => 'Organization',
                '@id'   => $site_url . '#organization',
                'name'  => $site_name,
                'url'   => $site_url,
                'logo'  => array(
                    '@type'  => 'ImageObject',
                    'url'    => $logo,
                    'width'  => 512,
                    'height' => 512,
                ),
                'sameAs' => array_filter(array(
                    get_theme_mod('bamero_instagram_url', ''),
                    get_theme_mod('bamero_telegram_url', ''),
                )),
                'contactPoint' => array(
                    '@type'       => 'ContactPoint',
                    'telephone'   => $phone,
                    'contactType' => 'customer service',
                    'areaServed'  => 'IR',
                    'availableLanguage' => array('fa', 'Persian'),
                ),
            ),
            array(
                '@type' => 'LocalBusiness',
                '@id'   => $site_url . '#localbusiness',
                'name'  => $site_name,
                'image' => $logo,
                'url'   => $site_url,
                'telephone' => $phone,
                'priceRange' => '$$',
                'address' => array(
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $address,
                    'addressLocality' => 'اصفهان',
                    'addressRegion'   => 'اصفهان',
                    'addressCountry'  => 'IR',
                ),
                // GEO: approximate central Isfahan — replace with exact coordinates for the store.
                'geo' => array(
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => 32.666756,
                    'longitude' => 51.644733,
                ),
                'areaServed' => array(
                    array('@type' => 'City', 'name' => 'اصفهان'),
                    array('@type' => 'Country', 'name' => 'ایران'),
                ),
                'openingHoursSpecification' => array(
                    array(
                        '@type'     => 'OpeningHoursSpecification',
                        'dayOfWeek' => array('Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday'),
                        'opens'     => '08:00',
                        'closes'    => '17:00',
                    ),
                    array(
                        '@type'     => 'OpeningHoursSpecification',
                        'dayOfWeek' => 'Thursday',
                        'opens'     => '08:00',
                        'closes'    => '14:00',
                    ),
                ),
                'parentOrganization' => array('@id' => $site_url . '#organization'),
            ),
            array(
                '@type' => 'WebSite',
                '@id'   => $site_url . '#website',
                'url'   => $site_url,
                'name'  => $site_name,
                'inLanguage' => 'fa-IR',
                'publisher'  => array('@id' => $site_url . '#organization'),
                'potentialAction' => array(
                    '@type'       => 'SearchAction',
                    'target'      => array(
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => $site_url . '?s={search_term_string}&post_type=product',
                    ),
                    'query-input' => 'required name=search_term_string',
                ),
            ),
        ),
    );

    // Product entity on single product (E-E-A-T + rich results)
    if (function_exists('is_product') && is_product()) {
        global $product;
        if ($product instanceof WC_Product) {
            $graph['@graph'][] = array(
                '@type' => 'Product',
                '@id'   => get_permalink() . '#product',
                'name'  => $product->get_name(),
                'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
                'sku'   => $product->get_sku(),
                'brand' => array(
                    '@type' => 'Brand',
                    'name'  => 'بامرو',
                ),
                'offers' => array(
                    '@type'         => 'Offer',
                    'url'           => get_permalink(),
                    'priceCurrency' => get_woocommerce_currency(),
                    'price'         => $product->get_price(),
                    'availability'  => $product->is_in_stock()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'seller'        => array('@id' => $site_url . '#organization'),
                ),
            );
        }
    }

    // BreadcrumbList (helps AI Overviews + sitelinks)
    if (!is_front_page()) {
        $crumbs = array(
            array(
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'خانه',
                'item'     => $site_url,
            ),
        );
        $pos = 2;
        if (function_exists('is_shop') && is_shop()) {
            $crumbs[] = array(
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'فروشگاه',
                'item'     => get_permalink(wc_get_page_id('shop')),
            );
        } elseif (is_singular()) {
            $crumbs[] = array(
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => get_the_title(),
                'item'     => get_permalink(),
            );
        }
        $graph['@graph'][] = array(
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        );
    }

    echo '<script type="application/ld+json" nonce="' . esc_attr( function_exists( 'bamero_csp_nonce' ) ? bamero_csp_nonce() : '' ) . '">' . wp_json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'bamero_output_entity_graph', 5);

/** Meta robots + canonical helpers for thin/archive pages */
function bamero_meta_robots() {
    if (is_search() || is_404()) {
        echo '<meta name="robots" content="noindex, follow" />' . "\n";
    }
}
add_action('wp_head', 'bamero_meta_robots', 1);

// =============================================================================
// DYNAMIC robots.txt (domain-agnostic; no hard-coded production host)
// =============================================================================

/**
 * Serve robots.txt dynamically so the Sitemap URL always matches the real
 * domain the owner configures under Settings > General. A static file would
 * hard-code a host that does not exist yet.
 */
function bamero_robots_txt($output, $public) {
    if (!$public) {
        return $output;
    }
    if (stripos($output, 'Sitemap:') === false) {
        $output = rtrim($output) . "\n\nSitemap: " . home_url('/wp-sitemap.xml') . "\n";
    }
    return $output;
}
add_filter('robots_txt', 'bamero_robots_txt', 99, 2);
