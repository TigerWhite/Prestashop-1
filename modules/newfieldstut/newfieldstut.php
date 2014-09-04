<?php

if (!defined('_PS_VERSION_'))
	exit;

class newFieldsTut extends Module
{
	/* @var boolean error */
	protected $_errors = false;
	
	public function __construct()
	{
		$this->name = 'newfieldstut';
		$this->tab = 'front_office_features';
		$this->version = '1.0';
		$this->author = 'Nemo';
		$this->need_instance = 0;

	 	parent::__construct();
                
		$this->displayName = $this->l('New Fields Tutorial');
		$this->description = $this->l('Test module from Nemo\'s Post Scriptum Tutorial (nemops.com).');
	}
	
	public function install()
	{
		if (!parent::install() OR
			!$this->alterTable('add') OR			
			!$this->registerHook('actionAdminControllerSetMedia') OR
			!$this->registerHook('actionProductUpdate') OR
			!$this->registerHook('displayAdminProductsExtra'))
			return false;
		return true;
	}
	
	public function uninstall()
	{
		if (!parent::uninstall() OR !$this->alterTable('remove'))
			return false;
		return true;
	}


	public function alterTable($method)
	{
		switch ($method) {
			case 'add':
				$sql = 'ALTER TABLE ' . _DB_PREFIX_ . 'product_lang ADD `custom_field` TEXT NOT NULL';
				break;
			
			case 'remove':
				$sql = 'ALTER TABLE ' . _DB_PREFIX_ . 'product_lang DROP COLUMN `custom_field`';
				break;
		}
		
		if(!Db::getInstance()->Execute($sql))
			return false;
		return true;
	}

	public function prepareNewTab()
	{

		$this->context->smarty->assign(array(
			'custom_field' => $this->getCustomField((int)Tools::getValue('id_product')),
			'languages' => $this->context->controller->_languages,
			'default_language' => (int)Configuration::get('PS_LANG_DEFAULT')
		));

	}

	public function hookDisplayAdminProductsExtra($params)
	{
		if (Validate::isLoadedObject($product = new Product((int)Tools::getValue('id_product'))))
		{
			$this->prepareNewTab();
			return $this->display(__FILE__, 'newfieldstut.tpl');
		}
	}	

	public function hookActionAdminControllerSetMedia($params)
	{

		// add necessary javascript to products back office
		if($this->context->controller->controller_name == 'AdminProducts' && Tools::getValue('id_product'))
		{
			$this->context->controller->addJS($this->_path.'/js/newfieldstut.js');
		}

	}

	public function hookActionProductUpdate($params)
	{
		// get all languages
		// for each of them, store the custom field!

		$id_product = (int)Tools::getValue('id_product');
		$languages = Language::getLanguages(true);
		foreach ($languages as $lang) {
			if(!Db::getInstance()->update('product_lang', array('custom_field'=> pSQL(Tools::getValue('custom_field_'.$lang['id_lang']))) ,'id_lang = ' . $lang['id_lang'] .' AND id_product = ' .$id_product ))
				$this->context->controller->_errors[] = Tools::displayError('Error: ').mysql_error();
		}

	}

	public function getCustomField($id_product)
	{
		$result = Db::getInstance()->ExecuteS('SELECT custom_field, id_lang FROM '._DB_PREFIX_.'product_lang WHERE id_product = ' . (int)$id_product);
		if(!$result)
			return array();

		foreach ($result as $field) {
			$fields[$field['id_lang']] = $field['custom_field'];
		}

		return $fields;
	}
}
