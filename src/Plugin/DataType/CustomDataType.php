<?php

namespace Drupal\custom_tables_datasource\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;



/**
 * A class that implements ComplexDataInterface extending Map/TypedData.
 * 
 * Each object represents a custom data item, in our case, a row from a custom table.
 * 
 * @see: https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21TypedData%21ComplexDataInterface.php/interface/ComplexDataInterface/10
 * 
 */
class CustomDataType extends Map
{

  /**
   * Constructs a CustomDataType object.
   *
   * @param array $row
   *   The row from the custom table.
   * 
   * @param string $name
   *   (optional) The name of the created property, or NULL if it is the root
   *   of a typed data tree. Defaults to NULL.
   * 
   * @param \Drupal\Core\TypedData\TypedDataInterface $parent
   *   (optional) The parent object of the data property, or NULL if it is the
   *   root of a typed data tree. Defaults to NULL.
   * 
   */
  public function __construct($row, $name=NULL, $parent=NULL)
  {

    \Drupal::logger('CustomDataType')->notice("__construct() running... ID: " . $row['id'] . " name: " . $name);

    $definition = new MyDefinition;

    parent::__construct($definition, $name, $parent);

    // Store the value
    $this->setValue($row);

    // \Drupal::logger('CustomDataType')->notice("__construct() running... properties count: "  . count($this->properties) );

    
  }


    /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE) {
    $properties = [];
    foreach ($this->definition->getPropertyDefinitions() as $name => $definition) {
      if ($include_computed || !$definition->isComputed()) {
        $properties[$name] = $this->get($name);
      }
    }
    // \Drupal::logger('CustomDataType')->notice("getProperties() running... properties count: "  . count($this->properties) );
    return $properties;
  }


    /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    if (!isset($this->properties[$property_name])) {
      $value = NULL;
      if (isset($this->values[$property_name])) {
        $value = $this->values[$property_name];
      }
      // If the property is unknown, this will throw an exception.
      $this->properties[$property_name] = $this->getTypedDataManager()->getPropertyInstance($this, $property_name, $value);
    }
    return $this->properties[$property_name];
  }



  /**
   * Overrides \Drupal\Core\TypedData\TypedData::setValue().
   *
   * @param array|null $values
   *   An array of property values.
   * @param bool $notify
   *   (optional) Whether to notify the parent object of the change. Defaults to
   *   TRUE. If a property is updated from a parent object, set it to FALSE to
   *   avoid being notified again.
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      throw new \InvalidArgumentException("Invalid values given. Values must be represented as an associative array.");
    }
    $this->values = $values;

    // Update any existing property objects.
    foreach ($this->properties as $name => $property) {
      $value = $values[$name] ?? NULL;
      $property->setValue($value, FALSE);
      // Remove the value from $this->values to ensure it does not contain any
      // value for computed properties.
      unset($this->values[$name]);
    }
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }


  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Update the values and return them.
    \Drupal::logger('CustomDataType')->notice("getValue() running... properties count: "  . count($this->properties) );
    foreach ($this->properties as $name => $property) {
      $definition = $property->getDataDefinition();
      if (!$definition->isComputed()) {
        $value = $property->getValue();
        \Drupal::logger('CustomDataType')->notice("getValue() running... definition not computed - value: " . $value );
        // Only write NULL values if the whole map is not NULL.
        if (isset($this->values) || isset($value)) {
          $this->values[$name] = $value;
        }
      }
      else {
        \Drupal::logger('CustomDataType')->notice("getValue() running... definition IS computed "  );
      }
    }
    return $this->values;
  }



}





class MyDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {

    if (!isset($this->propertyDefinitions)) {


      $this->propertyDefinitions['id'] = DataDefinition::create('integer')
        ->setLabel('ID')
        ->addConstraint('Range', ['min' => 0, 'max' => 255])
        ->setRequired(TRUE);

      $this->propertyDefinitions['name'] = DataDefinition::create('string')
        ->setLabel('Name')
        // ->addConstraint('Range', ['min' => 0, 'max' => 255])
        ->setRequired(TRUE);
      
      $this->propertyDefinitions['url'] = DataDefinition::create('uri')
        ->setLabel('URL')
        // ->addConstraint('Range', ['min' => 0, 'max' => 255])
        ->setRequired(TRUE);
        
        
      $this->propertyDefinitions['description'] = DataDefinition::create('string')
      ->setLabel('Description')
      // ->addConstraint('Range', ['min' => 0, 'max' => 255])
      ->setRequired(TRUE);

      $this->propertyDefinitions['price'] = DataDefinition::create('float')
        ->setLabel('Price')
        ->addConstraint('Range', ['min' => 0, 'max' => 1000000])
        ->setRequired(TRUE);

      $this->propertyDefinitions['category'] = DataDefinition::create('string')
        ->setLabel('Category')
        // ->addConstraint('Range', ['min' => 0, 'max' => 255])
        ->setRequired(TRUE);

    }

    return $this->propertyDefinitions;
  }

}
