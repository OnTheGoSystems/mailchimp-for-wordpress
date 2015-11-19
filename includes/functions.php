<?php

/**
 * Gets the MailChimp for WP options from the database
 * Uses default values to prevent undefined index notices.
 *
 * @since 1.0
 * @access public
 *
 * @return array
 */
function mc4wp_get_options() {
	$defaults = require MC4WP_PLUGIN_DIR . 'config/default-settings.php';
	$options = (array) get_option( 'mc4wp', array() );
	return array_merge( $defaults, $options );
}


/**
 * Gets the MailChimp for WP API class and injects it with the API key
 *
 * @staticvar $instance
 *
 * @since 1.0
 * @access public
 *
 * @return MC4WP_API
 */
function mc4wp_get_api() {
	static $instance;

	if( $instance instanceof MC4WP_API ) {
		return $instance;
	}

	$opts = mc4wp_get_options();
	$instance = new MC4WP_API( $opts['api_key'] );
	return $instance;
}

/**
 * Retrieves the URL of the current WordPress page
 *
 * @access public
 * @since 2.0
 *
 * @return  string  The current URL (escaped)
 */
function mc4wp_get_current_url() {

	global $wp;

	// get requested url from global $wp object
	$site_request_uri = $wp->request;

	// fix for IIS servers using index.php in the URL
	if( false !== stripos( $_SERVER['REQUEST_URI'], '/index.php/' . $site_request_uri ) ) {
		$site_request_uri = 'index.php/' . $site_request_uri;
	}

	// concatenate request url to home url
	$url = home_url( $site_request_uri );
	$url = trailingslashit( $url );

	return esc_url( $url );
}

/**
 * Sanitizes all values in a mixed variable.
 *
 * @access public
 *
 * @param mixed $value
 *
 * @return mixed
 */
function mc4wp_sanitize_deep( $value ) {

	if ( is_scalar( $value ) ) {
		$value = sanitize_text_field( $value );
	} elseif( is_array( $value ) ) {
		$value = array_map( 'mc4wp_sanitize_deep', $value );
	} elseif ( is_object($value) ) {
		$vars = get_object_vars( $value );
		foreach ( $vars as $key => $data ) {
			$value->{$key} = mc4wp_sanitize_deep( $data );
		}
	}

	return $value;
}

/**
 * @ignore
 * @access private
 *
 * @param $name
 * @param $instance
 */
function mc4wp_register_instance( $name, $instance ) {
	return MC4WP_Service_Container::instance()->register( $name, $instance );
}

/**
 * @param $name
 *
 * @ignore
 * @access private
 *
 * @return mixed
 * @throws Exception
 */
function mc4wp_get_instance( $name ) {
	return MC4WP_Service_Container::instance()->get( $name );
}

/**
 * Guesses merge vars based on given data & current request.
 *
 * @since 3.0
 * @access public
 *
 * @param array $merge_vars
 *
 * @return array
 */
function mc4wp_guess_merge_vars( $merge_vars = array() ) {

	// maybe guess first and last name
	if ( isset( $merge_vars['NAME'] ) ) {
		if( ! isset( $merge_vars['FNAME'] ) && ! isset( $merge_vars['LNAME'] ) ) {
			$strpos = strpos( $merge_vars['NAME'], ' ' );
			if ( $strpos !== false ) {
				$merge_vars['FNAME'] = trim( substr( $merge_vars['NAME'], 0, $strpos ) );
				$merge_vars['LNAME'] = trim( substr( $merge_vars['NAME'], $strpos ) );
			} else {
				$merge_vars['FNAME'] = $merge_vars['NAME'];
			}
		}
	}

	// set ip address
	if( empty( $merge_vars['OPTIN_IP'] ) ) {
		$optin_ip = MC4WP_Request::create_from_globals()->get_client_ip();

		if( ! empty( $optin_ip ) ) {
			$merge_vars['OPTIN_IP'] = $optin_ip;
		}
	}

	/**
	 * Filters merge vars which are sent to MailChimp
	 *
	 * @param array $merge_vars
	 */
	$merge_vars = (array) apply_filters( 'mc4wp_merge_vars', $merge_vars );

	return $merge_vars;
}

/**
 * Gets the "email type" for new subscribers.
 *
 * Possible return values are either "html" or "text"
 *
 * @access public
 * @since 3.0
 *
 * @return string
 */
function mc4wp_get_email_type() {

	$email_type = 'html';

	/**
	 * Filters the email type preference for this new subscriber.
	 *
	 * @param string $email_type
	 */
	$email_type = apply_filters( 'mc4wp_email_type', $email_type );

	return $email_type;
}