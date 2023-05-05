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
