<?php
/**
 * Floating 24/7 Perfect 10 Concierge Chatbot & Lead Capture Window.
 *
 * Works 100% without any API key via live website scanning, captures visitor
 * Name, Mobile Number, Email & Preferences, and supports optional Agent API
 * enhancement.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) || ! p10_chat_is_visible() ) {
	return;
}

$chat = p10_chat_ui();
$pos  = 'left' === $chat['position'] ? ' p10-chat--left' : ' p10-chat--right';
?>
<div class="p10-concierge p10-chat<?php echo esc_attr( $pos ); ?>" data-p10-chat style="<?php echo esc_attr( p10_chat_style_attr() ); ?>">

	<button type="button" class="p10-concierge__btn p10-chat__launcher" data-chat-open aria-haspopup="dialog" aria-controls="p10-chat-dialog" aria-expanded="false" aria-label="<?php echo esc_attr( $chat['title'] ); ?>">
		<span class="p10-chat__launcher-pulse" aria-hidden="true"></span>
		<span class="p10-chat__launcher-icon" aria-hidden="true">
			<?php
			if ( $chat['icon'] ) {
				echo '<img src="' . esc_url( $chat['icon'] ) . '" alt="" width="28" height="28" loading="lazy" decoding="async">';
			} else {
				echo $chat['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed inline SVG.
			}
			?>
		</span>
		<span class="p10-chat__launcher-label"><?php esc_html_e( 'Concierge', 'perfect10' ); ?></span>
	</button>

	<dialog id="p10-chat-dialog" class="p10-chat-dialog" aria-labelledby="p10-chat-title" aria-describedby="p10-chat-subtitle">
		<header class="p10-chat__header">
			<div class="p10-chat__identity">
				<span class="p10-chat__mark" aria-hidden="true">
					<?php echo p10_premium_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 id="p10-chat-title" class="p10-chat__title"><?php echo esc_html( $chat['title'] ); ?></h2>
					<?php if ( $chat['subtitle'] ) : ?>
						<p id="p10-chat-subtitle" class="p10-chat__subtitle"><?php echo esc_html( $chat['subtitle'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="p10-chat__header-actions">
				<button type="button" class="p10-chat__icon-btn" data-chat-minimize aria-label="<?php esc_attr_e( 'Minimize assistant', 'perfect10' ); ?>" aria-expanded="true" aria-controls="p10-chat-panel">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14"/></svg>
				</button>
				<button type="button" class="p10-chat__icon-btn" data-chat-close aria-label="<?php esc_attr_e( 'Close assistant', 'perfect10' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
			</div>
		</header>

		<div class="p10-chat__panel" id="p10-chat-panel" data-chat-panel>
			<div class="p10-chat-bar">
				<span class="p10-chat-provider" data-chat-provider></span>
				<?php if ( p10_phone_display() ) : ?>
					<a class="p10-chat-bar__phone" href="<?php echo esc_url( p10_phone_href() ); ?>">
						<?php echo esc_html( p10_phone_display() ); ?>
					</a>
				<?php endif; ?>
			</div>

			<div class="p10-chat-log" role="log" aria-live="polite" aria-relevant="additions" tabindex="0" aria-label="<?php esc_attr_e( 'Conversation', 'perfect10' ); ?>" data-chat-log></div>

			<div class="p10-chat-suggestions" data-chat-suggestions aria-label="<?php esc_attr_e( 'Suggested questions', 'perfect10' ); ?>">
				<?php foreach ( $chat['suggestions'] as $suggestion ) : ?>
					<button type="button" class="p10-chat__suggestion" data-chat-suggestion><?php echo esc_html( $suggestion ); ?></button>
				<?php endforeach; ?>
			</div>

			<p class="p10-chat__status" role="status" data-chat-status></p>

			<form class="p10-chat__form" data-chat-form>
				<label class="screen-reader-text" for="p10-chat-input"><?php esc_html_e( 'Your message', 'perfect10' ); ?></label>
				<div class="p10-chat-input">
					<input id="p10-chat-input" name="message" maxlength="600" autocomplete="off" required placeholder="<?php esc_attr_e( 'Ask about companions, cities, rates, or request a callback…', 'perfect10' ); ?>">
					<button type="submit" class="p10-chat__send">
						<span><?php esc_html_e( 'Send', 'perfect10' ); ?></span>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 12 16-8-6 8 6 8-16-8Z"/></svg>
					</button>
				</div>
			</form>

			<div class="p10-chat-tools" aria-label="<?php esc_attr_e( 'Assistant controls', 'perfect10' ); ?>">
				<button type="button" class="p10-chat-tools__primary" data-chat-follow>
					<?php esc_html_e( 'Leave Mobile & Email', 'perfect10' ); ?>
				</button>
				<button type="button" data-chat-mic hidden><?php esc_html_e( 'Voice Input', 'perfect10' ); ?></button>
				<button type="button" data-chat-mute aria-pressed="true"><?php esc_html_e( 'Audio Off', 'perfect10' ); ?></button>
				<button type="button" data-chat-reset><?php esc_html_e( 'Reset Chat', 'perfect10' ); ?></button>
			</div>
		</div>
	</dialog>
</div>
