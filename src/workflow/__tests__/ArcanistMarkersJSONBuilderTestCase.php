<?php

/**
 * Tests the conversion of branch markers into the flat list used by
 * `arc branches --json`.
 *
 * These use a real git repository so the markers are produced by the same
 * query the workflow uses, while revision refs (which normally come from
 * Conduit) are attached by hand.
 */
final class ArcanistMarkersJSONBuilderTestCase extends PhutilTestCase {

  // Fixtures delete their directories when destroyed, so hold a reference
  // to each one for as long as this test case exists.
  private $fixtures = array();

  public function testNoRevisions() {
    // A single branch with no associated revisions has an empty revision
    // list, and is reported as the active branch.
    $repo = $this->newRepository();

    $this->assertEqual(
      array(
        array(
          'name' => 'master',
          'active' => true,
          'commit' => $this->getHeadCommit($repo),
          'summary' => 'one',
          'revisions' => array(),
        ),
      ),
      $this->build($repo, array()));
  }

  public function testMultipleBranchesOnlyCurrentIsActive() {
    // Every branch gets an entry, and only the checked out one is active.
    $repo = $this->newRepository();
    $this->git($repo, 'checkout -q -b feature');
    $this->commit($repo, 'two');

    $result = ipull($this->build($repo, array()), null, 'name');

    $this->assertEqual(array('feature', 'master'), array_keys($result));
    $this->assertEqual(true, $result['feature']['active']);
    $this->assertEqual('two', $result['feature']['summary']);
    $this->assertEqual(false, $result['master']['active']);
    $this->assertEqual('one', $result['master']['summary']);
  }

  public function testRevisions() {
    // Revisions attached to a branch's commit are included with their
    // identifying information and status.
    $repo = $this->newRepository();
    $head = $this->getHeadCommit($repo);

    $revision = ArcanistRevisionRef::newFromConduit(
      array(
        'id' => 123,
        'fields' => array(
          'title' => 'Fix the thing',
          'uri' => 'https://example.com/D123',
          'status' => array(
            'name' => 'Needs Review',
            'value' => 'needs-review',
            'closed' => false,
          ),
        ),
      ));

    $this->assertEqual(
      array(
        array(
          'id' => 123,
          'monogram' => 'D123',
          'title' => 'Fix the thing',
          'uri' => 'https://example.com/D123',
          'status' => 'needs-review',
        ),
      ),
      idx(head($this->build($repo, array($head => array($revision)))),
        'revisions'));
  }

  private function build($repo, array $revisions_by_hash) {
    $api = new ArcanistGitAPI($repo);

    $markers = $api->newMarkerRefQuery()
      ->withMarkerTypes(array(ArcanistMarkerRef::TYPE_BRANCH))
      ->execute();

    $state_refs = array();
    foreach ($markers as $marker) {
      $hash = $marker->getCommitHash();

      $state_refs[$hash] = id(new ArcanistWorkingCopyStateRef())
        ->attachHardpoint(
          ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS,
          idx($revisions_by_hash, $hash, array()));
    }

    return id(new ArcanistMarkersJSONBuilder())
      ->setMarkers($markers)
      ->setStateRefs($state_refs)
      ->buildList();
  }

  private function getHeadCommit($repo) {
    list($stdout) = id(new ExecFuture('git rev-parse HEAD'))
      ->setCWD($repo)
      ->resolvex();
    return trim($stdout);
  }

  private function newRepository() {
    $fixture = PhutilDirectoryFixture::newEmptyFixture();
    $this->fixtures[] = $fixture;
    $repo = $fixture->getPath();

    $this->git($repo, 'init -q -b master');
    $this->git($repo, 'config user.name test');
    $this->git($repo, 'config user.email test@example.com');
    $this->commit($repo, 'one');
    return $repo;
  }

  private function commit($repo, $name) {
    Filesystem::writeFile($repo.'/'.$name, $name);
    $this->git($repo, 'add %s', $name);
    $this->git($repo, 'commit -q -m %s', $name);
  }

  private function git($repo, $pattern /* , ... */) {
    $args = func_get_args();
    $repo = array_shift($args);
    $command = call_user_func_array('csprintf', $args);
    $command = 'git '.$command;
    id(new ExecFuture('%C', $command))->setCWD($repo)->resolvex();
  }

}
