<?php
/**
* 2007-2022 PrestaShop
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
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * Class SellixpayWebhookModuleFrontController
 */
class SellixpayWebhookModuleFrontController extends ModuleFrontController
{
    /**
     * @return bool|void
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $this->module->log('Sellixpay Webhook received data:');
            $this->module->log($data);
            
            if (!isset($data['data']['uniqid']) || empty($data['data']['uniqid'])) {
                throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-001', 'webhook')));
            }

            $cart_id = (int)Tools::getValue('cart_id');
            $secure_key = Tools::getValue('secure_key');

            if ((Tools::isSubmit('cart_id') == false)
                || (Tools::isSubmit('secure_key') == false)
                || ($cart_id == 0)) {
                throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-002', 'webhook')));
            }

            $cart = new Cart((int) $cart_id);
            if (!Validate::isLoadedObject($cart)) {
                throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-003', 'webhook')));
            }
            
            $customer = new Customer((int) $cart->id_customer);
            if (!Validate::isLoadedObject($customer)) {
                throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-004', 'webhook')));
            }

            if ($secure_key != $customer->secure_key) {
                throw new \Exception(sprintf($this->module->l('Sellixpay: suspected fraud. Code-005', 'webhook')));
            }
            
            $sellix_order = $this->module->validSellixOrder($data['data']['uniqid']);
            $this->module->log('Concerning Sellix order:');
            $this->module->log($sellix_order);
            
            $order_id = Order::getIdByCartId((int) $cart->id);
            if ($order_id > 0) {
                $transaction_id = $sellix_order['uniqid'];
                $this->module->log('Sellixpay: Order #' . $order_id . ' (' . $transaction_id . '). Status: ' . $sellix_order['status']);
                $order = new Order($order_id);
                if ($sellix_order['status'] == 'COMPLETED') {
                    $new_history = new OrderHistory();
                    $new_history->id_order = (int) $order_id;
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $order_id, true);
                    $new_history->add();
                    
                    $order_payments = OrderPayment::getByOrderReference($order->reference);
                    if (isset($order_payments[0])) {
                        $order_payments[0]->transaction_id = $transaction_id;
                        $order_payments[0]->save();
                    }
                    
                    $comment = sprintf($this->module->l('Sellix payment successful. '));
                    $comment .= sprintf($this->module->l('Transaction ID: '). $sellix_order['uniqid']);
                    $comment .= sprintf($this->module->l(' Status: '). $sellix_order['status']);
                    
                    $this->module->updateTransaction($cart->id, $transaction_id, Tools::jsonEncode($sellix_order));
                    $this->module->updateOrderNote($cart->id, $comment);
                } elseif ($sellix_order['status'] == 'WAITING_FOR_CONFIRMATIONS') {
                    $comment = sprintf($this->module->l('Awaiting crypto currency confirmations. '));
                    $comment .= sprintf($this->module->l('Transaction ID: '). $sellix_order['uniqid']);
                    $comment .= sprintf($this->module->l(' Status: '). $sellix_order['status']);
                    
                    $new_history = new OrderHistory();
                    $new_history->id_order = (int) $order_id;
                    $new_history->changeIdOrderState(Configuration::get('SELLIXPAY_HOLD_PAYMENT_STATUS'), $order_id, true);
                    $new_history->add();
                    
                    $this->module->updateOrderNote($cart->id, $comment);
                } elseif ($sellix_order['status'] == 'PARTIAL') {
                    $comment = sprintf($this->module->l('Cryptocurrency payment only partially paid. '));
                    $comment .= sprintf($this->module->l('Transaction ID: '). $sellix_order['uniqid']);
                    $comment .= sprintf($this->module->l(' Status: '). $sellix_order['status']);

                    $new_history = new OrderHistory();
                    $new_history->id_order = (int) $order_id;
                    $new_history->changeIdOrderState(Configuration::get('SELLIXPAY_HOLD_PAYMENT_STATUS'), $order_id, true);
                    $new_history->add();
                    
                    $this->module->updateOrderNote($cart->id, $comment);
                } else {
                    $comment = sprintf($this->module->l('Order canceled. '));
                    $comment .= sprintf($this->module->l('Transaction ID: '). $sellix_order['uniqid']);
                    $comment .= sprintf($this->module->l(' Status: '). $sellix_order['status']);

                    $new_history = new OrderHistory();
                    $new_history->id_order = (int) $order_id;
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $order_id, true);
                    $new_history->add();
                    
                    $this->module->updateOrderNote($cart->id, $comment);
                }
            } else {
                $this->module->log(sprintf($this->module->l('Sellixpay: Webhook order is not created yet for cart #'.$cart->id)));
            }
        } catch (\Exception $e) {
            $this->module->log($e->getMessage(), 3);
        }
        exit;
    }
}
