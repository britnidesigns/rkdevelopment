<?php

/**
 * Time_Tracking_Toggl Controller
 *
 * @package Sprout_Invoice
 * @subpackage Time_Tracking_Toggl
 */
class Zapier_API extends Zapier_Controller {

	public static function send_zaps( $zaps = array(), $data = array()  ) {
		foreach ( $zaps as $zap_id ) {
			$target_url = self::get_zap_target_url( $zap_id );
			self::send_to_zapier( $target_url, $data );
		}
	}

	private static function send_to_zapier( $target_url, $data ) {
		if ( strpos( $target_url, 'zapier.com/hooks' ) === false ) {
			return;
		}

		$headers = array();
		if ( empty( $data ) ) {
			$headers['X-Hook-Test'] = 'true';
		}

		$post_args = array(
			'headers' => $headers,
			'body' => wp_json_encode( $data ) ,
			'timeout' => apply_filters( 'http_request_timeout', 15 ),
			'sslverify' => false,
			'ssl' => true,
		);

		$resp = wp_safe_remote_post( $target_url, $post_args );
		if ( $resp ) {
			// If 410 header than unsubscribe the zap
		}
	}
}