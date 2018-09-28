<?php
/**
 * Copyright 2018, Google LLC All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Bigtable;

use Google\ApiCore\ApiException;
use Google\ApiCore\Serializer;
use Google\Cloud\Bigtable\Exception\BigtableDataOperationException;
use Google\Cloud\Bigtable\V2\BigtableClient as TableClient;
use Google\Cloud\Bigtable\V2\RowRange;
use Google\Cloud\Bigtable\V2\RowSet;
use Google\Cloud\Core\ArrayTrait;
use Google\Cloud\Core\ClientTrait;
use Google\Rpc\Code;

/**
 * Represents a DataOperation Client to perform data operation on Bigtable table.
 * This is used to perform insert,update, delete operation on table in Bigtable.
 *
 * Example:
 * ```
 * use Google\Cloud\Bigtable\DataClient;
 *
 * $dataClient = new DataClient('my-instance', 'my-table');
 * ```
 *
 */
class DataClient
{
    use ArrayTrait;
    use ClientTrait;

    /**
     * @var string
     */
    private $instanceId;

    /**
     * @var string
     */
    private $tableId;

    /**
     * @var TableClient
     */
    private $bigtableClient;

    /**
     * @var array
     */
    private $options;

    /**
     * @var string
     */
    private $tableName;

    /**
     * Create a Bigtable data client.
     *
     * @param string $instanceId The instance id.
     * @param string $tableId The table id on which operation to be performed.
     * @param array $config [optional] {
     *     Configuration Options.
     *
     *      @type string $appProfileId The appProfileId to be used.
     *      @type array $headers The headers to be passed to request.
     *      @type TableClient $bigtableClient The GAPIC Bigtable client to use.
     *            If not provided it will create one.
     *     @type array $keyFile The contents of the service account credentials
     *           .json file retrieved from the Google Developer's Console.
     *           Ex: `json_decode(file_get_contents($path), true)`.
     *     @type string $keyFilePath The full path to your service account
     *           credentials .json file retrieved from the Google Developers
     *           Console.
     * }
     */
    public function __construct($instanceId, $tableId, array $config = [])
    {
        $this->serializer = new Serializer();
        $this->instanceId = $instanceId;
        $this->tableId = $tableId;
        $this->options = [];
        if (isset($config['appProfileId'])) {
            $this->options['appProfileId'] = $config['appProfileId'];
        }
        if (isset($config['headers'])) {
            $this->options['headers'] = $config['headers'];
        }
        $config = $this->configureAuthentication($config);
        if (isset($config['bigtableClient'])) {
            $this->bigtableClient = $config['bigtableClient'];
        } else {
            // Temp workaround for large messages.
            $config['transportConfig'] = [
                'grpc' => [
                    'stubOpts' => [
                        'grpc.max_send_message_length' => -1,
                        'grpc.max_receive_message_length' => -1
                    ]
                ]
            ];
            $this->bigtableClient = new TableClient($config);
        }
        $this->tableName = TableClient::tableName($this->projectId, $instanceId, $tableId);
    }

    /**
     * Mutates rows in a table.
     *
     * Example:
     * ```
     * use Google\Cloud\Bigtable\DataClient;
     * use Google\Cloud\Bigtable\RowMutation;
     *
     * $rowMutation = new RowMutation('r1');
     * $rowMutation->upsert('cf1','cq1','value1',1534183334215000);
     *
     * $dataClient->mutateRows([$rowMutation]);
     * ```
     * @param array $rowMutations array of RowMutation object.
     * @param array $options [optional] Configuration options.
     * @return void
     * @throws ApiException|BigtableDataOperationException if the remote call fails or operation fails
     */
    public function mutateRows(array $rowMutations, array $options = [])
    {
        $entries = [];
        foreach ($rowMutations as $rowMutation) {
            $entries[] = $rowMutation->getEntry();
        }
        $responseStream = $this->bigtableClient->mutateRows($this->tableName, $entries, $options + $this->options);
        $rowMutationsFailedResponse = [];
        $failureCode = Code::OK;
        $message = 'partial failure';
        foreach ($responseStream->readAll() as $mutateRowsResponse) {
            $mutateRowsResponseEntries = $mutateRowsResponse->getEntries();
            foreach ($mutateRowsResponseEntries as $mutateRowsResponseEntry) {
                if ($mutateRowsResponseEntry->getStatus()->getCode() !== Code::OK) {
                    $failureCode = $mutateRowsResponseEntry->getStatus()->getCode();
                    $message = $mutateRowsResponseEntry->getStatus()->getMessage();
                    $rowMutationsFailedResponse[] = [
                        'rowKey' => $rowMutations[$mutateRowsResponseEntry->getIndex()]->getRowKey(),
                        'rowMutationIndex' => $mutateRowsResponseEntry->getIndex(),
                        'statusCode' => $failureCode,
                        'message' => $message
                    ];
                }
            }
        }
        if (!empty($rowMutationsFailedResponse)) {
            throw new BigtableDataOperationException($message, $failureCode, $rowMutationsFailedResponse);
        }
    }

    /**
     * Insert/update rows in a table.
     *
     * Example:
     * ```
     * use Google\Cloud\Bigtable\DataClient;
     *
     * $dataClient->upsert(['r1' => ['cf1' => ['cq1' => ['value'=>'value1', 'timeStamp' => 1534183334215000]]]]);
     * ```
     * @param array[] $rows array of row.
     * @param array $options [optional] Configuration options.
     * @return void
     * @throws ApiException|BigtableDataOperationException if the remote call fails or operation fails
     */
    public function upsert(array $rows, array $options = [])
    {
        $rowMutations = [];
        foreach ($rows as $rowKey => $families) {
            $rowMutation = new RowMutation($rowKey);
            foreach ($families as $family => $qualifiers) {
                foreach ($qualifiers as $qualifier => $value) {
                    if (isset($value['timeStamp'])) {
                        $rowMutation->upsert(
                            $family,
                            $qualifier,
                            $value['value'],
                            $value['timeStamp']
                        );
                    } else {
                        $rowMutation->upsert($family, $qualifier, $value['value']);
                    }
                }
            }
            $rowMutations[] = $rowMutation;
        }
        $this->mutateRows($rowMutations, $options);
    }

    /**
     * Read rows from the table.
     *
     * Example:
     * ```
     * $rows = $dataClient->readRows();
     *
     * foreach ($rows as $row) {
     *     print_r($row) . PHP_EOL;
     * }
     * ```
     *
     * // Specify a set of row ranges.
     * ```
     * $rows = $dataClient->readRows([
     *     'rowRanges' => [
     *         [
     *             'startKeyOpen' => 'jefferson',
     *             'endKeyOpen' => 'lincoln'
     *         ]
     *     ]
     * ]);
     *
     * foreach ($rows as $row) {
     *     print_r($row) . PHP_EOL;
     * }
     * ```
     *
     * @param array $options [optional] {
     *     Configuration options.
     *
     *     @type string[] $rowKeys A set of row keys to read.
     *     @type array $rowRanges A set of row ranges. Each row range is an
     *           associative array which may contain a start key
     *           (`startKeyClosed` or `startKeyOpen`) and/or an end key
     *           (`endKeyOpen` or `endKeyClosed`).
     *     @type filter $filter A {@see Google\Cloud\Bigtable\Filter\FilterInterface} object to filter the rows.
     *     @type int $rowsLimit The number of rows to scan.
     * }
     * @return ChunkFormatter
     */
    public function readRows(array $options = [])
    {
        $rowKeys = $this->pluck('rowKeys', $options, false) ?: [];
        $ranges = $this->pluck('rowRanges', $options, false) ?: [];
        $filter = $this->pluck('filter', $options, false) ?: null;

        array_walk($ranges, function (&$range) {
            $range = $this->serializer->decodeMessage(
                new RowRange(),
                $range
            );
        });
        if (is_string($rowKeys)) {
            $rowKeys = [$rowKeys];
        }
        if ($ranges || $rowKeys) {
            $options['rows'] = $this->serializer->decodeMessage(
                new RowSet,
                [
                    'rowKeys' => $rowKeys,
                    'rowRanges' => $ranges
                ]
            );
        }

        if ($filter !== null) {
            $options['filter'] = $filter->toProto();
        }

        $serverStream = $this->bigtableClient->readRows(
            $this->tableName,
            $options + $this->options
        );
        return new ChunkFormatter($serverStream);
    }

    /**
     * Read a single row from the table.
     *
     * Example:
     * ```
     * $row = $dataClient->readRow('jefferson');
     *
     * print_r($row);
     * ```
     *
     * @param string $rowKey The row key to read.
     * @param array $options [optional] Configuration options.
     * @return array|null
     */
    public function readRow($rowKey, array $options = [])
    {
        return $this->readRows(
            ['rowKeys' => [$rowKey]],
            $options + $this->options
        )
            ->readAll()
            ->current();
    }
}
