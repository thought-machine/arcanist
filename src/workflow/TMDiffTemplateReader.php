<?php

/**
 * Reads DIFF_TEMPLATE files from the repository to generate the placeholder message in `arc diff`.
 *
 * The class is stateful and shouldn't be persisted.
 */
final class TMDiffTemplateReader {

  private $knownPaths = array();

  public function generateSummary($working_copy, $paths) {
    $message = '';
    foreach ($paths as $path => $godknowswhat) {
      $message = $message.$this->summary($working_copy, $path);
    }
    return $message;
  }

  private function summary($working_copy, $path) {
    for ($dir = dirname($path); $dir != '.'; $dir = dirname($dir)) {
      if (array_key_exists($dir, $this->knownPaths)) {
        return '';  // We've already generated this message
      }
      $filename = $working_copy->getProjectPath($dir.'/DIFF_TEMPLATE');  // where oh where is the join_path function
      if (Filesystem::pathExists($filename)) {
        $this->knownPaths[$dir] = true;  // Mark this so we don't do it again.
        return Filesystem::readFile($filename);
      }
    }

    return '';  // No file was found, just leave it at the default
  }
}
