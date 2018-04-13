<?php
namespace SilverStoreS\Btcz\Block\Index;

//use Magento\Sales\Model\Order;

class Index extends \Magento\Framework\View\Element\Template {
	
	protected $_checkoutSession;
    protected $customerSession;
    protected $_orderFactory;
	private $_objectManager;
	protected $_resource;
    
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
		\Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Registry $registry,
		\Magento\Framework\ObjectManagerInterface $objectmanager,
		\Magento\Framework\App\ResourceConnection $resource,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
       
        $this->_checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
		$this->_scopeConfig = $scopeConfig;
		$this->_objectManager = $objectmanager;
		$this->_resource = $resource;
		parent::__construct($context, $data);
    }
	
	protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }
	
	public function getOrder($id)
    {
        return  $this->_order = $this->_orderFactory->create()->loadByIncrementId($id);
    }
	
	public function getSecretKey()
    {
        return $this->_scopeConfig->getValue('payment/btcz/secret_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function decryptIt( $q ) {
		$cryptKey  = $this->getSecretKey();
		$qDecoded      = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, md5( $cryptKey ), base64_decode( $q ), MCRYPT_MODE_CBC, md5( md5( $cryptKey ) ) ), "\0");
		
		return( $qDecoded );
	}
	
	public function changeState( $jsondata ) {
				
		if($jsondata){
			$connection = $this->_resource->getConnection();			
			$tableNamepingback = $this->_resource->getTableName('btcz_pingback');
			$sql = 'INSERT INTO `' . $tableNamepingback . '` (json) VALUES (:json)';
			$connection->query($sql, array('json' => $jsondata));
			
			$data = json_decode($jsondata);
			$this->ProcessPingback($data);
		};
		
	}
	
	function ProcessPingback( $data ) {
		
		
		$connection = $this->_resource->getConnection();
		$tableName = $this->_resource->getTableName('btcz');
		
		$status_after = $this->_scopeConfig->getValue('payment/btcz/after_complete', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$orderloaded = $this->getOrder($data->invoicename);
		
		if($data->state == 5) { //success
					
			$orderloaded->setState($status_after)->setStatus($status_after);
			$orderloaded->addStatusHistoryComment('Invoice '.$data->url_id.' was payed successfully. JSON: https://btcz.in/api/process?f=getinfo&id='.$data->url_id);
			$orderloaded->save();
			$sqlupt = "UPDATE `". $tableName ."` SET `processed` = '1' WHERE `increment_id` = ".$data->invoicename;
			$connection->query($sqlupt);
						
		} else if($data->state == 2) { //expired
					
			$orderloaded->setState("closed")->setStatus("closed");
			$orderloaded->addStatusHistoryComment('Invoice '.$data->url_id.' was payed not payed ['.$data->strState.']. JSON: https://btcz.in/api/process?f=getinfo&id='.$data->url_id);
			$orderloaded->save();
			$sqlupt = "UPDATE `". $tableName ."` SET `processed` = '1' WHERE `increment_id` = ".$data->invoicename;
			$connection->query($sqlupt);
						
		};
			
	}
	
	public function noData() {
		
		$connection = $this->_resource->getConnection();			
		$tableNamepingback = $this->_resource->getTableName('btcz_pingback');
		$sql = 'INSERT INTO `' . $tableNamepingback . '` (json) VALUES ("-- no data --")';
		$connection->query($sql);
		//die();
		
	}



}