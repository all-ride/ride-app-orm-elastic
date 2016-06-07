# Ride: ORM Elastic

This module will integrate Elasticsearch into the ORM.

To enable Elastic on your model, add the _behaviour.elastic_ property to your model options.
Set it to the _index/type_ of your data in the Elasticsearch server eg. geo/locations.

When this behaviour is enabled, a method getElasticDocument will be generated in your entry class.
This method will make the conversion between the ORM and Elastic.

The mapping to Elastic is based on your model definition. 
You can skip fields by adding the _elastic.omit_ option to the field.

You will need the Elastic ORM commands for the CLI to define the mapping and to index existing records.
Whenever a manipulation is done (insert, update or delete), the index is automatically updated.

_Note: when you enable the json API, you can add the elastic filter to add search through Elastic._


```xml
<model name="GeoLocation">
    <field name="path" type="string">
        <validation name="required"/>
    </field>
    <field name="parent" model="GeoLocation" relation="belongsTo">
        <option name="elastic.omit" value="true"/>
    </field>
    <field name="name" type="string" localized="true">
        <validation name="required"/>
    </field>

    <option name="behaviour.elastic" value="geo/locations"/>
    <option name="json.api" value="geo-locations"/>
    <option name="json.api.filters" value="query,exact,match,expression,elastic"/>
</model>
```
