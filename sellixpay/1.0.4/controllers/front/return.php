<?php
/**
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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
class SellixpayReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Tools::isSubmit('cart_id') == false) {
            throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-002-1', 'webhook')));
        } elseif (Tools::isSubmit('secure_key') == false) {
            if (Tools::isSubmit('amp;secure_key') == false) {
                throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-002-2', 'webhook')));
            }
        }

        $cart_id = (int) Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');

        $cart = new Cart((int) $cart_id);
        if (!Validate::isLoadedObject($cart)) {
            throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-003', 'webhook')));
        }

        if ($cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer
        // changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'sellixpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            echo $this->module->l('This payment method is not available.', 'pay');
            exit;
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        $order_id = Order::getIdByCartId((int) $cart->id);
        if ($order_id > 0) {
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $order_id . '&key=' . $customer->secure_key);
        } else {
            $order_status = Configuration::get('SELLIXPAY_WAITING_PAYMENT_STATUS');
            $this->module->validateOrder(
                $cart->id,
                $order_status,
                $total,
                $this->module->displayName,
                null,
                null,
                (int) $currency->id,
                false,
                $customer->secure_key
            );
            $comment = sprintf($this->module->l('Order is just created and waiting for the payment confirmation. Order status will be updated in the webhook.'));
            $this->module->updateOrderNote($cart->id, $comment);
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
        }
    }
}
