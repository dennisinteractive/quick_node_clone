<?php
namespace Drupal\quick_node_clone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuickNodeParagraphCloneSettingForm extends ConfigFormBase {

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
   * The Entity Bundle Type Info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info')
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
    return 'quick_node_clone_paragraph_setting_form';
  }

  /**
   * QuickNodeParagraphCloneSettingForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, ConfigFactoryInterface $configFactory, EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $configFactory;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $paragraph_bundles = $this->entityTypeBundleInfo->getBundleInfo('paragraph');
    if (!empty($paragraph_bundles)) {
      $para_bundle_list = [];
      foreach ($paragraph_bundles as $paragraph => $label) {
        $para_bundle_list[$paragraph] = $label['label'];
      }
      $form['para'] = [
        '#type' => 'checkboxes',
        '#title' => 'Paragraph Types',
        '#options' => $para_bundle_list,
        '#default_value' => ($this->getSettings('para')) ? $this->getSettings('para') : [],
        '#ajax' => [
          'callback' => 'Drupal\quick_node_clone\Form\QuickNodeParagraphCloneSettingForm::paraFieldsCallback',
          'wrapper' => 'pfields-list',
        ],
      ];

      $form['pfields'] = [
        '#type' => 'details',
        '#prefix' => '<div id = "pfields-list" >',
        '#suffix' => '</div>',
        '#open' => TRUE,
        '#title' => 'Fields',
        '#description' => $this->getParaDescription($form_state),
      ];

      if ($paragraph_fields = $this->getParaFields($form_state)) {
        foreach ($paragraph_fields as $k => $value) {
          if (!empty($value)) {
            $foptions = [];
            $fields = $this->entityFieldManager->getFieldDefinitions('paragraph', $k);
            foreach ($fields as $key => $f) {
              if ($f instanceof FieldConfig) {
                $foptions[$f->getName()] = $f->getLabel();
              }
              $description = "";
              if (empty($foptions)) {
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
                '#default_value' => ($this->getSettings($k)) ? $this->getSettings($k) : [],
                '#options' => $foptions,
                '#description' => $description,
              ];
            }
          }
        }
      }
    }
    else {
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
    $form_state->cleanValues();
    $formvalues = $form_state->getValues();
    foreach ($formvalues['para'] as $key => $values) {
      if (empty($value)) {
        $this->config('quick_node_clone.settings')->clear($key)->save();
      }
    }
    foreach ($formvalues as $key => $values) {
      $this->config('quick_node_clone.settings')->set($key, $values)->save();
    }
  }

  public static function paraFieldsCallback(array $form, FormStateInterface $form_state) {
    return $form['pfields'];
  }

  /**
   * Get the correct description for the fields form.
   *
   * @param $form_state
   *
   * @return string
   */
  public function getParaDescription($form_state) {
    $desc = "No paragraph selected";
    if ($form_state->getValue('para') != NULL && array_filter($form_state->getValue('para'))) {
      $desc = '';
    }
    if (!empty($this->getSettings('para')) && array_filter($this->getSettings('para'))) {
      $desc = '';
    }
    return $desc;
  }

  /**
   * Get the paragraph bundles.
   *
   * @param $form_state
   *
   * @return array|mixed|null
   */
  public function getParaFields($form_state) {
    $para_bundles = NULL;
    if ($form_state->getValue('para') != NULL && array_filter($form_state->getValue('para'))) {
      $para_bundles = $form_state->getValue('para');
    }
    else {
      if (!empty($this->getSettings('para')) && array_filter($this->getSettings('para'))) {
        $para_bundles = $this->getSettings('para');
      }
    }
    return $para_bundles;
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
