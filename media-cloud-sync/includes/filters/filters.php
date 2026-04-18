<?php

namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Filter {
    /**
	 * Get an array of bare base_urls that can be used for uploaded items.
	 *
	 * @param bool $refresh Refresh cached domains, default false.
	 *
	 * @return array
	 */
	public static function get_bare_upload_base_urls( $refresh = false ) {
		static $base_urls = array();

		if ( $refresh || empty( $base_urls ) ) {
			$domains = array();

			// Original domain and path.
			$uploads     = wp_upload_dir();
			$base_url    = Utils::remove_scheme( $uploads['baseurl'] );
			$orig_domain = Utils::parse_url( $base_url, PHP_URL_HOST );
			$port        = Utils::parse_url( $base_url, PHP_URL_PORT );
			if ( ! empty( $port ) ) {
				$orig_domain .= ':' . $port;
			}

			$domains[] = $orig_domain;
			$base_urls = array( $base_url );

			// Current domain and path after potential domain mapping.
			$base_url    = self::maybe_fix_server_subsite_url( $uploads['baseurl'] );
			$base_url    = Utils::remove_scheme( $base_url );
			$curr_domain = Utils::parse_url( $base_url, PHP_URL_HOST );
			$port        = Utils::parse_url( $base_url, PHP_URL_PORT );
			if ( ! empty( $port ) ) {
				$curr_domain .= ':' . $port;
			}

			if ( $curr_domain !== $orig_domain ) {
				$domains[] = $curr_domain;
			}

			/**
			 * Allow alteration of the server domains that can be matched on.
			 *
			 * @param array $domains
			 */
			$domains = apply_filters( 'wpmcs_server_domains', $domains );

			if ( ! empty( $domains ) ) {
				foreach ( array_unique( $domains ) as $match_domain ) {
					$base_urls[] = substr_replace( $base_url, $match_domain, 2, strlen( $curr_domain ) );
				}
			}
		}

		return array_unique( $base_urls );
	}


	/**
	 * Get an array of domain names that can be used for remote items.
	 *
	 * @param bool $refresh Refresh cached domains, default false.
	 *
	 * @return array
	 */
	public static function get_remote_domains( $refresh = false ) {
		static $domains = array();

		if ( $refresh || empty( $domains ) ) {
			$domain 		= Service::instance()->get_domain();
			$settings 		= Utils::get_settings();
			if (
				isset($settings['enable_cdn']) && $settings['enable_cdn'] &&
				isset($settings['cdn_url']) && !empty($settings['cdn_url'])
			) {
				$cdn_base_url 	= Utils::remove_scheme( $settings['cdn_url'] );
				$cdn_domain 	= Utils::parse_url( $cdn_base_url, PHP_URL_HOST );
				$cdn_port       = Utils::parse_url( $cdn_base_url, PHP_URL_PORT );
				if ( ! empty( $cdn_port ) ) {
					$cdn_domain .= ':' . $cdn_port;
				}
				$domains[] = $cdn_domain;
			}


			$base_url   	= Utils::remove_scheme( $domain );
			$curr_domain 	= Utils::parse_url( $base_url, PHP_URL_HOST );
			$port       	= Utils::parse_url( $base_url, PHP_URL_PORT );
			if ( ! empty( $port ) ) {
				$curr_domain .= ':' . $port;
			}
			$domains[] = $curr_domain;

			/**
			 * Allow alteration of the remote domains that can be matched on.
			 *
			 * @param array $domains
			 */
			$domains = array_unique( apply_filters( 'wpmcs_remote_domains', $domains ) );
		}

		return $domains;
	}

    /**
	 * Ensure server URL is correct for multisite's non-primary subsites.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public static function maybe_fix_server_subsite_url( $url ) {
		$siteurl = trailingslashit( get_option( 'siteurl' ) );

		if ( is_multisite() && ! self::is_current_blog( get_current_blog_id() ) && 0 !== strpos( $url, $siteurl ) ) {
			// Replace original URL with subsite's current URL.
			$orig_siteurl = trailingslashit( apply_filters( 'wpmcs_get_original_siteurl', network_site_url() ) );
			$url          = str_replace( $orig_siteurl, $siteurl, $url );
		}

		return $url;
	}

    /**
	 * Is the current blog ID that specified in wp-config.php
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	public static function is_current_blog( $blog_id ) {
		$default = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : 1;

		if ( $default === $blog_id ) {
			return true;
		}

		return false;
	}
}