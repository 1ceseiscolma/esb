<?php

namespace Webgriffe\Esb;

use Amp\PHPUnit\TestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use org\bovigo\vfs\vfsStream;
use Pheanstalk\Pheanstalk;
use Symfony\Component\Yaml\Yaml;

class KernelTestCase extends BeanstalkTestCase
{
    /**
     * @var Kernel
     */
    protected static $kernel;

    /**
     * @param $additionalConfig
     */
    protected static function createKernel(array $additionalConfig)
    {
        $basicConfig = [
            'parameters' => [
                'beanstalkd' => self::getBeanstalkdConnectionUri(),
                'critical_events_to' => 'toemail@address.com',
                'critical_events_from' => 'From Name <fromemail@address.com>',
            ],
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => true,
                    'public' => true,
                ],
                TestHandler::class => ['class' => TestHandler::class],
                Logger::class => ['class' => Logger::class, 'arguments' => ['esb', ['@' . TestHandler::class]]],
            ]
        ];
        $config = array_replace_recursive($basicConfig, $additionalConfig);
        vfsStream::setup('root', null, ['config.yml' => Yaml::dump($config)]);
        self::$kernel = new Kernel(vfsStream::url('root/config.yml'));
    }

    /**
     * @return TestHandler
     * @throws \Exception
     */
    protected function logHandler()
    {
        return self::$kernel->getContainer()->get(TestHandler::class);
    }
}
