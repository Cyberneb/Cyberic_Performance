<?php

namespace Cyberic\Performance\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class JsBundle extends AbstractDb
{
    /**
     * Define main table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('cyberic_js_bundle', 'js_bundle_id');
    }

    /**
     * Initialize array fields
     *
     * @return $this
     */
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = [
            ['field' => ['page_type', 'dependency_path'], 'title' => __('JS dependency for specific page type')],
        ];
        return $this;
    }
}
