<?php
/**
 * Navigation: menu walker, fallback menus and the mobile drawer source.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker that outputs the theme's BEM nav markup.
 */
class P10_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * Open a submenu list.
	 *
	 * @param string   $output Buffer.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Menu args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '<ul class="sub-menu">';
	}

	/**
	 * Render one menu item.
	 *
	 * @param string   $output Buffer.
	 * @param WP_Post  $item   Menu item.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Menu args.
	 * @param int      $id     Item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes   = array( 'p10-nav__item' );
		$classes[] = 'menu-item-' . $item->ID;

		if ( in_array( 'current-menu-item', (array) $item->classes, true )
			|| in_array( 'current-menu-ancestor', (array) $item->classes, true )
			|| in_array( 'current_page_item', (array) $item->classes, true ) ) {
			$classes[] = 'current-menu-item';
		}
		if ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$classes[] = 'p10-nav__item--parent';
		}

		$has_children = in_array( 'menu-item-has-children', (array) $item->classes, true );
		$link_class   = 'p10-nav__link';
		if ( in_array( 'p10-nav__cta', (array) $item->classes, true ) ) {
			$link_class .= ' p10-btn p10-btn--ghost p10-btn--sm';
		}

		$attributes  = ! empty( $item->attr_title ) ? ' title="' . esc_attr( $item->attr_title ) . '"' : '';
		$attributes .= ! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '"' : '';
		$attributes .= ! empty( $item->xfn ) ? ' rel="' . esc_attr( $item->xfn ) . '"' : '';
		$attributes .= ! empty( $item->url ) ? ' href="' . esc_url( $item->url ) . '"' : '';

		$title = apply_filters( 'the_title', $item->title, $item->ID );

		$output .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$output .= '<a class="' . esc_attr( $link_class ) . '"' . $attributes . '>';

		if ( 0 === $depth && ! empty( $item->description ) ) {
			$output .= '<span class="p10-nav__label">' . esc_html( $title ) . '</span>';
			$output .= '<span class="p10-nav__desc">' . esc_html( $item->description ) . '</span>';
		} else {
			$output .= esc_html( $title );
		}

		if ( 0 === $depth && $has_children ) {
			$output .= ' <span class="p10-nav__caret" aria-hidden="true">▾</span>';
		}

		$output .= '</a>';
	}
}

/**
 * Navigation used when no menu is assigned to the Primary location.
 *
 * Built from live directory data so the desktop top bar is ALWAYS complete,
 * even before Demo Import has run.
 */
function p10_default_nav() {
	$items = p10_default_nav_items();

	echo '<ul class="p10-nav__list">';
	foreach ( $items as $item ) {
		$children = ! empty( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();
		$li_class = 'p10-nav__item' . ( $children ? ' p10-nav__item--parent' : '' );
		echo '<li class="' . esc_attr( $li_class ) . '">';
		echo '<a class="p10-nav__link" href="' . esc_url( $item['url'] ) . '">';
		echo esc_html( $item['label'] );
		if ( $children ) {
			echo ' <span class="p10-nav__caret" aria-hidden="true">▾</span>';
		}
		echo '</a>';
		if ( $children ) {
			echo '<ul class="sub-menu">';
			foreach ( $children as $sub ) {
				printf(
					'<li class="p10-nav__item"><a class="p10-nav__link" href="%1$s">%2$s</a></li>',
					esc_url( $sub['url'] ),
					esc_html( $sub['label'] )
				);
			}
			echo '</ul>';
		}
		echo '</li>';
	}
	echo '</ul>';
}

/**
 * The default navigation set (with structured dropdowns for Locations & Browse).
 *
 * @return array
 */
function p10_default_nav_items() {
	$location_children = array();
	foreach ( p10_get_counties() as $county ) {
		$location_children[] = array(
			'label' => $county->name,
			'url'   => p10_term_url( $county ),
		);
	}
	foreach ( array_slice( p10_get_featured_cities( 6 ), 0, 6 ) as $city ) {
		$location_children[] = array(
			'label' => $city->name,
			'url'   => p10_term_url( $city ),
		);
	}
	$location_children[] = array(
		'label' => __( 'All 211+ Cities', 'perfect10' ),
		'url'   => p10_utility_url( 'cities' ),
	);

	$browse_children = array(
		array(
			'label' => __( 'Premium Collection', 'perfect10' ),
			'url'   => p10_directory_url( array( 'premium' => 1 ) ),
		),
	);
	foreach ( array_slice( p10_get_categories(), 0, 6 ) as $category ) {
		$browse_children[] = array(
			'label' => $category->name,
			'url'   => get_term_link( $category ),
		);
	}

	$items = array(
		array(
			'label' => __( 'Home', 'perfect10' ),
			'url'   => home_url( '/' ),
		),
		array(
			'label' => __( 'Directory', 'perfect10' ),
			'url'   => p10_directory_url(),
		),
		array(
			'label'    => __( 'Locations', 'perfect10' ),
			'url'      => p10_utility_url( 'cities' ),
			'children' => $location_children,
		),
		array(
			'label'    => __( 'Browse', 'perfect10' ),
			'url'      => p10_directory_url( array( 'premium' => 1 ) ),
			'children' => $browse_children,
		),
		array(
			'label' => __( 'Packages', 'perfect10' ),
			'url'   => p10_utility_url( 'packages' ),
		),
		array(
			'label' => __( 'Reviews', 'perfect10' ),
			'url'   => p10_utility_url( 'reviews' ),
		),
		array(
			'label' => __( 'Journal', 'perfect10' ),
			'url'   => p10_journal_url(),
		),
		array(
			'label' => __( 'Advertise', 'perfect10' ),
			'url'   => p10_utility_url( 'register' ),
		),
	);

	return apply_filters( 'p10_default_nav_items', $items );
}

/**
 * Items for the mobile drawer. Prefers the assigned Primary menu.
 *
 * @return array
 */
function p10_drawer_items() {
	if ( has_nav_menu( 'primary' ) ) {
		$locations = get_nav_menu_locations();
		if ( ! empty( $locations['primary'] ) ) {
			$menu_items = wp_get_nav_menu_items( (int) $locations['primary'] );
			if ( $menu_items ) {
				$items = array();
				foreach ( $menu_items as $menu_item ) {
					// All submenu destinations stay reachable when a crowded header uses the drawer.
					$items[] = array(
						'label' => $menu_item->title,
						'url'   => $menu_item->url,
						'child' => ! empty( $menu_item->menu_item_parent ),
					);
				}
				if ( $items ) {
					return apply_filters( 'p10_drawer_items', $items );
				}
			}
		}
	}

	$flattened = array();
	foreach ( p10_default_nav_items() as $nav_item ) {
		$flattened[] = array(
			'label' => $nav_item['label'],
			'url'   => $nav_item['url'],
			'child' => false,
		);
		if ( ! empty( $nav_item['children'] ) && is_array( $nav_item['children'] ) ) {
			foreach ( $nav_item['children'] as $child ) {
				$flattened[] = array(
					'label' => $child['label'],
					'url'   => $child['url'],
					'child' => true,
				);
			}
		}
	}

	return apply_filters( 'p10_drawer_items', $flattened );
}

/**
 * Breadcrumb trail for archive and single views.
 *
 * @param array $args Optional overrides.
 */
function p10_breadcrumb( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'separator'  => '<span class="p10-breadcrumb__sep" aria-hidden="true">/</span>',
		'home_label' => __( 'Home', 'perfect10' ),
	) );

	$trail = array();
	$trail[] = array(
		'label' => $args['home_label'],
		'url'   => home_url( '/' ),
	);

	if ( is_singular( 'p10_profile' ) ) {
		$post   = get_queried_object();
		$county = p10_get_primary_term( $post->ID, 'p10_county' );
		$city   = p10_get_primary_term( $post->ID, 'p10_city' );
		$type   = p10_get_primary_term( $post->ID, 'p10_category' );

		if ( $county ) {
			$trail[] = array( 'label' => $county->name, 'url' => get_term_link( $county ) );
		}
		if ( $city ) {
			$trail[] = array( 'label' => $city->name, 'url' => get_term_link( $city ) );
		}
		if ( $type && ! $city ) {
			$trail[] = array( 'label' => $type->name, 'url' => get_term_link( $type ) );
		}
		$trail[] = array( 'label' => get_the_title( $post ), 'url' => '' );

	} elseif ( is_tax( 'p10_city' ) ) {
		$term        = get_queried_object();
		$county_slug = p10_city_county_slug( $term );
		$county      = $county_slug ? get_term_by( 'slug', $county_slug, 'p10_county' ) : null;

		$trail[] = array( 'label' => __( 'Directory', 'perfect10' ), 'url' => p10_directory_url() );
		if ( $county ) {
			$trail[] = array( 'label' => $county->name, 'url' => get_term_link( $county ) );
		}
		$trail[] = array( 'label' => $term->name, 'url' => '' );

	} elseif ( is_tax() ) {
		$term    = get_queried_object();
		$trail[] = array( 'label' => __( 'Directory', 'perfect10' ), 'url' => p10_directory_url() );
		$trail[] = array( 'label' => $term->name, 'url' => '' );

	} elseif ( is_post_type_archive( 'p10_profile' ) ) {
		$trail[] = array( 'label' => post_type_archive_title( '', false ), 'url' => '' );

	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post->post_parent ) {
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor ) {
				$trail[] = array( 'label' => get_the_title( $ancestor ), 'url' => get_permalink( $ancestor ) );
			}
		}
		$trail[] = array( 'label' => get_the_title( $post ), 'url' => '' );

	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		$trail[] = array( 'label' => sprintf( __( 'Search: %s', 'perfect10' ), get_search_query() ), 'url' => '' );

	} elseif ( is_404() ) {
		$trail[] = array( 'label' => __( 'Not found', 'perfect10' ), 'url' => '' );
	}

	$trail = apply_filters( 'p10_breadcrumb_trail', $trail );

	if ( count( $trail ) < 2 ) {
		return;
	}

	echo '<nav class="p10-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'perfect10' ) . '">';
	$last = count( $trail ) - 1;
	foreach ( array_values( $trail ) as $index => $crumb ) {
		if ( $index === $last || empty( $crumb['url'] ) ) {
			echo '<span aria-current="page">' . esc_html( $crumb['label'] ) . '</span>';
		} else {
			echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a>';
		}
		if ( $index !== $last ) {
			echo $args['separator']; // phpcs:ignore WordPress.Security.EscapeOutput -- markup defined above.
		}
	}
	echo '</nav>';
}
