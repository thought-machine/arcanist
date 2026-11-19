<?php

/**
 * Converts markers (like branches) into a flat list suitable for JSON output.
 */
final class ArcanistMarkersJSONBuilder
  extends Phobject {

  private $markers = array();
  private $stateRefs = array();

  public function setMarkers(array $markers) {
    $this->markers = $markers;
    return $this;
  }

  public function setStateRefs(array $state_refs) {
    $this->stateRefs = $state_refs;
    return $this;
  }

  public function buildList() {
    $results = array();

    foreach ($this->markers as $marker) {
      $revisions = array();
      $state_ref = idx($this->stateRefs, $marker->getCommitHash());
      if ($state_ref) {
        foreach ($state_ref->getRevisionRefs() as $revision_ref) {
          $revisions[] = array(
            'id' => $revision_ref->getID(),
            'monogram' => $revision_ref->getMonogram(),
            'title' => $revision_ref->getName(),
            'uri' => $revision_ref->getURI(),
            'status' => $revision_ref->getStatus(),
          );
        }
      }

      $results[] = array(
        'name' => $marker->getName(),
        // In git, "active" means the branch is currently checked out (the one
        // "HEAD" points at). With a detached HEAD, no branch is active.
        'active' => (bool)$marker->getIsActive(),
        'commit' => $marker->getCommitHash(),
        'summary' => $marker->getSummary(),
        'revisions' => $revisions,
      );
    }

    return $results;
  }

}
