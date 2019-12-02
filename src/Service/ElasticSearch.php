<?php

declare(strict_types=1);

namespace Webgriffe\Esb\Service;

use Amp;
use Generator;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Webgriffe\AmpElasticsearch\Client;
use Webgriffe\AmpElasticsearch\Error;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webgriffe\Esb\Model\Job;
use Webgriffe\Esb\Model\JobInterface;
use Webgriffe\Esb\Exception\ElasticSearch\JobNotFoundException;
use Webmozart\Assert\Assert;

class ElasticSearch
{
    private const NO_SHARD_AVAILABLE_INDEX_MAX_RETRY = 10;

    /**
     * @var Client
     */
    private $client;
    /**
     * @var NormalizerInterface&DenormalizerInterface
     */
    private $normalizer;

    public function __construct(Client $client, $normalizer)
    {
        $this->client = $client;
        Assert::isInstanceOfAny($normalizer, [NormalizerInterface::class, DenormalizerInterface::class]);
        $this->normalizer = $normalizer;
    }

    public function indexJob(JobInterface $job, string $indexName): Amp\Promise
    {
        return Amp\call(function () use ($job, $indexName) {
            yield from $this->doIndexJob($job, $indexName, 0);
        });
    }

    public function fetchJob(string $uuid, string $indexName): Amp\Promise
    {
        return Amp\call(function () use ($uuid, $indexName) {
            try {
                $response = yield $this->client->getDocument($indexName, $uuid);
            } catch (Error $error) {
                if ($error->getCode() === 404) {
                    throw new JobNotFoundException($uuid);
                }
                throw $error;
            }
            Assert::keyExists($response, '_source');
            return $this->normalizer->denormalize($response['_source'], Job::class, 'json');
        });
    }

    /**
     * @param JobInterface $job
     * @param string $indexName
     * @param int $retry
     * @return Generator
     * @throws ExceptionInterface
     */
    private function doIndexJob(JobInterface $job, string $indexName, int $retry): Generator
    {
        try {
            yield $this->client->indexDocument(
                $indexName,
                $job->getUuid(),
                (array)$this->normalizer->normalize($job, 'json')
            );
        } catch (Error $error) {
            $errorData = $error->getData();
            $errorType = $errorData['error']['type'] ?? null;
            if ($errorType === 'no_shard_available_action_exception' &&
                $retry < self::NO_SHARD_AVAILABLE_INDEX_MAX_RETRY) {
                yield Amp\delay(1000);
                yield from $this->doIndexJob($job, $indexName, ++$retry);
                return;
            }
            throw $error;
        }
    }
}
