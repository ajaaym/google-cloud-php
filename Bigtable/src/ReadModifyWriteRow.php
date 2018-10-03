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

use Google\Cloud\Bigtable\V2\ReadModifyWriteRule;

/**
 * Represents a ReadModifyWriteRow to perform data operation on Bigtable table.
 * This is used to readModifyWriteRow operation on row in Bigtable table.
 */
class ReadModifyWriteRow
{
    /**
     * @var string
     */
    private $rowKey;

    /**
     * @var array
     */
    private $options;

    /**
     * @var ReadModifyWriteRule
     */
    private $rules = [];

    public function __construct($rowKey, array $options = [])
    {
        $this->rowKey = $rowKey;
        $this->options = $options;
    }

    public function append($familyName, $qualifier, $value)
    {
        $rule = (new ReadModifyWriteRule)
            ->setFamilyName($familyName)
            ->setColumnQualifier($qualifier)
            ->setAppendValue($value);
        $rules[] = $rule;
        return $this;
    }

    public function increment($familyName, $qualifier, $amount)
    {
        $rule = (new ReadModifyWriteRule)
            ->setFamilyName($familyName)
            ->setColumnQualifier($qualifier)
            ->setIncrementAmount($amount);
        $rules[] = $rule;
        return $this;
    }

    public function getRowKey()
    {
        return $this->rowKey;
    }

    public function toRulesProto()
    {
        return $this->rules;
    }
}
