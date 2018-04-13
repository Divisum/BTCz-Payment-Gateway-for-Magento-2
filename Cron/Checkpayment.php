<?php


namespace SilverStoreS\Btcz\Cron;

class Checkpayment
{

    protected $logger;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
		\Psr\Log\LoggerInterface $logger,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	)
    {
        $this->logger = $logger;
		$this->_scopeConfig = $scopeConfig;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->addInfo("Cronjob BTCz Payment Check is executed.");
		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
		$connection = $resource->getConnection();
		$tableName = $resource->getTableName('btcz');
		$sql = 'SELECT * FROM `' . $tableName . '` WHERE `processed` = 0';
		$orders = $connection->query($sql);
		
		$status_after = $this->_scopeConfig->getValue('payment/btcz/after_complete', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		
		foreach ($orders as $order) {
			
			//$this->logger->addInfo("order - ".json_encode($order));
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$orderloaded = $objectManager->create('\Magento\Sales\Model\Order') ->loadByIncrementId($order['increment_id']);
			
			$result = $this->checkPayment($order['url_id']);
			$this->logger->addInfo("order result - ".$result);
			//$this->logger->addInfo('compl ' . $status_after);
			if ($result) {
				$data = json_decode($result);
				
				/* EXAMPLE
				$data = json_decode('{
					"url_id":"cc3ef6c0310d76f32101da08a1bf7b2a",
					"amount_needed":"46.9025",
					"amount_received":"46.9025",
					"generated":"t1SmXwTe2BTFCc4snDiQutStWpxd2Vgwvey",
					"state":"5",
					"tx_right":";e67b6d101dc34dcbda3e0c86684ef079dbf485e10e8bbe59b1d863259330fc12",
					"timestamp_start":"1522087456",
					"timestamp_end":"1522088956",
					"timestamp_initiated":"1522087460",
					"confirmations":"2",
					"timeComplete":"1522088161",
					"invoicename":"000000025",
					"b64_successurl":"",
					"currentTime":"1522088161"
					}');
				*/
				
				if($data->state == 5) { //success
					
					$orderloaded->setState($status_after)->setStatus($status_after);
					$orderloaded->addStatusHistoryComment('Invoice '.$data->url_id.' was payed successfully. JSON: https://btcz.in/api/process?f=getinfo&id='.$data->url_id);
					$orderloaded->save();
					$sqlupt = "UPDATE `". $tableName ."` SET `processed` = '1' WHERE `increment_id` = ".$order['increment_id'];
					$connection->query($sqlupt);
						
				} else if($data->state == 2) { //expired
					
					$orderloaded->setState("closed")->setStatus("closed");
					$orderloaded->addStatusHistoryComment('Invoice '.$data->url_id.' was payed not payed ['.$data->strState.']. JSON: https://btcz.in/api/process?f=getinfo&id='.$data->url_id);
					$orderloaded->save();
					$sqlupt = "UPDATE `". $tableName ."` SET `processed` = '1' WHERE `increment_id` = ".$order['increment_id'];
					$connection->query($sqlupt);
						
				};
			} else {
				continue;
			};
		
		};
		
    }
	
	function checkPayment($InvoiceID)
	{
		$APIUrl = 'https://btcz.in/api/process';
				
		$fields = array(
			'f' => "getinfo",
			'id' => urlencode($InvoiceID)
		);
		
		$fields_string = "";
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');

		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $APIUrl);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
			
		$result = curl_exec($ch);
		$response = curl_getinfo( $ch );
		curl_close($ch);
		
		if($response['http_code'] != 200)
			return false;
		
		return $result;
	}

}
