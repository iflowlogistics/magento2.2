<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Iflow\IflowShipping\Controller\Adminhtml\Order;

use Iflow\IflowShipping\Helper\Data;
use Iflow\IflowShipping\Model\ShippingGenerator;
use Iflow\IflowShipping\Utils\EmailSender;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class massCreateShipping extends Action /*implements HttpPostActionInterface*/
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Iflow_IflowShipping::iflow_operations';

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;
    /**
     * @var Filter
     */
    private $filter;
    /**
     * @var ShippingGenerator
     */
    private $shippingGenerator;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ShippingGenerator $shippingGenerator
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ShippingGenerator $shippingGenerator
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->filter = $filter;
        $this->shippingGenerator = $shippingGenerator;
    }

    /**
     * Send confirmation email of selected orders
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $countOrdersCreated = 0;
        $erroresDeGeneracion = [];
        foreach ($collection->getItems() as $order) {
            try {
                if($this->shippingGenerator->generateShipping($order)){
                    $countOrdersCreated++;
                }
                else{
                    $erroresDeGeneracion[] = $order->getIncrementId();
                }
            }catch (\Exception $e){
                $this->messageManager->addErrorMessage(__('Hubo un error imprevisto generando los envios. ' .  $e->getMessage()));
            }
        }

        $countNonSended = $collection->count() - $countOrdersCreated;
        if ($countNonSended && $countOrdersCreated) {
            $this->messageManager->addErrorMessage(__('%1 envio(s) generado(s) sin exito.', $countNonSended));
            $this->messageManager->addErrorMessage(__('Números de pedidos erroneos: %1', implode(',',$erroresDeGeneracion)));
        } elseif ($countNonSended) {
            $this->messageManager->addErrorMessage(__('No se genero ningun envio con exito. Revise el archivo log para mas detalle.'));
        }

        if ($countOrdersCreated) {
            $this->messageManager->addSuccessMessage(__('%1 envio(s) generado(s) con exito.', $countOrdersCreated));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
