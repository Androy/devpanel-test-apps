<?php

/**
 * @file
 * Contains \Drupal\field\Tests\EntityReference\Views\EntityReferenceRelationshipTest.
 */

namespace Drupal\field\Tests\EntityReference\Views;

use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Tests\ViewKernelTestBase;
use Drupal\views\Views;

/**
 * Tests entity reference relationship data.
 *
 * @group entity_reference
 *
 * @see core_field_views_data()
 */
class EntityReferenceRelationshipTest extends ViewKernelTestBase {

  use EntityReferenceTestTrait;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array(
    'test_entity_reference_entity_test_view',
    'test_entity_reference_reverse_entity_test_view',
    'test_entity_reference_entity_test_mul_view',
    'test_entity_reference_reverse_entity_test_mul_view',
    );

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['user', 'field', 'entity_test', 'views', 'entity_reference_test_views'];

  /**
   * The entity_test entities used by the test.
   *
   * @var array
   */
  protected $entities = array();

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');

    // Create reference from entity_test to entity_test_mul.
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_data', 'field_test_data', 'entity_test_mul');

    // Create reference from entity_test_mul to entity_test.
    $this->createEntityReferenceField('entity_test_mul', 'entity_test_mul', 'field_data_test', 'field_data_test', 'entity_test');

    ViewTestData::createTestViews(get_class($this), array('entity_reference_test_views'));
  }

  /**
   * Tests using the views relationship.
   */
  public function testNoDataTableRelationship() {

    // Create some test entities which link each other.
    $referenced_entity = EntityTestMul::create();
    $referenced_entity->save();

    $entity = EntityTest::create();
    $entity->field_test_data->target_id = $referenced_entity->id();
    $entity->save();
    $this->assertEqual($entity->field_test_data[0]->entity->id(), $referenced_entity->id());
    $this->entities[] = $entity;

    $entity = EntityTest::create();
    $entity->field_test_data->target_id = $referenced_entity->id();
    $entity->save();
    $this->assertEqual($entity->field_test_data[0]->entity->id(), $referenced_entity->id());
    $this->entities[] = $entity;

    Views::viewsData()->clear();

    // Check the generated views data.
    $views_data = Views::viewsData()->get('entity_test__field_test_data');
    $this->assertEqual($views_data['field_test_data']['relationship']['id'], 'standard');
    $this->assertEqual($views_data['field_test_data']['relationship']['base'], 'entity_test_mul_property_data');
    $this->assertEqual($views_data['field_test_data']['relationship']['base field'], 'id');
    $this->assertEqual($views_data['field_test_data']['relationship']['relationship field'], 'field_test_data_target_id');
    $this->assertEqual($views_data['field_test_data']['relationship']['entity type'], 'entity_test_mul');

    // Check the backwards reference.
    $views_data = Views::viewsData()->get('entity_test_mul_property_data');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['id'], 'entity_reverse');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['base'], 'entity_test');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['base field'], 'id');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['field table'], 'entity_test__field_test_data');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['field field'], 'field_test_data_target_id');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['field_name'], 'field_test_data');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['entity_type'], 'entity_test');
    $this->assertEqual($views_data['reverse__entity_test__field_test_data']['relationship']['join_extra'][0], ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE]);

    // Check an actual test view.
    $view = Views::getView('test_entity_reference_entity_test_view');
    $this->executeView($view);
    /** @var \Drupal\views\ResultRow $row */
    foreach ($view->result as $index => $row) {
      // Check that the actual ID of the entity is the expected one.
      $this->assertEqual($row->id, $this->entities[$index]->id());

      // Also check that we have the correct result entity.
      $this->assertEqual($row->_entity->id(), $this->entities[$index]->id());

      // Test the forward relationship.
      $this->assertEqual($row->entity_test_mul_property_data_entity_test__field_test_data_i, 1);

      // Test that the correct relationship entity is on the row.
      $this->assertEqual($row->_relationship_entities['field_test_data']->id(), 1);
      $this->assertEqual($row->_relationship_entities['field_test_data']->bundle(), 'entity_test_mul');

    }

    // Check the backwards reference view.
    $view = Views::getView('test_entity_reference_reverse_entity_test_view');
    $this->executeView($view);
    /** @var \Drupal\views\ResultRow $row */
    foreach ($view->result as $index => $row) {
      $this->assertEqual($row->id, 1);
      $this->assertEqual($row->_entity->id(), 1);

      // Test the backwards relationship.
      $this->assertEqual($row->field_test_data_entity_test_mul_property_data_id, $this->entities[$index]->id());

      // Test that the correct relationship entity is on the row.
      $this->assertEqual($row->_relationship_entities['reverse__entity_test__field_test_data']->id(), $this->entities[$index]->id());
      $this->assertEqual($row->_relationship_entities['reverse__entity_test__field_test_data']->bundle(), 'entity_test');
    }
  }

  /**
   * Tests views data generated for relationship.
   *
   * @see entity_reference_field_views_data()
   */
  public function testDataTableRelationship() {

    // Create some test entities which link each other.
    $referenced_entity = EntityTest::create();
    $referenced_entity->save();

    $entity = EntityTestMul::create();
    $entity->field_data_test->target_id = $referenced_entity->id();
    $entity->save();
    $this->assertEqual($entity->field_data_test[0]->entity->id(), $referenced_entity->id());
    $this->entities[] = $entity;

    $entity = EntityTestMul::create();
    $entity->field_data_test->target_id = $referenced_entity->id();
    $entity->save();
    $this->assertEqual($entity->field_data_test[0]->entity->id(), $referenced_entity->id());
    $this->entities[] = $entity;

    Views::viewsData()->clear();

    // Check the generated views data.
    $views_data = Views::viewsData()->get('entity_test_mul__field_data_test');
    $this->assertEqual($views_data['field_data_test']['relationship']['id'], 'standard');
    $this->assertEqual($views_data['field_data_test']['relationship']['base'], 'entity_test');
    $this->assertEqual($views_data['field_data_test']['relationship']['base field'], 'id');
    $this->assertEqual($views_data['field_data_test']['relationship']['relationship field'], 'field_data_test_target_id');
    $this->assertEqual($views_data['field_data_test']['relationship']['entity type'], 'entity_test');

    // Check the backwards reference.
    $views_data = Views::viewsData()->get('entity_test');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['id'], 'entity_reverse');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['base'], 'entity_test_mul_property_data');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['base field'], 'id');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['field table'], 'entity_test_mul__field_data_test');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['field field'], 'field_data_test_target_id');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['field_name'], 'field_data_test');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['entity_type'], 'entity_test_mul');
    $this->assertEqual($views_data['reverse__entity_test_mul__field_data_test']['relationship']['join_extra'][0], ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE]);

    // Check an actual test view.
    $view = Views::getView('test_entity_reference_entity_test_mul_view');
    $this->executeView($view);
    /** @var \Drupal\views\ResultRow $row */
    foreach ($view->result as $index => $row) {
      // Check that the actual ID of the entity is the expected one.
      $this->assertEqual($row->id, $this->entities[$index]->id());

      // Also check that we have the correct result entity.
      $this->assertEqual($row->_entity->id(), $this->entities[$index]->id());

      // Test the forward relationship.
      $this->assertEqual($row->entity_test_entity_test_mul__field_data_test_id, 1);

      // Test that the correct relationship entity is on the row.
      $this->assertEqual($row->_relationship_entities['field_data_test']->id(), 1);
      $this->assertEqual($row->_relationship_entities['field_data_test']->bundle(), 'entity_test');

    }

    // Check the backwards reference view.
    $view = Views::getView('test_entity_reference_reverse_entity_test_mul_view');
    $this->executeView($view);
    /** @var \Drupal\views\ResultRow $row */
    foreach ($view->result as $index => $row) {
      $this->assertEqual($row->id, 1);
      $this->assertEqual($row->_entity->id(), 1);

      // Test the backwards relationship.
      $this->assertEqual($row->field_data_test_entity_test_id, $this->entities[$index]->id());

      // Test that the correct relationship entity is on the row.
      $this->assertEqual($row->_relationship_entities['reverse__entity_test_mul__field_data_test']->id(), $this->entities[$index]->id());
      $this->assertEqual($row->_relationship_entities['reverse__entity_test_mul__field_data_test']->bundle(), 'entity_test_mul');
    }
  }

}