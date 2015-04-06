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
 * @author    Alexander Simonchik <support@belvg.com>
 * @copyright Copyright (c) 2010 - 2015 BelVG LLC. (http://www.belvg.com)
 * @license   http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 */

if (!defined('_PS_VERSION_'))
	exit;

require_once _PS_MODULE_DIR_.'yellow_bitcoin/includer.php';

class Yellow_Bitcoin extends PaymentModule
{
	public $server_root = 'https://api.yellowpay.co/v1/';
	public $api_uri_create_invoice = 'invoice/';
	public $api_uri_check_payment = 'invoice/[id]/';
	protected $hooks = array(
		'payment',
		'header',
		'orderConfirmation',
		'adminOrder',
	);
	private $os_statuses = array(
		'YP_OS_WAITING' => 'Authorizing Yellow payment',
	);
	private $os_payment_green_statuses = array(
		'YP_OS_PAYMENT_VALID' => 'Accepted Yellow payment',
	);
	private $os_payment_red_statuses = array(
		'YP_OS_PAYMENT_ERROR' => 'Error Yellow payment',
	);

	public function __construct()
	{
		$this->name = 'yellow_bitcoin';
		$this->tab = 'payments_gateways';
		$this->version = '1.6.3';
		$this->author = 'belvg';
		$this->bootstrap = true;
		$this->module_key = '';

		parent::__construct();

		$this->displayName = 'Yellow Bitcoin';
		$this->description = $this->l('Accept payments in Bitcoin from anywhere in the world; and receive
		settlements in your local currency to your bank account');

		if (!Configuration::get('YELLOWPAY_PUBLIC') || !isset($this->currencies))
			$this->warning = $this->l('your Yellow\'s public key number must be configured in order to use this module correctly');
		if (!Configuration::get('YELLOWPAY_PRIVATE'))
			$this->warning = $this->l('your Yellow\'s private key must be configured in order to use this module correctly');
	}

	public function install()
	{
		//Call PaymentModule default install function
		$install = parent::install();
		if ($install)
		{
			foreach ($this->hooks as $hook)
			{
				if (!$this->registerHook($hook))
					return false;
			}

			if (!function_exists('curl_version'))
			{
				$this->_errors[] = $this->l('Unable to install the module (CURL isn\'t installed).');
				return false;
			}

			if (!$this->installDb())
				return false;
		}

		//waiting payment status creation
		$this->createYellowPayStatus($this->os_statuses, '#3333FF', '', false, false, '', true);

		//validate green payment status creation
		$this->createYellowPayStatus($this->os_payment_green_statuses, '#32cd32', 'payment', true, true, true, true);

		//validate red payment status creation
		$this->createYellowPayStatus($this->os_payment_red_statuses, '#ec2e15', 'payment_error', false, true, false, false);

		return $install;
	}

	protected function installDb()
	{
		return Db::getInstance()->Execute('
				CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'yellow_bitcoin_ipn` (
				  `id_yellow_bitcoin_ipn` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `reference` varchar(9),
				  `invoice_id` VARCHAR( 255 ),
				  `url` varchar(400) NOT NULL,
				  `raw_body` VARCHAR( 255 ) NOT NULL,
				  `status` varchar(20) NOT NULL,
				  `address` VARCHAR( 255 ) NOT NULL,
				  `invoice_price` decimal(16,8) NOT NULL,
				  `invoice_ccy` varchar(10) NOT NULL,
				  `base_price` decimal(16,8) NOT NULL,
				  `base_ccy` varchar(10) NOT NULL,
				  `server_time` datetime NOT NULL,
				  `expiration_time` datetime NOT NULL,
				  `created_at` datetime NOT NULL,
				  `updated_at` datetime NOT NULL,
				  `hash` varchar(400) NOT NULL ,
				  `date_add` datetime NOT NULL,
				  `date_upd` datetime NOT NULL,
				PRIMARY KEY (`id_yellow_bitcoin_ipn`),
				KEY `reference` (`reference`)
			) ENGINE= '._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8
		') && Db::getInstance()->Execute('
				CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'yellow_bitcoin_log` (
				  `id_yellow_bitcoin_log` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `message` longtext CHARACTER SET utf8 NOT NULL,
				  `reference` varchar(9),
				  `date_add` datetime NOT NULL,
				  `date_upd` datetime NOT NULL,
				PRIMARY KEY (`id_yellow_bitcoin_log`),
				KEY `reference` (`reference`)
			) ENGINE= '._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8
		');
	}

	public function createYellowPayStatus($array, $color, $template, $invoice, $send_email, $paid, $logable)
	{
		foreach ($array as $key => $value)
		{
			$ow_status = Configuration::get($key);
			if ($ow_status === false)
			{
				$order_state = new OrderState();
				//$order_state->id_order_state = (int)$key;
			}
			else
				$order_state = new OrderState((int)$ow_status);

			$langs = Language::getLanguages();

			foreach ($langs as $lang)
				$order_state->name[$lang['id_lang']] = utf8_encode(html_entity_decode($value));

			$order_state->invoice = $invoice;
			$order_state->send_email = $send_email;

			if ($template != '')
				$order_state->template = $template;

			if ($paid != '')
				$order_state->paid = $paid;

			$order_state->logable = $logable;
			$order_state->color = $color;
			$order_state->save();

			Configuration::updateValue($key, (int)$order_state->id);

			copy(dirname(__FILE__).'/views/img/statuses/'.$key.'.gif', dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.gif');
		}
	}

	public function uninstall()
	{
		Configuration::deleteByName('YELLOWPAY_PUBLIC');
		Configuration::deleteByName('YELLOWPAY_PRIVATE');
		Configuration::deleteByName('YELLOWPAY_DBLOG');
		foreach ($this->hooks as $hook)
		{
			if (!$this->unregisterHook($hook))
				return false;
		}

		return parent::uninstall();
	}

	public function getContent()
	{
		$helper = $this->initForm();
		$this->postProcess();
		foreach ($this->fields_form as $field_form)
		{
			if (isset($field_form['form']['input']))
			{
				foreach ($field_form['form']['input'] as $input)
					$helper->fields_value[$input['name']] = Configuration::get(Tools::strtoupper($input['name']));
			}
		}

		$this->html .= $helper->generateForm($this->fields_form);
		return $this->html;
	}

	private function initForm()
	{
		$helper = new HelperForm();
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->identifier = $this->identifier;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->toolbar_scroll = true;
		$helper->toolbar_btn = $this->initToolbar();
		$helper->title = $this->displayName;
		$helper->submit_action = 'submitUpdate';

		$this->fields_form[0]['form'] = array(
			'tinymce' => true,
			'legend' => array('title' => $this->l('YELLOW SETUP'), 'image' => $this->_path.'logo.png'),
			'submit' => array(
				'name' => 'submitUpdate',
				'title' => $this->l('   Save   ')
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Public Key'),
					'name' => 'YELLOWPAY_PUBLIC',
					'size' => 64,
				),
				array(
					'type' => 'password',
					'label' => $this->l('Private Key'),
					'desc' => array('Private key hidden for security. To update your key, paste in the new value.'),
					'name' => 'YELLOWPAY_PRIVATE',
					'size' => 64,
				),
				array(
					'type' => Tools::version_compare(_PS_VERSION_, '1.5.9.9', '>') ? 'switch' : 'radio',
					'values' => array(
						array('label' => $this->l('Yes'), 'value' => 1, 'id' => 'dblog_on'),
						array('label' => $this->l('No'), 'value' => 0, 'id' => 'dblog_on'),
					),
					'is_bool' => true,
					'class' => 't',
					'label' => $this->l('Database Log'),
					'name' => 'YELLOWPAY_DBLOG',
					'desc' => array(
						'<a href="http://yellowpay.co" target="_blank">'.$this->l('Get Support').'</a>',
					),
				),
			),
		);

		return $helper;
	}

	private function initToolbar()
	{
		$toolbar_btn = array();
		$toolbar_btn['save'] = array('href' => '#', 'desc' => $this->l('Save'));
		return $toolbar_btn;
	}

	/**
	 * save configuration values
	 */
	protected function postProcess()
	{
		if (Tools::isSubmit('submitUpdate'))
		{
			foreach ($this->fields_form as $field_form)
			{
				foreach ($field_form['form']['input'] as $input)
				{
					$value = Tools::getValue(Tools::strtoupper($input['name']));
					if (in_array($input['name'], array('YELLOWPAY_PRIVATE')) && empty($value))
						continue;
					Configuration::updateValue(Tools::strtoupper($input['name']), $value);
				}
			}

			Tools::redirectAdmin('index.php?tab=AdminModules&conf=4&configure='.$this->name.'&token='.Tools::getAdminToken('AdminModules'.
			(int)Tab::getIdFromClassName('AdminModules').(int)$this->context->employee->id));
		}
	}

	public function hookHeader()
	{
		if (!$this->active)
			return;

		if (!in_array($this->context->controller->php_self, array('order-opc', 'order')) ||
			(isset($this->context->controller->page_name) && $this->context->controller->page_name != 'module-yellow_bitcoin-payment'))
			return;

		$this->context->controller->addCSS($this->_path.'views/css/yellow_bitcoin.css', 'all');
	}

	public function getSessionValue($key)
	{
		if (!isset($_SESSION))
			session_start();

		if (isset($_SESSION[$key]))
			return $_SESSION[$key];
	}

	public function getCheckoutUrl()
	{
		$delivery = new Address((int)$this->context->cart->id_address_delivery);
		$invoice = new Address((int)$this->context->cart->id_address_invoice);
		if (!Validate::isLoadedObject($this->context->cart) ||
			!Validate::isLoadedObject($this->context->customer) ||
			!Validate::isLoadedObject($delivery) ||
			!Validate::isLoadedObject($invoice))
			return 'no payment';

		if (!$this->active || !Configuration::get('YELLOWPAY_PUBLIC') || !Configuration::get('YELLOWPAY_PRIVATE'))
			return 'disabled';

		$invoice = $this->createYellowInvoice($this->context->cart, false);
		return $invoice['url'];
	}

	public function createYellowInvoice($quote)
	{
		$this->clearSessionData();
		$private_key = Configuration::get('YELLOWPAY_PRIVATE');
		$public_key = Configuration::get('YELLOWPAY_PUBLIC');
		$currency = new Currency($quote->id_currency);
		$ipn_url = $this->context->link->getModuleLink('yellow_bitcoin', 'default', array('action' => 'ipn', 'id' => base64_encode($quote->id)), true);
		$nonce = round(microtime(true) * 1000);
		$url = $this->server_root.$this->api_uri_create_invoice;
		$yellow_payment_data = array(
			'base_price' => $quote->getOrderTotal(), /// Set to 0.30 for testing
			'base_ccy' => $currency->iso_code, /// Set to "USD" for testing
			'callback' => $ipn_url,
			'redirect' => ''
		);
		$body = Tools::jsonEncode($yellow_payment_data);
		$message = $nonce.$url.$body;
		$signature = hash_hmac('sha256', $message, $private_key, false);
		$http_client = curl_init();
		curl_setopt($http_client, CURLOPT_HTTPHEADER, array(
			'Content-type:application/json',
			'API-Key:'.$public_key,
			'API-Nonce:'.$nonce,
			'API-Sign:'.$signature
		));
		curl_setopt($http_client, CURLOPT_USERAGENT, 'PRESTASHOP STORE');
		curl_setopt($http_client, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($http_client, CURLOPT_POST, true);
		curl_setopt($http_client, CURLOPT_POSTFIELDS, $body);
		curl_setopt($http_client, CURLOPT_URL, $url);

		try {
			$response = curl_exec($http_client);
			$http_status = curl_getinfo($http_client, CURLINFO_HTTP_CODE);
			if ($http_status == '200')
			{
				$data = Tools::jsonDecode($response, true);

				/* save the invoice in the database  */
				$ipn = new YellowBitcoinIpn();
				$ipn->reference = pSQL($quote->id);
				$ipn->invoice_id = pSQL($data['id']);
				$ipn->url = pSQL($data['url']);
				$ipn->status = pSQL($data['status']);
				$ipn->address = pSQL($data['address']);
				$ipn->invoice_price = pSQL($data['invoice_price']);
				$ipn->invoice_ccy = pSQL($data['invoice_ccy']);
				$ipn->server_time = pSQL($data['server_time']);
				$ipn->expiration_time = pSQL($data['expiration']);
				$ipn->raw_body = pSQL($body);
				$ipn->base_price = pSQL($yellow_payment_data['base_price']);
				$ipn->base_ccy = pSQL($yellow_payment_data['base_ccy']);
				$ipn->hash = pSQL($signature);
				$ipn->save();
				/* end saving invoice */
				$this->setSessionValue('invoice', $data);
				$this->setSessionValue('has_invoice', true);

				return $data;
			}
			else
			{
				YellowBitcoinDbLog::log($quote->id, 'Error code response received: '.$http_status.'; Response body: '.
					Tools::jsonEncode($response).'; id cart:'.pSQL($quote->id));

				return false;
			}
		} catch (Exception $exc) {
			YellowBitcoinDbLog::log($quote->id, 'EXCEPTION: '.$exc->getMessage().'; Response body: '.
				Tools::jsonEncode($response).'; id cart:'.pSQL($quote->id));

			return $this->l('We\'re sorry, an error has occurred while completing your request. Please refresh the page
			to try again. If the error persists, please send us an email at support@yellowpay.co');
		}
	}

	public function clearSessionData()
	{
		if (!isset($_SESSION))
			session_start();

		unset($_SESSION['yellow_invoice']);
		unset($_SESSION['yellow_has_invoice']);
		unset($_SESSION['yellow_check_invoice']);

		return true;
	}

	public function setSessionValue($key, $value)
	{
		if (!isset($_SESSION))
			session_start();

		$_SESSION[$key] = $value;
	}

	public function hookPayment()
	{
		if (!$this->active)
			return;

		if (!Configuration::get('YELLOWPAY_PUBLIC') || !Configuration::get('YELLOWPAY_PRIVATE'))
			return false;

		$this->context->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/",
		));

		return $this->display(__FILE__, (Tools::version_compare(_PS_VERSION_, '1.5.9.9', '>') ? '1.6/' : '1.5/').'payment.tpl');
	}

	public function hookOrderConfirmation($params)
	{
		return $this->hookPaymentReturn($params);
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return;

		if ($params['objOrder']->module != $this->name)
			return;

		$state = $params['objOrder']->getCurrentState();
		if (in_array($state, array(Configuration::get('YP_OS_PAYMENT_VALID'), Configuration::get('YP_OS_WAITING'), _PS_OS_PAYMENT_, _PS_OS_OUTOFSTOCK_)))
		{
			$this->context->smarty->assign(array(
				'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj']),
				'status' => 'ok',
				'id_order' => $params['objOrder']->id
			));
			if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
				$this->smarty->assign('reference', $params['objOrder']->reference);
		}
		else
			$this->context->smarty->assign('status', 'failed');

		return $this->display(__FILE__, (Tools::version_compare(_PS_VERSION_, '1.5.9.9', '>') ? '1.6/' : '1.5/').'payment_return.tpl');
	}

	public function hookAdminOrder($params)
	{
		if (!$this->active)
			return;

		$log = Db::getInstance()->executeS('
			select * from `'._DB_PREFIX_.'yellow_bitcoin_log`
			where reference = "'.(int)$params['cart']->id.'"');

		$this->context->smarty->assign('yellow_log', $log);

		return $this->display(__FILE__, (Tools::version_compare(_PS_VERSION_, '1.5.9.9', '>') ? '1.6/' : '1.5/').'admin_order.tpl');
	}

}

?>