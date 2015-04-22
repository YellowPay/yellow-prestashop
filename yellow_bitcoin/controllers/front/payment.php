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

class yellow_bitcoinpaymentModuleFrontController extends ModuleFrontController {

	public function postProcess()
	{
		if ($this->context->cart->id_customer == 0 || $this->context->cart->id_address_delivery == 0 ||
		$this->context->cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');

		/* Check that this payment option is still available in case the customer changed
		 * his address just before the end of the checkout process */
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
		{
			if ($module['name'] == 'yellow_bitcoin')
			{
				$authorized = true;
				break;
			}
		}

		if (!$authorized)
			die(Tools::displayError('This payment method is not available.'));

		$customer = new Customer($this->context->cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
	}

	public function initContent()
	{
		$this->display_column_left = false;
		$this->display_column_right = false;
		parent::initContent();

		$this->context->smarty->assign(array(
			'yellow_iframe' => $this->module->getCheckoutUrl(),
			'status_url_controller' => $this->context->link->getModuleLink('yellow_bitcoin', 'default', array('action' => 'status'), true)
		));
		$this->setTemplate((Tools::version_compare(_PS_VERSION_, '1.5.9.9', '>') ? '1.6/':'1.5/').'iframe.tpl');
	}

}