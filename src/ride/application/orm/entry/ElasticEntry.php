<?php

namespace ride\application\orm\entry;

/**
 * Interface for an entry with Elasticsearch support
 */
interface ElasticEntry {

    /**
     * Gets the elastic document
     * @return array Array with the properties for Elasticsearch
     */
    public function getElasticDocument();

}
