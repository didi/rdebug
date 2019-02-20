<?php
/**
 * @author tanmingliang
 */

namespace Midi\Resolver;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface ResolverInterface
{
    public function resolve(InputInterface $input, OutputInterface $output, $options = []);
}