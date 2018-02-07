<?php

namespace Drupal\quick_node_clone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Entity\EntityFieldManager;

class QuickNodeCloneSettingForm extends ConfigFormBase {

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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->configFactory->get('quick_node_clone.settings');
    $form['text_to_prepend_to_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text to prepend to title'),
      '#default_value' => $settings->get('text_to_prepend_to_title'),
      '#description' => $this->t('Enter text to add to the title of a cloned node to help content editors. A space will be added between this text and the title. Example: "Clone of"'),
    ];

    $form['nodeTypes'] = [
      '#type' => 'checkboxes',
      '#title' => 'Content Types',
      '#options' => $this->getNodeTypes(),
      '#default_value' => $this->config('quick_node_clone.settings')->get('nodeTypes'),
      '#ajax' => [
        'callback' => 'Drupal\quick_node_clone\Form\QuickNodeCloneSettingForm::fieldsCallback',
        'wrapper' => 'fields-list',
      ],
    ];

    $form['fields'] = [
      '#type' => 'details',
      '#prefix' => '<div id = "fields-list" >',
      '#suffix' => '</div>',
      '#open' => TRUE,
      '#title' => 'Fields',
      '#description' => $this->getDescription($form_state),
    ];

    if ($selected_nodes = $this->getSelectedNodeTypes($form_state)) {
      foreach ($selected_nodes as $k => $value) {
        if (!empty($value)) {
          $foptions = [];
          $fields = \Drupal::EntityManager()->getFieldDefinitions('node', $value);
          foreach ($fields as $k => $f) {
            if ($f instanceof \Drupal\field\Entity\FieldConfig) {
              $foptions[$f->getName()] = $f->getLabel();
            }
          }
          $form['fields']['nodeTypes_' . $value][$value] = [
            '#type' => 'checkboxes',
            '#title' => 'Fields for ' . $value,
            '#default_value' => $this->getDefaultFields($value),
            '#options' => $foptions,
          ];
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $formvalues = $form_state->getValues();
    foreach ($formvalues['nodeTypes'] as $key => $values) {
      if (empty($value)) {
        $this->config('quick_node_clone.settings')->clear($key)->save();
      }
    }
    foreach ($formvalues as $key => $values) {
      $this->config('quick_node_clone.settings')->set($key, $values)->save();
    }
  }

  public static function fieldsCallback(array $form, FormStateInterface $form_state) {
    return $form['fields'];
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
    if (!empty($this->config('quick_node_clone.settings')->get('nodeTypes')) && array_filter($this->config('quick_node_clone.settings')->get('nodeTypes'))) {
      $selected_types = $this->config('quick_node_clone.settings')->get('nodeTypes');
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
    $desc = "No content Types Selected";
    if ($form_state->getValue('nodeTypes') != NULL && array_filter($form_state->getValue('nodeTypes'))) {
      $desc = '';
    }
    if (!empty($this->config('quick_node_clone.settings')->get('nodeTypes')) && array_filter($this->config('quick_node_clone.settings')->get('nodeTypes'))) {
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
    $default_fields = '';
    if (!empty($this->config('quick_node_clone.settings')->get($value))) {
      $default_fields = $this->config('quick_node_clone.settings')->get($value);
    }
    return $default_fields;
  }
}
