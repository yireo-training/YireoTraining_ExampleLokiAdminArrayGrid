<?php
declare(strict_types=1);

namespace YireoTraining\ExampleLokiAdminArrayGrid\Provider;

use GuzzleHttp\Client;
use Loki\AdminComponents\Grid\Column\Column;
use Loki\AdminComponents\Grid\Column\ColumnFactory;
use Loki\AdminComponents\Provider\ArrayProviderInterface;

class ArrayProvider implements ArrayProviderInterface
{
    public function __construct(
        private Client $client,
        private ColumnFactory $columnFactory
    ) {
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return [
            $this->columnFactory->create(['code' => 'package_name', 'label' => 'Composer package']),
            $this->columnFactory->create(['code' => 'version',  'label' =>'Version']),
            $this->columnFactory->create(['code' => 'release_date',  'label' =>'Release date']),
        ];
    }

    public function getData(): array
    {
        $response = $this->client->get('https://composer.yireo.com/packages.json');
        $composerData = json_decode($response->getBody()->getContents(), true);

        $rows = [];

        foreach ($composerData['packages'] as $packageName => $packageVersions) {
            foreach ($packageVersions as $packageVersion) {
                if (str_starts_with($packageVersion['version'], 'dev-')) {
                    continue;
                }

                $rows[] = [
                    'package_name' => $packageName,
                    'version' => $packageVersion['version'],
                    'release_date' => $packageVersion['release_date'],
                ];
            }
        }

        return $rows;
    }
}
