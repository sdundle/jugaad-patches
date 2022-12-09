<?php

namespace Drupal\jugaad_patches\Plugin\Block;

use Endroid\QrCode\QrCode;
use Drupal\node\NodeInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Access\AccessResult;
use Endroid\QrCode\Writer\PngWriter;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with QR code generated.
 *
 * @Block(
 *   id = "jugaad_patches_block",
 *   admin_label = @Translation("App Purchase Link Block"),
 * )
 */
class AppPurchaseLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entityTypeManager object.
   *
   * @var object
   */
  public $entityTypeManager;

  /**
   * The route object.
   *
   * @var object
   */
  public $routeMatch;

  /**
   * The file system object.
   *
   * @var object
   */
  public $fileSystem;

  /**
   * The configuration object.
   *
   * @var object
   */
  public $config;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, RouteMatchInterface $routeMatch, FileSystem $fileSystem, ConfigFactory $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->routeMatch = $routeMatch;
    $this->fileSystem = $fileSystem;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Instantiates this form class.
    return new static(
          // Load the service required to construct this class.
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('entity_type.manager'),
          $container->get('current_route_match'),
          $container->get('file_system'),
          $container->get('config.factory')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    $qrCodeName = '';
    $qrCodeTitle = '';
    if ($node instanceof NodeInterface) {
      if ($node->getType() == 'product') {
        $nid = $node->id();
        $nodeObject = $this->entityTypeManager->getStorage('node')->load($nid);
        $qrCodeLink = $nodeObject->get('field_link')[0]->get('uri')->getValue();
        $qrCodeTitle = $nodeObject->get('field_link')[0]->get('title')->getValue();
        
        // Generate the QR code.
        $qrCode = new QrCode($qrCodeLink);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        $path = $this->fileSystem->realpath($this->config->get('system.file')->get('default_scheme') . "://");
        $qrCodeName = $nid . '.png';
        imagepng($result->getImage(), $path . '/' . $qrCodeName);
      }
    }
    

    return [
      '#theme' => 'apppurchaseblock',
      '#qrCodeLink' => 'public://' . $qrCodeName,
      '#qrCodeTitle' => $qrCodeTitle,
      '#attached' =>
              [
                'library' => ['jugaad_patches/jugaad_patches.custom_script'],
              ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['my_block_settings'] = $form_state->getValue('my_block_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
