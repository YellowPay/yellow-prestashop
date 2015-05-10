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

class YellowBitcoinIpn extends ObjectModel
{
	public $id_yellowbitcoin_order;
	public $reference;
	public $invoice_id;
	public $url;
	public $raw_body;
	public $status;
	public $address;
	public $invoice_price;
	public $invoice_ccy;
	public $base_price;
	public $base_ccy;
	public $server_time;
	public $expiration_time;
	public $created_at;
	public $updated_at;
	public $hash;
	public $date_add;
	public $date_upd;

	public static $definition = array(
		'table' => 'yellow_bitcoin_ipn',
		'primary' => 'id_yellow_bitcoin_ipnr',
		'fields' => array(
			'reference'             => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'invoice_id'            => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'url'                   => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'raw_body'              => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'status'                => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'address'               => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'invoice_price'         => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'invoice_ccy'           => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'base_price'            => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'base_ccy'              => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'server_time'           => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'expiration_time'       => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'created_at'            => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'updated_at'            => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'hash'                  => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
			'date_add'              => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
			'date_upd'              => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
		),
	);

	public static function getReferenceByYellowInvoiceId($invoice_id)
	{
		return Db::getInstance()->getValue('
			SELECT `reference` FROM `'._DB_PREFIX_.'yellow_bitcoin_ipn` WHERE `invoice_id` = "'.pSQL($invoice_id).'"
		');
	}

	public static function checkInvoice($id)
	{
		$module = Module::getInstanceByName('yellow_bitcoin');
		$url = $module->server_root.str_replace('[id]', $id, $module->api_uri_check_payment);

		$nonce = round(microtime(true) * 1000);
		$message = $nonce.$url;
		$private_key = Configuration::get('YELLOWPAY_PRIVATE');
		$signature = hash_hmac('sha256', $message, $private_key, false);
		$http_client = curl_init();
		curl_setopt($http_client, CURLOPT_HTTPHEADER, array(
			'Content-type:application/json',
			'API-Key:'.Configuration::get('YELLOWPAY_PUBLIC'),
			'API-Nonce:'.$nonce,
			'API-Sign:'.$signature,
			'API-Platform: '.PHP_OS.' - PHP'.phpversion(),
			'API-Plugin: prestashop '._PS_VERSION_
		));
		curl_setopt($http_client, CURLOPT_USERAGENT, 'PRESTASHOP STORE');
		curl_setopt($http_client, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($http_client, CURLOPT_URL, $url);
		try {
			$response = curl_exec($http_client);
			$data = Tools::jsonDecode($response, true);

			$module->setSessionValue('check_invoice', $data);
			return $data;
		} catch (Exception $e) {
			YellowBitcoinDbLog::log($id, 'EXCEPTION: '.$e->getMessage.'|'.$e->getLine());
		}

		return false;
	}

	public static function checkInvoiceStatus($id)
	{
		$data = self::checkInvoice($id);
		$module = Module::getInstanceByName('yellow_bitcoin');

		if (!is_array($data))
		{
			throw new Exception('We\'re sorry, an error has occurred while completing your request.
			Please refresh the page to try again. If the error persists, please send us an email at support@yellowpay.co\n line');
		}

		$id_cart = YellowBitcoinIpn::getReferenceByYellowInvoiceId($id);
		switch ($data['status'])
		{
			case $data['status'] == 'new':
				YellowBitcoinDbLog::log($id_cart, 'Status page accessed for a new invoice');
				YellowBitcoinDbLog::log($id_cart, 'Nothing to do. Redirecting back to the payment page');
				break;
			case $data['status'] == 'paid':
				YellowBitcoinDbLog::log($id_cart, 'Payment confirmed');
				break;
			case $data['status'] == 'authorizing':
				YellowBitcoinDbLog::log($id_cart, 'Payment authorizing');
				$cart = new Cart($id_cart);
				$customer = new Customer($cart->id_customer);
				$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
				$module->validateOrder((int)$id_cart, Configuration::get('YP_OS_WAITING'), $total, $module->displayName, null,
					array('transaction_id' => $data['id'].' ('.$data['invoice_price'].' BTC)'), (int)$cart->id_currency, false,
					$customer->secure_key);
				break;
			case $data['status'] == 'refund_requested' :
				/*TODO: */
				break;
			case $data['status'] === 'failed':
			case $data['status'] === 'expired':
				/*TODO: */
				break;
			default:
				return false;
				//break;
		}

		return $data['status'];
	}
}