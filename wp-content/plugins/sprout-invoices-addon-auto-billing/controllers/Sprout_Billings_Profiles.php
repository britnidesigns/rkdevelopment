<?php

/**
 * Auto Billing Admin Controller
 *
 *
 * @package Sprout_Billings_Profiles
 */
class Sprout_Billings_Profiles extends SI_Sprout_Billings {
	const AJAX_ACTION_MANAGE = 'si_ab_card_mngt';
	// moved from CIM in 2.0, meta name should stay for compatibility
	const CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES = 'cim_card_mngt_hidden_v92';
	const CLIENT_META_PROFILE_ID = 'si_authnet_cim_profile_id_v92';

	public static function init() {
		// Options
		add_action( 'wp_ajax_' . self::AJAX_ACTION_MANAGE, array( get_class(), 'card_management' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_MANAGE, array( get_class(), 'card_management' ) );
	}

	public static function get_client_payment_profile_id( $client_id = 0 ) {
		if ( ! $client_id ) {
			return;
		}
		return get_post_meta( $client_id, self::CLIENT_META_PROFILE_ID, true );
	}

	public static function update_client_payment_profile_id( $client_id = 0, $new_customer_id = 0 ) {
		if ( ! $client_id ) {
			return;
		}
		return update_post_meta( $client_id, self::CLIENT_META_PROFILE_ID, $new_customer_id );
	}

	public static function delete_profile( $client_id = 0, $invoice_id = 0 ) {
		if ( ! $client_id && $invoice_id ) {
			$client_id = self::get_client_id( $invoice_id );
		}
		if ( ! $client_id ) {
			return;
		}
		delete_post_meta( $client_id, self::CLIENT_META_PROFILE_ID );
	}

	public static function get_customer_profile_id( $invoice_id = 0, $validate = false ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_id();
		}

		$client_id = self::get_client_id( $invoice_id );
		$profile_id = self::get_client_payment_profile_id( $client_id );
		if ( $validate && $profile_id ) {
			// Validate via API
			$profile_id = SI_Auto_Payment_Processors::validate_profile_id( $profile_id );
		}

		return $profile_id;
	}

	public static function get_client_payment_profiles( $client_id = 0, $hide_profiles = true, $cached = true ) {
		$profile_id = self::get_client_payment_profile_id( $client_id );
		if ( ! $profile_id ) {
			return array();
		}
		$cards = self::get_payment_profiles( $profile_id, $cached );
		if ( is_array( $cards ) && $hide_profiles ) { // hide profiles from client/public
			$hidden_profiles = get_post_meta( $client_id, self::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, true );
			if ( ! empty( $hidden_profiles ) ) {
				foreach ( $cards as $key => $info ) {
					if ( in_array( $key, $hidden_profiles ) ) {
						unset( $cards[ $key ] );
					}
				}
			}
		}
		if ( ! is_array( $cards ) ) {
			return array();
		}
		return $cards;
	}

	public static function get_payment_profiles( $profile_id = 0, $use_cache = true ) {
		if ( ! $profile_id ) {
			return array();
		}

		if ( $use_cache ) {
			$cache_key = '_si_billings_get_payment_profiles_cached_id_' . $profile_id;
			$cards = get_transient( $cache_key );
			if ( $cards ) {
				if ( ! empty( $cards ) ) {
					return $cards;
				}
			}
		}

		// Get from API
		$cards = SI_Auto_Payment_Processors::get_payment_profiles( $profile_id );

		set_transient( $cache_key, $cards, HOUR_IN_SECONDS * 2 );
		return $cards;

	}

	public static function delete_payment_profiles_cache( $profile_id = 0 ) {
		$cache_key = '_si_billings_get_payment_profiles_cached_id_' . $profile_id;
		delete_transient( $cache_key );
	}

	public static function is_payment_profile_hidden( $profile_id = 0, $invoice_id = 0 ) {
		if ( ! $profile_id ) {
			return true;
		}

		$account_id = self::get_client_id( $invoice_id );
		$hidden_profiles = get_post_meta( $account_id, self::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, true );
		if ( ! is_array( $hidden_profiles ) ) {
			return false;
		}
		return in_array( $profile_id, $hidden_profiles );
	}

	public static function card_management() {
		switch ( $_REQUEST['cim_action'] ) {
			case 'remove_payment_profile':
				self::remove_payment_profile( $_REQUEST['remove_profile'], $_REQUEST['invoice_id'] );
				exit();
			break;

			case 'save_payment_profile':
				self::save_payment_profile( $_REQUEST['add_profile'], $_REQUEST['invoice_id'] );
				exit();
			break;

			default:
			break;
		}
	}



	public static function create_new_payment_profile( $profile_id = 0, $client_id = 0, $payment_info = array() ) {
		// do something with the data if necessary.
		$payment_profile_id = SI_Auto_Payment_Processors::create_new_payment_profile( $profile_id, $client_id, $payment_info );

		self::delete_payment_profiles_cache();
		return $payment_profile_id;

	}

	public static function hide_payment_profile( $profile_id = 0, $invoice_id = 0 ) {
		$client_id = self::get_client_id( $invoice_id );
		$hidden_profiles = get_post_meta( $client_id, self::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, true );
		if ( ! is_array( $hidden_profiles ) ) {
			$hidden_profiles = array();
		}
		$hidden_profiles[] = $profile_id;
		update_post_meta( $client_id, self::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, $hidden_profiles );
		return $hidden_profiles;
	}

	public static function remove_payment_profile( $profile_id = 0, $invoice_id = 0 ) {
		// hide the profile first
		self::hide_payment_profile( $profile_id, $invoice_id );
		// Get API Update
		$response = SI_Auto_Payment_Processors::remove_payment_profile( $profile_id, $invoice_id );
		self::delete_payment_profiles_cache();
		return $response;
	}

	public static function save_payment_profile( $profile_id = 0, $invoice_id = 0 ) {
		$client_id = self::get_client_id( $invoice_id );
		if ( ! $profile_id ) {
			update_post_meta( $client_id, self::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, array() );
			return;
		}
		$hidden_profiles = get_post_meta( $client_id, self::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, true );
		if ( is_array( $hidden_profiles ) ) {
			// search for position
			$pos = array_search( $profile_id, $hidden_profiles );
			// remove
			unset( $hidden_profiles[ $pos ] );
			// save
			update_post_meta( $client_id, self::CLIENT_HIDDEN_CARDS_PAYMENT_PROFILES, $hidden_profiles );
		}

		// Get API Update
		$response = SI_Auto_Payment_Processors::save_payment_profile( $profile_id, $invoice_id );
		self::delete_payment_profiles_cache();
		return $response;

	}

	public static function get_client_id_by_profile_id( $profile_id = 0 ) {
		if ( ! $profile_id ) {
			return 0;
		}
		$args = array(
			'post_type' => SI_Client::POST_TYPE,
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => self::CLIENT_META_PROFILE_ID,
					'value' => $profile_id,
					),
				),
		);
		$clients = get_posts( $args );
		return $clients[0];
	}
}
