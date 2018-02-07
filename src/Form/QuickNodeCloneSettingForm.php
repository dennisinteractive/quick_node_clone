<?php

namespace Drupal\quick_node_clone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
        '#description' => $this->t('Enter text to add to the title of a cloned node to help content editors. A space will be added between this text and the title. Example: "Clone of"')
      ];
      $node_type = node_type_get_types();
      $cck = [];
      foreach($node_type as $node => $nobj) {
        $cck[$nobj->id()] = $nobj->label();
      }      
      $form['cck'] = [
         '#type' => 'checkboxes',
         '#title' => 'Content Types',
         '#options' => $cck,
         '#default_value' => $this->config('quick_node_clone.settings')->get('cck'),
         '#ajax' => [
            'callback' => 'Drupal\quick_node_clone\Form\QuickNodeCloneSettingForm::fieldsCallback',
             'wrapper' => 'fields-list', 
          ],
      ];
      $desc = "No content Types Selected";
      if(array_filter($form_state->getValue('cck'))) {
        $desc = '';
        $ccks = $form_state->getValue('cck');
      } else if(array_filter($this->config('quick_node_clone.settings')->get('cck'))) {
        $desc = '';
        $ccks = $this->config('quick_node_clone.settings')->get('cck');
      } 
      $form['fields'] = [
         '#type' => 'details',
         '#prefix' => '<div id = "fields-list" >',
         '#suffix' => '</div>',
         '#open' => TRUE,
         '#title' => 'Fields',
         '#description' => $desc,
      ];
      foreach($ccks as $k => $value) {
        if(!empty($value)) {
         $foptions = [];
         $fields = \Drupal::entityManager()->getFieldDefinitions('node',$value);
         foreach($fields as $k => $f) {
            if($f instanceof \Drupal\field\Entity\FieldConfig) {
              $foptions[$f->getName()] = $f->getLabel();
            }
          }
          $form['fields']['cck_' . $value] = [
            '#type' => 'details',
            '#title' => $value,
            '#open' => TRUE,
          ];
          $form['fields']['cck_' . $value][$value] = [
            '#type' => 'checkboxes',
            '#title' => 'Fields',
            '#default_value' => $this->config('quick_node_clone.settings')->get($value),
            '#options' => $foptions,
          ];
        }
      }
      return parent::buildForm($form, $form_state);
  }
   /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
      $formvalues = $form_state->getValues();
      foreach($formvalues['cck'] as $key => $values) {
        if(empty($value)) {
          $this->config('quick_node_clone.settings')->clear($key)->save();
        }
      }  
      foreach($formvalues as $key => $values) {
        $this->config('quick_node_clone.settings')->set($key, $values)->save();
      } 
  }
  public static function fieldsCallback(array $form, FormStateInterface $form_state) {
    return $form['fields'];
  }
}