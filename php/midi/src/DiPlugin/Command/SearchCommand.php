<?php

/**
 * @author tanmingliang
 */

namespace DiPlugin\Command;

use DiPlugin\Message;
use DiPlugin\Resolver\ElasticResolver;
use DiPlugin\Resolver\EsDSL;
use Midi\Command\BaseCommand;
use Midi\Container;
use Midi\Koala\ParseRecorded;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SearchCommand extends BaseCommand
{
    const CMD = 'search';

    /** @var OutputInterface */
    private $output;

    const DEFAULT_SIZE = 5;

    protected function configure()
    {
        $this
            ->setName(self::CMD)
            ->setDescription('search request session')
            ->addOption('--request-in', '-i', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                '搜 URI 和 Body 等 接口输入关键字')
            ->addOption('--request-out', '-o', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '搜 接口返回值')
            ->addOption('--upstream-request', '-u', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '搜 下游请求')
            ->addOption('--upstream-response', '-p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                '搜 下游返回值')
            ->addOption('--apollo', '-a', InputOption::VALUE_REQUIRED, '搜 Apollo')
            ->addOption('--count', '-c', InputOption::VALUE_REQUIRED, '数据的条数，譬如 5', 0)
            ->addOption('--begin', '-b', InputOption::VALUE_REQUIRED, '开始时间，譬如 20180101')
            ->addOption('--end', '-e', InputOption::VALUE_REQUIRED, '结束时间，譬如 20181231')
            ->addOption('--run', '-r', InputOption::VALUE_NONE, '搜索后 直接运行')
            ->addOption('--exclude-key', '-E', InputOption::VALUE_OPTIONAL, '忽略某 Keys 的 DIFF', false)
            ->addOption('--display-diff-only', '-D', InputOption::VALUE_OPTIONAL, '是否只显示 DIFF，默认全部显示', false)
            ->addOption('--open', '-O', InputOption::VALUE_NONE, '回放流量后，直接在浏览器中打开报告')
            ->addOption('--report', '-R', InputOption::VALUE_NONE, '生成回放报告')
            ->setHelp('<info>php midi search</info>');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws \Midi\Exception\Exception
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $stopWatch    = Container::make('stopWatch');
        $stopWatch->start('search');

        $recordedSession = ElasticResolver::asyncQuery($this->parseParams($input));
        if (empty($recordedSession)) {
            // without context: record-host
            $recordedSession = ElasticResolver::asyncQuery($this->parseParams($input, false));
            if (empty($recordedSession)) {
                $output->writeln(Message::SEARCH_COMMAND_NO_SESSION);
                return;
            }
        }
        $output->writeln(sprintf(Message::SEARCH_COUNT_AND_SPENT, count($recordedSession),
            $stopWatch->stop('search')->getDuration()));

        if ($input->getOption('run')) {
            $runCommand = $this->getApplication()->find('run');
            $sessionIds = [];
            foreach ($recordedSession as $session) {
                $sessionIds[] = $session['SessionId'];
            }
            $runCommand->run($this->getRunCommandInput($input, $sessionIds), $this->output);
        } else {
            foreach ($recordedSession as $session) {
                $this->display($session);
            }
        }
    }

    /**
     * @param InputInterface $input
     *
     * @param bool $withContext
     * @return array
     * @throws \Midi\Exception\Exception
     */
    private function parseParams($input, $withContext = true)
    {
        $params = [
            'inbound_request'   => $input->getOption('request-in'),
            'inbound_response'  => $input->getOption('request-out'),
            'outbound_request'  => $input->getOption('upstream-request'),
            'outbound_response' => $input->getOption('upstream-response'),
            'apollo'            => $input->getOption('apollo'),
            'size'              => $input->getOption('count'),
            'begin'             => $input->getOption('begin'),
            'end'               => $input->getOption('end'),
        ];

        $dsl = new EsDSL();
        $dsl->build($params, $withContext);
        $aDSL = array_merge([$dsl,], $this->getFixParamsDSL($params));
        $this->output->writeln('<info>ES Query: <comment>' . $dsl->json() . '</comment></info>',
            OutputInterface::VERBOSITY_VERY_VERBOSE);
        return $aDSL;
    }

    private function display($session)
    {
        $fcgi = ParseRecorded::callFromInbound($session['CallFromInbound']);
        $resp = ParseRecorded::returnInbound($session['ReturnInbound']);
        $resp = end(explode("\n\r", $resp));

        $this->output->writeln("<info>< < < SessionId: <comment>" . $session['SessionId'] . "</comment> > > ></info>");

        if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            /* mini */
            $url     = $fcgi['params']['REQUEST_URI'];
            $pos     = strpos($url, '?');
            $onlyUri = $pos !== false ? substr($url, 0, $pos) : $url;
            $this->output->writeln("<info>Request: <comment>" . $onlyUri . " ...</comment> -v READ MORE</info>");
            $this->output->writeln("<info>Response: <comment>" . substr($resp, 0,
                    400) . " ...</comment> -v READ MORE</info>");
        } else {
            $this->output->writeln("<info>Request: <comment>" . $fcgi['params']['REQUEST_URI'] . "</comment></info>");
            $this->output->writeln("<info>Response: <comment>" . $resp . "</comment></info>");
        }

        $this->output->writeln('');
    }

    public function getFixParamsDSL($params)
    {
        if (!empty($params['outbound_request'])) {
            $params['outbound_request'] = sprintf('x%d%s',
                base_convert(strlen($params['outbound_request']), 10, 16),
                $params['outbound_request']
            );
            $dsl                        = new EsDSL();
            $dsl->build($params);
            return [$dsl];
        }
        return [];
    }

    public function getRunCommandInput(InputInterface $input, $sessionIds)
    {
        $runInput = ['--sessionId' => $sessionIds,];
        if ($input->getOption('exclude-key') !== false) {
            $runInput['--exclude-key'] = $input->getOption('exclude-key') ?? true;
        }
        if ($input->getOption('report') !== false) {
            $runInput['--report'] = $input->getOption('report') ?? true;
        }
        if ($input->getOption('open') !== false) {
            $runInput['--open'] = $input->getOption('open') ?? true;
        }
        $runInput['--display-diff-only'] = $input->getOption('display-diff-only'); // 透传

        return new ArrayInput($runInput);
    }
}
