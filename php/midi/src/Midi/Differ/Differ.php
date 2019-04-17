<?php declare(strict_types=1);

/**
 * @author fangjunda
 * @author yangbochen
 */
namespace Midi\Differ;

use Midi\Container;
use Midi\Koala\Replaying\ReplayingSession;
use Midi\Koala\Replayed\ReplayedSession;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Simple differ, only differ response body
 */
class Differ implements DifferInterface
{
    const ERROR_WORD = [
        'Fatal error',
        'Parse error'
    ];

    const ALLOW_TIME_DIFF_RANGE = 120;

    /**
     * @var array
     */
    private $config = [
        'display-diff-only' => false,
        'ignore-time-diff' => false,
        'exclude-keys' => [],
    ];

    /**
     * @var $input InputInterface
     */
    private $input;

    /**
     * @var $output OutputInterface
     */
    private $output;

    /**
     * @var array
     */
    private $jsonpWrapper = [];

    public function setOptions(array $config, InputInterface $input = null, OutputInterface $output = null)
    {
        if ($config !== null) {
            $this->config = array_merge($this->config, $config);
        }
        if ($output !== null) {
            $this->output = $output;
        }
        if ($input !== null) {
            $this->input = $input;

            /**
             * $v = $this->input->getOption('display-diff-only')
             *
             * run -a          => $v == false    => display-diff-only = false
             * run -a -D       => $v == NULL     => display-diff-only = true
             * run -a -D 0     => $v == '0'      => display-diff-only = false
             * run -a -D 1     => $v == '1'      => display-diff-only = true
             * run -a -D false => $v == 'false'  => display-diff-only = false
             * run -a -D true  => $v == 'true'   => display-diff-only = true
             * run -a -D abc   => $v == 'abc'    => display-diff-only = true
             * run -a -D ...                     => display-diff-only = true
             *
             * only consider: false、'0'、'false'
             */
            $diffOnly = $this->input->getOption('display-diff-only');
            if ($diffOnly === false || $diffOnly === '0' || is_string($diffOnly) && strtolower($diffOnly) === 'false') {
                $this->config['display-diff-only'] = false; // display all
            } else {
                $this->config['display-diff-only'] = true;  // display different only
            }

            // `exclude-key` default values is false，without -E will get false
            if ($this->input->getOption('exclude-key') !== false) {
                // with -E options, default will ignore different of value look like time
                $this->config['ignore-time-diff'] = true;

                // with -E options，but without value will get NULL and convert to true
                $excludeKeys = $this->input->getOption('exclude-key') ?? true;
                if (is_string($excludeKeys)) {
                    $excludeKeys = explode(',', $excludeKeys);
                    foreach ($excludeKeys as $excludeKey) {
                        $this->config['exclude-keys'][$excludeKey] = 1;
                    }
                }
            }
        }
    }

    /**
     * @param ReplayingSession $replayingSession
     * @param ReplayedSession $replayedSession
     * @param array $options
     * @return bool
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public function diff(ReplayingSession $replayingSession, ReplayedSession $replayedSession)
    {
        $stopWatch = Container::make('stopWatch');
        $stopWatch->start('diff');

        $originResp = base64_decode($replayedSession->getReturnInbound()->getOriginalResponse());
        $testResp = base64_decode($replayedSession->getReturnInbound()->getResponse());

        $bSame = false;
        $sOriginErr = $sTestErr = '';
        $this->jsonpWrapper = $aOriginHead = $aOriginBody = $aTestHead = $aTestBody = [];
        $bOriginOk = $this->_parseResp($originResp, $aOriginHead, $aOriginBody, $sOriginErr);
        $bTestOk = $this->_parseResp($testResp, $aTestHead, $aTestBody, $sTestErr);

        if (!$bOriginOk || !$bTestOk || !is_array($aOriginHead) || !is_array($aTestBody)) {
            if ($this->config['display-diff-only'] && $aOriginBody == $aTestBody) {
                $this->output->writeln('<info>Diff Result: <fg=green;options=bold>Response Body No Diff!</fg=green;options=bold></info>');
                $this->output->writeln('');
                return true;
            }
            $this->output->writeln('<info>===Online Response:</info>');
            $this->output->writeln('<comment>' . self::markError($originResp) . '</comment>');
            if (!$bOriginOk && !empty($sOriginErr)) {
                $this->output->writeln($sOriginErr . PHP_EOL);
            }
            $this->output->writeln('<info>===Local Response:</info>');
            $this->output->writeln('<comment>' . self::markError($testResp) . '</comment>');
            if (!$bTestOk && !empty($sTestErr)) {
                $this->output->writeln($sTestErr);
            }
            if ($aOriginBody === $aTestBody) {
                return true;
            }
            return false;
        }

        $originBodyDiff = $this->_getDiff($aOriginBody, $aTestBody);
        $testBodyDiff = $this->_getDiff($aTestBody, $aOriginBody);

        if ($this->config['display-diff-only'] && empty($originBodyDiff) && empty($testBodyDiff)) {
            $this->output->writeln('<info>Diff Result: <fg=green;options=bold>Response Body No Diff!</fg=green;options=bold></info>');
            $this->output->writeln('');
            return true;
        }

        $this->output->writeln('');
        $this->output->writeln('<info>===Online Response:</info>');
        $this->_displayDiff($aOriginHead, $aTestHead, $aOriginBody, $originBodyDiff);
        $this->output->writeln('');
        $this->output->writeln('<info>===Local Response:</info>');
        $this->_displayDiff($aTestHead, $aOriginHead, $aTestBody, $testBodyDiff);
        $this->output->writeln('');

        if (empty($originBodyDiff) && empty($testBodyDiff)) {
            $bSame = true;
            $this->output->writeln('<info>===Diff Result: <fg=green;options=bold>Response Body No Diff!</fg=green;options=bold></info>');
        } else {
            $this->output->writeln('<info>===Diff Result: <fg=red;options=bold>Exist Difference!</fg=red;options=bold></info>');
        }
        $this->output->writeln(sprintf('<info>Diff result spent %d ms.</info>', $stopWatch->stop('diff')->getDuration()), OutputInterface::VERBOSITY_VERBOSE);
        $this->output->writeln('');
        return $bSame;
    }

    /**
     * @param string $resp
     * @param array  $aHead
     * @param array  $aBody
     * @param string $sErrMsg
     *
     * @return bool
     */
    private function _parseResp($resp, &$aHead, &$aBody, &$sErrMsg)
    {
        $aResp = explode("\r\n\r\n", $resp);
        $aHead = explode("\r\n", $aResp[0]);

        if (empty($aResp[1])) {
            // response body empty
            $aBody = '';
            $sErrMsg = '<error>Notice: Response Body Empty!</error>';
            return false;
        }

        $aTmpBody = json_decode($aResp[1], true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            // for jsonp
            if (preg_match('~^[\s]*(\w+)\(({.*})\)(.*)$~', $aResp[1], $matches)) {
                if ('' != $matches[1]) {
                    $this->jsonpWrapper[] = [$matches[1], $matches[3]];
                } else {
                    $this->jsonpWrapper[] = '';
                }
                $aBody = json_decode($matches[2], true);
                if (JSON_ERROR_NONE !== json_last_error()) {
                    $sErrMsg = '<error>Notice: Response NOT JSON, Err = ' . json_last_error_msg() . '.</error>';
                    return false;
                }
            } else {
                $aBody = $aResp[1];
                $sErrMsg = '<error>Notice: Response NOT JSON, Err = ' . json_last_error_msg() . '.</error>';
                return false;
            }
        } else {
            $aBody = $aTmpBody;
        }

        return true;
    }

    private function _displayDiff($aHeadFst, $aHeadSec, $aBody, $aBodyDiff)
    {
        $this->_diffHeader($aHeadFst, $aHeadSec);
        $aBody = $this->_diffBody($aBody, $aBodyDiff);
        $body = json_encode($aBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $errMsg = json_last_error_msg();
            $this->output->writeln("<error>$errMsg</error>");
        } else {
            $jsonp = array_shift($this->jsonpWrapper);
            if (!empty($jsonp) && count($jsonp) == 2) {
                $body = $jsonp[0] . $body . $jsonp[1];
            }
            $this->output->writeln("<comment>$body</comment>");
        }
    }

    /**
     * do diff http header
     *
     * @param $aHeadFst
     * @param $aHeadSec
     */
    private function _diffHeader($aHeadFst, $aHeadSec)
    {
        foreach ($aHeadFst as $idx => $header) {
            if (!in_array($header, $aHeadSec)) {
                if ($idx !== 0) {
                    $this->output->writeln('<options=bold;fg=red>' . $header . '</>');
                } else {
                    $aHttpStatus = explode(' ', $header);
                    $aHttpStatus[1] = ' <options=bold;underscore>' . $aHttpStatus[1] . '</>';
                    $aHttpStatus[2] = ' <options=bold;underscore>' . $aHttpStatus[2] . '</>';
                    $sHttpStatus = implode($aHttpStatus);
                    $this->output->writeln($sHttpStatus);
                }
            } else {
                $this->output->writeln("<comment>$header</comment>");
            }
        }
        $this->output->writeln("");
    }

    /**
     * diff array
     *
     * @param $aBodyFst
     * @param $aBodySec
     *
     * @return array
     */
    private function _getDiff(&$aBodyFst, $aBodySec)
    {
        $diff = [];
        foreach ($aBodyFst as $key => $value) {
            if (is_array($value) && is_array($aBodySec[$key])) {
                $result = $this->_getDiff($value, $aBodySec[$key]);
                if (!empty($result)) {
                    if (isset($this->config['exclude-keys'][$key])) {
                        $aBodyFst[$key] = '<fg=black;bg=cyan>' . $value . '</>';
                        continue;
                    }
                    $diff[$key] = $result;
                }
                continue;
            }

            if (is_float($aBodyFst[$key])) {
                if (round($aBodyFst[$key], 10) != round($aBodySec[$key], 10)) {
                    if (isset($this->config['exclude-keys'][$key])) {
                        $aBodyFst[$key] = '<fg=black;bg=cyan>' . $value . '</>';
                        continue;
                    }
                    $diff[$key] = $value;
                }
            } elseif ($aBodyFst[$key] !== $aBodySec[$key]) {
                if (isset($this->config['exclude-keys'][$key])) {
                    $aBodyFst[$key] = '<fg=black;bg=cyan>' . $value . '</>';
                    continue;
                }
                if (is_int($value) && $value >= (time() - 86400 * 30)) { // look like time
                    $absTime = min(abs(time() - $value), abs($aBodySec[$key] - $value));
                    if ($absTime < self::ALLOW_TIME_DIFF_RANGE) {
                        $aBodyFst[$key] = '<fg=black;bg=cyan>' . $value . '</>';
                        $this->config['exclude-keys'][$key] = 1;
                        continue;
                    }
                }
                $diff[$key] = $value;
            }
        }

        return $diff;
    }

    /**
     * @param $aBody
     * @param $diff
     *
     * @return mixed
     */
    private function _diffBody($aBody, $diff)
    {
        foreach ($diff as $key => $value) {
            if (!isset($aBody[$key])) {
                continue;
            }
            if (is_array($value)) {
                $aBody[$key] = $this->_diffBody($aBody[$key], $value);
            } else {
                $aBody[$key] = '<options=bold;fg=red>' . $aBody[$key] . '</>';
            }
        }

        return $aBody;
    }

    public static function markError($testResp)
    {
        foreach (self::ERROR_WORD as $word) {
            $testResp = str_replace($word,
                sprintf('<error>%s</error>', $word),
                $testResp);
        }

        return $testResp;
    }
}
