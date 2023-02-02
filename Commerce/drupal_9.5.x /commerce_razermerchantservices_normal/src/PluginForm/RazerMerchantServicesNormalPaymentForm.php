<?php

namespace Drupal\commerce_razermerchantservices_normal\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

class RazerMerchantServicesNormalPaymentForm extends BasePaymentOffsiteForm {
	
	/**
	* {@inheritdoc}
	*/
	public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
		$form = parent::buildConfigurationForm($form, $form_state);
		$countries = \Drupal\Core\Locale\CountryManager::getStandardList();
		/** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
		$payment = $this->entity;
		/** @var \Drupal\commerce_razermerchantservices_normal\Plugin\Commerce\PaymentGateway\RazerMerchantServicesNormalInterface $payment_gateway_plugin */
		$payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
		$gateway_configuration = $payment_gateway_plugin->getConfiguration();
		$mode = $gateway_configuration['mode'];
		$merchantid = $gateway_configuration['merchant_id'];
		$verifykey = $gateway_configuration['verify_key'];
		if($mode == 'test') {
			$url = "https://sandbox.merchant.razer.com/MOLPay/pay/".$merchantid."/";
		}
		elseif ($mode == 'live') {
			$url = "https://www.onlinepayment.com.my/MOLPay/pay/".$merchantid."/";
		}
		$order = $payment->getOrder();
		$bill_desc="";
		foreach ($order->getItems() as $order_item) {
			$bill_desc .= $order_item->getTitle()." x ". intval($order_item->getQuantity()) ." \n";
		}

		$billingprofile = $order->getBillingProfile()->get('address');
		$bill_address = $billingprofile->organization." ".$billingprofile->address_line1." ".$billingprofile->address_line2." ".$billingprofile->postal_code." ".$billingprofile->administrative_area." ".$countries[$billingprofile->country_code];
		$data = array(
			'amount' => number_format((float)$payment->getAmount()->getNumber(), 2, '.', ''),
			'orderid' => $payment->getOrderId(),
			'bill_name' => $billingprofile->given_name." ".$billingprofile->family_name,
			'bill_email' => $order->getEmail(),
			'bill_mobile' => "",
			'bill_address' => $bill_address,
			'bill_desc' => $bill_desc,
			'currency' => $payment->getAmount()->getCurrencyCode(),
			'country' => $billingprofile->country_code,
			'vcode' => md5(number_format((float)$payment->getAmount()->getNumber(), 2, '.', '').$merchantid.$payment->getOrderId().$verifykey),
			'returnurl' =>  $form['#return_url'],
			'callbackurl' =>  $form['#return_url'],
			'notifyurl' => $form['#return_url'],
			'cancel_url' => $form['#cancel_url'],
			
		);
		return $this->buildRedirectForm($form, $form_state, $url, $data, "post");
	}
}

?>
