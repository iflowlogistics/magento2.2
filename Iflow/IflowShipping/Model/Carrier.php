<?php
namespace Iflow\IflowShipping\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
//use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Shipment\Request;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Constraint\IsFalse;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = 'iflow';

    /**
     * Whether this carrier has fixed rates calculation
     *
     * @var bool
     */
    protected $_isFixed = false;

    /**
     * Container types that could be customized
     *
     * @var string[]
     */
    protected $_customizableContainerTypes = ['CUSTOM'];
    /**
     * @var LoggerInterface
     */
    protected $_logger;


    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        //\Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        //\Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        //\Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        //\Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,

        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->_rateFactory = $rateFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_logger = $logger;
        // \Iflow\IflowShipping\Helper\Data::log('Iflow Carrier constructed');
        // $debug = [
        //     'active' => $this->getConfigFlag('active'),
        //     'store_id' => $this->getConfigData('store_id'),
        //     'methods' => $this->getAllowedMethods(),
        // ];
        // \Iflow\IflowShipping\Helper\Data::log(var_export($debug, true));
    }

    public function collectRates(RateRequest $request)
    {  
        \Iflow\IflowShipping\Helper\Data::log(json_encode($request->getData()));
        \Iflow\IflowShipping\Helper\Data::log('collectRates call');
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        $shippingPrice = $this->getConfigData('price');
        $packages = array();
        $shippment_data = array();
        foreach($request->getAllItems() as $item){
            /*
                \Iflow\IflowShipping\Helper\Data::log('item data');
                \Iflow\IflowShipping\Helper\Data::log(json_encode($item->getData()));
                \Iflow\IflowShipping\Helper\Data::log('item id');
                \Iflow\IflowShipping\Helper\Data::log(json_encode($item->getProductId()));
            */
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $product = $objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());
            if ($product->getTypeId() == "simple") {
                /*
                    \Iflow\IflowShipping\Helper\Data::log('Product data');
                    \Iflow\IflowShipping\Helper\Data::log(json_encode($product->getData()));
                */
                $itemHeight = $product->getTsDimensionsHeight();
                $itemLength = $product->getTsDimensionsLength();
                $itemWidth = $product->getTsDimensionsWidth();
                $itemWeight = $product->getWeight();
                $price = $item->getPrice();
                if ((int)$price== 0){
                    $price = (int)$product->getPrice();
                }
                \Iflow\IflowShipping\Helper\Data::log("PRECIO ".$price);
                $qty = $item->getQty();
                for($i =0 ; $i < $item->getQty(); $i++) {
                    $packages[] = array(
                        'width' => (int)$itemWidth,
                        'height' => (int)$itemHeight,
                        'length' => (int)$itemLength,
                        'real_weight' => $itemWeight,// * 1000, //Quitado el *1000, manda la cotizacion en kg pero la impresion de paquetes en g;
                        'gross_price' => $price,
                    );
                }
                /*
                    \Iflow\IflowShipping\Helper\Data::log('item heigth');
                    \Iflow\IflowShipping\Helper\Data::log($itemHeight);
                    \Iflow\IflowShipping\Helper\Data::log('item Length');
                    \Iflow\IflowShipping\Helper\Data::log($itemLength);
                    \Iflow\IflowShipping\Helper\Data::log('item Width');
                    \Iflow\IflowShipping\Helper\Data::log($itemWidth);
                    \Iflow\IflowShipping\Helper\Data::log('item Weight');
                    \Iflow\IflowShipping\Helper\Data::log($itemWeight);
                */
            }
        }
        $province = $request->getDestRegionCode();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        $customer = $customerSession->getCustomer();
        if ($customer) {
            $shippingAddress = $customer->getDefaultShippingAddress();
            if ($shippingAddress) {

                // \Iflow\IflowShipping\Helper\Data::log("POSTCODE:: ".$shippingAddress->getRegionCode());
                $province = $shippingAddress->getRegionCode();

            }
        }
        
        $zip = $request->getDestPostcode();
        $shipment_data = array(
            'zip_code' => $zip,
            'province' => $province,
            'packages' => $packages,
            'delivery_mode' => 1,
        );
        \Iflow\IflowShipping\Helper\Data::log(json_encode($shipment_data));
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $price_result_json = $this->_getShipmentRate($shipment_data);

        $price_result  = json_decode($price_result_json, TRUE);
        \Iflow\IflowShipping\Helper\Data::log("Price result");
        \Iflow\IflowShipping\Helper\Data::log($price_result_json);
        
        if(isset($price_result["results"])) {
            if(isset($price_result["results"]["final_value"])) {
                $shippingPrice = $price_result["results"]["final_value"];
            }
        }
		if($request->getFreeShipping()){
            $shippingPrice = 0;
            \Iflow\IflowShipping\Helper\Data::log("Is free shipping");
        }								
        
        $result = $this->_rateFactory->create();
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));
        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $result->append($method);
        \Iflow\IflowShipping\Helper\Data::log('collectRates result');

        return $result;
    }

    /**
     * Do request to shipment
     *
     * @param Request $request
     * @return \Magento\Framework\DataObject
     */
    public function requestToShipment($request)
    {
        \Iflow\IflowShipping\Helper\Data::log('requestToShipment call');
        $packages = $request->getPackages();
        if (!is_array($packages) || !$packages) {
            throw new LocalizedException(__('No packages for request'));
        }
        if ($request->getStoreId() != null) {
            $this->setStore($request->getStoreId());
        }
        $data = [];
        foreach ($packages as $packageId => $package) {
            $request->setPackageId($packageId);
            \Iflow\IflowShipping\Helper\Data::log("PARAMETROS".json_encode($package['params']));
            $request->setPackagingType($package['params']['container']);
            $request->setPackageWeight($package['params']['weight']);
            $request->setPackageParams(new \Magento\Framework\DataObject($package['params']));
            $items = $package['items'];
            foreach ($items as $itemid => $item) {
                // \Iflow\IflowShipping\Helper\Data::log("ITEM: ".$itemid.$item['weight']);
                \Iflow\IflowShipping\Helper\Data::log("WEIGHT: ".($item['weight']*1000));
                $items[$itemid]['weight'] = $item['weight']*1000;                      
            }
            \Iflow\IflowShipping\Helper\Data::log("ITEMS: ".print_r($package['items'],true));
            $request->setPackageItems($items);

            $result = $this->_doShipmentRequest($request);

            if ($result->hasErrors()) {
                \Iflow\IflowShipping\Helper\Data::log('Result has errors');
                $this->rollBack($data);
                break;
            } else {
                $data[] = [
                    'tracking_number' => $result->getTrackingNumber(),
                    'label_content' => $result->getLabelContent(),
                    'description' => $result->getDescription(),
                ];
            }
            \Iflow\IflowShipping\Helper\Data::log('Description ' . $result->getDescription());
            if (!isset($isFirstRequest)) {
                \Iflow\IflowShipping\Helper\Data::log('Setting Master Tracking Id: ' . $result->getTrackingNumber());
                $request->setMasterTrackingId($result->getTrackingNumber());
                $isFirstRequest = false;
            }
        }

        $response = new \Magento\Framework\DataObject(['info' => $data]);
        if ($result->getErrors()) {
            $response->setErrors($result->getErrors());
        }

        return $response;
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        \Iflow\IflowShipping\Helper\Data::log('_doShipmentRequest call');
        //$this->_prepareShipmentRequest($request);
        $payload = $this->_getRequestPayload($request);
        $payloadJson = json_encode($payload);

        $storeId = $this->getConfigData('store_id');
        $url = $this->getConfigFlag(
            'sandbox_mode'
        ) ? $this->getConfigData('sandbox_webservices_url') : $this->getConfigData('production_webservices_url');
        //$storeId = '569cb42fa9c8d4d3cbd16464';
        $url .= 'magento/orders/' . $storeId . '/create';
        \Iflow\IflowShipping\Helper\Data::log('Api url: '.$url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $cinfo = curl_getinfo($ch);
        $error = false;
        \Iflow\IflowShipping\Helper\Data::log('CURL RESPONSE: ' . $response);
        \Iflow\IflowShipping\Helper\Data::log('CURL CINFO');
        \Iflow\IflowShipping\Helper\Data::log(json_encode($cinfo));
        if ($response === false) {
            $error = "No cURL data returned for $url [". $cinfo['http_code']. "]";
            if (curl_error($ch)) {
                $error .= "\n". curl_error($ch);
            }
        } else {
            if (! in_array($cinfo['http_code'], [200, 201])) {
                $error = "API CODE {$cinfo['http_code']}: $response";
            } else {
                $result = $response;
                $resultJson = json_decode($response);
            }
        }
        curl_close($ch);

        if ($error) {
            \Iflow\IflowShipping\Helper\Data::log('API error: ' . $error);
            throw new \Magento\Framework\Exception\LocalizedException(__('Error contacting API: '.$response));
        }

        \Iflow\IflowShipping\Helper\Data::log('API response: ' . $result);
        //throw new \Magento\Framework\Exception\LocalizedException(__('API testing'));
        
        if (! $resultJson->success) {
            \Iflow\IflowShipping\Helper\Data::log('API unsuccessful response: ' . $result);
            throw new \Magento\Framework\Exception\LocalizedException(__('Error creating shipment'));
        } else {
            $trackingNumber = '';
            $labelContent = '';
            try {
                $trackingNumber = $resultJson->results->tracking_id;
                $shipping = $resultJson->results->shippings[0];
                $labelUrl = $shipping->print_url;
                // $labelUrl = 'http://test-api.iflow21.com/api/order/print/436364/T00000000098.pdf';
                $labelContent = $this->_getLabelContentFromUrl($labelUrl);
            } catch (Exception $e) {
                \Iflow\IflowShipping\Helper\Data::log('API response parsing error: ' . $e->getMessage());
            }
            
            return new \Magento\Framework\DataObject([
                'tracking_number' => $trackingNumber,
                'label_content' => $labelContent,
                'description' => $labelUrl
                ]);
        }
    }

    protected function _getLabelContentFromUrl($url)
    {
        // $pdf = \Zend_Pdf::load($url); // local only
        // $label_content = $pdf->render();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $cinfo = curl_getinfo($ch);
        $error = false;
        
        if ($response === false) {
            $error = "No cURL data returned for $url [". $cinfo['http_code']. "]";
            if (curl_error($ch)) {
                $error .= "\n". curl_error($ch);
            }
        } else {
            if ($cinfo['http_code'] != 200) {
                $error = "API CODE {$cinfo['http_code']}: $response";
            } else {
                $result = $response;
            }
        }
        curl_close($ch);

        if ($error) {
            \Iflow\IflowShipping\Helper\Data::log('Error retrieving Labels: ' . $error);
            throw new \Magento\Framework\Exception\LocalizedException(__('Error retrieving Labels'));
        }
        return $result;
    }
    
    /**
     * Form Object with appropriate structure for shipment request
     *
     * @param \Magento\Framework\DataObject $request
     * @return \stdClass
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _getRequestPayload(\Magento\Framework\DataObject $request)
    {
        \Iflow\IflowShipping\Helper\Data::log('_getRequestPayload call');
        // \Iflow\IflowShipping\Helper\Data::log(var_export($request, true));
        $packageParams = $request->getPackageParams();
        //\Iflow\IflowShipping\Helper\Data::log('Request packageParams: ' . $packageParams->toJson());


	    \Iflow\IflowShipping\Helper\Data::log('Request debug: ' . $request->toJson());

	    $piso = $request->getOrderShipment()->getOrder()->getShippingAddress()->getPiso();
	    $departamento = $request->getOrderShipment()->getOrder()->getShippingAddress()->getDepartamento();
	    $observaciones = $request->getOrderShipment()->getOrder()->getShippingAddress()->getObservaciones();
        $datosAdicionalesArray = array();
        $datosAdicionalesArray[] = (!empty($piso)) ? 'Piso: ' . $piso : '';
        $datosAdicionalesArray[] = (!empty($departamento)) ? 'Departamento: ' . $departamento : '';
        $datosAdicionalesArray[] = (!empty($observaciones)) ? 'Observaciones: ' . $observaciones : '';

        $datosAdicionales = trim(implode(', ',$datosAdicionalesArray),', ');

        $payload = new \stdClass;
        $payload->softlightUser = $this->getConfigData('softlightusername');
        $payload->softlightPassword = $this->getConfigData('softlightpassword');
        $payload->orderId = $request->getOrderShipment()->getOrder()->getIncrementId();
        $payload->packageId = $request->getPackageId(); // not req by SL
        $payload->name = $request->getRecipientContactPersonFirstName(); // not available separately
        $payload->lastname = $request->getRecipientContactPersonLastName();
        // $payload->documentType = details.order.documentType; // not req by SL
        // $payload->document = details.order.document; // not req by SL
        $payload->email = $request->getRecipientEmail(); // not available in default Magento
        $payload->phone = $request->getRecipientContactPhoneNumber();
        $payload->address = new \stdClass;
        $payload->address->street = $request->getRecipientAddressStreet();
        $payload->address->number = $request->getOrderShipment()->getOrder()->getShippingAddress()->getAltura(); // # not necessarily available
        //$payload->address->addressComplement = $datosAdicionales; // SL "between_1"
        $payload->address->postalCode = $request->getRecipientAddressPostalCode();
        $payload->address->city = $request->getRecipientAddressCity();
        $payload->address->state = 'BUENOS AIRES';//$request->getRecipientAddressStateOrProvinceCode();
        $payload->address->receiverName = $request->getRecipientContactPersonName();
        $payload->address->comments = $datosAdicionales;
        // $payload->address->dockId = ; // not req by SL
        // $payload->courierName = ''; // not req by SL
        // $payload->branchId = ''; // not req by SL
        // $payload->branchName = ''; // not req by SL
        // $payload->branchAddress = ''; // not req by SL
        // $payload->value = ''; // not req by SL
        // $payload->tax = ''; // not req by SL
        // $payload->startDateUtc = ''; // not req by SL
        // $payload->endDateUtc = ''; // not req by SL

        $payload->items = [];

        $packageItems = $request->getPackageItems();

        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        foreach ($packageItems as $itemShipment) {
            $reqItem = new \Magento\Framework\DataObject();
            $reqItem->setData($itemShipment);
            
            $item = new \stdClass;
                
            $item->productId = $reqItem->getProductId();
            $product = $objectManager->get('Magento\Catalog\Model\Product')->load($reqItem->getProductId());
            if($product->getTypeId() == "configurable") {

                
                $_children = $product->getTypeInstance()->getUsedProducts($product);
                foreach ($_children as $child){
                    \Iflow\IflowShipping\Helper\Data::log("Here are your child Product Ids ".$child->getID()."\n");
                    $product = $objectManager->get('Magento\Catalog\Model\Product')->load($child->getID());
            
                    $item->height = $product->getTsDimensionsHeight();
                    $item->width = $product->getTsDimensionsWidth();
                    $item->length = $product->getTsDimensionsLength();
                    if (($item->height + $item->width + $item->length) > 0
                    ){
                        break;
                    }
                }

            }
            else {
                $item->height = $product->getTsDimensionsHeight();
                $item->width = $product->getTsDimensionsWidth();
                $item->length = $product->getTsDimensionsLength();
            }
            
            // \Iflow\IflowShipping\Helper\Data::log('Request item: ' . $reqItem->toJson());
            // \Iflow\IflowShipping\Helper\Data::log("DIMENSIONS LENGTH".$product->toJson());
            $item->name = $reqItem->getName();
            // cubicWeight: item.cubicWeight, // not req by SL
            $item->weight = $reqItem->getWeight();
            $item->quantity = $reqItem->getQty();
            $item->price = $reqItem->getPrice();
            // tax: item.tax // not req by SL
            $payload->items[] = $item;
        }
    
        \Iflow\IflowShipping\Helper\Data::log('Request data: ' . json_encode($payload));
        // throw new \Magento\Framework\Exception\LocalizedException(__('Payload debug'));

        return $payload;

    //     if ($request->getReferenceData()) {
    //         $referenceData = $request->getReferenceData() . $request->getPackageId();
    //     } else {
    //         $referenceData = 'Order #' .
    //             $request->getOrderShipment()->getOrder()->getIncrementId() .
    //             ' P' .
    //             $request->getPackageId();
    //     }
    //     $packageParams = $request->getPackageParams();
    //     $customsValue = $packageParams->getCustomsValue();
    //     $height = $packageParams->getHeight();
    //     $width = $packageParams->getWidth();
    //     $length = $packageParams->getLength();
    //     $weightUnits = $packageParams->getWeightUnits() == \Zend_Measure_Weight::POUND ? 'LB' : 'KG';

    //     // set dimensions
    //     if ($length || $width || $height) {
    //         $requestClient['RequestedShipment']['RequestedPackageLineItems']['Dimensions'] = [
    //             'Length' => $length,
    //             'Width' => $width,
    //             'Height' => $height,
    //             'Units' => $packageParams->getDimensionUnits() == \Zend_Measure_Length::INCH ? 'IN' : 'CM',
    //         ];
    //     }

    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        \Iflow\IflowShipping\Helper\Data::log('getAllowedMethods call');
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return boolean
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    public function getTrackingInfo($tracking)
    {
    /*
        $track = Mage::getModel('shipping/tracking_result_status');
        $track->setUrl('http://www.example.com/' . $tracking)
            ->setTracking($tracking)
            ->setCarrierTitle($this->getConfigData('name'));
        return $track;
        */
        var_dump($tracking);

        $result = $this->getTracking($tracking);
        var_dump($result);
        if($result instanceof Mage_Shipping_Model_Tracking_Result){
            if ($trackings = $result->getAllTrackings()) {
                return $trackings[0];
            }
        }
        elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }
    /**
     * Check if carrier has shipping label option available
     *
     * @return boolean
     */
    public function isShippingLabelsAvailable()
    {
        return true;
    }

    /**
     * Return delivery confirmation types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDeliveryConfirmationTypes(\Magento\Framework\DataObject $params = null)
    {
        return [
            'NO_SIGNATURE_REQUIRED' => __('Not Required'),
        ];
    }

    /**
     * Return container types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getContainerTypes(\Magento\Framework\DataObject $params = null)
    {
        return $this->_getAllowedContainers($params);
    }

    /**
     * Get allowed containers of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array|bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getAllowedContainers(\Magento\Framework\DataObject $params = null)
    {
        return $containersAll = $this->getContainerTypesAll();
    }

    /**
     * Return all container types of carrier
     *
     * @return array|bool
     */
    public function getContainerTypesAll()
    {
        return ['PAQUETE' => 'PAQUETE'];
    }

    /**
     * Processing additional validation to check if carrier applicable.
     *
     * @param \Magento\Framework\DataObject $request
     * @return $this|bool|\Magento\Framework\DataObject
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function proccessAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        \Iflow\IflowShipping\Helper\Data::log('proccessAdditionalValidation call');
        return $this;
    }

    protected function _getShipmentRate($payload)
    {
        \Iflow\IflowShipping\Helper\Data::log('_doShipmentRequest call');
        $payload['softlightUser'] = $this->getConfigData('softlightusername');
        $payload['softlightPassword'] = $this->getConfigData('softlightpassword');
        $payloadJson = json_encode($payload);

        $storeId = $this->getConfigData('store_id');
        $url = $this->getConfigFlag(
            'sandbox_mode'
        ) ? $this->getConfigData('sandbox_webservices_url') : $this->getConfigData('production_webservices_url');
        //$storeId = '569cb42fa9c8d4d3cbd16464';
        $url .= 'magento/orders/getrate';
        \Iflow\IflowShipping\Helper\Data::log('Api url: '.$url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $cinfo = curl_getinfo($ch);
        $error = false;
        \Iflow\IflowShipping\Helper\Data::log('CURL RESPONSE: ' . $response);
        \Iflow\IflowShipping\Helper\Data::log('CURL CINFO');
        //\Iflow\IflowShipping\Helper\Data::log(json_encode($cinfo));
        if ($response === false) {
            $error = "No cURL data returned for $url [". $cinfo['http_code']. "]";
            if (curl_error($ch)) {
                $error .= "\n". curl_error($ch);
            }
        } else {
            if (! in_array($cinfo['http_code'], [200, 201])) {
                $error = "API CODE {$cinfo['http_code']}: $response";
            } else {
                $result = $response;
                $resultJson = json_decode($response);
            }
        }
        curl_close($ch);

        if ($error) {
            \Iflow\IflowShipping\Helper\Data::log('API error: ' . $error);
        }

        \Iflow\IflowShipping\Helper\Data::log('API response: ' . $response);
        //throw new \Magento\Framework\Exception\LocalizedException(__('API testing'));
        
        return $response;
    }
}
