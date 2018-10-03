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

use Google\Cloud\Bigtable\Filter\FilterInterface;
use Google\Cloud\Bigtable\Mutation;

/**
 * Represents a ConditionalRowMutation to perform data operation on Bigtable table.
 * This is used to checkAndMutate operation on row in Bigtable table.
 */
class ConditionalRowMutation
{
    /**
     * @var FilterInterface
     */
    private $predicateFilter;

    /**
     * @var Mutation
     */
    private $trueMutations;

    /**
     * @var Mutation
     */
    private $falseMutations;

    /**
     * @var array
     */
    private $options;

    public function __construct(FilterInterface $predicateFilter, array $options = [])
    {
        $this->predicateFilter = $predicateFilter;
        $this->options = $options;
    }

    public function setTrueMutations(Mutation $mutations)
    {
        $this->trueMutations = $mutations;
        return $this;
    }

    public function setFalseMutations(Mutation $mutations)
    {
        $this->falseMutations = $mutations;
    }

    public function toPredicateFilterProto()
    {
        return $this->predicateFilter->toProto();
    }

    public function toTrueMutationsProto()
    {
        if ($this->trueMutations !== null) {
            return $this->trueMutations->toProto();
        }
        return [];
    }

    public function toFalseMutationsProto()
    {
        if ($this->falseMutations !== null) {
            return $this->falseMutations->toProto();
        }
        return [];
    }
}
