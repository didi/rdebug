<?php

/**
 * @author tanmingliang
 */

namespace Midi\Resolver;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Midi\Exception\ResolveInvalidParam;
use Midi\Message;

/**
 * Simplest resolver, find session from local files
 */
class FileResolver implements ResolverInterface
{
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $options
     * @return array
     * @throws ResolveInvalidParam
     */
    public function resolve(InputInterface $input, OutputInterface $output, $options = []) {
        $files = $input->getOption('file');
        if (empty($files)) {
            throw new ResolveInvalidParam(Message::RUN_COMMAND_INVALID_PARAMS);
        }

        $sessions = [];
        foreach ($files as $file) {
            $file = trim($file);
            if (!file_exists($file)) {
                $output->writeln("Session file '$file' not found!");
                continue;
            }
            $session = json_decode(file_get_contents($file), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $output->writeln("Session file '$file' not invalid json!");
                continue;
            }
            $sessions[] = $session;
        }

        return $sessions;
    }
}