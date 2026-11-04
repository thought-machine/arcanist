<?php

/**
 * Tests for detecting a branch which already contains its upstream's upstream,
 * while the upstream itself is behind.
 *
 * The fixture is a single repository with a stack of local branches:
 * "feature" tracks "parent", which tracks "root".
 */
final class ArcanistGitAPIStaleUpstreamTestCase extends PhutilTestCase {

  private $fixture;
  private $path;

  protected function didRunOneTest($test_method_name) {
    // Destroying the fixture removes the repository from disk.
    $this->fixture = null;
    $this->path = null;
  }

  public function testNotTriggeredForOrdinaryBranch() {
    $api = $this->newTestRepository();

    $this->commit('feature-1');

    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));
  }

  public function testTriggeredWhenUpstreamOfUpstreamMerged() {
    $api = $this->newTestRepository();

    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('merge --no-edit root');

    $result = $api->getStaleUpstreamCommits(20);

    $this->assertEqual('parent', $result['base']);
    $this->assertEqual('root', $result['upstream_of_upstream']);
    $this->assertEqual(3, $result['total']);
    $this->assertEqual(3, count($result['commits']));
    $this->assertTrue(
      (bool)preg_grep('(root-1)', $result['commits']),
      pht('Excess upstream commit should be listed.'));
  }

  public function testTriggeredWhenBranchRebasedOntoUpstreamOfUpstream() {
    $api = $this->newTestRepository();

    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('rebase root');

    $result = $api->getStaleUpstreamCommits(20);

    $this->assertEqual(2, $result['total']);
  }

  public function testTriggeredWhenUpstreamOfUpstreamMovedOnAfterMerge() {
    $api = $this->newTestRepository();

    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('merge --no-edit root');
    $this->advanceRoot('root-2');

    $result = $api->getStaleUpstreamCommits(20);

    $this->assertEqual(3, $result['total']);
    $this->assertTrue(
      (bool)preg_grep('(root-1)', $result['commits']),
      pht('Excess upstream commit should be listed.'));
    $this->assertFalse(
      (bool)preg_grep('(root-2)', $result['commits']),
      pht('Commit which is not in HEAD should not be listed.'));
  }

  public function testTriggeredWhenUpstreamOfUpstreamMovedOnAfterRebase() {
    $api = $this->newTestRepository();

    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('rebase root');
    $this->advanceRoot('root-2');

    $result = $api->getStaleUpstreamCommits(20);

    $this->assertEqual(2, $result['total']);
  }

  public function testNotTriggeredWhenUpstreamOfUpstreamMovedOnSeparately() {
    $api = $this->newTestRepository();

    // "root" has commits which "feature" does not contain, but none of them
    // are in the diff.
    $this->commit('feature-1');
    $this->advanceRoot('root-1');

    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));
  }

  public function testCommitListIsLimited() {
    $api = $this->newTestRepository();

    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->advanceRoot('root-2');
    $this->advanceRoot('root-3');
    $this->git('merge --no-edit root');

    $result = $api->getStaleUpstreamCommits(2);

    $this->assertEqual(2, count($result['commits']));
    $this->assertTrue($result['total'] > 2);
  }

  public function testNotTriggeredOnceUpstreamIsUpdated() {
    $api = $this->newTestRepository();

    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('merge --no-edit root');
    $this->git('branch --set-upstream-to=root');

    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));
  }

  public function testNotTriggeredWhenUpstreamHasNoUpstream() {
    $api = $this->newTestRepository();

    $this->git('branch --unset-upstream parent');
    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('merge --no-edit root');

    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));
  }

  public function testTriggeredForExplicitBaseWithoutUpstream() {
    $api = $this->newTestRepository();

    // The branch has no upstream, but the user chose "parent" as the base
    // (for example, at the "default relative commit" prompt).
    $this->git('branch --unset-upstream');
    $api->setBaseCommit('parent');
    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('merge --no-edit root');

    $result = $api->getStaleUpstreamCommits(20);

    $this->assertEqual('parent', $result['base']);
    $this->assertEqual('root', $result['upstream_of_upstream']);
    $this->assertEqual(3, $result['total']);
  }

  public function testNotTriggeredWhenBaseIsNotABranch() {
    $api = $this->newTestRepository();

    list($sha) = $this->git('rev-parse parent');
    $api->setBaseCommit(trim($sha));
    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('merge --no-edit root');

    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));
  }

  public function testNotTriggeredWhenExplicitBaseHasNoUpstream() {
    $api = $this->newTestRepository();

    $api->setBaseCommit('root');
    $this->commit('feature-1');
    $this->advanceRoot('root-1');
    $this->git('merge --no-edit root');

    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));
  }

  public function testStackedBranchesAfterFirstBranchIsSquashLanded() {
    // In this test, "root" plays the part of "origin/master" and "parent" is
    // the local "master" which tracks it. "feature" is the first feature
    // branch, and "stacked" is a branch stacked on top of it.
    $api = $this->newTestRepository();

    // Send "feature" for review.
    $this->commit('feature-1');
    $this->commit('feature-2');
    $api->reloadCommitRange();
    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));

    // Stack another branch on top of it, and send that for review too.
    $this->git('checkout -b stacked --track feature');
    $this->commit('stacked-1');
    $this->commit('stacked-2');
    $api->reloadCommitRange();
    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));

    // Address a review comment on "feature", then land it as a single
    // squashed commit on "root".
    $this->git('checkout feature');
    $this->commit('feature-3');
    $this->git('checkout root');
    $this->git('merge --squash feature');
    $this->git('commit -m %s', 'feature (squashed)');
    $this->git('checkout stacked');
    $api->reloadCommitRange();
    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));

    // Rebase "stacked" onto "root" without updating its upstream. The diff
    // against "feature" now includes the squashed commit as well as the two
    // commits on "stacked", even though "root" is further up the chain of
    // upstreams than the upstream of "feature".
    $this->git('rebase --onto root feature');
    $api->reloadCommitRange();
    $result = $api->getStaleUpstreamCommits(20);

    $this->assertEqual('feature', $result['base']);
    $this->assertEqual('root', $result['upstream_of_upstream']);
    $this->assertEqual(3, $result['total']);
    $this->assertTrue(
      (bool)preg_grep('(feature \(squashed\))', $result['commits']),
      pht('Squashed commit should be listed.'));

    // Point "stacked" at "root".
    $this->git('branch --set-upstream-to=root');
    $api->reloadCommitRange();
    $this->assertEqual(null, $api->getStaleUpstreamCommits(20));
  }

  /**
   * Build a repository with local branches "root", "parent" (tracking "root")
   * and "feature" (tracking "parent"), and check out "feature".
   */
  private function newTestRepository() {
    if (!Filesystem::binaryExists('git')) {
      $this->assertSkipped(pht('Git is not installed'));
    }

    $this->fixture = PhutilDirectoryFixture::newEmptyFixture();
    $this->path = $this->fixture->getPath();

    $this->git('init --initial-branch=root');
    $this->git('config user.name test');
    $this->git('config user.email test@example.com');
    $this->commit('root-0');
    $this->git('checkout -b parent --track root');
    $this->git('checkout -b feature --track parent');

    $working_copy = ArcanistWorkingCopyIdentity::newFromPath($this->path);
    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);
    $api = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
      $configuration_manager);

    return $api;
  }

  private function commit($name) {
    Filesystem::writeFile($this->path.'/'.$name, $name);
    $this->git('add .');
    $this->git('commit -m %s', $name);
  }

  /**
   * Add a commit to "root" without disturbing the checked out branch.
   */
  private function advanceRoot($name) {
    $this->git('checkout root');
    $this->commit($name);
    $this->git('checkout feature');
  }

  private function git($pattern /* , ... */) {
    $args = array_slice(func_get_args(), 1);
    $future = new ExecFuture('git '.$pattern, ...$args);
    $future->setCWD($this->path);
    return $future->resolvex();
  }

}
