<?php
/**
 * Sellix
 *
 * @author    Sellix.io SA <info@sellix.io>
 * @copyright Sellix
 * @license   Sellix Private License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class SellixPay extends PaymentModule
{
    public $tableName = 'sellixpay_orders';

    protected $postErrors = [];
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'sellixpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.4';
        $this->author = 'Sellix';
        $this->need_instance = 0;
        $this->bootstrap = false;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Sellix Pay');
        $this->description = $this->l('Accept Cryptocurrencies, Credit Cards, PayPal and regional banking methods with Sellix Pay. 
          Sellix supports over 10+ different cryptocurrency payment methods. 
          This includes the most popular ones such as Bitcoin, Ethereum, Bitcoin Cash, Litecoin, Solana, and more. 
          We also support 5+ different fiat payment methods such as PayPal, Stripe, CashApp, and more.');
        $this->confirmUninstall = $this->l('Are you sure want to delete Sellix Pay?');
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('SELLIXPAY_LIVE_MODE', false);

        include dirname(__FILE__) . '/sql/install.php';

        $install_result = parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('actionDispatcher') &&
            $this->registerHook('displayAdminOrder');

        if (!$install_result) {
          return false;
        }

        $this->createOrderStates();
        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('SELLIXPAY_LIVE_MODE');

        include dirname(__FILE__) . '/sql/uninstall.php';

        $uninstall_result = parent::uninstall();
        if (!$uninstall_result) {
          return false;
        }

        $this->deleteOrderStates();
        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /* If values have been submitted in the form, process. */
        if (((bool) Tools::isSubmit('submitSellixPayModule')) === true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
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
        $helper->submit_action = 'submitSellixPayModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
          . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'SELLIXPAY_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => [
                            [
                                'id' => 'sellixpay_active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'sellixpay_active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Debug mode'),
                        'name' => 'SELLIXPAY_DEBUG_MODE',
                        'is_bool' => true,
                        'values' => [
                            [
                              'id' => 'sellixpay_debug_on',
                              'value' => true,
                              'label' => $this->l('Enabled'),
                            ],
                            [
                              'id' => 'sellixpay_debug_off',
                              'value' => false,
                              'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_API_KEY',
                        'label' => $this->l('API Key'),
                        'desc' => $this->l('Please enter your Sellix API Key.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => true,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_CASHAPP_CASHTAG',
                        'label' => $this->l('Cashapp cashtag'),
                        'desc' => $this->l('Please enter your cashapp cashtag.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_BITCOIN',
                        'label' => $this->l('Bitcoin address'),
                        'desc' => $this->l('Please enter your bitcoin address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_BITCOIN_LN_EMAIL',
                        'label' => $this->l('Bitcoin ln email'),
                        'desc' => $this->l('Please enter your bitcoin ln email.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_BITCOIN_LN_URL',
                        'label' => $this->l('Bitcoin ln url'),
                        'desc' => $this->l('Please enter your bitcoin ln url.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_BITCOINCASH',
                        'label' => $this->l('Bitcoincash address'),
                        'desc' => $this->l('Please enter your bitcoincash address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_ETHEREUM',
                        'label' => $this->l('Ethereum address'),
                        'desc' => $this->l('Please enter your ethereum address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_POLYGON',
                        'label' => $this->l('Polygon address'),
                        'desc' => $this->l('Please enter your polygon address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_LITECOIN',
                        'label' => $this->l('Polygon address'),
                        'desc' => $this->l('Please enter your polygon address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_MONERO',
                        'label' => $this->l('Monero address'),
                        'desc' => $this->l('Please enter your monero address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_NANO',
                        'label' => $this->l('Nano address'),
                        'desc' => $this->l('Please enter your nano address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_SOLANA',
                        'label' => $this->l('Solana address'),
                        'desc' => $this->l('Please enter your solana address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_CONCORDIUM',
                        'label' => $this->l('Concordium address'),
                        'desc' => $this->l('Please enter your concordium address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_CRONOS',
                        'label' => $this->l('Cronos address'),
                        'desc' => $this->l('Please enter your cronos address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_TRON',
                        'label' => $this->l('Tron address'),
                        'desc' => $this->l('Please enter your tron address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_RIPPLE',
                        'label' => $this->l('Ripple address'),
                        'desc' => $this->l('Please enter your ripple address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_RIPPLE_DESTINATION_TAG',
                        'label' => $this->l('Ripple destination tag'),
                        'desc' => $this->l('Please enter your ripple destination tag.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_ADDRESS_BINANCE_COIN',
                        'label' => $this->l('Binance coin address'),
                        'desc' => $this->l('Please enter your binance coin address.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                    [
                        //'col' => 3,
                        'type' => 'text',
                        'name' => 'SELLIXPAY_BINANCE_ID',
                        'label' => $this->l('Binance id'),
                        'desc' => $this->l('Please enter your binance id.'),
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'required' => false,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return [
            'SELLIXPAY_LIVE_MODE' => Configuration::get('SELLIXPAY_LIVE_MODE', false),
            'SELLIXPAY_DEBUG_MODE' => Configuration::get('SELLIXPAY_DEBUG_MODE', false),
            'SELLIXPAY_API_KEY' => Configuration::get('SELLIXPAY_API_KEY', null),
            'SELLIXPAY_CASHAPP_CASHTAG' => Configuration::get('SELLIXPAY_CASHAPP_CASHTAG', null),
            'SELLIXPAY_ADDRESS_BITCOIN' => Configuration::get('SELLIXPAY_ADDRESS_BITCOIN', null),
            'SELLIXPAY_BITCOIN_LN_EMAIL' => Configuration::get('SELLIXPAY_BITCOIN_LN_EMAIL', null),
            'SELLIXPAY_BITCOIN_LN_URL' => Configuration::get('SELLIXPAY_BITCOIN_LN_URL', null),
            'SELLIXPAY_ADDRESS_BITCOINCASH' => Configuration::get('SELLIXPAY_ADDRESS_BITCOINCASH', null),
            'SELLIXPAY_ADDRESS_ETHEREUM' => Configuration::get('SELLIXPAY_ADDRESS_ETHEREUM', null),
            'SELLIXPAY_ADDRESS_POLYGON' => Configuration::get('SELLIXPAY_ADDRESS_POLYGON', null),
            'SELLIXPAY_ADDRESS_LITECOIN' => Configuration::get('SELLIXPAY_ADDRESS_LITECOIN', null),
            'SELLIXPAY_ADDRESS_MONERO' => Configuration::get('SELLIXPAY_ADDRESS_MONERO', null),
            'SELLIXPAY_ADDRESS_NANO' => Configuration::get('SELLIXPAY_ADDRESS_NANO', null),
            'SELLIXPAY_ADDRESS_SOLANA' => Configuration::get('SELLIXPAY_ADDRESS_SOLANA', null),
            'SELLIXPAY_ADDRESS_CONCORDIUM' => Configuration::get('SELLIXPAY_ADDRESS_CONCORDIUM', null),
            'SELLIXPAY_ADDRESS_CRONOS' => Configuration::get('SELLIXPAY_ADDRESS_CRONOS', null),
            'SELLIXPAY_ADDRESS_TRON' => Configuration::get('SELLIXPAY_ADDRESS_TRON', null),
            'SELLIXPAY_ADDRESS_RIPPLE' => Configuration::get('SELLIXPAY_ADDRESS_RIPPLE', null),
            'SELLIXPAY_RIPPLE_DESTINATION_TAG' => Configuration::get('SELLIXPAY_RIPPLE_DESTINATION_TAG', null),
            'SELLIXPAY_ADDRESS_BINANCE_COIN' => Configuration::get('SELLIXPAY_ADDRESS_BINANCE_COIN', null),
            'SELLIXPAY_BINANCE_ID' => Configuration::get('SELLIXPAY_BINANCE_ID', null),
        ];
    }

    /**
     * Validate form data.
     */
    protected function postValidation()
    {
        $sellixpay_api_key = strip_tags(trim(Tools::getValue('SELLIXPAY_API_KEY')));

        if (empty($sellixpay_api_key)) {
          $this->postErrors[] = $this->l('Please provide Sellix API Key');
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
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

        if (!$this->isAvailable()) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_gateways = [
            [
              'id' => 'bitcoin',
              'value' => 'BITCOIN',
              'label' => $this->l('Bitcoin'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/bitcoin.png'
            ],
            [
              'id' => 'bitcoin_ln',
              'value' => 'BITCOINLN',
              'label' => $this->l('Bitcoin LN'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/bitcoin_ln.png'
            ],
            [
              'id' => 'bitcoin_cash',
              'value' => 'BITCOINCASH',
              'label' => $this->l('Bitcoin cash'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/bitcoin_cash.png'
            ],
            [
              'id' => 'ethereum',
              'value' => 'ETHEREUM',
              'label' => $this->l('Ethereum'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/ethereum.png'
            ],
            [
              'id' => 'polygon',
              'value' => 'POLYGON',
              'label' => $this->l('Polygon'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/polygon.png'
            ],
            [
              'id' => 'litecoin',
              'value' => 'LITECOIN',
              'label' => $this->l('Litecoin'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/litecoin.png'
            ],
            [
              'id' => 'monero',
              'value' => 'MONERO',
              'label' => $this->l('Monero'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/monero.png'
            ],
            [
              'id' => 'nano',
              'value' => 'NANO',
              'label' => $this->l('Nano'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/nano.png'
            ],
            [
              'id' => 'solana',
              'value' => 'SOLANA',
              'label' => $this->l('Solana'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/solana.png'
            ],
            [
              'id' => 'concordium',
              'value' => 'CONCORDIUM',
              'label' => $this->l('Concordium'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/concordium.png'
            ],
            [
              'id' => 'cronos',
              'value' => 'CRONOS',
              'label' => $this->l('Cronos'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/cronos.png'
            ],
            [
              'id' => 'tron',
              'value' => 'TRON',
              'label' => $this->l('Tron'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/tron.png'
            ],
            [
              'id' => 'ripple',
              'value' => 'RIPPLE',
              'label' => $this->l('Ripple'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/ripple.png'
            ],
            [
              'id' => 'binance_pay',
              'value' => 'BINANCE_PAY',
              'label' => $this->l('Binance pay'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/binance_pay.png'
            ],
            [
              'id' => 'binance_coin',
              'value' => 'BINANCECOIN',
              'label' => $this->l('Binance coin'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/binance_coin.png'
            ],
            [
              'id' => 'stripe',
              'value' => 'STRIPE',
              'label' => $this->l('Stripe'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/stripe.png'
            ],
            [
              'id' => 'paypal',
              'value' => 'PAYPAL',
              'label' => $this->l('PayPal'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/paypal.png'
            ],
            [
              'id' => 'skrill',
              'value' => 'SKRILL',
              'label' => $this->l('Skrill'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/skrill.png'
            ],
            [
              'id' => 'perfectmoney',
              'value' => 'PERFECTMONEY',
              'label' => $this->l('PerfectMoney'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/perfectmoney.png'
            ],
            [
              'id' => 'cashapp',
              'value' => 'CASHAPP',
              'label' => $this->l('Cashapp'),
              'img' => _MODULE_DIR_.$this->name.'/views/img/cashapp.png'
            ],
        ];

        $this->context->smarty->assign([
          'payment_gateways' => $payment_gateways,
        ]);

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option
            ->setCallToActionText($this->l('Sellix Pay'))
            ->setAction($this->context->link->getModuleLink($this->name, 'pay', [], true));
        $option->setLogo(_MODULE_DIR_ . $this->name . '/views/img/sellixpay.png');
        $option->setModuleName($this->name);
        $option->setForm($this->context->smarty->fetch('module:sellixpay/views/templates/hook/payment_form.tpl'));

        return [
            $option,
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
        $sellixpay_api_key = Configuration::get('SELLIXPAY_API_KEY');
        if (empty($sellixpay_api_key)) {
          return false;
        }
        return true;
    }

    public function hookActionDispatcher($params)
    {
        if ($params['controller_class'] == 'OrderController') {
            if (Tools::getIsset('sellixpay_message')) {
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
                        $response = [];
                    }
                    $this->context->smarty->assign('response', $response);
                    return $this->display(__FILE__, 'admin_order.tpl');
                }
            }
        }
    }

    public function hookDisplayPayment()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentReturn()
    {
        /* Place your code here. */
    }

    public function getApiUrl()
    {
        return 'https://dev.sellix.io';
    }

    public function sellixPostAuthenticatedJsonRequest($route, $body = false, $extra_headers = false, $method = 'POST')
    {
        $server = $this->getApiUrl();

        $url = $server . $route;

        $uaString = 'Sellix PrestaShop (PHP ' . PHP_VERSION . ')';
        $apiKey = trim(Configuration::get('sellixpay_api_key'));
        $headers = [
          'Content-Type: application/json',
          'User-Agent: ' . $uaString,
          'Authorization: Bearer ' . $apiKey,
        ];

        if ($extra_headers && is_array($extra_headers)) {
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

        if (!empty($body)) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
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
        if (empty($payment_gateway)) {
            throw new \Exception($this->l('Could not process the request because payment gateway was not specified!'));
        }

        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $customer = new Customer($cart->id_customer);
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        $params = [
            'title' => $cart->id,
            'currency' => $currency->iso_code,
            'return_url' => $this->context->link->getModuleLink($this->name, 'return', ['cart_id' => $cart->id, 'secure_key' => $customer->secure_key], true),
            'webhook' => $this->context->link->getModuleLink($this->name, 'webhook', ['cart_id' => $cart->id, 'secure_key' => $customer->secure_key], true),
            'email' => $customer->email,
            'value' => $total,
            'gateway' => $payment_gateway,
            'gateway_config' => $this->getSellixPaymentGatewayCustomConfig($payment_gateway)
        ];

        $route = '/1.0.0/payments';
        $response = $this->sellixPostAuthenticatedJsonRequest($route, $params);

        if (isset($response['body']) && !empty($response['body'])) {
          $responseDecode = json_decode($response['body'], true);
          if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
            throw new \Exception($this->l('Payment error: ' . $responseDecode['status'] . '-' . $responseDecode['error']));
          }

          $transaction_id = '';
          if (isset($responseDecode['data']['uniqid'])) {
            $transaction_id = $responseDecode['data']['uniqid'];
          }
          $this->updateTransaction($cart->id, $transaction_id, $response['body']);

          return $responseDecode['data']['url'];
        } else {
          throw new \Exception($this->l('Payment error: ' . $response['error']));
        }
    }

    public function getSellixPaymentGatewayCustomConfig($payment_gateway) {
        $config = [];
        if ($payment_gateway == "BITCOIN") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_BITCOIN');
        } elseif ($payment_gateway == "BITCOINLN") {
            $config["email"] = Configuration::get('SELLIXPAY_BITCOIN_LN_EMAIL');
            $config["url"] = Configuration::get('SELLIXPAY_BITCOIN_LN_URL');
        } elseif ($payment_gateway == "BITCOINCASH") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_BITCOINCASH');
        } elseif ($payment_gateway == "ETHEREUM") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_ETHEREUM');
        } elseif ($payment_gateway == "POLYGON") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_POLYGON');
        } elseif ($payment_gateway == "LITECOIN") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_LITECOIN');
        } elseif ($payment_gateway == "MONERO") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_MONERO');
        } elseif ($payment_gateway == "NANO") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_NANO');
        } elseif ($payment_gateway == "SOLANA") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_SOLANA');
        } elseif ($payment_gateway == "CONCORDIUM") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_CONCORDIUM');
        } elseif ($payment_gateway == "CRONOS") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_CRONOS');
        } elseif ($payment_gateway == "TRON") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_TRON');
        } elseif ($payment_gateway == "RIPPLE") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_RIPPLE');
            $config["tag"] = Configuration::get('SELLIXPAY_RIPPLE_DESTINATION_TAG');
        } elseif ($payment_gateway == "BINANCE_PAY") {
            $config["binance_id"] = Configuration::get('SELLIXPAY_BINANCE_ID');
        } elseif ($payment_gateway == "BINANCECOIN") {
            $config["address"] = Configuration::get('SELLIXPAY_ADDRESS_BINANCE_COIN');
        }
        return $config;
    }

    public function validSellixOrder($order_uniqid)
    {
        $route = '/1.0.0/orders/' . $order_uniqid;
        $response = $this->sellixPostAuthenticatedJsonRequest($route, '', '', 'GET');

        $this->log($this->l('Order validation returned:' . $response['body']));

        if (isset($response['body']) && !empty($response['body'])) {
          $responseDecode = json_decode($response['body'], true);
          if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
            throw new \Exception($this->l('Payment error: ' . $responseDecode['status'] . '-' . $responseDecode['error']));
          }

          return $responseDecode['data']['order'];
        } else {
          throw new \Exception($this->l('Unable to verify order via Sellix Pay API'));
        }
    }

    public function createOrderStates()
    {
        $exist = false;
        $order_status_id = (int) Configuration::get('SELLIXPAY_WAITING_PAYMENT_STATUS');
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
        $order_status_id = (int) Configuration::get('SELLIXPAY_HOLD_PAYMENT_STATUS');
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
        $order_status_id = (int) Configuration::get('SELLIXPAY_WAITING_PAYMENT_STATUS');
        if ($order_status_id > 0) {
            $order_status = new OrderState($order_status_id);
            if (Validate::isLoadedObject($order_status)) {
                $order_status->delete();
            }
        }

        $order_status_id = (int) Configuration::get('SELLIXPAY_HOLD_PAYMENT_STATUS');
        if ($order_status_id > 0) {
            $order_status = new OrderState($order_status_id);
            if (Validate::isLoadedObject($order_status)) {
                $order_status->delete();
            }
        }
    }

    public function getTransactionByOrderId($order_id)
    {
        return Db::getInstance()->getRow('select * FROM ' . _DB_PREFIX_ . $this->tableName . ' WHERE order_id=' . (int) $order_id);
    }

    public function getTransactionByCode($code)
    {
        return Db::getInstance()->getRow('select * FROM ' . _DB_PREFIX_ . $this->tableName . ' WHERE transaction_id="' . pSql($code) . '"');
    }

    public function updateTransaction($order_id, $transaction_id, $response = '')
    {
        $transaction = $this->getTransactionByOrderId($order_id);
        if ($transaction) {
            Db::getInstance()->query('UPDATE ' . _DB_PREFIX_ . $this->tableName . ' SET response="' . pSql($response) . '", transaction_id="' . pSql($transaction_id) . '" WHERE order_id=' . (int) $order_id);
        } else {
            Db::getInstance()->insert(
                $this->tableName,
                [
                  'order_id' => (int) $order_id,
                  'response' => $response,
                  'transaction_id' => $transaction_id,
                ]
            );
        }
    }

    public function updateOrderNote($order_id, $comment)
    {
        Db::getInstance()->query('UPDATE ' . _DB_PREFIX_ . $this->tableName . ' SET order_notes="' . pSql($comment) . '" WHERE order_id=' . (int) $order_id);
    }
}
