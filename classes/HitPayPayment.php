<?php
/**
* 2007-2021 PrestaShop
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
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class HitPayPayment extends ObjectModel
{
    /**
     * @var int $id_hitpay_payments
     */
    public $id_hitpay_payments;

    /**
     * @var string $payment_id
     */
    public $payment_id;

    /**
     * @var int $cart_id
     */
    public $cart_id;

    /**
     * @var int $order_id
     */
    public $order_id;

    /**
     * @var float $amount
     */
    public $amount;

    /**
     * @var int $currency_id
     */
    public $currency_id;

    /**
     * @var string $date_add
     */
    public $date_add;

    /**
     * @var string $date_upd
     */
    public $date_upd;

    /**
     * @var int $id_shop_default
     */
    public $id_shop_default;

    /**
     * @var string
     */
    public $status;

    /**
     * @var int
     */
    public $customer_id;

    /**
     * @var bool
     */
    public $is_paid;

    /**
     * @var array
     */
    public static $definition = array(
        'table' => 'hitpay_payments',
        'primary' => 'id_hitpay_payments',
        'multilang_shop' => true,
        'multishop' => true,
        'fields' => array(
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'id_shop_default' => array('type' => self::TYPE_INT, 'validate' => 'isInt'),

            //shop fields
            'payment_id' => array(
                'type' => self::TYPE_STRING,
                'shop' => true,
                'required' => true,
                'validate' => 'isString',
                'size' => 255,
            ),
            'status' => array(
                'type' => self::TYPE_STRING,
                'shop' => true,
                'validate' => 'isString',
                'size' => 255,
            ),
            'cart_id' => array(
                'type' => self::TYPE_STRING,
                'shop' => true,
                'validate' => 'isInt',
                'size' => 20
            ),
            'order_id' => array(
                'type' => self::TYPE_STRING,
                'shop' => true,
                'validate' => 'isInt',
                'size' => 20
            ),
            'amount' => array(
                'type' => self::TYPE_FLOAT,
                'shop' => true,
                'required' => true,
                'validate' => 'isFloat',
                'size' => 10
            ),
            'currency_id' => array(
                'type' => self::TYPE_INT,
                'shop' => true,
                'validate' => 'isInt',
                'size' => 10
            ),
            'customer_id' => array(
                'type' => self::TYPE_INT,
                'shop' => true,
                'validate' => 'isInt',
                'size' => 10
            ),
            'is_paid' => array(
                'type' => self::TYPE_BOOL,
                'shop' => true,
                'validate' => 'isBool',
                'size' => 10
            ),
        ),
    );

    /**
     * PrsProducts constructor.
     * @param null $id
     * @param null $id_lang
     * @param null $id_shop
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        Shop::addTableAssociation(self::$definition['table'], array('type' => 'shop'));
        parent::__construct($id, $id_lang, $id_shop);
    }

    /**
     * @param bool $auto_date
     * @param bool $null_values
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function add($auto_date = true, $null_values = false)
    {
        $context = Context::getContext();
        $this->id_shop_default = $context->shop->id;

        return parent::add($auto_date, $null_values);
    }

    /**
     * @param bool $null_values
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function update($null_values = false)
    {
        $context = Context::getContext();
        $this->id_shop_default = $context->shop->id;

        return parent::update($null_values);
    }

    /**
     * @return bool
     */
    public static function install()
    {
        $product_tab = new self();
        if ($product_tab->createTable()) {
            return true;
        }

        return false;
    }

    /**
     * @param $id
     * @return HitPayPayment
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getById($id)
    {
        $shop_id = Context::getContext()->shop->id;

        $id = Db::getInstance()->getValue(
            ' SELECT prsps.' . self::$definition['primary'] . ' FROM '
            . _DB_PREFIX_ . static::$definition['table'] . ' AS prsp'
            . ' LEFT JOIN ' . _DB_PREFIX_ . self::$definition['table'] . '_shop AS prsps '
            . ' ON (prsps.' . self::$definition['primary'] . ' = prsp.' . self::$definition['primary'] . ' '
            . ' AND prsps.`id_shop` = ' . (int)$shop_id . ')'
            . ' WHERE prsps.payment_id = "' . pSQL($id) . '"'
        );

        return new HitPayPayment($id, null, $shop_id);
    }
    
    /**
     * @param $order_id
     * @return HitPayPayment
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByOrderId($order_id)
    {
        $shop_id = Context::getContext()->shop->id;

        $id = Db::getInstance()->getValue(
            ' SELECT prsps.' . self::$definition['primary'] . ' FROM '
            . _DB_PREFIX_ . static::$definition['table'] . ' AS prsp'
            . ' LEFT JOIN ' . _DB_PREFIX_ . self::$definition['table'] . '_shop AS prsps '
            . ' ON (prsps.' . self::$definition['primary'] . ' = prsp.' . self::$definition['primary'] . ' '
            . ' AND prsps.`id_shop` = ' . (int)$shop_id . ')'
            . ' WHERE prsp.order_id = "' . pSQL($order_id) . '"'
        );

        return new HitPayPayment($id, null, $shop_id);
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    protected function createTable()
    {
        $tables = array();

        $tables[] = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . self::$definition['table'] . ' ('
            . '`' . self::$definition['primary'] . '` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT, '
            . '`id_shop_default` INT(11) NOT NULL, '
            . '`payment_id` CHAR(255) NOT NULL, '
            . '`status` CHAR(255) NOT NULL, '
            . '`cart_id` INT(11) NOT NULL, '
            . '`order_id` INT(11), '
            . '`amount` DECIMAL(20, 6) NOT NULL, '
            . '`currency_id` INT(11) NOT NULL, '
            . '`customer_id` INT(11) NOT NULL, '
            . '`is_paid` BOOL NOT NULL DEFAULT 0, '
            . '`date_add` TIMESTAMP, '
            . '`date_upd` TIMESTAMP '
            . ') ENGINE=' . _MYSQL_ENGINE_ . ' CHARACTER SET=UTF8;';

        $tables[] = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . self::$definition['table'] . '_shop ('
            . '`' . self::$definition['primary'] . '` INT(11) NOT NULL, '
            . '`id_shop` INT(11) NOT NULL, '
            . '`payment_id` CHAR(255) NOT NULL, '
            . '`status` CHAR(255) NOT NULL, '
            . '`cart_id` INT(11) NOT NULL, '
            . '`order_id` INT(11), '
            . '`amount` DECIMAL(20, 6) NOT NULL, '
            . '`currency_id` INT(11) NOT NULL, '
            . '`customer_id` INT(11) NOT NULL, '
            . '`is_paid` BOOL NOT NULL DEFAULT 0, '
            . 'UNIQUE KEY ' . self::$definition['table'] . '_shop (`' . self::$definition['primary'] . '`, `id_shop`) '
            . ') ENGINE=' . _MYSQL_ENGINE_ . ' CHARACTER SET=UTF8;';


        return $this->execute($tables);
    }

    /**
     * @return bool
     */
    public static function uninstall()
    {
        $product_tab = new self();
        if ($product_tab->dropTables()) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function dropTables()
    {
        $tables = array();

        $tables[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . self::$definition['table'] . ';';
        $tables[] = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . self::$definition['table'] . '_shop;';

        return $this->execute($tables);
    }

    /**
     * @param $sql
     * @return bool
     * @throws PrestaShopException
     */
    protected function query($sql)
    {
        try {
            return Db::getInstance()->execute($sql);
        } catch (Exception $e) {
            throw new PrestaShopException($e->getMessage());
        }
    }

    /**
     * @param $sqls
     * @return bool
     * @throws PrestaShopException
     */
    protected function execute($sqls)
    {
        foreach ($sqls as $sql) {
            if (!$this->query($sql)) {
                return false;
            }
        }

        return true;
    }
}
