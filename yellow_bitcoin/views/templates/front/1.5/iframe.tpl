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
 * @category    Yellow
 * @package    YellowPay
 * @author    Alexander Simonchik <support@belvg.com>
 * @copyright Copyright (c) 2010 - 2015 BelVG LLC. (http://www.belvg.com)
 * @license   https://github.com/YellowPay/yellow-prestashop/blob/master/yellow_bitcoin/Yellow-license.txt
*}

{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='yellow_bitcoin'}">{l s='Checkout' mod='yellow_bitcoin'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Pay with bitcoin' mod='yellow_bitcoin'}
{/capture}

<div class="row">
    <div class="col-xs-12 col-md-6">
		{if $yellow_iframe == 'no payment' || $yellow_iframe == 'disabled'}
            <div class="alert alert-warning">
                {l s='This payment method currently unavailable, please use another method' mod='yellow_bitcoin'}
            </div>
		{elseif $yellow_iframe == 'paid'}
            <div class="alert alert-warning">
			    {l s='Order payment received. Place Order to complete.' mod='yellow_bitcoin'}
            </div>
		{elseif $yellow_iframe == false}
            <div class="alert alert-warning">
			    {l s='Error creating invoice. Please try again or try another payment solution.' mod='yellow_bitcoin'}
            </div>
		{else}
			<iframe src="{$yellow_iframe|escape:'html'}" style="width:500px; height:255px; overflow:hidden; border:none; margin:auto; display:block;" scrolling="no" allowtransparency="true" frameborder="0"> </iframe>

            <script type="text/javascript">
                var yellow_unexpected_domain_txt = "{l s='Received message from unexpected domain: ' mod='yellow_bitcoin'}";
                var yellow_unknown_order_status_txt = "{l s='Unknown order status :' mod='yellow_bitcoin'}";
                var yellow_status_url_controller = "{$status_url_controller|escape:false}";

                {literal}
                function invoiceListener(event) {
                    if (/\.yellowpay\.co$/.test(event.origin) == false) {
                        alert(yellow_unexpected_domain_txt + event.origin);
                        return;
                    }
                    switch (event.data) {
                        case "authorizing":
                        case "paid":
                            window.location = yellow_status_url_controller;
                            break;
                        case "expired":
                        case "refund_requested":
                            //window.location = yellow_status_url_controller;
                            break;
                        default:
                            alert(yellow_unknown_order_status_txt + event.data);
                            break;
                    }
                }
                // Attach the message listener
                if (window.addEventListener) {
                    addEventListener("message", invoiceListener, false)
                } else {
                    attachEvent("onmessage", invoiceListener)
                }
                {/literal}
            </script>
		{/if}
    </div>
</div>