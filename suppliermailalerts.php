<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 *  @author Lorenzo Milesi <maxxer@yetopen.it>
 *  @copyright  2014 YetOpen S.r.l.
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_CAN_LOAD_FILES_'))
    exit;

class SupplierMailAlerts extends Module {

    private $_html = '';
    private $_merchant_mails;
    private $_merchant_order;
    private $_merchant_oos;
    private $_customer_qty;
    private $_merchant_coverage;
    private $_product_coverage;

    public function __construct() {
        $this->name = 'suppliermailalerts';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'YetOpen S.r.l.';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Supplier mail alerts');
        $this->description = $this->l('Sends e-mail notifications to the supplier when an order of its items is placed.');
        $this->ps_versions_compliancy = array('min' => '1.5.6.1', 'max' => _PS_VERSION_);
    }

    public function install($delete_params = true) {
        if (!parent::install() || !$this->registerHook('actionValidateOrder'))
            return false;
        
        if (!Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'supplier` ADD `email` VARCHAR( 128 ) NULL '))
            return false;
        
        return true;
    }

    public function uninstall($delete_params = true) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'supplier` DROP `email` '))
            return false;
        return parent::uninstall();
    }

    public function reset() {
        if (!$this->uninstall(false))
            return false;
        if (!$this->install(false))
            return false;

        return true;
    }

    public function hookActionValidateOrder($params) {
        // Getting differents vars
        $context = Context::getContext();
        $id_lang = (int) $context->language->id;
        $id_shop = (int) $context->shop->id;
        $currency = $params['currency'];
        $order = $params['order'];
        $customer = $params['customer'];
        $configuration = Configuration::getMultiple(array(
            'PS_SHOP_EMAIL',
            'PS_MAIL_METHOD',
            'PS_MAIL_SERVER',
            'PS_MAIL_USER',
            'PS_MAIL_PASSWD',
            'PS_SHOP_NAME',
            'PS_MAIL_COLOR'
            ), $id_lang, null, $id_shop);
        $delivery = new Address((int) $order->id_address_delivery);
        $invoice = new Address((int) $order->id_address_invoice);
        $order_date_text = Tools::displayDate($order->date_add);
        $carrier = new Carrier((int) $order->id_carrier);
        $message = $this->getAllMessages($order->id);

        if (!$message || empty($message))
            $message = $this->l('No message');

        $items_table = '';

        // Will store here suppliers email and products
        $suppliers_cache = array ();
        $products = $params['order']->getProducts();
        $customized_datas = Product::getAllCustomizedDatas((int) $params['cart']->id);
        Product::addCustomizationPrice($products, $customized_datas);
        foreach ($products as $key => $product) {
            // Get supplier for the product
            $supplier = new Supplier($product['id_supplier']);
            // If the supplier has an email set prepare it, null it otherwise
            if (empty($supplier->email)) {
                // We can go on, we don't have to notify this product
                continue;
            } else {
                $suppliers_cache[$supplier->id]['email'] = $supplier->email;
            }
            
            // From now on mostly taken from mailalerts source
            $unit_price = $product['product_price_wt'];

            $customization_text = '';
            if (isset($customized_datas[$product['product_id']][$product['product_attribute_id']])) {
                foreach ($customized_datas[$product['product_id']][$product['product_attribute_id']][$order->id_address_delivery] as $customization) {
                    if (isset($customization['datas'][_CUSTOMIZE_TEXTFIELD_]))
                        foreach ($customization['datas'][_CUSTOMIZE_TEXTFIELD_] as $text)
                            $customization_text .= $text['name'] . ': ' . $text['value'] . '<br />';

                    if (isset($customization['datas'][_CUSTOMIZE_FILE_]))
                        $customization_text .= count($customization['datas'][_CUSTOMIZE_FILE_]) . ' ' . $this->l('image(s)') . '<br />';

                    $customization_text .= '---<br />';
                }
                if (method_exists('Tools', 'rtrimString'))
                    $customization_text = Tools::rtrimString($customization_text, '---<br />');
                else
                    $customization_text = preg_replace('/---<br \/>$/', '', $customization_text);
            }

            $suppliers_cache[$supplier->id]['items_table'] .=
                    '<tr style="background-color:' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
					<td style="padding:0.6em 0.4em;">' . $product['product_reference'] . '</td>
					<td style="padding:0.6em 0.4em;">
						<strong>'
                    . $product['product_name'] . (isset($product['attributes_small']) ? ' ' . $product['attributes_small'] : '') . (!empty($customization_text) ? '<br />' . $customization_text : '') .
                    '</strong>
			</td>
			<td style="padding:0.6em 0.4em; text-align:right;">' . Tools::displayPrice($unit_price, $currency, false) . '</td>
					<td style="padding:0.6em 0.4em; text-align:center;">' . (int) $product['product_quantity'] . '</td>
					<td style="padding:0.6em 0.4em; text-align:right;">' . Tools::displayPrice(($unit_price * $product['product_quantity']), $currency, false) . '</td>
				</tr>';
            
        }
        $discount_txt = "";
        foreach ($params['order']->getCartRules() as $discount) {
            $discount_txt =
                    '<tr style="background-color:#EBECEE;">
						<td colspan="4" style="padding:0.6em 0.4em; text-align:right;">' . $this->l('Voucher code:') . ' ' . $discount['name'] . '</td>
					<td style="padding:0.6em 0.4em; text-align:right;">-' . Tools::displayPrice($discount['value'], $currency, false) . '</td>
			</tr>';
        }
        if ($delivery->id_state)
            $delivery_state = new State((int) $delivery->id_state);
        if ($invoice->id_state)
            $invoice_state = new State((int) $invoice->id_state);
        
        // Filling-in vars for email
        $template_vars = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{email}' => $customer->email,
            '{delivery_block_txt}' => MailAlert::getFormatedAddress($delivery, "\n"),
            '{invoice_block_txt}' => MailAlert::getFormatedAddress($invoice, "\n"),
            '{delivery_block_html}' => MailAlert::getFormatedAddress(
                    $delivery, '<br />', array(
                'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>'
                    )
            ),
            '{invoice_block_html}' => MailAlert::getFormatedAddress(
                    $invoice, '<br />', array(
                'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . ' font-weight:bold;">%s</span>',
                'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>'
                    )
            ),
            '{delivery_company}' => $delivery->company,
            '{delivery_firstname}' => $delivery->firstname,
            '{delivery_lastname}' => $delivery->lastname,
            '{delivery_address1}' => $delivery->address1,
            '{delivery_address2}' => $delivery->address2,
            '{delivery_city}' => $delivery->city,
            '{delivery_postal_code}' => $delivery->postcode,
            '{delivery_country}' => $delivery->country,
            '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
            '{delivery_phone}' => $delivery->phone ? $delivery->phone : $delivery->phone_mobile,
            '{delivery_other}' => $delivery->other,
            '{invoice_company}' => $invoice->company,
            '{invoice_firstname}' => $invoice->firstname,
            '{invoice_lastname}' => $invoice->lastname,
            '{invoice_address2}' => $invoice->address2,
            '{invoice_address1}' => $invoice->address1,
            '{invoice_city}' => $invoice->city,
            '{invoice_postal_code}' => $invoice->postcode,
            '{invoice_country}' => $invoice->country,
            '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
            '{invoice_phone}' => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}' => $invoice->other,
            '{order_name}' => $order->reference,
            '{shop_name}' => $configuration['PS_SHOP_NAME'],
            '{date}' => $order_date_text,
            '{carrier}' => (($carrier->name == '0') ? $configuration['PS_SHOP_NAME'] : $carrier->name),
            '{payment}' => Tools::substr($order->payment, 0, 32),
//            '{items}' => $s['items_table'],
            '{total_paid}' => Tools::displayPrice($order->total_paid, $currency),
            '{total_products}' => Tools::displayPrice($order->getTotalProductsWithTaxes(), $currency),
            '{total_discounts}' => Tools::displayPrice($order->total_discounts, $currency),
            '{total_shipping}' => Tools::displayPrice($order->total_shipping, $currency),
            '{total_tax_paid}' => Tools::displayPrice(($order->total_products_wt - $order->total_products) + ($order->total_shipping_tax_incl - $order->total_shipping_tax_excl), $currency, false),
            '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $currency),
            '{currency}' => $currency->sign,
            '{message}' => $message
        );
        

        $iso = Language::getIsoById($id_lang);
        $dir_mail = false;
        if (file_exists(dirname(__FILE__) . '/mails/' . $iso . '/new_order.txt') &&
                file_exists(dirname(__FILE__) . '/mails/' . $iso . '/new_order.html'))
            $dir_mail = dirname(__FILE__) . '/mails/';

        if (file_exists(_PS_MAIL_DIR_ . $iso . '/new_order.txt') &&
                file_exists(_PS_MAIL_DIR_ . $iso . '/new_order.html'))
            $dir_mail = _PS_MAIL_DIR_;

        if ($dir_mail)
            foreach ($suppliers_cache as $s) {
                $template_vars ['{items}'] = $s['items_table'].$discount_txt;
                Mail::Send(
                        $id_lang, 
                        'new_order', 
                        sprintf(Mail::l('New order for your products: #%d - %s', $id_lang), $order->id, $order->reference), 
                        $template_vars, 
                        $s['email'], 
                        null, 
                        $configuration['PS_SHOP_EMAIL'], 
                        $configuration['PS_SHOP_NAME'], 
                        null, 
                        null, 
                        $dir_mail, 
                        null, 
                        $id_shop
                );
            }
    }

    public function getAllMessages($id) {
        $messages = Db::getInstance()->executeS(
            'SELECT `message` FROM `'._DB_PREFIX_.'message`
            WHERE `id_order` = '.(int)$id.'
            ORDER BY `id_message` ASC'
        );
        $result = array();
        foreach ($messages as $message) {
            $result[] = $message['message'];
        }
        return implode('<br/>', $result);
    }
    
}
