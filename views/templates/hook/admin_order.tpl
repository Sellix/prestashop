{*
* 2007-2019 PrestaShop
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
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2019 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<style>
    .sellixpay-subtable {
        border-bottom: 1px solid #bbcdd2 !important;
        margin-bottom: 10px;
    }
    .sellixpay-subtable th {
        font-weight: normal !important;
        width: 200px !important;
    }
    .sellixpay-subtable td, .sellixpay-subtable th{
        border-top: none !important;
    }
</style>
<div id="formAddPaymentPanel" class="panel card">
    <div class="card-header">
        <h3 class="card-header-title">{l s="Sellix Pay Payment Information" mod='sellixpay'}</h3>
    </div>
    <div class="table-responsive card-body">
      <table class="table sellixpay-subtable">
         <tr>
            <th><span class="title_box ">{l s="Transaction ID:" mod='sellixpay'}</th>
            <td class="value"><strong>{$transaction['transaction_id']}</strong></td>
        </tr>
        <tr>
            <th><span class="title_box ">{l s="Status:" mod='sellixpay'}</th>
            <td class="value">
                <strong>
                    {if $response['data']['status']}}
                        {$response['data']['status']}
                    {else}
                        {l s="Waiting" mod='sellixpay'}
                    {/if}
                </strong>
            </td>
        </tr>
        <tr>
            <th><span class="title_box ">{l s="Notes:" mod='sellixpay'}</th>
            <td class="value">{$transaction['order_notes']}</td>
        </tr> 
       </table>
    </div>
</div>
