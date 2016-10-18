<?php 
class ModelPaymentNextpay extends Model {
  	public function getMethod($address) {
		$this->load->language('payment/nextpay');

		if ($this->config->get('nextpay_status')) {
      		$status = true;
      	} else {
			$status = false;
		}

		$method_data = array();
		
		if ($status) {
      		$method_data = array( 
        		'code'       => 'nextpay',
        		'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('nextpay_sort_order')
      		);
    	}
		
    	return $method_data;
  	}
}
?>