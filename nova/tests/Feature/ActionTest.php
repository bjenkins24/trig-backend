<?php

namespace Laravel\Nova\Tests\Feature;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Tests\IntegrationTest;

class ActionTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testActionMessagesCanBeGenerated()
    {
        $this->assertEquals(['message' => 'test'], Action::message('test'));
    }

    public function testActionDownloadsCanBeGenerated()
    {
        $this->assertEquals(['download' => 'test', 'name' => 'name'], Action::download('test', 'name'));
    }

    public function testActionsRespectOldOnlyOnIndexValue()
    {
        $action = (new class() extends Action {
            public $onlyOnIndex = true;
        });

        $this->assertShownOnIndex($action);
        $this->assertHiddenFromDetail($action);
        $this->assertHiddenFromTableRow($action);
    }

    public function testActionsRespectOldOnlyOnDetailValue()
    {
        $action = (new class() extends Action {
            public $onlyOnDetail = true;
        });

        $this->assertHiddenFromIndex($action);
        $this->assertShownOnDetail($action);
        $this->assertHiddenFromTableRow($action);
    }

    public function testActionsShouldBeHiddenFromTheTableRowByDefaultAndShownEverywhereElse()
    {
        $action = (new class() extends Action {
        });

        $this->assertShownOnIndex($action);
        $this->assertShownOnDetail($action);
        $this->assertHiddenFromTableRow($action);
    }

    public function testActionsCanBeShownOnIndex()
    {
        $action = new class() extends Action {
        };
        $action->showOnIndex = false;
        $action->showOnIndex();

        $this->assertShownOnIndex($action);
    }

    public function testActionsCanBeShownOnlyOnIndex()
    {
        $action = (new class() extends Action {
        })->onlyOnIndex();

        $this->assertShownOnIndex($action);
        $this->assertHiddenFromDetail($action);
        $this->assertHiddenFromTableRow($action);

        $action->onlyOnIndex(false);

        $this->assertHiddenFromIndex($action);
        $this->assertShownOnDetail($action);
        $this->assertShownOnTableRow($action);
    }

    public function testActionsCanBeHiddenFromIndex()
    {
        $action = (new class() extends Action {
        })->exceptOnIndex();

        $this->assertHiddenFromIndex($action);
        $this->assertShownOnDetail($action);
        $this->assertShownOnTableRow($action);
    }

    public function testActionsCanBeShownOnDetail()
    {
        $action = new class() extends Action {
        };
        $action->showOnDetail = false;
        $action->showOnDetail();

        $this->assertShownOnDetail($action);
    }

    public function testActionsCanBeShownOnlyOnDetail()
    {
        $action = (new class() extends Action {
        })->onlyOnDetail();

        $this->assertHiddenFromIndex($action);
        $this->assertShownOnDetail($action);
        $this->assertHiddenFromTableRow($action);

        $action->onlyOnDetail(false);

        $this->assertShownOnIndex($action);
        $this->assertHiddenFromDetail($action);
        $this->assertShownOnTableRow($action);
    }

    public function testActionsCanBeHiddenFromDetail()
    {
        $action = (new class() extends Action {
        })->exceptOnDetail();

        $this->assertShownOnIndex($action);
        $this->assertHiddenFromDetail($action);
        $this->assertShownOnTableRow($action);
    }

    public function testActionsCanBeShownOnTableRow()
    {
        $action = new class() extends Action {
        };
        $action->showOnTableRow = false;
        $action->showOnTableRow();

        $this->assertShownOnTableRow($action);
    }

    public function testActionsCanBeShownOnlyOnTableRow()
    {
        $action = (new class() extends Action {
        })->onlyOnTableRow();

        $action->onlyOnTableRow(false);

        $this->assertShownOnIndex($action);
        $this->assertShownOnDetail($action);
        $this->assertHiddenFromTableRow($action);
    }

    public function testActionsCanBeHiddenFromTableRow()
    {
        $action = (new class() extends Action {
        })->exceptOnTableRow();

        $this->assertShownOnIndex($action);
        $this->assertShownOnDetail($action);
        $this->assertHiddenFromTableRow($action);
    }

    public function testActionsCanHaveCustomConfirmationButtonText()
    {
        $action = new class() extends Action {
        };

        $this->assertSubset(['confirmButtonText' => 'Run Action'], $action->jsonSerialize());

        $action->confirmButtonText('Yes!');

        $this->assertSubset(['confirmButtonText' => 'Yes!'], $action->jsonSerialize());
    }

    public function testActionsCanHaveCustomCancelButtonText()
    {
        $action = new class() extends Action {
        };

        $this->assertSubset(['cancelButtonText' => 'Cancel'], $action->jsonSerialize());

        $action->cancelButtonText('Nah!');

        $this->assertSubset(['cancelButtonText' => 'Nah!'], $action->jsonSerialize());
    }

    public function testActionsWithNoFieldsCanHaveCustomConfirmationText()
    {
        $action = new class() extends Action {
        };

        $this->assertSubset(['confirmText' => 'Are you sure you want to run this action?'], $action->jsonSerialize());

        $action->confirmText('Are you sure!');

        $this->assertSubset(['confirmText' => 'Are you sure!'], $action->jsonSerialize());
    }

    public function testActionsCanUseCustomCssClassesForTheButtons()
    {
        $action = new class() extends Action {
            public function actionClass()
            {
                return 'bg-warning text-warning-dark';
            }
        };

        $this->assertSubset(['class' => 'bg-warning text-warning-dark'], $action->jsonSerialize());
    }

    protected function assertShownOnIndex(Action $action)
    {
        $this->assertTrue($action->shownOnIndex());
    }

    protected function assertShownOnDetail(Action $action)
    {
        $this->assertTrue($action->shownOnDetail());
    }

    protected function assertHiddenFromTableRow(Action $action)
    {
        $this->assertFalse($action->shownOnTableRow());
    }

    protected function assertShownOnTableRow(Action $action)
    {
        return $this->assertTrue($action->shownOnTableRow());
    }

    protected function assertHiddenFromDetail(Action $action)
    {
        $this->assertFalse($action->shownOnDetail());
    }

    protected function assertHiddenFromIndex(Action $action)
    {
        $this->assertFalse($action->shownOnIndex());
    }
}
