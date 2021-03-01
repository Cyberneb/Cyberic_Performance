<?php

namespace Cyberic\Performance\Package\Bundle;

use Cyberic\Performance\Model\ResourceModel\JsBundleCollection;
use Magento\Deploy\Config\BundleConfig;
use Magento\Deploy\Package\BundleInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\File\WriteInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\View\Asset\Minification;

/**
 * RequireJs static files bundle object
 *
 * All files added will be bundled to multiple bundle files compatible with RequireJS AMD format
 */
class RequireJs implements BundleInterface
{
    /**
     * Static files Bundling configuration class
     *
     * @var BundleConfig
     */
    private $bundleConfig;

    /**
     * @var array
     */
    private $requirejsBundles;

    /**
     * @var array
     */
    private $bundleFiles;

    /**
     * Helper class for static files minification related processes
     *
     * @var Minification
     */
    private $minification;

    /**
     * Static content directory writable interface
     *
     * @var WriteInterface
     */
    private $staticDir;

    /**
     * Package area
     *
     * @var string
     */
    private $area;

    /**
     * Package theme
     *
     * @var string
     */
    private $theme;

    /**
     * Package locale
     *
     * @var string
     */
    private $locale;

    /**
     * Bundle content pools
     *
     * @var string[]
     */
    private $contentPools = [
        'js' => 'jsbuild',
        'html' => 'text'
    ];

    /**
     * Files to be bundled
     *
     * @var array[]
     */
    private $files = [
        'jsbuild' => [],
        'text' => []
    ];

    /**
     * Files content cache
     *
     * @var string[]
     */
    private $fileContent = [];

    /**
     * Incremental index of bundle file
     *
     * Chosen bundling strategy may result in creating multiple bundle files instead of one
     *
     * @var int
     */
    private $bundleFileIndex = 0;

    /**
     * Relative path to directory where bundle files should be created
     *
     * @var string
     */
    private $pathToBundleDir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var JsBundleCollection
     */
    private $jsBundleCollection;

    /**
     * Bundle constructor
     *
     * @param Filesystem $filesystem
     * @param BundleConfig $bundleConfig
     * @param Minification $minification
     * @param JsBundleCollection $jsBundleCollection
     * @param string $area
     * @param string $theme
     * @param string $locale
     * @param array $contentPools
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        BundleConfig $bundleConfig,
        Minification $minification,
        JsBundleCollection $jsBundleCollection,
        string $area,
        string $theme,
        string $locale,
        array $contentPools = []
    ) {
        $this->filesystem = $filesystem;
        $this->bundleConfig = $bundleConfig;
        $this->minification = $minification;
        $this->staticDir = $filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);
        $this->area = $area;
        $this->theme = $theme;
        $this->locale = $locale;
        $this->contentPools = array_merge($this->contentPools, $contentPools);
        $this->pathToBundleDir = $this->area . '/' . $this->theme . '/' . $this->locale . '/' . self::BUNDLE_JS_DIR;
        $this->jsBundleCollection = $jsBundleCollection;
    }

    /**
     * Goes through the collection items and separate them into page specific bundles.
     *
     * @return array
     */
    protected function getBundleFiles(): array
    {
        if (!isset($this->bundleFiles)) {
            $items = $this->jsBundleCollection->getItems();
            $this->getPageSpecificBundles($items);
        }

        return $this->bundleFiles;
    }

    /**
     * As it goes through the items of our collection, it counts how many occurrences of these items are saved.
     * If it is equal to the number of page types, it is included in the default bundle.
     * If not, it is used in the page specific bundle.
     *
     * @param $items
     */
    protected function getPageSpecificBundles($items)
    {
        $pageSpecificFiles = $this->getSpecificPageFiles($items);
        $commonCount = count($this->bundleFiles);
        $this->bundleFiles = [];
        foreach ($items as $item) {
            if ($pageSpecificFiles[$item->getData('dependency_path')] == $commonCount) {
                $this->bundleFiles['default'][$item->getData('dependency_path')] = $item->getData('dependency_name');
                continue;
            }
            $this->bundleFiles[$item->getData('page_type')][$item->getData('dependency_path')]
                = $item->getData('dependency_name');
        }
    }

    /**
     * Returns an array of specific page files.
     *
     * @param $items
     * @return array
     */
    protected function getSpecificPageFiles($items): array
    {
        $this->bundleFiles = [];
        $pageSpecificFiles = [];
        foreach ($items as $item) {
            if (!isset($pageSpecificFiles[$item->getData('dependency_path')])) {
                $pageSpecificFiles[$item->getData('dependency_path')] = 0;
            }
            $pageSpecificFiles[$item->getData('dependency_path')] += 1;
            if (!isset($this->bundleFiles[$item->getData('page_type')])) {
                $this->bundleFiles[$item->getData('page_type')] = [];
            }
        }

        return $pageSpecificFiles;
    }

    /**
     * @inheritdoc
     */
    public function addFile($filePath, $sourcePath, $contentType): bool
    {
        // all unknown content types designated to "text" pool
        $contentPoolName = isset($this->contentPools[$contentType]) ? $this->contentPools[$contentType] : 'text';
        if ($this->area == 'admin' || empty($this->getBundleFiles())) {
            $this->files[$contentPoolName][$filePath] = $sourcePath;
            return true;
        }
        $this->addSourcePath($filePath, $sourcePath);

        return true;
    }

    /**
     * Each time the Magento deploy service attempts to add a file to the JS bundles, we save it in the respective page
     * type bundles, if needed. It ensures that only files recognized by Magento can be added to these bundles.
     *
     * @param $filePath
     * @param $sourcePath
     */
    protected function addSourcePath($filePath, $sourcePath) {
        $dependencyPath = str_replace(
            [$sourcePath, '.min.', 'jquery/jquery.storageapi'],
            ['', '.', 'jquery/jquery.storageapi.min'],
            $filePath
        );
        foreach (array_keys($this->bundleFiles) as $pageType) {
            if (isset($this->bundleFiles[$pageType][$dependencyPath])) {
                $this->files[$pageType][$filePath] = [
                    'name' => $this->bundleFiles[$pageType][$dependencyPath],
                    'path' => $sourcePath
                ];
            }
        }
    }

    /**
     * @inheritdoc
     * @throws FileSystemException
     */
    public function flush(): bool
    {
        if (!empty($this->getBundleFiles())) {
            return $this->flushPageSpecific();
        }
        $this->bundleFileIndex = 0;

        $bundleFile = null;
        foreach ($this->files as $contentPoolName => $files) {
            if (empty($files)) {
                continue;
            }
            $content = [];
            $freeSpace = $this->getBundleFileMaxSize();
            $bundleFile = $this->startNewBundleFile($contentPoolName);
            foreach ($files as $filePath => $sourcePath) {
                $fileContent = $this->getFileContent($sourcePath);
                $size = mb_strlen($fileContent, 'utf-8') / 1024;
                if ($freeSpace > $size) {
                    $freeSpace -= $size;
                    $content[$this->minification->addMinifiedSign($filePath)] = $fileContent;
                } else {
                    $this->endBundleFile($bundleFile, $content);
                    $freeSpace = $this->getBundleFileMaxSize();
                    $freeSpace -= $size;
                    $content = [
                        $this->minification->addMinifiedSign($filePath) => $fileContent
                    ];
                    $bundleFile = $this->startNewBundleFile($contentPoolName);
                }
            }
            $this->endBundleFile($bundleFile, $content);
        }

        if ($bundleFile) {
            $bundleFile->write($this->getInitJs());
        }

        $this->files = [];

        return true;
    }

    /**
     * Flushes all files added to appropriate bundle
     *
     * @return bool true on success
     * @throws FileSystemException
     */
    public function flushPageSpecific(): bool
    {
        foreach ($this->files as $pageType => $dependencies) {
            if (empty($dependencies)) {
                continue;
            }
            $content = [];
            $bundleFile = $this->startSpecificBundleFile($pageType);
            foreach ($dependencies as $dependency) {
                $fileContent = $this->getFileContent($dependency['path']);
                $content[$dependency['name']] = $fileContent;
            }
            $this->endSpecificBundleFile($pageType, $bundleFile, $content);
        }
        $this->writeConfigBundleFiles();
        $this->files = [];

        return true;
    }

    /**
     * @inheritdoc
     * @throws FileSystemException
     */
    public function clear(): bool
    {
        return $this->staticDir->delete($this->pathToBundleDir);
    }

    /**
     * Create new bundle file and write beginning content to it
     *
     * @param string $contentPoolName
     * @return WriteInterface
     * @throws FileSystemException
     */
    private function startNewBundleFile(string $contentPoolName): WriteInterface
    {
        $filePath = $this->pathToBundleDir . '/' . (!empty($pageType) ? $pageType . '-' : '') . 'bundle'
            . $this->bundleFileIndex . '.js';
        $bundleFile = $this->staticDir->openFile($this->minification->addMinifiedSign($filePath));
        $bundleFile->write("require.config({\"config\": {\n");
        $bundleFile->write("        \"{$contentPoolName}\":");
        ++$this->bundleFileIndex;
        return $bundleFile;
    }

    /**
     * Create new bundle file and write beginning content to it
     *
     * @param string $pageType
     * @return WriteInterface
     */
    private function startSpecificBundleFile(string $pageType): WriteInterface
    {
        $filePath = $this->pathToBundleDir . '/' . $pageType . '.js';
        return $this->staticDir->openFile($this->minification->addMinifiedSign($filePath));
    }

    /**
     * Write ending content to bundle file
     *
     * @param WriteInterface $bundleFile
     * @param array $contents
     * @return bool true on success
     * @throws FileSystemException
     * @noinspection PhpComposerExtensionStubsInspection
     */
    private function endBundleFile(WriteInterface $bundleFile, array $contents): bool
    {
        if ($contents) {
            $content = json_encode($contents, JSON_UNESCAPED_SLASHES);
            $bundleFile->write("{$content}\n");
        } else {
            $bundleFile->write("{}\n");
        }
        $bundleFile->write("}});\n");
        return true;
    }

    /**
     * Write ending content to bundle file
     *
     * @param string $pageType
     * @param WriteInterface $bundleFile
     * @param array $contents
     * @return bool true on success
     * @throws FileSystemException
     */
    private function endSpecificBundleFile(string $pageType, WriteInterface $bundleFile, array $contents): bool
    {
        $contents = $this->setDependencyOrder($contents);
        $modules = [];
        foreach ($contents as $moduleName => $content) {
            $modules[] = $moduleName;
            if ($this->isHtmlFile($moduleName)) {
                $bundleFile->write($this->wrapTextContent($moduleName, $content) . "\n");
                continue;
            }
            if (!$this->isAmd($content)) {
                $bundleFile->write($this->wrapNonAmdContent($moduleName, $content) . "\n");
                continue;
            }
            $bundleFile->write($this->wrapAmdContent($moduleName, $content) . "\n");
        }
        $this->requirejsBundles[$pageType] = "'js/bundle/" . $pageType
            . "':['" . implode("','", $modules) . "']";

        return true;
    }

    /**
     * A very important step is to sort the order of the Requirejs dependencies.
     *
     * @param array $contents
     * @return array
     */
    protected function setDependencyOrder(array $contents): array
    {
        $newContents = [];
        foreach ($contents as $moduleName => $content) {
            $newContents[$moduleName] = $content;
            $dependencies = $this->extractDependencies($content, $contents);
            do {
                $parentDependencies = [];
                foreach ($dependencies as $dependency) {
                    if (!isset($newContents[$dependency])) {
                         $this->insertBefore($newContents, $moduleName, $dependency, $contents[$dependency]);
                    }
                    $parentDependency = $this->extractDependencies($contents[$dependency], $contents);
                    if (!empty($parentDependency)) {
                        $parentDependencies[] = $parentDependency;
                    }
                    $dependencies = current($parentDependencies);
                }
            } while(!empty($dependencies));
        }

        return $newContents;
    }

    /**
     * When a new dependency is found, it needs to be inserted before the module that depends on it.
     *
     * @param $contents
     * @param $moduleName
     * @param $dependency
     * @param $content
     */
    protected function insertBefore(&$contents, $moduleName, $dependency, $content)
    {
        $newContent = [];
        foreach ($contents as $key => $value) {
            if ($key == $moduleName) {
                $newContent[$dependency] = $content;
            }
            $newContent[$key] = $value;
        }
        $contents = $newContent;
    }

    /**
     * We need to know what the dependencies are for each of our JS modules.
     * It will look for the dependencies defined within the content of the javascript files.
     *
     * @param string $content
     * @param array $contents
     * @return array
     */
    protected function extractDependencies(string $content, array $contents): array
    {
        preg_match('/define\s*\(\[(.*?)]/', $content, $jsonMatches);
        $dependencies = !empty($jsonMatches[1]) ? explode(',', str_replace(['"', "'"], '', $jsonMatches[1])) : [];
        foreach ($dependencies as $key => $dependency) {
            if (!isset($contents[$dependency])) {
                unset($dependencies[$key]);
            }
        }

        return $dependencies;
    }

    /**
     * Write content to bundle files
     *
     * @return bool true on success
     * @throws FileSystemException
     */
    public function writeConfigBundleFiles(): bool
    {
        foreach ($this->requirejsBundles as $pageType => $modules) {
            if ($pageType == 'default' || empty($modules)) {
                continue;
            }
            $configBundleFile = $this->staticDir->openFile(
                $this->minification->addMinifiedSign(
                    $this->pathToBundleDir . '/requirejs-config-' . $pageType . '.js'
                )
            );
            $content = "requirejs.config({bundles:{";
            if (isset($this->requirejsBundles['default'])) {
                $content .= $this->requirejsBundles['default'] . ',';
            }
            $content .= $this->requirejsBundles[$pageType] . "}});";
            $configBundleFile->write($content);
        }

        return true;
    }

    /**
     * Get content of static file
     *
     * @param string $sourcePath
     * @return string
     * @throws FileSystemException
     */
    private function getFileContent(string $sourcePath): string
    {
        if (!isset($this->fileContent[$sourcePath])) {
            $content = $this->staticDir->readFile($this->minification->addMinifiedSign($sourcePath));
            if (mb_detect_encoding($content) !== "UTF-8") {
                $content = mb_convert_encoding($content, "UTF-8");
            }

            $this->fileContent[$sourcePath] = $content;
        }
        return $this->fileContent[$sourcePath];
    }

    /**
     * @param string $moduleName
     * @return bool
     */
    private function isHtmlFile(string $moduleName): bool
    {
        return stripos($moduleName, "text!") === 0;
    }

    /**
     * @param string $content
     * @return bool
     */
    private function isAmd(string $content): bool
    {
        return (bool)preg_match('/define\s*\(/m', $content);
    }

    /**
     * @param string $moduleName
     * @param string $content
     * @return string
     */
    private function wrapAmdContent(string $moduleName, string $content): string
    {
        if ($moduleName == 'jquery' || $moduleName == 'underscore') {
            return $content;
        }
        return str_replace(['define(', 'define ('], "define('{$moduleName}',", $content);
    }

    /**
     * @param string $moduleName
     * @param string $content
     * @return string
     */
    private function wrapNonAmdContent(string $moduleName, string $content): string
    {
        return "define('{$moduleName}', (require.s.contexts._.config.shim['{$moduleName}'] "
            . "&& require.s.contexts._.config.shim['{$moduleName}'].deps || []), function() {
    {$content}
    return (require.s.contexts._.config.shim['{$moduleName}'] && require.s.contexts._.config.shim['{$moduleName}']."
            . "exportsFn && require.s.contexts._.config.shim['{$moduleName}'].exportsFn());
}.bind(window));";
    }

    /**
     * @param $moduleName
     * @param $content
     * @return string
     */
    private function wrapTextContent($moduleName, $content): string
    {
        return "define('" . str_replace('Magento_Ui/templates', 'ui/template', $moduleName)
            . "', function() {return '" . $this->escapeContent($content) . "';});";
    }

    /**
     * @param string $content
     * @return string
     */
    private function escapeContent(string $content): string
    {
        return str_replace(['\'', "\n"], ['\\\'', '\n'], $content);
    }

    /**
     * Get max size of bundle files (in KB)
     *
     * @return int
     */
    private function getBundleFileMaxSize(): int
    {
        return $this->bundleConfig->getBundleFileMaxSize($this->area, $this->theme);
    }

    /**
     * Bundle initialization script content (this must be added to the latest bundle file at the very end)
     *
     * @return string
     */
    private function getInitJs(): string
    {
        return "require.config({\n" .
            "    bundles: {\n" .
            "        'mage/requirejs/static': [\n" .
            "            'jsbuild',\n" .
            "            'buildTools',\n" .
            "            'text',\n" .
            "            'statistician'\n" .
            "        ]\n" .
            "    },\n" .
            "    deps: [\n" .
            "        'jsbuild'\n" .
            "    ]\n" .
            "});\n";
    }
}
