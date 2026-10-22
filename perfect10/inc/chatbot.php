<?php
/**
 * Perfect 10 — Full-Website-Scanning Concierge Chatbot & Lead Capture.
 *
 * Works 100% out of the box WITHOUT any API key by dynamically scanning all
 * published companions, counties, 211+ cities, profile types, nationalities,
 * packages, blog posts, pages, reviews, contact details, and our official child
 * website (Hollywood Elite Escorts), while conversationally collecting visitor
 * leads (Name, Mobile Number, Email, Preferences) into WP Admin -> Enquiries
 * ("we will get in touch with you very soon").
 *
 * Optional Agent API Enhancement: Site administrators can optionally connect an
 * OpenAI, Anthropic, or custom OpenAI-compatible Agent API key + endpoint +
 * model in Appearance -> Website Assistant (or Appearance -> Customize ->
 * Perfect 10 -> 12 · Website Assistant & Agent API) to synthesize responses
 * with an LLM grounded strictly in the live website scan.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'P10_CHAT_NOTICE', '2026-10-22-v2' );

/**
 * Retrieve merged Website Assistant settings.
 *
 * @return array
 */
function p10_chat_settings() {
	$saved = get_option( 'p10_chat_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$defaults = array(
		'provider'         => 'local',
		'api_key'          => '',
		'agent_endpoint'   => 'https://api.openai.com/v1/chat/completions',
		'model'            => 'gpt-4o-mini',
		'page_ids'         => '',
		'followup'         => 1,
		'auto_prompt_lead' => 1,
		/* Appearance & Copy */
		'title'            => __( 'Perfect 10 Concierge', 'perfect10' ),
		'subtitle'         => __( '24/7 Live Website Guide & Private Reservations', 'perfect10' ),
		'welcome'          => __( 'Welcome to Perfect 10. Ask me anything about our companions, Beverly Hills & Orange County locations, rates, packages, or share your mobile & email for a private concierge callback.', 'perfect10' ),
		'suggestions'      => __( "Who is available in Beverly Hills or Newport Beach?\nWhat are your companion rates & VIP packages?\nBook a reservation / request a callback\nTell me about Hollywood Elite Escorts", 'perfect10' ),
		'position'         => 'right',
		'icon'             => '',
		'width'            => 430,
		'height'           => 680,
		'header_bg'        => '',
		'header_text'      => '',
		'bubble_bg'        => '',
		'bubble_text'      => '',
		'user_bubble_bg'   => '',
		'user_bubble_text' => '',
		'input_bg'         => '',
		'input_text'       => '',
		'input_border'     => '',
		'radius'           => '',
		'shadow'           => 'strong',
		'font_size'        => 0,
		'show_on'          => 'all',
	);

	$merged = wp_parse_args( $saved, $defaults );

	// Allow Customizer overrides for provider/key/model/endpoint if configured there.
	$mod_provider = get_theme_mod( 'p10_chat_provider', '' );
	if ( $mod_provider && in_array( $mod_provider, array( 'local', 'openai', 'anthropic', 'custom_agent' ), true ) ) {
		$merged['provider'] = $mod_provider;
	}
	$mod_key = trim( (string) get_theme_mod( 'p10_chat_api_key', '' ) );
	if ( '' !== $mod_key && empty( $merged['api_key'] ) ) {
		$merged['api_key'] = $mod_key;
	}
	$mod_model = trim( (string) get_theme_mod( 'p10_chat_model', '' ) );
	if ( '' !== $mod_model ) {
		$merged['model'] = $mod_model;
	}
	$mod_endpoint = trim( (string) get_theme_mod( 'p10_chat_agent_endpoint', '' ) );
	if ( '' !== $mod_endpoint ) {
		$merged['agent_endpoint'] = $mod_endpoint;
	}

	return $merged;
}

/**
 * Resolve active Agent API key (constant takes precedence, then admin/Customizer setting).
 * Never exposed to frontend JavaScript.
 *
 * @return string
 */
function p10_chat_resolve_api_key() {
	if ( defined( 'P10_CHAT_API_KEY' ) && P10_CHAT_API_KEY ) {
		return trim( (string) P10_CHAT_API_KEY );
	}
	$s = p10_chat_settings();
	return trim( (string) $s['api_key'] );
}

add_action(
	'admin_menu',
	function () {
		add_theme_page(
			__( 'Website Assistant & Agent API', 'perfect10' ),
			__( 'Website Assistant', 'perfect10' ),
			'manage_options',
			'p10-chat',
			'p10_chat_settings_screen'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'p10_chat',
			'p10_chat_settings',
			array(
				'sanitize_callback' => 'p10_sanitize_chat_settings',
			)
		);
	}
);

/**
 * Sanitize Website Assistant settings.
 *
 * @param mixed $raw Raw posted array.
 * @return array
 */
function p10_sanitize_chat_settings( $raw ) {
	$raw      = is_array( $raw ) ? $raw : array();
	$existing = get_option( 'p10_chat_settings', array() );
	$existing = is_array( $existing ) ? $existing : array();

	$color = static function ( $key ) use ( $raw ) {
		$value = isset( $raw[ $key ] ) ? sanitize_hex_color( (string) $raw[ $key ] ) : '';
		return $value ? $value : '';
	};
	$text  = static function ( $key, $fallback = '' ) use ( $raw ) {
		$value = isset( $raw[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $raw[ $key ] ) ) : '';
		return '' !== $value ? $value : $fallback;
	};
	$lines = static function ( $key ) use ( $raw ) {
		$value = isset( $raw[ $key ] ) ? sanitize_textarea_field( wp_unslash( (string) $raw[ $key ] ) ) : '';
		$rows  = array_filter( array_map( 'trim', preg_split( '/\R/', $value ) ) );
		$rows  = array_slice( array_unique( $rows ), 0, 5 );
		return implode(
			"\n",
			array_map(
				static function ( $row ) {
					return mb_substr( $row, 0, 120 );
				},
				$rows
			)
		);
	};

	$allowed_providers = array( 'local', 'openai', 'anthropic', 'custom_agent' );
	$provider          = isset( $raw['provider'] ) && in_array( $raw['provider'], $allowed_providers, true ) ? $raw['provider'] : 'local';

	// Preserve existing API key if the password input was submitted masked/blank unless 'clear_api_key' is ticked.
	$new_key = isset( $raw['api_key'] ) ? trim( sanitize_text_field( wp_unslash( (string) $raw['api_key'] ) ) ) : '';
	if ( ! empty( $raw['clear_api_key'] ) ) {
		$api_key = '';
	} elseif ( '' !== $new_key && 0 !== strpos( $new_key, '••••' ) ) {
		$api_key = $new_key;
	} else {
		$api_key = isset( $existing['api_key'] ) ? (string) $existing['api_key'] : '';
	}

	return array(
		'provider'         => $provider,
		'api_key'          => $api_key,
		'agent_endpoint'   => esc_url_raw( isset( $raw['agent_endpoint'] ) && $raw['agent_endpoint'] ? (string) $raw['agent_endpoint'] : 'https://api.openai.com/v1/chat/completions' ),
		'model'            => $text( 'model', 'gpt-4o-mini' ),
		'page_ids'         => implode( ',', array_filter( wp_parse_id_list( isset( $raw['page_ids'] ) ? $raw['page_ids'] : '' ) ) ),
		'followup'         => empty( $raw['followup'] ) ? 0 : 1,
		'auto_prompt_lead' => empty( $raw['auto_prompt_lead'] ) ? 0 : 1,
		'title'            => $text( 'title', __( 'Perfect 10 Concierge', 'perfect10' ) ),
		'subtitle'         => $text( 'subtitle', __( '24/7 Live Website Guide & Private Reservations', 'perfect10' ) ),
		'welcome'          => mb_substr( sanitize_textarea_field( wp_unslash( isset( $raw['welcome'] ) ? (string) $raw['welcome'] : '' ) ), 0, 400 ),
		'suggestions'      => $lines( 'suggestions' ),
		'position'         => isset( $raw['position'] ) && 'left' === $raw['position'] ? 'left' : 'right',
		'icon'             => esc_url_raw( isset( $raw['icon'] ) ? (string) $raw['icon'] : '' ),
		'width'            => max( 340, min( 620, absint( isset( $raw['width'] ) ? $raw['width'] : 430 ) ) ),
		'height'           => max( 460, min( 900, absint( isset( $raw['height'] ) ? $raw['height'] : 680 ) ) ),
		'header_bg'        => $color( 'header_bg' ),
		'header_text'      => $color( 'header_text' ),
		'bubble_bg'        => $color( 'bubble_bg' ),
		'bubble_text'      => $color( 'bubble_text' ),
		'user_bubble_bg'   => $color( 'user_bubble_bg' ),
		'user_bubble_text' => $color( 'user_bubble_text' ),
		'input_bg'         => $color( 'input_bg' ),
		'input_text'       => $color( 'input_text' ),
		'input_border'     => $color( 'input_border' ),
		'radius'           => max( 0, min( 32, absint( isset( $raw['radius'] ) ? $raw['radius'] : 0 ) ) ) ?: '',
		'shadow'           => in_array( isset( $raw['shadow'] ) ? $raw['shadow'] : '', array( 'soft', 'medium', 'strong', 'none' ), true ) ? $raw['shadow'] : 'strong',
		'font_size'        => max( 0, min( 20, absint( isset( $raw['font_size'] ) ? $raw['font_size'] : 0 ) ) ),
		'show_on'          => in_array( isset( $raw['show_on'] ) ? $raw['show_on'] : 'all', array( 'all', 'front', 'profiles', 'locations' ), true ) ? $raw['show_on'] : 'all',
	);
}

/**
 * Admin settings screen: Appearance -> Website Assistant.
 */
function p10_chat_settings_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = p10_chat_settings();
	$has_key = '' !== p10_chat_resolve_api_key();

	$color_fields = array(
		'header_bg'        => __( 'Header background', 'perfect10' ),
		'header_text'      => __( 'Header text', 'perfect10' ),
		'bubble_bg'        => __( 'Assistant bubble background', 'perfect10' ),
		'bubble_text'      => __( 'Assistant bubble text', 'perfect10' ),
		'user_bubble_bg'   => __( 'Visitor bubble background', 'perfect10' ),
		'user_bubble_text' => __( 'Visitor bubble text', 'perfect10' ),
		'input_bg'         => __( 'Input background', 'perfect10' ),
		'input_text'       => __( 'Input text', 'perfect10' ),
		'input_border'     => __( 'Input border', 'perfect10' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Perfect 10 · Website Assistant & Optional Agent API', 'perfect10' ); ?></h1>
		<p style="max-width:860px;font-size:14px;line-height:1.6">
			<?php esc_html_e( 'Out of the box (without any API key), the Perfect 10 Concierge dynamically scans your entire website—companions, rates, counties, 211+ cities, profile types, packages, blog articles, pages, contact details, and Hollywood Elite Escorts—answers visitor questions instantly, and captures customer leads (Name, Mobile Number, Email, Inquiry) straight into Enquiries.', 'perfect10' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'p10_chat' ); ?>

			<h2><?php esc_html_e( '1. Intelligence Engine & Optional Agent API Key', 'perfect10' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="p10-chat-provider"><?php esc_html_e( 'Intelligence Mode', 'perfect10' ); ?></label></th>
					<td>
						<select id="p10-chat-provider" name="p10_chat_settings[provider]">
							<option value="local"<?php selected( $s['provider'], 'local' ); ?>><?php esc_html_e( 'Built-in Full-Website Scanner (No API Key Required — Default)', 'perfect10' ); ?></option>
							<option value="openai"<?php selected( $s['provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI Agent API (Enhanced Conversational Synthesis)', 'perfect10' ); ?></option>
							<option value="anthropic"<?php selected( $s['provider'], 'anthropic' ); ?>><?php esc_html_e( 'Anthropic Claude Agent API (Enhanced Conversational Synthesis)', 'perfect10' ); ?></option>
							<option value="custom_agent"<?php selected( $s['provider'], 'custom_agent' ); ?>><?php esc_html_e( 'Custom Agent Endpoint (OpenAI-Compatible URL + Key)', 'perfect10' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Even when an Agent API mode is selected, if no key is entered or the external API times out, the chatbot automatically falls back to the built-in Website Scanner so it never breaks.', 'perfect10' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-api-key"><?php esc_html_e( 'Agent API Key (Optional)', 'perfect10' ); ?></label></th>
					<td>
						<input
							type="password"
							id="p10-chat-api-key"
							name="p10_chat_settings[api_key]"
							value="<?php echo esc_attr( ! empty( $s['api_key'] ) ? '••••••••••••••••' : '' ); ?>"
							class="regular-text"
							autocomplete="new-password"
							placeholder="<?php esc_attr_e( 'sk-... (leave blank for No-API-Key mode)', 'perfect10' ); ?>">
						<?php if ( $has_key ) : ?>
							<span style="color:#1b7e3c;font-weight:600;margin-left:8px"><?php esc_html_e( '✓ API key configured', 'perfect10' ); ?></span>
							<label style="margin-left:14px">
								<input type="checkbox" name="p10_chat_settings[clear_api_key]" value="1">
								<?php esc_html_e( 'Clear saved key', 'perfect10' ); ?>
							</label>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Stored server-side only (never sent to browsers). You may also define P10_CHAT_API_KEY in wp-config.php.', 'perfect10' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-model"><?php esc_html_e( 'Agent Model', 'perfect10' ); ?></label></th>
					<td>
						<input id="p10-chat-model" name="p10_chat_settings[model]" value="<?php echo esc_attr( $s['model'] ); ?>" class="regular-text" placeholder="gpt-4o-mini">
						<p class="description"><?php esc_html_e( 'Examples: gpt-4o-mini, gpt-4o, claude-3-5-haiku-latest.', 'perfect10' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-endpoint"><?php esc_html_e( 'Custom Agent Endpoint URL', 'perfect10' ); ?></label></th>
					<td>
						<input id="p10-chat-endpoint" name="p10_chat_settings[agent_endpoint]" value="<?php echo esc_attr( $s['agent_endpoint'] ); ?>" class="large-text" placeholder="https://api.openai.com/v1/chat/completions">
						<p class="description"><?php esc_html_e( 'Used when Intelligence Mode is set to Custom Agent Endpoint.', 'perfect10' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Lead Capture (Mobile & Email)', 'perfect10' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px">
							<input type="checkbox" name="p10_chat_settings[followup]" value="1"<?php checked( $s['followup'], 1 ); ?>>
							<?php esc_html_e( 'Collect customer Name, Mobile Number, Email & Inquiry in chat and save to WP Admin → Enquiries', 'perfect10' ); ?>
						</label>
						<label style="display:block">
							<input type="checkbox" name="p10_chat_settings[auto_prompt_lead]" value="1"<?php checked( $s['auto_prompt_lead'], 1 ); ?>>
							<?php esc_html_e( 'Proactively invite visitors to leave their mobile number & email so we can get in touch soon', 'perfect10' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( '2. Launcher & Window Copy', 'perfect10' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="p10-chat-title"><?php esc_html_e( 'Title', 'perfect10' ); ?></label></th>
					<td><input id="p10-chat-title" class="regular-text" name="p10_chat_settings[title]" value="<?php echo esc_attr( $s['title'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-subtitle"><?php esc_html_e( 'Subtitle', 'perfect10' ); ?></label></th>
					<td><input id="p10-chat-subtitle" class="large-text" name="p10_chat_settings[subtitle]" value="<?php echo esc_attr( $s['subtitle'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-welcome"><?php esc_html_e( 'Welcome message', 'perfect10' ); ?></label></th>
					<td><textarea id="p10-chat-welcome" class="large-text" rows="2" maxlength="400" name="p10_chat_settings[welcome]"><?php echo esc_textarea( $s['welcome'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-suggestions"><?php esc_html_e( 'Suggested quick prompts', 'perfect10' ); ?></label></th>
					<td><textarea id="p10-chat-suggestions" class="large-text" rows="4" name="p10_chat_settings[suggestions]"><?php echo esc_textarea( $s['suggestions'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-position"><?php esc_html_e( 'Launcher position', 'perfect10' ); ?></label></th>
					<td>
						<select id="p10-chat-position" name="p10_chat_settings[position]">
							<option value="right"<?php selected( $s['position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'perfect10' ); ?></option>
							<option value="left"<?php selected( $s['position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'perfect10' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Window size', 'perfect10' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Width', 'perfect10' ); ?> <input type="number" min="340" max="620" step="10" name="p10_chat_settings[width]" value="<?php echo esc_attr( $s['width'] ); ?>"> px</label>
						<label style="margin-left:12px"><?php esc_html_e( 'Height', 'perfect10' ); ?> <input type="number" min="460" max="900" step="10" name="p10_chat_settings[height]" value="<?php echo esc_attr( $s['height'] ); ?>"> px</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="p10-chat-visibility"><?php esc_html_e( 'Show on', 'perfect10' ); ?></label></th>
					<td>
						<select id="p10-chat-visibility" name="p10_chat_settings[show_on]">
							<option value="all"<?php selected( $s['show_on'], 'all' ); ?>><?php esc_html_e( 'Every page', 'perfect10' ); ?></option>
							<option value="front"<?php selected( $s['show_on'], 'front' ); ?>><?php esc_html_e( 'Homepage only', 'perfect10' ); ?></option>
							<option value="profiles"<?php selected( $s['show_on'], 'profiles' ); ?>><?php esc_html_e( 'Profile pages', 'perfect10' ); ?></option>
							<option value="locations"<?php selected( $s['show_on'], 'locations' ); ?>><?php esc_html_e( 'Location pages', 'perfect10' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( '3. Optional Color Overrides', 'perfect10' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( $color_fields as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="p10-chat-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<input type="text" class="p10-color-field" id="p10-chat-<?php echo esc_attr( $key ); ?>" name="p10_chat_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="#">
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button( __( 'Save Assistant Settings', 'perfect10' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Should the assistant render on the current view?
 *
 * @return bool
 */
function p10_chat_is_visible() {
	if ( ! p10_option( 'concierge_enabled', 1 ) ) {
		return false;
	}

	$s   = p10_chat_settings();
	$on  = isset( $s['show_on'] ) ? $s['show_on'] : 'all';
	$yes = true;

	switch ( $on ) {
		case 'front':
			$yes = is_front_page();
			break;
		case 'profiles':
			$yes = is_singular( 'p10_profile' ) || is_post_type_archive( 'p10_profile' ) || is_tax( 'p10_category' ) || is_tax( 'p10_nationality' );
			break;
		case 'locations':
			$yes = is_tax( array( 'p10_county', 'p10_city' ) );
			break;
	}

	return (bool) apply_filters( 'p10_chat_is_visible', $yes );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! p10_chat_is_visible() ) {
			return;
		}
		wp_enqueue_style( 'p10-chat', P10_URI . 'assets/css/chatbot.css', array(), P10_VERSION );
		wp_enqueue_script( 'p10-chat', P10_URI . 'assets/js/chatbot.js', array(), P10_VERSION, true );

		$s           = p10_chat_settings();
		$suggestions = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $s['suggestions'] ) ) ) );
		$agent_ready = ( 'local' !== $s['provider'] && '' !== p10_chat_resolve_api_key() );

		wp_localize_script(
			'p10-chat',
			'p10Chat',
			array(
				'root'           => esc_url_raw( rest_url( 'p10/v1/assistant' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'notice'         => P10_CHAT_NOTICE,
				'provider'       => $s['provider'],
				'agentReady'     => $agent_ready,
				'followup'       => (bool) $s['followup'],
				'autoPromptLead' => (bool) $s['auto_prompt_lead'],
				'phone'          => p10_phone_display(),
				'phoneHref'      => p10_phone_href(),
				'bookingUrl'     => p10_utility_url( 'booking' ),
				'directoryUrl'   => p10_directory_url(),
				'childSiteName'  => (string) p10_option( 'child_site_name', 'Hollywood Elite Escorts' ),
				'childSiteUrl'   => (string) p10_option( 'child_site_url', 'https://hollywoodseliteescorts.com/' ),
				'welcome'        => $s['welcome'],
				'suggestions'    => $suggestions,
				'i18n'           => array(
					'you'       => __( 'You', 'perfect10' ),
					'assistant' => __( 'Perfect 10 Concierge', 'perfect10' ),
					'typing'    => __( 'Concierge is scanning website…', 'perfect10' ),
					'offline'   => __( 'You appear to be offline. Reconnect and try again.', 'perfect10' ),
					'error'     => __( 'Something went wrong. Please try again or call our 24/7 desk.', 'perfect10' ),
				),
			)
		);
	}
);

/**
 * REST permission check with reverse-proxy host compatibility and rate limiting.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function p10_chat_permission( $request ) {
	$nonce = $request->get_header( 'x_wp_nonce' );
	if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'p10_nonce', __( 'Session refreshed. Please reload this page and try again.', 'perfect10' ), array( 'status' => 403 ) );
	}

	$origin = $request->get_header( 'origin' );
	if ( $origin ) {
		$origin_host = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$home_host   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$req_host    = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( preg_replace( '/:\d+$/', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) ) : '';
		if ( $origin_host && $origin_host !== $home_host && $origin_host !== $req_host ) {
			return new WP_Error( 'p10_origin', __( 'Origin not allowed.', 'perfect10' ), array( 'status' => 403 ) );
		}
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	$key = 'p10_chat_' . hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	foreach ( array(
		$key              => 40,
		'p10_chat_global' => 240,
	) as $bucket => $limit ) {
		$counter = get_transient( $bucket );
		if ( ! is_array( $counter ) || $counter['start'] < time() - 60 ) {
			$counter = array(
				'start' => time(),
				'count' => 0,
			);
		}
		if ( $counter['count'] >= $limit ) {
			return new WP_Error( 'p10_rate', __( 'Too many requests. Please wait a moment.', 'perfect10' ), array( 'status' => 429 ) );
		}
		++$counter['count'];
		set_transient( $bucket, $counter, 60 );
	}

	return true;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'p10/v1',
			'/assistant',
			array(
				'methods'             => 'POST',
				'callback'            => 'p10_chat_answer',
				'permission_callback' => 'p10_chat_permission',
			)
		);
		register_rest_route(
			'p10/v1',
			'/assistant/followup',
			array(
				'methods'             => 'POST',
				'callback'            => 'p10_chat_followup',
				'permission_callback' => 'p10_chat_permission',
			)
		);
	}
);

add_filter(
	'rest_post_dispatch',
	function ( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/p10/v1/assistant' ) ) {
			$response->header( 'Cache-Control', 'no-store, private' );
		}
		return $response;
	},
	10,
	3
);

/**
 * Inline CSS variables for the assistant launcher and window.
 *
 * @return string
 */
function p10_chat_style_attr() {
	$s    = p10_chat_settings();
	$vars = array(
		'--p10-chat-w' => max( 340, min( 620, (int) $s['width'] ) ) . 'px',
		'--p10-chat-h' => max( 460, min( 900, (int) $s['height'] ) ) . 'px',
	);

	$map = array(
		'header_bg'        => '--p10-chat-header-bg',
		'header_text'      => '--p10-chat-header-text',
		'bubble_bg'        => '--p10-chat-bubble-bg',
		'bubble_text'      => '--p10-chat-bubble-text',
		'user_bubble_bg'   => '--p10-chat-user-bg',
		'user_bubble_text' => '--p10-chat-user-text',
		'input_bg'         => '--p10-chat-input-bg',
		'input_text'       => '--p10-chat-input-text',
		'input_border'     => '--p10-chat-input-border',
	);
	foreach ( $map as $key => $var ) {
		$color = sanitize_hex_color( (string) $s[ $key ] );
		if ( $color ) {
			$vars[ $var ] = $color;
		}
	}

	if ( '' !== (string) $s['radius'] ) {
		$vars['--p10-chat-radius'] = max( 0, min( 32, (int) $s['radius'] ) ) . 'px';
	}

	$shadows = array(
		'none'   => 'none',
		'soft'   => '0 6px 24px rgb(0 0 0 / 0.35)',
		'medium' => '0 18px 60px rgb(0 0 0 / 0.55)',
		'strong' => '0 28px 90px rgb(0 0 0 / 0.78)',
	);
	$vars['--p10-chat-shadow'] = isset( $shadows[ $s['shadow'] ] ) ? $shadows[ $s['shadow'] ] : $shadows['strong'];

	$font = (int) $s['font_size'];
	if ( $font > 0 ) {
		$vars['--p10-chat-font'] = 'calc(var(--p10-fluid-type, 1rem) + ' . $font . 'px)';
	}

	$css = '';
	foreach ( $vars as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}

	return $css;
}

/**
 * UI descriptor used by template-parts/concierge.php.
 *
 * @return array
 */
function p10_chat_ui() {
	$s = p10_chat_settings();

	return array(
		'title'       => $s['title'],
		'subtitle'    => $s['subtitle'],
		'welcome'     => $s['welcome'],
		'suggestions' => array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $s['suggestions'] ) ) ) ),
		'position'    => $s['position'],
		'icon'        => esc_url_raw( $s['icon'] ),
		'icon_svg'    => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 11a8 8 0 0 1-8 8H5l-4 3 2-7a8 8 0 1 1 17-4Z"/><path d="M8 10h8M8 14h5"/></svg>',
	);
}

/**
 * Clean HTML/shortcodes into single-line plain text.
 *
 * @param string $text Raw HTML.
 * @return string
 */
function p10_chat_plain( $text ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $text ) ), ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * Redact visitor PII before sending any query to an optional external LLM.
 *
 * @param string $text Raw text.
 * @return string
 */
function p10_chat_redact( $text ) {
	$text = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', (string) $text );
	return preg_replace( '/\+?\d[\d\s().-]{7,}\d/', '[phone]', $text );
}

/**
 * Dynamically scan the ENTIRE WordPress website for content relevant to $question:
 *   - Profiles (p10_profile + rates, attributes, services, cities, types)
 *   - Locations (p10_county & p10_city — all 211+ cities)
 *   - Profile types & nationalities (p10_category & p10_nationality)
 *   - Advertising packages (p10_package)
 *   - Editorial Journal / Blog posts (post)
 *   - Pages (page — Booking, Directory, FAQ, About, Privacy, Advertise)
 *   - Guest reviews (p10_review)
 *   - Sitewide concierge contact info & official child website (Hollywood Elite Escorts)
 *
 * @param string $question Visitor question.
 * @return array{sources: array, profiles: array, terms: array, packages: array, blogs: array, pages: array, intent: string}
 */
function p10_chat_scan_website( $question ) {
	$q_lower = strtolower( $question );
	$stop    = array( 'the', 'and', 'for', 'are', 'what', 'where', 'when', 'how', 'who', 'can', 'you', 'your', 'with', 'have', 'this', 'that', 'from', 'tell', 'about', 'please', 'want', 'need', 'looking', 'show', 'find', 'any', 'some' );
	$tokens  = array_values(
		array_filter(
			preg_split( '/[^a-z0-9+]+/i', $q_lower ),
			static function ( $w ) use ( $stop ) {
				return strlen( $w ) >= 3 && ! in_array( $w, $stop, true );
			}
		)
	);
	$tokens = array_slice( array_unique( $tokens ), 0, 12 );

	// Detect primary visitor intent.
	$intent = 'general';
	if ( preg_match( '/\b(book|booking|reserve|reservation|callback|call me|contact me|get in touch|appointment|schedule|hire|enquire|inquire)\b/i', $question ) ) {
		$intent = 'lead';
	} elseif ( preg_match( '/\b(hollywood elite|sister|child site|child website|partner site|network)\b/i', $question ) ) {
		$intent = 'sister';
	} elseif ( preg_match( '/\b(package|packages|advertise|advertising|listing|join|register|apply)\b/i', $question ) ) {
		$intent = 'packages';
	} elseif ( preg_match( '/\b(rate|rates|price|pricing|cost|hourly|overnight|dinner|fee)\b/i', $question ) ) {
		$intent = 'rates';
	} elseif ( preg_match( '/\b(blog|journal|article|articles|guide|guides|etiquette|read)\b/i', $question ) ) {
		$intent = 'blogs';
	} elseif ( preg_match( '/\b(phone|number|email|hours|open|reach|telephone)\b/i', $question ) ) {
		$intent = 'contact';
	} elseif ( preg_match( '/\b(city|cities|location|locations|area|areas|county|counties|where|cover|beverly|hollywood|monica|malibu|newport|irvine|laguna|pasadena|downtown)\b/i', $question ) ) {
		$intent = 'locations';
	}

	$sources       = array();
	$matched_terms = array();
	$term_hits     = array();

	// 1. Scan all Counties, Cities, Categories & Nationalities (including 0-post cities!).
	$all_terms = get_terms(
		array(
			'taxonomy'   => array( 'p10_city', 'p10_county', 'p10_category', 'p10_nationality' ),
			'hide_empty' => false,
			'number'     => 400,
		)
	);
	if ( ! is_wp_error( $all_terms ) ) {
		foreach ( $all_terms as $term ) {
			$hay   = strtolower( str_replace( '-', ' ', $term->name . ' ' . $term->slug ) );
			$score = 0;
			if ( false !== strpos( $q_lower, strtolower( $term->name ) ) ) {
				$score += 10;
			} else {
				foreach ( $tokens as $tk ) {
					if ( strlen( $tk ) >= 4 && false !== strpos( $hay, $tk ) ) {
						$score += 3;
					}
				}
			}
			if ( $score > 0 ) {
				$url = 'p10_city' === $term->taxonomy || 'p10_county' === $term->taxonomy ? p10_term_url( $term ) : get_term_link( $term );
				if ( ! is_wp_error( $url ) && $url ) {
					$desc = p10_chat_plain( $term->description );
					if ( ! $desc && function_exists( 'p10_editorial_term_blurb' ) ) {
						$desc = p10_editorial_term_blurb( $term );
					}
					$row = array(
						'id'    => 't' . $term->term_id,
						'badge' => 'p10_city' === $term->taxonomy ? __( 'Destination', 'perfect10' ) : ( 'p10_county' === $term->taxonomy ? __( 'County', 'perfect10' ) : __( 'Collection', 'perfect10' ) ),
						'title' => $term->name,
						'text'  => mb_substr( $desc, 0, 260 ),
						'url'   => $url,
						'score' => $score,
					);
					$term_hits[]                        = $row;
					$matched_terms[ $term->taxonomy ][] = $term->term_id;
				}
			}
		}
	}
	usort(
		$term_hits,
		static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		}
	);
	$term_hits = array_slice( $term_hits, 0, 4 );

	// 2. Scan Companion Profiles (via keyword matcher + taxonomy hits + fallback featured).
	$profile_ids = array();
	if ( function_exists( 'p10_matching_profile_ids_for_keyword' ) ) {
		foreach ( $tokens as $tk ) {
			$profile_ids = array_merge( $profile_ids, p10_matching_profile_ids_for_keyword( $tk ) );
		}
	}
	if ( ! empty( $matched_terms ) ) {
		$tq = array( 'relation' => 'OR' );
		foreach ( $matched_terms as $tax => $tids ) {
			$tq[] = array(
				'taxonomy' => $tax,
				'field'    => 'term_id',
				'terms'    => $tids,
			);
		}
		$by_tax = get_posts(
			array(
				'post_type'      => 'p10_profile',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'tax_query'      => $tq, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$profile_ids = array_merge( $profile_ids, $by_tax );
	}
	$profile_ids = array_values( array_unique( array_map( 'intval', $profile_ids ) ) );

	// If visitor asks broadly about companions, rates, availability, or VIPs without a specific token match, pull featured profiles!
	if ( empty( $profile_ids ) && ( in_array( $intent, array( 'rates', 'locations', 'general', 'lead' ), true ) || preg_match( '/\b(companion|companions|escort|escorts|model|models|vip|premium|available|who)\b/i', $question ) ) ) {
		$featured = p10_get_featured_profiles( 4 );
		$profile_ids = wp_list_pluck( $featured, 'ID' );
	}

	$profile_rows = array();
	foreach ( array_slice( $profile_ids, 0, 5 ) as $pid ) {
		$p = get_post( $pid );
		if ( ! $p || 'publish' !== $p->post_status ) {
			continue;
		}
		$loc        = p10_profile_location_line( $p );
		$rate       = p10_meta( $p, 'rate' );
		$dinner     = p10_meta( $p, 'rate_dinner' );
		$overnight  = p10_meta( $p, 'rate_overnight' );
		$age        = p10_meta( $p, 'age' );
		$hair       = p10_meta( $p, 'hair' );
		$langs      = p10_meta( $p, 'languages' );
		$services   = p10_meta( $p, 'services' );
		$is_premium = (bool) p10_meta( $p, 'premium' );
		$avail      = p10_profile_availability( $p );

		$summary_bits = array();
		if ( $loc ) {
			$summary_bits[] = $loc;
		}
		if ( $age ) {
			$summary_bits[] = 'Age ' . $age;
		}
		if ( $hair ) {
			$summary_bits[] = $hair;
		}
		if ( $rate ) {
			$rate_str = p10_format_money( $rate ) . '/hr';
			if ( $dinner ) {
				$rate_str .= ' · Dinner ' . p10_format_money( $dinner );
			}
			if ( $overnight ) {
				$rate_str .= ' · Overnight ' . p10_format_money( $overnight );
			}
			$summary_bits[] = $rate_str;
		}
		if ( $langs ) {
			$summary_bits[] = 'Speaks ' . $langs;
		}
		$summary_bits[] = $avail['label'];

		$profile_rows[] = array(
			'id'      => 'p' . $p->ID,
			'post_id' => $p->ID,
			'badge'   => $is_premium ? __( 'Premium Companion', 'perfect10' ) : __( 'Verified Companion', 'perfect10' ),
			'title'   => get_the_title( $p ),
			'loc'     => $loc,
			'rate'    => $rate ? p10_format_money( $rate ) . '/hr' : __( 'On request', 'perfect10' ),
			'text'    => implode( ' · ', $summary_bits ) . ( $services ? ' — ' . $services : '' ),
			'url'     => get_permalink( $p ),
			'image'   => p10_profile_image( $p, 'p10-thumb' ),
			'premium' => $is_premium,
		);
	}

	// 3. Scan Packages (`p10_package`).
	$package_rows = array();
	foreach ( p10_get_packages() as $pkg ) {
		$price    = p10_get_meta( $pkg->ID, 'price', '' );
		$period   = (string) p10_get_meta( $pkg->ID, 'period', 'per month' );
		$features = str_replace( array( "\r\n", "\n" ), ', ', (string) p10_get_meta( $pkg->ID, 'features', '' ) );
		$package_rows[] = array(
			'id'    => 'pkg' . $pkg->ID,
			'badge' => __( 'Placement Package', 'perfect10' ),
			'title' => get_the_title( $pkg ) . ( '' !== $price ? ' (' . p10_format_money( $price ) . ' ' . $period . ')' : '' ),
			'text'  => mb_substr( $features, 0, 240 ),
			'url'   => p10_utility_url( 'packages' ),
		);
	}

	// 4. Scan Editorial Blog Articles (`post`).
	$blog_rows = array();
	$blogs     = p10_get_blog_posts( 6 );
	foreach ( $blogs as $b ) {
		if ( 'hello-world' === $b->post_name ) {
			continue;
		}
		$hay     = strtolower( $b->post_title . ' ' . p10_chat_plain( $b->post_content ) );
		$matched = ( 'blogs' === $intent );
		if ( ! $matched ) {
			foreach ( $tokens as $tk ) {
				if ( false !== strpos( $hay, $tk ) ) {
					$matched = true;
					break;
				}
			}
		}
		if ( $matched ) {
			$blog_rows[] = array(
				'id'    => 'b' . $b->ID,
				'badge' => __( 'Journal Article', 'perfect10' ),
				'title' => get_the_title( $b ),
				'text'  => mb_substr( has_excerpt( $b ) ? get_the_excerpt( $b ) : p10_chat_plain( $b->post_content ), 0, 220 ),
				'url'   => get_permalink( $b ),
			);
		}
	}

	// 5. Scan Published Pages (`page`).
	$page_rows = array();
	$pages     = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 20,
			'no_found_rows'  => true,
		)
	);
	foreach ( $pages as $pg ) {
		$plain = p10_chat_plain( $pg->post_content );
		$hay   = strtolower( $pg->post_title . ' ' . $plain );
		$hit   = false;
		foreach ( $tokens as $tk ) {
			if ( false !== strpos( $hay, $tk ) ) {
				$hit = true;
				break;
			}
		}
		if ( $hit ) {
			$page_rows[] = array(
				'id'    => 'pg' . $pg->ID,
				'badge' => __( 'Guide Page', 'perfect10' ),
				'title' => get_the_title( $pg ),
				'text'  => mb_substr( $plain ? $plain : get_the_excerpt( $pg ), 0, 240 ),
				'url'   => get_permalink( $pg ),
			);
		}
	}

	// Assemble prioritized source cards for the chat response.
	if ( 'packages' === $intent ) {
		$sources = array_merge( $package_rows, $page_rows, $profile_rows );
	} elseif ( 'blogs' === $intent ) {
		$sources = array_merge( $blog_rows, $term_hits, $profile_rows );
	} elseif ( 'locations' === $intent ) {
		$sources = array_merge( $term_hits, $profile_rows, $blog_rows );
	} else {
		$sources = array_merge( $profile_rows, $term_hits, $blog_rows, $page_rows );
	}

	if ( 'sister' === $intent ) {
		array_unshift(
			$sources,
			array(
				'id'       => 'sister-site',
				'badge'    => __( 'Official Child Website', 'perfect10' ),
				'title'    => (string) p10_option( 'child_site_name', 'Hollywood Elite Escorts' ),
				'text'     => (string) p10_option( 'child_site_desc', '' ),
				'url'      => (string) p10_option( 'child_site_url', 'https://hollywoodseliteescorts.com/' ),
				'external' => true,
			)
		);
	}

	return array(
		'intent'   => $intent,
		'sources'  => array_slice( $sources, 0, 5 ),
		'profiles' => $profile_rows,
		'terms'    => $term_hits,
		'packages' => $package_rows,
		'blogs'    => $blog_rows,
		'pages'    => $page_rows,
	);
}

/**
 * Compose a natural, accurate concierge answer from the live website scan
 * WITHOUT requiring any external API key.
 *
 * @param string $question Visitor question.
 * @param array  $scan     Result of p10_chat_scan_website().
 * @return string
 */
function p10_chat_compose_local_answer( $question, $scan ) {
	$phone        = p10_phone_display();
	$email        = (string) p10_option( 'email', 'concierge@myperfect10.com' );
	$hours        = (string) p10_option( 'hours', '24 hours · 7 days a week' );
	$child_name   = (string) p10_option( 'child_site_name', 'Hollywood Elite Escorts' );
	$total_cities = count( p10_get_cities() );
	$total_profs  = p10_count_profiles();
	$intent       = $scan['intent'];

	if ( 'sister' === $intent ) {
		return sprintf(
			'%s is Perfect 10’s official child website and sister directory, curating VIP companions across Hollywood Hills, the Sunset Strip, West Hollywood, and Beverly Hills. You can visit %s directly via the link below, or share your name, mobile number, and email here and our concierge desk will get in touch soon.',
			$child_name,
			$child_name
		);
	}

	if ( 'contact' === $intent ) {
		return sprintf(
			'Our private concierge desk is open %s. You can reach us directly at %s (please call from an unblocked number) or by email at %s. If you would like us to call or email you discreetly, click “Leave Mobile & Email” below—we will get in touch with you very soon!',
			$hours,
			$phone ? $phone : '310-600-8360',
			$email
		);
	}

	if ( 'lead' === $intent ) {
		return sprintf(
			'We would be delighted to arrange your private introduction! You can call our 24/7 concierge line at %s for immediate same-evening coordination, or share your Name, Mobile Number, and Email right here in chat and our specialist will get in touch with you very soon.',
			$phone ? $phone : '310-600-8360'
		);
	}

	if ( 'packages' === $intent && ! empty( $scan['packages'] ) ) {
		$names = array();
		foreach ( $scan['packages'] as $pkg ) {
			$names[] = $pkg['title'];
		}
		return sprintf(
			'Independent adult companions (18+) can apply to advertise across Perfect 10 through %d placement tiers: %s. Every tier includes verified profile badges, multi-photo gallery hosting, and city/type indexing. Would you like to leave your mobile number and email so our onboarding desk can get in touch soon?',
			count( $names ),
			implode( ', ', $names )
		);
	}

	if ( 'rates' === $intent ) {
		$examples = array();
		foreach ( array_slice( $scan['profiles'], 0, 3 ) as $pr ) {
			$examples[] = $pr['title'] . ' (' . $pr['rate'] . ')';
		}
		$sample_line = $examples ? ' Current published examples include ' . implode( ', ', $examples ) . '.' : '';
		return sprintf(
			'Each independent companion on Perfect 10 sets her own hourly, dinner-date, and overnight honorarium—typically ranging from $650/hr to $1,000/hr for standard meetings and $1,900–$6,000 for dinner or overnight engagements.%s Share your name, mobile number, and email below if you would like tailored recommendations and we will get in touch soon.',
			$sample_line
		);
	}

	if ( 'locations' === $intent ) {
		if ( ! empty( $scan['terms'] ) ) {
			$top_loc = $scan['terms'][0]['title'];
			$p_names = wp_list_pluck( array_slice( $scan['profiles'], 0, 3 ), 'title' );
			$who     = $p_names ? ' Featured companions serving this area include ' . implode( ', ', $p_names ) . '.' : '';
			return sprintf(
				'Yes—Perfect 10 covers %s along with %d+ cities and districts across Los Angeles County and Orange County for discreet five-star hotel outcalls, dinner dates, and private introductions.%s Click any link below or share your mobile number & email so our concierge can get in touch soon.',
				$top_loc,
				max( 211, $total_cities ),
				$who
			);
		}
		return sprintf(
			'Perfect 10 covers %d+ cities and districts across Los Angeles County (Beverly Hills, West Hollywood, Santa Monica, Malibu, Downtown LA, Bel Air) and Orange County (Newport Beach, Laguna Beach, Irvine, Costa Mesa), plus our sister site %s. Tell me which neighborhood you are visiting—or leave your mobile & email and we will get in touch soon!',
			max( 211, $total_cities ),
			$child_name
		);
	}

	if ( 'blogs' === $intent && ! empty( $scan['blogs'] ) ) {
		$titles = wp_list_pluck( array_slice( $scan['blogs'], 0, 3 ), 'title' );
		return sprintf(
			'From the Perfect 10 Editorial Journal, here are published guides matching your interest: %s. Select any article below to read the full 300–400 word guide, or ask me about any companion or city!',
			implode( '; ', $titles )
		);
	}

	if ( ! empty( $scan['profiles'] ) ) {
		$summaries = array();
		foreach ( array_slice( $scan['profiles'], 0, 3 ) as $pr ) {
			$summaries[] = $pr['title'] . ( $pr['loc'] ? ' (' . $pr['loc'] . ', ' . $pr['rate'] . ')' : ' (' . $pr['rate'] . ')' );
		}
		return sprintf(
			'Scanning our live Perfect 10 directory (%d verified portfolios across %d+ Southern California locations), I found matching companions including %s. Explore their full multi-photo profiles below, or leave your Name, Mobile Number & Email and our concierge team will get in touch with you very soon.',
			max( 12, $total_profs ),
			max( 211, $total_cities ),
			implode( '; ', $summaries )
		);
	}

	if ( ! empty( $scan['sources'] ) ) {
		return 'Here is what I found by scanning the published pages and guides on Perfect 10. Open any link below for full details, or share your mobile number and email so our concierge desk can get in touch with you soon.';
	}

	return sprintf(
		'Perfect 10 features %d verified independent companions across %d+ cities in Los Angeles County and Orange County (plus our child website %s), with 24/7 coordination at %s. Tell me your preferred city, companion style, or evening plans—or tap “Leave Mobile & Email” and we will get in touch with you very soon!',
		max( 12, $total_profs ),
		max( 211, $total_cities ),
		$child_name,
		$phone ? $phone : '310-600-8360'
	);
}

/**
 * Optional Agent API synthesis grounded strictly in our live website scan.
 * Falls back cleanly to null if unconfigured or unreachable.
 *
 * @param string $question Visitor question.
 * @param array  $scan     Scan payload.
 * @param array  $settings Chat settings.
 * @return string|null
 */
function p10_chat_call_agent_api( $question, $scan, $settings ) {
	$api_key  = p10_chat_resolve_api_key();
	$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'local';
	if ( 'local' === $provider || '' === $api_key ) {
		return null;
	}

	$context_items = array();
	foreach ( $scan['sources'] as $src ) {
		$context_items[] = $src['badge'] . ': ' . $src['title'] . ' — ' . $src['text'];
	}
	$site_summary = sprintf(
		"Brand: %s | Phone: %s | Email: %s | Child Website: %s (%s)\nScanned Website Matches:\n- %s",
		p10_option( 'brand_name', 'Perfect 10' ),
		p10_phone_display(),
		p10_option( 'email', 'concierge@myperfect10.com' ),
		p10_option( 'child_site_name', 'Hollywood Elite Escorts' ),
		p10_option( 'child_site_url', 'https://hollywoodseliteescorts.com/' ),
		implode( "\n- ", $context_items )
	);

	$system_prompt = 'You are the discreet, polished 24/7 Concierge for Perfect 10 (Los Angeles & Orange County luxury companion referral directory, partnered with child website Hollywood Elite Escorts). Answer concisely (2–4 sentences) using ONLY the provided Scanned Website Matches. Politely invite the guest to share their name, mobile number, and email so our concierge team can get in touch soon.';

	if ( 'anthropic' === $provider ) {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 15,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $settings['model'] ? $settings['model'] : 'claude-3-5-haiku-latest',
						'max_tokens' => 260,
						'system'     => $system_prompt,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => "Website Data:\n" . $site_summary . "\n\nVisitor Question: " . p10_chat_redact( $question ),
							),
						),
					)
				),
			)
		);
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['content'][0]['text'] ) ) {
				return trim( (string) $body['content'][0]['text'] );
			}
		}
		return null;
	}

	// OpenAI or Custom OpenAI-Compatible Agent Endpoint.
	$endpoint = 'custom_agent' === $provider && ! empty( $settings['agent_endpoint'] )
		? $settings['agent_endpoint']
		: 'https://api.openai.com/v1/chat/completions';

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'model'      => $settings['model'] ? $settings['model'] : 'gpt-4o-mini',
					'max_tokens' => 260,
					'messages'   => array(
						array(
							'role'    => 'system',
							'content' => $system_prompt,
						),
						array(
							'role'    => 'user',
							'content' => "Website Data:\n" . $site_summary . "\n\nVisitor Question: " . p10_chat_redact( $question ),
						),
					),
				)
			),
		)
	);

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['choices'][0]['message']['content'] ) ) {
			return trim( (string) $body['choices'][0]['message']['content'] );
		}
	}

	return null;
}

/**
 * REST callback: POST /wp-json/p10/v1/assistant
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function p10_chat_answer( $request ) {
	$q = $request->get_param( 'message' );
	if ( ! is_string( $q ) || mb_strlen( trim( $q ) ) < 1 || mb_strlen( $q ) > 600 ) {
		return new WP_Error( 'p10_input', __( 'Please enter a message between 1 and 600 characters.', 'perfect10' ), array( 'status' => 400 ) );
	}

	$q        = sanitize_textarea_field( $q );
	$settings = p10_chat_settings();
	$scan     = p10_chat_scan_website( $q );

	$provider_status = 'local_site_scanner';
	$answer          = null;

	if ( 'local' !== $settings['provider'] ) {
		$agent_reply = p10_chat_call_agent_api( $q, $scan, $settings );
		if ( $agent_reply ) {
			$answer          = $agent_reply;
			$provider_status = 'agent_api_active';
		} else {
			$provider_status = 'agent_fallback_local_scanner';
		}
	}

	if ( null === $answer ) {
		$answer = p10_chat_compose_local_answer( $q, $scan );
	}

	return rest_ensure_response(
		array(
			'answer'          => $answer,
			'intent'          => $scan['intent'],
			'sources'         => $scan['sources'],
			'directory'       => p10_directory_url(),
			'prompt_lead'     => (bool) ( $settings['followup'] && ( 'lead' === $scan['intent'] || $settings['auto_prompt_lead'] ) ),
			'provider_status' => $provider_status,
		)
	);
}

/**
 * REST callback: POST /wp-json/p10/v1/assistant/followup
 * Saves customer lead (Name, Mobile Number, Email, Location/Companion, Inquiry)
 * into WP Admin -> Enquiries (`p10_enquiry`) without requiring external APIs.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function p10_chat_followup( $request ) {
	$s = p10_chat_settings();
	if ( empty( $s['followup'] ) ) {
		return new WP_Error( 'p10_disabled', __( 'Lead capture is currently disabled.', 'perfect10' ), array( 'status' => 403 ) );
	}

	$values = array();
	foreach ( array(
		'name'     => 100,
		'email'    => 160,
		'phone'    => 50,
		'message'  => 800,
		'location' => 140,
	) as $key => $max ) {
		$raw            = $request->get_param( $key );
		$str            = is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';
		$values[ $key ] = mb_substr( trim( $str ), 0, $max );
	}

	if ( '' === $values['name'] ) {
		return new WP_Error( 'p10_input', __( 'Please share your name so our concierge knows how to address you.', 'perfect10' ), array( 'status' => 400 ) );
	}
	if ( '' === $values['phone'] && '' === $values['email'] ) {
		return new WP_Error( 'p10_input', __( 'Please provide your mobile number or email address so we can get in touch.', 'perfect10' ), array( 'status' => 400 ) );
	}
	if ( '' !== $values['email'] && ! is_email( $values['email'] ) ) {
		return new WP_Error( 'p10_input', __( 'Please enter a valid email address (for example, name@domain.com).', 'perfect10' ), array( 'status' => 400 ) );
	}
	if ( '' !== $values['phone'] && ! preg_match( '/^\+?[0-9 ().\-]{7,40}$/', $values['phone'] ) ) {
		return new WP_Error( 'p10_input', __( 'Please enter a valid mobile telephone number (at least 7 digits).', 'perfect10' ), array( 'status' => 400 ) );
	}

	if ( '' === $values['message'] ) {
		$values['message'] = __( 'Concierge callback requested via Website Assistant.', 'perfect10' );
	}

	$profile_raw       = $request->get_param( 'profile' );
	$profile           = is_scalar( $profile_raw ) ? absint( $profile_raw ) : 0;
	$values['profile'] = $profile;

	$id = wp_insert_post(
		array(
			'post_type'    => 'p10_enquiry',
			'post_status'  => 'private',
			'post_title'   => sprintf( 'Concierge Chat Lead — %s (%s)', $values['name'], $values['phone'] ? $values['phone'] : $values['email'] ),
			'post_content' => $values['message'],
			'meta_input'   => array(
				'p10_enquiry_type'    => 'chatbot',
				'p10_enquiry_status'  => 'new',
				'p10_enquiry_profile' => $profile,
				'p10_enquiry_values'  => wp_json_encode( $values ),
				'_p10_chat_email'     => $values['email'],
				'_p10_chat_phone'     => $values['phone'],
				'_p10_chat_consent'   => array(
					'consent'        => true,
					'timestamp'      => gmdate( 'c' ),
					'notice_version' => P10_CHAT_NOTICE,
				),
			),
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'p10_save', __( 'Your request could not be saved right now. Please call our 24/7 line directly.', 'perfect10' ), array( 'status' => 500 ) );
	}

	$accepted = @wp_mail( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		get_option( 'admin_email' ),
		sprintf( '[Perfect 10] New Chatbot Lead — %s', $values['name'] ),
		'Review the new concierge lead in WordPress Admin: ' . admin_url( 'post.php?post=' . $id . '&action=edit' )
	);
	update_post_meta( $id, '_p10_chat_notification', $accepted ? 'sent' : 'saved_in_admin' );

	return rest_ensure_response(
		array(
			'ok'           => true,
			'lead_id'      => $id,
			'message'      => sprintf(
				/* translators: %s: visitor name. */
				__( 'Thank you, %s! Your contact details have been privately recorded by our concierge desk — we will get in touch with you very soon.', 'perfect10' ),
				$values['name']
			),
			'notification' => $accepted ? 'sent' : 'saved_in_admin',
		)
	);
}

// Admin meta box on p10_enquiry for chatbot leads.
add_action(
	'add_meta_boxes_p10_enquiry',
	function () {
		add_meta_box(
			'p10-chat-consent',
			__( 'Website Assistant Lead Details', 'perfect10' ),
			function ( $post ) {
				if ( ! current_user_can( 'manage_options' ) || 'chatbot' !== get_post_meta( $post->ID, 'p10_enquiry_type', true ) ) {
					return;
				}
				echo '<pre style="white-space:pre-wrap">' . esc_html(
					wp_json_encode(
						array(
							'lead'         => p10_enquiry_values( $post->ID ),
							'consent'      => get_post_meta( $post->ID, '_p10_chat_consent', true ),
							'notification' => get_post_meta( $post->ID, '_p10_chat_notification', true ),
						),
						JSON_PRETTY_PRINT
					)
				) . '</pre>';
			},
			'p10_enquiry',
			'normal'
		);
	}
);

add_action(
	'restrict_manage_posts',
	function ( $type ) {
		if ( 'p10_enquiry' !== $type || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$selected = isset( $_GET['p10_status'] ) ? sanitize_key( $_GET['p10_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="p10_status"><option value="">All enquiry statuses</option>';
		foreach ( p10_enquiry_statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $selected, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
);

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'p10_enquiry' !== $query->get( 'post_type' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['p10_status'] ) && isset( p10_enquiry_statuses()[ sanitize_key( $_GET['p10_status'] ) ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$query->set( 'meta_key', 'p10_enquiry_status' );
			$query->set( 'meta_value', sanitize_key( $_GET['p10_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
);

/** WordPress Privacy Export/Erasure integration for chatbot leads. */
function p10_chat_privacy_ids( $email, $page ) {
	return get_posts(
		array(
			'post_type'      => 'p10_enquiry',
			'post_status'    => 'private',
			'fields'         => 'ids',
			'posts_per_page' => 50,
			'paged'          => $page,
			'meta_key'       => '_p10_chat_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
}

add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['p10-chat'] = array(
			'exporter_friendly_name' => __( 'Website Assistant Leads', 'perfect10' ),
			'callback'               => function ( $email, $page = 1 ) {
				$ids  = p10_chat_privacy_ids( $email, $page );
				$data = array();
				foreach ( $ids as $id ) {
					$data[] = array(
						'group_id'    => 'p10-chat',
						'group_label' => __( 'Website Assistant Leads', 'perfect10' ),
						'item_id'     => 'p10-chat-' . $id,
						'data'        => array(
							array(
								'name'  => 'Lead',
								'value' => wp_json_encode( p10_enquiry_values( $id ) ),
							),
						),
					);
				}
				return array(
					'data' => $data,
					'done' => count( $ids ) < 50,
				);
			},
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['p10-chat'] = array(
			'eraser_friendly_name' => __( 'Website Assistant Leads', 'perfect10' ),
			'callback'             => function ( $email ) {
				$ids     = p10_chat_privacy_ids( $email, 1 );
				$removed = false;
				foreach ( $ids as $id ) {
					$removed = (bool) wp_delete_post( $id, true ) || $removed;
				}
				return array(
					'items_removed'  => $removed,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => count( $ids ) < 50,
				);
			},
		);
		return $erasers;
	}
);
