<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Iflow\IflowShipping\Model\Shipping;


/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LabelGenerator extends \Magento\Shipping\Model\Shipping\LabelGenerator
{


    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param RequestInterface $request
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function create(\Magento\Sales\Model\Order\Shipment $shipment, \Magento\Framework\App\RequestInterface $request)
    {
        $order = $shipment->getOrder();
        $carrier = $this->carrierFactory->create($order->getShippingMethod(true)->getCarrierCode());
        if (!$carrier->isShippingLabelsAvailable()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Shipping labels is not available.'));
        }
        $shipment->setPackages($request->getParam('packages'));
        $response = $this->labelFactory->create()->requestToShipment($shipment);
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
        $outputPdf = $this->combineLabelsPdf($labelsContent);
        $shipment->setShippingLabel($outputPdf->render());
        $carrierCode = $carrier->getCarrierCode();
        $carrierTitle = $this->scopeConfig->getValue(
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
                $this->trackFactory->create()
                    ->setNumber($inf['tracking_number'])
                    ->setCarrierCode($carrierCode)
                    ->setDescription($inf['tracking_number'])
                    ->setTitle($carrierTitle)
            );
        }
    }
}
