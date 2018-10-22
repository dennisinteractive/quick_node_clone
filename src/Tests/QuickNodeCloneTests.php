<?php

namespace Drupal\quick_node_clone\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * QuickNodeClone tests.
 *
 * @group quick_node_clone
 */
class QuickNodeCloneTests extends WebTestBase {

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

    // Services to test.
    $this->services = ['facebook', 'email', 'tumblr', 'twitter'];

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser(array(
      'access administration pages',
      'administer quick_node_clone',
      'administer blocks',
      'access contextual links',
    ), 'QuickNodeClone Admin', TRUE); //@todo remove TRUE
  }

  /**
   * Check that an element exists in HTML markup.
   *
   * @param $xpath
   *   An XPath expression.
   * @param array $arguments
   *   (optional) An associative array of XPath replacement tokens to pass to
   *   DrupalWebTestCase::buildXPathQuery().
   * @param $message
   *   The message to display along with the assertion.
   * @param $group
   *   The type of assertion - examples are "Browser", "PHP".
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertElementByXPath($xpath, array $arguments = array(), $message, $group = 'Other') {
    $elements = $this->xpath($xpath, $arguments);
    return $this->assertTrue(!empty($elements[0]), $message, $group);
  }

  function testLinkToConfig() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/modules');
    $link = $this->xpath('//a[contains(@href, :href) and contains(@id, :id)]', [
      ':href' => 'admin/structure/quick_node_clone',
      ':id' => 'edit-modules-quick_node_clone-links-configure'
    ]);
    $this->assertTrue(count($link) === 1, 'Link to config is present');
  }

  /**
   * Admin UI.
   */
  function testAdminUI() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/quick_node_clone/default');
  }

}
