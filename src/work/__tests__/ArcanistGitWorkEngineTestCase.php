<?php

final class ArcanistGitWorkEngineTestCase extends PhutilTestCase {

  // Fixtures delete their directories when destroyed, so hold a reference
  // to each one for as long as this test case exists.
  private $fixtures = array();

  public function testNoUpstream() {
    $repo = $this->newRepository();
    $this->git($repo, 'checkout -b feature');

    $this->assertEqual('feature', $this->getStart($repo));
  }

  public function testRemoteUpstreamSameNameEqual() {
    $repo = $this->newRepositoryWithPushedMaster();

    $this->assertEqual('origin/master', $this->getStart($repo));
  }

  public function testRemoteUpstreamSameNameLocalBehind() {
    $repo = $this->newRepositoryWithPushedMaster();
    $this->commit($repo, 'two');
    $this->git($repo, 'push origin master');
    $this->git($repo, 'reset --hard HEAD~1');

    $this->assertEqual('origin/master', $this->getStart($repo));
  }

  public function testRemoteUpstreamSameNameLocalAhead() {
    $repo = $this->newRepositoryWithPushedMaster();
    $this->commit($repo, 'two');

    $this->assertEqual('master', $this->getStart($repo));
  }

  public function testRemoteUpstreamSameNameDiverged() {
    $repo = $this->newRepositoryWithPushedMaster();
    $this->commit($repo, 'two');
    $this->git($repo, 'push origin master');
    $this->git($repo, 'reset --hard HEAD~1');
    $this->commit($repo, 'three');

    $this->assertEqual('master', $this->getStart($repo));
  }

  public function testRemoteUpstreamDifferentName() {
    $repo = $this->newRepositoryWithPushedMaster();
    $this->git($repo, 'checkout -b other');
    $this->git($repo, 'branch --set-upstream-to=origin/master');

    $this->assertEqual('other', $this->getStart($repo));
  }

  public function testLocalUpstream() {
    $repo = $this->newRepository();
    $this->git($repo, 'checkout -b feature');
    $this->git($repo, 'branch --set-upstream-to=master');

    $this->assertEqual('feature', $this->getStart($repo));
  }

  public function testLocalUpstreamWithRemoteAvailable() {
    // The local "master" is not treated as a remote tracking branch just
    // because "origin/master" exists.
    $repo = $this->newRepositoryWithPushedMaster();
    $this->git($repo, 'checkout -b feature');
    $this->git($repo, 'branch --set-upstream-to=master');

    $this->assertEqual('feature', $this->getStart($repo));
  }

  public function testDetachedHead() {
    // With no current branch there is no upstream to consider, so the start
    // point is the current commit, even if "origin/master" is at the same
    // commit.
    $repo = $this->newRepositoryWithPushedMaster();
    $this->git($repo, 'checkout -q --detach');

    $this->assertEqual($this->getHeadCommit($repo), $this->getStart($repo));
  }

  private function getHeadCommit($repo) {
    list($stdout) = id(new ExecFuture('git rev-parse HEAD'))
      ->setCWD($repo)
      ->resolvex();
    return trim($stdout);
  }

  private function getStart($repo) {
    $api = new ArcanistGitAPI($repo);

    $engine = new ArcanistGitWorkEngine();
    $engine->setRepositoryAPI($api);
    $engine->setLogEngine(new ArcanistLogEngine());

    return $engine->getDefaultStartSymbol();
  }

  private function newRepository() {
    $repo = $this->newDirectory();
    $this->git($repo, 'init -q -b master');
    $this->git($repo, 'config user.name test');
    $this->git($repo, 'config user.email test@example.com');
    $this->commit($repo, 'one');
    return $repo;
  }

  private function newRepositoryWithPushedMaster() {
    $remote = $this->newDirectory();
    $this->git($remote, 'init -q --bare');

    $repo = $this->newRepository();
    $this->git($repo, 'remote add origin %s', $remote);
    $this->git($repo, 'push -q -u origin master');
    return $repo;
  }

  private function newDirectory() {
    $fixture = PhutilDirectoryFixture::newEmptyFixture();
    $this->fixtures[] = $fixture;
    return $fixture->getPath();
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
