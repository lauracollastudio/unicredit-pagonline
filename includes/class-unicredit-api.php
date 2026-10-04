<?php
/**
 * Thin SOAP client for the UniCredit PagOnline Imprese "PaymentInitGateway" service.
 *
 * Field names, operation names ("Init" / "Verify") and the wrapped "request" parameter
 * shape come from the real sample messages in the spec's Appendix D, not just the prose
 * tables (which use PascalCase for readability but the wire format is camelCase).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Unicredit_PagOnline_API {

	private $wsdl_url;
	private $tid;
	private $ksig;
	private $debug;

	public function __construct( $wsdl_url, $tid, $ksig, $debug = false ) {
		$this->wsdl_url = $wsdl_url;
		$this->tid      = $tid;
		$this->ksig     = $ksig;
		$this->debug    = (bool) $debug;
	}

	/**
	 * Appendix A: HMAC-SHA256 of the concatenated field values, Base64 encoded.
	 * Fields that are null are skipped entirely (not sent as empty strings).
	 */
	private function sign( array $fields ) {
		$concat = '';
		foreach ( $fields as $field ) {
			if ( null !== $field ) {
				$concat .= $field;
			}
		}
		return base64_encode( hash_hmac( 'sha256', $concat, $this->ksig, true ) );
	}

	/**
	 * @return SoapClient|WP_Error
	 */
	private function get_client() {
		try {
			return new SoapClient(
				$this->wsdl_url,
				array(
					'trace'              => true,
					'exceptions'         => true,
					'cache_wsdl'         => WSDL_CACHE_NONE,
					'connection_timeout' => 20,
					'stream_context'     => stream_context_create( array( 'http' => array( 'timeout' => 25 ) ) ),
				)
			);
		} catch ( SoapFault $e ) {
			return new WP_Error( 'unicredit_wsdl_fault', $e->getMessage() );
		}
	}

	private function log( $message ) {
		if ( ! $this->debug ) {
			return;
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'unicredit-pagonline' ) );
		}
	}

	/**
	 * Converts the stdClass SOAP response to an array keyed case-insensitively.
	 * Confirmed live: SoapClient does NOT unwrap the single "response" part for
	 * this WSDL, so the actual fields are nested one level deeper than the plain
	 * property access the docs would suggest ($result->response->rc, not
	 * $result->rc) — unwrap that single wrapper if present.
	 */
	private function response_field( $response, $key ) {
		$array = json_decode( wp_json_encode( $response ), true );
		if ( ! is_array( $array ) ) {
			return null;
		}
		if ( 1 === count( $array ) ) {
			$only = reset( $array );
			if ( is_array( $only ) ) {
				$array = $only;
			}
		}
		$lower = array_change_key_case( $array, CASE_LOWER );
		$key   = strtolower( $key );
		return isset( $lower[ $key ] ) ? $lower[ $key ] : null;
	}

	/**
	 * @param array $args {
	 *   @type string $shop_id        Unique external key for this payment attempt.
	 *   @type string $shop_user_ref  Customer identifier, e.g. email.
	 *   @type string $tr_type        PURCHASE | AUTH | VERIFY.
	 *   @type int    $amount         Amount in cents (100 = 1.00 EUR).
	 *   @type string $currency_code  "EUR".
	 *   @type string $lang_id        IT | EN.
	 *   @type string $notify_url
	 *   @type string $error_url
	 *   @type string $description    Optional.
	 * }
	 * @return array|WP_Error
	 */
	public function init( array $args ) {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		/*
		 * Per Appendix A, the signature is documented as covering Tid, ShopID,
		 * ShopUserRef, ShopUserName, ShopUserAccount, TrType, Amount,
		 * CurrencyCode, LangID, NotifyURL, ErrorURL, AddInfo1-5, Description,
		 * Recurrent, PaymentReason, FreeText, ValidityExpire (skipping any that
		 * are null). Empirically verified live against the test server: on this
		 * merchant account, including Description in the signature makes the
		 * server reject it with IGFS_20022 "CAMPO SIGNATURE NON VALIDO", even
		 * though Description is still accepted as a normal (unsigned) request
		 * field. So it's deliberately left out of the signature below. If
		 * AddInfo1-5 / Recurrent / PaymentReason / FreeText / ValidityExpire are
		 * ever used, verify each against the sandbox the same way before
		 * trusting the spec's field list.
		 */
		$signature = $this->sign(
			array(
				$this->tid,
				$args['shop_id'],
				isset( $args['shop_user_ref'] ) ? $args['shop_user_ref'] : null,
				null, // ShopUserName
				null, // ShopUserAccount
				$args['tr_type'],
				$args['amount'],
				$args['currency_code'],
				$args['lang_id'],
				$args['notify_url'],
				$args['error_url'],
			)
		);

		$request = array(
			'tid'          => $this->tid,
			'signature'    => $signature,
			'shopID'       => $args['shop_id'],
			'trType'       => $args['tr_type'],
			'amount'       => $args['amount'],
			'currencyCode' => $args['currency_code'],
			'langID'       => $args['lang_id'],
			'notifyURL'    => $args['notify_url'],
			'errorURL'     => $args['error_url'],
		);
		if ( ! empty( $args['shop_user_ref'] ) ) {
			$request['shopUserRef'] = $args['shop_user_ref'];
		}
		if ( ! empty( $args['description'] ) ) {
			$request['description'] = $args['description'];
		}

		try {
			// The WSDL's Init operation takes a single "request" wrapper element
			// (confirmed against the spec's Appendix D sample message), not a
			// flat list of fields.
			$result = $client->Init( array( 'request' => $request ) );
			$this->log( 'Init request: ' . $client->__getLastRequest() );
			$this->log( 'Init response: ' . $client->__getLastResponse() );
		} catch ( SoapFault $e ) {
			$this->log( 'Init SoapFault: ' . $e->getMessage() );
			return new WP_Error( 'unicredit_soap_fault', $e->getMessage() );
		}

		return array(
			'error'        => filter_var( $this->response_field( $result, 'error' ), FILTER_VALIDATE_BOOLEAN ),
			'rc'           => $this->response_field( $result, 'rc' ),
			'error_desc'   => $this->response_field( $result, 'errorDesc' ),
			'payment_id'   => $this->response_field( $result, 'paymentID' ),
			'redirect_url' => $this->response_field( $result, 'redirectURL' ),
		);
	}

	/**
	 * @return array|WP_Error
	 */
	public function verify( $shop_id, $payment_id ) {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$signature = $this->sign( array( $this->tid, $shop_id, $payment_id ) );

		$request = array(
			'tid'       => $this->tid,
			'signature' => $signature,
			'shopID'    => $shop_id,
			'paymentID' => $payment_id,
		);

		try {
			$result = $client->Verify( array( 'request' => $request ) );
			$this->log( 'Verify request: ' . $client->__getLastRequest() );
			$this->log( 'Verify response: ' . $client->__getLastResponse() );
		} catch ( SoapFault $e ) {
			$this->log( 'Verify SoapFault: ' . $e->getMessage() );
			return new WP_Error( 'unicredit_soap_fault', $e->getMessage() );
		}

		return array(
			'error'       => filter_var( $this->response_field( $result, 'error' ), FILTER_VALIDATE_BOOLEAN ),
			'rc'          => $this->response_field( $result, 'rc' ),
			'error_desc'  => $this->response_field( $result, 'errorDesc' ),
			'tran_id'     => $this->response_field( $result, 'tranID' ),
			'auth_code'   => $this->response_field( $result, 'authCode' ),
			'enr_status'  => $this->response_field( $result, 'enrStatus' ),
			'auth_status' => $this->response_field( $result, 'authStatus' ),
			'brand'       => $this->response_field( $result, 'brand' ),
		);
	}
}
