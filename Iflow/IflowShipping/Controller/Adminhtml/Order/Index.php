<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2019 Amasty (https://www.amasty.com)
 * @package Amasty_ElasticSearch
 */


namespace Iflow\IflowShipping\Controller\Adminhtml\Order;

use Magento\Framework\Controller\ResultFactory;

class Index extends AbstractOrder
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $this->initPage($resultPage)
            ->getConfig()->getTitle()->prepend(__('Generación masiva de envíos'));

        return $resultPage;
    }
}
