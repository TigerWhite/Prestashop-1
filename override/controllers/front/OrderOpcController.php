<?php
class OrderOpcController extends OrderOpcControllerCore{
    
    public function initContent()
	{
		parent::initContent();
		
		/* id_carrier is not defined in database before choosing a carrier, set it to a default one to match a potential cart _rule */
		if (empty($this->context->cart->id_carrier))
		{
			$checked = $this->context->cart->simulateCarrierSelectedOutput();
			$checked = ((int)Cart::desintifier($checked));
			$this->context->cart->id_carrier = $checked;
			$this->context->cart->update();
			CartRule::autoRemoveFromCart($this->context);
			CartRule::autoAddToCart($this->context);
		}				

		// SHOPPING CART
		$this->_assignSummaryInformations();
		// WRAPPING AND TOS
		$this->_assignWrappingAndTOS();

		$selectedCountry = (int)(Configuration::get('PS_COUNTRY_DEFAULT'));

		if (Configuration::get('PS_RESTRICT_DELIVERED_COUNTRIES'))
			$countries = Carrier::getDeliveredCountries($this->context->language->id, true, true);
		else
			$countries = Country::getCountries($this->context->language->id, true);
		
		// If a rule offer free-shipping, force hidding shipping prices
		$free_shipping = false;
		foreach ($this->context->cart->getCartRules() as $rule)
			if ($rule['free_shipping'] && !$rule['carrier_restriction'])
			{
				$free_shipping = true;
				break;
			}

		$this->context->smarty->assign(array(
			'free_shipping' => $free_shipping,
			'isGuest' => isset($this->context->cookie->is_guest) ? $this->context->cookie->is_guest : 0,
			'countries' => $countries,
			'sl_country' => isset($selectedCountry) ? $selectedCountry : 0,
			'PS_GUEST_CHECKOUT_ENABLED' => Configuration::get('PS_GUEST_CHECKOUT_ENABLED'),
			'errorCarrier' => Tools::displayError('You must choose a carrier.', false),
			'errorTOS' => Tools::displayError('You must accept the Terms of Service.', false),
			'isPaymentStep' => (bool)(isset($_GET['isPaymentStep']) && $_GET['isPaymentStep']),
			'genders' => Gender::getGenders(),
			'one_phone_at_least' => (int)Configuration::get('PS_ONE_PHONE_AT_LEAST'),
			'HOOK_CREATE_ACCOUNT_FORM' => Hook::exec('displayCustomerAccountForm'),
			'HOOK_CREATE_ACCOUNT_TOP' => Hook::exec('displayCustomerAccountFormTop')
		));
		$years = Tools::dateYears();
		$months = Tools::dateMonths();
		$days = Tools::dateDays();
		$this->context->smarty->assign(array(
			'years' => $years,
			'months' => $months,
			'days' => $days,
		));

		/* Load guest informations */
		if ($this->isLogged && $this->context->cookie->is_guest)
			$this->context->smarty->assign('guestInformations', $this->_getGuestInformations());
		// ADDRESS
		if ($this->isLogged)
			$this->_assignAddress(); 
		// CARRIER
		//$this->_assignCarrier();  //Remove Carrier
		// PAYMENT
		$this->_assignPayment();
		Tools::safePostVars();

		$blocknewsletter = Module::getInstanceByName('blocknewsletter');
		$this->context->smarty->assign('newsletter', (bool)($blocknewsletter && $blocknewsletter->active));

		$this->_processAddressFormat();
		$this->setTemplate(_PS_THEME_DIR_.'order-opc.tpl');
	}
    protected function _getPaymentMethods()
	{
		if (!$this->isLogged)
			return '<p class="warning">'.Tools::displayError('Please sign in to see payment methods.').'</p>';
		if ($this->context->cart->OrderExists())
			return '<p class="warning">'.Tools::displayError('Error: This order has already been validated.').'</p>';
		if (!$this->context->cart->id_customer || !Customer::customerIdExistsStatic($this->context->cart->id_customer) || Customer::isBanned($this->context->cart->id_customer))
			return '<p class="warning">'.Tools::displayError('Error: No customer.').'</p>';
		$address_delivery = new Address($this->context->cart->id_address_delivery);
		$address_invoice = ($this->context->cart->id_address_delivery == $this->context->cart->id_address_invoice ? $address_delivery : new Address($this->context->cart->id_address_invoice));
		if (!$this->context->cart->id_address_delivery || !$this->context->cart->id_address_invoice || !Validate::isLoadedObject($address_delivery) || !Validate::isLoadedObject($address_invoice) || $address_invoice->deleted || $address_delivery->deleted)
			return '<p class="warning">'.Tools::displayError('Error: Please select an address.').'</p>';
//		if (count($this->context->cart->getDeliveryOptionList()) == 0 && !$this->context->cart->isVirtualCart())
//		{
//			if ($this->context->cart->isMultiAddressDelivery())
//				return '<p class="warning">'.Tools::displayError('Error: None of your chosen carriers deliver to some of  the addresses you\'ve selected.').'</p>';
//			else
//				return '<p class="warning">'.Tools::displayError('Error: None of your chosen carriers deliver to the address you\'ve selected.').'</p>';
//		}
//		if (!$this->context->cart->getDeliveryOption(null, false) && !$this->context->cart->isVirtualCart())
//			return '<p class="warning">'.Tools::displayError('Error: Please choose a carrier.').'</p>';
		if (!$this->context->cart->id_currency)
			return '<p class="warning">'.Tools::displayError('Error: No currency has been selected.').'</p>';
		if (!$this->context->cookie->checkedTOS && Configuration::get('PS_CONDITIONS'))
			return '<p class="warning">'.Tools::displayError('Please accept the Terms of Service.').'</p>';
		
		/* If some products have disappear */
		if (!$this->context->cart->checkQuantities())
			return '<p class="warning">'.Tools::displayError('An item in your cart is no longer available. You cannot proceed with your order.').'</p>';

		/* Check minimal amount */
		$currency = Currency::getCurrency((int)$this->context->cart->id_currency);

		$minimal_purchase = Tools::convertPrice((float)Configuration::get('PS_PURCHASE_MINIMUM'), $currency);
		if ($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS) < $minimal_purchase)
			return '<p class="warning">'.sprintf(
				Tools::displayError('A minimum purchase total of %1s (tax excl.) is required in order to validate your order, current purchase total is %2s (tax excl.).'),
				Tools::displayPrice($minimal_purchase, $currency), Tools::displayPrice($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS), $currency)
			).'</p>';

		/* Bypass payment step if total is 0 */
		if ($this->context->cart->getOrderTotal() <= 0)
			return '<p class="center"><button class="button btn btn-default button-medium" name="confirmOrder" id="confirmOrder" onclick="confirmFreeOrder();" type="submit"> <span>'.Tools::displayError('I confirm my order.').'</span></button></p>';

		$return = Hook::exec('displayPayment');
		if (!$return)
			return '<p class="warning">'.Tools::displayError('No payment method is available for use at this time. ').'</p>';
		return $return;
	}
}