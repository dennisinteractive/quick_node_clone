<?php

namespace Drupal\quick_node_clone\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
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

    // Check for entity fields which need to be duplicated as well.
    foreach ($new_node->getTranslationLanguages() as $langcode => $language) {
      $translated_node = $new_node->getTranslation($langcode);

      // Unset excluded fields.
      $excludeFields = $this->getConfigSettings($translated_node->getType());

      if (!empty($excludeFields)) {
        foreach($excludeFields as $key => $excludeField) {
          unset($translated_node->{$excludeField});
        }
      }

      foreach ($translated_node->getFieldDefinitions() as $field_definition) {
        $field_storage_definition = $field_definition->getFieldStorageDefinition();
        $field_settings = $field_storage_definition->getSettings();
        $field_name = $field_storage_definition->getName();

        if (isset($field_settings['target_type'])) {

          // Each entity will be duplicated, so we won't be editing the same as the parent in every clone.
          if (!$translated_node->get($field_name)->isEmpty()) {
            foreach ($translated_node->get($field_name) as $key => $value) {
              if ($value->entity instanceof FieldableEntityInterface) {
                $value->entity = $value->entity->createDuplicate();
                foreach ($value->entity->getFieldDefinitions() as $field_definition) {
                  if (!($field_definition instanceof FieldConfig)) {
                    continue;
                  }
                  $field_storage_definition = $field_definition->getFieldStorageDefinition();
                  $entity_field_settings = $field_storage_definition->getSettings();
                  $entity_field_name = $field_storage_definition->getName();

                  // Exclude target entity fields based on config.
                  $bundle_config_key = $field_name . '__' . $value->entity->getEntityTypeId() . '__' . $value->entity->bundle();
                  $field_config_key = $bundle_config_key . '__' . $entity_field_name;
                  if (!empty($excludeFields[$bundle_config_key]) || !empty($excludeFields[$field_config_key])) {
                    unset($value->entity->{$entity_field_name});
                  }

                  $this->moduleHandler->alter('cloned_node_entity_field', $value->entity, $entity_field_name, $entity_field_settings);
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
