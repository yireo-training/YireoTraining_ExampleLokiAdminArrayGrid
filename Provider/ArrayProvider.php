<?php
declare(strict_types=1);

namespace YireoTraining\ExampleLokiAdminArrayGrid\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use Loki\AdminComponents\Grid\Column\Column;
use Loki\AdminComponents\Grid\Column\ColumnFactory;
use Loki\AdminComponents\Provider\ArrayProviderInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ArrayProvider implements ArrayProviderInterface
{
    public const PACKAGES_URL = 'https://composer.yireo.com/packages.json';
    public const CACHE_KEY = 'yireo_training_loki_admin_array_grid_packages';
    public const CACHE_LIFETIME = 3600;
    public const MAX_RESPONSE_BYTES = 5242880;
    public const MAX_ROWS = 5000;
    public const REQUEST_OPTIONS = ['timeout' => 5, 'connect_timeout' => 3];

    private ?array $rows = null;

    public function __construct(
        private readonly Client $client,
        private readonly ColumnFactory $columnFactory,
        private readonly CacheInterface $cache,
        private readonly Json $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return [
            $this->columnFactory->create(['code' => 'package_name', 'label' => 'Composer package']),
            $this->columnFactory->create(['code' => 'version', 'label' => 'Version']),
            $this->columnFactory->create(['code' => 'release_date', 'label' => 'Release date']),
        ];
    }

    public function getData(): array
    {
        if ($this->rows === null) {
            $this->rows = $this->loadRows();
        }

        return $this->rows;
    }

    private function loadRows(): array
    {
        $cachedRows = $this->getCachedRows();
        if ($cachedRows !== null) {
            return $cachedRows;
        }

        try {
            $rows = $this->parseRows($this->fetch());
        } catch (GuzzleException|JsonException|RuntimeException $exception) {
            $this->logger->warning('Unable to load Composer packages: ' . $exception->getMessage());
            return [];
        }

        $this->cache->save(
            $this->serializer->serialize($rows),
            self::CACHE_KEY,
            [ConfigCache::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $rows;
    }

    private function getCachedRows(): ?array
    {
        $cachedRows = $this->cache->load(self::CACHE_KEY);
        if (!$cachedRows) {
            return null;
        }

        try {
            $rows = $this->serializer->unserialize($cachedRows);
        } catch (InvalidArgumentException) {
            return null;
        }

        return is_array($rows) ? $rows : null;
    }

    private function fetch(): string
    {
        $body = $this->client->request('GET', self::PACKAGES_URL, self::REQUEST_OPTIONS)->getBody();
        $contents = '';
        while (!$body->eof()) {
            $contents .= $body->read(8192);
            if (strlen($contents) > self::MAX_RESPONSE_BYTES) {
                throw new RuntimeException('Response exceeds the maximum size');
            }
        }

        return $contents;
    }

    private function parseRows(string $json): array
    {
        $composerData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $packages = is_array($composerData) ? ($composerData['packages'] ?? null) : null;
        if (!is_array($packages)) {
            throw new RuntimeException('Response contains no packages');
        }

        $rows = [];
        foreach ($packages as $packageName => $packageVersions) {
            if (!is_string($packageName) || !is_array($packageVersions)) {
                continue;
            }

            foreach ($packageVersions as $packageVersion) {
                if (!is_array($packageVersion)) {
                    continue;
                }

                $version = $this->toString($packageVersion['version'] ?? null);
                if ($version === null || $version === '' || str_starts_with($version, 'dev-')) {
                    continue;
                }

                $rows[] = [
                    'package_name' => $packageName,
                    'version' => $version,
                    'release_date' => (string)$this->toString($packageVersion['release_date'] ?? null),
                ];

                if (count($rows) >= self::MAX_ROWS) {
                    return $rows;
                }
            }
        }

        return $rows;
    }

    private function toString(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string)$value;
        }

        return null;
    }
}
