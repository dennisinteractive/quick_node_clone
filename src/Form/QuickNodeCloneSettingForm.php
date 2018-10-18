<?php

namespace Drupal\quick_node_clone\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Module settings form.
 */
class QuickNodeCloneSettingForm extends ConfigFormBase {

  /**
   * The Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['quick_node_clone.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quick_node_clone_setting_form';
  }

  /**
   * QuickNodeCloneSettingForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityFieldManagerInterface $entityFieldManager ) {
    $this->configFactory = $configFactory;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['text_to_prepend_to_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text to prepend to title'),
      '#default_value' => $this->getSettings('text_to_prepend_to_title'),
      '#description' => $this->t('Enter text to add to the title of a cloned node to help content editors. A space will be added between this text and the title. Example: "Clone of"'),
    ];
    $form['exclude'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exclusion list'),
    ];
    $form['exclude']['description'] = [
      '#markup' => $this->t('You can select fields that you do not want to be included when the node is cloned.'),
    ];
    $form['exclude']['node_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Types'),
      '#options' => $this->getNodeTypes(),
      '#default_value' => !is_null($this->getSettings('exclude.node')) ? array_keys($this->getSettings('exclude.node')) : [],
      '#description' => $this->t('Select content types above and you will see a list of fields that can be excluded.'),
      '#ajax' => [
        'callback' => 'Drupal\quick_node_clone\Form\QuickNodeCloneSettingForm::fieldsCallback',
        'wrapper' => 'fields-list',
        'method' => 'replace',
      ],
    ];

    $form['exclude']['fields'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Fields'),
      '#description' => $this->getDescription($form_state),
      '#prefix' => '<div id="fields-list">',
      '#suffix' => '</div>',
    ];

    if ($selected_node_types = $this->getSelectedNodeTypes($form_state)) {
      foreach ($selected_node_types as $node_type => $selected_fields) {
        if (!empty($selected_fields)) {
          $options = [];
          $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $node_type);
          foreach ($field_definitions as $k => $f) {
            if ($f instanceof FieldConfig) {
              $options[$f->getName()] = $f->getLabel();
            }
          }
          if (!empty($options)) {
            $form['exclude']['fields']['node_type_' . $node_type] = [
              '#type' => 'details',
              '#title' => $node_type,
              '#open' => TRUE,
            ];
            $form['exclude']['fields']['node_type_' . $node_type][$node_type] = [
              '#type' => 'checkboxes',
              '#title' => $this->t('Fields for @node_type', ['@node_type' => $node_type]),
              '#default_value' => $this->getDefaultFields($node_type),
              '#options' => $options,
            ];
          }
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $formvalues = $form_state->getValues();

    // Build an array of excluded fields for each node type.
    $node_types = [];
    foreach (array_filter($formvalues['node_types']) as $key => $type) {
      if (!empty($formvalues[$type])) {
        $node_types[$type] = array_values(array_filter($formvalues[$type]));
      }
    }

    // Build config array.
    $this->config('quick_node_clone.settings')->set('exclude.node', $node_types)->save();
  }

  public static function fieldsCallback(array $form, FormStateInterface $form_state) {
    return $form['exclude']['fields'];
  }

  /**
   * Get a list of content types.
   *
   * @return array
   */
  public function getNodeTypes() {
    $node_type = NodeType::loadMultiple();
    $node_types = [];
    foreach ($node_type as $node => $nobj) {
      $node_types[$nobj->id()] = $nobj->label();
    }
    return $node_types;
  }

  /**
   * Get the selected content types if there are any.
   *
   * @param $form_state
   *
   * @return array|mixed|null
   */
  public function getSelectedNodeTypes($form_state) {
    $selected_types = NULL;
    if ($form_state->getValue('node_types') != NULL && array_filter($form_state->getValue('node_types'))) {
      $selected_types = $form_state->getValue('node_types');
    }
    if (!empty($this->getSettings('exclude.node')) && array_filter($this->getSettings('exclude.node'))) {
      $selected_types = $this->getSettings('exclude.node');
    }

    return $selected_types;
  }

  /**
   * Get the correct description for the fields form.
   *
   * @param $form_state
   *
   * @return string
   */
  public function getDescription($form_state) {
    $desc = $this->t('No content Types Selected');
    if ($form_state->getValue('node_types') != NULL && array_filter($form_state->getValue('node_types'))) {
      $desc = '';
    }
    if (!empty($this->getSettings('exclude.node')) && array_filter($this->getSettings('exclude.node'))) {
      $desc = '';
    }

    return $desc;
  }

  /**
   * Get default fields.
   *
   * @param $value
   *
   * @return array|mixed|null|string
   */
  public function getDefaultFields($value) {
    $default_fields = [];
    if (!empty($this->getSettings('exclude.node.' . $value))) {
      $default_fields = $this->getSettings('exclude.node.' . $value);
    }

    return $default_fields;
  }

  /**
   * Get the settings.
   *
   * @param $value
   *
   * @return array|mixed|null
   */
  public function getSettings($value) {
    $settings = $this->configFactory->get('quick_node_clone.settings')->get($value);

    return $settings;
  }
}
