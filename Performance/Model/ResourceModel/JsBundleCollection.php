<?php

namespace Cyberic\Performance\Model\ResourceModel;

use Cyberic\Performance\Model;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class JsBundleCollection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Model\JsBundle::class, Model\ResourceModel\JsBundle::class);
    }
}
