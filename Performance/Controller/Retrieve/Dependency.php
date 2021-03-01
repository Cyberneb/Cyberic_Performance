<?php
/**
 * @noinspection PhpDeprecationInspection
 * @noinspection PhpUndefinedClassInspection
 */

namespace Cyberic\Performance\Controller\Retrieve;

use Cyberic\Performance\Model\JsBundleFactory;
use Cyberic\Performance\Model\ResourceModel\JsBundle as JsBundleResourceModel;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Dependency extends Action implements CsrfAwareActionInterface
{
    /**
     * @var SerializerInterface
     */
    private $jsonSerializer;

    /** @var  JsonFactory */
    private $jsonFactory;

    /**
     * @var JsBundleFactory
     */
    private $jsBundleFactory;

    /**
     * @var JsBundleResourceModel
     */
    private $jsBundleResourceModel;

    /** @var  Http */
    protected $_request;

    /**
     * @param Context $context
     * @param SerializerInterface $jsonSerializer
     * @param JsonFactory $jsonFactory
     * @param JsBundleFactory $jsBundleFactory
     * @param JsBundleResourceModel $jsBundleResourceModel
     */
    public function __construct(
        Context $context,
        SerializerInterface $jsonSerializer,
        JsonFactory $jsonFactory,
        JsBundleFactory $jsBundleFactory,
        JsBundleResourceModel $jsBundleResourceModel
    ) {
        parent::__construct($context);
        $this->jsonSerializer = $jsonSerializer;
        $this->jsonFactory = $jsonFactory;
        $this->jsBundleFactory = $jsBundleFactory;
        $this->jsBundleResourceModel = $jsBundleResourceModel;
    }

    /**
     * Receive the RequireJs dependency to save in the DB.
     *
     * @return Json
     * @noinspection PhpUndefinedMethodInspection
     */
    public function execute(): Json
    {
        $data = $this->jsonSerializer->unserialize($this->_request->getContent());
        if (isset($data['deps'])) {
            foreach ($data['deps'] as $dependencyPath) {
                if (strpos($dependencyPath, 'js/bundle/') !== false) {
                    continue;
                }
                $dependencyPath = $this->setDependencyPath($dependencyPath);
                $dependencyName = $this->setDependencyName($dependencyPath, $data['paths']);
                if (empty($dependencyName)) {
                    continue;
                }
                $jsBundle = $this->jsBundleFactory->create();
                $jsBundle->setData('page_type', $data['route']);
                $jsBundle->setData('dependency_name', $dependencyName);
                $jsBundle->setData('dependency_path', $dependencyPath);
                try {
                    $this->jsBundleResourceModel->save($jsBundle);
                } catch (Exception $exception) {
                    // insert ignore
                }
            }
        }

        return $this->jsonFactory->create()->setData([
            'result' => true
        ]);
    }

    /**
     * @param $dependencyPath
     * @return string
     */
    protected function setDependencyPath($dependencyPath): string
    {
        $search = ['.min.js','jquery/jquery.storageapi','ui/template'];
        $replace = ['.js','jquery/jquery.storageapi.min','Magento_Ui/templates'];

        return str_replace ($search, $replace, $dependencyPath);
    }

    /**
     * @param $dependencyPath
     * @param $paths
     * @return string
     */
    protected function setDependencyName($dependencyPath, $paths): string
    {
        if (substr($dependencyPath, -5) == '.html') {
            return 'text!' . $dependencyPath;
        }
        if (substr($dependencyPath, -3) == '.js') {
            $modulePath = substr($dependencyPath, 0, -3);
            foreach ($paths as $alias => $moduleName) {
                if ($modulePath == $moduleName) {
                    return str_replace(['text'], ['mage/requirejs/text'], $alias);
                }
            }
            return str_replace(['jquery/ui-modules'], ['jquery-ui-modules'], $modulePath);
        }

        return '';
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
