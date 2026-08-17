<?php
namespace Dudlewebs\WPMCS;

defined( 'ABSPATH' ) || exit;

/**
 * Imagify Push CDN adapter for offloaded media.
 *
 * Implements Imagify's PushCDNInterface the same way the official AS3CF integration does.
 * Loaded by the autoloader when `register_cdn()` runs for an offloaded attachment.
 *
 * @since 1.3.12
 */
if ( ! interface_exists( '\Imagify\CDN\PushCDNInterface' ) ) {
	return;
}

class ImagifyPushCDN implements \Imagify\CDN\PushCDNInterface {
	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Constructor.
	 *
	 * @param int $media_id Attachment ID.
	 */
	public function __construct( $media_id ) {
		$this->id = (int) $media_id;
	}

	/**
	 * @inheritDoc
	 */
	public function is_ready() {
		return Utils::is_service_enabled();
	}

	/**
	 * @inheritDoc
	 */
	public function media_is_on_cdn() {
		return Imagify::instance()->media_is_on_provider( $this->id );
	}

	/**
	 * @inheritDoc
	 */
	public function get_files_from_cdn( $file_paths ) {
		return Imagify::instance()->copy_files_from_cdn( $this->id, $file_paths );
	}

	/**
	 * @inheritDoc
	 */
	public function remove_files_from_cdn( $file_paths ) {
		return Imagify::instance()->remove_files_from_cdn( $this->id, $file_paths );
	}

	/**
	 * @inheritDoc
	 */
	public function send_to_cdn( $is_new_upload ) {
		return Imagify::instance()->send_attachment_to_cdn( $this->id, $is_new_upload );
	}

	/**
	 * @inheritDoc
	 */
	public function get_file_url( $file_name = '' ) {
		return Imagify::instance()->get_provider_file_url( $this->id, $file_name );
	}

	/**
	 * @inheritDoc
	 */
	public function get_file_path( $file_name = '' ) {
		return Imagify::instance()->get_local_file_path( $this->id, $file_name );
	}
}
