<?php

namespace LOTS\Controller;

class MyResearchController extends \VuFind\Controller\MyResearchController
{
    /**
     * Gather user transaction history
     * LOTS added here for transaction history relating to LOBININTEG-19 aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
     *
     * @return mixed
     */
    public function profileAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }
        $values = $this->getRequest()->getPost();
        $view = parent::profileAction();
        $patron = $this->catalogLogin();
        $history = $this->params()->fromPost('loan_history', false);

        if (is_array($patron) && $history >= 0) {
            if ($this->processLibraryDataUpdate($patron, $values, $user)) {
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            }
            $view = parent::profileAction();
        }
        return $view;
    }

    /**
     * Changing transaction history
     * LOTS added here for transaction history relating to LOBININTEG-19
     *
     * @param array  $patron patron data
     * @param object $values form values
     *
     * @return bool
     */
    protected function processLibraryDataUpdate($patron, $values)
    {
        // Connect to the ILS:
        $catalog = $this->getILS();

        $success = true;
        // Update checkout history state
        $updateState = $catalog
        ->checkFunction('updateTransactionHistoryState', compact('patron'));
        if (isset($values->loan_history) && $updateState) {
            $result = $catalog->updateTransactionHistoryState(
                $patron,
                $values->loan_history
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        return $success;
    }
}
