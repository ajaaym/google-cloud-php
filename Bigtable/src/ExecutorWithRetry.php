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

class ExecutorWithRetry
{
    const MAX_RETRIES = 3;
    const MAX_DELAY_MICROSECONDS = 60000000;

    /**
     * @var array
     */
    private static $retryableStatusCodes = [4=>4, 10=>10, 14=>14];

    /**
     * @var int
     */
    private $maxRetries;

    /**
     * @var int
     */
    private $numRequestsMade;

    /**
     * @var callable
     */
    private $call;

    /**
     * @var array
     */
    private $options;

    public function __construct(callable $call, array $options = [])
    {
        $this->call = $call;
        $this->options = $options;
    }


    private function shouldRetry($statusCode)
    {
        if ($this->numRequestsMade === 0 ||
            (isset($this->retryableStatusCodes[$statusCode]) && $this->numRequestsMade < $this->maxRetries)
             ) {
            return true;
        }
        return false;
    }

    public function executeRetryWithBackoff(array $arguments = [])
    {
        if ($this->numRequestsMade > 0) {
            usleep($this->calculateDelay());
        }
        $this->numRequestsMade++;
        return call_user_func_array($this->call, $arguments);
    }

    /**
     * Calculates exponential delay.
     *
     * @return int
     */
    private function calculateDelay()
    {
        return min(
            mt_rand(0, 1000000) + (pow(2, $this->numRequestsMade) * 1000000),
            self::MAX_DELAY_MICROSECONDS
        );
    }
}
