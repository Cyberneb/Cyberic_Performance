<?php

namespace Cyberic\Performance\Model;

use Magento\Framework\Model\AbstractModel;

class JsBundle extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\JsBundle::class);
    }
}
