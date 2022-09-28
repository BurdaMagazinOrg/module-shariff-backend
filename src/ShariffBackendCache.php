<?php

/**
 * @file
 * Contains \Drupal\shariff_backend\ShariffBackendCache.
 */

namespace Drupal\shariff_backend;

use Heise\Shariff\CacheInterface;

/**
 * Shariff backend cache class.
 */
class ShariffBackendCache implements CacheInterface {

  /**
   * Cache bin instance.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Cache options.
   *
   * @var array
   */
  protected $options;

  /**
   * Whether the Shariff backend database table exists.
   *
   * @var bool
   */
  protected $tableExists;

  /**
   * Constructs a new ShariffBackendCache.
   */
  public function __construct(array $options) {
    $this->options = $options;
    $this->database = \Drupal::database();
    $this->tableExists = $this->database->schema()->tableExists('shariff_backend');
  }

  /**
   * {@inheritdoc}
   */
  public function setItem(string $key, string $content): void {
    if (!$this->tableExists) {
      return;
    }
    $existing = $this->getItem($key);
    $content_decoded = json_decode($content, TRUE);
    $existing_decoded = !empty($existing) ? json_decode($existing, TRUE) : [];

    if (empty($content_decoded) && empty($existing_decoded)) {
      // No data available?
      $content = json_encode(NULL);
    }
    elseif (!empty($existing_decoded) && empty($content_decoded)) {
      // No new data available, but existing data available.
      $content = $existing;
    }
    elseif (!empty($existing_decoded)) {
      // Existing data and new data available.
      $content = json_encode(array_merge($existing_decoded, $content_decoded));
    }

    if (empty($content)) {
      // Simply do nothing.
      return;
    }

    // Create/update database record.
    $this->database
      ->merge('shariff_backend')
      ->fields([
        'url_hash' => $key,
        'data' => $content,
        'timestamp' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('url_hash', $key)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getItem(string $key): string {
    if ($this->tableExists) {
      $result = $this->query($key)
        ->fields('sb', [
          'data',
        ])
        ->execute()
        ->fetchAssoc();

      if (isset($result['data'])) {
        return $result['data'];
      }
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function hasItem(string $key): bool {
    if ($this->tableExists) {
      $query = $this->query($key);

      $query->condition('timestamp', \Drupal::time()->getRequestTime() - (!empty($this->options['ttl']) && is_numeric($this->options['ttl']) ? $this->options['ttl'] : 0), '>=');

      return $query
          ->countQuery()
          ->execute()
          ->fetchField() > 0;
    }

    return FALSE;
  }

  /**
   * Return select query.
   *
   * @param string $key
   *   An optional hash of the URL to query share counts for.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query used to query share counts.
   */
  protected function query($key = NULL) {
    $query = $this->database
      ->select('shariff_backend', 'sb');

    if (isset($key)) {
      $query->condition('url_hash', $key);
    }

    return $query;
  }

}
