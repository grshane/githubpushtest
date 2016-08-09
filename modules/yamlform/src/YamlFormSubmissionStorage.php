<?php

namespace Drupal\yamlform;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the YAML form submission storage.
 */
class YamlFormSubmissionStorage extends SqlContentEntityStorage implements YamlFormSubmissionStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions() {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
    $field_definitions = $this->entityManager->getBaseFieldDefinitions('yamlform_submission');

    // For now never let any see or export the serialize YAML data field.
    unset($field_definitions['data']);

    $definitions = [];
    foreach ($field_definitions as $field_name => $field_definition) {
      $definitions[$field_name] = [
        'title' => $field_definition->getLabel(),
        'name' => $field_name,
        'type' => $field_definition->getType(),
        'target_type' => $field_definition->getSetting('target_type'),
      ];
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function loadDraft(YamlFormInterface $yamlform, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    $query = $this->getQuery();
    $query->condition('in_draft', 1);
    $query->condition('yamlform_id', $yamlform->id());
    $query->condition('uid', $account->id());
    if ($source_entity) {
      $query->condition('entity_type', $source_entity->getEntityTypeId());
      $query->condition('entity_id', $source_entity->id());
    }
    else {
      $query->notExists('entity_type');
      $query->notExists('entity_id');
    }
    if ($entity_ids = $query->execute()) {
      return $this->load(reset($entity_ids));
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasPrevious(YamlFormInterface $yamlform, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    $query = $this->getQuery();
    $query->condition('in_draft', 0);
    $query->condition('yamlform_id', $yamlform->id());
    $query->condition('uid', $account->id());
    if ($source_entity) {
      $query->condition('entity_type', $source_entity->getEntityTypeId());
      $query->condition('entity_id', $source_entity->id());
    }
    else {
      $query->notExists('entity_type');
      $query->notExists('entity_id');
    }
    return ($query->execute()) ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    /** @var \Drupal\yamlform\YamlFormSubmissionInterface $entity */
    $entity = parent::doCreate($values);
    if (!empty($values['data'])) {
      $data = (is_array($values['data'])) ? $values['data'] : Yaml::decode($values['data']);
      $entity->setData($data);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    /** @var \Drupal\yamlform\YamlFormSubmissionInterface[] $yamlform_submissions */
    $yamlform_submissions = parent::loadMultiple($ids);

    // Load YAML form submission data.
    if ($sids = array_keys($yamlform_submissions)) {
      $result = Database::getConnection()->select('yamlform_submission_data', 'sd')
        ->fields('sd', ['sid', 'name', 'delta', 'value'])
        ->condition('sd.sid', $sids, 'IN')
        ->orderBy('sd.sid', 'ASC')
        ->orderBy('sd.name', 'ASC')
        ->orderBy('sd.delta', 'ASC')
        ->execute();
      $submissions_data = [];
      while ($record = $result->fetchAssoc()) {
        if ($record['delta'] === '') {
          $submissions_data[$record['sid']][$record['name']] = $record['value'];
        }
        else {
          $submissions_data[$record['sid']][$record['name']][$record['delta']] = $record['value'];
        }
      }

      // Set YAML form submission data via setData().
      foreach ($submissions_data as $sid => $submission_data) {
        $yamlform_submissions[$sid]->setData($submission_data);
        $yamlform_submissions[$sid]->setOriginalData($submission_data);
      }
    }

    return $yamlform_submissions;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(YamlFormInterface $yamlform = NULL, EntityInterface $source_entity = NULL, $limit = NULL, $max_sid = NULL) {
    $query = $this->getQuery()
      ->sort('sid');
    if ($yamlform) {
      $query->condition('yamlform_id', $yamlform->id());
    }
    if ($source_entity) {
      $query->condition('entity_type', $source_entity->getEntityTypeId());
      $query->condition('entity_id', $source_entity->id());
    }
    if ($limit) {
      $query->range(0, $limit);
    }
    if ($max_sid) {
      $query->condition('sid', $max_sid, '<=');
    }

    $entity_ids = $query->execute();
    $entities = $this->loadMultiple($entity_ids);
    $this->delete($entities);
    return count($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getTotal(YamlFormInterface $yamlform = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    $query = $this->getQuery()->count();
    if ($yamlform) {
      $query->condition('yamlform_id', $yamlform->id());
    }
    if ($source_entity) {
      $query->condition('entity_type', $source_entity->getEntityTypeId());
      $query->condition('entity_id', $source_entity->id());
    }
    if ($account) {
      $query->condition('uid', $account->id());
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxSubmissionId(YamlFormInterface $yamlform = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    $query = $this->getQuery();
    $query->sort('sid', 'DESC');
    if ($yamlform) {
      $query->condition('yamlform_id', $yamlform->id());
    }
    if ($source_entity) {
      $query->condition('entity_type', $source_entity->getEntityTypeId());
      $query->condition('entity_id', $source_entity->id());
    }
    if ($account) {
      $query->condition('uid', $account->id());
    }
    $query->range(0, 1);
    $result = $query->execute();
    return reset($result);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousSubmission(YamlFormSubmissionInterface $yamlform_submission, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    return $this->getSiblingSubmission($yamlform_submission, $source_entity, $account, 'previous');
  }

  /**
   * {@inheritdoc}
   */
  public function getNextSubmission(YamlFormSubmissionInterface $yamlform_submission, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    return $this->getSiblingSubmission($yamlform_submission, $source_entity, $account, 'next');
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityTypes(YamlFormInterface $yamlform) {
    $entity_types = Database::getConnection()->select('yamlform_submission', 's')
      ->distinct()
      ->fields('s', ['entity_type'])
      ->condition('s.yamlform_id', $yamlform->id())
      ->condition('s.entity_type', 'yamlform', '<>')
      ->orderBy('s.entity_type', 'ASC')
      ->execute()
      ->fetchCol();

    $entity_type_labels = \Drupal::entityManager()->getEntityTypeLabels();
    ksort($entity_type_labels);

    return array_intersect_key($entity_type_labels, array_flip($entity_types));
  }

  /**
   * {@inheritdoc}
   */
  protected function getSiblingSubmission(YamlFormSubmissionInterface $yamlform_submission, EntityInterface $entity = NULL, AccountInterface $account = NULL, $direction = 'previous') {
    $yamlform = $yamlform_submission->getYamlForm();

    $query = $this->getQuery();
    $query->condition('yamlform_id', $yamlform->id());
    $query->range(0, 1);

    if ($entity) {
      $query->condition('entity_type', $entity->getEntityTypeId());
      $query->condition('entity_id', $entity->id());
    }

    if ($account) {
      $access_any = $yamlform->access('view_any', $account);
      $entity_access_any = ($entity && $entity->access('yamlform_submission_view'));
      if (!$access_any && !$entity_access_any) {
        $query->condition('uid', $account->id());
      }
    }

    if ($direction == 'previous') {
      $query->condition('sid', $yamlform_submission->id(), '<');
      $query->sort('sid', 'DESC');
    }
    else {
      $query->condition('sid', $yamlform_submission->id(), '>');
      $query->sort('sid', 'ASC');
    }

    return ($entity_ids = $query->execute()) ? $this->load(reset($entity_ids)) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomColumns(YamlFormInterface $yamlform = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    // Get custom columns from the YAML form's state.
    if ($source_entity) {
      $source_key = $source_entity->getEntityTypeId() . '.' . $source_entity->id();
      $custom_column_names = $yamlform->getState("results.custom.columns.$source_key", []);
      // If the source entity does not have custom columns, then see if we
      // can use the main form as the default custom columns.
      if (empty($custom_column_names) && $yamlform->getState("results.custom.default", FALSE)) {
        $custom_column_names = $yamlform->getState('results.custom.columns', []);
      }
    }
    else {
      $custom_column_names = $yamlform->getState('results.custom.columns', []);
    }

    if (empty($custom_column_names)) {
      return $this->getDefaultColumns($yamlform, $source_entity, $account, $include_elements);
    }

    // Get custom column with labels.
    $columns = $this->getColumns($yamlform, $source_entity, $account, $include_elements);
    $custom_columns = [];
    foreach ($custom_column_names as $column_name) {
      if (isset($columns[$column_name])) {
        $custom_columns[$column_name] = $columns[$column_name];
      }
    }
    return $custom_columns;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultColumns(YamlFormInterface $yamlform = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    $columns = $this->getColumns($yamlform, $source_entity, $account, $include_elements);

    // Hide certain unnecessary columns, that have default set to FALSE.
    foreach ($columns as $column_name => $column) {
      if (isset($column['default']) && $column['default'] === FALSE) {
        unset($columns[$column_name]);
      }
    }

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function getColumns(YamlFormInterface $yamlform = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    $view_any = ($yamlform && $yamlform->access('submission_view_any')) ? TRUE : FALSE;

    $columns = [];

    // Submission id.
    $columns['sid'] = [
      'title' => t('#'),
    ];

    // UUID.
    $columns['uuid'] = [
      'title' => t('UUID'),
      'default' => FALSE,
    ];

    // Sticky (Starred/Unstarred).
    if (empty($account)) {
      $columns['sticky'] = [
        'title' => t('Starred'),
      ];

      // Notes.
      $columns['notes'] = [
        'title' => t('Notes'),
      ];
    }

    // Created.
    $columns['created'] = [
      'title' => t('Created'),
    ];

    // Completed.
    $columns['completed'] = [
      'title' => t('Completed'),
      'default' => FALSE,
    ];

    // Changed.
    $columns['changed'] = [
      'title' => t('Changed'),
      'default' => FALSE,
    ];

    // Source entity.
    if ($view_any && empty($source_entity)) {
      $columns['entity'] = [
        'title' => t('Submitted to'),
        'sort' => FALSE,
      ];
    }

    // Submitted by.
    if (empty($account)) {
      $columns['uid'] = [
        'title' => t('User'),
      ];
    }

    // Submission language.
    if ($view_any && \Drupal::moduleHandler()->moduleExists('language')) {
      $columns['langcode'] = [
        'title' => t('Language'),
      ];
    }

    // Remote address.
    $columns['remote_addr'] = [
      'title' => t('IP address'),
    ];

    // YAML form.
    if (empty($yamlform) && empty($source_entity)) {
      $columns['yamlform_id'] = [
        'title' => t('Form'),
      ];
    }

    // YAML form elements.
    if ($yamlform && $include_elements) {
      /** @var \Drupal\yamlform\YamlFormElementManagerInterface $yamlform_element_manager */
      $yamlform_element_manager = \Drupal::service('plugin.manager.yamlform.element');

      $elements = $yamlform->getElementsFlattenedAndHasValue();
      foreach ($elements as $element) {
        /** @var \Drupal\yamlform\YamlFormElementInterface $element_handler */
        $element_handler = $yamlform_element_manager->createInstance($element['#type']);
        $columns += $element_handler->getTableColumn($element);
      }
    }

    // Operations.
    if (empty($account)) {
      $columns['operations'] = [
        'title' => t('Operations'),
        'sort' => FALSE,
      ];
    }

    // Add name to all columns.
    foreach ($columns as $name => &$column) {
      $column['name'] = $name;
    }

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomSetting($name, $default, YamlFormInterface $yamlform = NULL, EntityInterface $source_entity = NULL) {
    // Return the default value is YAML form and source entity is not defined.
    if (!$yamlform && !$source_entity) {
      return $default;
    }

    $key = "results.custom.$name";
    if (!$source_entity) {
      return $yamlform->getState($key, $default);
    }

    $source_key = $source_entity->getEntityTypeId() . '.' . $source_entity->id();
    if ($yamlform->hasState("$key.$source_key")) {
      return $yamlform->getState("$key.$source_key", $default);
    }
    if ($yamlform->getState("results.custom.default", FALSE)) {
      return $yamlform->getState($key, $default);
    }
    else {
      return $default;
    }
  }

}
