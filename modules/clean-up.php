<?php
/**
 * Clean up wp_head()
 *
 * Remove unnecessary <link>'s
 * Remove inline CSS used by Recent Comments widget
 * Remove inline CSS used by posts with galleries
 * Remove self-closing tag and change ''s to "'s on rel_canonical()
 * 
 * You can enable/disable this feature in functions.php (or lib/config.php if you're using Roots):
 * add_theme_support('soil-clean-up');
 */
function soil_head_cleanup() {
  // Originally from http://wpengineer.com/1438/wordpress-header/
  remove_action('wp_head', 'feed_links', 2);
  remove_action('wp_head', 'feed_links_extra', 3);
  remove_action('wp_head', 'rsd_link');
  remove_action('wp_head', 'wlwmanifest_link');
  remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
  remove_action('wp_head', 'wp_generator');
  remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);

  global $wp_widget_factory;

  if(isset($wp_widget_factory->widgets['WP_Widget_Recent_Comments'])) {
    remove_action('wp_head', array($wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style'));
  }

  if (!class_exists('WPSEO_Frontend')) {
    remove_action('wp_head', 'rel_canonical');
    add_action('wp_head', 'soil_rel_canonical');
  }
}

function soil_rel_canonical() {
  global $wp_the_query;

  if (!is_singular()) {
    return;
  }

  if (!$id = $wp_the_query->get_queried_object_id()) {
    return;
  }

  $link = get_permalink($id);
  echo "\t<link rel=\"canonical\" href=\"$link\">\n";
}
add_action('init', 'soil_head_cleanup');

/**
 * Remove the WordPress version from RSS feeds
 */
add_filter('the_generator', '__return_false');

/**
 * Clean up language_attributes() used in <html> tag
 *
 * Remove dir="ltr"
 */
function soil_language_attributes() {
  $attributes = array();
  $output = '';

  if (is_rtl()) {
    $attributes[] = 'dir="rtl"';
  }

  $lang = get_bloginfo('language');

  if ($lang) {
    $attributes[] = "lang=\"$lang\"";
  }

  $output = implode(' ', $attributes);
  $output = apply_filters('soil/language_attributes', $output);

  return $output;
}
add_filter('language_attributes', 'soil_language_attributes');

/**
 * Clean up output of stylesheet <link> tags
 */
function soil_clean_style_tag($input) {
  preg_match_all("!<link rel='stylesheet'\s?(id='[^']+')?\s+href='(.*)' type='text/css' media='(.*)' />!", $input, $matches);
  // Only display media if it is meaningful
  $media = $matches[3][0] !== '' && $matches[3][0] !== 'all' ? ' media="' . $matches[3][0] . '"' : '';
  return '<link rel="stylesheet" href="' . $matches[2][0] . '"' . $media . '>' . "\n";
}
add_filter('style_loader_tag', 'soil_clean_style_tag');

/**
 * Add and remove body_class() classes
 */
function soil_body_class($classes) {
  // Add post/page slug
  if (is_single() || is_page() && !is_front_page()) {
    $classes[] = basename(get_permalink());
  }

  // Remove unnecessary classes
  $home_id_class = 'page-id-' . get_option('page_on_front');
  $remove_classes = array(
    'page-template-default',
    $home_id_class
  );
  $classes = array_diff($classes, $remove_classes);

  return $classes;
}
add_filter('body_class', 'soil_body_class');

/**
 * Wrap embedded media using Bootstrap responsive embeds
 *
 * @link http://getbootstrap.com/components/#responsive-embed
 */
function soil_embed_wrap($cache, $url, $attr = '', $post_ID = '') {
  $cache = str_replace('<iframe', '<iframe class="embed-responsive-item"', $cache);
  return '<div class="embed-responsive embed-responsive-16by9">' . $cache . '</div>';
}
add_filter('embed_oembed_html', 'soil_embed_wrap', 10, 4);

/**
 * Use <figure> and <figcaption> for captions
 *
 * @link http://justintadlock.com/archives/2011/07/01/captions-in-wordpress
 */
function soil_caption($output, $attr, $content) {
  if (is_feed()) {
    return $output;
  }

  $defaults = array(
    'id'      => '',
    'align'   => 'alignnone',
    'width'   => '',
    'caption' => ''
  );

  $attr = shortcode_atts($defaults, $attr);

  // If the width is less than 1 or there is no caption, return the content wrapped between the [caption] tags
  if ($attr['width'] < 1 || empty($attr['caption'])) {
    return $content;
  }

  // Set up the attributes for the caption <figure>
  $attributes  = (!empty($attr['id']) ? ' id="' . esc_attr($attr['id']) . '"' : '' );
  $attributes .= ' class="wp-caption ' . esc_attr($attr['align']) . '"';
  $attributes .= ' style="width: ' . (esc_attr($attr['width']) + 10) . 'px"';

  $output  = '<figure' . $attributes .'>';
  $output .= do_shortcode($content);
  $output .= '<figcaption class="caption wp-caption-text">' . $attr['caption'] . '</figcaption>';
  $output .= '</figure>';

  return $output;
}
add_filter('img_caption_shortcode', 'soil_caption', 10, 3);

/**
 * Remove unnecessary dashboard widgets
 *
 * @link http://www.deluxeblogtips.com/2011/01/remove-dashboard-widgets-in-wordpress.html
 */
function soil_remove_dashboard_widgets() {
  remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
  remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
  remove_meta_box('dashboard_primary', 'dashboard', 'normal');
  remove_meta_box('dashboard_secondary', 'dashboard', 'normal');
}
add_action('admin_init', 'soil_remove_dashboard_widgets');

/**
 * Remove unnecessary self-closing tags
 */
function soil_remove_self_closing_tags($input) {
  return str_replace(' />', '>', $input);
}
add_filter('get_avatar',          'soil_remove_self_closing_tags'); // <img />
add_filter('comment_id_fields',   'soil_remove_self_closing_tags'); // <input />
add_filter('post_thumbnail_html', 'soil_remove_self_closing_tags'); // <img />

/**
 * Don't return the default description in the RSS feed if it hasn't been changed
 */
function soil_remove_default_description($bloginfo) {
  $default_tagline = 'Just another WordPress site';
  return ($bloginfo === $default_tagline) ? '' : $bloginfo;
}
add_filter('get_bloginfo_rss', 'soil_remove_default_description');

/**
 * Fix for empty search queries redirecting to home page
 *
 * @link http://wordpress.org/support/topic/blank-search-sends-you-to-the-homepage#post-1772565
 * @link http://core.trac.wordpress.org/ticket/11330
 */
function soil_request_filter($query_vars) {
  if (isset($_GET['s']) && empty($_GET['s']) && !is_admin()) {
    $query_vars['s'] = ' ';
  }

  return $query_vars;
}
add_filter('request', 'soil_request_filter');

/**
 * Set low priorty for WordPress SEO metabox
 */
add_filter( 'wpseo_metabox_prio', function() {
  return 'low';
});

/**
 * Remove WordPress SEO columns
 */
add_filter( 'wpseo_use_page_analysis', '__return_false' );

/**
 * Remove version query string from all styles and scripts
 */
function soil_remove_script_version( $src ){
  return remove_query_arg( 'ver', $src );
}
add_filter( 'script_loader_src', 'soil_remove_script_version', 15, 1 );
add_filter( 'style_loader_src', 'soil_remove_script_version', 15, 1 );

/**
 * Load gravity forms in the footer
 */
add_filter( 'gform_init_scripts_footer', '__return_true' );

/**
 * Disable gravity forms css
 */
function soil_remove_gravityforms_style() {
  global $wp_styles;
  if ( isset($wp_styles->registered['gforms_reset_css']) ) {
    unset( $wp_styles->registered['gforms_reset_css'] );
  }
  if ( isset($wp_styles->registered['gforms_formsmain_css']) ) {
    unset( $wp_styles->registered['gforms_formsmain_css'] );
  }
  if ( isset($wp_styles->registered['gforms_ready_class_css']) ) {
    unset( $wp_styles->registered['gforms_ready_class_css'] );
  }
  if ( isset($wp_styles->registered['gforms_browsers_css']) ) {
    unset( $wp_styles->registered['gforms_browsers_css'] );
  }
  if ( isset($wp_styles->registered['gforms_datepicker_css']) ) {
    unset( $wp_styles->registered['gforms_datepicker_css'] );
  }
}
add_action( 'gform_enqueue_scripts', 'soil_remove_gravityforms_style' );

/**
 * Disable tablepress css
 */
add_filter( 'tablepress_use_default_css', '__return_false' );