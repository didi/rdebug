<?php
/**
 * @author tanmingliang
 */

namespace Midi\Reporter;

use ReflectionClass;
use Midi\Container;
use Midi\Exception\RuntimeException;
use Midi\Reporter\Coverage\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Html\Facade;

class Coverage
{

    const DIST = 'phpunit.xml.dist';

    /**
     * @var string
     */
    protected static $dist;

    /**
     * @var bool
     */
    protected static $enable = false;

    /**
     * @var array
     */
    protected static $options;

    /**
     * @var InputInterface
     */
    protected static $input;

    /**
     * @var OutputInterface
     */
    protected static $output;

    /**
     * @var string
     */
    protected static $template = <<<CODE
Midi\Reporter\Coverage::factory('PROJECT-XML-DIST', 'COVERAGE-LOG', 'SESSION-ID');
CODE;

    /**
     * @param $options
     * @param $input
     * @param $output
     */
    public static function setOptions($options, InputInterface $input, OutputInterface $output)
    {
        self::$options = $options;
        self::$input = $input;
        self::$output = $output;
    }

    /**
     * @param string $sessionId
     * @return string
     *
     * @throws RuntimeException
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public static function buildPatch($sessionId)
    {
        static $code;
        if ($code) {
            if (empty($code)) {
                return '';
            }
            return str_replace('SESSION-ID', $sessionId, $code);
        }

        if (!isset(self::$options['isCoverage']) || self::$options['isCoverage'] !== true) {
            return $code = '';
        }

        if (self::$dist !== null && file_exists(self::$dist)) {
            $dist = self::$dist;
        } else {
            // check working dir
            $workingDir = Container::make('workingDir');
            $dist = $workingDir . DR . self::DIST;
            if (!file_exists($dist)) {
                $preDist = $dist;
                $dist = $workingDir . DR . '.midi' . DR . self::DIST;
                if (!file_exists($dist)) {
                    self::$output->writeln("<info>Can not find phpunit.xml.dist file: 
<comment>$preDist</comment>
<comment>$dist</comment>. 
Coverage will not work.</info>");
                    return $code = '';
                }
            }
        }

        try {
            $reflect = new ReflectionClass('Composer\Autoload\ClassLoader');
        } catch (\ReflectionException $e) {
            self::$output->writeln("<info>Can not find autoload file, Coverage will not work.</info>");
            return $code = '';
        }
        $autoloader = dirname($reflect->getFileName()) . '/../autoload.php';
        $code = <<<CODE
<?php
include_once('$autoloader');

CODE;

        $collect = self::getCollectFile();
        self::$template = str_replace('PROJECT-XML-DIST', $dist, self::$template);
        self::$template = str_replace('COVERAGE-LOG', $collect, self::$template);
        $code .= self::$template; // $code is a template
        $code .= <<<CODE
?>
CODE;

        self::$enable = true;

        return str_replace('SESSION-ID', $sessionId, $code);
    }

    /**
     * @param string $phpunitDist
     * @param string $outputFile
     * @param string $id
     * @return CodeCoverage
     */
    public static function factory($phpunitDist, $outputFile, $id)
    {
        $coverage = new CodeCoverage();
        if (is_string($phpunitDist) && file_exists($phpunitDist)) {
            self::initFromUnitDist($coverage, $phpunitDist);
        }

        $coverage->setProcessUncoveredFilesFromWhitelist(false);
        $coverage->start($id);
        register_shutdown_function([$coverage, 'stopSaveToFile'], $outputFile);

        return $coverage;
    }

    public static function setPhpUnitDist($dist)
    {
        self::$dist = $dist;
    }

    /**
     * Init from PHPUnit.xml.dist
     */
    public static function initFromUnitDist(CodeCoverage $coverage, string $dist)
    {
        $config = Configuration::getInstance($dist);
        $filterConfig = $config->getFilterConfiguration();

        if (!empty($filterConfig['whitelist'])) {
            $coverage->setAddUncoveredFilesFromWhitelist(
                $filterConfig['whitelist']['addUncoveredFilesFromWhitelist']
            );
            $coverage->setProcessUncoveredFilesFromWhitelist(
                $filterConfig['whitelist']['processUncoveredFilesFromWhitelist']
            );

            foreach ($filterConfig['whitelist']['include']['directory'] as $dir) {
                $coverage->filter()->addDirectoryToWhitelist($dir['path'], $dir['suffix'], $dir['prefix']);
            }
            foreach ($filterConfig['whitelist']['include']['file'] as $file) {
                $coverage->filter()->addFileToWhitelist($file);
            }
            foreach ($filterConfig['whitelist']['exclude']['directory'] as $dir) {
                $coverage->filter()->removeDirectoryFromWhitelist($dir['path'], $dir['suffix'], $dir['prefix']);
            }
            foreach ($filterConfig['whitelist']['exclude']['file'] as $file) {
                $coverage->filter()->removeFileFromWhitelist($file);
            }
        }
    }

    /**
     * Report html use json record
     *
     * @param bool $open
     * @throws RuntimeException
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public static function renderHTML($open)
    {
        $file = self::getCollectFile();
        if (!self::$enable || !file_exists($file)) {
            return null;
        }

        $workingDir = Container::make('workingDir');
        $deployDir = Container::make('config')->get('php', 'deploy-path');
        if (!empty($deployDir) && $workingDir !== $deployDir) {
            $data = file_get_contents($file);
            $data = str_replace(str_replace('/', '\/', $deployDir), str_replace('/', '\/', $workingDir), $data);
            file_put_contents($file, $data);
        }

        $dir = Container::make('coverageDir');
        $coverage = CodeCoverage::instanceFromFile($file);
        $writer = new Facade;
        $writer->process($coverage, $dir);

        // clean data
        unlink($file);

        if (!self::$options['isReport'] && $open) {
            system("open $dir/index.html");
        }

        return $dir . DR . 'index.html';
    }

    /**
     * @param string
     */
    public static function setTemplate(string $template)
    {
        self::$template = $template;
    }

    /**
     * @return string
     */
    public static function getTemplate()
    {
        return self::$template;
    }

    public static function getCollectFile()
    {
        static $file;
        if ($file) {
            return $file;
        }

        $collectDir = Container::make('coverageDir') . DR . 'collect';
        if (!file_exists($collectDir) && !mkdir($collectDir, 0777, true)) {
            throw new RuntimeException("Create Dir $collectDir fail!");
        }
        $file = $collectDir . DR . 'coverage.log';
        return $file;
    }
}