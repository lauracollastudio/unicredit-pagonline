<?php

if (! defined('ABSPATH')) {
	exit;
}

class WC_Gateway_Unicredit_PagOnline extends WC_Payment_Gateway
{

	public function __construct()
	{
		$this->id                 = 'unicredit_pagonline';
		$this->has_fields         = false;
		$this->method_title       = __('UniCredit PagOnline Imprese', 'unicredit-pagonline');
		$this->method_description = __('Carta di Credito, MyBank, Bancomat Pay e altri strumenti tramite la pagina di pagamento ospitata UniCredit PagOnline Imprese.', 'unicredit-pagonline');

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled     = $this->get_option('enabled');

		$this->testmode          = 'yes' === $this->get_option('testmode');
		$this->debug             = 'yes' === $this->get_option('debug');
		$this->transaction_type  = $this->get_option('transaction_type', 'PURCHASE');
		$this->lang_id           = $this->get_option('lang_id', 'IT');

		$this->tid      = $this->testmode ? $this->get_option('test_tid') : $this->get_option('live_tid');
		$this->ksig     = $this->testmode ? $this->get_option('test_ksig') : $this->get_option('live_ksig');
		$this->wsdl_url = $this->testmode ? $this->get_option('test_wsdl') : $this->get_option('live_wsdl');

		$this->enable_bacs_link = 'yes' === $this->get_option('enable_bacs_link');

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_api_unicredit_pagonline', array($this, 'handle_return'));

		// Optional: generate a UniCredit pay-by-link for orders placed with
		// WooCommerce's own core "Direct Bank Transfer" (BACS) gateway, and email
		// it alongside the bank details, as a faster alternative to the wire.
		add_action('woocommerce_order_status_on-hold', array($this, 'maybe_create_bacs_pay_by_link'), 10, 2);
		add_action('woocommerce_email_before_order_table', array($this, 'add_pay_by_link_to_email'), 20, 4);
	}

	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __('Abilita/Disabilita', 'unicredit-pagonline'),
				'type'    => 'checkbox',
				'label'   => __('Abilita UniCredit PagOnline Imprese', 'unicredit-pagonline'),
				'default' => 'no',
			),
			'title'            => array(
				'title'       => __('Titolo', 'unicredit-pagonline'),
				'type'        => 'text',
				'description' => __('Il TID fornito è un Selector: alla cassa il cliente vedrà tutti gli strumenti abilitati (carte, MyBank, Bancomat Pay, ecc.), non solo le carte. Usa un titolo generico.', 'unicredit-pagonline'),
				'default'     => __('Carta di Credito, MyBank, Bancomat Pay', 'unicredit-pagonline'),
				'desc_tip'    => false,
			),
			'description'      => array(
				'title'   => __('Descrizione', 'unicredit-pagonline'),
				'type'    => 'textarea',
				'default' => __('Sarai reindirizzato alla pagina sicura di UniCredit per completare il pagamento.', 'unicredit-pagonline'),
			),
			'transaction_type' => array(
				'title'       => __('Tipo transazione', 'unicredit-pagonline'),
				'type'        => 'select',
				'options'     => array(
					'PURCHASE' => __('PURCHASE – addebito diretto', 'unicredit-pagonline'),
					'AUTH'     => __('AUTH – pre-autorizzazione (conferma manuale in BackOffice entro 30gg, 7gg Maestro)', 'unicredit-pagonline'),
				),
				'default'     => 'PURCHASE',
			),
			'lang_id'          => array(
				'title'   => __('Lingua pagina di pagamento', 'unicredit-pagonline'),
				'type'    => 'select',
				'options' => array(
					'IT' => 'Italiano',
					'EN' => 'English',
				),
				'default' => 'IT',
			),
			'enable_bacs_link' => array(
				'title'       => __('Link di pagamento su Bonifico Bancario', 'unicredit-pagonline'),
				'type'        => 'checkbox',
				'label'       => __('Includi un link di pagamento UniCredit nell\'email di Bonifico Bancario (BACS)', 'unicredit-pagonline'),
				'description' => __('Quando un ordine viene pagato con il Bonifico Bancario nativo di WooCommerce, genera (con le stesse credenziali di questa pagina) un link di pagamento UniCredit e lo aggiunge, oltre ai dati bancari, nell\'email "In attesa del pagamento". Se il cliente lo usa, l\'ordine passa automaticamente a pagato.', 'unicredit-pagonline'),
				'default'     => 'no',
			),
			'testmode'         => array(
				'title'   => __('Modalità test', 'unicredit-pagonline'),
				'type'    => 'checkbox',
				'label'   => __('Usa le credenziali e l\'endpoint di staging', 'unicredit-pagonline'),
				'default' => 'yes',
			),
			'debug'            => array(
				'title'   => __('Log', 'unicredit-pagonline'),
				'type'    => 'checkbox',
				'label'   => __('Registra le richieste/risposte SOAP nel log di WooCommerce', 'unicredit-pagonline'),
				'default' => 'yes',
			),
			'test_tid'         => array(
				'title'   => __('Terminal ID (test)', 'unicredit-pagonline'),
				'type'    => 'text',
				'default' => 'UNI_SEL',
			),
			'test_ksig'        => array(
				'title'   => __('API key / kSig (test)', 'unicredit-pagonline'),
				'type'    => 'password',
				'default' => 'UNI_TESTKEY',
			),
			'test_wsdl'        => array(
				'title'       => __('URL WSDL (test)', 'unicredit-pagonline'),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => 'https://.../UNI_CG_SERVICES/services/PaymentInitGatewayPort?wsdl',
			),
			'live_tid'         => array(
				'title'   => __('Terminal ID (produzione)', 'unicredit-pagonline'),
				'type'    => 'text',
				'default' => '',
			),
			'live_ksig'        => array(
				'title'   => __('API key / kSig (produzione)', 'unicredit-pagonline'),
				'type'    => 'password',
				'default' => '',
			),
			'live_wsdl'        => array(
				'title'   => __('URL WSDL (produzione)', 'unicredit-pagonline'),
				'type'    => 'text',
				'default' => '',
			),
		);
	}

	public function is_available()
	{
		if ('yes' !== $this->enabled) {
			return false;
		}
		if ('EUR' !== get_woocommerce_currency()) {
			return false;
		}
		if (empty($this->tid) || empty($this->ksig) || empty($this->wsdl_url)) {
			return false;
		}
		return parent::is_available();
	}

	private function api()
	{
		return new Unicredit_PagOnline_API($this->wsdl_url, $this->tid, $this->ksig, $this->debug);
	}

	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return array('result' => 'failure');
		}

		$shop_id = $order->get_id() . '-' . time();
		$amount  = (int) round($order->get_total() * 100);

		$return_url = add_query_arg(
			array(
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			WC()->api_request_url('unicredit_pagonline')
		);

		$response = $this->api()->init(
			array(
				'shop_id'       => $shop_id,
				'shop_user_ref' => $order->get_billing_email(),
				'tr_type'       => $this->transaction_type,
				'amount'        => $amount,
				'currency_code' => 'EUR',
				'lang_id'       => $this->lang_id,
				'notify_url'    => $return_url,
				'error_url'     => $return_url,
				// "#" was empirically confirmed to make UniCredit reject the whole
				// request with IGFS_20044 (CAMPO PAYMENT DESCRIPTION NON VALIDO) —
				// avoid special characters here, plain alphanumeric only.
				'description'   => sprintf('Ordine n. %s', $order->get_order_number()),
			)
		);

		if (is_wp_error($response)) {
			wc_add_notice(__('Impossibile contattare il gateway di pagamento UniCredit. Riprova tra qualche minuto.', 'unicredit-pagonline'), 'error');
			$order->add_order_note('UniCredit init() error: ' . $response->get_error_message());
			return array('result' => 'failure');
		}

		if (! empty($response['error']) || empty($response['redirect_url'])) {
			wc_add_notice(
				sprintf(__('Pagamento non avviato: %s', 'unicredit-pagonline'), $response['error_desc']),
				'error'
			);
			$order->add_order_note(sprintf('UniCredit init() failed: %s - %s', $response['rc'], $response['error_desc']));
			return array('result' => 'failure');
		}

		$order->update_meta_data('_unicredit_shop_id', $shop_id);
		$order->update_meta_data('_unicredit_payment_id', $response['payment_id']);
		$order->update_status('pending', __('In attesa del pagamento UniCredit PagOnline.', 'unicredit-pagonline'));
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $response['redirect_url'],
		);
	}

	/**
	 * notifyURL / errorURL callback. UniCredit only redirects the browser here —
	 * it is not a server-to-server call — so the cron fallback below is what
	 * catches customers who close the tab before the redirect happens.
	 */
	public function handle_return()
	{
		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key      = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = $order_id ? wc_get_order($order_id) : false;

		if (! $order || ! hash_equals($order->get_order_key(), $key)) {
			wp_safe_redirect(wc_get_checkout_url());
			exit;
		}

		$this->check_order_status($order);

		/*
		 * Deliberately NOT using wc_add_notice() here. The block-based checkout's
		 * Store API treats any queued session error/notice as a persistent
		 * cart-validation failure and never clears it afterward (unlike the
		 * classic checkout template, which flushes notices via
		 * wc_print_notices()) — confirmed live: one wc_add_notice( ..., 'error' )
		 * call here permanently blocked all further checkout attempts for that
		 * customer, for every payment method, until their cookies were cleared.
		 * The order-received and order-pay pages already communicate status on
		 * their own, so a notice isn't needed.
		 */
		if ($order->has_status(array('processing', 'completed', 'on-hold', 'pending'))) {
			wp_safe_redirect($order->get_checkout_order_received_url());
		} else {
			wp_safe_redirect(wc_get_checkout_url());
		}
		exit;
	}

	/**
	 * Fired on woocommerce_order_status_on-hold. Generates a UniCredit pay-by-link
	 * for orders placed with WooCommerce's own core BACS (bank transfer) gateway,
	 * so it can be emailed alongside the bank details by add_pay_by_link_to_email().
	 * Idempotent: does nothing if a link already exists for the order.
	 */
	public function maybe_create_bacs_pay_by_link($order_id, $order = null)
	{
		if (! $this->enable_bacs_link || empty($this->tid) || empty($this->ksig) || empty($this->wsdl_url)) {
			return;
		}

		// Use the order instance WooCommerce handed us, not a fresh wc_get_order()
		// lookup: wc_get_order() constructs a brand new object from the DB rather
		// than reusing the one already in play for this request (confirmed live —
		// WC_Order_Factory::get_order() only caches WC_Abstract_Legacy_Order
		// instances, not HPOS WC_Order objects). A separate copy would still
		// successfully persist our meta to the DB, but the *original* order object
		// that the rest of this request (including the email about to be sent)
		// keeps using would never see it in its own in-memory meta cache — the
		// link would exist in the DB but silently never appear in the email.
		if (! $order instanceof WC_Order) {
			$order = wc_get_order($order_id);
		}
		if (! $order || 'bacs' !== $order->get_payment_method()) {
			return;
		}

		if ($order->get_meta('_unicredit_shop_id')) {
			return;
		}

		$shop_id = $order->get_id() . '-bacs-' . time();
		$amount  = (int) round($order->get_total() * 100);

		$notify_url = add_query_arg(
			array(
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			WC()->api_request_url('unicredit_pagonline')
		);

		$response = $this->api()->init(
			array(
				'shop_id'       => $shop_id,
				'shop_user_ref' => $order->get_billing_email(),
				'tr_type'       => $this->transaction_type,
				'amount'        => $amount,
				'currency_code' => 'EUR',
				'lang_id'       => $this->lang_id,
				'notify_url'    => $notify_url,
				'error_url'     => $notify_url,
				'description'   => sprintf('Ordine n. %s', $order->get_order_number()),
			)
		);

		if (is_wp_error($response) || ! empty($response['error']) || empty($response['redirect_url'])) {
			$order->add_order_note(
				'UniCredit pay-by-link (BACS) init() failed: ' . (is_wp_error($response)
					? $response->get_error_message()
					: $response['rc'] . ' - ' . $response['error_desc'])
			);
			return;
		}

		$order->update_meta_data('_unicredit_shop_id', $shop_id);
		$order->update_meta_data('_unicredit_payment_id', $response['payment_id']);
		$order->update_meta_data('_unicredit_redirect_url', $response['redirect_url']);
		$order->save();
	}

	/**
	 * Fired on woocommerce_email_before_order_table. Appends the pay-by-link
	 * (generated above) under the bank details already printed by WooCommerce's
	 * core BACS gateway, on the "order on-hold" / invoice emails, while the link
	 * is still unpaid.
	 */
	public function add_pay_by_link_to_email($order, $sent_to_admin, $plain_text, $email)
	{
		if ($sent_to_admin || ! $this->enable_bacs_link || ! $order instanceof WC_Order) {
			return;
		}
		if ('bacs' !== $order->get_payment_method()) {
			return;
		}
		if (! isset($email->id) || ! in_array($email->id, array('customer_on_hold_order', 'customer_invoice'), true)) {
			return;
		}

		$redirect_url = $order->get_meta('_unicredit_redirect_url');
		if (empty($redirect_url) || 'yes' === $order->get_meta('_unicredit_verify_done')) {
			return;
		}

		if ($plain_text) {
			echo "\n\n" . esc_html__('Preferisci pagare subito online invece di fare il bonifico?', 'unicredit-pagonline') . "\n" . esc_url($redirect_url) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo '<p>' . esc_html__('Preferisci pagare subito online invece di fare il bonifico?', 'unicredit-pagonline') . '</p>';
		echo '<p><a href="' . esc_url($redirect_url) . '" style="display:inline-block;padding:10px 18px;background:#e2001a;color:#ffffff;text-decoration:none;border-radius:4px;">' . esc_html__('Paga ora', 'unicredit-pagonline') . '</a></p>';
	}

	/**
	 * Calls verify() and updates the order accordingly. Safe to call repeatedly
	 * (from the return URL and from the cron fallback) since it's a no-op once
	 * the order has reached a final state.
	 *
	 * '_unicredit_verify_done' (set once a final rc is reached, below) is what
	 * actually tracks "is this resolved", rather than the order status alone:
	 * for this gateway's own checkout flow, 'on-hold'/'failed' are themselves
	 * final states (AUTH pre-auth / a previous failed verify), but for a
	 * pay-by-link emailed on a BACS order, 'on-hold' is BACS's own *starting*
	 * state, so it must not short-circuit verification here.
	 */
	public function check_order_status(WC_Order $order)
	{
		if ('yes' === $order->get_meta('_unicredit_verify_done')) {
			return;
		}

		if ($order->has_status(array('processing', 'completed', 'cancelled', 'refunded'))) {
			return;
		}

		if ('unicredit_pagonline' === $order->get_payment_method() && $order->has_status(array('on-hold', 'failed'))) {
			return;
		}

		$payment_id = $order->get_meta('_unicredit_payment_id');
		$shop_id    = $order->get_meta('_unicredit_shop_id');
		if (empty($payment_id) || empty($shop_id)) {
			return;
		}

		$response = $this->api()->verify($shop_id, $payment_id);

		if (is_wp_error($response)) {
			$order->add_order_note('UniCredit verify() error: ' . $response->get_error_message());
			return;
		}

		$rc = $response['rc'];

		if ('IGFS_000' === $rc) {
			$order->update_meta_data('_unicredit_tran_id', $response['tran_id']);
			$order->update_meta_data('_unicredit_auth_code', $response['auth_code']);
			$order->update_meta_data('_unicredit_brand', $response['brand']);
			$order->update_meta_data('_unicredit_verify_done', 'yes');
			$order->save();

			if ('AUTH' === $this->transaction_type) {
				$order->update_status(
					'on-hold',
					sprintf(
						/* translators: %s: UniCredit tranID */
						__('Pre-autorizzazione UniCredit confermata (tranID %s). Confermare manualmente in BackOffice entro 30 giorni (7 per Maestro) o il plafond verrà rilasciato.', 'unicredit-pagonline'),
						$response['tran_id']
					)
				);
			} else {
				$order->payment_complete($response['tran_id']);
				$order->add_order_note(
					sprintf(
						/* translators: 1: tranID, 2: authCode */
						__('Pagamento UniCredit confermato (tranID %1$s, authCode %2$s).', 'unicredit-pagonline'),
						$response['tran_id'],
						$response['auth_code']
					)
				);
			}

			if (WC()->cart) {
				WC()->cart->empty_cart();
			}
			return;
		}

		if (in_array($rc, array('IGFS_814', 'IGFS_890'), true)) {
			$order->add_order_note('UniCredit: transazione ancora in corso (' . $rc . ').');
			return;
		}

		if ('IGFS_20090' === $rc) {
			$order->update_meta_data('_unicredit_verify_done', 'yes');
			$order->save();
			$order->update_status('cancelled', __('Pagamento annullato dal cliente su UniCredit PagOnline.', 'unicredit-pagonline'));
			return;
		}

		$order->update_meta_data('_unicredit_verify_done', 'yes');
		$order->save();
		$order->update_status(
			'failed',
			sprintf('UniCredit verify() rc=%s: %s', $rc, $response['error_desc'])
		);
	}
}

add_action('unicredit_pagonline_check_pending', 'unicredit_pagonline_check_pending_orders');
function unicredit_pagonline_check_pending_orders()
{
	$direct_checkout_orders = wc_get_orders(
		array(
			'status'         => 'pending',
			'payment_method' => 'unicredit_pagonline',
			'date_created'   => '<' . (time() - 10 * MINUTE_IN_SECONDS),
			'limit'          => 50,
		)
	);

	// BACS orders carrying an emailed UniCredit pay-by-link stay 'on-hold' the
	// whole time (that's BACS's own default status), rather than 'pending', so
	// they need a separate query; check_order_status() itself is what actually
	// skips ones already resolved via '_unicredit_verify_done'.
	$bacs_link_orders = wc_get_orders(
		array(
			'status'         => 'on-hold',
			'payment_method' => 'bacs',
			'date_created'   => '<' . (time() - 10 * MINUTE_IN_SECONDS),
			'limit'          => 50,
			'meta_query'     => array(
				array('key' => '_unicredit_shop_id', 'compare' => 'EXISTS'),
			),
		)
	);

	$orders = array_merge($direct_checkout_orders, $bacs_link_orders);
	if (empty($orders)) {
		return;
	}

	$gateways = WC()->payment_gateways()->payment_gateways();
	$gateway  = isset($gateways['unicredit_pagonline']) ? $gateways['unicredit_pagonline'] : null;
	if (! $gateway instanceof WC_Gateway_Unicredit_PagOnline) {
		return;
	}

	foreach ($orders as $order) {
		$gateway->check_order_status($order);
	}
}
