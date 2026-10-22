<?php
/**
 * Self-contained one-click demo content factory.
 *
 * Imports counties, 211 cities & districts, 7 profile types, 10 nationalities,
 * 10 rich 300–400 word pages, 12 multi-gallery companion profiles across LA &
 * Orange County, 6 editorial blog articles, 6 guest reviews, 3 packages,
 * bundled luxury photography into the WordPress Media Library, and assigns the
 * desktop top navigation, footer, and legal menus under ANY server conditions
 * (no external internet requests required).
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import a bundled image from assets/images/demo/ into the WordPress Media
 * Library with resilient metadata generation (works even without GD/Imagick).
 *
 * Idempotent: returns the existing attachment ID when already imported.
 *
 * @param string $filename File name inside assets/images/demo/.
 * @param string $title    Human-readable attachment title.
 * @return int Attachment ID or 0.
 */
function p10_import_bundled_image( $filename, $title = '' ) {
	$filename = sanitize_file_name( basename( (string) $filename ) );
	$source   = P10_DIR . 'assets/images/demo/' . $filename;

	if ( ! $filename || ! is_readable( $source ) ) {
		return 0;
	}

	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_p10_bundled_file', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => $filename, // phpcs:ignore WordPress.DB.SlowDBQuery
		'no_found_rows'  => true,
	) );

	if ( ! empty( $existing[0] ) && p10_attachment_has_file( (int) $existing[0] ) ) {
		return (int) $existing[0];
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['path'] ) ) {
		return 0;
	}

	wp_mkdir_p( $uploads['path'] );

	$dest_name = wp_unique_filename( $uploads['path'], 'p10-' . $filename );
	$dest_path = trailingslashit( $uploads['path'] ) . $dest_name;

	if ( ! @copy( $source, $dest_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$contents = file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $contents ) {
			return 0;
		}
		$bits = wp_upload_bits( 'p10-' . $filename, null, $contents );
		if ( ! empty( $bits['error'] ) || empty( $bits['file'] ) ) {
			return 0;
		}
		$dest_path = $bits['file'];
		$dest_name = basename( $dest_path );
	}

	$filetype = wp_check_filetype( $dest_name, null );
	$mime     = ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/jpeg';
	$label    = $title ? $title : ucwords( str_replace( array( '-', '.jpg', '.jpeg', '.png' ), ' ', $filename ) );

	$attachment_id = wp_insert_attachment(
		array(
			'guid'           => trailingslashit( $uploads['url'] ) . $dest_name,
			'post_mime_type' => $mime,
			'post_title'     => sanitize_text_field( $label ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$dest_path,
		0,
		true
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	$attachment_id = (int) $attachment_id;
	$meta          = wp_generate_attachment_metadata( $attachment_id, $dest_path );

	// Resilient fallback when minimal PHP environments lack GD/Imagick.
	if ( empty( $meta ) || empty( $meta['width'] ) ) {
		$size          = @getimagesize( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$rel           = _wp_relative_upload_path( $dest_path );
		$meta          = is_array( $meta ) ? $meta : array();
		$meta['file']  = $rel ? $rel : $dest_name;
		$meta['width'] = ! empty( $size[0] ) ? (int) $size[0] : 1200;
		$meta['height'] = ! empty( $size[1] ) ? (int) $size[1] : 900;
		if ( empty( $meta['sizes'] ) ) {
			$meta['sizes'] = array();
		}
	}

	wp_update_attachment_metadata( $attachment_id, $meta );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $label ) );
	update_post_meta( $attachment_id, '_p10_bundled_file', $filename );

	if ( function_exists( 'p10_demo_mark_post' ) ) {
		p10_demo_mark_post( $attachment_id, 'attachment' );
	}

	return $attachment_id;
}

/**
 * Legacy remote-image helper retained for backward compatibility; automatically
 * resolves to bundled high-resolution photography when remote fetching is off
 * or fails.
 *
 * @param string $url  Remote URL or bundled filename.
 * @param string $name Attachment title.
 * @return int Attachment ID or 0.
 */
function p10_import_fetch_image( $url, $name = '' ) {
	if ( $url && false === strpos( $url, '://' ) ) {
		return p10_import_bundled_image( $url, $name );
	}

	if ( apply_filters( 'p10_demo_fetch_remote', false ) && $url ) {
		$cache_key = 'p10_img_' . md5( $url );
		$cached    = (int) get_transient( $cache_key );
		if ( $cached && p10_attachment_has_file( $cached ) ) {
			return $cached;
		}
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attachment_id = media_sideload_image( $url, 0, $name ? $name : null, 'id' );
		if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
			set_transient( $cache_key, (int) $attachment_id, MONTH_IN_SECONDS );
			return (int) $attachment_id;
		}
	}

	return p10_import_bundled_image( 'portrait-01.jpg', $name );
}

/**
 * Run the complete importer synchronously.
 *
 * @param string $scope 'all' or 'locations'.
 * @return array Log lines.
 */
function p10_run_demo_import( $scope = 'all' ) {
	$log = array();

	if ( ! current_user_can( 'manage_options' ) ) {
		return array( __( 'Permission denied.', 'perfect10' ) );
	}

	p10_raise_time_limit();

	$log[] = __( 'Starting Perfect 10 complete demo import…', 'perfect10' );

	$counties = p10_import_counties( $log );
	p10_import_cities( $counties, $log );
	$types    = p10_import_categories( $log );
	p10_import_nationalities( $log );

	if ( 'locations' === $scope ) {
		p10_add_nested_rules();
		flush_rewrite_rules();
		$log[] = __( 'Locations & 300–400 word destination guides imported.', 'perfect10' );
		return $log;
	}

	p10_import_imagery( $log );
	$pages = p10_import_pages( $log );
	p10_import_packages( $log );
	p10_import_reviews( $log );
	p10_import_profiles( $counties, $types, $log );
	p10_import_blogs( $log );
	p10_import_home_settings( $pages, $log );
	p10_import_menus( $pages, $log );
	p10_clean_default_wp_clutter( $log );

	p10_add_nested_rules();
	flush_rewrite_rules();

	$log[] = __( 'Permalinks flushed and all menus assigned.', 'perfect10' );
	$log[] = __( 'Import complete — all content, images, galleries, blogs, and menus are live.', 'perfect10' );

	do_action( 'p10_demo_imported', $log );

	return $log;
}

/**
 * Ask PHP for more time when importing media and 211 locations.
 */
function p10_raise_time_limit() {
	if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
}

/**
 * Create or enrich the two counties with 300–400 word editorial guides and imagery.
 *
 * @param array $log Log lines, by reference.
 * @return array Slug => term_id.
 */
function p10_import_counties( &$log ) {
	$definitions = array(
		'los-angeles-county' => array(
			'name'    => __( 'Los Angeles County', 'perfect10' ),
			'tagline' => __( '88 cities & 89 neighbourhoods, from Malibu to Long Beach', 'perfect10' ),
			'blurb'   => __( 'Verified 18+ independent companions across Los Angeles County — Beverly Hills, Hollywood, West Hollywood, Santa Monica, Downtown LA, Malibu, and every neighbourhood in between.', 'perfect10' ),
			'image'   => 'city-beverly-hills.jpg',
			'sort'    => 1,
		),
		'orange-county'      => array(
			'name'    => __( 'Orange County', 'perfect10' ),
			'tagline' => __( '34 coastal & executive cities, from Seal Beach to San Clemente', 'perfect10' ),
			'blurb'   => __( 'Verified 18+ independent companions across Orange County — Newport Beach, Irvine, Laguna Beach, Costa Mesa, Huntington Beach, and Anaheim.', 'perfect10' ),
			'image'   => 'city-newport-beach.jpg',
			'sort'    => 2,
		),
	);

	$map = array();

	foreach ( $definitions as $slug => $definition ) {
		$rich_html  = p10_editorial_county_html( $slug, $definition['name'] );
		$plain_desc = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $rich_html ) ) );
		$existing   = get_term_by( 'slug', $slug, 'p10_county' );

		if ( $existing ) {
			$term_id = (int) $existing->term_id;
			if ( p10_word_count( $existing->description ) < 300 ) {
				wp_update_term( $term_id, 'p10_county', array( 'description' => $plain_desc ) );
			}
		} else {
			$result = wp_insert_term(
				$definition['name'],
				'p10_county',
				array(
					'slug'        => $slug,
					'description' => $plain_desc,
				)
			);
			if ( is_wp_error( $result ) ) {
				$log[] = sprintf( __( 'County failed: %s', 'perfect10' ), $result->get_error_message() );
				continue;
			}
			$term_id = (int) $result['term_id'];
			if ( function_exists( 'p10_demo_mark_term' ) ) {
				p10_demo_mark_term( $term_id );
			}
		}

		$map[ $slug ] = $term_id;

		if ( ! get_term_meta( $term_id, 'p10_tagline', true ) ) {
			update_term_meta( $term_id, 'p10_tagline', $definition['tagline'] );
		}
		if ( ! get_term_meta( $term_id, 'p10_blurb', true ) ) {
			update_term_meta( $term_id, 'p10_blurb', $definition['blurb'] );
		}
		if ( p10_word_count( (string) get_term_meta( $term_id, 'p10_editorial_html', true ) ) < 300 ) {
			update_term_meta( $term_id, 'p10_editorial_html', $rich_html );
		}
		update_term_meta( $term_id, 'p10_sort', $definition['sort'] );

		$cur_img = (int) get_term_meta( $term_id, 'p10_image', true );
		if ( ! $cur_img || ! p10_attachment_has_file( $cur_img ) ) {
			$img_id = p10_import_bundled_image( $definition['image'], $definition['name'] );
			if ( $img_id ) {
				update_term_meta( $term_id, 'p10_image', $img_id );
			}
		}
	}

	$log[] = sprintf( __( 'Counties ready with 300–400 word editorial guides: %d', 'perfect10' ), count( $map ) );

	return $map;
}

/**
 * Create or enrich every city and district from inc/locations.php with
 * 300–400 word destination guides and cover photography.
 *
 * @param array $counties Slug => term_id map.
 * @param array $log      Log lines, by reference.
 * @return string Log line.
 */
function p10_import_cities( $counties, &$log ) {
	$locations = require P10_DIR . 'inc/locations.php';

	$created  = 0;
	$enriched = 0;

	$featured = array(
		'beverly-hills'        => array( 'sort' => 1, 'img' => 'city-beverly-hills.jpg' ),
		'west-hollywood'       => array( 'sort' => 2, 'img' => 'city-hollywood.jpg' ),
		'hollywood'            => array( 'sort' => 3, 'img' => 'city-hollywood.jpg' ),
		'santa-monica'         => array( 'sort' => 4, 'img' => 'city-santa-monica.jpg' ),
		'downtown-los-angeles' => array( 'sort' => 5, 'img' => 'city-downtown-la.jpg' ),
		'malibu'               => array( 'sort' => 6, 'img' => 'city-santa-monica.jpg' ),
		'newport-beach'        => array( 'sort' => 7, 'img' => 'city-newport-beach.jpg' ),
		'irvine'               => array( 'sort' => 8, 'img' => 'city-newport-beach.jpg' ),
		'laguna-beach'         => array( 'sort' => 9, 'img' => 'city-santa-monica.jpg' ),
		'los-angeles'          => array( 'sort' => 10, 'img' => 'hero-la-nights.jpg' ),
		'anaheim'              => array( 'sort' => 11, 'img' => 'city-downtown-la.jpg' ),
		'pasadena'             => array( 'sort' => 12, 'img' => 'city-beverly-hills.jpg' ),
	);

	foreach ( $locations as $county_slug => $group ) {
		if ( empty( $counties[ $county_slug ] ) ) {
			continue;
		}

		foreach ( array( 'cities', 'districts' ) as $kind ) {
			if ( empty( $group[ $kind ] ) ) {
				continue;
			}

			$kind_key = 'districts' === $kind ? 'district' : 'city';
			$sort     = 0;

			foreach ( $group[ $kind ] as $name ) {
				$sort++;
				$slug       = sanitize_title( $name );
				$rich_html  = p10_editorial_city_html( $slug, $name, $county_slug, $kind_key );
				$plain_desc = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $rich_html ) ) );
				$existing   = get_term_by( 'slug', $slug, 'p10_city' );

				if ( $existing ) {
					$term_id = (int) $existing->term_id;
					if ( p10_word_count( $existing->description ) < 300 ) {
						wp_update_term( $term_id, 'p10_city', array( 'description' => $plain_desc ) );
						$enriched++;
					}
				} else {
					$result = wp_insert_term(
						$name,
						'p10_city',
						array(
							'slug'        => $slug,
							'parent'      => 0,
							'description' => $plain_desc,
						)
					);
					if ( is_wp_error( $result ) ) {
						continue;
					}
					$term_id = (int) $result['term_id'];
					if ( function_exists( 'p10_demo_mark_term' ) ) {
						p10_demo_mark_term( $term_id );
					}
					$created++;
				}

				if ( ! get_term_meta( $term_id, 'p10_county', true ) ) {
					update_term_meta( $term_id, 'p10_county', $county_slug );
				}
				if ( ! get_term_meta( $term_id, 'p10_kind', true ) ) {
					update_term_meta( $term_id, 'p10_kind', $kind_key );
				}
				if ( p10_word_count( (string) get_term_meta( $term_id, 'p10_editorial_html', true ) ) < 300 ) {
					update_term_meta( $term_id, 'p10_editorial_html', $rich_html );
				}
				if ( ! get_term_meta( $term_id, 'p10_sort', true ) ) {
					update_term_meta( $term_id, 'p10_sort', $sort );
				}

				if ( isset( $featured[ $slug ] ) ) {
					update_term_meta( $term_id, 'p10_featured', 1 );
					update_term_meta( $term_id, 'p10_sort', $featured[ $slug ]['sort'] );
					$cur_img = (int) get_term_meta( $term_id, 'p10_image', true );
					if ( ! $cur_img || ! p10_attachment_has_file( $cur_img ) ) {
						$img_id = p10_import_bundled_image( $featured[ $slug ]['img'], $name );
						if ( $img_id ) {
							update_term_meta( $term_id, 'p10_image', $img_id );
						}
					}
				}
			}
		}
	}

	$line = sprintf(
		/* translators: 1: cities created, 2: cities enriched. */
		__( 'Cities & districts: %1$d created, %2$d updated with 300–400 word editorial guides.', 'perfect10' ),
		$created,
		$enriched
	);
	$log[] = $line;

	return $line;
}

/**
 * Create or enrich all 7 profile types with 300–400 word guides and imagery.
 *
 * @param array $log Log lines, by reference.
 * @return array Slug => term_id.
 */
function p10_import_categories( &$log ) {
	$definitions = array(
		'ultimate-la-vip-escorts' => array(
			'name'   => __( 'Ultimate LA VIP Escorts', 'perfect10' ),
			'short'  => __( 'VIP Collection', 'perfect10' ),
			'number' => '01',
			'sort'   => 1,
			'image'  => 'portrait-01.jpg',
			'blurb'  => __( 'Runway-calibre VIP muses curated for five-star hotels, red-carpet evenings, fine dining, and extended retreats.', 'perfect10' ),
		),
		'independent-escorts'     => array(
			'name'   => __( 'Independent Escorts', 'perfect10' ),
			'short'  => __( 'Independent', 'perfect10' ),
			'number' => '02',
			'sort'   => 2,
			'image'  => 'portrait-02.jpg',
			'blurb'  => __( 'Self-represented companions who manage their own availability, boundaries, and private introductions.', 'perfect10' ),
		),
		'trans-escorts'           => array(
			'name'   => __( 'Trans Escorts', 'perfect10' ),
			'short'  => __( 'Trans', 'perfect10' ),
			'number' => '03',
			'sort'   => 3,
			'image'  => 'portrait-04.jpg',
			'blurb'  => __( 'Verified glamorous trans companions across Los Angeles County and Orange County.', 'perfect10' ),
		),
		'couples'                 => array(
			'name'   => __( 'Couples', 'perfect10' ),
			'short'  => __( 'Couples', 'perfect10' ),
			'number' => '04',
			'sort'   => 4,
			'image'  => 'portrait-06.jpg',
			'blurb'  => __( 'Poised, communicative companions welcoming married and partnered couples with warmth and discretion.', 'perfect10' ),
		),
		'escort-agencies'         => array(
			'name'   => __( 'Escort Agencies', 'perfect10' ),
			'short'  => __( 'Agencies', 'perfect10' ),
			'number' => '05',
			'sort'   => 5,
			'image'  => 'portrait-03.jpg',
			'blurb'  => __( 'Established boutique rosters offering coordinated scheduling across Greater Southern California.', 'perfect10' ),
		),
		'erotic-massage'          => array(
			'name'   => __( 'Erotic Massage', 'perfect10' ),
			'short'  => __( 'Massage', 'perfect10' ),
			'number' => '06',
			'sort'   => 6,
			'image'  => 'portrait-05.jpg',
			'blurb'  => __( 'Independent practitioners offering tranquil, unhurried sensual bodywork in serene suites or hotel outcalls.', 'perfect10' ),
		),
		'escort-tours'            => array(
			'name'   => __( 'Escort Tours', 'perfect10' ),
			'short'  => __( 'Tours', 'perfect10' ),
			'number' => '07',
			'sort'   => 7,
			'image'  => 'portrait-08.jpg',
			'blurb'  => __( 'Visiting international and domestic companions on limited-date Southern California engagements.', 'perfect10' ),
		),
	);

	$map = array();

	foreach ( $definitions as $slug => $definition ) {
		$rich_html  = p10_editorial_category_html( $slug, $definition['name'] );
		$plain_desc = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $rich_html ) ) );
		$existing   = get_term_by( 'slug', $slug, 'p10_category' );

		if ( $existing ) {
			$term_id = (int) $existing->term_id;
			if ( p10_word_count( $existing->description ) < 300 ) {
				wp_update_term( $term_id, 'p10_category', array( 'description' => $plain_desc ) );
			}
		} else {
			$result = wp_insert_term(
				$definition['name'],
				'p10_category',
				array(
					'slug'        => $slug,
					'description' => $plain_desc,
				)
			);
			if ( is_wp_error( $result ) ) {
				continue;
			}
			$term_id = (int) $result['term_id'];
			if ( function_exists( 'p10_demo_mark_term' ) ) {
				p10_demo_mark_term( $term_id );
			}
		}

		update_term_meta( $term_id, 'p10_number', $definition['number'] );
		update_term_meta( $term_id, 'p10_short', $definition['short'] );
		if ( ! get_term_meta( $term_id, 'p10_blurb', true ) ) {
			update_term_meta( $term_id, 'p10_blurb', $definition['blurb'] );
		}
		if ( p10_word_count( (string) get_term_meta( $term_id, 'p10_editorial_html', true ) ) < 300 ) {
			update_term_meta( $term_id, 'p10_editorial_html', $rich_html );
		}
		update_term_meta( $term_id, 'p10_sort', $definition['sort'] );

		$cur_img = (int) get_term_meta( $term_id, 'p10_image', true );
		if ( ! $cur_img || ! p10_attachment_has_file( $cur_img ) ) {
			$img_id = p10_import_bundled_image( $definition['image'], $definition['name'] );
			if ( $img_id ) {
				update_term_meta( $term_id, 'p10_image', $img_id );
			}
		}

		$map[ $slug ] = $term_id;
	}

	// Also enrich any extra custom p10_category terms already in DB.
	$all_cats = get_terms( array( 'taxonomy' => 'p10_category', 'hide_empty' => false ) );
	if ( ! is_wp_error( $all_cats ) ) {
		foreach ( $all_cats as $cat ) {
			if ( p10_word_count( (string) get_term_meta( $cat->term_id, 'p10_editorial_html', true ) ) < 300 ) {
				$html = p10_editorial_category_html( $cat->slug, $cat->name );
				update_term_meta( $cat->term_id, 'p10_editorial_html', $html );
			}
		}
	}

	$log[] = sprintf( __( 'Profile types ready with images & 300–400 word guides: %d', 'perfect10' ), count( $map ) );

	return $map;
}

/**
 * Create or enrich nationalities with 300–400 word guides.
 *
 * @param array $log Log lines, by reference.
 */
function p10_import_nationalities( &$log ) {
	$names = array(
		'American'   => 1,
		'British'    => 2,
		'European'   => 3,
		'Latina'     => 4,
		'Asian'      => 5,
		'Australian' => 6,
		'Canadian'   => 7,
		'French'     => 8,
		'Italian'    => 9,
		'Spanish'    => 10,
	);

	$ready = 0;

	foreach ( $names as $name => $sort ) {
		$slug       = sanitize_title( $name );
		$rich_html  = p10_editorial_nationality_html( $slug, $name );
		$plain_desc = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $rich_html ) ) );
		$existing   = get_term_by( 'slug', $slug, 'p10_nationality' );

		if ( $existing ) {
			$term_id = (int) $existing->term_id;
			if ( p10_word_count( $existing->description ) < 300 ) {
				wp_update_term( $term_id, 'p10_nationality', array( 'description' => $plain_desc ) );
			}
		} else {
			$result = wp_insert_term(
				$name,
				'p10_nationality',
				array(
					'slug'        => $slug,
					'description' => $plain_desc,
				)
			);
			if ( is_wp_error( $result ) ) {
				continue;
			}
			$term_id = (int) $result['term_id'];
			if ( function_exists( 'p10_demo_mark_term' ) ) {
				p10_demo_mark_term( $term_id );
			}
		}

		update_term_meta( $term_id, 'p10_sort', $sort );
		if ( p10_word_count( (string) get_term_meta( $term_id, 'p10_editorial_html', true ) ) < 300 ) {
			update_term_meta( $term_id, 'p10_editorial_html', $rich_html );
		}
		$ready++;
	}

	$log[] = sprintf( __( 'Nationalities ready with 300–400 word guides: %d.', 'perfect10' ), $ready );
}

/**
 * Create or backfill all 10 core & utility pages with 300–400 words of rich
 * editorial copy and featured photography.
 *
 * @param array $log Log lines, by reference.
 * @return array Slug => page ID.
 */
function p10_import_pages( &$log ) {
	$pages = array(
		'home' => array(
			'title'    => __( 'Home', 'perfect10' ),
			'slug'     => 'home',
			'template' => '',
			'image'    => 'hero-la-nights.jpg',
			'excerpt'  => __( 'Southern California’s boutique 18+ referral directory of verified independent companions across Los Angeles County and Orange County.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'home' ),
		),
		'all-independent-escorts' => array(
			'title'    => __( 'All Independent Escorts', 'perfect10' ),
			'slug'     => 'all-independent-escorts',
			'template' => 'page-templates/directory.php',
			'image'    => 'city-beverly-hills.jpg',
			'excerpt'  => __( 'Every verified 18+ profile across Los Angeles and Orange County. Filter by county, city, profile type, Premium crown placement, rate, and availability.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'all-independent-escorts' ),
		),
		'booking' => array(
			'title'    => __( 'Booking', 'perfect10' ),
			'slug'     => 'booking',
			'template' => 'page-templates/booking.php',
			'image'    => 'hero-la-nights.jpg',
			'excerpt'  => __( 'Request a private referral across Los Angeles County or Orange County. A senior booking coordinator will contact you discreetly to confirm.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'booking' ),
		),
		'register-independent-profile' => array(
			'title'    => __( 'Create Independent Listing', 'perfect10' ),
			'slug'     => 'register-independent-profile',
			'template' => 'page-templates/register.php',
			'image'    => 'city-hollywood.jpg',
			'excerpt'  => __( 'Apply for verified independent placement across Los Angeles County and Orange County city archives.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'register-independent-profile' ),
		),
		'premium-packages' => array(
			'title'    => __( 'Premium Packages', 'perfect10' ),
			'slug'     => 'premium-packages',
			'template' => 'page-templates/packages.php',
			'image'    => 'city-beverly-hills.jpg',
			'excerpt'  => __( 'Royal Crown featured placement tiers for Southern California’s most sought-after independent profiles.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'premium-packages' ),
		),
		'all-cities' => array(
			'title'    => __( 'All Cities', 'perfect10' ),
			'slug'     => 'all-cities',
			'template' => 'page-templates/cities.php',
			'image'    => 'city-santa-monica.jpg',
			'excerpt'  => __( 'Complete A–Z index of all 211 Southern California cities and neighbourhoods across Los Angeles County and Orange County.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'all-cities' ),
		),
		'about' => array(
			'title'    => __( 'About Perfect 10', 'perfect10' ),
			'slug'     => 'about',
			'template' => '',
			'image'    => 'hero-la-nights.jpg',
			'excerpt'  => __( 'Established in 2010, Perfect 10 is Southern California’s trusted boutique referral network for verified independent companions.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'about' ),
		),
		'etiquette-faq' => array(
			'title'    => __( 'Etiquette & FAQ', 'perfect10' ),
			'slug'     => 'etiquette-faq',
			'template' => '',
			'image'    => 'city-downtown-la.jpg',
			'excerpt'  => __( 'Essential concierge guidelines, reservation timing, attire notes, and answers to common guest questions.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'etiquette-faq' ),
		),
		'privacy-policy' => array(
			'title'    => __( 'Privacy Policy', 'perfect10' ),
			'slug'     => 'privacy-policy',
			'template' => '',
			'image'    => 'city-newport-beach.jpg',
			'excerpt'  => __( 'How Perfect 10 safeguards client confidentiality, private enquiries, and 18+ compliance.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'privacy-policy' ),
		),
		'journal' => array(
			'title'    => __( 'Journal', 'perfect10' ),
			'slug'     => 'journal',
			'template' => '',
			'image'    => 'city-beverly-hills.jpg',
			'excerpt'  => __( 'Destination guides, five-star dining notes, coastal escapes, and boutique etiquette from the Perfect 10 editorial desk.', 'perfect10' ),
			'content'  => p10_editorial_page_html( 'about' ),
		),
	);

	$map = array();

	foreach ( $pages as $key => $definition ) {
		$existing = get_page_by_path( $definition['slug'] );

		if ( $existing ) {
			$page_id = (int) $existing->ID;
			$updates = array( 'ID' => $page_id );
			$dirty   = false;

			if ( 'publish' !== $existing->post_status ) {
				$updates['post_status'] = 'publish';
				$dirty                  = true;
			}
			if ( ! p10_is_built_with_elementor( $page_id ) && p10_word_count( $existing->post_content ) < 300 && ! empty( $definition['content'] ) ) {
				$updates['post_content'] = $definition['content'];
				$dirty                   = true;
			}
			if ( '' === trim( (string) $existing->post_excerpt ) && ! empty( $definition['excerpt'] ) ) {
				$updates['post_excerpt'] = $definition['excerpt'];
				$dirty                   = true;
			}
			if ( $dirty ) {
				wp_update_post( $updates );
			}
		} else {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $definition['title'],
					'post_name'    => $definition['slug'],
					'post_content' => isset( $definition['content'] ) ? $definition['content'] : '',
					'post_excerpt' => isset( $definition['excerpt'] ) ? $definition['excerpt'] : '',
				),
				true
			);

			if ( is_wp_error( $page_id ) ) {
				$log[] = sprintf( __( 'Page failed: %s', 'perfect10' ), $page_id->get_error_message() );
				continue;
			}
			$page_id = (int) $page_id;
			if ( function_exists( 'p10_demo_mark_post' ) ) {
				p10_demo_mark_post( $page_id, 'page' );
			}
		}

		if ( ! empty( $definition['template'] ) ) {
			update_post_meta( $page_id, '_wp_page_template', $definition['template'] );
		}

		$cur_thumb = (int) get_post_thumbnail_id( $page_id );
		if ( ! empty( $definition['image'] ) && ( ! $cur_thumb || ! p10_attachment_has_file( $cur_thumb ) ) ) {
			$img_id = p10_import_bundled_image( $definition['image'], $definition['title'] );
			if ( $img_id ) {
				set_post_thumbnail( $page_id, $img_id );
			}
		}

		$map[ $key ] = $page_id;
	}

	if ( ! empty( $map['all-independent-escorts'] ) ) {
		update_option( 'p10_directory_page', $map['all-independent-escorts'] );
		set_theme_mod( 'p10_directory_page', $map['all-independent-escorts'] );
	}
	if ( ! empty( $map['privacy-policy'] ) ) {
		update_option( 'wp_page_for_privacy_policy', (int) $map['privacy-policy'] );
	}

	$log[] = sprintf( __( 'Pages ready with 300–400 word content & imagery: %d', 'perfect10' ), count( $map ) );

	return $map;
}

/**
 * Apply static front page and posts (Journal) page settings.
 *
 * @param array $pages Slug => page ID.
 * @param array $log   Log lines, by reference.
 */
function p10_import_home_settings( $pages, &$log ) {
	if ( ! empty( $pages['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		if ( ! (int) get_option( 'page_on_front' ) ) {
			update_option( 'page_on_front', (int) $pages['home'] );
		}
		$log[] = __( 'Static front page configured.', 'perfect10' );
	}

	if ( ! empty( $pages['journal'] ) && ! (int) get_option( 'page_for_posts' ) ) {
		update_option( 'page_for_posts', (int) $pages['journal'] );
		$log[] = __( 'Posts page set to “Journal”.', 'perfect10' );
	}
}

/**
 * Create the premium packages.
 *
 * @param array $log Log lines, by reference.
 */
function p10_import_packages( &$log ) {
	$packages = array(
		array(
			'title'    => __( 'Essential Listing', 'perfect10' ),
			'slug'     => 'essential-listing',
			'price'    => 99,
			'period'   => __( 'per month', 'perfect10' ),
			'badge'    => '',
			'featured' => 0,
			'order'    => 1,
			'features' => implode( "\n", array(
				__( 'Standard directory placement', 'perfect10' ),
				__( 'Up to 6 high-resolution gallery photos', 'perfect10' ),
				__( 'One primary city placement', 'perfect10' ),
				__( 'Verified 18+ badge after ID check', 'perfect10' ),
				__( 'Private enquiry forwarding', 'perfect10' ),
			) ),
		),
		array(
			'title'    => __( 'Premium Placement', 'perfect10' ),
			'slug'     => 'premium-placement',
			'price'    => 249,
			'period'   => __( 'per month', 'perfect10' ),
			'badge'    => __( 'Most Popular', 'perfect10' ),
			'featured' => 1,
			'order'    => 2,
			'features' => implode( "\n", array(
				__( 'Everything in Essential', 'perfect10' ),
				__( 'Royal Crown “Premium” emblem site-wide', 'perfect10' ),
				__( 'The Premium Collection homepage showcase', 'perfect10' ),
				__( 'Up to 15 lightbox gallery photos plus video', 'perfect10' ),
				__( 'Three city placements across LA & OC', 'perfect10' ),
				__( 'Priority ranking in city and type archives', 'perfect10' ),
			) ),
		),
		array(
			'title'    => __( 'Featured VIP Muse', 'perfect10' ),
			'slug'     => 'featured-muse',
			'price'    => 499,
			'period'   => __( 'per month', 'perfect10' ),
			'badge'    => __( 'Limited VIP Slots', 'perfect10' ),
			'featured' => 0,
			'order'    => 3,
			'features' => implode( "\n", array(
				__( 'Everything in Premium Placement', 'perfect10' ),
				__( '“This Month’s Muses” #01–06 homepage spotlight', 'perfect10' ),
				__( 'Numbered editorial showcase card', 'perfect10' ),
				__( 'Unlimited gallery portraits & tour updates', 'perfect10' ),
				__( 'Multi-city visibility across both counties', 'perfect10' ),
				__( 'Priority Hollywood Elite Escorts cross-network review', 'perfect10' ),
			) ),
		),
	);

	$created = 0;

	foreach ( $packages as $package ) {
		$existing = get_page_by_path( $package['slug'], OBJECT, 'p10_package' );
		if ( $existing ) {
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'p10_package',
				'post_status'  => 'publish',
				'post_title'   => $package['title'],
				'post_name'    => $package['slug'],
				'menu_order'   => $package['order'],
				'post_content' => p10_editorial_page_html( 'premium-packages' ),
				'post_excerpt' => $package['features'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			continue;
		}
		if ( function_exists( 'p10_demo_mark_post' ) ) {
			p10_demo_mark_post( (int) $post_id, 'package' );
		}

		update_post_meta( $post_id, 'p10_price', (float) $package['price'] );
		update_post_meta( $post_id, 'p10_period', $package['period'] );
		update_post_meta( $post_id, 'p10_badge', $package['badge'] );
		update_post_meta( $post_id, 'p10_features', $package['features'] );
		update_post_meta( $post_id, 'p10_featured', $package['featured'] );

		$created++;
	}

	$log[] = sprintf( __( 'Packages ready: %d created.', 'perfect10' ), $created );
}

/**
 * Create guest reviews.
 *
 * @param array $log Log lines, by reference.
 */
function p10_import_reviews( &$log ) {
	$reviews = array(
		array(
			'slug'     => 'effortless-five-star-service',
			'title'    => __( 'Effortless, five-star service', 'perfect10' ),
			'body'     => __( 'The booking coordinator was polished, discreet, and made the entire Beverly Hills dinner arrangement effortless. Every detail—from attire alignment to punctual suite arrival—matched a true five-star concierge.', 'perfect10' ),
			'name'     => 'Jonathan R.',
			'location' => 'Beverly Hills',
			'order'    => 1,
		),
		array(
			'slug'     => 'the-gold-standard',
			'title'    => __( 'The Southern California gold standard', 'perfect10' ),
			'body'     => __( 'I have used boutique referral services across New York, London, and Milan. Nothing compares to the calibre of independent companions here or the calm professionalism of the booking desk.', 'perfect10' ),
			'name'     => 'William S.',
			'location' => 'Newport Beach',
			'order'    => 2,
		),
		array(
			'slug'     => 'confirmed-in-under-an-hour',
			'title'    => __( 'Confirmed in under an hour after landing', 'perfect10' ),
			'body'     => __( 'Called from LAX after touchdown and an evening dinner introduction in Century City was confirmed in under an hour. Radiant, articulate, and 100% true to her gallery portraits.', 'perfect10' ),
			'name'     => 'M. Delgado',
			'location' => 'Century City',
			'order'    => 3,
		),
		array(
			'slug'     => 'unhurried-santa-monica-weekend',
			'title'    => __( 'An unforgettable oceanfront weekend', 'perfect10' ),
			'body'     => __( 'Reserved a Friday-to-Sunday coastal stay in Santa Monica through the advance booking form. Warm chemistry, effortless conversation over sunset dining, and seamless privacy throughout.', 'perfect10' ),
			'name'     => 'David K.',
			'location' => 'Santa Monica',
			'order'    => 4,
		),
		array(
			'slug'     => 'seamless-irvine-executive-outcall',
			'title'    => __( 'Impeccable weeknight executive hospitality', 'perfect10' ),
			'body'     => __( 'After three days of summits near John Wayne Airport in Irvine, meeting a poised companion for a quiet late dinner and nightcap was the highlight of my trip.', 'perfect10' ),
			'name'     => 'A. Sterling',
			'location' => 'Irvine',
			'order'    => 5,
		),
		array(
			'slug'     => 'welcoming-for-couples',
			'title'    => __( 'Graceful, attentive & welcoming for couples', 'perfect10' ),
			'body'     => __( 'My wife and I celebrated our anniversary in West Hollywood and appreciated how thoughtfully the concierge matched us with a warm, emotionally intuitive companion.', 'perfect10' ),
			'name'     => 'C. & E. Vance',
			'location' => 'West Hollywood',
			'order'    => 6,
		),
	);

	$created = 0;

	foreach ( $reviews as $review ) {
		if ( get_page_by_path( $review['slug'], OBJECT, 'p10_review' ) ) {
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'p10_review',
				'post_status'  => 'publish',
				'post_title'   => $review['title'],
				'post_name'    => $review['slug'],
				'post_content' => $review['body'],
				'post_excerpt' => $review['body'],
				'menu_order'   => $review['order'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			continue;
		}
		if ( function_exists( 'p10_demo_mark_post' ) ) {
			p10_demo_mark_post( (int) $post_id, 'review' );
		}

		update_post_meta( $post_id, 'p10_guest_name', $review['name'] );
		update_post_meta( $post_id, 'p10_guest_location', $review['location'] );
		update_post_meta( $post_id, 'p10_guest_label', __( 'Verified Guest Reflection', 'perfect10' ) );
		update_post_meta( $post_id, 'p10_verified_review', 1 );
		update_post_meta( $post_id, 'p10_published_ok', 1 );
		update_post_meta( $post_id, 'p10_rating', 5 );
		update_post_meta( $post_id, 'p10_featured', $review['order'] <= 3 ? 1 : 0 );

		$created++;
	}

	$log[] = sprintf( __( 'Guest reviews ready: %d created.', 'perfect10' ), $created );
}

/**
 * Create 12 multi-gallery demo profiles spanning LA County, Orange County, and
 * every Profile Type — AND backfill any existing profiles missing photos or
 * 300–400 word biographies.
 *
 * @param array $counties Slug => term_id.
 * @param array $types    Slug => term_id.
 * @param array $log      Log lines, by reference.
 */
function p10_import_profiles( $counties, $types, &$log ) {
	// Pre-import all 8 bundled high-fashion portraits once so galleries build instantaneously.
	$portrait_ids = array();
	for ( $i = 1; $i <= 8; $i++ ) {
		$fn  = sprintf( 'portrait-%02d.jpg', $i );
		$aid = p10_import_bundled_image( $fn, sprintf( 'Perfect 10 Editorial Portrait %02d', $i ) );
		if ( $aid ) {
			$portrait_ids[ $i ] = $aid;
		}
	}

	$profiles = array(
		array(
			'slug'        => 'nicky',
			'name'        => 'Nicky',
			'city'        => 'beverly-hills',
			'city_label'  => 'Beverly Hills',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'ultimate-la-vip-escorts', 'independent-escorts' ),
			'nationality' => 'british',
			'nat_label'   => 'British',
			'age'         => 23,
			'height'      => 165,
			'rate'        => 900,
			'premium'     => 1,
			'featured'    => 1,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 1, 2, 6, 7 ),
			'services'    => 'Fine dining companion, Black-tie galas, Overnight retreats, Domestic & international travel',
			'languages'   => 'English, French',
			'hair'        => 'Blonde',
			'eyes'        => 'Hazel',
			'body_type'   => 'Slender',
			'dress'       => 'US 2',
			'quote'       => 'True luxury is feeling completely unhurried and effortlessly understood from the very first hello.',
		),
		array(
			'slug'        => 'jenna',
			'name'        => 'Jenna',
			'city'        => 'west-hollywood',
			'city_label'  => 'West Hollywood',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'ultimate-la-vip-escorts', 'independent-escorts' ),
			'nationality' => 'american',
			'nat_label'   => 'American',
			'age'         => 24,
			'height'      => 170,
			'rate'        => 800,
			'premium'     => 1,
			'featured'    => 2,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 2, 3, 6, 8 ),
			'services'    => 'Sunset Strip dinner dates, Red-carpet events, Cocktail lounges, Overnight companionship',
			'languages'   => 'English, Spanish',
			'hair'        => 'Brunette',
			'eyes'        => 'Amber',
			'body_type'   => 'Statuesque',
			'dress'       => 'US 4',
			'quote'       => 'Great company should feel effortless—warm laughter, keen conversation, and total discretion.',
		),
		array(
			'slug'        => 'alexia-rae',
			'name'        => 'Alexia Rae',
			'city'        => 'newport-beach',
			'city_label'  => 'Newport Beach',
			'county'      => 'orange-county',
			'categories'  => array( 'ultimate-la-vip-escorts', 'escort-tours' ),
			'nationality' => 'italian',
			'nat_label'   => 'Italian',
			'age'         => 25,
			'height'      => 168,
			'rate'        => 850,
			'premium'     => 1,
			'featured'    => 3,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 6, 1, 4, 5 ),
			'services'    => 'Harbor yacht evenings, Coastal weekend escapes, Fine dining, Overnight hospitality',
			'languages'   => 'English, Italian, French',
			'hair'        => 'Honey Brunette',
			'eyes'        => 'Brown',
			'body_type'   => 'Hourglass',
			'dress'       => 'US 4',
			'quote'       => 'From Newport Harbor sunsets to Beverly Hills tasting menus, every evening deserves poise.',
		),
		array(
			'slug'        => 'valentina',
			'name'        => 'Valentina',
			'city'        => 'santa-monica',
			'city_label'  => 'Santa Monica',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'independent-escorts', 'couples' ),
			'nationality' => 'latina',
			'nat_label'   => 'Latina',
			'age'         => 24,
			'height'      => 167,
			'rate'        => 750,
			'premium'     => 1,
			'featured'    => 4,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 4, 2, 7, 1 ),
			'services'    => 'Oceanfront dinner dates, Couple-friendly evenings, Weekend retreats, Travel companion',
			'languages'   => 'English, Spanish, Portuguese',
			'hair'        => 'Raven',
			'eyes'        => 'Dark Brown',
			'body_type'   => 'Athletic Curvy',
			'dress'       => 'US 4',
			'quote'       => 'Ocean breezes, unhurried conversation, and genuine warmth turn an evening into a memory.',
		),
		array(
			'slug'        => 'celeste',
			'name'        => 'Celeste',
			'city'        => 'irvine',
			'city_label'  => 'Irvine',
			'county'      => 'orange-county',
			'categories'  => array( 'independent-escorts', 'erotic-massage' ),
			'nationality' => 'asian',
			'nat_label'   => 'Asian',
			'age'         => 25,
			'height'      => 164,
			'rate'        => 700,
			'premium'     => 1,
			'featured'    => 5,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 5, 8, 3, 2 ),
			'services'    => 'Executive hotel outcalls, Sensual relaxation bodywork, Fine dining, Private suite evenings',
			'languages'   => 'English, Mandarin',
			'hair'        => 'Jet Black',
			'eyes'        => 'Dark Brown',
			'body_type'   => 'Petite Slim',
			'dress'       => 'US 2',
			'quote'       => 'Calm presence and restorative grace for busy executives visiting Orange County.',
		),
		array(
			'slug'        => 'anna',
			'name'        => 'Anna',
			'city'        => 'hollywood',
			'city_label'  => 'Hollywood',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'ultimate-la-vip-escorts', 'escort-agencies' ),
			'nationality' => 'european',
			'nat_label'   => 'European',
			'age'         => 26,
			'height'      => 173,
			'rate'        => 800,
			'premium'     => 1,
			'featured'    => 6,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 7, 1, 3, 6 ),
			'services'    => 'Premiere & gala accompaniment, Supper clubs, Overnight stays, Fly-me-to-you travel',
			'languages'   => 'English, German, French',
			'hair'        => 'Platinum Blonde',
			'eyes'        => 'Blue',
			'body_type'   => 'Runway Slim',
			'dress'       => 'US 2',
			'quote'       => 'Continental sophistication meets Hollywood twilight—always discreet, always punctual.',
		),
		array(
			'slug'        => 'wendy',
			'name'        => 'Wendy',
			'city'        => 'downtown-los-angeles',
			'city_label'  => 'Downtown Los Angeles',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'independent-escorts', 'escort-agencies' ),
			'nationality' => 'american',
			'nat_label'   => 'American',
			'age'         => 27,
			'height'      => 170,
			'rate'        => 650,
			'premium'     => 0,
			'featured'    => 7,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 3, 6, 2, 5 ),
			'services'    => 'Symphony & gallery evenings, Skyline hotel outcalls, Conversation-first dinner dates',
			'languages'   => 'English',
			'hair'        => 'Auburn',
			'eyes'        => 'Green',
			'body_type'   => 'Curvy',
			'dress'       => 'US 6',
			'quote'       => 'Articulate conversation over a great Bordeaux makes Downtown Los Angeles shine.',
		),
		array(
			'slug'        => 'cristy',
			'name'        => 'Cristy',
			'city'        => 'laguna-beach',
			'city_label'  => 'Laguna Beach',
			'county'      => 'orange-county',
			'categories'  => array( 'independent-escorts', 'escort-tours' ),
			'nationality' => 'spanish',
			'nat_label'   => 'Spanish',
			'age'         => 26,
			'height'      => 169,
			'rate'        => 750,
			'premium'     => 1,
			'featured'    => 8,
			'incall'      => 0,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 8, 4, 5, 6 ),
			'services'    => 'Cliffside resort weekends, Gallery-walk afternoons, Coastal fine dining, Overnights',
			'languages'   => 'English, Spanish',
			'hair'        => 'Dark Waves',
			'eyes'        => 'Brown',
			'body_type'   => 'Slim',
			'dress'       => 'US 4',
			'quote'       => 'Laguna sunsets and unhurried weekend retreats are my favourite Southern California canvas.',
		),
		array(
			'slug'        => 'camille',
			'name'        => 'Camille',
			'city'        => 'malibu',
			'city_label'  => 'Malibu',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'trans-escorts', 'independent-escorts' ),
			'nationality' => 'french',
			'nat_label'   => 'French',
			'age'         => 25,
			'height'      => 174,
			'rate'        => 750,
			'premium'     => 1,
			'featured'    => 9,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 2, 4, 7, 8 ),
			'services'    => 'Coastal dinner dates, Private villa evenings, Sunset Strip lounges, Overnight travel',
			'languages'   => 'English, French',
			'hair'        => 'Chestnut',
			'eyes'        => 'Hazel',
			'body_type'   => 'Tall Slender',
			'dress'       => 'US 4',
			'quote'       => 'Parisian charm, Malibu calm, and warm, judgement-free connection.',
		),
		array(
			'slug'        => 'alex',
			'name'        => 'Alex',
			'city'        => 'pasadena',
			'city_label'  => 'Pasadena',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'couples', 'erotic-massage' ),
			'nationality' => 'canadian',
			'nat_label'   => 'Canadian',
			'age'         => 26,
			'height'      => 168,
			'rate'        => 650,
			'premium'     => 0,
			'featured'    => 10,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 3, 5, 1, 4 ),
			'services'    => 'Couple introductions, Sensual relaxation massage, Heritage hotel evenings, Dinner dates',
			'languages'   => 'English, French',
			'hair'        => 'Warm Brown',
			'eyes'        => 'Hazel',
			'body_type'   => 'Athletic',
			'dress'       => 'US 4',
			'quote'       => 'Grounded, attentive, and genuinely delighted to make both first-timers and couples feel at ease.',
		),
		array(
			'slug'        => 'serena',
			'name'        => 'Serena',
			'city'        => 'costa-mesa',
			'city_label'  => 'Costa Mesa',
			'county'      => 'orange-county',
			'categories'  => array( 'escort-agencies', 'ultimate-la-vip-escorts' ),
			'nationality' => 'australian',
			'nat_label'   => 'Australian',
			'age'         => 24,
			'height'      => 171,
			'rate'        => 800,
			'premium'     => 1,
			'featured'    => 11,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 1, 7, 2, 6 ),
			'services'    => 'South Coast Plaza afternoons, Symphony evenings, Fine dining, Orange County hotel outcalls',
			'languages'   => 'English',
			'hair'        => 'Golden Blonde',
			'eyes'        => 'Blue',
			'body_type'   => 'Toned Slim',
			'dress'       => 'US 4',
			'quote'       => 'Bright Australian warmth paired with five-star coastal polish.',
		),
		array(
			'slug'        => 'maya',
			'name'        => 'Maya',
			'city'        => 'los-angeles',
			'city_label'  => 'Los Angeles',
			'county'      => 'los-angeles-county',
			'categories'  => array( 'erotic-massage', 'escort-tours' ),
			'nationality' => 'british',
			'nat_label'   => 'British',
			'age'         => 25,
			'height'      => 166,
			'rate'        => 700,
			'premium'     => 0,
			'featured'    => 12,
			'incall'      => 1,
			'outcall'     => 1,
			'overnight'   => 1,
			'portraits'   => array( 8, 5, 3, 4 ),
			'services'    => 'Aromatic bodywork sessions, Boutique hotel outcalls, Dinner companionship, Weekend tours',
			'languages'   => 'English, Hindi',
			'hair'        => 'Ebony Waves',
			'eyes'        => 'Dark Brown',
			'body_type'   => 'Slender Curvy',
			'dress'       => 'US 4',
			'quote'       => 'Quiet sanctuary, unhurried touch, and thoughtful conversation in the heart of Los Angeles.',
		),
	);

	$created = 0;
	$updated = 0;

	foreach ( $profiles as $definition ) {
		$headline  = $definition['city_label'] . ' · ' . $definition['nat_label'];
		$rich_bio  = p10_editorial_profile_bio_html(
			$definition['name'],
			$definition['city_label'],
			$definition['nat_label'],
			$headline,
			$definition['services']
		);
		$existing  = get_page_by_path( $definition['slug'], OBJECT, 'p10_profile' );

		if ( $existing ) {
			$post_id = (int) $existing->ID;
			if ( p10_word_count( $existing->post_content ) < 300 ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $rich_bio,
						'post_excerpt' => wp_trim_words( wp_strip_all_tags( $rich_bio ), 28 ),
					)
				);
			}
			$updated++;
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'p10_profile',
					'post_status'  => 'publish',
					'post_title'   => $definition['name'],
					'post_name'    => $definition['slug'],
					'post_content' => $rich_bio,
					'post_excerpt' => wp_trim_words( wp_strip_all_tags( $rich_bio ), 28 ),
					'menu_order'   => (int) $definition['featured'],
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			$post_id = (int) $post_id;
			if ( function_exists( 'p10_demo_mark_post' ) ) {
				p10_demo_mark_post( $post_id, 'profile' );
			}
			$created++;
		}

		update_post_meta( $post_id, 'p10_headline', $headline );
		update_post_meta( $post_id, 'p10_age', (int) $definition['age'] );
		update_post_meta( $post_id, 'p10_height_cm', (int) $definition['height'] );
		update_post_meta( $post_id, 'p10_rate', (float) $definition['rate'] );
		update_post_meta( $post_id, 'p10_currency', 'USD' );
		update_post_meta( $post_id, 'p10_verified', 1 );
		update_post_meta( $post_id, 'p10_available', 'available' );
		update_post_meta( $post_id, 'p10_featured_order', (int) $definition['featured'] );
		update_post_meta( $post_id, 'p10_incall', (int) $definition['incall'] );
		update_post_meta( $post_id, 'p10_outcall', (int) $definition['outcall'] );
		update_post_meta( $post_id, 'p10_overnight', (int) $definition['overnight'] );
		update_post_meta( $post_id, 'p10_dinner', 1 );
		update_post_meta( $post_id, 'p10_travel', 1 );
		update_post_meta( $post_id, 'p10_services', $definition['services'] );
		update_post_meta( $post_id, 'p10_languages', $definition['languages'] );
		update_post_meta( $post_id, 'p10_hair', $definition['hair'] );
		update_post_meta( $post_id, 'p10_eyes', $definition['eyes'] );
		update_post_meta( $post_id, 'p10_body_type', $definition['body_type'] );
		update_post_meta( $post_id, 'p10_dress', $definition['dress'] );
		update_post_meta( $post_id, 'p10_bio_heading', __( 'About', 'perfect10' ) );
		update_post_meta( $post_id, 'p10_premium', (int) $definition['premium'] );

		if ( ! empty( $definition['quote'] ) ) {
			update_post_meta( $post_id, 'p10_quote', $definition['quote'] );
		}

		// Taxonomies: city, county, categories, nationality.
		$city_term = get_term_by( 'slug', $definition['city'], 'p10_city' );
		if ( $city_term ) {
			wp_set_object_terms( $post_id, array( (int) $city_term->term_id ), 'p10_city' );
		}

		$county_slug = $definition['county'];
		if ( ! empty( $counties[ $county_slug ] ) ) {
			wp_set_object_terms( $post_id, array( (int) $counties[ $county_slug ] ), 'p10_county' );
		} else {
			$ct = get_term_by( 'slug', $county_slug, 'p10_county' );
			if ( $ct ) {
				wp_set_object_terms( $post_id, array( (int) $ct->term_id ), 'p10_county' );
			}
		}

		$cat_ids = array();
		foreach ( $definition['categories'] as $cat_slug ) {
			if ( ! empty( $types[ $cat_slug ] ) ) {
				$cat_ids[] = (int) $types[ $cat_slug ];
			} else {
				$cterm = get_term_by( 'slug', $cat_slug, 'p10_category' );
				if ( $cterm ) {
					$cat_ids[] = (int) $cterm->term_id;
				}
			}
		}
		if ( $cat_ids ) {
			wp_set_object_terms( $post_id, $cat_ids, 'p10_category' );
		}

		$nationality = get_term_by( 'slug', $definition['nationality'], 'p10_nationality' );
		if ( $nationality ) {
			wp_set_object_terms( $post_id, array( (int) $nationality->term_id ), 'p10_nationality' );
		}

		// Attach featured cover + 4 distinct gallery portraits so thumbnail switching works everywhere!
		$gallery_ids = array();
		foreach ( $definition['portraits'] as $p_idx ) {
			if ( ! empty( $portrait_ids[ $p_idx ] ) ) {
				$gallery_ids[] = (int) $portrait_ids[ $p_idx ];
			}
		}
		if ( ! empty( $gallery_ids ) ) {
			$cur_thumb = (int) get_post_thumbnail_id( $post_id );
			if ( ! $cur_thumb || ! p10_attachment_has_file( $cur_thumb ) ) {
				set_post_thumbnail( $post_id, $gallery_ids[0] );
			}
			$valid_existing = p10_profile_gallery( $post_id );
			if ( count( $valid_existing ) < 3 ) {
				update_post_meta( $post_id, 'p10_gallery', implode( ',', $gallery_ids ) );
			}
		}
	}

	// Also backfill any pre-existing profiles (e.g. from source-manifest) that lack valid binaries or multi-image galleries.
	if ( ! empty( $portrait_ids ) ) {
		$all_profiles = get_posts(
			array(
				'post_type'      => 'p10_profile',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
			)
		);
		$p_values = array_values( $portrait_ids );
		$p_count  = count( $p_values );

		foreach ( $all_profiles as $prof ) {
			$pid   = (int) $prof->ID;
			$thumb = (int) get_post_thumbnail_id( $pid );
			if ( ! $thumb || ! p10_attachment_has_file( $thumb ) ) {
				set_post_thumbnail( $pid, $p_values[ $pid % $p_count ] );
			}
			$gal = p10_profile_gallery( $prof );
			if ( count( $gal ) < 3 ) {
				$set = array();
				for ( $k = 0; $k < 4; $k++ ) {
					$set[] = $p_values[ ( $pid + $k * 2 ) % $p_count ];
				}
				update_post_meta( $pid, 'p10_gallery', implode( ',', array_unique( $set ) ) );
			}
			if ( p10_word_count( $prof->post_content ) < 300 ) {
				$city_t = p10_get_primary_term( $pid, 'p10_city' );
				$nat_t  = p10_get_primary_term( $pid, 'p10_nationality' );
				$c_name = $city_t ? $city_t->name : 'Los Angeles';
				$n_name = $nat_t ? $nat_t->name : 'American';
				$svcs   = (string) get_post_meta( $pid, 'p10_services', true );
				if ( ! $svcs ) {
					$svcs = 'Fine dining companion, Hotel outcalls, Evening events, Overnight retreats';
					update_post_meta( $pid, 'p10_services', $svcs );
				}
				$bio = p10_editorial_profile_bio_html( $prof->post_title, $c_name, $n_name, $c_name . ' · ' . $n_name, $svcs );
				wp_update_post(
					array(
						'ID'           => $pid,
						'post_content' => $bio,
						'post_excerpt' => wp_trim_words( wp_strip_all_tags( $bio ), 28 ),
					)
				);
			}
		}
	}

	$log[] = sprintf( __( 'Profiles ready with 4-photo galleries & 300–400w bios: %1$d created, %2$d updated.', 'perfect10' ), $created, $updated );
}

/**
 * Import 6 full 300–400 word editorial Blog / Journal posts for the Homepage
 * Blogs section and /journal/ archive.
 *
 * @param array $log Log lines, by reference.
 */
function p10_import_blogs( &$log ) {
	$articles = p10_demo_blog_posts_data();
	$created  = 0;

	foreach ( $articles as $article ) {
		$existing = get_page_by_path( $article['slug'], OBJECT, 'post' );

		if ( $existing ) {
			$post_id = (int) $existing->ID;
			if ( p10_word_count( $existing->post_content ) < 300 ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $article['content'],
						'post_excerpt' => $article['excerpt'],
					)
				);
			}
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => $article['title'],
					'post_name'    => $article['slug'],
					'post_date'    => $article['date'],
					'post_content' => $article['content'],
					'post_excerpt' => $article['excerpt'],
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			$post_id = (int) $post_id;
			if ( function_exists( 'p10_demo_mark_post' ) ) {
				p10_demo_mark_post( $post_id, 'post' );
			}
			$created++;
		}

		if ( ! empty( $article['category'] ) ) {
			$cat_term = term_exists( $article['category'], 'category' );
			if ( ! $cat_term ) {
				$cat_term = wp_insert_term( $article['category'], 'category' );
			}
			if ( ! is_wp_error( $cat_term ) ) {
				$cat_id = is_array( $cat_term ) ? (int) $cat_term['term_id'] : (int) $cat_term;
				wp_set_post_categories( $post_id, array( $cat_id ), false );
			}
		}

		$cur_thumb = (int) get_post_thumbnail_id( $post_id );
		if ( ! empty( $article['image'] ) && ( ! $cur_thumb || ! p10_attachment_has_file( $cur_thumb ) ) ) {
			$img_id = p10_import_bundled_image( $article['image'], $article['title'] );
			if ( $img_id ) {
				set_post_thumbnail( $post_id, $img_id );
			}
		}
	}

	// Unpublish WordPress's default "Hello world!" post if still sitting around.
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( $hello && 'publish' === $hello->post_status ) {
		wp_update_post(
			array(
				'ID'          => (int) $hello->ID,
				'post_status' => 'draft',
			)
		);
	}

	$log[] = sprintf( __( 'Editorial Journal articles ready: %d created.', 'perfect10' ), $created );
}

/**
 * Import hero, advertiser, city, and profile-type imagery unconditionally from
 * the bundled luxury image library.
 *
 * @param array $log Optional log lines, by reference.
 */
function p10_import_imagery( &$log = array() ) {
	$cur_hero = (int) get_theme_mod( 'p10_hero_image', 0 );
	if ( ! $cur_hero || ! p10_attachment_has_file( $cur_hero ) ) {
		$hero_id = p10_import_bundled_image( 'hero-la-nights.jpg', 'Perfect 10 Beverly Hills Twilight Hero' );
		if ( $hero_id ) {
			set_theme_mod( 'p10_hero_image', $hero_id );
		}
	}

	$cur_ads = (int) get_theme_mod( 'p10_ads_image', 0 );
	if ( ! $cur_ads || ! p10_attachment_has_file( $cur_ads ) ) {
		$ads_id = p10_import_bundled_image( 'city-hollywood.jpg', 'Perfect 10 Advertisers Showcase' );
		if ( $ads_id ) {
			set_theme_mod( 'p10_ads_image', $ads_id );
		}
	}

	if ( is_array( $log ) ) {
		$log[] = __( 'Hero, advertiser, destination, and category imagery attached.', 'perfect10' );
	}
}

/**
 * Create AND assign the desktop Primary Menu, Footer Menu, and Legal Menu
 * under ALL conditions (even if the menu already existed from an earlier theme
 * activation).
 *
 * @param array $pages Slug => page ID.
 * @param array $log   Optional log lines, by reference.
 */
function p10_import_menus( $pages, &$log = array() ) {
	$locations = get_theme_mod( 'nav_menu_locations' );
	$locations = is_array( $locations ) ? $locations : array();

	$rel_url = static function ( $url ) {
		if ( ! $url ) {
			return '';
		}
		$rel = wp_make_link_relative( (string) $url );
		return '' !== $rel ? $rel : '/';
	};

	// 1. Desktop Top Menu ("Perfect 10 Primary") -> 'primary' location.
	$primary_name = __( 'Perfect 10 Primary', 'perfect10' );
	$primary_menu = wp_get_nav_menu_object( $primary_name );
	$primary_id   = $primary_menu ? (int) $primary_menu->term_id : (int) wp_create_nav_menu( $primary_name );

	if ( $primary_id && ! is_wp_error( $primary_id ) ) {
		$existing_items = wp_get_nav_menu_items( $primary_id );
		if ( empty( $existing_items ) ) {
			$add_item = static function ( $title, $url, $parent = 0 ) use ( $primary_id, $rel_url ) {
				if ( ! $url ) {
					return 0;
				}
				$id = wp_update_nav_menu_item(
					$primary_id,
					0,
					array(
						'menu-item-title'     => $title,
						'menu-item-url'       => $rel_url( $url ),
						'menu-item-parent-id' => (int) $parent,
						'menu-item-status'    => 'publish',
						'menu-item-type'      => 'custom',
					)
				);
				return is_wp_error( $id ) ? 0 : (int) $id;
			};

			$add_item( __( 'Home', 'perfect10' ), home_url( '/' ) );
			$add_item( __( 'Directory', 'perfect10' ), p10_directory_url() );

			// Dropdown: Locations.
			$loc_parent = $add_item(
				__( 'Locations', 'perfect10' ),
				! empty( $pages['all-cities'] ) ? get_permalink( $pages['all-cities'] ) : p10_utility_url( 'cities' )
			);
			if ( $loc_parent ) {
				$add_item( __( 'Los Angeles County', 'perfect10' ), trailingslashit( home_url( '/los-angeles-county/' ) ), $loc_parent );
				$add_item( __( 'Orange County', 'perfect10' ), trailingslashit( home_url( '/orange-county/' ) ), $loc_parent );
				$add_item( __( 'Beverly Hills', 'perfect10' ), trailingslashit( home_url( '/los-angeles-county/beverly-hills/' ) ), $loc_parent );
				$add_item( __( 'West Hollywood', 'perfect10' ), trailingslashit( home_url( '/los-angeles-county/west-hollywood/' ) ), $loc_parent );
				$add_item( __( 'Santa Monica', 'perfect10' ), trailingslashit( home_url( '/los-angeles-county/santa-monica/' ) ), $loc_parent );
				$add_item( __( 'Downtown Los Angeles', 'perfect10' ), trailingslashit( home_url( '/los-angeles-county/downtown-los-angeles/' ) ), $loc_parent );
				$add_item( __( 'Newport Beach', 'perfect10' ), trailingslashit( home_url( '/orange-county/newport-beach/' ) ), $loc_parent );
				$add_item( __( 'Irvine', 'perfect10' ), trailingslashit( home_url( '/orange-county/irvine/' ) ), $loc_parent );
				$add_item( __( 'All 211 Cities A–Z', 'perfect10' ), p10_utility_url( 'cities' ), $loc_parent );
			}

			// Dropdown: Browse Profile Types.
			$browse_parent = $add_item( __( 'Browse', 'perfect10' ), p10_directory_url() );
			if ( $browse_parent ) {
				$add_item( __( 'Royal Crown Premium', 'perfect10' ), p10_directory_url( array( 'premium' => 1 ) ), $browse_parent );
				foreach ( p10_get_categories() as $cat ) {
					$add_item( $cat->name, p10_term_url( $cat ), $browse_parent );
				}
			}

			$add_item( __( 'Packages', 'perfect10' ), p10_utility_url( 'packages' ) );
			$add_item( __( 'Reviews', 'perfect10' ), p10_utility_url( 'reviews' ) );
			$add_item( __( 'Journal', 'perfect10' ), p10_journal_url() );
			$add_item( __( 'Advertise', 'perfect10' ), p10_utility_url( 'register' ) );
		}

		// Always assign to 'primary' location even when items already existed!
		$locations['primary'] = $primary_id;
	}

	// 2. Footer Explore Menu ("Perfect 10 Footer") -> 'footer' location.
	$footer_name = __( 'Perfect 10 Footer', 'perfect10' );
	$footer_menu = wp_get_nav_menu_object( $footer_name );
	$footer_id   = $footer_menu ? (int) $footer_menu->term_id : (int) wp_create_nav_menu( $footer_name );

	if ( $footer_id && ! is_wp_error( $footer_id ) ) {
		if ( empty( wp_get_nav_menu_items( $footer_id ) ) ) {
			$footer_links = array(
				__( 'All Companions', 'perfect10' )         => p10_directory_url(),
				__( 'Premium Collection', 'perfect10' )     => p10_directory_url( array( 'premium' => 1 ) ),
				__( 'Reservation Form', 'perfect10' )       => p10_utility_url( 'booking' ),
				__( 'Editorial Journal', 'perfect10' )      => p10_journal_url(),
				__( 'Guest Reviews', 'perfect10' )          => p10_utility_url( 'reviews' ),
				__( 'Premium Packages', 'perfect10' )       => p10_utility_url( 'packages' ),
				__( 'Create a Listing', 'perfect10' )       => p10_utility_url( 'register' ),
				__( 'About Perfect 10', 'perfect10' )       => p10_utility_url( 'about' ),
				__( 'Etiquette & FAQ', 'perfect10' )        => p10_utility_url( 'faq' ),
				__( 'All Cities A–Z', 'perfect10' )         => p10_utility_url( 'cities' ),
			);
			foreach ( $footer_links as $title => $url ) {
				if ( $url ) {
					wp_update_nav_menu_item(
						$footer_id,
						0,
						array(
							'menu-item-title'  => $title,
							'menu-item-url'    => $rel_url( $url ),
							'menu-item-status' => 'publish',
							'menu-item-type'   => 'custom',
						)
					);
				}
			}
		}
		if ( empty( $locations['footer'] ) ) {
			$locations['footer'] = $footer_id;
		}
	}

	// 3. Legal Menu ("Perfect 10 Legal") -> 'legal' location.
	$legal_name = __( 'Perfect 10 Legal', 'perfect10' );
	$legal_menu = wp_get_nav_menu_object( $legal_name );
	$legal_id   = $legal_menu ? (int) $legal_menu->term_id : (int) wp_create_nav_menu( $legal_name );

	if ( $legal_id && ! is_wp_error( $legal_id ) ) {
		if ( empty( wp_get_nav_menu_items( $legal_id ) ) ) {
			$legal_links = array(
				__( 'Privacy Policy', 'perfect10' )  => p10_utility_url( 'privacy' ),
				__( 'Etiquette & FAQ', 'perfect10' ) => p10_utility_url( 'faq' ),
				__( 'About', 'perfect10' )           => p10_utility_url( 'about' ),
				__( 'Contact & Booking', 'perfect10' ) => p10_utility_url( 'booking' ),
			);
			foreach ( $legal_links as $title => $url ) {
				if ( $url ) {
					wp_update_nav_menu_item(
						$legal_id,
						0,
						array(
							'menu-item-title'  => $title,
							'menu-item-url'    => $rel_url( $url ),
							'menu-item-status' => 'publish',
							'menu-item-type'   => 'custom',
						)
					);
				}
			}
		}
		if ( empty( $locations['legal'] ) ) {
			$locations['legal'] = $legal_id;
		}
	}

	set_theme_mod( 'nav_menu_locations', $locations );

	if ( function_exists( 'p10_demo_manifest' ) ) {
		$manifest = p10_demo_manifest();
		foreach ( array( $primary_id, $footer_id, $legal_id ) as $mid ) {
			if ( $mid && ! is_wp_error( $mid ) && ! in_array( (int) $mid, (array) $manifest['menus'], true ) ) {
				$manifest['menus'][] = (int) $mid;
			}
		}
		update_option( P10_DEMO_MANIFEST_OPTION, $manifest, false );
	}

	if ( is_array( $log ) ) {
		$log[] = __( 'Desktop Primary, Footer, and Legal navigation menus created and assigned.', 'perfect10' );
	}
}

/**
 * Remove default WordPress starter widget clutter (Archives, Uncategorized
 * Categories, Meta) from the footer widget area and normalize legacy dim color
 * mods so the site looks publication-ready immediately.
 *
 * @param array $log Optional log lines, by reference.
 */
function p10_clean_default_wp_clutter( &$log = array() ) {
	$sidebars = get_option( 'sidebars_widgets', array() );
	if ( is_array( $sidebars ) && ! empty( $sidebars['p10-footer-1'] ) ) {
		$cleaned = array();
		foreach ( (array) $sidebars['p10-footer-1'] as $widget_id ) {
			// Strip default WP starter widgets (block-*, archives-*, categories-*, meta-*, recent-*).
			if ( preg_match( '/^(block|archives|categories|meta|recent-comments|recent-posts|search)-\d+$/', (string) $widget_id ) ) {
				continue;
			}
			$cleaned[] = $widget_id;
		}
		$sidebars['p10-footer-1'] = $cleaned;
		update_option( 'sidebars_widgets', $sidebars );
	}

	// Ensure high-contrast crisp white typography mods replace any legacy dim values.
	$legacy_mods = array(
		'p10_color_cream'       => array( '#f5f0e6', '#ffffff' ),
		'p10_color_muted'       => array( '#9c9484', '#efefef' ),
		'p10_footer_text_color' => array( '#a9a49a', '#f2f2f2' ),
		'p10_footer_link_color' => array( '#d7d1c5', '#ffffff' ),
	);
	foreach ( $legacy_mods as $mod_key => $pair ) {
		$cur = strtolower( (string) get_theme_mod( $mod_key, '' ) );
		if ( '' === $cur || $cur === $pair[0] ) {
			set_theme_mod( $mod_key, $pair[1] );
		}
	}

	if ( is_array( $log ) ) {
		$log[] = __( 'Footer widget clutter cleared and high-contrast white typography verified.', 'perfect10' );
	}
}
