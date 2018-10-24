<?php

namespace Drupal\quick_node_clone\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * QuickNodeClone tests.
 *
 * @group Quick Node Clone
 */
class QuickNodeCloneTests extends WebTestBase {

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
  public static $modules = array('paragraphs', 'quick_node_clone');

  /**
   * A user with the 'Administer quick_node_clone' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'Administer Quick Node Clone Settings',
      'clone page content',
      'access contextual links',
      'access administration pages',
      'create page content',
      'edit any page content',
      'delete any page content',
    ]);
  }

  /**
   * Admin UI.
   */
  function testAdminUI() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/config/quick-node-clone');

    $title = $this->randomGenerator->sentences(10);
    $body =  $this->randomGenerator->word(10);
    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => $body,
      'body[0][format]' => 'basic_html',
    ];
    $this->drupalPostForm('node/add/page', $edit, t('Save'));
    $this->assertRaw($title);
    $this->assertRaw($body);

    $this->drupalGet('admin/config/quick-node-clone');
  }

}
