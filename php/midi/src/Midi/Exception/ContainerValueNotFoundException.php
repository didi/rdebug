<?php

namespace Midi\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Not Found Exception
 */
class ContainerValueNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
