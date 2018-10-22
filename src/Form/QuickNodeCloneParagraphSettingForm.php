<?php

namespace Drupal\quick_node_clone\Form;

/**
 * Module settings form.
 */
class QuickNodeCloneParagraphSettingForm extends QuickNodeCloneEntitySettingForm {

  /**
   * The machine name of the entity type.
   *
   * @var $entityTypeId
   *   The entity type id i.e. node
   */
  protected $entityTypeId = 'paragraph';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quick_node_clone_paragraph_setting_form';
  }

}
