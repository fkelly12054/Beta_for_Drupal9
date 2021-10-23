<?php

namespace Drupal\Tests\juicebox\Functional;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Html;

/**
 * Tests general file and non-image handling.
 *
 * @group Juicebox
 */
class JuiceboxFileCase extends JuiceboxCaseTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['node', 'field_ui', 'image', 'juicebox'];

  /**
   * The field name.
   *
   * @var string
   */
  protected $instFieldName = 'field_file';

  /**
   * The field type.
   *
   * @var string
   */
  public $instFieldType = 'file';

  /**
   * Define setup tasks.
   */
  public function setUp(): void {
    parent::setUp();
    // Create and login user.
    $this->webUser = $this->drupalCreateUser([
      'access content',
      'access administration pages',
      'administer site configuration',
      'administer content types',
      'administer nodes',
      'administer node fields',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($this->webUser);
    // Prep a node with an image/file field and create a test entity.
    $this->initNode();
    // Activte the field formatter for our new node instance.
    $this->activateJuiceboxFieldFormatter();
  }

  /**
   * Test the field formatter with a file field and file upload widget.
   */
  public function testFile() {
    // Create a test node with an image file.
    $this->createNodeWithFile();
    $node = $this->node;
    $xml_path = 'juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full';
    $xml_url = Url::fromRoute('juicebox.xml_field', [
      'entityType' => 'node',
      'entityId' => $node->id(),
      'fieldName' => $this->instFieldName,
      'displayName' => 'full',
    ])->toString();
    // Get the urls to the test image and thumb derivative used by default.
    $uri = File::load($node->{$this->instFieldName}[0]->target_id)->getFileUri();
    $test_image_url = \Drupal::entityTypeManager()
      ->getStorage('image_style')->load('juicebox_medium')->buildUrl($uri);
    $test_thumb_url = \Drupal::entityTypeManager()
      ->getStorage('image_style')->load('juicebox_square_thumb')->buildUrl($uri);
    // Check for correct embed markup as anon user.
    $this->drupalLogout();
    $this->drupalGet('node/' . $node->id());
    /*$this->assertRaw(trim(json_encode(['configUrl' => $xml_url]), '{}"'), 'Gallery setting found in Drupal.settings.');*/
    $this->assertSession()->pageTextContains(trim(json_encode(['configUrl' => $xml_url]), '{}"'));
    $this->assertSession()->pageTextContains('id="node--' . $node->id() . '--' . str_replace('_', '-', $this->instFieldName) . '--full"');
    $this->assertSession()->pageTextContains(Html::escape(file_url_transform_relative($test_image_url)));
    // Check for correct XML.
    $this->drupalGet($xml_path);
    $this->assertSession()->responseContains('<?xml version="1.0" encoding="UTF-8"?>', 'Valid XML detected.');
    $this->assertSession()->responseContains('imageURL="' . Html::escape($test_image_url), 'Test image found in XML.');
    $this->assertSession()->responseContains('thumbURL="' . Html::escape($test_thumb_url), 'Test thumbnail found in XML.');
    $this->assertSession()->responseContains('<juicebox gallerywidth="100%" galleryheight="100%" backgroundcolor="#222222" textcolor="rgba(255,255,255,1)" thumbframecolor="rgba(255,255,255,.5)" showopenbutton="TRUE" showexpandbutton="TRUE" showthumbsbutton="TRUE" usethumbdots="FALSE" usefullscreenexpand="FALSE">', 'Expected default configuration options set in XML.');
  }

  /**
   * Test the non-image handling feature.
   */
  public function testFileNonImage() {
    // Create a test node with a non-image file.
    $this->createNodeWithFile('text');
    $node = $this->node;
    // Check the XML as anon user.
    $this->drupalLogout();
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    // With the default settings we expect an "application-octet-stream.png"
    // value for both the image and the thumbnail.
    // $this->assertPattern('|imageURL=.*text.png.*thumbURL=.*text.png|',
    // 'Non-image mimetype placeholder found for image and thumbnail.');.
    $this->assertSession()->responseMatches('|imageURL=.*text.png.*thumbURL=.*text.png|');
    // Change the file handling option to "skip".
    $this->drupalLogin($this->webUser);
    $this->drupalGet('admin/structure/types/manage/' . $this->instBundle . '/display');
    $this->submitForm([], $this->instFieldName . '_settings_edit', 'entity-view-display-edit-form');
    $edit = [
      'fields[' . $this->instFieldName . '][settings_edit_form][settings][incompatible_file_action]' => 'skip',
    ];
    $this->submitForm($edit, 'Save');
    $savemsg = ('Gallery configuration changes saved.');
    $this->assertText($savemsg);
    // Re-check the XML. This time no image should appear at all.
    $this->drupalLogout();
    $this->drupalGet('juicebox/xml/field/node/' . $node->id() . '/' . $this->instFieldName . '/full');
    $this->assertRaw('<?xml version="1.0" encoding="UTF-8"?>', 'Valid XML detected.');
    $this->assertNoRaw('<image', 'Non-image items was skipped.');
    // @todo , Check other incompatible_file_action combinations.
  }

}
