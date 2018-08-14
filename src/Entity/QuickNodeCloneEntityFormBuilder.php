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
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
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

    // Clone all translations of a node.
    foreach ($new_node->getTranslationLanguages() as $langcode => $language) {
      /** @var \Drupal\node\Entity\Node $translated_node */
      $translated_node = $new_node->getTranslation($langcode);
      $translated_node = $this->cloneParagraphs($translated_node);
      $prepend_text = "";
      $config = $this->getConfigSettings('quick_node_clone.settings');
      if(!empty($config->get('text_to_prepend_to_title'))) {
        $prepend_text = $config->get('text_to_prepend_to_title') . " ";
      }
      $translated_node->setTitle(t($prepend_text . '@title', ['@title' => $original_entity->getTitle()], ['langcode' => $langcode]));
    }

    // Get the form object for the entity defined in entity definition
    $form_object = $this->entityManager->getFormObject($new_node->getEntityTypeId(), $operation);

    // Assign the form's entity to our duplicate!
    $form_object->setEntity($new_node);

    $form_state = (new FormState())->setFormState($form_state_additions);
    $new_form = $this->formBuilder->buildForm($form_object, $form_state);

    // If we are cloning addresses, we need to reset our delta counter
    // once the form is built.
    $tempstore = \Drupal::service('user.private_tempstore')->get('quick_node_clone');
    if ($tempstore->get('address_initial_value_delta') != NULL) {
      $tempstore->set('address_initial_value_delta', NULL);
    }

    return $new_form;
  }

  /**
   * Clone the paragraphs of a designated node. If we do not clone the
   * paragraphs attached to the node, the linked paragraphs will be linked
   * to two nodes which is not ideal.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to clone.
   *
   * @return \Drupal\node\Entity\Node
   *   The node with cloned paragraph fields.
   */
  public function cloneParagraphs($node) {
    // Unset excluded fields.
    if ($excludeFields = $this->getConfigSettings($node->getType())) {
      foreach($excludeFields as $key => $excludeField) {
        unset($node->{$excludeField});
      }
    }

    foreach ($node->getFieldDefinitions() as $field_definition) {
      $field_storage_definition = $field_definition->getFieldStorageDefinition();
      $field_settings = $field_storage_definition->getSettings();
      $field_name = $field_storage_definition->getName();
      if (isset($field_settings['target_type']) && $field_settings['target_type'] == "paragraph") {

        if (!$node->get($field_name)->isEmpty()) {
          foreach ($node->get($field_name) as $value) {
            if ($value->entity) {
              $bundle_config_key = $value->entity->bundle();
              $value->entity = $value->entity->createDuplicate();
              foreach($value->entity->getFieldDefinitions() as $field_definition) {
                $field_storage_definition = $field_definition->getFieldStorageDefinition();
                $pfield_settings = $field_storage_definition->getSettings();
                $pfield_name = $field_storage_definition->getName();

                // Check whether this field is excluded and if so unset.
                if ($this->excludeParagraphField($pfield_name, $bundle_config_key)) {
                  unset($value->entity->{$pfield_name});
                }

                $this->moduleHandler->alter('cloned_node_paragraph_field', $value->entity, $pfield_name, $pfield_settings);
              }
            }
          }
          $this->moduleHandler->alter('cloned_node', $node, $field_name, $field_settings);
        }
      }
    }
    return $node;
  }

  /**
   * Check whether to exclude the paragraph field.
   *
   * @param $entity_field_name
   * @param $bundle_config_key
   *
   * @return bool
   */
  public function excludeParagraphField($entity_field_name, $bundle_config_key) {
    $excludeBundles = $this->getConfigSettings('paragraphs');
    foreach ($excludeBundles as $bundle) {
      if ($excludeParagraphs = $this->getConfigSettings($bundle)) {
        foreach ($excludeParagraphs as $excludeParagraph) {
          if ($entity_field_name === $excludeParagraph && $bundle === $bundle_config_key) {
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
