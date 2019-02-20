<?php

/**
 * Use PHPUnit code parser phpunit.xml and get coverage config
 */

namespace Midi\Reporter\Coverage;

use Exception;
use DOMDocument;
use DOMElement;
use DOMXPath;

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Wrapper for the PHPUnit XML configuration file.
 *
 * Example XML configuration file:
 * <code>
 * <?xml version="1.0" encoding="utf-8" ?>
 *
 * <phpunit>
 *   <filter>
 *     <whitelist addUncoveredFilesFromWhitelist="true"
 *                processUncoveredFilesFromWhitelist="false">
 *       <directory suffix=".php">/path/to/files</directory>
 *       <file>/path/to/file</file>
 *       <exclude>
 *         <directory suffix=".php">/path/to/files</directory>
 *         <file>/path/to/file</file>
 *       </exclude>
 *     </whitelist>
 *   </filter>
 * </phpunit>
 * </code>
 */
final class Configuration
{
    /**
     * @var self[]
     */
    private static $instances = [];

    /**
     * @var DOMDocument
     */
    private $document;

    /**
     * @var DOMXPath
     */
    private $xpath;

    /**
     * @var string
     */
    private $filename;

    /**
     * Returns a PHPUnit configuration object.
     *
     * @throws Exception
     */
    public static function getInstance(string $filename)
    {
        $exist = file_exists($filename);
        if ($exist === false) {
            throw new Exception(
                \sprintf(
                    'Could not read "%s".',
                    $filename
                )
            );
        }

        // optimize for file in phar will return false
        $realPath = \realpath($filename);
        if ($realPath === false) {
            $realPath = $filename;
        }

        /** @var string $realPath */
        if (!isset(self::$instances[$realPath])) {
            self::$instances[$realPath] = new self($realPath);
        }

        return self::$instances[$realPath];
    }

    /**
     * Loads a PHPUnit configuration file.
     *
     * @throws Exception
     */
    private function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->document = Xml::loadFile($filename, false, true, true);
        $this->xpath = new DOMXPath($this->document);
    }

    /**
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }

    /**
     * Returns the configuration for SUT filtering.
     */
    public function getFilterConfiguration(): array
    {
        $addUncoveredFilesFromWhitelist = true;
        $processUncoveredFilesFromWhitelist = false;
        $includeDirectory = [];
        $includeFile = [];
        $excludeDirectory = [];
        $excludeFile = [];

        $tmp = $this->xpath->query('filter/whitelist');

        if ($tmp->length === 1) {
            if ($tmp->item(0)->hasAttribute('addUncoveredFilesFromWhitelist')) {
                $addUncoveredFilesFromWhitelist = $this->getBoolean(
                    (string)$tmp->item(0)->getAttribute(
                        'addUncoveredFilesFromWhitelist'
                    ),
                    true
                );
            }

            if ($tmp->item(0)->hasAttribute('processUncoveredFilesFromWhitelist')) {
                $processUncoveredFilesFromWhitelist = $this->getBoolean(
                    (string)$tmp->item(0)->getAttribute(
                        'processUncoveredFilesFromWhitelist'
                    ),
                    false
                );
            }

            $includeDirectory = $this->readFilterDirectories(
                'filter/whitelist/directory'
            );

            $includeFile = $this->readFilterFiles(
                'filter/whitelist/file'
            );

            $excludeDirectory = $this->readFilterDirectories(
                'filter/whitelist/exclude/directory'
            );

            $excludeFile = $this->readFilterFiles(
                'filter/whitelist/exclude/file'
            );
        }

        return [
            'whitelist' => [
                'addUncoveredFilesFromWhitelist'     => $addUncoveredFilesFromWhitelist,
                'processUncoveredFilesFromWhitelist' => $processUncoveredFilesFromWhitelist,
                'include'                            => [
                    'directory' => $includeDirectory,
                    'file'      => $includeFile,
                ],
                'exclude'                            => [
                    'directory' => $excludeDirectory,
                    'file'      => $excludeFile,
                ],
            ],
        ];
    }

    /**
     * Returns Nuwa configuration.
     */
    public function getNuwaConfiguration(): array
    {
        $recordFile = '';
        $framework = '';

        $tmp = $this->xpath->query('nuwa');

        if ($tmp->length === 1) {
            foreach ($this->xpath->query('nuwa/recordFile') as $file) {
                /** @var DOMElement $file */
                $recordFile = $file->textContent;
                break;
            }
            foreach ($this->xpath->query('nuwa/framework') as $fw) {
                /** @var DOMElement $file */
                $framework = $fw->textContent;
                break;
            }
        }

        return [
            'recordFile' => $recordFile,
            'framework'  => $framework,
        ];
    }

    /**
     * if $value is 'false' or 'true', this returns the value that $value represents.
     * Otherwise, returns $default, which may be a string in rare cases.
     * See PHPUnit\Util\ConfigurationTest::testPHPConfigurationIsReadCorrectly
     *
     * @param bool|string $default
     *
     * @return bool|string
     */
    private function getBoolean(string $value, $default)
    {
        if (\strtolower($value) === 'false') {
            return false;
        }

        if (\strtolower($value) === 'true') {
            return true;
        }

        return $default;
    }

    private function readFilterDirectories(string $query): array
    {
        $directories = [];

        foreach ($this->xpath->query($query) as $directoryNode) {
            /** @var DOMElement $directoryNode */
            $directoryPath = (string)$directoryNode->textContent;

            if (!$directoryPath) {
                continue;
            }

            $prefix = '';
            $suffix = '.php';
            $group = 'DEFAULT';

            if ($directoryNode->hasAttribute('prefix')) {
                $prefix = (string)$directoryNode->getAttribute('prefix');
            }

            if ($directoryNode->hasAttribute('suffix')) {
                $suffix = (string)$directoryNode->getAttribute('suffix');
            }

            if ($directoryNode->hasAttribute('group')) {
                $group = (string)$directoryNode->getAttribute('group');
            }

            $directories[] = [
                'path'   => $this->toAbsolutePath($directoryPath),
                'prefix' => $prefix,
                'suffix' => $suffix,
                'group'  => $group,
            ];
        }

        return $directories;
    }

    /**
     * @return string[]
     */
    private function readFilterFiles(string $query): array
    {
        $files = [];

        foreach ($this->xpath->query($query) as $file) {
            $filePath = (string)$file->textContent;

            if ($filePath) {
                $files[] = $this->toAbsolutePath($filePath);
            }
        }

        return $files;
    }

    private function toAbsolutePath(string $path, bool $useIncludePath = false): string
    {
        $path = \trim($path);

        if ($path[0] === '/') {
            return $path;
        }

        // Matches the following on Windows:
        //  - \\NetworkComputer\Path
        //  - \\.\D:
        //  - \\.\c:
        //  - C:\Windows
        //  - C:\windows
        //  - C:/windows
        //  - c:/windows
        if (\defined('PHP_WINDOWS_VERSION_BUILD') &&
            ($path[0] === '\\' || (\strlen($path) >= 3 && \preg_match('#^[A-Z]\:[/\\\]#i', \substr($path, 0, 3))))) {
            return $path;
        }

        if (\strpos($path, '://') !== false) {
            return $path;
        }

        $file = \dirname($this->filename) . \DIRECTORY_SEPARATOR . $path;

        if ($useIncludePath && !\file_exists($file)) {
            $includePathFile = \stream_resolve_include_path($path);

            if ($includePathFile) {
                $file = $includePathFile;
            }
        }

        return $file;
    }
}