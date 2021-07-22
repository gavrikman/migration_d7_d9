<?php

namespace Drupal\seven_to_eight_migration\Plugin\migrate\process;

use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Drupal\Core\Database\Database;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\Core\File\FileSystemInterface;


/**
 * Class RelatedNodes for related nodes.
 *
 * @MigrateProcessPlugin (
 *    id = "video_files",
 * )
 */
class VideoMigrate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!isset($this->configuration['destination_field'])) {
      throw new MigrateException('Destination field not found.');
    }
    if (!isset($this->configuration['destination_bundle'])) {
      throw new MigrateException('Destination bundle not found.');
    }
    $destination_field = $this->configuration['destination_field'];
    $destination_bundle = $this->configuration['destination_bundle'];
    // image bundle

    if ($destination_bundle != 'video') {
      exit(0);
    }

    $d7_db = Database::getConnection('default', 'migrate');
    $d7_query = $d7_db->select('file_managed', 'fm')
      ->fields('fm', ['uid', 'filename', 'uri']);
    $d7_query->condition('fm.fid', $value['fid']);
    $d7_file_data = $d7_query->execute()->fetch();
    if (!is_object($d7_file_data)) {
      echo "\n" . 'File ' . $value['fid'] . ' not found. Node: ' . $row->getSourceProperty('title') . "\n";
    }
    else {
      $file = NULL;
      $file_date = file_get_contents(str_replace('public://', 'sites/default/files_drupal7/', $d7_file_data->uri));
      if (!$file_date) {
        echo "\n" . 'File ' . $d7_file_data->uri . ' not found. Node: ' . $row->getSourceProperty('title') . "\n";
      }
      else {
        $file = file_save_data($file_date, 'public://' . $d7_file_data->filename, FileSystemInterface::EXISTS_REPLACE);
      }
      if (!$file) {
        throw new MigrateException('File is not created.');
      }
      $d8_db = Database::getConnection('default', 'default');
      $query_media = $d8_db->select('media_field_data', 'm')
        ->fields('m', ['mid']);
      $query_media->join('media__' . $destination_field, 'mf', 'm.mid = mf.entity_id');
      $query_media->condition('m.bundle', $destination_bundle);
      $query_media->condition('m.name', $file->label());
      $query_media->condition('mf.' . $destination_field . '_target_id', $file->id());
      $data_media = $query_media->execute()->fetch();
      if (!is_object($data_media)) {
        $media = Media::create([
          'bundle' => 'video',
          'uid' => $d7_file_data->uid,
          'status' => 1,
          'name' => $d7_file_data->filename,
          $destination_field => [
            'target_id' => $file->id(),
          ],
        ]);
        $media->setPublished(TRUE)->save();
        return [
          'target_id' => $media->id(),
          'target_revision_id' => $media->getRevisionId(),
        ];
      }
    }
  }

}
