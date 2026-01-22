<?php

namespace BBHubspotForms\Forms;

use BBHubspotForms\Settings;

final class CPT {
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_hubspot_form', array( __CLASS__, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'manage_hubspot_form_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
		add_action( 'manage_hubspot_form_posts_custom_column', array( __CLASS__, 'render_admin_columns' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function register_cpt(): void {
		register_post_type(
			'hubspot_form',
			array(
				'labels' => array(
					'name'          => __( 'HubSpot Forms', 'bb-hubspot-forms' ),
					'singular_name' => __( 'HubSpot Form', 'bb-hubspot-forms' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'capability_type'     => 'post',
				'has_archive'         => false,
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-feedback',
			)
		);
	}

	public static function register_meta(): void {
		$meta_args = array(
			'type'              => 'object',
			'single'            => true,
			'show_in_rest'      => array(
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'fields' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
					),
				),
			),
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
			'sanitize_callback' => array( __CLASS__, 'sanitize_meta_object' ),
		);

		register_post_meta( 'hubspot_form', 'hubspot_form_schema', $meta_args );
		register_post_meta( 'hubspot_form', 'hubspot_form_settings', $meta_args );
		register_post_meta( 'hubspot_form', 'hubspot_form_hidden_fields', $meta_args );
		register_post_meta( 'hubspot_form', 'hubspot_form_progressive_rules', $meta_args );
		register_post_meta( 'hubspot_form', 'hubspot_form_consent', $meta_args );
		register_post_meta( 'hubspot_form', 'hubspot_form_utm', $meta_args );

		register_post_meta(
			'hubspot_form',
			'hubspot_form_version',
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'default'       => 'v1',
			)
		);

		register_post_meta(
			'hubspot_form',
			'hubspot_form_token_ttl',
			array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'default'       => 600,
			)
		);
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'bb_hubspot_forms_form_id',
			__( 'HubSpot Form ID', 'bb-hubspot-forms' ),
			array( __CLASS__, 'render_form_id_meta_box' ),
			'hubspot_form',
			'side',
			'default'
		);
	}

	public static function render_form_id_meta_box( \WP_Post $post ): void {
		$settings = get_post_meta( $post->ID, 'hubspot_form_settings', true );
		$current  = is_array( $settings ) && isset( $settings['hubspot_form_id'] ) ? $settings['hubspot_form_id'] : '';
		$form_ids = Settings::get_form_ids();
		$settings_url = admin_url( 'options-general.php?page=bb-hubspot-forms-settings' );

		wp_nonce_field( 'bb_hubspot_forms_form_id', 'bb_hubspot_forms_form_id_nonce' );
		echo '<p>' . esc_html__( 'Select a HubSpot Form ID from the global list.', 'bb-hubspot-forms' ) . '</p>';

		if ( empty( $form_ids ) ) {
			echo '<p>' . esc_html__( 'No Form IDs found. Add them in the plugin settings.', 'bb-hubspot-forms' ) . '</p>';
			echo '<p><a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Go to Settings', 'bb-hubspot-forms' ) . '</a></p>';
			return;
		}

		echo '<select name="bb_hubspot_forms_form_id" class="widefat">';
		echo '<option value="">' . esc_html__( 'Select a form ID', 'bb-hubspot-forms' ) . '</option>';
		foreach ( $form_ids as $entry ) {
			$id    = isset( $entry['id'] ) ? $entry['id'] : '';
			$label = isset( $entry['label'] ) ? $entry['label'] : $id;
			if ( $id === '' ) {
				continue;
			}
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $id ),
				selected( $current, $id, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public static function save_meta_boxes( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['bb_hubspot_forms_form_id_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bb_hubspot_forms_form_id_nonce'] ) ), 'bb_hubspot_forms_form_id' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( $post->post_type !== 'hubspot_form' ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$selected_id = isset( $_POST['bb_hubspot_forms_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['bb_hubspot_forms_form_id'] ) ) : '';
		$settings    = get_post_meta( $post_id, 'hubspot_form_settings', true );
		$settings    = is_array( $settings ) ? $settings : array();
		$settings['hubspot_form_id'] = $selected_id;

		update_post_meta( $post_id, 'hubspot_form_settings', $settings );
	}

	public static function add_admin_columns( array $columns ): array {
		$columns['hubspot_form_id']  = __( 'HubSpot Form ID', 'bb-hubspot-forms' );
		$columns['hubspot_shortcode'] = __( 'Shortcode', 'bb-hubspot-forms' );
		$columns['captcha_required'] = __( 'CAPTCHA', 'bb-hubspot-forms' );
		return $columns;
	}

	public static function render_admin_columns( string $column, int $post_id ): void {
		if ( $column === 'hubspot_form_id' ) {
			$settings = get_post_meta( $post_id, 'hubspot_form_settings', true );
			$form_id  = is_array( $settings ) && isset( $settings['hubspot_form_id'] ) ? $settings['hubspot_form_id'] : '';
			echo esc_html( $form_id ? $form_id : '—' );
			return;
		}
		if ( $column === 'hubspot_shortcode' ) {
			$shortcode = sprintf( '[hubspot_form id="%d"]', $post_id );
			printf(
				'<code class="bb-hubspot-forms-shortcode">%s</code> <button type="button" class="button button-small bb-hubspot-forms-copy-shortcode" data-shortcode="%s">%s</button>',
				esc_html( $shortcode ),
				esc_attr( $shortcode ),
				esc_html__( 'Copy', 'bb-hubspot-forms' )
			);
			return;
		}
		if ( $column === 'captcha_required' ) {
			$settings = get_post_meta( $post_id, 'hubspot_form_settings', true );
			$required = is_array( $settings ) && ! empty( $settings['captcha_required'] );
			echo $required ? esc_html__( 'Required', 'bb-hubspot-forms' ) : esc_html__( 'Optional', 'bb-hubspot-forms' );
		}
	}

	public static function enqueue_admin_assets( string $hook ): void {
		if ( $hook !== 'edit.php' ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'hubspot_form' ) {
			return;
		}
		wp_enqueue_script( 'wp-util' );
		wp_add_inline_script(
			'wp-util',
			"(function(){document.addEventListener('click',function(e){var btn=e.target.closest('.bb-hubspot-forms-copy-shortcode');if(!btn){return;}var shortcode=btn.getAttribute('data-shortcode')||'';if(!shortcode){return;}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(shortcode).then(function(){btn.textContent='Copied';setTimeout(function(){btn.textContent='Copy';},1500);});}else{window.prompt('Copy shortcode:',shortcode);}});})();"
		);
	}

	public static function sanitize_meta_object( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return self::sanitize_meta_value( $value );
	}

	private static function sanitize_meta_value( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean_key = is_string( $key ) ? sanitize_key( $key ) : $key;
				$clean[ $clean_key ] = self::sanitize_meta_value( $item );
			}
			return $clean;
		}
		if ( is_bool( $value ) ) {
			return (bool) $value;
		}
		if ( is_int( $value ) ) {
			return (int) $value;
		}
		if ( is_float( $value ) ) {
			return (float) $value;
		}
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}
		return '';
	}
}

