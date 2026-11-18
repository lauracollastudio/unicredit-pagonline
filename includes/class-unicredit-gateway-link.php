<?php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Second UniCredit PagOnline Imprese checkout method, on its own terminal
 * ("Terminal ID link di pagamento"). Same hosted-payment-page flow as
 * WC_Gateway_Unicredit_PagOnline (clicking it opens UniCredit's own payment
 * page/popup for that terminal) — just a distinct payment method with its own
 * credentials, title and description.
 */
class WC_Gateway_Unicredit_PagOnline_Link extends WC_Gateway_Unicredit_PagOnline_Base
{

	public function __construct()
	{
		$this->id                  = 'unicredit_pagonline_link';
		$this->method_title        = __('UniCredit PagOnline – Bank Payment', 'unicredit-pagonline');
		$this->method_description  = __('Pagamento tramite link UniCredit, su un terminal dedicato separato dalle carte/MyBank/Bancomat Pay.', 'unicredit-pagonline');

		parent::__construct();
	}

	protected function get_default_title()
	{
		return __('Bonifico bancario, MyBank', 'unicredit-pagonline');
	}

	protected function get_live_tid_field_title()
	{
		return __('Terminal ID  (produzione)', 'unicredit-pagonline');
	}
}
