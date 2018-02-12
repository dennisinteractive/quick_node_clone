<?php

namespace Drupal\quick_node_clone\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds entity forms.
 */
class QuickNodeCloneEntityFormBuilder extends EntityFormBuilder {

  protected $formBuilder;

  /**
   * The Entity Bundle Type Info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * QuickNodeCloneEntityFormBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   */
  public function __construct (FormBuilderInterface $formBuilder, EntityTypeBundleInfoInterface $entityTypeBundleInfo, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, EntityTypeManagerInterface $entityTypeManager) {
    $this->formBuilder = $formBuilder;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(EntityInterface $original_entity, $operation = 'default', array $form_state_additions = array()) {

    // Clone the node using the awesome createDuplicate() core function.
    /** @var \Drupal\node\Entity\Node $new_node */
    $new_node = $original_entity->createDuplicate();

    // Check for paragraph fields which need to be duplicated as well.
    foreach ($new_node->getTranslationLanguages() as $langcode => $language) {
      $translated_node = $new_node->getTranslation($langcode);

      // Unset excluded fields.
      if ($excludeFields = $this->getConfigSettings($translated_node->getType())) {
        foreach($excludeFields as $key => $excludeField) {
          unset($translated_node->{$excludeField});
        }
      }

      foreach ($translated_node->getFieldDefinitions() as $field_definition) {
        $field_storage_definition = $field_definition->getFieldStorageDefinition();
        $field_settings = $field_storage_definition->getSettings();
        $field_name = $field_storage_definition->getName();
        if (isset($field_settings['target_type']) && $field_settings['target_type'] == 'paragraph') {

          // Each paragraph entity will be duplicated, so we won't be editing the same as the parent in every clone.
          if (!$translated_node->get($field_name)->isEmpty()) {
            foreach ($translated_node->get($field_name) as $value) {
              if ($value->entity) {
                $value->entity = $value->entity->createDuplicate();
                foreach ($value->entity->getFieldDefinitions() as $field_definition) {
                  $field_storage_definition = $field_definition->getFieldStorageDefinition();
                  $pfield_settings = $field_storage_definition->getSettings();
                  $pfield_name = $field_storage_definition->getName();

                  // Check whether this field is excluded and if so unset.
                  if ($this->excludeParagraphField($pfield_name)) {
                    unset($value->entity->{$pfield_name});
                  }
                  $this->moduleHandler->alter('cloned_node_paragraph_field', $value->entity, $pfield_name, $pfield_settings);
                }
              }
            }
          }
        }
        $this->moduleHandler->alter('cloned_node', $translated_node, $field_name, $field_settings);
      }
      $prepend_text = "";

      if ($qnc_config = $this->getConfigSettings('text_to_prepend_to_title')) {
        $prepend_text = $qnc_config . " ";
      }
      $translated_node->setTitle(t($prepend_text . '@title', ['@title' => $original_entity->getTitle()], ['langcode' => $langcode]));
    }

    // Get the form object for the entity defined in entity definition
    $form_object = $this->entityTypeManager->getFormObject($new_node->getEntityTypeId(), $operation);

    // Assign the form's entity to our duplicate!
    $form_object->setEntity($new_node);

    $form_state = (new FormState())->setFormState($form_state_additions);
    return $this->formBuilder->buildForm($form_object, $form_state);
  }

  /**
   * Check whether to exclude the paragraph field.
   *
   * @param $pfield_name
   *
   * @return bool
   */
  public function excludeParagraphField($pfield_name) {
    $paragraph_bundles = $this->entityTypeBundleInfo->getBundleInfo('paragraph');
    foreach ($paragraph_bundles as $paragraph => $p) {
      if ($excludeParagraphs = $this->getConfigSettings($paragraph)) {
        foreach ($excludeParagraphs as $excludeParagraph) {
          if ($pfield_name === $excludeParagraph) {
           return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the settings.
   *
   * @param $value
   *
   * @return array|mixed|null
   */
  public function getConfigSettings($value) {
    $settings = $this->configFactory->get('quick_node_clone.settings')->get($value);
    return $settings;
  }
}
