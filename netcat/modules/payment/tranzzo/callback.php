<?php
//netcat ver. 6.0.0
$NETCAT_FOLDER = realpath(__DIR__ . '/../../../../') . '/';

require_once $NETCAT_FOLDER . 'vars.inc.php';
require $INCLUDE_FOLDER . 'index.php';

// Регистрация слушателя событий для netshop, если этот модуль включён
if (nc_module_check_by_keyword('netshop')) {
    $netshop = nc_netshop::get_instance();
    /** @var nc_event $event */
    $event = nc_core('event');
    $event->bind($netshop, array(nc_payment_system::EVENT_ON_PAY_SUCCESS => 'on_payment_success_event_handler'));
    $event->bind($netshop, array(nc_payment_system::EVENT_ON_PAY_FAILURE => 'on_payment_failure_event_handler'));
    $event->bind($netshop, array(nc_payment_system::EVENT_ON_PAY_REJECTED => 'on_payment_rejected_event_handler'));
}

// Собственно обработка ответа

/** @var nc_input $input */
$input = nc_core('input');
wrlog($input);
$payment_system_class = 'nc_payment_system_tranzzo';

$payment = nc_payment_factory::create($payment_system_class);
wrlog($payment);
if ($payment) {
	wrlog('start process_callback_response');
    $payment->process_callback_response($input->fetch_get_post());
	wrlog('end process_callback_response');
}




	     function wrlog($content)
        {
            return true;
			$file = 'log.txt';
            $doc = fopen($file, 'a');
            if($doc){
                file_put_contents($file, PHP_EOL . '====================' . date("H:i:s") . '=====================', FILE_APPEND);
                if (is_array($content)) {
                    foreach ($content as $k => $v) {
                        if (is_array($v)) {
                            wrlog($v);
                        } else {
                            file_put_contents($file, PHP_EOL . $k . '=>' . $v, FILE_APPEND);
                        }
                    }
                }elseif(is_object($content)){
                    foreach (get_object_vars($content) as $k => $v) {
                        if (is_object($v)) {
                            wrlog($v);
                        } else {
                            file_put_contents($file, PHP_EOL . $k . '=>' . $v, FILE_APPEND);
                        }
                    }
                } else {
                    file_put_contents($file, PHP_EOL . $content, FILE_APPEND);
                }
                fclose($doc);
            }
        }
	
	
 