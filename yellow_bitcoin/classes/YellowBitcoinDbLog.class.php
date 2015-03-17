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
 * @category    Belvg
 * @package    Belvg_YellowPay
 * @author    Alexander Simonchik <support@belvg.com>
 * @copyright Copyright (c) 2010 - 2015 BelVG LLC. (http://www.belvg.com)
 * @license   http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 */

require_once _PS_MODULE_DIR_.'yellow_bitcoin/includer.php';

class YellowBitcoinDbLog extends ObjectModel
{
    public $id_yellow_bitcoin_log;
    public $message;
    public $reference;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'yellow_bitcoin_log',
        'primary' => 'id_yellow_bitcoin_log',
        'fields' => array(
            'reference' => 			array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
            'message' => 			array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
            'date_add' => 			array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => 			array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    public static function log($reference, $message)
    {
        if (!Configuration::get('YELLOWPAY_DBLOG'))
            return false;

        $log = new self();
        $log->reference = pSQL($reference);
        $log->message = pSQL($message);

        $log->add();
    }
}