<?php

namespace ride\application\orm\elastic;

use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Client;

use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\model\Model;
use ride\library\orm\OrmManager;

use \Exception;

/**
 * Mapper for ORM model definitions to Elastic search
 */
class ElasticMapper {

    /**
     * Constructs a new mapper
     * @param \Elasticsearch\Client $client
     * @param \ride\library\orm\OrmManager $orm
     */
    public function __construct(Client $client, OrmManager $orm) {
        $this->client = $client;
        $this->orm = $orm;
    }

    /**
     * Defines the indices for all ORM models in Elastic search. New indices
     * will be added, existing ones will be edited
     * @return null
     */
    public function defineIndices() {
        $models = $this->orm->getModels();

        $mappings = array();

        foreach ($models as $model) {
            $mapping = $this->getModelMapping($model);
            if (!$mapping) {
                continue;
            }

            $mappings[$mapping['index']][$mapping['type']] = $mapping['body'][$mapping['type']];
        }

        foreach ($mappings as $index => $types) {
            $parameters = array(
                'index' => $index,
                'body' => array(
                    'mappings' => $types,
                ),
            );

            try {
                $this->client->indices()->create($parameters);
            } catch (BadRequest400Exception $e) {
                foreach ($types as $type => $mapping) {
                    $parameters['type'] = $type;
                    $parameters['body'] = array($type => $mapping);

                    $this->client->indices()->putMapping($parameters);
                }
            }
        }
    }

    /**
     * Gets the mapping for the provided model
     * @param \ride\library\orm\model\Model $model
     * @return array Index mapping for Elastic
     */
    private function getModelMapping(Model $model) {
        $meta = $model->getMeta();

        $behaviour = $meta->getOption('behaviour.elastic');
        if (!$behaviour) {
            return;
        }

        list($index, $type) = explode('/', $behaviour);

        $mapping = array(
            'index' => $index,
            'type' => $type,
            'body' => array(
                $type => array(
                    '_source' => array(
                        'enabled' => true
                    ),
                    'properties' => $this->getFieldsMapping($meta->getFields()),
                ),
            ),
        );

        if ($meta->isLocalized()) {
            $mapping['body'][$type]['properties']['locale'] = array(
                'type' => 'string',
                'index' => 'not_analyzed',
            );
        }

        if (isset($mapping['body'][$type]['properties']['latitude']) && isset($mapping['body'][$type]['properties']['longitude'])) {
            $mapping['body'][$type]['properties']['geo'] = array(
                'type' => 'geo_point',
            );
        }

        return $mapping;
    }

    /**
     * Gets the Elastic mapping for the provided fields
     * @param array $fields Array with ModelField instances
     * @return array Array with the Elastic mapping for the probided fields
     */
    private function getFieldsMapping(array $fields) {
        $mapping = array();

        foreach ($fields as $fieldName => $field) {
            if ($field->getOption('elastic.omit')) {
                continue;
            }

            $fieldMapping = $this->getFieldMapping($field);
            if (!$fieldMapping) {
                continue;
            }

            $mapping[$fieldName] = $fieldMapping;
        }

        return $mapping;
    }

    /**
     * Gets the Elastic mapping for & model field
     * @param \ride\library\orm\definietion\field\ModelField
     * @return array
     */
    private function getFieldMapping(ModelField $field) {
        if ($field instanceof PropertyField) {
            return $this->getPropertyFieldMapping($field);
        } elseif ($field instanceof HasManyField) {
            return $this->getHasManyFieldMapping($field);
        } else {
            return $this->getBelongsToFieldMapping($field);
        }
    }

    /**
     * Gets the Elastic mapping for a property field
     * @param \ride\library\orm\definition\field\PropertyField $field
     * @return array
     */
    private function getPropertyFieldMapping(PropertyField $field) {
        $type = $field->getType();

        switch ($type) {
            case 'binary':
            case 'file':
            case 'image':
            case 'password':
            case 'serialize':
                return false;
            case 'boolean':
                return array(
                    'type' => 'boolean',
                );
            case 'date':
            case 'datetime':
                return array(
                    'type' => 'date',
                );
            case 'email':
            case 'richcontent':
            case 'string':
            case 'text':
            case 'website':
            case 'wysiwyg':
                return array(
                    'type' => 'string',
                );
            case 'float':
                return array(
                    'type' => 'float',
                );
            case 'pk':
            case 'integer':
            case 'time':
                return array(
                    'type' => 'long',
                );
            default:
                throw new Exception('Could not map ' . $field->getName() . ': no type available for ' . $type);
        }
    }

    /**
     * Gets the Elastic mapping for a hasMany field
     * @param \ride\library\orm\definition\field\HasManyField $field
     * @return array
     */
    private function getHasManyFieldMapping(HasManyField $field) {
        return array(
            'type' => 'string',
        );
    }

    /**
     * Gets the Elastic mapping for a belongsTo or hasOne field
     * @param \ride\library\orm\definition\field\ModelField $field
     * @return array
     */
    private function getBelongsToFieldMapping(ModelField $field) {
        $modelName = $field->getRelationModelName($field);
        $model = $this->orm->getModel($modelName);

        return array(
            'type' => 'object',
            'properties' => $this->getObjectMapping($model),
        );
    }

    /**
     * Gets the Elastic mapping for an object
     * @param \ride\library\orm\model\Model $model
     * @return array
     */
    private function getObjectMapping(Model $model) {
        $mapping = array();

        $meta = $model->getMeta();
        $properties = $meta->getProperties();
        foreach ($properties as $propertyName => $property) {
            $propertyMapping = $this->getPropertyFieldMapping($property);
            if (!$propertyMapping) {
                continue;
            }

            $mapping[$propertyName] = $propertyMapping;
        }

        return $mapping;
    }

}
