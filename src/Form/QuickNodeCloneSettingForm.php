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
    $form['exclude']['nodeTypes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content Types'),
      '#options' => $this->getNodeTypes(),
      '#default_value' => !is_null($this->getSettings('nodeTypes')) ? $this->getSettings('nodeTypes') : [],
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

    if ($selected_nodes = $this->getSelectedNodeTypes($form_state)) {
      foreach ($selected_nodes as $k => $value) {
        if (!empty($value)) {
          $options = [];

          $fields = $this->entityFieldManager->getFieldDefinitions('node', $value);
          foreach ($fields as $k => $f) {
            if ($f instanceof FieldConfig) {
              $options[$f->getName()] = $f->getLabel();
            }
            $form['exclude']['fields']['nodeTypes_' . $value] = [
              '#type' => 'details',
              '#title' => $value,
              '#open' => TRUE,
            ];
            $form['exclude']['fields']['nodeTypes_' . $value][$value] = [
              '#type' => 'checkboxes',
              '#title' => $this->t('Fields for @value', ['@value' => $value]),
              '#default_value' => $this->getDefaultFields($value),
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
    foreach ($formvalues['nodeTypes'] as $key => $values) {
      if (empty($values)) {
        $this->config('quick_node_clone.settings')->clear($key)->save();
      }
    }
    foreach ($formvalues as $key => $values) {
      $this->config('quick_node_clone.settings')->set($key, $values)->save();
    }
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
    $nodeTypes = [];
    foreach ($node_type as $node => $nobj) {
      $nodeTypes[$nobj->id()] = $nobj->label();
    }
    return $nodeTypes;
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
    if ($form_state->getValue('nodeTypes') != NULL && array_filter($form_state->getValue('nodeTypes'))) {
      $selected_types = $form_state->getValue('nodeTypes');
    }
    if (!empty($this->getSettings('nodeTypes')) && array_filter($this->getSettings('nodeTypes'))) {
      $selected_types = $this->getSettings('nodeTypes');
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
    if ($form_state->getValue('nodeTypes') != NULL && array_filter($form_state->getValue('nodeTypes'))) {
      $desc = '';
    }
    if (!empty($this->getSettings('nodeTypes')) && array_filter($this->getSettings('nodeTypes'))) {
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
    if (!empty($this->getSettings($value))) {
      $default_fields = $this->getSettings($value);
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
