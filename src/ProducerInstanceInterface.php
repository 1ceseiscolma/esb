<?php
declare(strict_types=1);

namespace Webgriffe\Esb;

use Amp\Promise;

interface ProducerInstanceInterface
{
    /**
     * @return Promise
     */
    public function boot(): Promise;

    /**
     * @param null $data
     * @return Promise
     */
    public function produceAndQueueJobs($data = null): Promise;

    /**
     * @return ProducerInterface
     */
    public function getProducer(): ProducerInterface;
}
