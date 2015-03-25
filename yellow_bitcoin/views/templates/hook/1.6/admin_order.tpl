{**
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
 
{if count($yellow_log)}
<div class="col-lg-12">
	<div class="panel">
		<div class="panel-heading">
			{l s='Yellow\'s log' mod='yellow_bitcoin'}
			<span class="badge">#{count($yellow_log)|escape:false}</span>
			<div class="panel-heading-action">
				<a class="btn btn-default log_toggle" href="#">
					<i class="icon-file-text"></i> 
					<span id="log_toggler">{l s='Show' mod='yellow_bitcoin'}</span>
				</a>
			</div>
		</div>
		<div class="table-responsive" id="yellow_log_wrapper">
			<table class="table history-status row-margin-bottom">
				<thead>
					<tr>
						<th>
							<span class="title_box ">{l s='#' mod='yellow_bitcoin'}</span>
						</th>
						<th>
							<span class="title_box ">{l s='Message' mod='yellow_bitcoin'}</span>
						</th>
						<th>
							<span class="title_box ">{l s='Date add' mod='yellow_bitcoin'}</span>
						</th>
					</tr>
				<thead>
				<tbody>
					{foreach $yellow_log as $y_log}
					<tr>
						<td>{$y_log.id_yellow_bitcoin_log|intval}</td>
						<td>{$y_log.message|escape:false}</td>
						<td>{$y_log.date_add|escape:false}</td>
					</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
	</div>
</div>

{literal}
<script type="text/javascript">
	var y_show_txt = '{/literal}{l s='Show' mod='yellow_bitcoin'}{literal}';
	var y_hide_txt = '{/literal}{l s='Hide' mod='yellow_bitcoin'}{literal}';
	$(document).ready(function() {
		$('#yellow_log_wrapper').hide();
	
		$('#log_toggler').click(function() {
			$('#yellow_log_wrapper').toggle('show', function(){
				$(this).is(':visible') ? $('#log_toggler').text(y_hide_txt) : $('#log_toggler').text(y_show_txt);;
			});
		});
	});
</script>
{/literal}
{/if}