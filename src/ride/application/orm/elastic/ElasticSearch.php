<?php

namespace ride\application\orm\elastic;

use ride\library\orm\model\Model;

/**
 * Search of entries in Elasticsearch
 */
class ElasticSearch extends AbstractElastic {

    /**
     * Search for model entries with the provided options
     * @param \ride\library\orm\model\Model $model Model to look for
     * @param array $options Array with search options
     * <ul>
     * <li>query: a string with a uri query for Elastic</li>
     * <li>limit: an optional value to limit the results, default to 50</li>
     * <li>offset: an optional value to set the offset on the result</li>
     * </ul>
     * @return array Result from the Elastic client
     */
    public function searchByQueryString(Model $model, array $options) {
        $parameters = $this->getParameters($model);
        if (!$parameters || !isset($options['query'])) {
            return false;
        }

        $parameters['body'] = array(
            'query' => array(
                'query_string' => array(
                    'query' => $options['query'],
                    'analyze_wildcard' => true,
                ),
            ),
            'size' => isset($options['limit']) ? $options['limit'] : 50,
            'from' => isset($options['offset']) ? $options['offset'] : 0,
        );

        return $this->client->search($parameters);
    }

    /**
     * Applies the result from Elastic search to the provided model query
     * @param array $result Result from the Elastic client
     * @param \ride\library\orm\query\ModelQuery $modelQuery
     * @return null
     */
    public function applyResultToModelQuery(array $result, ModelQuery $modelQuery) {
        if (!isset($result['hits']['hits'])) {
            $modelQuery->addCondition('{id} = 0');

            return;
        }

        $ids = array();
        foreach ($result['hits']['hits'] as $document) {
            $id = $document['_id'];
            if (strpos($id, '-')) {
                list($id, $locale) = explode('-', $id, 2);
            } else {
                $locale = null;
            }

            $ids[$id] = $id;
        }

        if ($ids) {
            $modelQuery->addCondition('{id} IN %1%', $ids);
        } else {
            $modelQuery->addCondition('{id} = %1%', 0);
        }
    }

}
