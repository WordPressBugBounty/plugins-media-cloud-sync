<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Admin UI schema (cards, wizard, providers, delivery, settings fields, job screens, nav).
 * Built server-side so add-ons can extend any section through filters without a JS rebuild.
 * @since 1.5.0
 */
class UISchema {
	private static $instance = null;

	/**
	 * Full schema after every section and field filter.
	 * @param bool $whole_filter Apply the whole-schema `wpmcs_ui_schema` filter.
	 * @return array
	 */
	public function get_all( $whole_filter = true ) {
		$sections = [
			'dashboard'                 => $this->dashboard(),
			'dashboard_cards'           => $this->dashboard_cards(),
			'dashboard_pro_cards'       => $this->locked_pro_cards(),
			'configure_progress_bar'    => $this->configure_progress_bar(),
			'configure_footer'          => $this->configure_footer(),
			'services'                  => $this->services_section(),
			'configureDefaultValues'    => $this->configure_default_values(),
			'bucketDefaultValues'       => $this->bucket_default_values(),
			'bucketAddNewDefaultValues' => $this->bucket_add_new_default_values(),
			'settingsDefaultValues'     => $this->settings_default_values(),
			'cdnServers'                => $this->cdn_servers(),
			'syncScreen'                => $this->sync_screen(),
			'routes'                    => [],
			'nav'                       => $this->nav(),
			// Tool cards for the Tools page; PRO fills it, the page and its menu item show only when it has cards.
			'tools'                     => [],
			'toolsPage'                 => $this->tools_page(),
			// Tool pages with their own settings cards and job, keyed by slug; PRO fills it.
			'toolPages'                 => [],
			'licensePage'               => $this->license_page(),
			'importExportPage'          => $this->import_export_page(),
			// Titles for the Settings page groups; a field picks one with its `section` key.
			'settingsPage'              => $this->settings_page(),
			'settingsSections'          => $this->settings_sections(),
			// Setting keys reset to defaults when the delivery provider changes.
			'deliveryResetKeys'         => [ 'rewrite_url', 'enable_cdn', 'cdn_url' ],
			'syncMethods'               => $this->sync_methods(),
			'welcome'                   => $this->welcome(),
			'wizard'                    => $this->wizard(),
			'upsell'                    => $this->upsell(),
			// Notice shown while an add-on license is expired; empty unless an add-on provides it.
			'licenseExpiredNotice'      => [],
		];

		foreach ( $sections as $section => $data ) {
			$sections[ $section ] = apply_filters( "wpmcs_ui_schema_{$section}", $data );
		}

		// Field filters run last so providers/CDN entries added by section filters get them too.
		foreach ( (array) $sections['services'] as $service => $config ) {
			if ( is_array( $config ) ) {
				$fields = array_merge( (array) ( $config['settings'] ?? [] ), $this->locked_settings_fields() );
				$sections['services'][ $service ]['settings'] = $this->with_sections( apply_filters( 'wpmcs_ui_schema_settings_fields', $fields, $service ) );
			}
		}
		foreach ( (array) $sections['cdnServers'] as $service => $entries ) {
			foreach ( (array) $entries as $key => $entry ) {
				if ( is_array( $entry ) ) {
					$fields = array_merge( (array) ( $entry['fields'] ?? [] ), $this->locked_delivery_fields( ! empty( $entry['privateSupport'] ) ) );
					$sections['cdnServers'][ $service ][ $key ]['fields'] = $this->with_sections( apply_filters( 'wpmcs_ui_schema_delivery_fields', $fields, $service, $key, $entry ) );
				}
			}
		}

		return $this->sanitize( $whole_filter ? apply_filters( 'wpmcs_ui_schema', $sections ) : $sections );
	}

	/**
	 * JSON for the admin page; falls back to skipping the whole-schema filter if it broke encoding.
	 * @return string
	 */
	public function get_json() {
		$json = wp_json_encode( $this->get_all(), JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG );
		if ( false === $json ) {
			$json = wp_json_encode( $this->get_all( false ), JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG );
		}
		return false === $json ? '{}' : $json;
	}

	/** Upgrade prompts shown when PRO isn't installed, plus the locked-option badge/tooltip. */
	private function upsell() {
		return [
			'url'           => 'https://dudlewebs.com/product/media-cloud-sync/',
			'headerButton'  => __( 'Upgrade to Pro', 'media-cloud-sync' ),
			'banner'        => [
				'badge'    => __( 'Media Cloud Sync PRO', 'media-cloud-sync' ),
				'title'    => __( 'Unlock the full power of Media Cloud Sync', 'media-cloud-sync' ),
				'text'     => __( 'Manage every file in your bucket, move between providers, and run bulk jobs faster, all from your WordPress admin.', 'media-cloud-sync' ),
				'features' => [
					__( 'Restore to server', 'media-cloud-sync' ),
					__( 'File Manager', 'media-cloud-sync' ),
					__( 'Bucket to Bucket', 'media-cloud-sync' ),
					__( 'Private media', 'media-cloud-sync' ),
					__( 'Import from other plugins', 'media-cloud-sync' ),
					__( 'Ajax and Mixed sync', 'media-cloud-sync' ),
				],
				'button'   => __( 'Upgrade to Pro', 'media-cloud-sync' ),
				'link'     => __( 'View all features', 'media-cloud-sync' ),
			],
			'card'          => [
				'title'    => __( 'Upgrade To Premium', 'media-cloud-sync' ),
				'text'     => __( 'Everything in the free version, plus:', 'media-cloud-sync' ),
				'features' => [
					__( 'Manage synced media from the Media Library', 'media-cloud-sync' ),
					__( 'File Manager to browse and manage your cloud bucket', 'media-cloud-sync' ),
					__( 'Restore media back to your server', 'media-cloud-sync' ),
					__( 'Remove local copies of synced media to free server space', 'media-cloud-sync' ),
					__( 'Move media to a new path', 'media-cloud-sync' ),
					__( 'Bucket-to-bucket migration', 'media-cloud-sync' ),
					__( 'Faster Ajax/Mixed bulk sync', 'media-cloud-sync' ),
					__( 'Private media with expiring links', 'media-cloud-sync' ),
					__( 'Import media offloaded by other plugins', 'media-cloud-sync' ),
					__( 'Works with WooCommerce, EDD, Elementor, Divi, WPML, and more', 'media-cloud-sync' ),
					__( 'And much more', 'media-cloud-sync' ),
				],
				'button'   => __( 'View All Features', 'media-cloud-sync' ),
			],
			'lockedBadge'   => __( 'PRO', 'media-cloud-sync' ),
			'lockedLicense' => __( 'Activate your PRO license to use this feature.', 'media-cloud-sync' ),
			'lockedUpgrade' => __( 'This feature is available in Media Cloud Sync PRO.', 'media-cloud-sync' ),
			'activateButton' => __( 'Activate License', 'media-cloud-sync' ),
			'upgradeButton' => __( 'Upgrade to Pro', 'media-cloud-sync' ),
		];
	}

	/** Locked PRO placeholders, shown until a licensed PRO swaps them for the real entries (same slug/name). */
	private function locked_pro_cards() {
		$card = function ( $slug, $label, $description, $icon, $bg, $group = '' ) {
			return array_filter( [
				'label'       => $label,
				'description' => $description,
				'iconBgColor' => $bg,
				'slug'        => $slug,
				'icon'        => $this->img( 'pro/' . $icon ),
				'group'       => $group,
				'external'    => false,
				'link'        => '/',
				'locked'      => true,
			], function ( $value ) {
				return $value !== '';
			} );
		};
		return [
			$card( 'restore_to_server', __( 'Restore Media to Server', 'media-cloud-sync' ), __( 'Download cloud copies back to your server', 'media-cloud-sync' ), 'restore-media-to-server.svg', '#FBE0E1' ),
			$card( 'move_to_new_path', __( 'Move Media to New Path', 'media-cloud-sync' ), __( 'Move synced media to the path set in Settings', 'media-cloud-sync' ), 'move-media-to-new-path.svg', '#EDD9FF' ),
			$card( 'file_manager', __( 'File Manager', 'media-cloud-sync' ), __( 'Browse and manage files in your bucket', 'media-cloud-sync' ), 'file-manager.svg', '#d5eae7', 'links' ),
			$card( 'bucket_to_bucket', __( 'Migrate Bucket to Bucket', 'media-cloud-sync' ), __( 'Copy synced media to another bucket or provider', 'media-cloud-sync' ), 'bucket-to-bucket.svg', '#dce6fb' ),
			$card( 'remove_from_cloud', __( 'Remove Media from Cloud', 'media-cloud-sync' ), __( 'Delete cloud copies, optionally restoring them first', 'media-cloud-sync' ), 'remove-from-cloud.svg', '#FBE0E1' ),
			$card( 'remove_from_server', __( 'Remove Media from Server', 'media-cloud-sync' ), __( 'Delete local copies of synced media to free server space', 'media-cloud-sync' ), 'remove-from-server.svg', '#FDE8D8' ),
			$card( 'tools', __( 'Tools', 'media-cloud-sync' ), __( 'One-off utilities like importing from other plugins', 'media-cloud-sync' ), 'tools.svg', '#E9E8FC', 'links' ),
		];
	}

	private function locked_field( $name, $label, $desc ) {
		return [
			'label'  => $label,
			'desc'   => $desc,
			'name'   => $name,
			'type'   => 'toggle',
			'pro'    => true,
			'locked' => true,
		];
	}

	private function locked_settings_fields() {
		return [
			$this->locked_field( 'extensions_include_enabled', __( 'Include Extensions', 'media-cloud-sync' ), __( 'Only upload files with the extensions you choose.', 'media-cloud-sync' ) ),
			$this->locked_field( 'extensions_exclude_enabled', __( 'Exclude Extensions', 'media-cloud-sync' ), __( 'Never upload files with the extensions you choose.', 'media-cloud-sync' ) ),
		];
	}

	private function locked_delivery_fields( $private_support ) {
		$fields = [];
		if ( $private_support ) {
			$fields[] = $this->locked_field( 'enable_private_media', __( 'Private Media', 'media-cloud-sync' ), __( 'Mark individual media items private and serve them through short-lived signed URLs.', 'media-cloud-sync' ) );
		}
		$fields[] = $this->locked_field( 'cache_control_enabled', __( 'Cache-Control Duration', 'media-cloud-sync' ), __( 'Set how long browsers and CDNs cache newly uploaded files.', 'media-cloud-sync' ) );
		return $fields;
	}

	/** Setup wizard Welcome step: intro paragraphs, highlight pills and the providers list title. */
	private function welcome() {
		return [
			'paragraphs'     => [
				__( 'Media Cloud Sync offloads your WordPress media to Amazon S3, Google Cloud Storage, DigitalOcean Spaces, Cloudflare R2, or any S3-compatible storage, and serves it from the cloud or your CDN.', 'media-cloud-sync' ),
				__( 'Follow the steps to connect your cloud storage. Click Get started to begin.', 'media-cloud-sync' ),
			],
			'highlights'     => [
				[ 'icon' => 'clock', 'text' => __( 'About 3 minutes', 'media-cloud-sync' ) ],
				[ 'icon' => 'shield', 'text' => __( 'No files move until you sync', 'media-cloud-sync' ) ],
				[ 'icon' => 'undo', 'text' => __( 'Change any time in Settings', 'media-cloud-sync' ) ],
			],
			'providersTitle' => __( 'Works with', 'media-cloud-sync' ),
		];
	}

	/** Bulk sync methods; `locked` ones show but can't be picked, the `default` one is used instead; `recommended` adds a label. */
	private function sync_methods() {
		return [
			[
				'value'       => 'ajax',
				'label'       => __( 'Ajax', 'media-cloud-sync' ),
				'description' => __( 'Fastest. Runs live in your browser — stay in the plugin admin while it syncs.', 'media-cloud-sync' ),
				'locked'      => true,
			],
			[
				'value'       => 'cron',
				'label'       => __( 'Cron', 'media-cloud-sync' ),
				'description' => __( 'Runs entirely in the background via WP-Cron. No need to keep this page open.', 'media-cloud-sync' ),
				'locked'      => false,
				'default'     => true,
			],
			[
				'value'       => 'mixed',
				'label'       => __( 'Mixed', 'media-cloud-sync' ),
				'description' => __( 'Fastest while you stay here, and automatically falls back to WP-Cron if you leave.', 'media-cloud-sync' ),
				'locked'      => true,
			],
		];
	}

	/** Header navigation. `requires` => 'service' hides the item until storage is configured. */
	// icon: dashboard|settings|tools|license|docs (unknown names show no icon).
	private function nav() {
		return [
			[
				'label'    => __( 'Dashboard', 'media-cloud-sync' ),
				'path'     => '/',
				'icon'     => 'dashboard',
				'external' => false,
			],
			[
				'label'    => __( 'Settings', 'media-cloud-sync' ),
				'path'     => '/settings',
				'icon'     => 'settings',
				'external' => false,
				'requires' => 'service',
			],
			[
				'label'    => __( 'Documentation', 'media-cloud-sync' ),
				'path'     => 'https://dudlewebs.com/guideline/media-cloud-sync/',
				'icon'     => 'docs',
				'external' => true,
			],
		];
	}

	/** Absolute URL of a free plugin image. */
	private function img( $path ) {
		return WPMCS_ASSETS_URL . 'images/' . ltrim( $path, '/' );
	}

	/**
	 * Drops malformed entries a filter may have returned and filters HTML in warning messages.
	 * @param mixed $sections
	 * @return array
	 */
	private function sanitize( $sections ) {
		$sections = is_array( $sections ) ? $sections : [];

		foreach ( [ 'dashboard_cards', 'dashboard_pro_cards', 'tools' ] as $section ) {
			$sections[ $section ] = array_values( array_filter( (array) ( $sections[ $section ] ?? [] ), function ( $card ) {
				return is_array( $card ) && ! empty( $card['slug'] ) && ! empty( $card['label'] ) && ! empty( $card['link'] );
			} ) );
		}

		$sections['syncScreen'] = array_filter( (array) ( $sections['syncScreen'] ?? [] ), function ( $screen ) {
			return is_array( $screen ) && ! empty( $screen['title'] );
		} );
		foreach ( $sections['syncScreen'] as $action => $screen ) {
			$counters = array_values( array_filter( (array) ( $screen['counters'] ?? [] ), function ( $counter ) {
				return is_array( $counter ) && ! empty( $counter['label'] ) && ! empty( $counter['value'] ) && is_string( $counter['value'] );
			} ) );
			$sections['syncScreen'][ $action ]['counters'] = $counters ?: $this->run_counters();
		}

		$sections['toolPages'] = array_filter( (array) ( $sections['toolPages'] ?? [] ), function ( $page ) {
			return is_array( $page ) && ! empty( $page['title'] ) && 0 === strpos( (string) ( $page['path'] ?? '' ), '/' );
		} );

		$sections['nav'] = array_values( array_filter( (array) ( $sections['nav'] ?? [] ), function ( $item ) {
			return is_array( $item ) && ! empty( $item['label'] ) && ! empty( $item['path'] );
		} ) );

		$sections['syncMethods'] = array_values( array_filter( (array) ( $sections['syncMethods'] ?? [] ), function ( $method ) {
			return is_array( $method ) && ! empty( $method['value'] ) && ! empty( $method['label'] );
		} ) );

		foreach ( [ 'routes', 'deliveryResetKeys' ] as $section ) {
			$sections[ $section ] = array_values( array_unique( array_filter( (array) ( $sections[ $section ] ?? [] ), 'is_string' ) ) );
		}

		foreach ( $sections as $section => $data ) {
			$sections[ $section ] = is_array( $data ) ? $this->kses_warnings( $data ) : [];
		}

		return $sections;
	}

	/** Field warning messages are rendered as HTML, so only allow post-safe markup. */
	private function kses_warnings( $data ) {
		foreach ( $data as $key => $value ) {
			if ( 'warning' === $key && is_array( $value ) && isset( $value['message'] ) && is_string( $value['message'] ) ) {
				$data[ $key ]['message'] = wp_kses_post( $value['message'] );
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = $this->kses_warnings( $value );
			}
		}
		return $data;
	}

	/**
	 * Get instance
	 * @return UISchema
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Settings page copy. */
	private function settings_page() {
		return [
			'title'        => __( 'Settings', 'media-cloud-sync' ),
			'description'  => __( 'Where media is stored and how it is delivered.', 'media-cloud-sync' ),
			'storage'      => __( 'Storage', 'media-cloud-sync' ),
			'delivery'     => __( 'Delivery', 'media-cloud-sync' ),
			'change'       => __( 'Change', 'media-cloud-sync' ),
			'noDelivery'   => __( 'No delivery provider chosen', 'media-cloud-sync' ),
			'noDeliveryText' => __( 'Media is served from your server until you choose one.', 'media-cloud-sync' ),
			'unsaved'      => __( 'You have unsaved changes', 'media-cloud-sync' ),
			'syncRunning'  => __( 'Sync in progress. Please wait until it completes before saving changes.', 'media-cloud-sync' ),
			'cancel'       => __( 'Cancel', 'media-cloud-sync' ),
			'save'         => __( 'Save Changes', 'media-cloud-sync' ),
		];
	}

	/**
	 * Settings page group titles, keyed by a field's `section`.
	 * A field can set: label, desc, type, section, pro, locked, hidden, notice, action, required/validation,
	 * documentationLink/Label, warning { message, title, confirmLabel }, childrens (each sets width: half|full; full when omitted).
	 */
	private function settings_sections() {
		return [
			'uploads'      => __( 'Uploads', 'media-cloud-sync' ),
			'paths'        => __( 'Paths', 'media-cloud-sync' ),
			'fileTypes'    => __( 'File types', 'media-cloud-sync' ),
			'delivery'     => __( 'Delivery', 'media-cloud-sync' ),
			'privateMedia' => __( 'Private media', 'media-cloud-sync' ),
		];
	}

	/** Gives known fields their default `section`; a field that sets its own keeps it. */
	private function with_sections( $fields ) {
		$map = [
			'copy_to_bucket'             => 'uploads',
			'remove_from_server'         => 'uploads',
			'enable_base_path'           => 'paths',
			'year_month'                 => 'paths',
			'object_versioning'          => 'paths',
			'extensions_include_enabled' => 'fileTypes',
			'extensions_exclude_enabled' => 'fileTypes',
			'rewrite_url'                => 'delivery',
			'enable_cdn'                 => 'delivery',
			'cache_control_enabled'      => 'delivery',
			'enable_private_media'       => 'privateMedia',
		];
		foreach ( (array) $fields as $i => $field ) {
			if ( is_array( $field ) && empty( $field['section'] ) && isset( $field['name'], $map[ $field['name'] ] ) ) {
				$fields[ $i ]['section'] = $map[ $field['name'] ];
			}
		}
		return $fields;
	}

	/** Tools page copy; the cards come from the `tools` section. */
	private function tools_page() {
		return [
			'title'       => __( 'Tools', 'media-cloud-sync' ),
			'description' => __( 'One-off utilities for moving to Media Cloud Sync and maintaining your media.', 'media-cloud-sync' ),
			'open'        => __( 'Open tool', 'media-cloud-sync' ),
			'unavailable' => __( 'Connect your cloud storage to use this tool.', 'media-cloud-sync' ),
		];
	}

	/** Import / Export page copy: a settings file for another site or a staging copy. */
	private function import_export_page() {
		return [
			'title'         => __( 'Import / Export', 'media-cloud-sync' ),
			'description'   => __( 'Copy these settings to another site or a staging copy. Storage keys and other secrets are never included.', 'media-cloud-sync' ),
			'exportTitle'   => __( 'Export settings', 'media-cloud-sync' ),
			'exportText'    => __( 'Downloads a JSON file with all Settings options and the delivery provider.', 'media-cloud-sync' ),
			'exportButton'  => __( 'Download settings file', 'media-cloud-sync' ),
			'importTitle'   => __( 'Import settings', 'media-cloud-sync' ),
			'importText'    => __( 'Choose a file exported from Media Cloud Sync. You see which settings would change before anything is saved.', 'media-cloud-sync' ),
			'importButton'  => __( 'Choose settings file', 'media-cloud-sync' ),
			'importBusy'    => __( 'Wait for the running job to finish, or stop it, before importing.', 'media-cloud-sync' ),
			'importOffline' => __( 'Connect your cloud storage to import settings.', 'media-cloud-sync' ),
			'previewTitle'  => __( 'These settings will change', 'media-cloud-sync' ),
			'nothing'       => __( 'Nothing to import: the settings already match.', 'media-cloud-sync' ),
			'apply'         => __( 'Apply settings', 'media-cloud-sync' ),
			'cancel'        => __( 'Cancel', 'media-cloud-sync' ),
			'invalidJson'   => __( 'This file isn\'t valid JSON. Choose a file exported from Media Cloud Sync.', 'media-cloud-sync' ),
		];
	}

	/** License page copy; the page itself is shown only when PRO is installed. */
	private function license_page() {
		return [
			'title'        => __( 'License', 'media-cloud-sync' ),
			'description'  => __( 'Activate your license to unlock PRO features on this site.', 'media-cloud-sync' ),
			'formTitle'    => __( 'Enter your license key', 'media-cloud-sync' ),
			'formHelp'     => __( 'You can find it in your purchase email and in your Dudlewebs account.', 'media-cloud-sync' ),
			'buyText'      => __( 'Don\'t have a license yet?', 'media-cloud-sync' ),
			'buyLink'      => __( 'Get Media Cloud Sync PRO', 'media-cloud-sync' ),
			'unlockTitle'  => __( 'What your license unlocks', 'media-cloud-sync' ),
			'activeTitle'  => __( 'PRO is active on this site', 'media-cloud-sync' ),
			'expiredTitle' => __( 'Your license has expired', 'media-cloud-sync' ),
			'expiredText'  => __( 'Renew it to continue using PRO features.', 'media-cloud-sync' ),
			'renew'        => __( 'Renew license', 'media-cloud-sync' ),
		];
	}

	/** Dashboard page copy. */
	private function dashboard() {
		return [
			'title'       => __( 'Dashboard', 'media-cloud-sync' ),
			'description' => __( 'Your media library in the cloud at a glance.', 'media-cloud-sync' ),
			'syncTitle'   => __( 'Library sync', 'media-cloud-sync' ),
			'toolsTitle'  => __( 'Bulk tools', 'media-cloud-sync' ),
		];
	}

	/** Dashboard cards; `group: links` puts a card in the sidebar quick links (`last` after add-on links), others list as bulk tools. */
	private function dashboard_cards() {
		return [
			[
				'label' => __( 'Settings', 'media-cloud-sync' ),
				'description' => __( 'Paths, delivery and file options', 'media-cloud-sync' ),
				'iconBgColor' => '#DEF7FF',
				'slug' => 'settings',
				'icon' => $this->img( 'icons/settings.svg' ),
				'group' => 'links',
				'external' => false,
				'requires' => 'service',
				'link' => '/settings',
			],
			[
				'label' => __( 'Import / Export', 'media-cloud-sync' ),
				'description' => __( 'Copy settings to another site', 'media-cloud-sync' ),
				'iconBgColor' => '#DFF7E8',
				'slug' => 'import_export',
				'icon' => $this->img( 'icons/import-export.svg' ),
				'group' => 'links',
				'last' => true,
				'external' => false,
				'requires' => 'service',
				'link' => '/import-export',
			],
			[
				'label' => __( 'Sync Media to Cloud', 'media-cloud-sync' ),
				'description' => __( 'Upload media that isn\'t in your bucket yet', 'media-cloud-sync' ),
				'iconBgColor' => '#E4F0FF',
				'slug' => 'sync_to_cloud',
				'icon' => $this->img( 'icons/sync-media-to-cloud.svg' ),
				'external' => false,
				'requires' => 'operational',
				'link' => '/sync-to-cloud',
			],
			[
				'label' => __( 'Documentation', 'media-cloud-sync' ),
				'description' => __( 'Guides for every feature', 'media-cloud-sync' ),
				'iconBgColor' => '#F3CFFF',
				'slug' => 'documentation',
				'icon' => $this->img( 'icons/documentation.svg' ),
				'group' => 'links',
				'external' => true,
				'link' => 'https://dudlewebs.com/guideline/media-cloud-sync/',
			],
		];
	}

	/**
	 * Setup wizard steps: rail label, caption, icon name and page heading.
	 * Placeholders: <servicename>, <storage>, <bucket>, <documentation_link>.
	 */
	private function configure_progress_bar() {
		return [
			[
				'label'       => __( 'Welcome', 'media-cloud-sync' ),
				'slug'        => 'welcome',
				'description' => __( 'Get started', 'media-cloud-sync' ),
				'icon'        => 'sparkle',
				'title'       => __( 'Hey, There', 'media-cloud-sync' ),
				'desc'        => __( 'Welcome to Media Cloud Sync', 'media-cloud-sync' ),
			],
			[
				'label'       => __( 'Choose Provider', 'media-cloud-sync' ),
				'slug'        => 'choose_provider',
				'description' => __( 'Where your media lives', 'media-cloud-sync' ),
				'icon'        => 'cloud',
				'title'       => __( 'Choose your cloud storage', 'media-cloud-sync' ),
				'desc'        => __( 'Pick where your media should live. You can switch later.', 'media-cloud-sync' ),
			],
			[
				'label'       => __( 'Connect', 'media-cloud-sync' ),
				'slug'        => 'configure_credentials',
				'description' => __( 'Credentials & bucket', 'media-cloud-sync' ),
				'icon'        => 'plug',
				// Keyed by each provider's `configurePageSlugs`.
				'subSteps'    => [
					'setCredentials'    => [
						'label' => __( 'Credentials', 'media-cloud-sync' ),
						'icon'  => 'key',
						'title' => __( 'Connect <servicename>', 'media-cloud-sync' ),
						'desc'  => __( 'Trouble finding the credentials? View our <documentation_link>', 'media-cloud-sync' ),
					],
					'setBucket'         => [
						'label' => __( 'Bucket', 'media-cloud-sync' ),
						'icon'  => 'database',
						'title' => __( 'Choose a <storage>', 'media-cloud-sync' ),
						'desc'  => __( 'Your media is stored here. Use one you already have, or create a new one.', 'media-cloud-sync' ),
					],
					'verifyPermissions' => [
						'label' => __( 'Permissions', 'media-cloud-sync' ),
						'icon'  => 'shieldCheck',
						'title' => __( 'Checking permissions', 'media-cloud-sync' ),
						'desc'  => __( 'A few quick checks against <bucket>.', 'media-cloud-sync' ),
					],
					'security'          => [
						'label' => __( 'Security', 'media-cloud-sync' ),
						'icon'  => 'lock',
						'title' => __( '<storage> security', 'media-cloud-sync' ),
						'desc'  => __( 'These settings change the bucket itself. Current values are loaded from your account.', 'media-cloud-sync' ),
					],
				],
			],
			[
				'label'       => __( 'Save', 'media-cloud-sync' ),
				'slug'        => 'save_settings',
				'description' => __( 'Review the connection', 'media-cloud-sync' ),
				'icon'        => 'save',
				'title'       => __( 'Connected to <servicename>', 'media-cloud-sync' ),
				'desc'        => __( 'Everything checks out. Save to finish connecting your storage.', 'media-cloud-sync' ),
			],
			[
				'label'       => __( 'Delivery', 'media-cloud-sync' ),
				'slug'        => 'choose_cdn',
				'description' => __( 'How media is served', 'media-cloud-sync' ),
				'icon'        => 'globe',
				'title'       => __( 'Choose how media is delivered', 'media-cloud-sync' ),
				'desc'        => __( 'Serve straight from your storage, or put a CDN in front for speed.', 'media-cloud-sync' ),
			],
		];
	}

	/**
	 * Shared setup wizard text: rail heading, help card and the Save step.
	 * The help card uses `general*` until a provider is chosen, then that provider's quick start.
	 */
	private function wizard() {
		return [
			/* translators: %1$s: current step number, %2$s: number of steps */
			'stepOf'   => __( 'Setup · step %1$s of %2$s', 'media-cloud-sync' ),
			'help'     => [
				'title'       => __( 'Need a hand?', 'media-cloud-sync' ),
				'text'        => __( 'Follow the <servicename> quick start.', 'media-cloud-sync' ),
				'generalText' => __( 'Read the Media Cloud Sync setup guide.', 'media-cloud-sync' ),
				'link'        => __( 'Open guide', 'media-cloud-sync' ),
				'url'         => 'https://dudlewebs.com/guideline/media-cloud-sync/',
			],
			'saved'    => __( 'Saved', 'media-cloud-sync' ),
			'saveNote' => __( 'Nothing is uploaded yet. After saving, choose how media is delivered, then run Sync Media to Cloud from the Dashboard.', 'media-cloud-sync' ),
		];
	}

	/** Setup wizard footer buttons per step. */
	private function configure_footer() {
		return [
			'welcome' => [
				'labels' => [
					'back' => __( 'Back', 'media-cloud-sync' ),
					'next' => __( 'Get started', 'media-cloud-sync' ),
				],
				'display' => [
					'back' => false,
					'next' => true,
				],
			],
			'choose_provider' => [
				'labels' => [
					'back' => __( 'Back', 'media-cloud-sync' ),
					'next' => __( 'Next', 'media-cloud-sync' ),
				],
				'display' => [
					'back' => true,
					'next' => true,
				],
			],
			'configure_credentials' => [
				'labels' => [
					'connect' => [
						'back' => __( 'Back', 'media-cloud-sync' ),
						'next' => __( 'Verify credentials', 'media-cloud-sync' ),
						'icon' => 'shieldCheck',
					],
					'bucket' => [
						'back' => __( 'Back', 'media-cloud-sync' ),
						'next' => __( 'Verify bucket', 'media-cloud-sync' ),
						'icon' => 'shieldCheck',
					],
					'checkPermission' => [
						'back' => __( 'Back', 'media-cloud-sync' ),
						'next' => __( 'Next', 'media-cloud-sync' ),
					],
					'security' => [
						'back' => __( 'Back', 'media-cloud-sync' ),
						'next' => __( 'Next', 'media-cloud-sync' ),
					],
				],
				'display' => [
					'back' => true,
					'next' => true,
				],
			],
			'save_settings' => [
				'labels' => [
					'back' => __( 'Back', 'media-cloud-sync' ),
					'next' => __( 'Save and continue', 'media-cloud-sync' ),
					'icon' => 'save',
				],
				'display' => [
					'back' => true,
					'next' => true,
				],
			],
		];
	}

	/** Storage providers: wizard fields, bucket step, security, storage settings. */
	private function services_section() {
		return [
			's3' => [
				'label' => __( 'Amazon S3', 'media-cloud-sync' ),
				'mainLabel' => __( 'Amazon S3', 'media-cloud-sync' ),
				'slug' => 's3',
				'icon' => $this->img( 's3.png' ),
				'description' => __( 'Amazon S3 is an object storage service that offers high scalability, durability, and security for data storage and retrieval across the web.', 'media-cloud-sync' ),
				'documentation' => [
					'external' => true,
					'link' => 'https://dudlewebs.com/guideline/media-cloud-sync/get-started/quick-start-amazon-web-services-aws',
				],
				'consoleLink' => 'https://console.aws.amazon.com/s3/buckets/<bucket_name>/',
				'fields' => [
					[
						'type' => 'text',
						'label' => __( 'Access Key', 'media-cloud-sync' ),
						'name' => 'access_key',
						'icon' => 'key',
						'placeholder' => __( 'Enter access key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'text',
						'label' => __( 'Secret Key', 'media-cloud-sync' ),
						'name' => 'secret_key',
						'icon' => 'lock',
						'placeholder' => __( 'Enter secret key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'select',
						'label' => __( 'Region', 'media-cloud-sync' ),
						'name' => 'region',
						'icon' => 'map',
						'placeholder' => __( 'Choose region', 'media-cloud-sync' ),
						'options' => [
							[
								'label' => 'US East (N. Virginia)',
								'value' => 'us-east-1',
							],
							[
								'label' => 'US East (Ohio)',
								'value' => 'us-east-2',
							],
							[
								'label' => 'US West (N. California)',
								'value' => 'us-west-1',
							],
							[
								'label' => 'US West (Oregon)',
								'value' => 'us-west-2',
							],
							[
								'label' => 'Africa (Cape Town)',
								'value' => 'af-south-1',
							],
							[
								'label' => 'Asia Pacific (Hong Kong)',
								'value' => 'ap-east-1',
							],
							[
								'label' => 'Asia Pacific (Hyderabad)',
								'value' => 'ap-south-2',
							],
							[
								'label' => 'Asia Pacific (Jakarta)',
								'value' => 'ap-southeast-3',
							],
							[
								'label' => 'Asia Pacific (Malaysia)',
								'value' => 'ap-southeast-5',
							],
							[
								'label' => 'Asia Pacific (Melbourne)',
								'value' => 'ap-southeast-4',
							],
							[
								'label' => 'Asia Pacific (Mumbai)',
								'value' => 'ap-south-1',
							],
							[
								'label' => 'Asia Pacific (New Zealand)',
								'value' => 'ap-southeast-6',
							],
							[
								'label' => 'Asia Pacific (Osaka)',
								'value' => 'ap-northeast-3',
							],
							[
								'label' => 'Asia Pacific (Seoul)',
								'value' => 'ap-northeast-2',
							],
							[
								'label' => 'Asia Pacific (Singapore)',
								'value' => 'ap-southeast-1',
							],
							[
								'label' => 'Asia Pacific (Sydney)',
								'value' => 'ap-southeast-2',
							],
							[
								'label' => 'Asia Pacific (Taipei)',
								'value' => 'ap-east-2',
							],
							[
								'label' => 'Asia Pacific (Thailand)',
								'value' => 'ap-southeast-7',
							],
							[
								'label' => 'Asia Pacific (Tokyo)',
								'value' => 'ap-northeast-1',
							],
							[
								'label' => 'Canada (Central)',
								'value' => 'ca-central-1',
							],
							[
								'label' => 'Canada West (Calgary)',
								'value' => 'ca-west-1',
							],
							[
								'label' => 'China (Beijing)',
								'value' => 'cn-north-1',
							],
							[
								'label' => 'China (Ningxia)',
								'value' => 'cn-northwest-1',
							],
							[
								'label' => 'Europe (Frankfurt)',
								'value' => 'eu-central-1',
							],
							[
								'label' => 'Europe (Ireland)',
								'value' => 'eu-west-1',
							],
							[
								'label' => 'Europe (London)',
								'value' => 'eu-west-2',
							],
							[
								'label' => 'Europe (Milan)',
								'value' => 'eu-south-1',
							],
							[
								'label' => 'Europe (Paris)',
								'value' => 'eu-west-3',
							],
							[
								'label' => 'Europe (Spain)',
								'value' => 'eu-south-2',
							],
							[
								'label' => 'Europe (Stockholm)',
								'value' => 'eu-north-1',
							],
							[
								'label' => 'Europe (Zurich)',
								'value' => 'eu-central-2',
							],
							[
								'label' => 'Israel (Tel Aviv)',
								'value' => 'il-central-1',
							],
							[
								'label' => 'Mexico (Central)',
								'value' => 'mx-central-1',
							],
							[
								'label' => 'Middle East (Bahrain)',
								'value' => 'me-south-1',
							],
							[
								'label' => 'Middle East (Saudi Arabia)',
								'value' => 'me-central-2',
							],
							[
								'label' => 'Middle East (UAE)',
								'value' => 'me-central-1',
							],
							[
								'label' => 'South America (Chile)',
								'value' => 'sa-west-1',
							],
							[
								'label' => 'South America (São Paulo)',
								'value' => 'sa-east-1',
							],
							[
								'label' => 'AWS GovCloud (US-East)',
								'value' => 'us-gov-east-1',
							],
							[
								'label' => 'AWS GovCloud (US-West)',
								'value' => 'us-gov-west-1',
							],
						],
						'validation' => [
							'required' => true,
						],
					],
				],
				'storage' => [
					'label' => __( 'Bucket', 'media-cloud-sync' ),
					'showManualInput' => true,
					'fields' => [
						[
							'type' => 'select',
							'isTextBoxType' => 'text',
							'label' => __( 'Choose Bucket', 'media-cloud-sync' ),
							'name' => 'bucket_name',
							'icon' => 'database',
							'placeholder' => __( 'Choose Bucket', 'media-cloud-sync' ),
							'validation' => [
								'required' => true,
							],
						],
						[
							'type' => 'toggle',
							'label' => __( 'Use Transfer Acceleration', 'media-cloud-sync' ),
							'name' => 'transfer_acceleration',
							'validation' => [],
						],
					],
					'addNew' => [
						'enable' => true,
						'fields' => [
							[
								'type' => 'text',
								'label' => __( 'Bucket Name', 'media-cloud-sync' ),
								'name' => 'bucket_name',
								'icon' => 'database',
								'placeholder' => __( 'Enter Bucket Name', 'media-cloud-sync' ),
								'validation' => [
									'required' => true,
									'length' => 63,
									'regex' => [
										'match' => true,
										'pattern' => [ '__regex' => '(?=^.{3,63}$)(?!^(\\d+\\.)+\\d+$)(^(([a-z0-9]|[a-z0-9][a-z0-9-]*[a-z0-9])\\.)*([a-z0-9]|[a-z0-9][a-z0-9-]*[a-z0-9])$)', 'flags' => '' ],
									],
								],
							],
							[
								'type' => 'toggle',
								'label' => __( 'Use Transfer Acceleration', 'media-cloud-sync' ),
								'name' => 'transfer_acceleration',
								'validation' => [],
							],
						],
					],
				],
				'security' => [
					'label' => __( 'Security', 'media-cloud-sync' ),
					'fields' => [
						[
							'type' => 'toggle',
							'label' => __( 'Block All Public Access', 'media-cloud-sync' ),
							'desc' => __( 'Since you are not using Amazon CloudFront for delivery, we recommend you keep Block All Public Access disabled, unless you have a very good reason to enable it.', 'media-cloud-sync' ),
							'endpoint' => 'block_public_access',
							'name' => 'block_public_access',
						],
						[
							'type' => 'toggle',
							'label' => __( 'Enforce Object Ownership', 'media-cloud-sync' ),
							'desc' => __( 'Since you are not using Amazon CloudFront for delivery, we recommend you do not enforce Object Ownership, unless you have a very good reason to do so.', 'media-cloud-sync' ),
							'endpoint' => 'object_ownership_enforce',
							'icon' => 'user',
							'name' => 'object_ownership_enforce',
						],
					],
				],
				'configurePageSlugs' => [
					'setCredentials',
					'setBucket',
					'verifyPermissions',
					'security',
				],
				'configSource' => [
					'database' => [
						'title' => __( 'Store in Database', 'media-cloud-sync' ),
						'description' => __( 'Enter your AWS IAM access key, secret key, and region below.', 'media-cloud-sync' ),
					],
					'config' => [
						'title' => __( 'Use wp-config.php Constant', 'media-cloud-sync' ),
						'description' => __( 'Keep credentials out of the database by defining a WPMCS_CONFIG constant in wp-config.php. More secure than storing in the database.', 'media-cloud-sync' ),
						'recommended' => true,
						'instruction' => __( 'Add the following to your wp-config.php (above the “That’s all, stop editing!” line) using your AWS IAM credentials:', 'media-cloud-sync' ),
						'note' => __( 'Once it’s added, continue — we’ll verify the constant on the server.', 'media-cloud-sync' ),
						'regionHint' => __( 'Pick an AWS region and copy its value for the region key in wp-config.php.', 'media-cloud-sync' ),
						'snippet' => "define( 'WPMCS_CONFIG', [\n    'access_key' => 'YOUR_ACCESS_KEY',\n    'secret_key' => 'YOUR_SECRET_KEY',\n    'region'     => 'YOUR_REGION',\n] );",
					],
				],
				'settings' => [
					[
						'label' => __( 'Copy files to bucket', 'media-cloud-sync' ),
						'desc' => __( 'Upload to bucket, when ever a file is uploaded to Media Library', 'media-cloud-sync' ),
						'name' => 'copy_to_bucket',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Remove files from server', 'media-cloud-sync' ),
						'desc' => __( 'Use this to remove files from the server once they are uploaded to the bucket.', 'media-cloud-sync' ),
						'warning' => [
							'enable' => true,
							'message' => __( '<p>This setting permanently deletes files from the server once they\'re uploaded to your bucket — be careful before enabling it.</p><p>If you stop using this plugin, you will need to restore these files manually, by downloading them from the cloud and re-uploading via FTP, or automatically using our PRO version\'s restore feature.</p>', 'media-cloud-sync' ),
						],
						'name' => 'remove_from_server',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Add Prefix to Bucket Path', 'media-cloud-sync' ),
						'desc' => __( 'Enable this option if you need a custom prefix for your bucket path in Amazon S3', 'media-cloud-sync' ),
						'name' => 'enable_base_path',
						'type' => 'toggle',
						'childrens' => [
							[
								'label' => '',
								'desc' => '',
								'name' => 'base_path',
								'type' => 'text',
								'required' => false,
								'placeholder' => __( 'Enter Prefix', 'media-cloud-sync' ),
								'values' => '',
							],
						],
					],
					[
						'label' => __( 'Year/Month Structure', 'media-cloud-sync' ),
						'desc' => __( 'This will enable year/month directories at the end of your base path. Otherwise it will be uploaded in to the base path directly.', 'media-cloud-sync' ),
						'name' => 'year_month',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Object versioning', 'media-cloud-sync' ),
						'desc' => __( 'Append a timestamp to the bucket path.', 'media-cloud-sync' ),
						'name' => 'object_versioning',
						'type' => 'toggle',
					],
				],
			],
			'gcloud' => [
				'label' => __( 'Google Cloud Storage', 'media-cloud-sync' ),
				'mainLabel' => __( 'Google Cloud Storage', 'media-cloud-sync' ),
				'slug' => 'gcloud',
				'icon' => $this->img( 'google.png' ),
				'configSource' => [
					'database' => [
						'title' => __( 'Store in Database', 'media-cloud-sync' ),
						'description' => __( 'Paste your Google Cloud service account JSON below.', 'media-cloud-sync' ),
					],
					'config' => [
						'title' => __( 'Use wp-config.php Constant', 'media-cloud-sync' ),
						'description' => __( 'Keep credentials out of the database by defining a WPMCS_CONFIG constant in wp-config.php. More secure than storing in the database.', 'media-cloud-sync' ),
						'recommended' => true,
						'instruction' => __( 'Add the following to your wp-config.php (above the “That’s all, stop editing!” line). Paste your service account JSON as a single-line string:', 'media-cloud-sync' ),
						'note' => __( 'Once it’s added, continue — we’ll verify the constant on the server.', 'media-cloud-sync' ),
						'snippet' => "define( 'WPMCS_CONFIG', [\n    'config_json' => 'PASTE_YOUR_SERVICE_ACCOUNT_JSON_HERE',\n] );",
					],
				],
				'description' => __( 'Google Cloud Storage is a unified object storage service that provides high scalability, durability, and seamless integration with Google Cloud services for efficient data management and access.', 'media-cloud-sync' ),
				'documentation' => [
					'external' => true,
					'link' => 'https://dudlewebs.com/support',
				],
				'consoleLink' => 'https://console.cloud.google.com/storage/browser/<bucket_name>/',
				'fields' => [
					[
						'type' => 'jsonReader',
						'label' => __( 'JSON Config File', 'media-cloud-sync' ),
						'name' => 'config_json',
						'placeholder' => __( 'Add JSON Code here', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
						],
					],
				],
				'storage' => [
					'label' => __( 'Bucket', 'media-cloud-sync' ),
					'showManualInput' => true,
					'fields' => [
						[
							'type' => 'select',
							'isTextBoxType' => 'text',
							'label' => __( 'Choose Bucket', 'media-cloud-sync' ),
							'name' => 'bucket_name',
							'icon' => 'database',
							'placeholder' => __( 'Choose Bucket', 'media-cloud-sync' ),
							'validation' => [
								'required' => true,
							],
						],
					],
					'addNew' => [
						'enable' => true,
						'fields' => [
							[
								'type' => 'text',
								'label' => __( 'Bucket Name', 'media-cloud-sync' ),
								'name' => 'bucket_name',
								'icon' => 'database',
								'placeholder' => __( 'Enter Bucket Name', 'media-cloud-sync' ),
								'validation' => [
									'required' => true,
									'length' => 63,
									'regex' => [
										'match' => true,
										'pattern' => [ '__regex' => '^(?!goog)(?!.*\\bgoogle\\b)(?!.*\\bg00gle\\b)(?!\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}$)(?!.*[._-]{2})[a-z0-9][a-z0-9._-]{1,61}[a-z0-9](?:\\.[a-z0-9][a-z0-9._-]{0,61}[a-z0-9]){0,3}?$', 'flags' => '' ],
									],
								],
							],
							[
								'type' => 'select',
								'label' => __( 'Region', 'media-cloud-sync' ),
								'name' => 'region',
								'icon' => 'map',
								'placeholder' => __( 'Choose region', 'media-cloud-sync' ),
								'options' => [
									[
										'value' => 'asia',
										'label' => 'Multi-Region (Asia)',
									],
									[
										'value' => 'eu',
										'label' => 'Multi-Region (EU)',
									],
									[
										'value' => 'us',
										'label' => 'Multi-Region (US)',
									],
									[
										'value' => 'us-central1',
										'label' => 'North America (Iowa)',
									],
									[
										'value' => 'us-east1',
										'label' => 'North America (South Carolina)',
									],
									[
										'value' => 'us-east4',
										'label' => 'North America (Northern Virginia)',
									],
									[
										'value' => 'us-east5',
										'label' => 'North America (Columbus)',
									],
									[
										'value' => 'us-south1',
										'label' => 'North America (Dallas)',
									],
									[
										'value' => 'us-west1',
										'label' => 'North America (Oregon)',
									],
									[
										'value' => 'us-west2',
										'label' => 'North America (Los Angeles)',
									],
									[
										'value' => 'us-west3',
										'label' => 'North America (Salt Lake City)',
									],
									[
										'value' => 'us-west4',
										'label' => 'North America (Las Vegas)',
									],
									[
										'value' => 'northamerica-northeast1',
										'label' => 'North America (Montréal)',
									],
									[
										'value' => 'northamerica-northeast2',
										'label' => 'North America (Toronto)',
									],
									[
										'value' => 'northamerica-south1',
										'label' => 'North America (Querétaro, Mexico)',
									],
									[
										'value' => 'southamerica-east1',
										'label' => 'South America (São Paulo)',
									],
									[
										'value' => 'southamerica-west1',
										'label' => 'South America (Santiago)',
									],
									[
										'value' => 'europe-central2',
										'label' => 'Europe (Warsaw)',
									],
									[
										'value' => 'europe-north1',
										'label' => 'Europe (Finland)',
									],
									[
										'value' => 'europe-north2',
										'label' => 'Europe (Stockholm)',
									],
									[
										'value' => 'europe-southwest1',
										'label' => 'Europe (Madrid)',
									],
									[
										'value' => 'europe-west1',
										'label' => 'Europe (Belgium)',
									],
									[
										'value' => 'europe-west2',
										'label' => 'Europe (London)',
									],
									[
										'value' => 'europe-west3',
										'label' => 'Europe (Frankfurt)',
									],
									[
										'value' => 'europe-west4',
										'label' => 'Europe (Netherlands)',
									],
									[
										'value' => 'europe-west6',
										'label' => 'Europe (Zürich)',
									],
									[
										'value' => 'europe-west8',
										'label' => 'Europe (Milan)',
									],
									[
										'value' => 'europe-west9',
										'label' => 'Europe (Paris)',
									],
									[
										'value' => 'europe-west10',
										'label' => 'Europe (Berlin)',
									],
									[
										'value' => 'europe-west12',
										'label' => 'Europe (Turin)',
									],
									[
										'value' => 'africa-south1',
										'label' => 'Africa (Johannesburg)',
									],
									[
										'value' => 'me-central1',
										'label' => 'Middle East (Doha)',
									],
									[
										'value' => 'me-central2',
										'label' => 'Middle East (Dammam, Saudi Arabia)',
									],
									[
										'value' => 'me-west1',
										'label' => 'Middle East (Tel Aviv)',
									],
									[
										'value' => 'asia-east1',
										'label' => 'Asia (Taiwan)',
									],
									[
										'value' => 'asia-east2',
										'label' => 'Asia (Hong Kong)',
									],
									[
										'value' => 'asia-northeast1',
										'label' => 'Asia (Tokyo)',
									],
									[
										'value' => 'asia-northeast2',
										'label' => 'Asia (Osaka)',
									],
									[
										'value' => 'asia-northeast3',
										'label' => 'Asia (Seoul)',
									],
									[
										'value' => 'asia-south1',
										'label' => 'India (Mumbai)',
									],
									[
										'value' => 'asia-south2',
										'label' => 'India (Delhi)',
									],
									[
										'value' => 'asia-southeast1',
										'label' => 'Asia (Singapore)',
									],
									[
										'value' => 'asia-southeast2',
										'label' => 'Indonesia (Jakarta)',
									],
									[
										'value' => 'asia-southeast3',
										'label' => 'Asia (Bangkok)',
									],
									[
										'value' => 'australia-southeast1',
										'label' => 'Australia (Sydney)',
									],
									[
										'value' => 'australia-southeast2',
										'label' => 'Australia (Melbourne)',
									],
									[
										'value' => 'asia1',
										'label' => 'Dual-Region (Tokyo/Osaka)',
									],
									[
										'value' => 'eur4',
										'label' => 'Dual-Region (Finland/Netherlands)',
									],
									[
										'value' => 'eur5',
										'label' => 'Dual-Region (Belgium/London)',
									],
									[
										'value' => 'eur6',
										'label' => 'Dual-Region (Frankfurt/Munich)',
									],
									[
										'value' => 'nam4',
										'label' => 'Dual-Region (Iowa/South Carolina)',
									],
								],
								'validation' => [
									'required' => true,
								],
							],
						],
					],
				],
				'security' => [
					'label' => __( 'Security', 'media-cloud-sync' ),
					'fields' => [
						[
							'type' => 'toggle',
							'label' => __( 'Block All Public Access', 'media-cloud-sync' ),
							'desc' => __( 'Enforces Public Access Prevention on this bucket, blocking all public access regardless of any other setting. If Private Media is enabled on this connection, this also breaks the public side of that feature — enabling this and Private Media together is not recommended.', 'media-cloud-sync' ),
							'endpoint' => 'block_public_access',
							'name' => 'block_public_access',
						],
					],
				],
				'configurePageSlugs' => [
					'setCredentials',
					'setBucket',
					'verifyPermissions',
					'security',
				],
				'settings' => [
					[
						'label' => __( 'Copy files to bucket', 'media-cloud-sync' ),
						'desc' => __( 'Upload to bucket, when ever a file is uploaded to Media Library', 'media-cloud-sync' ),
						'name' => 'copy_to_bucket',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Remove files from server', 'media-cloud-sync' ),
						'desc' => __( 'Use this to remove files from the server once they are uploaded to the bucket.', 'media-cloud-sync' ),
						'warning' => [
							'enable' => true,
							'message' => __( '<p>This setting permanently deletes files from the server once they\'re uploaded to your bucket — be careful before enabling it.</p><p>If you stop using this plugin, you will need to restore these files manually, by downloading them from the cloud and re-uploading via FTP, or automatically using our PRO version\'s restore feature.</p>', 'media-cloud-sync' ),
						],
						'name' => 'remove_from_server',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Add Prefix to Bucket Path', 'media-cloud-sync' ),
						'desc' => __( 'Enable this option if you need a custom prefix for your bucket path in Google Cloud Storage', 'media-cloud-sync' ),
						'name' => 'enable_base_path',
						'type' => 'toggle',
						'childrens' => [
							[
								'label' => '',
								'desc' => '',
								'name' => 'base_path',
								'type' => 'text',
								'required' => false,
								'placeholder' => __( 'Enter Prefix', 'media-cloud-sync' ),
								'values' => '',
							],
						],
					],
					[
						'label' => __( 'Year/Month Structure', 'media-cloud-sync' ),
						'desc' => __( 'This will enable year/month directories at the end of your base path. Otherwise it will be uploaded in to the base path directly.', 'media-cloud-sync' ),
						'name' => 'year_month',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Object versioning', 'media-cloud-sync' ),
						'desc' => __( 'Append a timestamp to the bucket path.', 'media-cloud-sync' ),
						'name' => 'object_versioning',
						'type' => 'toggle',
					],
				],
			],
			'docean' => [
				'label' => __( 'Digital Ocean Spaces', 'media-cloud-sync' ),
				'mainLabel' => __( 'Digital Ocean Spaces', 'media-cloud-sync' ),
				'slug' => 'docean',
				'icon' => $this->img( 'ocean.png' ),
				'configSource' => [
					'database' => [
						'title' => __( 'Store in Database', 'media-cloud-sync' ),
						'description' => __( 'Enter your DigitalOcean Spaces access key, secret key, and region below.', 'media-cloud-sync' ),
					],
					'config' => [
						'title' => __( 'Use wp-config.php Constant', 'media-cloud-sync' ),
						'description' => __( 'Keep credentials out of the database by defining a WPMCS_CONFIG constant in wp-config.php. More secure than storing in the database.', 'media-cloud-sync' ),
						'recommended' => true,
						'instruction' => __( 'Add the following to your wp-config.php (above the “That’s all, stop editing!” line) using your Spaces API credentials:', 'media-cloud-sync' ),
						'note' => __( 'Once it’s added, continue — we’ll verify the constant on the server.', 'media-cloud-sync' ),
						'regionHint' => __( 'Pick a Spaces region (e.g. nyc3, sfo3) and copy its value for wp-config.php.', 'media-cloud-sync' ),
						'snippet' => "define( 'WPMCS_CONFIG', [\n    'access_key' => 'YOUR_ACCESS_KEY',\n    'secret_key' => 'YOUR_SECRET_KEY',\n    'region'     => 'YOUR_REGION',\n] );",
					],
				],
				'description' => __( 'DigitalOcean Spaces is a cost-effective object storage solution designed for simplicity, scalability, and high availability, ideal for storing and serving large amounts of unstructured data', 'media-cloud-sync' ),
				'documentation' => [
					'external' => true,
					'link' => 'https://dudlewebs.com/support',
				],
				'consoleLink' => 'https://cloud.digitalocean.com/spaces/<region>/<bucket_name>/',
				'fields' => [
					[
						'type' => 'text',
						'label' => __( 'Access Key', 'media-cloud-sync' ),
						'name' => 'access_key',
						'icon' => 'key',
						'placeholder' => __( 'Enter access key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'text',
						'label' => __( 'Secret Key', 'media-cloud-sync' ),
						'name' => 'secret_key',
						'icon' => 'lock',
						'placeholder' => __( 'Enter secret key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'select',
						'label' => __( 'Region', 'media-cloud-sync' ),
						'placeholder' => __( 'Choose region', 'media-cloud-sync' ),
						'name' => 'region',
						'icon' => 'map',
						'options' => [
							[
								'label' => 'New York City (NYC1)',
								'value' => 'nyc1',
							],
							[
								'label' => 'New York City (NYC2)',
								'value' => 'nyc2',
							],
							[
								'label' => 'New York City (NYC3)',
								'value' => 'nyc3',
							],
							[
								'label' => 'San Francisco (SFO1)',
								'value' => 'sfo1',
							],
							[
								'label' => 'San Francisco (SFO2)',
								'value' => 'sfo2',
							],
							[
								'label' => 'San Francisco (SFO3)',
								'value' => 'sfo3',
							],
							[
								'label' => 'Amsterdam (AMS2)',
								'value' => 'ams2',
							],
							[
								'label' => 'Amsterdam (AMS3)',
								'value' => 'ams3',
							],
							[
								'label' => 'Singapore (SGP1)',
								'value' => 'sgp1',
							],
							[
								'label' => 'London (LON1)',
								'value' => 'lon1',
							],
							[
								'label' => 'Frankfurt (FRA1)',
								'value' => 'fra1',
							],
							[
								'label' => 'Toronto (TOR1)',
								'value' => 'tor1',
							],
							[
								'label' => 'Bangalore (BLR1)',
								'value' => 'blr1',
							],
							[
								'label' => 'Sydney (SYD1)',
								'value' => 'syd1',
							],
							[
								'label' => 'Atlanta (ATL1)',
								'value' => 'atl1',
							],
						],
						'validation' => [
							'required' => true,
						],
					],
				],
				'storage' => [
					'label' => __( 'Space', 'media-cloud-sync' ),
					'showManualInput' => false,
					'fields' => [
						[
							'type' => 'text',
							'label' => __( 'Enter Space Name', 'media-cloud-sync' ),
							'name' => 'bucket_name',
							'icon' => 'database',
							'placeholder' => __( 'Enter Space Name', 'media-cloud-sync' ),
							'validation' => [
								'required' => true,
							],
						],
					],
					'addNew' => [
						'enable' => true,
						'fields' => [
							[
								'type' => 'text',
								'label' => __( 'Enter Space Name', 'media-cloud-sync' ),
								'name' => 'bucket_name',
								'icon' => 'database',
								'placeholder' => __( 'Enter Space Name', 'media-cloud-sync' ),
								'validation' => [
									'required' => true,
									'length' => 63,
									'regex' => [
										'match' => true,
										'pattern' => [ '__regex' => '(?=^.{3,63}$)(?!^(\\d+\\.)+\\d+$)(^(([a-z0-9]|[a-z0-9][a-z0-9-]*[a-z0-9])\\.)*([a-z0-9]|[a-z0-9][a-z0-9-]*[a-z0-9])$)', 'flags' => '' ],
									],
								],
							],
						],
					],
				],
				'configurePageSlugs' => [
					'setCredentials',
					'setBucket',
					'verifyPermissions',
				],
				'settings' => [
					[
						'label' => __( 'Copy files to bucket', 'media-cloud-sync' ),
						'desc' => __( 'Upload to bucket, when ever a file is uploaded to Media Library', 'media-cloud-sync' ),
						'name' => 'copy_to_bucket',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Remove files from server', 'media-cloud-sync' ),
						'desc' => __( 'Use this to remove files from the server once they are uploaded to the bucket.', 'media-cloud-sync' ),
						'warning' => [
							'enable' => true,
							'message' => __( '<p>This setting permanently deletes files from the server once they\'re uploaded to your bucket — be careful before enabling it.</p><p>If you stop using this plugin, you will need to restore these files manually, by downloading them from the cloud and re-uploading via FTP, or automatically using our PRO version\'s restore feature.</p>', 'media-cloud-sync' ),
						],
						'name' => 'remove_from_server',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Add Prefix to Bucket Path', 'media-cloud-sync' ),
						'desc' => __( 'Enable this option if you need a custom prefix for your bucket path in Digital Ocean Spaces.', 'media-cloud-sync' ),
						'name' => 'enable_base_path',
						'type' => 'toggle',
						'childrens' => [
							[
								'label' => '',
								'desc' => '',
								'name' => 'base_path',
								'type' => 'text',
								'required' => false,
								'placeholder' => __( 'Enter Prefix', 'media-cloud-sync' ),
								'values' => '',
							],
						],
					],
					[
						'label' => __( 'Year/Month Structure', 'media-cloud-sync' ),
						'desc' => __( 'This will enable year/month directories at the end of your base path. Otherwise it will be uploaded in to the base path directly.', 'media-cloud-sync' ),
						'name' => 'year_month',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Object versioning', 'media-cloud-sync' ),
						'desc' => __( 'Append a timestamp to the bucket path.', 'media-cloud-sync' ),
						'name' => 'object_versioning',
						'type' => 'toggle',
					],
				],
			],
			'cloudflareR2' => [
				'label' => __( 'Cloudflare R2', 'media-cloud-sync' ),
				'mainLabel' => __( 'Cloudflare R2', 'media-cloud-sync' ),
				'slug' => 'cloudflareR2',
				'icon' => $this->img( 'cloudflareR2.png' ),
				'configSource' => [
					'database' => [
						'title' => __( 'Store in Database', 'media-cloud-sync' ),
						'description' => __( 'Enter your Cloudflare account ID, R2 access key, secret key, and region below.', 'media-cloud-sync' ),
					],
					'config' => [
						'title' => __( 'Use wp-config.php Constant', 'media-cloud-sync' ),
						'description' => __( 'Keep credentials out of the database by defining a WPMCS_CONFIG constant in wp-config.php. More secure than storing in the database.', 'media-cloud-sync' ),
						'recommended' => true,
						'instruction' => __( 'Add the following to your wp-config.php (above the “That’s all, stop editing!” line) using your Cloudflare R2 API credentials:', 'media-cloud-sync' ),
						'note' => __( 'Once it’s added, continue — we’ll verify the constant on the server.', 'media-cloud-sync' ),
						'regionHint' => __( 'Pick a jurisdiction/region and copy its value for wp-config.php.', 'media-cloud-sync' ),
						'snippet' => "define( 'WPMCS_CONFIG', [\n    'account_id' => 'YOUR_ACCOUNT_ID',\n    'access_key' => 'YOUR_ACCESS_KEY',\n    'secret_key' => 'YOUR_SECRET_KEY',\n    'region'     => 'YOUR_REGION',\n] );",
					],
				],
				'description' => __( 'Cloudflare R2 is a globally distributed, zero-egress-fee object storage service designed for high performance, reliability, and massive scalability.', 'media-cloud-sync' ),
				'documentation' => [
					'external' => true,
					'link' => 'https://dudlewebs.com/support',
				],
				'consoleLink' => 'https://dash.cloudflare.com/',
				'fields' => [
					[
						'type' => 'text',
						'label' => __( 'Account ID', 'media-cloud-sync' ),
						'name' => 'account_id',
						'icon' => 'user',
						'placeholder' => __( 'Enter Account ID', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'select',
						'label' => __( 'Data Jurisdiction / Region', 'media-cloud-sync' ),
						'placeholder' => __( 'Choose region', 'media-cloud-sync' ),
						'name' => 'region',
						'icon' => 'map',
						'options' => [
							[
								'label' => 'Global (Default)',
								'value' => '',
								'default' => true,
							],
							[
								'label' => 'European Union (EU)',
								'value' => 'eu',
							],
							[
								'label' => 'FedRAMP Compliant',
								'value' => 'fedramp',
							],
						],
						'validation' => [],
					],
					[
						'type' => 'text',
						'label' => __( 'Access Key', 'media-cloud-sync' ),
						'name' => 'access_key',
						'icon' => 'key',
						'placeholder' => __( 'Enter Access Key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'text',
						'label' => __( 'Secret Key', 'media-cloud-sync' ),
						'name' => 'secret_key',
						'icon' => 'lock',
						'placeholder' => __( 'Enter Secret Key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
				],
				'storage' => [
					'label' => __( 'Bucket', 'media-cloud-sync' ),
					'showManualInput' => true,
					'fields' => [
						[
							'type' => 'select',
							'isTextBoxType' => 'text',
							'label' => __( 'Choose Bucket', 'media-cloud-sync' ),
							'name' => 'bucket_name',
							'icon' => 'database',
							'placeholder' => __( 'Choose Bucket', 'media-cloud-sync' ),
							'validation' => [
								'required' => true,
							],
						],
					],
					'addNew' => [
						'enable' => true,
						'fields' => [
							[
								'type' => 'text',
								'label' => __( 'Enter Bucket Name', 'media-cloud-sync' ),
								'name' => 'bucket_name',
								'icon' => 'database',
								'placeholder' => __( 'Enter Bucket Name', 'media-cloud-sync' ),
								'validation' => [
									'required' => true,
									'length' => 63,
									'regex' => [
										'match' => true,
										'pattern' => [ '__regex' => '(?=^.{3,63}$)(?!^(\\d+\\.)+\\d+$)(^(([a-z0-9]|[a-z0-9][a-z0-9-]*[a-z0-9])\\.)*([a-z0-9]|[a-z0-9][a-z0-9-]*[a-z0-9])$)', 'flags' => '' ],
									],
								],
							],
						],
					],
				],
				'configurePageSlugs' => [
					'setCredentials',
					'setBucket',
					'verifyPermissions',
				],
				'settings' => [
					[
						'label' => __( 'Copy files to bucket', 'media-cloud-sync' ),
						'desc' => __( 'Upload to the bucket whenever a file is uploaded to the Media Library.', 'media-cloud-sync' ),
						'name' => 'copy_to_bucket',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Remove files from server', 'media-cloud-sync' ),
						'desc' => __( 'Remove the server file once it is successfully uploaded to the bucket.', 'media-cloud-sync' ),
						'warning' => [
							'enable' => true,
							'message' => __( '<p>This setting permanently deletes files from the server once they\'re uploaded to your bucket — be careful before enabling it.</p><p>If you stop using this plugin, you will need to restore these files manually, by downloading them from the cloud and re-uploading via FTP, or automatically using our PRO version\'s restore feature.</p>', 'media-cloud-sync' ),
						],
						'name' => 'remove_from_server',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Add Prefix to Bucket Path', 'media-cloud-sync' ),
						'desc' => __( 'Enable this option if you want a custom folder prefix for your uploaded files.', 'media-cloud-sync' ),
						'name' => 'enable_base_path',
						'type' => 'toggle',
						'childrens' => [
							[
								'label' => '',
								'desc' => '',
								'name' => 'base_path',
								'type' => 'text',
								'placeholder' => __( 'Enter Prefix', 'media-cloud-sync' ),
								'required' => false,
							],
						],
					],
					[
						'label' => __( 'Year/Month Structure', 'media-cloud-sync' ),
						'desc' => __( 'Automatically create year/month folders inside your base path.', 'media-cloud-sync' ),
						'name' => 'year_month',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Object versioning', 'media-cloud-sync' ),
						'desc' => __( 'Append a timestamp to the file path for versioning.', 'media-cloud-sync' ),
						'name' => 'object_versioning',
						'type' => 'toggle',
					],
				],
			],
			's3compatible' => [
				'label' => __( 'S3 Compatible', 'media-cloud-sync' ),
				'mainLabel' => '',
				'slug' => 's3compatible',
				'icon' => $this->img( 'icons/other-provider.svg' ),
				'configSource' => [
					'database' => [
						'title' => __( 'Store in Database', 'media-cloud-sync' ),
						'description' => __( 'Enter your S3-compatible storage endpoint, access key, secret key, and region below.', 'media-cloud-sync' ),
					],
					'config' => [
						'title' => __( 'Use wp-config.php Constant', 'media-cloud-sync' ),
						'description' => __( 'Keep credentials out of the database by defining a WPMCS_CONFIG constant in wp-config.php. More secure than storing in the database.', 'media-cloud-sync' ),
						'recommended' => true,
						'instruction' => __( 'Add the following to your wp-config.php (above the “That’s all, stop editing!” line). Replace YOUR_STORAGE_ENDPOINT with your provider’s S3-compatible endpoint URL:', 'media-cloud-sync' ),
						'note' => __( 'Once it’s added, continue — we’ll verify the constant on the server.', 'media-cloud-sync' ),
						'regionHint' => __( 'Pick a region and copy its value for wp-config.php.', 'media-cloud-sync' ),
						'snippet' => "define( 'WPMCS_CONFIG', [\n    'endpoint'   => 'YOUR_STORAGE_ENDPOINT',\n    'access_key' => 'YOUR_ACCESS_KEY',\n    'secret_key' => 'YOUR_SECRET_KEY',\n    'region'     => 'YOUR_REGION',\n] );",
					],
				],
				'description' => __( 'An S3-compatible storage service provides scalable, durable, and secure object storage with an interface and functionality similar to Amazon S3, enabling seamless integration with existing S3-based tools and applications.', 'media-cloud-sync' ),
				'documentation' => [
					'external' => true,
					'link' => 'https://dudlewebs.com/guideline/media-cloud-sync/get-started/configure-s3-compatible-object-storage/',
				],
				'consoleLink' => 'https://dudlewebs.com',
				'fields' => [
					[
						'type' => 'text',
						'label' => __( 'Storage Endpoint', 'media-cloud-sync' ),
						'name' => 'endpoint',
						'icon' => 'globe',
						'placeholder' => __( 'Enter storage endpoint URL', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => true,
								'pattern' => [ '__regex' => '^https?:\\/\\/([a-zA-Z0-9-]+\\.)+[a-zA-Z]{2,}(:\\d+)?(\\/[^\\s]*)?$', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'text',
						'label' => __( 'Access Key', 'media-cloud-sync' ),
						'name' => 'access_key',
						'icon' => 'key',
						'placeholder' => __( 'Enter access key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'text',
						'label' => __( 'Secret Key', 'media-cloud-sync' ),
						'name' => 'secret_key',
						'icon' => 'lock',
						'placeholder' => __( 'Enter secret key', 'media-cloud-sync' ),
						'validation' => [
							'required' => true,
							'length' => 100,
							'regex' => [
								'match' => false,
								'pattern' => [ '__regex' => '\\s', 'flags' => '' ],
							],
						],
					],
					[
						'type' => 'text',
						'label' => __( 'Region', 'media-cloud-sync' ),
						'name' => 'region',
						'icon' => 'map',
						'placeholder' => __( 'Enter region', 'media-cloud-sync' ),
						'validation' => [],
					],
				],
				'storage' => [
					'label' => __( 'Bucket', 'media-cloud-sync' ),
					'showManualInput' => false,
					'fields' => [
						[
							'type' => 'text',
							'label' => __( 'Enter Bucket Name', 'media-cloud-sync' ),
							'name' => 'bucket_name',
							'icon' => 'database',
							'placeholder' => __( 'Enter bucket name', 'media-cloud-sync' ),
							'validation' => [
								'required' => true,
							],
						],
					],
					'addNew' => [
						'enable' => false,
					],
				],
				'configurePageSlugs' => [
					'setCredentials',
					'setBucket',
					'verifyPermissions',
				],
				'settings' => [
					[
						'label' => __( 'Copy files to bucket', 'media-cloud-sync' ),
						'desc' => __( 'Upload to bucket, when ever a file is uploaded to Media Library', 'media-cloud-sync' ),
						'name' => 'copy_to_bucket',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Remove files from server', 'media-cloud-sync' ),
						'desc' => __( 'Use this to remove files from the server once they are uploaded to the bucket.', 'media-cloud-sync' ),
						'warning' => [
							'enable' => true,
							'message' => __( '<p>This setting permanently deletes files from the server once they\'re uploaded to your bucket — be careful before enabling it.</p><p>If you stop using this plugin, you will need to restore these files manually, by downloading them from the cloud and re-uploading via FTP, or automatically using our PRO version\'s restore feature.</p>', 'media-cloud-sync' ),
						],
						'name' => 'remove_from_server',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Add Prefix to Bucket Path', 'media-cloud-sync' ),
						'desc' => __( 'Enable this option if you need a custom prefix for your bucket path in Amazon S3', 'media-cloud-sync' ),
						'name' => 'enable_base_path',
						'type' => 'toggle',
						'childrens' => [
							[
								'label' => '',
								'desc' => '',
								'name' => 'base_path',
								'type' => 'text',
								'required' => false,
								'placeholder' => __( 'Enter Prefix', 'media-cloud-sync' ),
								'values' => '',
							],
						],
					],
					[
						'label' => __( 'Year/Month Structure', 'media-cloud-sync' ),
						'desc' => __( 'This will enable year/month directories at the end of your base path. Otherwise it will be uploaded in to the base path directly.', 'media-cloud-sync' ),
						'name' => 'year_month',
						'type' => 'toggle',
					],
					[
						'label' => __( 'Object versioning', 'media-cloud-sync' ),
						'desc' => __( 'Append a timestamp to the bucket path.', 'media-cloud-sync' ),
						'name' => 'object_versioning',
						'type' => 'toggle',
					],
				],
			],
		];
	}

	/** Wizard credential defaults. */
	private function configure_default_values() {
		return [
			's3' => [
				'access_key' => '',
				'secret_key' => '',
				'region' => '',
			],
			'gcloud' => [
				'config_json' => '',
			],
			'docean' => [
				'access_key' => '',
				'secret_key' => '',
				'region' => '',
			],
			'cloudflareR2' => [
				'account_id' => '',
				'access_key' => '',
				'secret_key' => '',
				'region' => '',
			],
			's3compatible' => [
				'endpoint' => '',
				'access_key' => '',
				'secret_key' => '',
				'region' => '',
			],
		];
	}

	/** Bucket step defaults. */
	private function bucket_default_values() {
		return [
			's3' => [
				'bucket_name' => '',
				'transfer_acceleration' => false,
			],
			'gcloud' => [
				'bucket_name' => '',
			],
			'docean' => [
				'bucket_name' => '',
			],
			'cloudflareR2' => [
				'bucket_name' => '',
			],
			's3compatible' => [
				'bucket_name' => '',
			],
		];
	}

	/** Create-bucket defaults. */
	private function bucket_add_new_default_values() {
		return [
			's3' => [
				'bucket_name' => '',
				'transfer_acceleration' => false,
			],
			'gcloud' => [
				'bucket_name' => '',
				'region' => '',
			],
			'docean' => [
				'bucket_name' => '',
			],
			'cloudflareR2' => [
				'bucket_name' => '',
			],
		];
	}

	/** Setting defaults per provider (all keys, including Pro ones, so saves never drop them). */
	private function settings_default_values() {
		return [
			's3' => [
				'copy_to_bucket' => true,
				'enable_base_path' => true,
				'base_path' => 'wp-content/uploads',
				'year_month' => true,
				'object_versioning' => false,
				'enable_cdn' => false,
				'cdn_url' => '',
				'enable_private_url' => false,
				'private_url_expire' => 20,
				'enable_private_media' => false,
				'private_path' => 'private',
				'private_cdn_key_id' => '',
				'private_cdn_key' => '',
				'private_cdn_distribution' => '',
				'private_cdn_domain' => '',
				'remove_from_server' => false,
				'rewrite_url' => true,
				'cache_control_enabled' => false,
				'cache_control_duration' => 1,
				'cache_control_unit' => 'months',
				'extensions_include_enabled' => false,
				'extensions_include' => [],
				'extensions_exclude_enabled' => false,
				'extensions_exclude' => [],
			],
			'gcloud' => [
				'copy_to_bucket' => true,
				'enable_base_path' => true,
				'base_path' => 'wp-content/uploads',
				'year_month' => true,
				'object_versioning' => false,
				'enable_cdn' => false,
				'cdn_url' => '',
				'enable_private_url' => false,
				'private_url_expire' => 20,
				'enable_private_media' => false,
				'private_path' => 'private',
				'private_cdn_key_id' => '',
				'private_cdn_key' => '',
				'private_cdn_distribution' => '',
				'private_cdn_domain' => '',
				'remove_from_server' => false,
				'rewrite_url' => true,
				'cache_control_enabled' => false,
				'cache_control_duration' => 1,
				'cache_control_unit' => 'months',
				'extensions_include_enabled' => false,
				'extensions_include' => [],
				'extensions_exclude_enabled' => false,
				'extensions_exclude' => [],
			],
			'docean' => [
				'copy_to_bucket' => true,
				'enable_base_path' => true,
				'base_path' => 'wp-content/uploads',
				'year_month' => true,
				'object_versioning' => false,
				'enable_cdn' => false,
				'cdn_url' => '',
				'enable_private_url' => false,
				'private_url_expire' => 20,
				'enable_private_media' => false,
				'private_path' => 'private',
				'private_cdn_key_id' => '',
				'private_cdn_key' => '',
				'private_cdn_distribution' => '',
				'private_cdn_domain' => '',
				'remove_from_server' => false,
				'rewrite_url' => true,
				'cache_control_enabled' => false,
				'cache_control_duration' => 1,
				'cache_control_unit' => 'months',
				'extensions_include_enabled' => false,
				'extensions_include' => [],
				'extensions_exclude_enabled' => false,
				'extensions_exclude' => [],
			],
			'cloudflareR2' => [
				'copy_to_bucket' => true,
				'enable_base_path' => true,
				'base_path' => 'wp-content/uploads',
				'year_month' => true,
				'object_versioning' => false,
				'enable_cdn' => false,
				'cdn_url' => '',
				'enable_private_url' => false,
				'private_url_expire' => 20,
				'remove_from_server' => false,
				'rewrite_url' => true,
				'cache_control_enabled' => false,
				'cache_control_duration' => 1,
				'cache_control_unit' => 'months',
				'extensions_include_enabled' => false,
				'extensions_include' => [],
				'extensions_exclude_enabled' => false,
				'extensions_exclude' => [],
			],
			's3compatible' => [
				'copy_to_bucket' => true,
				'enable_base_path' => true,
				'base_path' => 'wp-content/uploads',
				'year_month' => true,
				'object_versioning' => false,
				'enable_cdn' => false,
				'cdn_url' => '',
				'enable_private_url' => false,
				'private_url_expire' => 20,
				'remove_from_server' => false,
				'rewrite_url' => true,
				'cache_control_enabled' => false,
				'cache_control_duration' => 1,
				'cache_control_unit' => 'months',
				'extensions_include_enabled' => false,
				'extensions_include' => [],
				'extensions_exclude_enabled' => false,
				'extensions_exclude' => [],
			],
		];
	}

	/**
	 * Bulk job screens; each entry's `path` becomes its route. Optional keys: description, confirm
	 * (title, message, confirmLabel, danger, options[] of toggle fields sent as start options),
	 * retry (default true), requiresKnownTotal (default true), syncMethods (allowed method values), back (label + path of the back link),
	 * libraryProgress (progress = Synced ÷ Total media), counters (label + value; defaults to run_counters()).
	 *
	 * Counter values:
	 * - libraryTotal:   all media in the library
	 * - librarySynced:  media synced to the cloud
	 * - libraryPending: media not synced yet (libraryTotal - librarySynced)
	 * - runTotal:       items in the current run
	 * - succeeded:      items done in this run without errors
	 * - failed:         items that failed in this run
	 * - remaining:      items this run hasn't processed yet
	 */
	private function sync_screen() {
		return [
			'sync_to_cloud' => [
				'title' => __( 'Sync Media to Cloud Storage', 'media-cloud-sync' ),
				'path' => '/sync-to-cloud',
				'doneMessage' => __( 'All your existing media are synced successfully.', 'media-cloud-sync' ),
				// Counters and progress show the whole library, matching the dashboard.
				'libraryProgress' => true,
				'counters' => [
					[ 'label' => __( 'Total media', 'media-cloud-sync' ), 'value' => 'libraryTotal' ],
					[ 'label' => __( 'Synced', 'media-cloud-sync' ), 'value' => 'librarySynced' ],
					[ 'label' => __( 'Pending', 'media-cloud-sync' ), 'value' => 'libraryPending' ],
					[ 'label' => __( 'Failed in this run', 'media-cloud-sync' ), 'value' => 'failed' ],
				],
			],
		];
	}

	/** Default job counters: this run's numbers, which always add up. */
	private function run_counters() {
		return [
			[ 'label' => __( 'In this run', 'media-cloud-sync' ), 'value' => 'runTotal' ],
			[ 'label' => __( 'Succeeded', 'media-cloud-sync' ), 'value' => 'succeeded' ],
			[ 'label' => __( 'Failed', 'media-cloud-sync' ), 'value' => 'failed' ],
			[ 'label' => __( 'Remaining', 'media-cloud-sync' ), 'value' => 'remaining' ],
		];
	}

	/** Delivery providers per storage service. */
	private function cdn_servers() {
		return [
			's3' => array_merge(
				[
					's3' => [
						'label' => __( 'Amazon S3', 'media-cloud-sync' ),
						'mainLabel' => __( 'Amazon S3', 'media-cloud-sync' ),
						'icon' => $this->img( 's3.png' ),
						'consoleLink' => 'https://ap-south-1.console.aws.amazon.com/s3/buckets/',
						'consoleLabel' => __( 'Provider Console', 'media-cloud-sync' ),
						'documentation' => 'https://dudlewebs.com/guideline/media-cloud-sync/get-started/quick-start-amazon-web-services-aws/',
						'speed' => __( 'Slow', 'media-cloud-sync' ),
						'privateSupport' => true,
						'default' => true,
						'fields' => [
							[
								'label' => __( 'Deliver Synced Media', 'media-cloud-sync' ),
								'desc' => __( 'For media library files that have been copied to the bucket, Enable this option to serve media from provider, so that they are served from the bucket instead of your server.', 'media-cloud-sync' ),
								'name' => 'rewrite_url',
								'type' => 'toggle',
							],
						],
					],
				],
				$this->named_cdn_providers( false ),
				[ 'other' => $this->other_cdn_provider() ]
			),
			'gcloud' => array_merge(
				[
					'gcloud' => [
						'label' => __( 'Google Cloud Storage', 'media-cloud-sync' ),
						'mainLabel' => __( 'Google Cloud Storage', 'media-cloud-sync' ),
						'icon' => $this->img( 'google.png' ),
						'consoleLink' => 'https://console.cloud.google.com/storage/',
						'consoleLabel' => __( 'Provider Console', 'media-cloud-sync' ),
						'documentation' => 'https://dudlewebs.com/support',
						'speed' => __( 'Slow', 'media-cloud-sync' ),
						'privateSupport' => true,
						'default' => true,
						'fields' => [
							[
								'label' => __( 'Deliver Synced Media', 'media-cloud-sync' ),
								'desc' => __( 'For media library files that have been copied to the bucket, Enable this option to serve media from provider, so that they are served from the storage instead of your server.', 'media-cloud-sync' ),
								'name' => 'rewrite_url',
								'type' => 'toggle',
							],
						],
					],
				],
				$this->named_cdn_providers( false ),
				[ 'other' => $this->other_cdn_provider() ]
			),
			'docean' => array_merge(
				[
					'docean' => [
						'label' => __( 'Digital Ocean Spaces', 'media-cloud-sync' ),
						'mainLabel' => __( 'Digital Ocean Spaces', 'media-cloud-sync' ),
						'icon' => $this->img( 'ocean.png' ),
						'consoleLink' => 'https://console.cloud.google.com/storage/',
						'consoleLabel' => __( 'Provider Console', 'media-cloud-sync' ),
						'documentation' => 'https://dudlewebs.com',
						'speed' => __( 'Slow', 'media-cloud-sync' ),
						'privateSupport' => true,
						'default' => true,
						'fields' => [
							[
								'label' => __( 'Deliver Synced Media', 'media-cloud-sync' ),
								'desc' => __( 'For media library files that have been copied to the bucket, Enable this option to serve media from provider, so that they are served from the space instead of your server.', 'media-cloud-sync' ),
								'name' => 'rewrite_url',
								'type' => 'toggle',
							],
						],
					],
				],
				$this->named_cdn_providers( false ),
				[ 'other' => $this->other_cdn_provider() ]
			),
			'cloudflareR2' => array_merge(
				[
					'cloudflareR2' => [
						'label' => __( 'Cloudflare R2', 'media-cloud-sync' ),
						'mainLabel' => __( 'Cloudflare R2', 'media-cloud-sync' ),
						'icon' => $this->img( 'cloudflareR2.png' ),
						'consoleLink' => 'https://dash.cloudflare.com/',
						'consoleLabel' => __( 'Provider Console', 'media-cloud-sync' ),
						'documentation' => 'https://dudlewebs.com',
						'speed' => __( 'Slow', 'media-cloud-sync' ),
						'privateSupport' => false,
						'default' => true,
						'fields' => [
							[
								'label' => __( 'Deliver Synced Media', 'media-cloud-sync' ),
								'desc' => __( 'For media library files that have been copied to the bucket, Enable this option to serve media from provider, so that they are served from the storage instead of your server.', 'media-cloud-sync' ),
								'name' => 'rewrite_url',
								'type' => 'toggle',
							],
							[
								'label' => __( 'Use Custom Domain Name (CNAME)', 'media-cloud-sync' ),
								'desc' => __( 'Required to deliver media from R2, because its storage endpoint isn\'t public. Enter your bucket\'s public r2.dev URL (R2 → your bucket → Settings → Public access) or a custom domain connected to the bucket.', 'media-cloud-sync' ),
								'name' => 'enable_cdn',
								'type' => 'toggle',
								'childrens' => [
									[
										'label' => '',
										'desc' => '',
										'name' => 'cdn_url',
										'type' => 'text',
										'parent' => 'enable_cdn',
										'required' => true,
										'placeholder' => __( 'Enter Domain', 'media-cloud-sync' ),
										'values' => '',
									],
								],
							],
						],
					],
				],
				$this->named_cdn_providers( true ),
				[ 'other' => $this->other_cdn_provider() ]
			),
			's3compatible' => array_merge(
				[

				],
				$this->named_cdn_providers( false ),
				[ 'other' => [
					'label' => __( 'Other', 'media-cloud-sync' ),
					'mainLabel' => '',
					'icon' => $this->img( 'icons/other-provider.svg' ),
					'consoleLink' => 'https://dudlewebs.com',
					'consoleLabel' => __( 'Documentation', 'media-cloud-sync' ),
					'documentation' => 'https://dudlewebs.com/support',
					'speed' => __( 'Fast', 'media-cloud-sync' ),
					'privateSupport' => false,
					'default' => true,
					'fields' => [
						[
							'label' => __( 'Deliver Synced Media', 'media-cloud-sync' ),
							'desc' => __( 'For media library files that have been copied to the bucket, Enable this option to serve media from provider, so that they are served from the bucket instead of your server.', 'media-cloud-sync' ),
							'documentation' => 'https://dudlewebs.com/support',
							'documentationLabel' => __( 'How URL rewriting works', 'media-cloud-sync' ),
							'name' => 'rewrite_url',
							'type' => 'toggle',
						],
						[
							'label' => __( 'Use Custom Domain Name (CNAME)', 'media-cloud-sync' ),
							'desc' => __( 'Serves media from a custom domain that has been pointed to your media storage.', 'media-cloud-sync' ),
							'documentation' => 'https://dudlewebs.com/support',
							'documentationLabel' => __( 'How to set a custom domain name', 'media-cloud-sync' ),
							'name' => 'enable_cdn',
							'type' => 'toggle',
							'childrens' => [
								[
									'label' => '',
									'desc' => '',
									'name' => 'cdn_url',
									'type' => 'text',
									'parent' => 'enable_cdn',
									'required' => true,
									'placeholder' => __( 'Enter Domain', 'media-cloud-sync' ),
								],
							],
						],
					],
				] ]
			),
		];
	}

	/** Fields shared by every named CDN provider (custom-domain rewrite). */
	private function cdn_provider_fields() {
		return [
			[
				'label' => __( 'Deliver Synced Media', 'media-cloud-sync' ),
				'desc' => __( 'For media library files that have been copied to the bucket, Enable this option to serve media from provider, so that they are served from the bucket instead of your server.', 'media-cloud-sync' ),
				'name' => 'rewrite_url',
				'type' => 'toggle',
			],
			[
				'label' => __( 'Use Custom Domain Name (CNAME)', 'media-cloud-sync' ),
				'desc' => __( 'Serves media from a custom domain that has been pointed to your media storage.', 'media-cloud-sync' ),
				'name' => 'enable_cdn',
				'type' => 'toggle',
				'childrens' => [
					[
						'label' => '',
						'desc' => '',
						'name' => 'cdn_url',
						'type' => 'text',
						'parent' => 'enable_cdn',
						'required' => true,
						'placeholder' => __( 'Enter Domain', 'media-cloud-sync' ),
						'values' => '',
					],
				],
			],
		];
	}

	/** Named CDN providers, identical for every storage service (Cloudflare CDN skipped for R2). */
	private function named_cdn_providers( $exclude_cloudflare_cdn = false ) {
		$providers = [
			'cloudfront' => [ __( 'Amazon CloudFront', 'media-cloud-sync' ), $this->img( 'icons/cdn-cloudfront.png' ) ],
			'bunnynet' => [ __( 'Bunny.net', 'media-cloud-sync' ), $this->img( 'icons/cdn-bunnynet.png' ) ],
			'fastly' => [ __( 'Fastly', 'media-cloud-sync' ), $this->img( 'icons/cdn-fastly.svg' ) ],
			'cloudflareCdn' => [ __( 'Cloudflare CDN', 'media-cloud-sync' ), $this->img( 'icons/cdn-cloudflare.svg' ) ],
			'akamai' => [ __( 'Akamai', 'media-cloud-sync' ), $this->img( 'icons/cdn-akamai.png' ) ],
			'keycdn' => [ __( 'KeyCDN', 'media-cloud-sync' ), $this->img( 'icons/cdn-keycdn.svg' ) ],
			'gcore' => [ __( 'Gcore', 'media-cloud-sync' ), $this->img( 'icons/cdn-gcore.svg' ) ],
			'leaseweb' => [ __( 'Leaseweb CDN', 'media-cloud-sync' ), $this->img( 'icons/cdn-leaseweb.png' ) ],
			'cdnetworks' => [ __( 'CDNetworks', 'media-cloud-sync' ), $this->img( 'icons/cdn-cdnetworks.png' ) ],
		];
		if ( $exclude_cloudflare_cdn ) {
			unset( $providers['cloudflareCdn'] );
		}
		$entries = [];
		foreach ( $providers as $key => $provider ) {
			$entries[ $key ] = [
				'label'          => $provider[0],
				'mainLabel'      => $provider[0],
				'icon'           => $provider[1],
				'consoleLink'    => 'https://dudlewebs.com',
				'consoleLabel'   => __( 'Documentation', 'media-cloud-sync' ),
				'documentation'  => 'https://dudlewebs.com/support',
				'speed'          => __( 'Fast', 'media-cloud-sync' ),
				'privateSupport' => false,
				'default'        => false,
				'fields'         => $this->cdn_provider_fields(),
			];
		}
		return $entries;
	}

	/** Fully custom domain ("Other") delivery entry. */
	private function other_cdn_provider() {
		return [
			'label' => __( 'Other', 'media-cloud-sync' ),
			'mainLabel' => '',
			'icon' => $this->img( 'icons/other-provider.svg' ),
			'consoleLink' => 'https://dudlewebs.com',
			'consoleLabel' => __( 'Documentation', 'media-cloud-sync' ),
			'documentation' => 'https://dudlewebs.com/support',
			'speed' => __( 'Fast', 'media-cloud-sync' ),
			'privateSupport' => false,
			'default' => false,
			'fields' => [
				[
					'label' => __( 'Deliver Synced Media', 'media-cloud-sync' ),
					'desc' => __( 'For media library files that have been copied to the bucket, Enable this option to serve media from provider, so that they are served from the bucket instead of your server.', 'media-cloud-sync' ),
					'documentation' => 'https://dudlewebs.com/support',
					'documentationLabel' => __( 'How URL rewriting works', 'media-cloud-sync' ),
					'name' => 'rewrite_url',
					'type' => 'toggle',
				],
				[
					'label' => __( 'Use Custom Domain Name (CNAME)', 'media-cloud-sync' ),
					'desc' => __( 'Serves media from a custom domain that has been pointed to your media storage.', 'media-cloud-sync' ),
					'documentation' => 'https://dudlewebs.com/support',
					'documentationLabel' => __( 'How to set a custom domain name', 'media-cloud-sync' ),
					'name' => 'enable_cdn',
					'type' => 'toggle',
					'childrens' => [
						[
							'label' => '',
							'desc' => '',
							'name' => 'cdn_url',
							'type' => 'text',
							'parent' => 'enable_cdn',
							'required' => true,
							'placeholder' => __( 'Enter Domain', 'media-cloud-sync' ),
							'values' => '',
						],
					],
				],
			],
		];
	}
}
