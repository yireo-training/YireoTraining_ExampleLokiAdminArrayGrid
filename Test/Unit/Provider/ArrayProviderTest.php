<?php declare(strict_types=1);

namespace YireoTraining\ExampleLokiAdminArrayGrid\Test\Unit\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Loki\AdminComponents\Grid\Column\ColumnFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use YireoTraining\ExampleLokiAdminArrayGrid\Provider\ArrayProvider;

class ArrayProviderTest extends TestCase
{
    private Client&MockObject $client;
    private CacheInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testValidPayloadReturnsRowsWithoutDevVersions(): void
    {
        $this->client->expects($this->once())->method('request')
            ->with('GET', ArrayProvider::PACKAGES_URL, ArrayProvider::REQUEST_OPTIONS)
            ->willReturn(new Response(200, [], (string)json_encode(['packages' => [
                'yireo/foo' => [
                    ['version' => '1.0.0', 'release_date' => '2026-01-01'],
                    ['version' => 'dev-master', 'release_date' => '2026-01-02'],
                ],
            ]])));

        $expectedRows = [['package_name' => 'yireo/foo', 'version' => '1.0.0', 'release_date' => '2026-01-01']];
        $this->cache->expects($this->once())->method('save')->with(
            (string)json_encode($expectedRows),
            ArrayProvider::CACHE_KEY,
            $this->anything(),
            ArrayProvider::CACHE_LIFETIME
        );

        $provider = $this->getProvider();
        $this->assertSame($expectedRows, $provider->getData());
        $this->assertSame($expectedRows, $provider->getData());
    }

    public function testCachedRowsSkipTheRequest(): void
    {
        $rows = [['package_name' => 'yireo/foo', 'version' => '1.0.0', 'release_date' => '']];
        $this->cache->method('load')->willReturn((string)json_encode($rows));
        $this->client->expects($this->never())->method('request');

        $this->assertSame($rows, $this->getProvider()->getData());
    }

    public function testInvalidCacheFallsBackToTheRequest(): void
    {
        $this->cache->method('load')->willReturn('{invalid');
        $this->client->expects($this->once())->method('request')
            ->willReturn(new Response(200, [], '{"packages":{}}'));

        $this->assertSame([], $this->getProvider()->getData());
    }

    public function testRequestFailureReturnsNoRowsAndIsLogged(): void
    {
        $this->client->method('request')->willThrowException(
            new ConnectException('Connection timed out', new Request('GET', ArrayProvider::PACKAGES_URL))
        );
        $this->logger->expects($this->once())->method('warning');
        $this->cache->expects($this->never())->method('save');

        $this->assertSame([], $this->getProvider()->getData());
    }

    #[DataProvider('getInvalidPayloads')]
    public function testInvalidPayloadReturnsNoRows(string $payload): void
    {
        $this->client->method('request')->willReturn(new Response(200, [], $payload));
        $this->logger->expects($this->once())->method('warning');
        $this->cache->expects($this->never())->method('save');

        $this->assertSame([], $this->getProvider()->getData());
    }

    public static function getInvalidPayloads(): array
    {
        return [
            'malformed JSON' => ['{"packages":'],
            'empty body' => [''],
            'scalar' => ['"packages"'],
            'missing packages' => ['{"foo":[]}'],
            'packages is no array' => ['{"packages":"foo"}'],
        ];
    }

    public function testOversizedResponseIsRejected(): void
    {
        $this->client->method('request')->willReturn(
            new Response(200, [], str_repeat(' ', ArrayProvider::MAX_RESPONSE_BYTES + 1))
        );
        $this->logger->expects($this->once())->method('warning');

        $this->assertSame([], $this->getProvider()->getData());
    }

    public function testInvalidEntriesAreSkippedOrNormalized(): void
    {
        $this->client->method('request')->willReturn(new Response(200, [], (string)json_encode(['packages' => [
            'yireo/foo' => [
                ['version' => ['1.0.0']],
                ['version' => true],
                ['version' => ''],
                ['release_date' => '2026-01-01'],
                'foo',
                ['version' => 2, 'release_date' => ['2026-01-01']],
                ['version' => '1.0.1'],
            ],
            'yireo/bar' => 'foo',
            0 => [['version' => '1.0.0']],
        ]])));

        $this->assertSame([
            ['package_name' => 'yireo/foo', 'version' => '2', 'release_date' => ''],
            ['package_name' => 'yireo/foo', 'version' => '1.0.1', 'release_date' => ''],
        ], $this->getProvider()->getData());
    }

    public function testRowsAreCappedAtTheMaximum(): void
    {
        $versions = [];
        for ($i = 0; $i <= ArrayProvider::MAX_ROWS; $i++) {
            $versions[] = ['version' => '1.0.' . $i];
        }

        $this->client->method('request')->willReturn(
            new Response(200, [], (string)json_encode(['packages' => ['yireo/foo' => $versions]]))
        );

        $this->assertCount(ArrayProvider::MAX_ROWS, $this->getProvider()->getData());
    }

    private function getProvider(): ArrayProvider
    {
        return new ArrayProvider(
            $this->client,
            $this->createStub(ColumnFactory::class),
            $this->cache,
            new Json(),
            $this->logger
        );
    }
}
