<?php
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2020 Drubu (https://www.drubu.net)
 * @package Iflow_IflowShipping
 */


namespace Iflow\IflowShipping\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;

/**
 * Class AbstractOrder
 */
abstract class AbstractOrder extends Action
{
    const ADMIN_RESOURCE = 'Iflow_IflowShipping::iflow_operations';

    /**
     * @var \Magento\Backend\Model\View\Result\ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Magento\Framework\Indexer\IndexerRegistry
     */
    protected $indexerRegistry;

    public function __construct(
        Context $context,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory,
        \Magento\Framework\Registry $registry
    ) {
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->registry = $registry;
    }

    /**
     * @param \Magento\Backend\Model\View\Result\Page $resultPage
     * @return \Magento\Backend\Model\View\Result\Page
     */
    protected function initPage($resultPage)
    {
        $resultPage->setActiveMenu('Iflow_IflowShipping::iflow_operations_list');

        return $resultPage;
    }
}
