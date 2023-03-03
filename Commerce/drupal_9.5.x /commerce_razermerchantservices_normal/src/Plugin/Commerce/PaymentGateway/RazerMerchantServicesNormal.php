<?php
namespace Drupal\commerce_razermerchantservices_normal\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Razer Merchant Services Normal Checkout payment gateway.
 * @link https://github.com/RazerMS/Shopping-Cart-Plugins-RazerMS_Drupal_Commerce_7
 *
 * @CommercePaymentGateway(
 *   id = "commerce_razermerchantservices_normal",
 *   label = "Razer Merchant Services Normal Payment Gateway",
 *   display_label = "Razer Merchant Services",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_razermerchantservices_normal\PluginForm\RazerMerchantServicesNormalPaymentForm"
 *   }
 * )
 */
class RazerMerchantServicesNormal extends OffsitePaymentGatewayBase {

	/**
	* For first time activation, default admin configuration value
	* @return array
	*/
	public function defaultConfiguration() {
		return [
		'merchant_id' => '',
		'verify_key' => '',
		'secret_key' => '',
		] + parent::defaultConfiguration();
	}
	
	/**
	* Define the Admin Configuration
	* @param	array $form
	* @param	\Drupal\Core\Form\FormStateInterface $form_state
	*
	* @return	array $form
	*/
	public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
		$form = parent::buildConfigurationForm($form, $form_state);

		$form['merchant_id'] = [
		'#type' => 'textfield',
		'#title' => $this->t('Merchant ID'),
		'#description' => $this->t('Merchant ID can be obtained from Razer Merchant Services Support team.'),
		'#default_value' => $this->configuration['merchant_id'],
		'#required' => TRUE,
		];

		$form['verify_key'] = [
		'#type' => 'textfield',
		'#title' => $this->t('Verify key'),
		'#description' => $this->t('Verify key can be obtained from Razer Merchant Services Support team.'),
		'#default_value' => $this->configuration['verify_key'],
		'#required' => TRUE,
		];

		$form['secret_key'] = [
		'#type' => 'textfield',
		'#title' => $this->t('Secret key'),
		'#description' => $this->t('Secret key can be obtained from Razer Merchant Services Support team.'),
		'#default_value' => $this->configuration['secret_key'],
		'#required' => TRUE,
		];

		return $form;
	}

	/**
	* Save/Update the Admin Configuration
	* @param	array $form
	* @param	\Drupal\Core\Form\FormStateInterface $form_state
	*
	*/
	public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
		parent::submitConfigurationForm($form, $form_state);
		$values = $form_state->getValue($form['#parents']);
		$this->configuration['merchant_id'] = $values['merchant_id'];
		$this->configuration['verify_key'] = $values['verify_key'];
		$this->configuration['secret_key'] = $values['secret_key'];
	}
	
	public function onReturn(OrderInterface $order, Request $request) {
		if($this->configuration['mode'] == "test") {
			$host = "https://sandbox.merchant.razer.com/";
		} else {
			$host = "https://www.onlinepayment.com.my/";
		}
		$verify_key = $this->configuration['verify_key'];
		
		if(isset($_POST['nbcb'])) {
			$nbcb = $_POST['nbcb'];
			$_POST['treq'] = 1;
		}

        $postData = [];		
		foreach($_POST as $k => $v) {
		    $postData[]= $k."=".$v;
	    }
	    \Drupal::logger('rms.return')->notice(print_r($postData,1));
	    
		if(isset($nbcb)) {
		    if($nbcb == 1) {
			    echo "CBTOKEN:MPSTATOK";
		    } elseif($nbcb == 2) {
			    $postdata   = implode("&",$postData);
			    $url        = $host."MOLPay/API/chkstat/returnipn.php";
			    $ch         = curl_init();
			    curl_setopt($ch, CURLOPT_POST           , 1     );
			    curl_setopt($ch, CURLOPT_POSTFIELDS     , $postdata );
			    curl_setopt($ch, CURLOPT_URL            , $url );
			    curl_setopt($ch, CURLOPT_HEADER         , 1  );
			    curl_setopt($ch, CURLINFO_HEADER_OUT    , TRUE   );
			    curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1  );
			    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , FALSE);
			    $result = curl_exec( $ch );
			    curl_close( $ch );
		    }
		}
		
		$tranID = $_POST['tranID']; 
		$orderid = $_POST['orderid']; 
		$status = $_POST['status']; 
		$domain = $_POST['domain']; 
		$amount = $_POST['amount']; 
		$currency = $_POST['currency']; 
		$appcode = $_POST['appcode']; 
		$paydate = $_POST['paydate']; 
		$skey = $_POST['skey'];
		
		$key0 = md5($tranID.$orderid.$status.$domain.$amount.$currency);
		$key1 = md5($paydate.$domain.$key0.$appcode.$verify_key);
		
		if($skey != $key1) {
			$status = -1;
		}
		
		$payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
		$order_storage = $this->entityTypeManager->getStorage('commerce_order');
		$messenger = \Drupal::messenger();

		$checkout_flow = $order->get('checkout_flow')->entity;
		$checkout_flow_plugin = $checkout_flow->getPlugin();

		if($status == "00") {
			$payment = $payment_storage->create([
						'state' => 'processing',
						'amount' => $order->getTotalPrice(),
						'payment_gateway' => $this->entityId,
						'payment_gateway_mode' => $this->configuration['mode'],
						'order_id' => $order->id(),
						'remote_id' => $tranID,
						'remote_state' => substr(implode("\n",$postData), 0, 252) . "..."
					]);
			$payment->save();
			$messenger->addMessage('Payment was processed for order '.$order->id());
		} elseif($status == "22") {
			$payment = $payment_storage->create([
						'state' => 'pending',
						'amount' => $order->getTotalPrice(),
						'payment_gateway' => $this->entityId,
						'payment_gateway_mode' => $this->configuration['mode'],
						'order_id' => $order->id(),
						'remote_id' => $tranID,
						'remote_state' => substr(implode("\n",$postData), 0, 252) . "..."
					]);
			$payment->save();
			$messenger->addMessage('Pending payment for order '.$order->id(),'warning');
		} else {
			$payment = $payment_storage->create([
						'state' => 'canceled',
						'amount' => $order->getTotalPrice(),
						'payment_gateway' => $this->entityId,
						'payment_gateway_mode' => $this->configuration['mode'],
						'order_id' => $order->id(),
						'remote_id' => $tranID,
						'remote_state' => substr(implode("\n",$postData), 0, 252) . "..."
					]);
			$payment->save();
			$order->set('state','canceled');
			$messenger->addMessage('Fail to complete payment for order '.$order->id(),'error');
			$checkout_flow_plugin->redirectToStep("complete");
		}
	}
}
?>