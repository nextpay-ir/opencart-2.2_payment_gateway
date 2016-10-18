<?php

include_once (dirname(__FILE__).'/include/nextpay_payment.php');

class ControllerPaymentNextpay extends Controller {
	public function index() {
		$this->load->language('payment/nextpay');
		
		$data['text_connect'] = $this->language->get('text_connect');
		$data['text_loading'] = $this->language->get('text_loading');
		$data['text_wait'] = $this->language->get('text_wait');
		
		$data['button_confirm'] = $this->language->get('button_confirm');

		return $this->load->view('default/template/payment/nextpay.tpl', $data);
	}

	public function confirm() {
		$this->load->language('payment/nextpay');

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		$amount = $this->correctAmount($order_info);
		
		$data['return'] = $this->url->link('checkout/success', '', true);
		$data['cancel_return'] = $this->url->link('checkout/payment', '', true);
		$data['back'] = $this->url->link('checkout/payment', '', true);
		
		$Api_Key = $this->config->get('nextpay_pin');  	//Required
		$Amount = $amount; 									//Amount will be based on Toman  - Required
		$data['order_id'] = $this->session->data['order_id'] ;
		$CallbackURL = $this->url->link('payment/nextpay/callback', 'order_id=' . $data['order_id'], true);  // Required

        $parameters = array (
            "api_key"=> $Api_Key,
            "order_id"=> $data['order_id'],
            "amount"=> $Amount,
            "callback_uri"=> $CallbackURL
        );

		$requestResult = $this->nxRequest($parameters);

		if(!$requestResult){
			$json = array();
			$json['error']= $this->language->get('error_cant_connect');				
		} elseif($requestResult->code == -1) {
			$data['action'] = "http://api.nextpay.org/gateway/payment/" . $requestResult->trans_id;
			$json['success']= $data['action'];
		} else {
			$json = $this->checkState($requestResult->code);
		}

		$this->response->addHeader('Content-Type: application/json');

		return $this->response->setOutput(json_encode($json));
	}



	public function callback() {
		
		$this->load->language('payment/nextpay');

		if ($this->session->data['payment_method']['code'] != 'nextpay') {
			return false;
		}


		$this->document->setTitle($this->language->get('text_title'));

		$data['heading_title'] = $this->language->get('text_title');
		$data['text_results'] = $this->language->get('text_results');
		$data['results'] = "";

			//breadcrumbs
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'), 
			'href' => $this->url->link('common/home', '', true)
			);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_title'), 
			'href' => $this->url->link('payment/nextpay/callback', '', true)
			);

		try {

			$order_id = isset($this->session->data['order_id']) ? $this->session->data['order_id'] : 0;
			$this->load->model('checkout/order');
			$order_info = @$this->model_checkout_order->getOrder($order_id);

			if (!$order_info)
				throw new Exception($this->language->get('error_order_id'));

			$trans_id = $this->request->post['trans_id'];
			$amount = $this->correctAmount($order_info);

			$verifyResult = $this->verifyPayment($trans_id, $order_id, $amount);

            if ($verifyResult == 0 ) {
					$comment = $this->language->get('text_results') . $trans_id;
					$data['text_results'] = $this->language->get('text_results') . '[' .  $trans_id . ']';
					$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('nextpay_order_status_id'), $comment, true);

					$data['error_warning'] = NULL;
					$data['results'] = 'پرداخت موفق';
					$data['button_continue'] = $this->language->get('button_complete');
					$data['continue'] = $this->url->link('checkout/success');
            }else{
                throw new Exception($this->checkState($verifyResult)['error']);
			}

		} catch (Exception $e) {
			$data['error_warning'] = $e->getMessage();
			$data['button_continue'] = $this->language->get('button_view_cart');
			$data['continue'] = $this->url->link('checkout/cart');
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('default/template/payment/nextpay_confirm.tpl', $data));
	}

	private function correctAmount($order_info)
	{
		$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$amount = round($amount);
		$amount = $this->currency->convert($amount, $order_info['currency_code'], "TOM");
		return (int)$amount;
	}

	private function nxRequest($parameters){
		try{
            $nextpay = new Nextpay_Payment($parameters);
            $res = $nextpay->token();

            return $res;

		} catch(SoapFault $e) {
			return false;
		}
	}

	private function nxVerification($context){
		try {
            $nextpay_payment = new Nextpay_Payment();
            $result = $nextpay_payment->verify_request($context);

            return $result;
		} catch(SoapFault $e) {
			return false;
		}		
	}

	private function checkState($status) {
		$json = array();
		$json['error'] = 'خطا در پرداخت : ' . $status;

		return $json;
	}


	private function verifyPayment($trans_id, $order_id, $amount){

		$data['api_key'] = $this->config->get('nextpay_pin');
		$context = array(
			'api_key'	 => $data['api_key'],
			'trans_id' 	 => $trans_id,
			'order_id'   => $order_id,
			'amount'	 => $amount
			);
		$verifyResult = $this->nxVerification($context);

		return $verifyResult ;
	}
}
?>
