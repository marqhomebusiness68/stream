<?php

class WP_Stream_API {

	/**
	 * API Key key/identifier
	 */
	const API_KEY_OPTION_KEY = 'wp_stream_api_master_key';

	/**
	 * Site UUID key/identifier
	 */
	const SITE_UUID_OPTION_KEY = 'wp_stream_site_uuid';

	/**
	 * The site's API Key
	 *
	 * @var string
	 */
	public $api_key = false;

	/**
	 * The site's unique identifier
	 *
	 * @var string
	 */
	public $site_uuid = false;

	/**
	 * The API URL
	 *
	 * @var string
	 */
	protected $api_url = 'http://api.wp-stream.com';

	/**
	 * The API Version
	 *
	 * @var string
	 */
	protected $api_version = 'v1';

	/**
	 * Status codes which indicate a successful request
	 *
	 * @var string
	 */
	protected $success_status_codes = array( 200, 201, 204 );

	/**
	 * Error messages
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Public constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->api_key   = get_option( self::API_KEY_OPTION_KEY, 0 );
		$this->site_uuid = get_option( self::SITE_UUID_OPTION_KEY, 0 );
	}

	/**
	 * Validate a site API key.
	 *
	 * @param string The API Key.
	 * @param bool   Allow API calls to be cached.
	 * @param int    Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function validate_key( $allow_cache = true, $expiration = 300 ) {
		$url = $this->request_url( '/validate-key' );

		return $this->remote_request( $url, $allow_cache, $expiration );
	}

	/**
	 * Get the details for a specific user.
	 *
	 * @param int  A user ID.
	 * @param bool Allow API calls to be cached.
	 * @param int  Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function get_user( $user_id = false, $allow_cache = true, $expiration = 300 ) {
		if ( false === $user_id ) {
			return false;
		}

		$url = $this->request_url( '/users/' . intval( $user_id ) );

		return $this->remote_request( $url, $allow_cache, $expiration );
	}

	/**
	 * Get a specific record.
	 *
	 * @param string A record ID.
	 * @param array  Returns specified fields only.
	 * @param bool   Allow API calls to be cached.
	 * @param int    Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function get_record( $record_id = false, $fields = array(), $allow_cache = true, $expiration = 30 ) {
		if ( false === $record_id ) {
			return false;
		}

		if ( ! $this->site_uuid ) {
			return false;
		}

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url = $this->request_url( '/sites/' . $this->site_uuid . '/records/' . $record_id, $args );

		return $this->remote_request( $url, $allow_cache, $expiration );
	}

	/**
	 * Get all records.
	 *
	 * @param array Returns specified fields only.
	 * @param bool  Allow API calls to be cached.
	 * @param int   Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function get_records( $fields = array(), $allow_cache = true, $expiration = 120 ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url = $this->request_url( '/sites/' . $this->site_uuid . '/records', $args );

		return $this->remote_request( $url, 'GET', null, $allow_cache, $expiration );
	}

	/**
	 * Create a new record.
	 *
	 * @param array Record data.
	 * @param array Returns specified fields only.
	 *
	 * @return mixed
	 */
	public function new_record( $record, $fields = array() ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$record['site_id'] = $this->site_uuid;

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url    = $this->request_url( '/sites/' . $this->site_uuid . '/records', array(), $record );
		$method = 'POST';

		return $this->remote_request( $url, $method, $record );
	}

	/**
	 * Helper function to create and escape a URL for an API request.
	 *
	 * @param string The endpoint path, with a starting slash.
	 * @param array  The $_GET args.
	 *
	 * @return string A properly escaped URL.
	 */
	protected function request_url( $path, $args = array() ) {
		return esc_url_raw(
			add_query_arg(
				$args,
				untrailingslashit( $this->api_url ) . $path //use this when /version/ is implemented: trailingslashit( $this->api_url ) . $this->api_version . $path
			)
		);
	}

	/**
	 * Helper function to query the marketplace API via wp_remote_request.
	 *
	 * @param string The url to access.
	 * @param string The method of the request.
	 * @param array  The headers sent during the request.
	 * @param bool   Allow API calls to be cached.
	 * @param int    Set transient expiration in seconds.
	 *
	 * @return object The results of the wp_remote_request request.
	 */
	protected function remote_request( $url = '', $method = 'GET', $body = null, $allow_cache = true, $expiration = 300 ) {
		if ( empty( $url ) ) {
			return false;
		}

		$args = array(
			'headers' => array( 'stream-api-master-key' => $this->api_key ),
			'method'  => $method,
			'body'    => isset( $body ) ? json_encode( $body ) : '',
		);

		$transient = 'wp_stream_' . md5( $url );

		if ( 'GET' === $method && $allow_cache ) {
			if ( false === ( $request = get_transient( $transient ) ) ) {
				$request = wp_remote_request( $url, $args );
				set_transient( $transient, $request, $expiration );
			}
		} else {
			$request = wp_remote_request( $url, $args );
		}

		if ( ! is_wp_error( $request ) ) {
			$data = apply_filters( 'wp_stream_api_request_data', json_decode( $request['body'] ), $url, $args );

			if ( in_array( $request['response']['code'], $this->success_status_codes ) ) {
				return $data;
			} else {
				$this->errors['errors']['http_code'] = $request['response']['code'];
			}

			if ( isset( $data->error ) ) {
				$this->errors['errors']['api_error'] = $data->error;
			}
		} else {
			$this->errors['errors']['remote_request_error'] = $request->get_error_message();
			WP_Stream::admin_notices( sprintf( '<strong>%s</strong> %s.', __( 'Stream API Error.', 'stream' ), $this->errors['errors']['remote_request_error'] ) );
		}

		if ( ! empty( $this->errors ) ) {
			delete_transient( $transient );
		}

		return false;
	}
}