<?php

final class ArcanistGitWorkEngine
  extends ArcanistWorkEngine {

  // NOTE: This is public (rather than protected, like the method it
  // implements) so that it can be tested directly.
  public function getDefaultStartSymbol() {
    $api = $this->getRepositoryAPI();

    // NOTE: In Git, we're trying to find the current branch name because the
    // behavior of "--track" depends on the symbol we pass.

    $marker = $api->newMarkerRefQuery()
      ->withIsActive(true)
      ->withMarkerTypes(array(ArcanistMarkerRef::TYPE_BRANCH))
      ->executeOne();
    if ($marker) {
      $name = $marker->getName();

      if ($this->getDisableAutoTrackRemote()) {
        $log = $this->getLogEngine();
        $log->writeTrace(
          pht('NO AUTO TRACK'),
          pht(
            'Not checking for a same-named remote tracking branch, so '.
            'branch "%s" will be the start point.',
            $name));
        return $name;
      }

      // If the branch tracks a remote branch with the same name and has not
      // diverged from it, start from the remote branch instead, so the new
      // branch is based on (and tracks) the latest remote state.
      $remote_branch = $this->getSameNameRemoteUpstream($name);
      if ($remote_branch !== null) {
        return $remote_branch;
      }

      return $name;
    }

    return $api->getWorkingCopyRevision();
  }

  private function getSameNameRemoteUpstream($name) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    list($err, $upstream) = $api->execManualLocal(
      'rev-parse --symbolic-full-name %s@{upstream}',
      $name);
    if ($err) {
      $log->writeTrace(
        pht('NO UPSTREAM'),
        pht(
          'Branch "%s" has no upstream, so it will be the start point.',
          $name));
      return null;
    }

    // The upstream must be a remote tracking branch with the same name as the
    // local branch, i.e. "refs/remotes/<remote>/<name>". Checking the prefix
    // and suffix (rather than splitting the ref) avoids confusion if the
    // remote's name or the branch's name contains a slash (unless there are
    // actually ambiguous remote+branch combinations, in which case all bets
    // are off).
    $prefix = 'refs/remotes/';
    $suffix = '/'.$name;

    $upstream = trim($upstream);
    if (strncmp($upstream, $prefix, strlen($prefix)) !== 0) {
      $log->writeTrace(
        pht('LOCAL UPSTREAM'),
        pht(
          'Upstream of branch "%s" is "%s", which is not a remote tracking '.
          'branch, so the local branch will be the start point.',
          $name,
          $upstream));
      return null;
    }

    if (strlen($upstream) <= strlen($prefix) + strlen($suffix) ||
        substr($upstream, -strlen($suffix)) !== $suffix) {
      $log->writeTrace(
        pht('DIFFERENT UPSTREAM'),
        pht(
          'Upstream of branch "%s" is "%s", which does not have the same '.
          'branch name, so the local branch will be the start point.',
          $name,
          $upstream));
      return null;
    }

    // The local branch must be an ancestor of (or equal to) the remote one.
    // This avoids switching to the remote branch if the user has local commits
    // that the remote does not have.
    list($err) = $api->execManualLocal(
      'merge-base --is-ancestor %s %s',
      'refs/heads/'.$name,
      $upstream);
    if ($err) {
      $log->writeTrace(
        pht('NOT ANCESTOR'),
        pht(
          'Branch "%s" is not an ancestor of its upstream "%s" (it has local '.
          'commits), so the local branch will be the start point.',
          $name,
          $upstream));
      return null;
    }

    $start = substr($upstream, strlen($prefix));

    $log->writeTrace(
      pht('REMOTE UPSTREAM'),
      pht(
        'Branch "%s" is an ancestor of its same-named remote tracking '.
        'branch "%s", so the remote branch will be the start point.',
        $name,
        $start));

    return $start;
  }

  protected function newMarker($symbol, $start) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $log->writeStatus(
      pht('NEW BRANCH'),
      pht(
        'Creating new branch "%s" from "%s".',
        $symbol,
        $start));

    $future = $api->newFuture(
      'checkout --track -b %s %s --',
      $symbol,
      $start);
    $future->resolve();
  }

  protected function moveToMarker(ArcanistMarkerRef $marker) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $log->writeStatus(
      pht('BRANCH'),
      pht(
        'Checking out branch "%s".',
        $marker->getName()));

    $future = $api->newFuture(
      'checkout %s --',
      $marker->getName());
    $future->resolve();
  }

}
