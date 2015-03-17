{*
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
*}
{if $status == 'ok'}
    <div class="alert alert-success">
        {l s='YOUR ORDER HAS BEEN RECEIVED.' mod='yellow_bitcoin'}
        <br /><br />{l s='THANK YOU FOR YOUR PURCHASE!' mod='yellow_bitcoin'}
        {if !isset($reference)}
            <br /><br />- {l s='Your order number is #%d.' sprintf=$id_order|intval mod='yellow_bitcoin'}
        {else}
            <br /><br />- {l s='Your order reference is %s.' sprintf=$reference|escape:'html' mod='yellow_bitcoin'}
        {/if}
        <br /><br />- {l s='You will receive an order confirmation email with details of your order and a link to track its progress.' mod='yellow_bitcoin'}
        <br /><br /><strong>{l s='Your order will be sent as soon as your payment is processed.' mod='yellow_bitcoin'}</strong>
        {l s='For any questions or for further information, please contact our' mod='yellow_bitcoin'} <a href="{$link->getPageLink('contact')}">{l s='customer support' mod='yellow_bitcoin'}</a>.
    </div>
{else}
    <div class="alert alert-warning">
        {l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='yellow_bitcoin'}
        <a href="{$link->getPageLink('contact')|escape:false}">{l s='customer support' mod='yellow_bitcoin'}</a>.
    </div>
{/if}
