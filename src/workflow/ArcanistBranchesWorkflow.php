<?php

final class ArcanistBranchesWorkflow
  extends ArcanistMarkersWorkflow {

  public function getWorkflowName() {
    return 'branches';
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('json')
        ->setHelp(pht('Output branches in JSON format instead of a table.')),
    );
  }

  protected function isJSONOutput() {
    return (bool)$this->getArgument('json');
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOHELP
Lists branches in the working copy, annotated with additional information
about review status.
EOHELP
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(
        pht('Show an enhanced view of branches in the working copy.'))
      ->addExample(pht('**branches** [__options__]'))
      ->setHelp($help);
  }

  protected function getWorkflowMarkerType() {
    $api = $this->getRepositoryAPI();
    $marker_type = ArcanistMarkerRef::TYPE_BRANCH;

    if (!$this->hasMarkerTypeSupport($marker_type)) {
      throw new PhutilArgumentUsageException(
        pht(
          'The version control system ("%s") in the current working copy '.
          'does not support branches.',
          $api->getSourceControlSystemName()));
    }

    return $marker_type;
  }

}
