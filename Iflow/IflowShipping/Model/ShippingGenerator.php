<?php


namespace Iflow\IflowShipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Convert\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Shipping\Model\ShipmentNotifier;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Magento\Shipping\Model\Shipping\LabelsFactory;
use Magento\Shipping\Model\CarrierFactory;
use Magento\Sales\Model\Order\Item;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ShippingGenerator
{
    /**
     * @var Order
     */
    protected $_convertOrder;

    /**
     * @var ShipmentNotifier
     */
    protected $_shipmentNotifier;

    /**
     * @var TrackFactory
     */
    protected $_trackFactory;
    /**
     * @var CarrierFactory
     */
    private $_carrierFactory;
    /**
     * @var LabelGenerator
     */
    private $_labelGenerator;
    /**
     * @var LabelsFactory
     */
    private $_labelFactory;
    /**
     * @var ScopeConfigInterface
     */
    private $_scopeConfig;

    /**
     * @param Order $convertOrder
     * @param ShipmentNotifier $shipmentNotifier
     * @param TrackFactory $trackFactory
     * @param CarrierFactory $carrierFactory
     * @param LabelGenerator $labelGenerator
     * @param LabelsFactory $labelFactory
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Order $convertOrder,
        ShipmentNotifier $shipmentNotifier,
        TrackFactory $trackFactory,
        CarrierFactory $carrierFactory,
        LabelGenerator $labelGenerator,
        LabelsFactory $labelFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_convertOrder = $convertOrder;
        $this->_shipmentNotifier = $shipmentNotifier;
        $this->_trackFactory = $trackFactory;
        $this->_carrierFactory = $carrierFactory;
        $this->_labelGenerator = $labelGenerator;
        $this->_labelFactory = $labelFactory;
        $this->_scopeConfig = $scopeConfig;

    }

    /**
     * @description Efectiviza la generación del envío siempre y cuando dicha orden
     * esté habilitada para ser enviada.
     * @param $order
     * @return boolean
     */
    public function generateShipping($order)
    {
        $response = [];
        $result = true;
        if($order->hasShipments()){
            $carrier = $this->_carrierFactory->create($order->getShippingMethod(true)->getCarrierCode());
            if ($carrier->isShippingLabelsAvailable()) {
                $itemsArray = [];
                $pesoTotal = 0;
                $valorTotal = 0;

                foreach ($order->getShipmentsCollection() as $shipment){
                    foreach ($shipment->getItemsCollection() as $shipmentItem){
                        $qtyShipped = $shipmentItem->getQty();
                        $valorTotal += $qtyShipped * $shipmentItem->getPrice();
                        $pesoTotal += $qtyShipped * $shipmentItem->getWeight();

                        $itemsArray[$shipmentItem->getId()] = [
                            'qty' => $qtyShipped,
                            'customs_value' => $shipmentItem->getPrice(),
                            'price' => $shipmentItem->getPrice(),
                            'name' => $shipmentItem->getName(),
                            'weight' => $shipmentItem->getWeight(),
                            'product_id' => $shipmentItem->getProductId(),
                            'order_item_id' => $shipmentItem->getId()
                        ];
                    }

                    $packages = [
                        1 => [
                            'items' => $itemsArray,
                            'params' => [
                                'weight' => $pesoTotal,
                                'container' => 1,
                                'customs_value' => $valorTotal
                            ]
                        ]];
                    try {
                        $this->generateLabel($shipment,$packages);
                        $shipment->save();
                    } catch (LocalizedException $e) {
                        \Iflow\IflowShipping\Helper\Data::log('ShippingGenerator::generateLabel::Exception::' . $e->getMessage());
                        $result = false;
                    }
                }
            }
            else{
                $result = false;
            }
        }
        else {
            if (!$order->canShip()) {
                $result = false;
            }
            else {
                $convertOrder = $this->_convertOrder;
                $shipment = $convertOrder->toShipment($order);

                $valorTotal = $pesoTotal = 0;
                $itemsArray = [];

                foreach ($order->getAllItems() as $orderItem) {
                    /**
                     * @var $orderItem Item
                     * @var $shipmentItem Shipment\Item
                     */
                    if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                        continue;
                    }
                    $qtyShipped = $orderItem->getQtyToShip();
                    $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                    $valorTotal += $qtyShipped * $orderItem->getPrice();
                    $pesoTotal += $qtyShipped * $orderItem->getWeight();

                    $itemsArray[$orderItem->getId()] = [
                        'qty' => $qtyShipped,
                        'customs_value' => $orderItem->getPrice(),
                        'price' => $orderItem->getPrice(),
                        'name' => $orderItem->getName(),
                        'weight' => $orderItem->getWeight(),
                        'product_id' => $orderItem->getProductId(),
                        'order_item_id' => $orderItem->getId()
                    ];
                    $shipment->addItem($shipmentItem);
                }
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);

                try {
                    $this->generateLabel($shipment,
                        [
                            1 => [
                                'items' => $itemsArray,
                                'params' => [
                                    'weight' => $pesoTotal,
                                    'container' => 1,
                                    'customs_value' => $valorTotal
                                ]
                            ]]);
//                    $response = $this->_labelFactory->create()->requestToShipment($shipment);
                    $shipment->save();
                    $shipment->getOrder()->save();

                    $this->_shipmentNotifier->notify($shipment);
                } catch (\Exception $e) {
                    \Iflow\IflowShipping\Helper\Data::log('ShippingGenerator::generateShipping::Exception::' . $e->getMessage());
                    $result = false;
                }
            }
        }
        return $result;
    }

    private function generateLabel($shipment,$packages = array()){
        $order = $shipment->getOrder();
        $carrier = $this->_carrierFactory->create($order->getShippingMethod(true)->getCarrierCode());
        if (!$carrier->isShippingLabelsAvailable()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Shipping labels is not available.'));
        }
        $shipment->setPackages($packages);
        $response = $this->_labelFactory->create()->requestToShipment($shipment);
        if ($response->hasErrors()) {
            throw new \Magento\Framework\Exception\LocalizedException(__($response->getErrors()));
        }
        if (!$response->hasInfo()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Response info is not exist.'));
        }
        $labelsContent = [];
        $trackingNumbers = [];
        $info = $response->getInfo();
        foreach ($info as $inf) {
            if (!empty($inf['tracking_number']) && !empty($inf['label_content'])) {
                $labelsContent[] = $inf['label_content'];
                if(!empty($inf['description'])) {
                    $tracking_info = array(
                        'tracking_number' => $inf['tracking_number'],
                        'description' => $inf['description'],
                    );
                    $trackingNumbers[] = $tracking_info;

                }
            }
        }
        $outputPdf = $this->_labelGenerator->combineLabelsPdf($labelsContent);
        $shipment->setShippingLabel($outputPdf->render());
        $carrierCode = $carrier->getCarrierCode();
        $carrierTitle = $this->_scopeConfig->getValue(
            'carriers/' . $carrierCode . '/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $shipment->getStoreId()
        );
        if (!empty($trackingNumbers)) {
            $this->addTrackingNumbersToShipmentWithDesc($shipment, $trackingNumbers, $carrierCode, $carrierTitle);
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param array $trackingNumbers
     * @param string $carrierCode
     * @param string $carrierTitle
     *
     * @return void
     */
    private function addTrackingNumbersToShipmentWithDesc(
        \Magento\Sales\Model\Order\Shipment $shipment,
        $trackingNumbers,
        $carrierCode,
        $carrierTitle
    ) {
        foreach ($trackingNumbers as $inf) {

            $shipment->addTrack(
                $this->_trackFactory->create()
                    ->setNumber($inf['tracking_number'])
                    ->setCarrierCode($carrierCode)
                    ->setDescription($inf['tracking_number'])
                    ->setTitle($carrierTitle)
            );
        }
    }
}
