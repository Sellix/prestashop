<div class="sellix-payment-gateway-form">
    <form action="{$link->getModuleLink('sellixpay', 'pay', [], true)|escape:'htmlall':'UTF-8'}" method="post" id="sellixpay-form" name="sellixpay-form" class="std box">
        <div class="payment_form_container">
            <div class="col-sm-12">
                <div class="form-group row">
                    <label id="sellixpay_gateway_label" for="sellixpay_gateway" class="col-md-12 form-control-label">
                        {l s='Select Sellix Payment Method' mod='sellixpay'}
                    </label>
                    <div class="col-md-12">
                        <select tabindex="1" id="sellixpay_gateway" class="form-control" name="sellixpay_gateway">
                            {foreach $payment_gateways as $payment_gateway}
                                <option value="{$payment_gateway.value|escape:'htmlall':'UTF-8'}">
                                    <img src="{$payment_gateway.img|escape:'htmlall':'UTF-8'}" alt="{$payment_gateway.label|escape:'htmlall':'UTF-8'}" style="border-radius: 0px;" width="20" height="20">
                                    {$payment_gateway.label|escape:'htmlall':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="clearfix"></div>
    <p></p>
</div>
