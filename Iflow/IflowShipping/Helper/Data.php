<?php

namespace Iflow\IflowShipping\Helper;

class Data
{
    public static function log($message, $fileName = 'iflow_shipping.log')
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/' . $fileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }
}