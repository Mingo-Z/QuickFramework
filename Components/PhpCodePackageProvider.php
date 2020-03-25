<?php
namespace Qf\Components;

use Qf\Kernel\ComponentManager;

class PhpFilesPackageProvider extends Provider
{
    protected $waitingFiles;
    public $allowFileExtensions;
    public $excludeFiles;
    public $defaultInFile;
    public $saveDirPath;
    public $packageFileName;

    /**
     * 待打包的项目基础路径，文件添加进包时将被
     * 自动去掉
     *
     * @var string
     */
    public $projectBasePath;

    /**
     * @var \Phar
     */
    protected $pharHandle;

    public function __construct(ComponentManager $com = null)
    {
        parent::__construct($com);
        $this->waitingFiles = [];
        $this->excludeFiles = [];
        $this->allowFileExtensions = ['php'];
        $this->saveDirPath = './';
        $this->packageFileName = date('YmdHis') . '.phar';
    }

    protected function scanPackageDirPath($fullDirPath)
    {
        if (is_dir($fullDirPath) && is_readable($fullDirPath)) {
            $dirHandle = opendir($fullDirPath);
            if ($dirHandle) {
                while (($entry = readdir($dirHandle))) {
                    if ($entry != '.' && $entry != '..' && (!$this->excludeFiles
                            || !$this->isFileMatch($entry, $this->excludeFiles))) {
                        $fullFilePath = $fullDirPath . '/' . $entry;
                        if (is_file($fullFilePath) && (!$this->allowFileExtensions || $this->allowFileExtensions[0] == '*'
                                || in_array(pathinfo($fullFilePath, PATHINFO_EXTENSION), $this->allowFileExtensions))) {
                            $this->waitingFiles[] = $fullFilePath;
                        } elseif (is_dir($fullFilePath)) {
                            $this->scanPackageDirPath($fullFilePath);
                        }
                    }
                }
                closedir($dirHandle);
            }
        }

        return $this;
    }

    public function exec($fullDirPath = null)
    {
        $savePgFileFullPath = null;
        $fullDirPath = $fullDirPath ?: $this->projectBasePath;
        if ($fullDirPath) {
            $this->scanPackageDirPath($fullDirPath);
            if ($this->waitingFiles) {
                $savePgFileFullPath = $this->saveDirPath . '/' . $this->packageFileName;
                $this->pharHandle = new \Phar($savePgFileFullPath);
                $this->pharHandle->compressFiles(Phar::GZ);
                $this->pharHandle->startBuffering();
                foreach ($this->waitingFiles as $fullFilePath) {
                    $this->packageFile($fullFilePath);
                }
                $this->pharHandle->stopBuffering();
            }
        }

        return $savePgFileFullPath;
    }

    protected function packageFile($fullFilePath)
    {
        if ($this->pharHandle) {
            $localName = null;
            if ($this->projectBasePath && !strncmp($fullFilePath, $this->projectBasePath, strlen($this->projectBasePath))) {
                $localName = substr($fullFilePath, strlen($this->projectBasePath));
                $localName = ltrim($localName, '/\\');
            }
            $this->pharHandle->addFile($fullFilePath, $localName);
        }
    }


    protected function isFileMatch($name, array $rules)
    {
        $pattern = join('|', $rules);
        return preg_match("@($pattern)@", $name);
    }

    public function __destruct()
    {
        $this->pharHandle = null;
        $this->waitingFiles = null;
    }

    public static function opcodeCompileFile($fullFilePath)
    {
        return opcache_compile_file($fullFilePath);
    }
}