<?php

if (!defined('_PS_VERSION_'))
	exit;

class pinCode extends Module{
    
    	protected $_errors = false;
        protected $_html;
    function __construct($dontTranslate = false)
 	{
 	 	$this->name = 'pincode';
		$this->version = '1';
		$this->author = 'PrestaShop';
 	 	$this->tab = 'front_office_features';
		$this->need_instance = 0;
		$this->_html='';
		parent::__construct();

		if (!$dontTranslate)
		{
			$this->displayName = $this->l('COD Availability');
			$this->description = $this->l('COD Avialability based on pincode');
 		}
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
	}
        
        public function install()
	{
	 	return (parent::install() && $this->registerHook('extraLeft') && $this->registerHook('header'));
	}
        
        public function uninstall()
	{
		return (parent::uninstall() && $this->unregisterHook('header') && $this->unregisterHook('extraLeft'));
	}
        
        public function hookExtraLeft($params)
	{
		

		return $this->display(__FILE__, 'cod_pincode_check.tpl');
	}
        
        public function getContent()
	{
		$this->_html = $this->renderForm();
                return $this->_html;
        }
        
        private function renderForm(){
            
            $options = array();
            foreach (CarrierCore::getCarriers((int)Context::getContext()->language->id,true,true) as $carrier)
            {
               
              $options[] = array(
                "id" => $carrier['id_carrier'],
                "name" => $carrier['name']
              );
            }
           // ddd(CarrierCore::getCarriers((int)Context::getContext()->language->id,true,true));
            $fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Uplod PinCode'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
                                            'type' => 'select',                              // This is a <select> tag.
                                            'label' => $this->l('Shipping method:'),         // The <label> for this <select> tag.
                                            'desc' => $this->l('Choose a shipping method'),  // A help text, displayed right next to the <select> tag.
                                            'name' => 'shipping_method',                     // The content of the 'id' attribute of the <select> tag.
                                            'required' => true,                              // If set to true, this option must be set.
                                            'options' => array(
                                              'query' => $options,                           // $options contains the data itself.
                                              'id' => 'id',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
                                              'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
                                            )
                                          ),
				),
				'submit' => array(
					'title' => $this->l('Save'),
					'class' => 'btn btn-default pull-right',
					'name' => 'submitText',
				)
			),
		);
            
               $helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitModule';
		$helper->module = $this;
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'currencies' => Currency::getCurrencies(),
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);
		
		$helper->override_folder = '/';
		
		return $helper->generateForm(array($fields_form));
        }
        
        private function getConfigFieldsValues(){
        return array(
			'shipping_method' => Tools::getValue('shipping_method', "Sthish"),
			
        );
        }
}