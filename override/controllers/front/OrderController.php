<?php

class OrderController extends OrderControllerCore {

    public function initContent() {
        parent::initContent();

        if (Tools::isSubmit('ajax') && Tools::getValue('method') == 'updateExtraCarrier') {
            // Change virtualy the currents delivery options
            $delivery_option = $this->context->cart->getDeliveryOption();
            $delivery_option[(int) Tools::getValue('id_address')] = Tools::getValue('id_delivery_option');
            $this->context->cart->setDeliveryOption($delivery_option);
            $this->context->cart->save();
            $return = array(
                'content' => Hook::exec(
                        'displayCarrierList', array(
                    'address' => new Address((int) Tools::getValue('id_address'))
                        )
                )
            );
            die(Tools::jsonEncode($return));
        }

        if ($this->nbProducts)
            $this->context->smarty->assign('virtual_cart', $this->context->cart->isVirtualCart());

        if (!Tools::getValue('multi-shipping'))
            $this->context->cart->setNoMultishipping();
        
        // 4 steps to the order
        switch ((int) $this->step) {
            case -1;
                $this->context->smarty->assign('empty', 1);
                $this->setTemplate(_PS_THEME_DIR_ . 'shopping-cart.tpl');
                break;

            case 1:
                $this->_assignAddress();
                $this->processAddressFormat();
                if (Tools::getValue('multi-shipping') == 1) {
                    $this->_assignSummaryInformations();
                    $this->context->smarty->assign('product_list', $this->context->cart->getProducts());
                    $this->setTemplate(_PS_THEME_DIR_ . 'order-address-multishipping.tpl');
                } else
                    $this->setTemplate(_PS_THEME_DIR_ . 'order-address.tpl');
                break;

            case 2:
                if (Tools::isSubmit('processAddress'))
					$this->processAddress();
                
				$this->autoStep();
                                
               if (($id_order = $this->_checkFreeOrder()) && $id_order) {
                    if ($this->context->customer->is_guest) {
                        $order = new Order((int) $id_order);
                        $email = $this->context->customer->email;
                        $this->context->customer->mylogout(); // If guest we clear the cookie for security reason
                        Tools::redirect('index.php?controller=guest-tracking&id_order=' . urlencode($order->reference) . '&email=' . urlencode($email));
                    } else
                        Tools::redirect('index.php?controller=history');
                }
                $this->_assignPayment();
                // assign some informations to display cart
                $this->_assignSummaryInformations();
                $this->setTemplate(_PS_THEME_DIR_ . 'order-payment.tpl');
                break;

         
            default:
                $this->_assignSummaryInformations();
                $this->setTemplate(_PS_THEME_DIR_ . 'shopping-cart.tpl');
                break;
        }

        $this->context->smarty->assign(array(
            'currencySign' => $this->context->currency->sign,
            'currencyRate' => $this->context->currency->conversion_rate,
            'currencyFormat' => $this->context->currency->format,
            'currencyBlank' => $this->context->currency->blank,
        ));
    }

    /**
	 * Manage address
	 */
	public function processAddress()
	{
		$same = Tools::isSubmit('same');
		if(!Tools::getValue('id_address_invoice', false) && !$same)
			$same = true;

		if (!Customer::customerHasAddress($this->context->customer->id, (int)Tools::getValue('id_address_delivery'))
			|| (!$same && Tools::getValue('id_address_delivery') != Tools::getValue('id_address_invoice')
				&& !Customer::customerHasAddress($this->context->customer->id, (int)Tools::getValue('id_address_invoice'))))
			$this->errors[] = Tools::displayError('Invalid address', !Tools::getValue('ajax'));
		else
		{
			$this->context->cart->id_address_delivery = (int)Tools::getValue('id_address_delivery');
			$this->context->cart->id_address_invoice = $same ? $this->context->cart->id_address_delivery : (int)Tools::getValue('id_address_invoice');
			
			CartRule::autoRemoveFromCart($this->context);
			CartRule::autoAddToCart($this->context);
			
			if (!$this->context->cart->update())
				$this->errors[] = Tools::displayError('An error occurred while updating your cart.', !Tools::getValue('ajax'));

			if (!$this->context->cart->isMultiAddressDelivery())
				$this->context->cart->setNoMultishipping(); // If there is only one delivery address, set each delivery address lines with the main delivery address

			if (Tools::isSubmit('message'))
				$this->_updateMessage(Tools::getValue('message'));
						
//			// Add checking for all addresses
//			$address_without_carriers = $this->context->cart->getDeliveryAddressesWithoutCarriers();
//			if (count($address_without_carriers) && !$this->context->cart->isVirtualCart())
//			{
//				if (count($address_without_carriers) > 1)
//					$this->errors[] = sprintf(Tools::displayError('There are no carriers that deliver to some addresses you selected.', !Tools::getValue('ajax')));
//				elseif ($this->context->cart->isMultiAddressDelivery())
//					$this->errors[] = sprintf(Tools::displayError('There are no carriers that deliver to one of the address you selected.', !Tools::getValue('ajax')));
//				else
//					$this->errors[] = sprintf(Tools::displayError('There are no carriers that deliver to the address you selected.', !Tools::getValue('ajax')));
//			}
		}
		
		if ($this->errors)
		{
			if (Tools::getValue('ajax'))
				die('{"hasError" : true, "errors" : ["'.implode('\',\'', $this->errors).'"]}');
			$this->step = 1;
		}

		if ($this->ajax)
			die(true);
	}
        
        public function autoStep()
	{
		if ($this->step >= 2 && (!$this->context->cart->id_address_delivery || !$this->context->cart->id_address_invoice))
			Tools::redirect('index.php?controller=order&step=1');

//		if ($this->step > 2 && !$this->context->cart->isVirtualCart())
//		{
//			$redirect = false;
//			if (count($this->context->cart->getDeliveryOptionList()) == 0)
//				$redirect = true;
//
//			if (!$this->context->cart->isMultiAddressDelivery())
//				foreach ($this->context->cart->getProducts() as $product)
//					if (!in_array($this->context->cart->id_carrier, Carrier::getAvailableCarrierList(new Product($product['id_product']), null, $this->context->cart->id_address_delivery)))
//					{
//						$redirect = true;
//						break;
//					}
//			
//			if ($redirect)
//				Tools::redirect('index.php?controller=order&step=2');
//		} 

		$delivery = new Address((int)$this->context->cart->id_address_delivery);
		$invoice = new Address((int)$this->context->cart->id_address_invoice);

		if ($delivery->deleted || $invoice->deleted)
		{
			if ($delivery->deleted)
				unset($this->context->cart->id_address_delivery);
			if ($invoice->deleted)
				unset($this->context->cart->id_address_invoice);
			Tools::redirect('index.php?controller=order&step=1');
		}
	}

}
