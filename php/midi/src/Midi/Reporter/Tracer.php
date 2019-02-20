<?php
/**
 * For Xdebug Trace
 *
 * @author tanmingliang
 */

namespace Midi\Reporter;

use Midi\Container;

class Tracer
{

    /**
     * Constants for Xdebug xt data
     */
    const ENTRY = '0';
    const EXIT = '1';
    const RETURN = 'R';
    const INTERNAL_FUNCTION = 0;
    const USER_DEFINED = 1;

    /**
     * Status for trace
     */
    const TRACE_DEL = 0;
    const TRACE_KEEP = 1;

    const KEEP_INTERNAL_CALL = [
        'require'               => 1,
        'include'               => 1,
        'require_once'          => 1,
        'include_once'          => 1,
        'spl_autoload_register' => 1,
        'spl_autoload_call'     => 1,
        'call_user_func'        => 1,
        'call_user_func_array'  => 1,
    ];

    const KEEP_PARAM_CALL = [
        'require'      => 1,
        'include'      => 1,
        'require_once' => 1,
        'include_once' => 1,
    ];

    public static function getTraceEnv()
    {
        return [
            'xdebug.auto_trace'        => 1,
            'xdebug.trace_output_name' => 'trace.%t',
            'xdebug.trace_format'      => 1,
            'xdebug.trace_options'     => 1,
            'xdebug.trace_output_dir'  => Container::make("traceTmpDir"),
        ];
    }

    /**
     * merge one request's multi traces files to html
     *
     * @param $sessionId
     * @return mixed|string
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public static function renderTraces2Html($sessionId)
    {
        $calledLists = self::mergeAndFilterReqTraces();
        if (count($calledLists) == 0) {
            return '';
        }

        $stack = [];
        $stackIdx = 0;
        $callTrees = [];
        foreach ($calledLists as $index => $parts) {
            if ($parts[2] === self::ENTRY) {
                $stack[$stackIdx++] = $parts;
            } else {
                $top = array_pop($stack);
                if (empty($top)) {
                    continue;
                }
                $fatherIdx = count($stack) - 1;
                $name = self::format($top);
                if ($fatherIdx < 0) {
                    $callTrees[] = [$name => $top['child'],]; // top node
                } else {
                    if (isset($top['child']) && count($top['child'])) {
                        $stack[$fatherIdx]['child'][] = [$name => $top['child'],];
                    } else {
                        $stack[$fatherIdx]['child'][] = [$name => [],];
                    }
                }
                --$stackIdx;
            }
        }

        $traceList = self::recursionRenderLiUi($callTrees);

        $templateDir = Container::make('templateDir') . DR;
        $loader = new \Twig_Loader_Filesystem($templateDir);
        $twig = new \Twig_Environment($loader);
        $template = $twig->load('replayed-trace.twig');
        $html = $template->render(
            [
                'SessionId' => $sessionId,
                'TraceList' => $traceList,
            ]
        );

        list($file, $realPath) = self::getTraceFileName($sessionId);
        file_put_contents($realPath, $html);
        return $file;
    }

    /**
     * merge and filter one request's multi traces files to array
     */
    public static function mergeAndFilterReqTraces()
    {
        $todoFiles = [];
        foreach (new \DirectoryIterator(Container::make("traceTmpDir")) as $fileInfo) {
            if ($fileInfo->isFile()) {
                $todoFiles[] = $fileInfo->getRealPath();
            }
        }
        if (count($todoFiles) == 0) {
            return [];
        }

        $index = 1;
        $filterLists = [];
        sort($todoFiles);
        foreach ($todoFiles as $file) {
            $in = fopen($file, "r");
            $version = fgets($in);
            $format = fgets($in);
            $valid = self::validXt($version, $format);
            if (!$valid) {
                fclose($in);
                continue;
            }

            $stack = [];
            while ($line = fgets($in)) {
                $parts = explode("\t", $line);
                if (count($parts) < 5) {
                    continue;
                }
                $parts[0] = intval($parts[0]);
                $parts[6] = intval($parts[6]);
                // only keep user defined function call
                if ($parts[2] === self::ENTRY) {
                    if ($parts[6] === self::INTERNAL_FUNCTION) {
                        if (isset(self::KEEP_INTERNAL_CALL[$parts[5]])) {
                            $stack[] = self::TRACE_KEEP;
                        } else {
                            $stack[] = self::TRACE_DEL;
                            continue;
                        }
                    } else {
                        $stack[] = self::TRACE_KEEP;
                    }
                } else {
                    $top = array_pop($stack);
                    if ($top === self::TRACE_DEL) {
                        continue;
                    }
                }
                // trace keep: include entry and return
                $filterLists[$index++] = $parts;
            }
            unlink($file);
        }

        return $filterLists;
    }

    private static function recursionRenderLiUi(array $callTrees)
    {
        $size = count($callTrees);
        if ($size === 0) {
            return '';
        }
        $html = '';
        $count = 0;
        foreach ($callTrees as $callTree) {
            ++$count;
            foreach ($callTree as $name => $subTrees) {
                break;
            }
            if (count($subTrees)) {
                $child = self::recursionRenderLiUi($subTrees);
                if ($count === $size) {
                    $html .= sprintf('<li class="lastChild">%s<ul>%s</ul></li>', $name, $child);
                } else {
                    $html .= sprintf('<li>%s<ul>%s</ul></li>', $name, $child);
                }
            } else {
                if ($count === $size) {
                    $html .= sprintf('<li class="lastChild">%s</li>', $name);
                } else {
                    $html .= sprintf('<li>%s</li>', $name);
                }
            }
        }
        return $html;
    }

    public static function getTraceFileName($sessionId)
    {
        $filename = 'trace-' . $sessionId . '-' . time() . '.html';
        return [$filename, Container::make('reportDir') . DR . $filename];
    }

    private static function format(array $parts)
    {
        $name = $parts[5];
        $file = $parts[8];
        $line = trim($parts[9]);

        if (isset(self::KEEP_PARAM_CALL[$name])) {
            $name = $name . ' (' . $parts[7] . ')';
        }
        return $name . ' - ' . $file . ':' . $line;
    }

    public static function validXt($version, $format = null)
    {
        if (!preg_match('@Version: [23].*@', $version) || !preg_match('@File format: [2-4]@', $format)) {
            return false;
        }
        return true;
    }
}
