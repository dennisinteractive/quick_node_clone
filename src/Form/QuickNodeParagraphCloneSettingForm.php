<?php
namespace Drupal\quick_node_clone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class QuickNodeParagraphCloneSettingForm extends ConfigFormBase {
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
    return 'quick_node_clone_paragraph_setting_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
      $paragraph_bundles = entity_get_bundles("paragraph");
      if(!empty($paragraph_bundles)) {
        $para_bundle_list = [];
        foreach($paragraph_bundles as $paragraph => $label) {
            $para_bundle_list[$paragraph]  = $label['label'];
        }      
        $form['para'] = [
            '#type' => 'checkboxes',
            '#title' => 'Content Types',
            '#options' => $para_bundle_list,
            '#default_value' => $this->config('quick_node_clone.settings')->get('para'),
            '#ajax' => [
                'callback' => 'Drupal\quick_node_clone\Form\QuickNodeParagraphCloneSettingForm::paraFieldsCallback',
                'wrapper' => 'pfields-list', 
            ],
        ];
        $desc = "No paragraph selected";
        if(array_filter($form_state->getValue('para'))) {
            $para_bundles = $form_state->getValue('para');
            $desc = "";
        } else if(array_filter($this->config('quick_node_clone.settings')->get('para'))) {
            $para_bundles = $this->config('quick_node_clone.settings')->get('para');
            $desc = "";          
        }
        $form['pfields'] = [
            '#type' => 'details',
            '#prefix' => '<div id = "pfields-list" >',
            '#suffix' => '</div>',
            '#open' => TRUE,
            '#title' => 'Fields',
            '#description' => $desc,
        ];
        foreach($para_bundles as $k => $value) {
            if(!empty($value)) {
                $foptions = [];
                $fields = \Drupal::entityManager()->getFieldDefinitions('paragraph',$k);
                foreach($fields as $key => $f) {
                    if($f instanceof \Drupal\field\Entity\FieldConfig) {
                        $foptions[$f->getName()] = $f->getLabel();
                    }
                }
                $description = "";
                if(empty($foptions)) {
                    $description = "No Fields Available";
                }
                $form['pfields']['paragraph_' . $k] = [
                    '#type' => 'details',
                    '#title' => $value,
                    '#open' => TRUE,
                ];
                $form['pfields']['paragraph_' . $k][$k] = [
                    '#type' => 'checkboxes',
                    '#title' => 'Fields',
                    '#default_value' => $this->config('quick_node_clone.settings')->get($k),
                    '#options' => $foptions,
                    '#description' => $description,
                ];
            }
        }
      } else {
          $form['no_paragraph'] = [
            '#type' => 'markup',
            '#markup' => 'No paragraph available',
          ];
      }
      return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
   public function submitForm(array &$form, FormStateInterface $form_state) {
      $formvalues = $form_state->getValues();
      foreach($formvalues['para'] as $key => $values) {
        if(empty($value)) {
          $this->config('quick_node_clone.settings')->clear($key)->save();
        }
      }  
      foreach($formvalues as $key => $values) {
        $this->config('quick_node_clone.settings')->set($key, $values)->save();
      }
   }
   public static function paraFieldsCallback(array $form, FormStateInterface $form_state) {
        return $form['pfields']; 
   }
}