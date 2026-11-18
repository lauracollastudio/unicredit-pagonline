<?php

if (! defined('ABSPATH')) {
	exit;
}

class WC_Gateway_Unicredit_PagOnline extends WC_Gateway_Unicredit_PagOnline_Base
{

	public function __construct()
	{
		$this->id                 = 'unicredit_pagonline';
		$this->method_title       = __('UniCredit PagOnline', 'unicredit-pagonline');
		$this->method_description = __('Carta di Credito, Bancomat Pay e altri strumenti tramite la pagina di pagamento ospitata UniCredit PagOnline Imprese.', 'unicredit-pagonline');

		parent::__construct();
	}

	protected function get_default_title()
	{
		return __('Carta di Credito, MyBank, Bancomat Pay', 'unicredit-pagonline');
	}

	public function init_form_fields()
	{
		parent::init_form_fields();

		$this->form_fields['title']['description'] = __('Il TID fornito è un Selector: alla cassa il cliente vedrà tutti gli strumenti abilitati (carte, MyBank, Bancomat Pay, ecc.), non solo le carte. Usa un titolo generico.', 'unicredit-pagonline');
		$this->form_fields['title']['desc_tip']     = false;
	}
}

add_action('unicredit_pagonline_check_pending', 'unicredit_pagonline_check_pending_orders');
function unicredit_pagonline_check_pending_orders()
{
	$gateways = WC()->payment_gateways()->payment_gateways();

	foreach ($gateways as $gateway) {
		if (! $gateway instanceof WC_Gateway_Unicredit_PagOnline_Base) {
			continue;
		}

		$orders = wc_get_orders(
			array(
				'status'         => 'pending',
				'payment_method' => $gateway->id,
				'date_created'   => '<' . (time() - 10 * MINUTE_IN_SECONDS),
				'limit'          => 50,
			)
		);

		foreach ($orders as $order) {
			$gateway->check_order_status($order);
		}
	}
}
