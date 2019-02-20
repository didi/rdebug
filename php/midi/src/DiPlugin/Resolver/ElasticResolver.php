<?php
/**
 * @author tanmingliang
 */

namespace DiPlugin\Resolver;

use Midi\Exception\ResolveInvalidParam;
use Midi\Resolver\ElasticResolver as BaseResolver;
use Midi\Container;
use Midi\Exception\Exception;
use Midi\Plugin\Event\CommandConfigureEvent;
use DiPlugin\Util\Helper;
use DiPlugin\DiConfig;
use DiPlugin\Message;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use GuzzleHttp\Client;

/**
 * Resolver session from elastic.
 *
 * You could search session by uri, request body keywords, response and upstream's request, upstream's response.
 *
 * Before use elastic, you should config `elastic-search-url` in your config.yml.
 */
class ElasticResolver extends BaseResolver
{
    /**
     * @var bool
     */
    private $allRecommend = false;

    /**
     * @var bool
     */
    private $allControllers = false;

    public static function onCommandConfigure(CommandConfigureEvent $event)
    {
        if (parent::onCommandConfigure($event)) {
            $event
                ->addOption('--all', '-a', InputOption::VALUE_NONE, 'Replay Nuwa recommend sessions')
                ->addOption('--all-controllers', '-A', InputOption::VALUE_NONE, 'Replay all controllers');

            return true;
        }

        return false;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $options
     * @return array
     * @throws Exception
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws \Throwable
     */
    public function resolve(InputInterface $input, OutputInterface $output, $options = [])
    {
        try {
            $sessions = parent::resolve($input, $output);
        } catch (ResolveInvalidParam $e) {
            $sessions = [];
        }

        $this->allControllers = $input->getOption('all-controllers');
        $this->allRecommend = $input->getOption('all');

        if (empty($sessions) && false === $this->allRecommend && false === $this->allControllers) {
            throw new Exception(Message::RUN_COMMAND_INVALID_PARAMS);
        }

        if ($this->allRecommend) {
            $sessions = array_merge($sessions, $this->loadRecommendSessions());
        }
        if ($this->allControllers) {
            $sessions = array_merge($sessions, $this->loadControllersSessions());
        }

        if (count($sessions) > 3) {
            $output->writeln("<info>If you want to display DIFF only, add <comment>`-D`</comment> option.</info>");
        }

        return $sessions;
    }

    /**
     * @param int $size 1
     * @param string $date 20181231
     *
     * @throws \Exception|\Throwable
     * @return array
     */
    public function loadRecommendSessions($size = 1, $date = null)
    {
        // query recommend dsl list
        $dslUrl = DiConfig::getRecommendDSLUrl();
        $client = new Client();
        $resp = $client->get($dslUrl);
        $ret = \GuzzleHttp\json_decode($resp->getBody(), true);
        if ($ret['errno'] != 0) {
            throw new Exception(sprintf("Query DSL Err. Url=%s, ErrMsg=%s.", $dslUrl, $ret['errmsg']));
        }

        if ($date == null) {
            $date = date("Y-m-d", time() - 86400 * 7);
        }

        $output = Container::make('output');
        $aDSL = [];
        foreach ($ret['data'] as $dsl) {
            if ($dsl['recommend'] != 1 || !isset($dsl['dsl'])) {
                continue;
            }
            $params = json_decode($dsl['dsl'], true);
            if (json_last_error() != JSON_ERROR_NONE) {
                continue;
            }
            $params['size'] = $size;
            $params['begin'] = $date;
            $oDSL = new EsDSL();
            $oDSL->build($params);
            $aDSL[] = $oDSL;
        }

        /* @var $output \Symfony\Component\Console\Output\OutputInterface */
        $output->writeln('<info>Query ' . count($aDSL) . ' Recommend Sessions DSL.</info>');
        $recordSessions = static::asyncQuery($aDSL);
        $output->writeln("<info>Finish Query " . count($recordSessions) . " ES Sessions.</info>");

        return $recordSessions;
    }

    /**
     * @param int $size
     * @param null $date
     *
     * @return array
     * @throws Exception
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws \Throwable
     */
    public function loadControllersSessions($size = 1, $date = null)
    {
        if (null === $date) {
            $date = date("Y-m-d", time() - 86400 * 7);
        }

        $output = Container::make('output');
        $aDSL = [];
        foreach (Helper::generateAllUri() as $uri) {
            $params['inbound_request'] = $uri;
            $params['size'] = $size;
            $params['begin'] = $date;
            $oDSL = new EsDSL();
            $oDSL->build($params);
            $aDSL[] = $oDSL;
        }

        /* @var $output \Symfony\Component\Console\Output\OutputInterface */
        $output->writeln('<info>Query '.count($aDSL).' sessions by Controller URI.</info>');
        $recordSessions = static::asyncQuery($aDSL);
        $output->writeln("<info>Finish Query ".count($recordSessions)." ES Sessions.</info>");

        return $recordSessions;
    }
}