<?php

final class ArcanistWorkWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'work';
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('start')
        ->setParameter('symbol')
        ->setHelp(
          pht(
            'When creating a new branch or bookmark, use this as the '.
            'branch point.')),
      $this->newWorkflowArgument('no-auto-track-remote')
        ->setHelp(
          pht(
            'In Git, forces the current branch to be used as the start point '.
            'instead of checking if the new branch can be started from an '.
            'upstream remote tracking branch. Has no effect if --start is set')),
      $this->newWorkflowArgument('symbol')
        ->setWildcard(true),
    );
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOHELP
Begin or resume work on a branch, bookmark, task, or revision.

The __symbol__ may be a branch or bookmark name, a revision name (like "D123"),
a task name (like "T123"), or a new symbol.

If you provide a symbol which currently does not identify any ongoing work,
a new branch or bookmark will be created with the name you provide.

If you provide the name of an existing branch or bookmark, the working copy
will be switched to that branch or bookmark.

If you provide the name of a revision or task, the workflow will look for a
related branch or bookmark that already exists in the working copy. If one is
found, it will switch to it. If it does not find one, it will attempt to create
a new branch or bookmark.

When "arc work" creates a branch or bookmark, it will use **--start** as the
branchpoint if it is provided. Otherwise, the current working copy state will
serve as the starting point. In Git, if the current branch tracks a remote
branch with the same name and has not diverged from it, that remote branch will
be used as the starting point instead. Use **--no-auto-track-remote** to
disable this.
EOHELP
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Begin or resume work.'))
      ->addExample(pht('**work** [--start __start__] [--no-auto-track-remote] __symbol__'))
      ->setHelp($help);
  }

  protected function runWorkflow() {
    $api = $this->getRepositoryAPI();

    $work_engine = $api->getWorkEngine();
    if (!$work_engine) {
      throw new PhutilArgumentUsageException(
        pht(
          '"arc work" must be run in a Git or Mercurial working copy.'));
    }

    $argv = $this->getArgument('symbol');
    if (count($argv) === 0) {
      throw new PhutilArgumentUsageException(
        pht(
          'Provide a branch, bookmark, task, or revision name to begin '.
          'or resume work on.'));
    } else if (count($argv) === 1) {
      $symbol_argument = $argv[0];
      if (!strlen($symbol_argument)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Provide a nonempty symbol to begin or resume work on.'));
      }
    } else {
      throw new PhutilArgumentUsageException(
        pht(
          'Too many arguments: provide exactly one argument.'));
    }

    $start_argument = $this->getArgument('start');

    $work_engine
      ->setViewer($this->getViewer())
      ->setWorkflow($this)
      ->setLogEngine($this->getLogEngine())
      ->setSymbolArgument($symbol_argument)
      ->setStartArgument($start_argument)
      ->setDisableAutoTrackRemote($this->getArgument('no-auto-track-remote'))
      ->execute();

    return 0;
  }

}
