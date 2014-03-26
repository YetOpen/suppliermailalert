<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *  @author Lorenzo Milesi <maxxer@yetopen.it>
 *  @copyright  2014 YetOpen S.r.l.
 *  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class AdminSuppliersController extends AdminSuppliersControllerCore {

    public function renderForm() {
        // loads current warehouse
        if (!($obj = $this->loadObject(true)))
            return;

        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Suppliers'),
                'image' => '../img/admin/suppliers.gif'
            ),
            'input' => array(
                array(
                    'type' => 'hidden',
                    'name' => 'id_address',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'size' => 40,
                    'required' => true,
                    'hint' => $this->l('Invalid characters:') . ' <>;=#{}',
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Description:'),
                    'name' => 'description',
                    'cols' => 60,
                    'rows' => 10,
                    'lang' => true,
                    'hint' => $this->l('Invalid characters:') . ' <>;=#{}',
                    'desc' => $this->l('Will appear in the supplier list'),
                    'autoload_rte' => 'rte' //Enable TinyMCE editor for short description
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Phone:'),
                    'name' => 'phone',
                    'size' => 15,
                    'maxlength' => 16,
                    'desc' => $this->l('Phone number for this supplier')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Email:'),
                    'name' => 'email',
                    'size' => 100,
                    'maxlength' => 128,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Address:'),
                    'name' => 'address',
                    'size' => 100,
                    'maxlength' => 128,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Address:') . ' (2)',
                    'name' => 'address2',
                    'size' => 100,
                    'maxlength' => 128,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Postal Code/Zip Code:'),
                    'name' => 'postcode',
                    'size' => 10,
                    'maxlength' => 12,
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('City:'),
                    'name' => 'city',
                    'size' => 20,
                    'maxlength' => 32,
                    'required' => true,
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Country:'),
                    'name' => 'id_country',
                    'required' => true,
                    'default_value' => (int) $this->context->country->id,
                    'options' => array(
                        'query' => Country::getCountries($this->context->language->id, false),
                        'id' => 'id_country',
                        'name' => 'name',
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('State'),
                    'name' => 'id_state',
                    'options' => array(
                        'id' => 'id_state',
                        'query' => array(),
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'file',
                    'label' => $this->l('Logo:'),
                    'name' => 'logo',
                    'display_image' => true,
                    'desc' => $this->l('Upload a supplier logo from your computer')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Meta title:'),
                    'name' => 'meta_title',
                    'lang' => true,
                    'hint' => $this->l('Forbidden characters:') . ' <>;=#{}'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Meta description:'),
                    'name' => 'meta_description',
                    'lang' => true,
                    'hint' => $this->l('Forbidden characters:') . ' <>;=#{}'
                ),
                array(
                    'type' => 'tags',
                    'label' => $this->l('Meta keywords:'),
                    'name' => 'meta_keywords',
                    'lang' => true,
                    'hint' => $this->l('Forbidden characters:') . ' <>;=#{}',
                    'desc' => $this->l('To add "tags" click in the field, write something and then press "Enter"')
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Enable:'),
                    'name' => 'active',
                    'required' => false,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                )
            ),
            'submit' => array(
                'title' => $this->l('   Save   '),
                'class' => 'button'
            )
        );

        // loads current address for this supplier - if possible
        $address = null;
        if (isset($obj->id)) {
            $id_address = Address::getAddressIdBySupplierId($obj->id);

            if ($id_address > 0)
                $address = new Address((int) $id_address);
        }

        // force specific fields values (address)
        if ($address != null) {
            $this->fields_value = array(
                'id_address' => $address->id,
                'phone' => $address->phone,
                'address' => $address->address1,
                'address2' => $address->address2,
                'postcode' => $address->postcode,
                'city' => $address->city,
                'id_country' => $address->id_country,
                'id_state' => $address->id_state,
            );
        } else
            $this->fields_value = array(
                'id_address' => 0,
                'id_country' => Configuration::get('PS_COUNTRY_DEFAULT')
            );


        if (Shop::isFeatureActive()) {
            $this->fields_form['input'][] = array(
                'type' => 'shop',
                'label' => $this->l('Shop association:'),
                'name' => 'checkBoxShopAsso',
            );
        }

        // set logo image
        $image = ImageManager::thumbnail(_PS_SUPP_IMG_DIR_ . '/' . $this->object->id . '.jpg', $this->table . '_' . (int) $this->object->id . '.' . $this->imageType, 350, $this->imageType, true);
        $this->fields_value['image'] = $image ? $image : false;
        $this->fields_value['size'] = $image ? filesize(_PS_SUPP_IMG_DIR_ . '/' . $this->object->id . '.jpg') / 1000 : false;

        return AdminControllerCore::renderForm();
    }

}
