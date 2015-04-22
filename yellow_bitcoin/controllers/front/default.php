<?php
/**
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *         DISCLAIMER   *
 * ***************************************
 * Do not edit or add to this file if you wish to upgrade Prestashop to newer
 * versions in the future.
 * ****************************************************
 *
 * @category    Yellow
 * @package    YellowPay
 * @author    Alexander Simonchik
 * @copyright Copyright (c) Yellow, Inc. (http://yellowpay.co)
 * @license   https://github.com/YellowPay/yellow-prestashop/blob/master/yellow_bitcoin/Yellow-license.txt
 */

require_once _PS_MODULE_DIR_.'yellow_bitcoin/includer.php';

class yellow_bitcoindefaultModuleFrontController extends ModuleFrontController
{
	public function __construct()
	{
		parent::__construct();
		$this->content_only = 1;
		$this->display_header = 0;
		$this->display_footer = 0;

		$this->context = Context::getContext();
	}

	public function initContent()
	{
		parent::initContent();

		$action = Tools::getValue('action');
		if (!empty($action) && method_exists($this, 'ajaxProcess'.Tools::ucfirst(Tools::toCamelCase($action))))
			return $this->{'ajaxProcess'.Tools::toCamelCase($action)}();
		elseif (!empty($action) && method_exists($this, 'process'.Tools::ucfirst(Tools::toCamelCase($action))))
			return $this->{'process'.Tools::toCamelCase($action)}();
		else
			return $this->processDefault();
	}

	/**
	 *
	 * return 403 header
	 * @return mixed
	 *
	 */
	private function returnForbidden()
	{
		header('HTTP/1.0 403 Forbidden');

		die('You are forbidden!');
	}

	private function returnSuccessHeader()
	{
		header('HTTP/1.1 200 OK');

		die('Success');
	}

	private function getHeader($header_name)
	{
		foreach (getallheaders() as $name => $value)
		{
			if ($name == $header_name)
				return $value;
		}
	}

	protected function processStatus()
	{
		$invoice = $this->module->getSessionValue('invoice');
		$id = YellowBitcoinIpn::getReferenceByYellowInvoiceId($invoice['id']);
		YellowBitcoinDbLog::log($id, 'processStatus');

		try {
			$status = YellowBitcoinIpn::checkInvoiceStatus($invoice['id']);
			if ($status == false)
			{
				YellowBitcoinDbLog::log($id, 'Invoice status check failed');
				return $this->returnForbidden();
			}

			$order_id = Order::getOrderByCartId($id);
			if (!$order_id)
				YellowBitcoinDbLog::log($id, 'This cart ID does not have a recent order. This page may have been accessed directly');
			else
			{
				$order = new Order($order_id);
				$customer = new Customer($order->id_customer);
			}

			switch ($status)
			{
				case 'new':
					Tools::redirect($this->context->link->getModuleLink('yellow_bitcoin', 'payment'));
					break;
				case 'paid':
				case 'partial':
				case 'authorizing':
					Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$id.'&id_module='.(int)$this->module->id.'&id_order='.
					$order->id.'&key='.$customer->secure_key);
					break;
				case 'failed':
					Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$id.'&id_module='.(int)$this->module->id.'&id_order='.
					$order->id.'&key='.$customer->secure_key);
					//return $this->_redirect('checkout/onepage/failure');
					break;
				case 'refund_requested':
				case 'refund_owed':
					Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$id.'&id_module='.(int)$this->module->id.'&id_order='.
					$order->id.'&key='.$customer->secure_key);
					//return $this->_redirect('checkout/onepage/failure');
					break;
				case 'expired':
					Tools::redirect($this->context->link->getModuleLink('yellow_bitcoin', 'payment'));
					//return $this->_redirect('checkout/onepage/failure');
					break;
				default:
					YellowBitcoinDbLog::log($id, 'Unknown invoice status. Status:'.$status);
					return $this->returnForbidden();
					//break;
			}
		} catch (Exception $e) {
			YellowBitcoinDbLog::log($id, 'An error occurred: '.$e->getMessage().' on line '.$e->getLine());
			Tools::redirect($this->context->link->getModuleLink('yellow_bitcoin', 'payment'));
		}
	}

	protected function processIpn()
	{
		$id = base64_decode(Tools::getValue('id'));
		if ($_SERVER['REQUEST_METHOD'] != 'POST')
		{
			YellowBitcoinDbLog::log($id, 'REQUEST_METHOD isn\'t POST');
			YellowBitcoinDbLog::log($id, '[php://]: '.Tools::file_get_contents('php://input'));
			return $this->returnForbidden();
		}

		$ip = long2ip(Tools::getRemoteAddr());
		YellowBitcoinDbLog::log($id, 'Start IPN request validation');
		YellowBitcoinDbLog::log($id, 'IP Address of the sender '.$ip);
		$payload = Tools::file_get_contents('php://input');
		$public_key = $this->getHeader('API-Key');
		$nonce = $this->getHeader('API-Nonce');
		$request_signature = $this->getHeader('API-Sign');
		/* start to validate the signature */
		YellowBitcoinDbLog::log($id, 'Received payload: '.$payload);
		YellowBitcoinDbLog::log($id, 'Received signature: '.$request_signature);
		if (!$public_key || !$nonce || !$request_signature || !$payload)
		{
			YellowBitcoinDbLog::log($id, 'Credentials missing. Exit.');
			YellowBitcoinDbLog::log($id, 'public_key: '.$public_key);
			YellowBitcoinDbLog::log($id, 'nonce: '.$nonce);
			YellowBitcoinDbLog::log($id, 'request_signature: '.$request_signature);

			return $this->returnForbidden();
		}
		$private_key = Configuration::get('YELLOWPAY_PRIVATE');
		$url = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$message = $nonce.$url.$payload;
		$current_signature = hash_hmac('sha256', $message, $private_key, false);
		YellowBitcoinDbLog::log($id, 'Calculated signature: '.$current_signature);
		if ($request_signature != $current_signature)
		{
			YellowBitcoinDbLog::log($id, 'IPN VALIDATION FAILED: '.$url);
			return $this->returnForbidden();
		}
		YellowBitcoinDbLog::log($id, 'IPN signature validation succeeded');
		/* end of validate the signature  */
		/* by this the request has passed validation */
		try {
			/* need to check the ip address of the source from a whitelist list of ips , otherwise this might be used illegaly to update orders  */
			YellowBitcoinDbLog::log($id, '----------- IPN request processing ------------');
			$body = Tools::jsonDecode($payload, true);
			$url = $body['url'];
			/* simple validation check | might be changed later */
			$count_yellow_log = Db::getInstance()->getValue('SELECT count(*) FROM `'._DB_PREFIX_.'yellow_bitcoin_ipn` WHERE url = "'.pSQL($url).'"');
			if (!$count_yellow_log == 1)
			{
				YellowBitcoinDbLog::log($id, 'URL validation failed: '.$url);
				YellowBitcoinDbLog::log($id, '----------- IPN request processing will be skipped -----------');

				return $this->returnForbidden();
			}

			/* skip quote + authorizing state because the order hasn't been placed yet */
			if ($body['status'] === 'authorizing')
			{
				YellowBitcoinDbLog::log($id, 'Cart id {'.$id.'} will be skipped because the payment hasn\'t been placed yet. IPN status: {'.$body['status'].'}');
				YellowBitcoinDbLog::log($id, '----------- IPN request processing will be skipped -----------');
				echo Tools::jsonEncode(array('message' => 'skipped'));
				return;
			}

			switch ($body['status'])
			{
				case 'paid':
					YellowBitcoinDbLog::log($id, 'Order paid');
					$order_id = Order::getOrderByCartId($id);
					$order = new Order($order_id);
					if (!$order->id)
					{
						YellowBitcoinDbLog::log($id, 'For this cart, order don\'t exists');
						YellowBitcoinDbLog::log($id, '----------- IPN request processing will be skipped -----------');
						echo Tools::jsonEncode(array('message' => 'skipped'));
						return;
					}
					else if ($order->current_state != Configuration::get('YP_OS_PAYMENT_VALID'))
					{
						$order_currency = new Currency($order->id_currency);
						$cart = new Cart($id);
						if ($cart->getOrderTotal() == $body['base_price'])
						{
							if ($order_currency->iso_code == $body['base_ccy'])
							{
								$new_history = new OrderHistory();
								$new_history->id_order = $order->id;
								$new_history->changeIdOrderState(Configuration::get('YP_OS_PAYMENT_VALID'), $order, true);
								$new_history->addWithemail();

								$this->returnSuccessHeader();
							}
							else
								YellowBitcoinDbLog::log($id, 'Currency of order {'.$order->id.'} is wrong. Order currency: '.$order_currency->iso_code.'; paid: '.
									$body['base_ccy']);
						}
						else
							YellowBitcoinDbLog::log($id, 'Paid amount of order {'.$order->id.'} is wrong. Order amount: '.$cart->getOrderTotal().'; paid: '.
								$body['base_price']);
					}
					break;
				case 'reissue':
					YellowBitcoinDbLog::log($id, 'Client re-issued the invoice. Invoice Id: '.
						$body['id']); //this must be changed when we had reissue / renew payment ready
					break;
				case 'partial':
					YellowBitcoinDbLog::log($id, 'Client made a partial payment. Invoice Id: '.
						$body['id']); //this must be changed when we had reissue / renew payment ready
					break;
				case 'failed':
				case 'invalid':
					YellowBitcoinDbLog::log($id, 'invalid or failed');
					$order_id = Cart::getOrderByCartId($id);
					$order = new Order($order_id);
					if (!$order->id)
					{
						YellowBitcoinDbLog::log($id, 'For this cart, order don\'t exists');
						YellowBitcoinDbLog::log($id, '----------- IPN request processing will be skipped -----------');
						echo Tools::jsonEncode(array('message' => 'skipped'));
						return;
					}
					else if ($order->current_state != Configuration::get('YP_OS_PAYMENT_ERROR'))
					{
						$new_history = new OrderHistory();
						$new_history->id_order = $order->id;
						$new_history->changeIdOrderState(Configuration::get('YP_OS_PAYMENT_ERROR'), $order, true);
						$new_history->addWithemail();
					}

					$this->returnSuccessHeader();
					break;
				/// its just a new invoice | authorizing , I will never expect a post with new status , though I had created the block of it
				case 'authorizing':
					YellowBitcoinDbLog::log($id, 'authorizing');
					break;
				case 'expired':
					YellowBitcoinDbLog::log($id, 'expired');
					break;
				case 'refund_owed':
					YellowBitcoinDbLog::log($id, 'refund_owed');
					break;
				case 'refund_requested':
					YellowBitcoinDbLog::log($id, 'refund_requested');
					break;
				case 'refund_paid':
					YellowBitcoinDbLog::log($id, 'refund_paid');
					$order_id = Cart::getOrderByCartId($id);
					$order = new Order($order_id);
					if (!$order->id)
					{
						YellowBitcoinDbLog::log($id, "For this cart, order don't exists");
						YellowBitcoinDbLog::log($id, '----------- IPN request processing will be skipped -----------');
						echo Tools::jsonEncode(array('message' => 'skipped'));
						return;
					}
					else if ($order->current_state != Configuration::get('PS_OS_REFUND'))
					{
						$new_history = new OrderHistory();
						$new_history->id_order = $order->id;
						$new_history->changeIdOrderState(Configuration::get('PS_OS_REFUND'), $order, true);
						$new_history->addWithemail();
					}

					$this->returnSuccessHeader();
					break;
				case 'new':
				default:
					/// @todo : we need to log here
					break;
			}
		} catch (Exception $e) {
			YellowBitcoinDbLog::log($id, 'EXCEPTION: '.$e->getMessage.'|'.$e->getLine());
		}
	}

	protected function processDefault()
	{
		/// @todo : we need to log here
	}
}

