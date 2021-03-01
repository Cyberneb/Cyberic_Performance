<?php
/**
 * @author Eric Allatt <eric@cyberic.net>
 * @link https://www.cyberic.net
 */

namespace Cyberic\Performance\Block;

use Magento\Framework\View\Element\Template;

class RetrieveDependency extends Template
{
    /**
     * @var Template\Context
     */
    private $context;

    /**
     * RequireJsDataCollector constructor.
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        array $data = []
    ) {
        $this->context = $context;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getRequestUrl()
    {
        return $this->getUrl('performance/retrieve/dependency');
    }

    /**
     * @return string
     */
    public function getRouteName()
    {
        return $this->context->getRequest()->getRouteName();
    }
}
