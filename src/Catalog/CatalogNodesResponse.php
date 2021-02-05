<?php declare(strict_types=1);

namespace DCarbone\PHPConsulAPI\Catalog;

/*
   Copyright 2016-2020 Daniel Carbone (daniel.p.carbone@gmail.com)

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
 */

use DCarbone\PHPConsulAPI\AbstractValuedQueryResponse;
use DCarbone\PHPConsulAPI\Error;
use DCarbone\PHPConsulAPI\QueryMeta;

/**
 * Class CatalogNodesResponse
 */
class CatalogNodesResponse extends AbstractValuedQueryResponse
{
    /** @var \DCarbone\PHPConsulAPI\Catalog\CatalogNode[]|null */
    public $Nodes = null;

    /**
     * CatalogNodesResponse constructor.
     * @param array|null $nodes
     * @param \DCarbone\PHPConsulAPI\QueryMeta|null $qm
     * @param \DCarbone\PHPConsulAPI\Error|null $err
     */
    public function __construct(?array $nodes, ?QueryMeta $qm, ?Error $err)
    {
        if (null !== $nodes) {
            $this->Nodes = [];
            foreach ($nodes as $node) {
                $this->Nodes[] = new Node($node);
            }
        }
        parent::__construct($qm, $err);
    }

    /**
     * @return \DCarbone\PHPConsulAPI\Catalog\CatalogNode[]|null
     */
    public function getValue()
    {
        return $this->Nodes;
    }
}
