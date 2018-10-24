<?php

namespace Drupal\quick_node_clone\Tests;

//use Drupal\simpletest\WebTestBase;
use Drupal\paragraphs\Tests\Classic\ParagraphsTestBase;
//use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\field_ui\Tests\FieldUiTestTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\paragraphs\Tests\Classic\ParagraphsCoreVersionUiTestTrait;
/**
 * Tests node cloning excluding paragraph fields.
 *
 * @group Quick Node Clone
 */
class QuickNodeCloneExcludeParagraphFieldsTests extends ParagraphsTestBase {

  use FieldUiTestTrait, ParagraphsCoreVersionUiTestTrait, ParagraphsTestBaseTrait;

  /**
   * The installation profile to use with this test.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'quick_node_clone',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Crete paragraphs.
    $this->createParagraphs();

    // Since we don't have ajax here, we need to set the config manually then test the form.
    \Drupal::configFactory()->getEditable('quick_node_clone.settings')
      ->set('exclude.paragraph.paragraphed_test', ['field_text2'])
      ->save();
  }

  /**
   * Creates the paragraphs used by the tests.
   */
  protected function createParagraphs() {
    $this->addParagraphedContentType('paragraphed_test', 'field_paragraphs', 'entity_reference_paragraphs');

    $this->loginAsAdmin([
      'create paragraphed_test content',
      'edit any paragraphed_test content',
    ]);

    $paragraph_type = 'text_paragraph';
    $this->addParagraphsType($paragraph_type);
    $this->addParagraphsType('text');

    // Add two text fields to the text_paragraph type.
    static::fieldUIAddNewField('admin/structure/paragraphs_type/' . $paragraph_type, 'text1', 'Text 1', 'string', [], []);
    static::fieldUIAddNewField('admin/structure/paragraphs_type/' . $paragraph_type, 'text2', 'Text 2', 'string', [], []);
    $this->drupalPostAjaxForm('node/add/paragraphed_test', [], 'field_paragraphs_text_paragraph_add_more');
  }

  /**
   * Creates nodes used by the tests.
   */
  protected function createNode() {

  }

  /**
   * Test node clone excluding fields.
   */
  function testNodeCloneExcludeParagraphFields() {

    $this->loginAsAdmin([
      'create paragraphed_test content',
      'edit any paragraphed_test content',
      'Administer Quick Node Clone Settings',
      'clone paragraphed_test content',
    ]);

    // Creates a node.
    $this->createNode();

    // Create a node with a Paragraph.
    $this->drupalGet('node/add/paragraphed_test');
    $title_value = $this->randomGenerator->word(10);
    $text1 = $this->randomGenerator->word(10);
    $text2 = $this->randomGenerator->word(10);
    $edit = [
      'title[0][value]' => $title_value,
      'field_paragraphs[0][subform][field_text1][0][value]' => $text1,
      'field_paragraphs[0][subform][field_text2][0][value]' => $text2,
    ];
    $this->drupalPostAjaxForm(NULL, [], 'field_paragraphs_text_paragraph_add_more');
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($title_value);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertFieldByName('field_paragraphs[0][subform][field_text1][0][value]', $text1);
    $this->assertFieldByName('field_paragraphs[0][subform][field_text2][0][value]', $text2);
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertText($text1);
    $this->assertText($text2);

    // Clone node.
    $this->clickLink('Clone');
    $this->drupalGet('clone/' . $node->id() . '/quick_clone');
    $this->drupalPostForm('clone/' . $node->id() . '/quick_clone', [], 'Save');
    $this->assertRaw('Cloned from ' . $title_value);
    $this->assertText($text1);
    $this->assertNoText($text2);
  }

}
