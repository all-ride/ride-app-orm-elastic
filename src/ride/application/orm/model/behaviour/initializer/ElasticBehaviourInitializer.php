<?php

namespace ride\application\orm\model\behaviour\initializer;

use ride\library\generator\CodeClass;
use ride\library\generator\CodeGenerator;
use ride\library\orm\definition\field\HasManyField;
use ride\library\orm\definition\field\ModelField;
use ride\library\orm\definition\field\PropertyField;
use ride\library\orm\definition\ModelTable;
use ride\library\orm\entry\LocalizedEntry;
use ride\library\orm\model\behaviour\initializer\BehaviourInitializer;
use ride\library\orm\OrmManager;

use \Exception;

/**
 * Setup the Elastic behaviour based on the model options
 */
class ElasticBehaviourInitializer implements BehaviourInitializer {

    /**
     * Constructs the behaviour initializer
     * @param \ride\library\orm\OrmManager $orm
     * @return nul
     */
    public function __construct(OrmManager $orm) {
        $this->orm = $orm;
    }

    /**
     * Gets the behaviours for the model of the provided model table
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return array An array with instances of Behaviour
     * @see \ride\library\orm\model\behaviour\Behaviour
     */
    public function getBehavioursForModel(ModelTable $modelTable) {
        return array();
    }

    /**
     * Generates the needed code for the entry class of the provided model table
     * @param \ride\library\orm\definition\ModelTable $table
     * @param \ride\library\generator\CodeGenerator $generator
     * @param \ride\library\generator\CodeClass $class
     * @return null
     */
    public function generateEntryClass(ModelTable $modelTable, CodeGenerator $generator, CodeClass $class) {
        $indexType = $modelTable->getOption('behaviour.elastic');
        if (!$indexType) {
            return;
        }

        if (!strpos($indexType, '/')) {
            throw new Exception('Could not initialize elastic behaviour: expecting \'index/type\' value');
        }

        $class->addImplements('ride\\application\\orm\\entry\\ElasticEntry');

        $code = $this->generateElasticDocumentMethod($modelTable);

        $getElasticDocumentMethod = $generator->createMethod('getElasticDocument', array(), $code);
        $getElasticDocumentMethod->setDescription('Gets the document which is indexed by Elastic search');
        $getElasticDocumentMethod->setReturnValue($generator->createVariable('result', 'array'));

        $class->addMethod($getElasticDocumentMethod);
    }

    /**
     * Generates the code for the getElasticDocument method for an entry
     * @param \ride\library\orm\definition\ModelTable $modelTable
     * @return string
     */
    private function generateElasticDocumentMethod(ModelTable $modelTable) {
        $code = '$properties = array();' . "\n\n";

        $fields = $modelTable->getFields();
        foreach ($fields as $field) {
            if ($field->getOption('elastic.omit')) {
                continue;
            }

            $code .= $this->generateFieldCode($field);
        }

        if ($modelTable->isLocalized()) {
            $code .= '$properties[\'locale\'] = $this->getLocale();' . "\n";
        }

        if ($modelTable->hasField('latitude') && $modelTable->hasField('longitude')) {
            $code .= 'if ($this->getLatitude() && $this->getLongitude()) {' . "\n";
            $code .= '    $properties[\'geo\'] = array(\'lat\' => $this->getLatitude(), \'lon\' => $this->getLongitude());' . "\n";
            $code .= '} else {' . "\n";
            $code .= '    $properties[\'geo\'] = null;' . "\n";
            $code .= '}' . "\n";
        }

        $code .= "\n" . 'return $properties;';

        return $code;
    }

    /**
     * Generates the code for a field in the getElasticDocument method
     * @param \ride\library\orm\definition\field\ModelField $field
     * @return string
     */
    private function generateFieldCode(ModelField $field) {
        if ($field instanceof PropertyField) {
            return $this->generatePropertyFieldCode($field);
        } elseif ($field instanceof HasManyField) {
            return $this->generateHasManyFieldCode($field);
        } else {
            return $this->generateBelongsToFieldCode($field);
        }
    }

    /**
     * Generates the code for a property field in the getElasticDocument method
     * @param \ride\library\orm\definition\field\PropertyField $field
     * @param string $instance Reference to the instance to retrieve the
     * property from
     * @param string $objectName Name of the object for properties of related
     * entries
     * @return string
     */
    private function generatePropertyFieldCode(PropertyField $field, $instance = '$this', $objectName = null) {
        $name = $field->getName();
        $type = $field->getType();
        if ($objectName) {
            $objectName = '[\'' . $objectName . '\']';
        }

        switch ($type) {
            case 'binary':
            case 'file':
            case 'image':
            case 'password':
            case 'pk':
            case 'serialize':
                return '';
            case 'boolean':
                if (substr($name, 0, 2) == 'is') {
                    $method = $name;
                } else {
                    $method = 'get' . ucfirst($name);
                }

                return '$properties' . $objectName . '[\'' . $name . '\'] = (boolean) ' . $instance . '->' . $method . '();' . "\n";
            case 'date':
            case 'datetime':
            case 'email':
            case 'richcontent':
            case 'string':
            case 'text':
            case 'website':
            case 'wysiwyg':
            case 'float':
            case 'integer':
            case 'time':
                return '$properties' . $objectName . '[\'' . $name . '\'] = ' . $instance . '->get' . ucfirst($name) . '();' . "\n";
            default:
                throw new Exception('Could not generate source for ' . $field->getName() . ': no type available for ' . $type);
        }
    }

    /**
     * Generates the code for a hasMany field in the getElasticDocument method
     * @param \ride\library\orm\definition\field\HasManyField $field
     * @return string
     */
    private function generateHasManyFieldCode(HasManyField $field) {
        $name = $field->getName();

        $code = "\n";
        $code = "\n";
        $code .= '$properties[\'' . $name . '\'] = array();' . "\n";
        $code = "\n";
        $code .= '$entries = $this->get' . ucfirst($name) . '();' . "\n";
        $code .= 'if ($entries) {' . "\n";
        $code .= '    foreach ($entries as $entry) {' . "\n";
        $code .= '        $properties[\'' . $name . '\'][] = (string) $entry;' . "\n";
        $code .= '    }' . "\n";
        $code .= '}' . "\n";

        return $code;
    }

    /**
     * Generates the code for a belongsTo or hasOne field in the
     * getElasticDocument method
     * @param \ride\library\orm\definition\field\ModelField $field
     * @return string
     */
    private function generateBelongsToFieldCode(ModelField $field) {
        $name = $field->getName();

        $modelName = $field->getRelationModelName($field);
        $model = $this->orm->getModel($modelName);

        $code = "\n";
        $code = "\n";
        $code .= '$entry = $this->get' . ucfirst($name) . '();' . "\n";
        $code .= 'if ($entry) {' . "\n";
        $code .= '    $properties[\'' . $name . '\'] = array();' . "\n";

        $properties = $model->getMeta()->getProperties();
        foreach ($properties as $propertyName => $property) {
            if ($property->getOption('elastic.omit')) {
                continue;
            }

            $propertyCode = $this->generatePropertyFieldCode($property, '$entry', $name);
            if ($propertyCode) {
                $code .= '    ' . $propertyCode;
            }
        }

        $code .= '} else {' . "\n";
        $code .= '    $properties[\'' . $name . '\'] = null;' . "\n";
        $code .= '}' . "\n";

        return $code;
    }

}
