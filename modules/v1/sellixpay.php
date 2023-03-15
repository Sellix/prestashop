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

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

/**
 * Class SellixPay
 */
class SellixPay extends PaymentModule
{
    protected $html = '';
    protected $postErrors = array();
    public $tableName = 'sellixpay_order';
    
    protected $form_fields = array(
        'sellixpay_active',
        'sellixpay_debug',
        'sellixpay_email',
        'sellixpay_api_key',
        'sellixpay_order_prefix',
        'sellixpay_layout',
        'sellixpay_confirmations'
    );
    
    protected $form_lang_fields = array(
        'sellixpay_title',
        'sellixpay_desc',
    );
    
    protected $form_skip_fields_auto_save = array(
        'sellixpay_title[]',
        'sellixpay_desc[]',
    );
    
    /**
     * SellixPay constructor.
     */
    public function __construct()
    {
        $this->name = 'sellixpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
        $this->author = 'Sellix';
        
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Sellix Pay');
        $this->description = $this->l('Accept Cryptocurrencies, Credit Cards, PayPal and regional banking methods with Sellix Pay.');
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('actionDispatcher') ||
            !$this->registerHook('displayAdminOrder') ||
            !$this->registerHook('header')
        ) {
            return false;
        }
        $this->createTables();
        $this->createOrderStates();
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        $this->dropTable();
        $this->deleteOrderStates();

        return true;
    }
    
    public function createTables()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.$this->tableName. '(
            id int not null auto_increment,
            order_id int(11),
            transaction_id varchar(255),
            response text,
            order_notes text,
            primary key(id)
        )';
        Db::getInstance()->execute($sql);
    }
    
    public function dropTables()
    {
        $sql = 'DROP TABLE IF  EXISTS '._DB_PREFIX_.$this->tableName;
        Db::getInstance()->execute($sql);
    }
    
    public function createOrderStates()
    {
        $exist = false;
        $order_status_id = (int)Configuration::get('SELLIXPAY_WAITING_PAYMENT_STATUS');
        if ($order_status_id > 0) {
            $order_status = new OrderState($order_status_id);
            if (Validate::isLoadedObject($order_status)) {
                $exist = true;
            }
        } 
        
        if (!$exist) {
            $order_status = new OrderState();
            foreach (Language::getLanguages() as $lang) {
                $order_status->name[$lang['id_lang']] = $this->l('Waiting for Sellixpay payment confirmation');
            }
            $order_status->module_name = $this->name;
            $order_status->color = '#FF8C00';
            $order_status->send_email = false;
            if ($order_status->save()) {
                Configuration::updateValue('SELLIXPAY_WAITING_PAYMENT_STATUS', $order_status->id);
            }
        }
        
        $exist = false;
        $order_status_id = (int)Configuration::get('SELLIXPAY_HOLD_PAYMENT_STATUS');
        if ($order_status_id > 0) {
            $order_status = new OrderState($order_status_id);
            if (Validate::isLoadedObject($order_status)) {
                $exist = true;
            }
        } 
        
        if (!$exist) {
            $order_status = new OrderState();
            foreach (Language::getLanguages() as $lang) {
                $order_status->name[$lang['id_lang']] = $this->l('On Hold (Sellix Pay)');
            }
            $order_status->module_name = $this->name;
            $order_status->color = '#e3e1dc';
            $order_status->send_email = false;
            if ($order_status->save()) {
                Configuration::updateValue('SELLIXPAY_HOLD_PAYMENT_STATUS', $order_status->id);
            }
        }
    }
    
    public function deleteOrderStates()
    {
        $order_status_id = (int)Configuration::get('SELLIXPAY_WAITING_PAYMENT_STATUS');
        if ($order_status_id > 0) {
            $order_status = new OrderState($order_status_id);
            if (Validate::isLoadedObject($order_status)) {
                $order_status->delete();
            }
        }
        
        $order_status_id = (int)Configuration::get('SELLIXPAY_HOLD_PAYMENT_STATUS');
        if ($order_status_id > 0) {
            $order_status = new OrderState($order_status_id);
            if (Validate::isLoadedObject($order_status)) {
                $order_status->delete();
            }
        }
    }
    
    public function getTransactionByOrderId($order_id)
    {
        return Db::getInstance()->getRow('select * FROM ' . _DB_PREFIX_.$this->tableName . ' WHERE order_id='.(int)($order_id));
    }
    
    public function getTransactionByCode($code)
    {
        return Db::getInstance()->getRow('select * FROM ' . _DB_PREFIX_.$this->tableName . ' WHERE transaction_id="'.pSql($code).'"');
    }
    
    public function updateTransaction($order_id, $transaction_id, $response='')
    {
        $transaction = $this->getTransactionByOrderId($order_id);
        if ($transaction) {
            Db::getInstance()->query('UPDATE ' . _DB_PREFIX_.$this->tableName . ' SET response="'.pSql($response).'", transaction_id="'.pSql($transaction_id).'" WHERE order_id='.(int)($order_id));
        } else {
            Db::getInstance()->insert($this->tableName, array(
                'order_id' => (int) $order_id,
                'response' => $response,
                'transaction_id' => $transaction_id
            ));
        }
    }
    
    public function updateOrderNote($order_id, $comment)
    {
        Db::getInstance()->query('UPDATE ' . _DB_PREFIX_.$this->tableName . ' SET order_notes="'.pSql($comment).'" WHERE order_id='.(int)($order_id));
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitSellixpayModule')) == true) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        }

        $this->html .=  $this->renderForm();
        return $this->html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSellixpayModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }
    
    public function getPaymentMethods()
    {
        $list = array(
            array(
                'id' => 'bitcoin',
                'value' => 'BITCOIN',
                'label' => $this->l('Bitcoin (BTC)'),
                'img' => 'bitcoin'
            ),
            array(
                'id' => 'ethereum',
                'value' => 'EUTHEREUM',
                'label' => $this->l('Ethereum (ETH)'),
                'img' => 'ethereum'
            ),
            array(
                'id' => 'bitcoin_cash',
                'value' => 'BITCOINCASH',
                'label' => $this->l('Bitcoin Cash (BCH)'),
                'img' => 'bitcoin-cash'
            ),
            array(
                'id' => 'litecoin',
                'value' => 'LITECOIN',
                'label' => $this->l('Litecoin (LTC)'),
                'img' => 'litecoin'
            ),
            array(
                'id' => 'concordium',
                'value' => 'CONCORDIUM',
                'label' => $this->l('Concordium (CCD)'),
                'img' => 'concordium'
            ),
            array(
                'id' => 'tron',
                'value' => 'TRON',
                'label' => $this->l('Tron (TRX)'),
                'img' => 'tron'
            ),
            array(
                'id' => 'nano',
                'value' => 'NANO',
                'label' => $this->l('Nano (XNO)'),
                'img' => 'nano'
            ),
            array(
                'id' => 'monero',
                'value' => 'MONERO',
                'label' => $this->l('Monero (XMR)'),
                'img' => 'monero'
            ),
            array(
                'id' => 'ripple',
                'value' => 'RIPPLE',
                'label' => $this->l('Ripple (XRP)'),
                'img' => 'ripple'
            ),
            array(
                'id' => 'solana',
                'value' => 'SOLANA',
                'label' => $this->l('Solana (SOL)'),
                'img' => 'solana'
            ),
            array(
                'id' => 'cronos',
                'value' => 'CRONOS',
                'label' => $this->l('Cronos (CRO)'),
                'img' => 'cronos'
            ),
            array(
                'id' => 'binance_coin',
                'value' => 'BINANCE_COIN',
                'label' => $this->l('Binance Coin (BNB)'),
                'img' => 'binance'
            ),
            array(
                'id' => 'paypal',
                'value' => 'PAYPAL',
                'label' => $this->l('PayPal'),
                'img' => 'paypal'
            ),
            array(
                'id' => 'stripe',
                'value' => 'STRIPE',
                'label' => $this->l('Stripe'),
                'img' => 'stripe'
            ),
            array(
                'id' => 'usdt',
                'value' => '',
                'label' => $this->l('USDT'),
                'img' => ''
            ),
            array(
                'id' => 'usdt_erc20',
                'value' => 'USDT:ERC20',
                'label' => $this->l('USDT:ERC20'),
                'img' => 'usdt'
            ),
            array(
                'id' => 'usdt_bep20',
                'value' => 'USDT:BEP20',
                'label' => $this->l('USDT:BEP20'),
                'img' => 'usdt'
            ),
            array(
                'id' => 'usdt_trc20',
                'value' => 'USDT:TRC20',
                'label' => $this->l('USDT:TRC20'),
                'img' => 'usdt'
            ),
            array(
                'id' => 'usdc',
                'value' => '',
                'label' => $this->l('USDC'),
                'img' => ''
            ),
            array(
                'id' => 'usdc_erc20',
                'value' => 'USDC:ERC20',
                'label' => $this->l('USDC:ERC20'),
                'img' => 'usdc'
            ),
            array(
                'id' => 'usdc_bep20',
                'value' => 'USDC:BEP20',
                'label' => $this->l('USDC:BEP20'),
                'img' => 'usdc'
            ),
            array(
                'id' => 'binance_pay',
                'value' => 'BINANCE_PAY',
                'label' => $this->l('Binance Pay (BUSD)'),
                'img' => 'binance'
            ),
            array(
                'id' => 'skrill',
                'value' => 'SKRILL',
                'label' => $this->l('Skrill'),
                'img' => 'skrill'
            ),
            array(
                'id' => 'perfectmoney',
                'value' => 'PERFECTMONEY',
                'label' => $this->l('PerfectMoney'),
                'img' => 'pm'
            ),
        );
        return $list;
    }
    
    public function getPaymentMethodOptions()
    {
        $options = [];
        $list = $this->getPaymentLogos();
        foreach ($list as $item) {
            $options[$item['id']] = $item;
        }
        
        return $options;
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $fields =  array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable'),
                        'name' => 'sellixpay_active',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Debug mode'),
                        'name' => 'sellixpay_debug',
                        'is_bool' => true,
                         'values' => array(
                            array(
                                'id' => 'sellixpay_debug_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'sellixpay_debug_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'desc' => $this->l('This controls the title which the user sees during checkout.'),
                        'name' => 'sellixpay_title',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Description'),
                        'desc' => $this->l('This will display on the checkout when selected this payment option.'),
                        'name' => 'sellixpay_desc',
                        'lang' => true
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'sellixpay_email',
                        'label' => $this->l('Email'),
                        'required' => true,
                        'desc' => $this->l('Please enter your Sellix email address.')
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'sellixpay_api_key',
                        'label' => $this->l('API Key'),
                        'required' => true,
                        'desc' => $this->l('Please enter your Sellix API Key.')
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Order ID Prefix'),
                        'name'     => 'sellixpay_order_prefix',
                        'desc' => $this->l('The prefix before the order number. For example, a prefix of "Order #" and a ID of "10" will result in "Order #10"'),
                    ),
                    array(
                        'type'     => 'switch',
                        'label'    => $this->l('Radio Box Layout'),
                        'name'     => 'sellixpay_layout',
                        'desc' => $this->l('Default layout is dropdown'),
                        'is_bool' => true,
                         'values' => array(
                            array(
                                'id' => 'sellixpay_layout_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'sellixpay_layout_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Number of confirmations for crypto currencies'),
                        'name'     => 'sellixpay_confirmations',
                        'desc' => $this->l('The default of 1 is advised for both speed and security'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
        
        $methods = $this->getPaymentMethods();
        
        foreach ($methods as $method) {
            $desc = '';
            if ($method['id'] == 'usdt') {
                $desc = $this->l('You have to select one from below USDT:ERC20 , USDT:BEP20 OR USDT:TRC20 if enable usdt');
            }
            if ($method['id'] == 'usdc') {
                $desc = $this->l('You have to select one from below USDC:ERC20 OR USDC:BEP20 if enable usdc');
            }
            $field = array(
                'type' => 'switch',
                'label' => $method['label'],
                'name' => 'sellix_'.$method['id'],
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => $method['id'].'_on',
                        'value' => true,
                        'label' => $this->l('Enabled')
                    ),
                    array(
                        'id' => $method['id'].'_off',
                        'value' => false,
                        'label' => $this->l('Disabled')
                    )
                ),
                'desc' => $desc
            );
            $fields['form']['input'][] = $field;
        }
        
        return $fields;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        foreach ($this->form_fields as $key => $val) {
            $field_val = Configuration::get($val);
            $params1[$val] = Tools::getValue($val, $field_val);
        }
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $id_lang = $language['id_lang'];
            foreach ($this->form_lang_fields as $key => $val) {
                $field_key = $val.'_'.$id_lang;
                $field_val = Configuration::get($field_key);
                $params2[$val][$id_lang] = Tools::getValue($field_key, $field_val);
            }
        }
        
        $params3 = array();
        $methods = $this->getPaymentMethods();
        
        foreach ($methods as $method) {
            $key = 'sellix_'.$method['id'];
            $value = Configuration::get($key);
            $params2[$key] = Tools::getValue($key, $value);
        }
        
        $params = array_merge($params1, $params2, $params3);
        
        return $params;
    }
    
    protected function postValidation()
    {
        $sellixpay_api_key = Tools::getValue('sellixpay_api_key');
        $sellixpay_api_key = trim($sellixpay_api_key);
        $sellixpay_api_key = strip_tags($sellixpay_api_key);
        
        $sellixpay_email = Tools::getValue('sellixpay_email');
        $sellixpay_email = trim($sellixpay_email);
        $sellixpay_email = strip_tags($sellixpay_email);
        
        if (empty($sellixpay_api_key)) {
            $this->postErrors[] = $this->l('Please provide Sellix API Key');
        }
        if (empty($sellixpay_email)) {
            $this->postErrors[] = $this->l('Please provide Sellix Email');
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $val) {
            if (!in_array($val, $this->form_skip_fields_auto_save)) {
                $field_val = Tools::getValue($val);
                Configuration::updateValue($val,  $field_val);
            }
        }
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $id_lang = $language['id_lang'];
            foreach ($this->form_lang_fields as $key => $val) {
                $field_key = $val.'_'.$id_lang;
                $field_val = Tools::getValue($field_key);
                Configuration::updateValue($field_key,  $field_val);
            }
        }
        
        $this->html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        
        if (!$this->isAvailable()) {
            return;
        }
        
        $methods = $this->getPaymentMethods();
        $payment_methods = [];
        
        foreach ($methods as $method) {
            $key = 'sellix_'.$method['id'];
            $value = Configuration::get($key);
            $method['active'] = 0;
            if ($value) {
                $method['active'] = 1;
                $payment_methods[] = $method;
            }
        }
        
        $data = [
            'sellixpay_layout' => Configuration::get('sellixpay_layout'),
            'payment_methods' => $payment_methods,
            'sellixpay_img_path' => _MODULE_DIR_.$this->name.'/views/img/',
        ];

        $this->context->smarty->assign($data);
        
        $id_lang = $this->context->language->id;

        $title = Configuration::get(
            'sellixpay_title_'.$id_lang,
            null, 
            null, 
            null, 
            $this->l('Sellix Pay')
        );
        
        $desc = Configuration::get('sellixpay_desc_'.$id_lang);

        $option = new PaymentOption();
        $option->setCallToActionText($title)
            ->setAction($this->context->link->getModuleLink($this->name, 'pay', array(), true));
        
        if (!empty($desc)) {
            $this->context->smarty->assign('sellixpay_desc', $desc);
            $desc = $this->fetch('module:sellixpay/views/templates/hook/payment_infos.tpl');
            $option->setAdditionalInformation($desc);
        }
        
        $payment_image = _MODULE_DIR_.$this->name.'/views/img/logo.png';
        $option->setLogo($payment_image);
        $option->setModuleName($this->name);
        
        $option->setForm($this->context->smarty->fetch('module:sellixpay/views/templates/hook/payment_form.tpl'));

        return [
            $option
        ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
    
    public function isAvailable()
    {
        $sellixpay_api_key = Configuration::get('sellixpay_api_key');
        $sellixpay_active = Configuration::get('sellixpay_active');
        if ($sellixpay_active && !(empty($sellixpay_api_key))){
            return true;
        }
        return false;
    }
    
    public function hookActionDispatcher($params)
    {
        if ($params['controller_class'] == 'OrderController') {
            if (isset($_GET['sellixpay_message']) && !empty($_GET['sellixpay_message'])) {
                $sellixpay_message = Tools::getValue('sellixpay_message');
                $sellixpay_message = base64_decode($sellixpay_message);
                $this->context->controller->errors[] = $sellixpay_message;
            }
        }
    }
    
    public function hookDisplayAdminOrder($params)
    {
        $id_order = Tools::getValue('id_order');
        if ($id_order > 0) {
            $order = new Order($id_order);
            if ($order && $order->id > 0) {
                $id_cart = $order->id_cart;
                $transaction = $this->getTransactionByOrderId($id_cart);
                if ($transaction) {
                    $this->context->smarty->assign('transaction', $transaction);
                    $response = $transaction['response'];
                    if ($response) {
                        $response = Tools::jsonDecode($response, true);
                        } else {
                        $response = array();
                    }
                    $this->context->smarty->assign('response', $response);
                    return $this->display(__FILE__, 'admin_order.tpl');
                }
            }
        }
    }

    public function log($content, $type=1)
    {
        $debug = (int)Configuration::get('sellixpay_debug');
        if ($debug) {
            PrestaShopLogger::addLog(
                date("Y-m-d H:i:s").': '.print_r($content, true),
                $type,
                null,
                'SellixPay'
            );
        }
    }

    public function getApiUrl()
    {
        return 'https://dev.sellix.io';
    }
    
    function sellixPostAuthenticatedJsonRequest($route, $body = false, $extra_headers = false, $method="POST")
    {
        $server = $this->getApiUrl();

        $url = $server . $route;

        $uaString = 'Sellix PrestaShop (PHP ' . PHP_VERSION . ')';
        $apiKey = trim(Configuration::get('sellixpay_api_key'));
        $headers = array(
            'Content-Type: application/json',
            'User-Agent: '.$uaString,
            'Authorization: Bearer ' . $apiKey
        );

        if($extra_headers && is_array($extra_headers)) {
            $headers = array_merge($headers, $extra_headers);
        }
        
        $this->log($url);
        $this->log($headers);
        $this->log($body);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if (! empty( $body )) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $body ));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response['body'] = curl_exec($ch);
        $response['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log($response['body']);
        $response['error'] = curl_error($ch);
        
        return $response;
    }
    
       public function generateSellixPayment($payment_gateway)
    {
        if (!empty($payment_gateway)) {
            $cart = $this->context->cart;
            $currency = $this->context->currency;
            $customer = new Customer($cart->id_customer);
            $total = $cart->getOrderTotal(true, Cart::BOTH);
            
            $params = [
                'title' => Configuration::get('sellixpay_order_prefix') . $cart->id,
                'currency' => $currency->iso_code,
                'return_url' => $this->context->link->getModuleLink($this->name, 'return', array(), true),
                'webhook' => $this->context->link->getModuleLink($this->name, 'webhook', array('cart_id' => $cart->id, 'secure_key' => $customer->secure_key), true),
                'email' => $customer->email,
                'value' => $total,
                'gateway' => $payment_gateway,
                'confirmations' => Configuration::get('sellixpay_confirmations')
            ];

            $route = "/v1/payments";
            $response = $this->sellixPostAuthenticatedJsonRequest($route, $params);
            
            if (isset($response['body']) && !empty($response['body'])) {
                $responseDecode = json_decode($response['body'], true);
                if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
                    throw new \Exception ($this->l('Payment error: '.$responseDecode['status'].'-'.$responseDecode['error']));
                }
                
                $transaction_id = '';
                if (isset($responseDecode['data']['uniqid'])) {
                    $transaction_id = $responseDecode['data']['uniqid'];
                }
                $this->updateTransaction($cart->id, $transaction_id, $response['body']);
                
                return $responseDecode['data']['url'];
            } else {
                throw new \Exception ($this->l('Payment error: '.$response['error']));
            }
        } else{
            throw new \Exception($this->l('Payment Gateway Error: Sellix Before API Error: Payment Method Not Selected'));
        }
    }
    
    public function validSellixOrder($order_uniqid)
    {
        $route = "/v1/orders/" . $order_uniqid;
        $response = $this->sellixPostAuthenticatedJsonRequest($route,'','','GET');

        $this->log($this->l('Order validation returned:'.$response['body']));
        
        if (isset($response['body']) && !empty($response['body'])) {
            $responseDecode = json_decode($response['body'], true);
            if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
                throw new \Exception ($this->l('Payment error: '.$responseDecode['status'].'-'.$responseDecode['error']));
            }

            return $responseDecode['data']['order'];
        } else {
            throw new \Exception ($this->l('Unable to verify order via Sellix Pay API'));
        }
    }
}
