<?php
/**
 * ITGallery connector for the Kogo theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kogo_ITGallery {
	const API_BASE_URL    = 'https://api.itgalleryapp.com/api/public';
	const OPTION          = 'kogo_itgallery_settings';
	const LAST_SYNC       = 'kogo_itgallery_last_sync';
	const SYNC_LOCK       = 'kogo_itgallery_sync_lock';
	const HASH_VERSION    = 1;
	const REWRITE_VERSION = 2;

	private static $post_types = array(
		'artist'     => 'kogo_artist',
		'work'       => 'kogo_work',
		'exposition' => 'kogo_exposition',
	);

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_head-edit.php', array( $this, 'render_admin_thumbnail_styles' ) );
		add_action( 'admin_post_kogo_itgallery_sync', array( $this, 'handle_sync' ) );

		foreach ( self::$post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_admin_columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_admin_column' ), 10, 2 );
		}
	}

	public static function register_post_types() {
		self::register_post_type(
			self::$post_types['artist'],
			__( 'Artists', 'kogo' ),
			__( 'Artist', 'kogo' ),
			'artists',
			'dashicons-admin-users'
		);

		self::register_post_type(
			self::$post_types['work'],
			__( 'Works', 'kogo' ),
			__( 'Work', 'kogo' ),
			'works',
			'dashicons-art'
		);

		self::register_post_type(
			self::$post_types['exposition'],
			__( 'Exhibitions', 'kogo' ),
			__( 'Exhibition', 'kogo' ),
			'exhibitions',
			'dashicons-format-gallery'
		);

		register_taxonomy(
			'kogo_artist_category',
			self::$post_types['artist'],
			array(
				'labels'            => array(
					'name'          => __( 'Artist Categories', 'kogo' ),
					'singular_name' => __( 'Artist Category', 'kogo' ),
					'search_items'  => __( 'Search Artist Categories', 'kogo' ),
					'all_items'     => __( 'All Artist Categories', 'kogo' ),
					'parent_item'   => __( 'Parent Artist Category', 'kogo' ),
					'edit_item'     => __( 'Edit Artist Category', 'kogo' ),
					'update_item'   => __( 'Update Artist Category', 'kogo' ),
					'add_new_item'  => __( 'Add New Artist Category', 'kogo' ),
					'new_item_name' => __( 'New Artist Category Name', 'kogo' ),
					'menu_name'     => __( 'Categories', 'kogo' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'artist-category' ),
			)
		);
	}

	private static function register_post_type( $post_type, $plural, $singular, $slug, $icon ) {
		register_post_type(
			$post_type,
			array(
				'labels'       => array(
					'name'          => $plural,
					'singular_name' => $singular,
					'add_new_item'  => sprintf( __( 'Add New %s', 'kogo' ), $singular ),
					'edit_item'     => sprintf( __( 'Edit %s', 'kogo' ), $singular ),
					'view_item'     => sprintf( __( 'View %s', 'kogo' ), $singular ),
					'search_items'  => sprintf( __( 'Search %s', 'kogo' ), $plural ),
					'not_found'     => sprintf( __( 'No %s found.', 'kogo' ), strtolower( $plural ) ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => $icon,
				'rewrite'      => array( 'slug' => $slug ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			)
		);
	}

	public static function maybe_flush_rewrite_rules() {
		if ( (int) get_option( 'kogo_itgallery_rewrite_version' ) === self::REWRITE_VERSION ) {
			return;
		}

		flush_rewrite_rules();
		update_option( 'kogo_itgallery_rewrite_version', self::REWRITE_VERSION, false );
	}

	public function register_settings() {
		register_setting(
			'kogo_itgallery',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$old      = self::settings();
		$input    = is_array( $input ) ? $input : array();
		$language = isset( $input['language'] ) ? sanitize_text_field( wp_unslash( $input['language'] ) ) : 'en_EN';

		if ( ! preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $language ) ) {
			$language = 'en_EN';
			add_settings_error(
				self::OPTION,
				'kogo_itgallery_language',
				__( 'The API language must use a five-character locale such as en_EN.', 'kogo' )
			);
		}

		$api_key = isset( $input['api_key'] ) ? trim( wp_unslash( (string) $input['api_key'] ) ) : '';
		$api_key = preg_replace( '/[\r\n]+/', '', $api_key );

		if ( empty( $input['clear_api_key'] ) && '' === $api_key ) {
			$api_key = $old['api_key'];
		}

		return array(
			'enabled'  => ! empty( $input['enabled'] ) ? 1 : 0,
			'api_key'  => $api_key,
			'language' => $language,
		);
	}

	public function add_settings_page() {
		add_menu_page(
			__( 'ITGallery', 'kogo' ),
			__( 'ITGallery', 'kogo' ),
			'manage_options',
			'kogo-itgallery',
			array( $this, 'render_settings_page' ),
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( __DIR__ . '/../assets/images/icons/itgallery.svg' ) ),
			29
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::settings();
		$notice   = get_transient( 'kogo_itgallery_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'kogo_itgallery_notice_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ITGallery', 'kogo' ); ?></h1>
			<?php settings_errors( self::OPTION ); ?>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'kogo_itgallery' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled', 'kogo' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>>
								<?php esc_html_e( 'Allow ITGallery synchronization', 'kogo' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kogo-itgallery-api-key"><?php esc_html_e( 'API key', 'kogo' ); ?></label></th>
						<td>
							<?php if ( defined( 'KOGO_ITGALLERY_API_KEY' ) ) : ?>
								<p><?php esc_html_e( 'The API key is defined by KOGO_ITGALLERY_API_KEY in the site configuration.', 'kogo' ); ?></p>
							<?php else : ?>
								<input id="kogo-itgallery-api-key" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[api_key]" value="" placeholder="<?php echo esc_attr( $settings['api_key'] ? __( 'Saved — leave blank to keep', 'kogo' ) : '' ); ?>">
								<?php if ( $settings['api_key'] ) : ?>
									<p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[clear_api_key]" value="1"> <?php esc_html_e( 'Remove the saved API key', 'kogo' ); ?></label></p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kogo-itgallery-language"><?php esc_html_e( 'API language', 'kogo' ); ?></label></th>
						<td>
							<input id="kogo-itgallery-language" class="small-text" type="text" maxlength="5" pattern="[a-z]{2}_[A-Z]{2}" name="<?php echo esc_attr( self::OPTION ); ?>[language]" value="<?php echo esc_attr( $settings['language'] ); ?>">
							<p class="description"><?php esc_html_e( 'Five-character ITGallery locale, for example en_EN or et_EE.', 'kogo' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'kogo' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Synchronization', 'kogo' ); ?></h2>
			<p>
				<?php
				$last_sync = get_option( self::LAST_SYNC );
				echo $last_sync
					? esc_html( sprintf( __( 'Last successful sync: %s UTC', 'kogo' ), $last_sync ) )
					: esc_html__( 'No successful sync has run yet.', 'kogo' );
				?>
			</p>
			<p class="description"><?php esc_html_e( 'Sync imports the complete web-visible catalogue. Unchanged items are skipped; imported items no longer returned by ITGallery are moved to Trash.', 'kogo' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kogo_itgallery_sync">
				<?php wp_nonce_field( 'kogo_itgallery_sync' ); ?>
				<?php submit_button( __( 'Sync now', 'kogo' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to synchronize ITGallery.', 'kogo' ) );
		}

		check_admin_referer( 'kogo_itgallery_sync' );
		$result = $this->sync();

		if ( is_wp_error( $result ) ) {
			$notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
		} else {
			$notice = array(
				'type'    => 'success',
				'message' => $this->format_sync_report( $result ),
			);
		}

		set_transient( 'kogo_itgallery_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=kogo-itgallery' ) );
		exit;
	}

	public function sync() {
		$settings = self::settings();
		$api_key  = self::api_key();

		if ( ! $settings['enabled'] ) {
			return new WP_Error( 'kogo_itgallery_disabled', __( 'Enable Kogo ITGallery before synchronizing.', 'kogo' ) );
		}

		if ( '' === $api_key ) {
			return new WP_Error( 'kogo_itgallery_missing_key', __( 'Enter an ITGallery API key before synchronizing.', 'kogo' ) );
		}

		if ( get_transient( self::SYNC_LOCK ) ) {
			return new WP_Error( 'kogo_itgallery_locked', __( 'An ITGallery sync is already running. Try again shortly.', 'kogo' ) );
		}

		set_transient( self::SYNC_LOCK, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$collections = array();
			$requests    = array(
				'artist'     => array( 'artists', array() ),
				'work'       => array( 'works', array() ),
				'exposition' => array( 'expositions', array( 'with' => 'works,artist' ) ),
			);

			foreach ( $requests as $kind => $request ) {
				$collections[ $kind ] = $this->request_collection( $request[0], $request[1], $api_key, $settings['language'] );
				if ( is_wp_error( $collections[ $kind ] ) ) {
					return $collections[ $kind ];
				}
			}

			$report = array();
			foreach ( $collections as $kind => $items ) {
				$report[ $kind ] = $this->sync_collection( $kind, $items );
				if ( is_wp_error( $report[ $kind ] ) ) {
					return $report[ $kind ];
				}
			}

			$this->rebuild_relationships();
			update_option( self::LAST_SYNC, gmdate( 'Y-m-d H:i:s' ), false );

			return $report;
		} finally {
			delete_transient( self::SYNC_LOCK );
		}
	}

	private function request_collection( $endpoint, $extra_query, $api_key, $language ) {
		$limit       = 100;
		$items       = array();
		$page_hashes = array();

		for ( $page = 1; $page <= 100; $page++ ) {
			$query    = array_merge(
				array(
					'limit'    => $limit,
					'page'     => $page,
					'language' => $language,
				),
				$extra_query
			);
			$url      = add_query_arg( $query, trailingslashit( self::API_BASE_URL ) . ltrim( $endpoint, '/' ) );
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 30,
					'headers' => array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $api_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'kogo_itgallery_request',
					sprintf( __( 'Could not retrieve ITGallery %1$s: %2$s', 'kogo' ), $endpoint, $response->get_error_message() )
				);
			}

			$status  = wp_remote_retrieve_response_code( $response );
			$body    = wp_remote_retrieve_body( $response );
			$decoded = json_decode( $body, true );

			if ( $status < 200 || $status >= 300 ) {
				$message = '';
				if ( is_array( $decoded ) ) {
					foreach ( array( 'message', 'error', 'detail' ) as $key ) {
						if ( isset( $decoded[ $key ] ) && is_scalar( $decoded[ $key ] ) ) {
							$message = ': ' . sanitize_text_field( (string) $decoded[ $key ] );
							break;
						}
					}
				}
				return new WP_Error(
					'kogo_itgallery_http',
					sprintf( __( 'ITGallery returned HTTP %1$d for %2$s%3$s', 'kogo' ), $status, $endpoint, $message )
				);
			}

			if ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
				$decoded = $decoded['data'];
			} elseif ( isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
				$decoded = $decoded['items'];
			} elseif ( isset( $decoded['hydra:member'] ) && is_array( $decoded['hydra:member'] ) ) {
				$decoded = $decoded['hydra:member'];
			}

			if ( ! is_array( $decoded ) || ! self::is_list( $decoded ) ) {
				return new WP_Error(
					'kogo_itgallery_json',
					sprintf( __( 'ITGallery returned an unexpected response for %s.', 'kogo' ), $endpoint )
				);
			}

			$page_ids = array();
			foreach ( $decoded as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['id'] ) || '' === (string) $item['id'] ) {
					return new WP_Error(
						'kogo_itgallery_missing_id',
						sprintf( __( 'ITGallery returned a %s record without an ID.', 'kogo' ), $endpoint )
					);
				}
				$id           = (string) $item['id'];
				$items[ $id ] = $item;
				$page_ids[]   = $id;
			}

			$page_hash = md5( implode( '|', $page_ids ) );
			if ( isset( $page_hashes[ $page_hash ] ) && ! empty( $decoded ) ) {
				return new WP_Error(
					'kogo_itgallery_pagination',
					sprintf( __( 'ITGallery pagination did not advance while retrieving %s.', 'kogo' ), $endpoint )
				);
			}
			$page_hashes[ $page_hash ] = true;

			if ( count( $decoded ) !== $limit ) {
				return array_values( $items );
			}
		}

		return new WP_Error(
			'kogo_itgallery_page_limit',
			sprintf( __( 'ITGallery returned more than 10,000 %s records; synchronization stopped safely.', 'kogo' ), $endpoint )
		);
	}

	private function sync_collection( $kind, $items ) {
		$post_type  = self::$post_types[ $kind ];
		$local      = $this->load_post_map( $post_type );
		$remote_ids = array();
		$report     = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'trashed'   => 0,
		);

		foreach ( $items as $item ) {
			$external_id                = (string) $item['id'];
			$remote_ids[ $external_id ] = true;
			$post_id                    = isset( $local[ $external_id ] ) ? $local[ $external_id ] : 0;

			if ( ! empty( $item['canceled_at'] ) ) {
				if ( $post_id && 'trash' !== get_post_status( $post_id ) ) {
					wp_trash_post( $post_id );
					$this->update_meta_if_changed( $post_id, '_kogo_itgallery_canceled_at', $item['canceled_at'] );
					++$report['trashed'];
				} else {
					++$report['unchanged'];
				}
				continue;
			}

			$result = $this->sync_item( $kind, $item, $post_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			++$report[ $result['status'] ];
			$local[ $external_id ] = $result['post_id'];
		}

		foreach ( $local as $external_id => $post_id ) {
			if ( ! isset( $remote_ids[ $external_id ] ) && 'trash' !== get_post_status( $post_id ) ) {
				wp_trash_post( $post_id );
				++$report['trashed'];
			}
		}

		return $report;
	}

	private function sync_item( $kind, $item, $post_id ) {
		$post_type = self::$post_types[ $kind ];
		$hash      = self::fingerprint( $kind, $item );

		if ( $post_id && 'trash' !== get_post_status( $post_id ) && hash_equals( (string) get_post_meta( $post_id, '_kogo_itgallery_hash', true ), $hash ) ) {
			return array(
				'status'  => 'unchanged',
				'post_id' => $post_id,
			);
		}

		$post_data = $this->post_data( $kind, $item );
		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			if ( 'trash' === get_post_status( $post_id ) || get_post_meta( $post_id, '_kogo_itgallery_canceled_at', true ) ) {
				$post_data['post_status'] = 'publish';
			}
			$result = wp_update_post( wp_slash( $post_data ), true );
			$status = 'updated';
		} else {
			$post_data['post_type']   = $post_type;
			$post_data['post_status'] = 'publish';
			$result                   = wp_insert_post( wp_slash( $post_data ), true );
			$status                   = 'created';
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'kogo_itgallery_post',
				sprintf( __( 'Could not save ITGallery %1$s %2$s: %3$s', 'kogo' ), $kind, $item['id'], $result->get_error_message() )
			);
		}

		$post_id = (int) $result;
		foreach ( $this->item_meta( $kind, $item ) as $key => $value ) {
			$this->update_meta_if_changed( $post_id, $key, $value );
		}
		delete_post_meta( $post_id, '_kogo_itgallery_canceled_at' );
		$this->update_meta_if_changed( $post_id, '_kogo_itgallery_hash', $hash );

		return array(
			'status'  => $status,
			'post_id' => $post_id,
		);
	}

	private function post_data( $kind, $item ) {
		if ( 'artist' === $kind ) {
			$title   = trim( ( isset( $item['name'] ) ? $item['name'] : '' ) . ' ' . ( isset( $item['surname'] ) ? $item['surname'] : '' ) );
			$content = isset( $item['bio'] ) ? $item['bio'] : '';
		} elseif ( 'exposition' === $kind ) {
			$title   = isset( $item['name'] ) ? $item['name'] : '';
			$content = isset( $item['description'] ) ? $item['description'] : '';
		} else {
			$title   = isset( $item['name'] ) ? $item['name'] : '';
			$content = isset( $item['description'] ) ? $item['description'] : '';
		}

		if ( '' === trim( (string) $title ) ) {
			$title = sprintf( 'ITGallery %s #%s', ucfirst( $kind ), $item['id'] );
		}

		return array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( (string) $content ),
		);
	}

	private function item_meta( $kind, $item ) {
		$meta = array(
			'_kogo_itgallery_id'            => (string) $item['id'],
			'_kogo_itgallery_last_modified' => isset( $item['last_modified'] ) ? $item['last_modified'] : '',
			'_kogo_itgallery_created'       => isset( $item['created'] ) ? $item['created'] : '',
			'_kogo_itgallery_payload'       => $item,
		);

		if ( 'artist' === $kind ) {
			$meta += array(
				'_kogo_itgallery_first_name'             => isset( $item['name'] ) ? $item['name'] : '',
				'_kogo_itgallery_surname'                => isset( $item['surname'] ) ? $item['surname'] : '',
				'_kogo_itgallery_artist_type'            => isset( $item['artist_type'] ) ? $item['artist_type'] : array(),
				'_kogo_itgallery_image_url'              => isset( $item['photo'] ) ? esc_url_raw( $item['photo'] ) : '',
				'_kogo_itgallery_phone_1'                => isset( $item['phone1'] ) ? $item['phone1'] : '',
				'_kogo_itgallery_phone_2'                => isset( $item['phone2'] ) ? $item['phone2'] : '',
				'_kogo_itgallery_email_1'                => isset( $item['email1'] ) ? $item['email1'] : '',
				'_kogo_itgallery_email_2'                => isset( $item['email2'] ) ? $item['email2'] : '',
				'_kogo_itgallery_document_id'            => isset( $item['document_id'] ) ? $item['document_id'] : '',
				'_kogo_itgallery_notes'                  => isset( $item['notes'] ) ? $item['notes'] : '',
				'_kogo_itgallery_country'                => isset( $item['country'] ) ? $item['country'] : '',
				'_kogo_itgallery_nationality'            => isset( $item['nacionality'] ) ? $item['nacionality'] : '',
				'_kogo_itgallery_birth_country'          => isset( $item['birth_country'] ) ? $item['birth_country'] : '',
				'_kogo_itgallery_birth_date'             => isset( $item['birth_date'] ) ? $item['birth_date'] : '',
				'_kogo_itgallery_birth_city'             => isset( $item['birth_city'] ) ? $item['birth_city'] : '',
				'_kogo_itgallery_death_country'          => isset( $item['death_country'] ) ? $item['death_country'] : '',
				'_kogo_itgallery_death_date'             => isset( $item['death_date'] ) ? $item['death_date'] : '',
				'_kogo_itgallery_death_city'             => isset( $item['death_city'] ) ? $item['death_city'] : '',
				'_kogo_itgallery_studio_address'         => isset( $item['studio_address'] ) ? $item['studio_address'] : '',
				'_kogo_itgallery_personal_address'       => isset( $item['personal_address'] ) ? $item['personal_address'] : '',
				'_kogo_itgallery_documents'              => isset( $item['documents'] ) ? $item['documents'] : array(),
				'_kogo_itgallery_remote_works_url'       => isset( $item['works'] ) && is_string( $item['works'] ) ? esc_url_raw( $item['works'] ) : '',
				'_kogo_itgallery_remote_expositions_url' => isset( $item['expositions'] ) && is_string( $item['expositions'] ) ? esc_url_raw( $item['expositions'] ) : '',
			);
		} elseif ( 'work' === $kind ) {
			$meta += array(
				'_kogo_itgallery_inventory_id' => isset( $item['inventory_id'] ) ? $item['inventory_id'] : '',
				'_kogo_itgallery_year'         => isset( $item['year'] ) ? $item['year'] : '',
				'_kogo_itgallery_type'         => isset( $item['type'] ) ? $item['type'] : array(),
				'_kogo_itgallery_technique'    => isset( $item['technique'] ) ? $item['technique'] : '',
				'_kogo_itgallery_location'     => isset( $item['location'] ) ? $item['location'] : array(),
				'_kogo_itgallery_sub_location' => isset( $item['sub_location'] ) ? $item['sub_location'] : '',
				'_kogo_itgallery_image_url'    => isset( $item['main_image'] ) ? esc_url_raw( $item['main_image'] ) : '',
				'_kogo_itgallery_images'       => isset( $item['images'] ) ? $item['images'] : array(),
				'_kogo_itgallery_availability' => isset( $item['availability'] ) ? $item['availability'] : '',
				'_kogo_itgallery_prices'       => isset( $item['prices'] ) ? $item['prices'] : array(),
				'_kogo_itgallery_dimensions'   => isset( $item['dimensions'] ) ? $item['dimensions'] : array(),
				'_kogo_itgallery_tags'         => isset( $item['tags'] ) ? $item['tags'] : array(),
				'_kogo_itgallery_artist'       => isset( $item['artist'] ) ? $item['artist'] : array(),
				'_kogo_itgallery_artist_id'    => isset( $item['artist'] ) && is_array( $item['artist'] ) && isset( $item['artist']['id'] ) ? (string) $item['artist']['id'] : '',
			);
		} else {
			$meta += array(
				'_kogo_itgallery_timetable'          => isset( $item['timetable'] ) ? $item['timetable'] : '',
				'_kogo_itgallery_type'               => isset( $item['type'] ) ? $item['type'] : array(),
				'_kogo_itgallery_character'          => isset( $item['character'] ) ? $item['character'] : array(),
				'_kogo_itgallery_image_url'          => isset( $item['main_image'] ) ? esc_url_raw( $item['main_image'] ) : '',
				'_kogo_itgallery_images'             => isset( $item['images'] ) ? $item['images'] : array(),
				'_kogo_itgallery_location'           => isset( $item['location'] ) ? $item['location'] : array(),
				'_kogo_itgallery_opening_date'       => isset( $item['opening_date'] ) ? $item['opening_date'] : '',
				'_kogo_itgallery_closing_date'       => isset( $item['closing_date'] ) ? $item['closing_date'] : '',
				'_kogo_itgallery_opening_begin_hour' => isset( $item['opening_begin_hour'] ) ? $item['opening_begin_hour'] : '',
				'_kogo_itgallery_opening_end_hour'   => isset( $item['opening_end_hour'] ) ? $item['opening_end_hour'] : '',
				'_kogo_itgallery_url'                => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
				'_kogo_itgallery_documents'          => isset( $item['documents'] ) ? $item['documents'] : array(),
				'_kogo_itgallery_artist_ids'         => self::extract_ids( isset( $item['artists'] ) ? $item['artists'] : array() ),
				'_kogo_itgallery_work_ids'           => self::extract_ids( isset( $item['works'] ) ? $item['works'] : array() ),
			);
		}

		return $meta;
	}

	private function rebuild_relationships() {
		$artists            = $this->active_post_map( self::$post_types['artist'] );
		$works              = $this->active_post_map( self::$post_types['work'] );
		$expositions        = $this->active_post_map( self::$post_types['exposition'] );
		$artist_works       = array_fill_keys( array_keys( $artists ), array() );
		$artist_expositions = array_fill_keys( array_keys( $artists ), array() );
		$work_expositions   = array_fill_keys( array_keys( $works ), array() );

		foreach ( $works as $work_id => $work_post_id ) {
			$payload        = get_post_meta( $work_post_id, '_kogo_itgallery_payload', true );
			$artist_id      = is_array( $payload ) && isset( $payload['artist'] ) && is_array( $payload['artist'] ) && isset( $payload['artist']['id'] ) ? (string) $payload['artist']['id'] : '';
			$artist_post_id = $artist_id && isset( $artists[ $artist_id ] ) ? (int) $artists[ $artist_id ] : 0;
			$this->update_meta_if_changed( $work_post_id, '_kogo_itgallery_artist_post_id', $artist_post_id );
			if ( $artist_post_id ) {
				$artist_works[ $artist_id ][] = (int) $work_post_id;
			}
		}

		foreach ( $expositions as $exposition_id => $exposition_post_id ) {
			$payload         = get_post_meta( $exposition_post_id, '_kogo_itgallery_payload', true );
			$artist_ids      = self::extract_ids( is_array( $payload ) && isset( $payload['artists'] ) ? $payload['artists'] : array() );
			$work_ids        = self::extract_ids( is_array( $payload ) && isset( $payload['works'] ) ? $payload['works'] : array() );
			$artist_post_ids = array();
			$work_post_ids   = array();

			foreach ( $artist_ids as $artist_id ) {
				if ( isset( $artists[ $artist_id ] ) ) {
					$artist_post_ids[]                  = (int) $artists[ $artist_id ];
					$artist_expositions[ $artist_id ][] = (int) $exposition_post_id;
				}
			}
			foreach ( $work_ids as $work_id ) {
				if ( isset( $works[ $work_id ] ) ) {
					$work_post_ids[]                = (int) $works[ $work_id ];
					$work_expositions[ $work_id ][] = (int) $exposition_post_id;
				}
			}

			$this->update_meta_if_changed( $exposition_post_id, '_kogo_itgallery_artist_post_ids', $artist_post_ids );
			$this->update_meta_if_changed( $exposition_post_id, '_kogo_itgallery_work_post_ids', $work_post_ids );
		}

		foreach ( $artists as $artist_id => $artist_post_id ) {
			$this->update_meta_if_changed( $artist_post_id, '_kogo_itgallery_work_post_ids', $artist_works[ $artist_id ] );
			$this->update_meta_if_changed( $artist_post_id, '_kogo_itgallery_exposition_post_ids', $artist_expositions[ $artist_id ] );
		}
		foreach ( $works as $work_id => $work_post_id ) {
			$this->update_meta_if_changed( $work_post_id, '_kogo_itgallery_exposition_post_ids', $work_expositions[ $work_id ] );
		}
	}

	private function load_post_map( $post_type ) {
		$post_ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		$map      = array();
		foreach ( $post_ids as $post_id ) {
			$external_id = (string) get_post_meta( $post_id, '_kogo_itgallery_id', true );
			if ( '' !== $external_id && ! isset( $map[ $external_id ] ) ) {
				$map[ $external_id ] = (int) $post_id;
			}
		}
		return $map;
	}

	private function active_post_map( $post_type ) {
		$map = $this->load_post_map( $post_type );
		foreach ( $map as $external_id => $post_id ) {
			if ( 'trash' === get_post_status( $post_id ) ) {
				unset( $map[ $external_id ] );
			}
		}
		return $map;
	}

	private function update_meta_if_changed( $post_id, $key, $value ) {
		$empty = null === $value || '' === $value || array() === $value || ( 0 === $value && false !== strpos( $key, '_post_id' ) );
		if ( $empty ) {
			if ( metadata_exists( 'post', $post_id, $key ) ) {
				delete_post_meta( $post_id, $key );
			}
			return;
		}

		$current = get_post_meta( $post_id, $key, true );
		if ( self::values_equal( $current, $value ) ) {
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}

	private static function values_equal( $left, $right ) {
		if ( is_array( $left ) || is_array( $right ) || is_object( $left ) || is_object( $right ) ) {
			return self::stable_json( $left ) === self::stable_json( $right );
		}
		return (string) $left === (string) $right;
	}

	public static function fingerprint( $kind, $item ) {
		return hash(
			'sha256',
			self::stable_json(
				array(
					'schema' => self::HASH_VERSION,
					'kind'   => $kind,
					'item'   => $item,
				)
			)
		);
	}

	private static function stable_json( $value ) {
		return wp_json_encode( self::sort_recursive( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function sort_recursive( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( ! self::is_list( $value ) ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::sort_recursive( $item );
		}
		return $value;
	}

	private static function is_list( $value ) {
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function extract_ids( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}
		$ids = array();
		foreach ( $items as $item ) {
			$id = is_array( $item ) && isset( $item['id'] ) ? $item['id'] : $item;
			if ( is_scalar( $id ) && '' !== (string) $id ) {
				$ids[] = (string) $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private static function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::default_settings() );
	}

	private static function default_settings() {
		return array(
			'enabled'  => 0,
			'api_key'  => '',
			'language' => 'en_EN',
		);
	}

	private static function api_key() {
		if ( defined( 'KOGO_ITGALLERY_API_KEY' ) && is_string( KOGO_ITGALLERY_API_KEY ) ) {
			return trim( KOGO_ITGALLERY_API_KEY );
		}
		$settings = self::settings();
		return trim( (string) $settings['api_key'] );
	}

	private function format_sync_report( $report ) {
		$totals = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'trashed'   => 0,
		);
		foreach ( $report as $counts ) {
			foreach ( $totals as $key => $unused ) {
				$totals[ $key ] += $counts[ $key ];
			}
		}
		return sprintf(
			__( 'ITGallery sync complete: %1$d created, %2$d updated, %3$d unchanged, %4$d moved to Trash.', 'kogo' ),
			$totals['created'],
			$totals['updated'],
			$totals['unchanged'],
			$totals['trashed']
		);
	}

	public function add_admin_columns( $columns ) {
		$with_thumbnail = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$with_thumbnail['kogo_itgallery_thumbnail'] = __( 'Image', 'kogo' );
			}
			$with_thumbnail[ $key ] = $label;
		}
		$columns                            = $with_thumbnail;
		$columns['kogo_itgallery_id']       = __( 'ITGallery ID', 'kogo' );
		$columns['kogo_itgallery_modified'] = __( 'Updated in ITGallery', 'kogo' );
		return $columns;
	}

	public function render_admin_column( $column, $post_id ) {
		if ( 'kogo_itgallery_thumbnail' === $column ) {
			$image_url = kogo_itgallery_get_image_url( $post_id, 'small' );
			if ( $image_url ) {
				printf( '<img class="kogo-itgallery-thumbnail" src="%s" alt="" width="44" height="44" loading="lazy" decoding="async">', esc_url( $image_url ) );
			} else {
				echo '<span class="kogo-itgallery-thumbnail kogo-itgallery-thumbnail--empty dashicons dashicons-format-image" aria-hidden="true"></span>';
			}
		} elseif ( 'kogo_itgallery_id' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_kogo_itgallery_id', true ) );
		} elseif ( 'kogo_itgallery_modified' === $column ) {
			echo esc_html( self::format_itgallery_datetime( get_post_meta( $post_id, '_kogo_itgallery_last_modified', true ) ) );
		}
	}

	public function render_admin_thumbnail_styles() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::$post_types, true ) ) {
			return;
		}
		?>
		<style>
			.fixed .column-kogo_itgallery_thumbnail { width: 52px; }
			.kogo-itgallery-thumbnail { display: block; width: 44px; height: 44px; object-fit: cover; border-radius: 2px; background: #f0f0f1; }
			.kogo-itgallery-thumbnail--empty { box-sizing: border-box; padding: 12px; color: #a7aaad; font-size: 20px; }
		</style>
		<?php
	}

	private static function format_itgallery_datetime( $value ) {
		if ( ! $value ) {
			return '';
		}

		try {
			$date = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
			return $date->setTimezone( new DateTimeZone( 'Europe/Tallinn' ) )->format( 'd.m.Y H:i' );
		} catch ( Exception $exception ) {
			return $value;
		}
	}
}

new Kogo_ITGallery();

/**
 * Return the best remote ITGallery image URL stored for a synced post.
 *
 * @param int    $post_id WordPress post ID.
 * @param string $size    One of small, medium, large, or original.
 * @return string
 */
function kogo_itgallery_get_image_url( $post_id, $size = 'large' ) {
	$images = get_post_meta( $post_id, '_kogo_itgallery_images', true );
	$key    = in_array( $size, array( 'small', 'medium', 'large' ), true ) ? $size . '_url' : 'url';

	if ( is_array( $images ) ) {
		foreach ( $images as $image ) {
			if ( is_array( $image ) && ! empty( $image[ $key ] ) ) {
				return esc_url_raw( $image[ $key ] );
			}
		}
	}

	$payload     = get_post_meta( $post_id, '_kogo_itgallery_payload', true );
	$photo_sizes = is_array( $payload ) && is_array( $payload['photo_sizes'] ?? null ) ? $payload['photo_sizes'] : array();
	$photo_key   = in_array( $size, array( 'small', 'medium', 'large' ), true ) ? $size : 'url';
	if ( ! empty( $photo_sizes[ $photo_key ] ) ) {
		return esc_url_raw( $photo_sizes[ $photo_key ] );
	}

	return esc_url_raw( get_post_meta( $post_id, '_kogo_itgallery_image_url', true ) );
}
