<?php

/**
 * @file
 * Contains \Drupal\shariff_backend\ShariffBackend.
 */

namespace Drupal\shariff_backend;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Heise\Shariff\Backend;

/**
 * Shariff backend service class.
 */
class ShariffBackend implements ShariffBackendInterface {

  /**
   * A Shariff backend instance.
   *
   * @var Backend
   */
  protected $backend;

  /**
   * A Shariff backend options instance.
   *
   * @var ShariffBackendOptionsInterface
   */
  protected $options;

  /**
   * Constructs a new ShariffBackend.
   *
   * @param ShariffBackendOptionsInterface $options
   *   A Shariff backend options object.
   */
  public function __construct(ShariffBackendOptionsInterface $options) {
    $this->options = $options;
    $this->backend = new Backend($this->options->toArray());
    $this->backend->setLogger(\Drupal::logger('shariff_backend'));
  }

  /**
   * {@inheritdoc}
   */
  public function getCounts($url, $refresh = FALSE) {
    $cache = drupal_static(__METHOD__, []);
    $cid = md5($url);

    if (isset($cache[$cid]) && !$refresh) {
      // Return cached data.
      return $cache[$cid];
    }

    $backend_settings = \Drupal::config('shariff_backend.settings')->get();
    // Simulate share counts if configured in settings file.
    if (empty($backend_settings['simulate_counts'])) {
      // Alter the URL if needed.
      if (!empty($backend_settings['base_domain'])) {
        // Replace domain in URL with configured domain.
        $domain_current = \Drupal::service('router.request_context')->getCompleteBaseUrl();
        $url = str_replace($domain_current, $backend_settings['base_domain'], $url);
      }

      // Load request counts for given URL.
      $cache[$cid] = $this->backend->get($url);
    }
    else {
      // Generate dummy values.
      $cache[$cid] = [
        'facebook' => rand(10, 1000000),
        'pinterest' => rand(10, 100),
      ];
    }

    if ($cache[$cid]) {
      $sum = 0;

      // Process count values.
      foreach ($cache[$cid] as $key => &$count) {
        // Sum up counts.
        $sum += $count;

        // Format count.
        $count = [
          'raw' => $count,
          'formatted' => $this->formatCountValue($count),
        ];
      }

      // Add sum.
      $cache[$cid]['_sum'] = [
        'raw' => $sum,
        'formatted' => $this->formatCountValue($sum),
      ];
    }

    return $cache[$cid];
  }

  /**
   * {@inheritdoc}
   */
  public function getCountsMultiple(array $urls) {
    $result = [];

    foreach ($urls as $url) {
      $result[$url] = $this->getCounts($url);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Return formatted count value.
   *
   * @param int $count
   *   The count value to format.
   * @return string
   *   The formatted count value.
   */
  protected function formatCountValue($count) {
    if ($count >= 100000) {
      return number_format(floor($count / 1000), 0, ',', '.') . 'K';
    }

    return number_format($count, 0, ',', '.');
  }

}
