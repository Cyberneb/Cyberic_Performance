<?php
/**
 * @noinspection PhpUnusedParameterInspection
 * @noinspection PhpUnused
 */
/**
 * @author Eric Allatt <eric@cyberic.net>
 * @link https://www.cyberic.net
 */

namespace Cyberic\Performance\Plugin\Model;

use Magento\Framework\App;
use Magento\Framework\View\Asset\File;
use Magento\RequireJs\Model\FileManager;

class FileManagerPlugin
{
    /**
     * @var App\State
     */
    private $appState;

    /**
     * @var App\Request\Http
     */
    protected $request;

    public function __construct(
        App\State $appState,
        App\RequestInterface $request
    ) {
        $this->appState = $appState;
        $this->request = $request;
    }

    /**
     * Create a view assets representing the bundle js functionality
     *
     * @param FileManager $subject
     * @param File[] $bundles
     * @return File[]
     */
    public function afterCreateBundleJsPool(FileManager $subject, array $bundles): array
    {
        if ($this->appState->getMode() == App\State::MODE_PRODUCTION) {
            $routeName = $this->request->getRouteName();
            if ($routeName == 'admin') {
                return $bundles;
            }
            foreach ($bundles as $key => $value) {
                if (strpos($value->getFilePath(), 'requirejs-config-' . $routeName) !== false) {
                    $bundles = [$value];
                    break;
                }
            }
        }

        return $bundles;
    }
}
