{*
* 2007-2023 PrestaShop
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
*  @copyright  2007-2023 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="sellix-payment-gateway-form">
    <form action="{$link->getModuleLink('sellixpay', 'pay', [], true)|escape:'htmlall':'UTF-8'}" method="post" id="sellixpay-form" name="sellixpay-form" class="std box">
        <div class="payment_form_container">
            <div class="col-sm-12">
                
                {if $sellixpay_layout}
                    <div class="form-group row">
                        <label id="sellixpay_payment_method_label" for="sellixpay_payment_method" class="col-md-12 form-control-label">
                            {l s='Select Sellix Payment Method' mod='sellixpay'}
                        </label>
                        <div class="col-md-12">
                        {foreach $payment_methods as $payment_method}
                            {if $payment_method.active && $payment_method.img}
                                <div class="payment-labels-container">
                                    <div class="payment-labels {$payment_method.id|escape:'htmlall':'UTF-8'}">
                                        <label class="{$payment_method.id|escape:'htmlall':'UTF-8'}">
                                            <input type="radio" name="sellixpay_gateway" value="{$payment_method.value|escape:'htmlall':'UTF-8'}" />
                                            <img src="{$sellixpay_img_path|escape:'htmlall':'UTF-8'}{$payment_method.img|escape:'htmlall':'UTF-8'}.png" alt="{$payment_method.label|escape:'htmlall':'UTF-8'}" style="border-radius: 0px;" width="20" height="20"> {$payment_method.label|escape:'htmlall':'UTF-8'} 
                                        </label>
                                    </div>
                                </div>
                            {/if}
                        {/foreach}
                        </div>    
                    </div>
                {else}
                <div class="form-group row">
                    <label id="sellixpay_payment_method_label" for="sellixpay_payment_method" class="col-md-12 form-control-label">
                        {l s='Select Sellix Payment Method' mod='sellixpay'}
                    </label>
                    <div class="col-md-12">
                        <select tabindex="1" id="sellixpay_payment_method" class="form-control" name="sellixpay_payment_method">
                            {foreach $payment_methods as $payment_method}
                                {if $payment_method.active && $payment_method.img}
                                <option value="{$payment_method.value|escape:'htmlall':'UTF-8'}">{$payment_method.label|escape:'htmlall':'UTF-8'}</option>
                                {/if}
                            {/foreach}
                        </select>
                    </div>
                </div>
                {/if}
            </div>
        </div>
    </form>
    <div class="clearfix"></div>
    <p></p>
</div>
