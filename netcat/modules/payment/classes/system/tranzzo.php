<?
//netcat ver. 6.0.0
class nc_payment_system_tranzzo extends nc_payment_system {


    protected $automatic = TRUE;

    // принимаемые валюты
    protected $accepted_currencies = array('USD', 'EUR', 'UAH', 'RUB', 'RUR');
	
    // сопоставление кодов валют
    protected $currency_map = array(
        'RUR' => 'RUB',
    );
	
    // параметры сайта в платежной системе
    protected $settings = array(
        'TRANZZO_POS_ID' => null,
        'TRANZZO_API_KEY' => null,
        'TRANZZO_API_SECRET' => null,
        'TRANZZO_ENDPOINTS_KEY' => null,
    );

    // передаваемые параметры
    protected $request_parameters = array();

    // получаемые параметры
    protected $callback_response = array(
        'data' => null,
        'signature' => null,
		'order_id' => null,
		'status' => null,
		);

    /**
     *
     */
    public function execute_payment_request(nc_payment_invoice $invoice) {
	
		 require_once(__DIR__ . '/tranzzo/TranzzoApi.php');


        $total = $invoice->get_amount('%0.2F');
        $currency = $this->get_currency_code($invoice->get_currency());
	
        $tranzzo_api = new TranzzoApi(trim($this->get_setting('TRANZZO_POS_ID')), trim($this->get_setting('TRANZZO_API_KEY')),trim($this->get_setting('TRANZZO_API_SECRET')), trim($this->get_setting('TRANZZO_ENDPOINTS_KEY')));
	    $tranzzo_api->setServerUrl($this->get_module_url() .'/tranzzo/callback.php');
		$tranzzo_api->setResultUrl($this->get_return_url($invoice));
		$tranzzo_api->setOrderId($invoice->get_id());
		$tranzzo_api->setAmount($total);
		$tranzzo_api->setCurrency($currency);
		$tranzzo_api->setDescription($invoice->get_description());
		$form = array();
		//print_r($tranzzo_api);
        $response = $tranzzo_api->createPaymentHosted(0);
		//print_r($response);
		//$this->wrlog($response);
	    $tr_action = '';
		if (!empty($response['redirect_url'])) {
			$tr_action = $response['redirect_url'];
        }else{
			// ?
		}	

		
        ob_end_clean();
        $form = "
            <html>
              <body>
                    <form action='" . $tr_action . "' method='get'></form>
                <script>
                  document.forms[0].submit();
                </script>
              </body>
            </html>";
        echo $form;
    }

    /**
     * @param nc_payment_invoice $invoice
     */
    public function on_response(nc_payment_invoice $invoice = null) {
			require_once(__DIR__ . '/tranzzo/TranzzoApi.php');
        $signature = $this->get_response_value('signature');
        $data = $this->get_response_value('data');
		
		$tranzzo_api = new TranzzoApi(trim($this->get_setting('TRANZZO_POS_ID')), trim($this->get_setting('TRANZZO_API_KEY')),trim($this->get_setting('TRANZZO_API_SECRET')), trim($this->get_setting('TRANZZO_ENDPOINTS_KEY')));
		$data_response = TranzzoApi::parseDataResponse($data);	
		$status = $data_response[TranzzoApi::P_RES_STATUS];
		$code = $data_response[TranzzoApi::P_RES_RESP_CODE];
		
       if ($status === TranzzoApi::P_TRZ_ST_SUCCESS) {
$this->on_payment_success($invoice);
        } else if ($status === TranzzoApi::P_TRZ_ST_CANCEL) {
            $this->on_payment_rejected($invoice);
        } else if ($status === TranzzoApi::P_TRZ_ST_PENDING && $invoice) {
            $invoice->set('status', nc_payment_invoice::STATUS_WAITING)->save();
        }
		
    }

    /**
     *
     */
    public function validate_payment_request_parameters() {
        if (!$this->get_setting('TRANZZO_POS_ID') ) {
            $this->add_error('ERROR_POS_ID_IS_NOT_VALID');
        }
		        if (!$this->get_setting('TRANZZO_API_KEY')) {
            $this->add_error('ERROR_API_KEY_IS_NOT_VALID');
        }
		        if (!$this->get_setting('TRANZZO_API_SECRET')) {
            $this->add_error('ERROR_API_SECRET_ID_NOT_VALID');
        }
		
		        if (!$this->get_setting('TRANZZO_ENDPOINTS_KEY')) {
            $this->add_error('ERROR_ENDPOINTS_KEY_IS_NOT_VALID');
        }
		
    }


    /**
     * @param nc_payment_invoice $invoice
     */
    public function validate_payment_callback_response(nc_payment_invoice $invoice = null) {
		
		
		
		 if (!$invoice) {
            $this->add_error(NETCAT_MODULE_PAYMENT_ERROR_INVOICE_NOT_FOUND);
            return ;
        }
		
		require_once(__DIR__ . '/tranzzo/TranzzoApi.php');
        $signature = $this->get_response_value('signature');
        $data = $this->get_response_value('data');
		
		$tranzzo_api = new TranzzoApi(trim($this->get_setting('TRANZZO_POS_ID')), trim($this->get_setting('TRANZZO_API_KEY')),trim($this->get_setting('TRANZZO_API_SECRET')), trim($this->get_setting('TRANZZO_ENDPOINTS_KEY')));
				$data_response = TranzzoApi::parseDataResponse($data);
	$method_response = $data_response[TranzzoApi::P_REQ_METHOD];
		if ($method_response == TranzzoApi::P_METHOD_AUTH || $method_response == TranzzoApi::P_METHOD_PURCHASE) {
                $order_id = (int)$data_response[TranzzoApi::P_RES_PROV_ORDER];
                $tranzzo_order_id = (int)$data_response[TranzzoApi::P_RES_ORDER];
            } else {
                $order_id = (int)$data_response[TranzzoApi::P_RES_ORDER];
            }


		$status = $data_response[TranzzoApi::P_RES_STATUS];
		$amount_payment = TranzzoApi::amountToDouble($data_response[TranzzoApi::P_RES_AMOUNT]);
		


        if (!$invoice || !$tranzzo_api->validateSignature($data, $signature) || $amount_payment != $invoice->get_amount()) {

            $this->add_error('ERROR_SIGNATURE_NOT_VALID');

            if ($invoice) {
                $invoice->set('status', nc_payment_invoice::STATUS_CALLBACK_ERROR);
                $invoice->save();
            }
        }
		

		
		
		
    }

    public function load_invoice_on_callback() {
		require_once(__DIR__ . '/tranzzo/TranzzoApi.php');

			$data = $this->get_response_value('data');
			$tranzzo_api = new TranzzoApi(trim($this->get_setting('TRANZZO_POS_ID')), trim($this->get_setting('TRANZZO_API_KEY')),trim($this->get_setting('TRANZZO_API_SECRET')), trim($this->get_setting('TRANZZO_ENDPOINTS_KEY')));
				$data_response = TranzzoApi::parseDataResponse($data);
	$method_response = $data_response[TranzzoApi::P_REQ_METHOD];
		if ($method_response == TranzzoApi::P_METHOD_AUTH || $method_response == TranzzoApi::P_METHOD_PURCHASE) {
                $order_id = (int)$data_response[TranzzoApi::P_RES_PROV_ORDER];
                $tranzzo_order_id = (int)$data_response[TranzzoApi::P_RES_ORDER];
            } else {
                $order_id = (int)$data_response[TranzzoApi::P_RES_ORDER];
            }			
		
        return $this->load_invoice($order_id);
    }
	
	    protected function get_return_url(nc_payment_invoice $invoice) {
        $return_url = $this->get_setting('return_url');
        if ($return_url) {
            return $return_url . (strpos($return_url, '?') ? '&' : '?') . 'invoice_id=' . $invoice->get_id();
        }

        $nc_core = nc_core::get_object();
        $site_id = $invoice->get('site_id');

        // Страница заказа
        if ($invoice->get('order_source') === 'netshop') {
            $order_component_id = nc_netshop::get_instance($site_id)->settings->get('OrderComponentID');
            $return_url = (string)nc_object_url($order_component_id, $invoice->get('order_id'));
        }

        // Личный кабинет
        if (!$return_url) {
            $auth_cabinet_subdivision_id = $nc_core->catalogue->get_by_id($site_id, 'AuthCabinetSubID');
            if ($auth_cabinet_subdivision_id) {
                $return_url = (string)nc_folder_url($auth_cabinet_subdivision_id);
            }
        }

        return $return_url ?: nc_get_scheme() . "://$_SERVER[HTTP_HOST]/";
    }
	
	
}
