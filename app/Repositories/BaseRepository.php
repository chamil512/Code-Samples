<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use App\Traits\Datatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Repositories\AdminActivityRepository;
use Modules\Admin\Services\Permission;

class BaseRepository
{
    use Datatable;

    private array $urls = [];
    private string $pageTitle = "";
    private array $errors = [];
    public bool $isValidData = false;
    public string $statusField = "status";
    public array $statuses = [
        ["id" =>"1", "name" =>"Enabled", "label"=>"success"],
        ["id" =>"0", "name" =>"Disabled", "label"=>"danger"]
    ];

    public function __construct()
    {
        $this->_setViewComposer();
    }

    /**
     * Setup view variables
     * @return void
     */
    private function _setViewComposer()
    {
        View::composer("*", function ($view) {

            if (!isset($view->getData()["pageTitle"])) {
                $view->with("pageTitle", $this->pageTitle);
            }

            if (!isset($view->getData()["urls"])) {
                $view->with("urls", $this->_getPageUrls());
            }
        });
    }

    /**
     * @param $pageTitle
     * @return void
     */
    public function setPageTitle($pageTitle)
    {
        $this->pageTitle = $pageTitle;
    }

    /**
     * @param string $accessKey Key variable to access the URL from array
     * @param string $url System URL
     * @return void
     */
    public function setPageUrl($accessKey, $url)
    {
        $this->urls[$accessKey] = $url;
    }

    /**
     * @param array $urls List of URLs with array
     * @return void
     */
    public function setPageUrls($urls)
    {
        if (is_array($urls) && count($urls) > 0) {
            foreach ($urls as $key => $url) {
                $this->urls[$key] = $url;
            }
        }
    }

    /**
     * @return array
     */
    private function _getPageUrls(): array
    {
        $urls = array();
        if (count($this->urls) > 0) {
            $validateUrls = $this->validateUrls($this->urls);

            $urls = [];
            foreach($validateUrls as $key => $url) {

                if ($url !== false) {

                    $urls[$key] = $url;
                }
            }
        }

        return $urls;
    }

    public function getRecordPrepared($record)
    {
        $record["enableView"] = true;
        $record["enableEdit"] = true;
        $record["enableDelete"] = true;
        $record["enableTrash"] = true;
        $record["enableRestore"] = true;

        return $record;
    }

    /**
     * @param $model
     * @return array
     */
    public function saveModel($model): array
    {
        $save = $model->save();

        $notify = array();
        if ($save) {

            $notify["status"] = "success";
            $notify["notify"][] = "Successfully saved the details.";

            $response["record"] = $model;
        } else {

            $notify["status"] = "failed";
            $notify["notify"][] = "Details saving was failed";

        }
        $response["notify"] = $notify;

        return $response;
    }

    /**
     * @param $model
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return mixed
     * @throws ValidationException
     */
    public function getValidatedData($model, $rules, $messages = [], $customAttributes = [])
    {
        $postData = Validator::make(request()->all(), $rules, $messages, $customAttributes);

        if ($postData->fails()) {

            $this->isValidData = false;
            return $this->getValidationErrors($postData->errors());
        } else {

            $this->isValidData = true;
            $data = $postData->validated();
        }

        foreach ($data as $key => $value) {
            $model->$key = $value;
        }

        return $model;
    }

    /**
     * @param $errors
     * @return array
     */
    public function getValidationErrors($errors): array
    {
        $errors = json_decode(json_encode($errors), true);

        $validationResponse["status"] = "failed";

        foreach ($errors as $error) {

            if (is_array($error) && count($error) > 0) {

                foreach ($error as $err) {
                    $validationResponse["notify"][] = $err;
                }
            } else {
                $validationResponse["notify"][] = $error;
            }
        }

        $response["notify"] = $validationResponse;

        return $response;
    }

    /**
     * @param array $response
     * @param bool $redirect
     * @param string $url
     * @return JsonResponse|RedirectResponse
     */
    public function handleResponse($response, $redirect = true, $url = "")
    {
        if (isset($response["status"]) && isset($response["notify"])) {

            $status = $response["status"];
            $notify = $response["notify"];

            $response["notify"]["status"] = $status;
            $response["notify"]["notify"] = $notify;
        }

        if (request()->expectsJson()) {

            return response()->json($response, 201);
        } else {

            request()->session()->flash("response", $response);
            if ($redirect) {

                if ($url !== "") {
                    return redirect()->to($url);
                } else {

                    if (isset($_SERVER["HTTP_REFERER"])) {

                        return redirect()->back();
                    } else {

                        $url = URL::to("/dashboard");
                        return redirect()->to($url);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array $urls
     * @return array
     */
    public function validateUrls($urls = []): array
    {
        if (class_exists("Permission")) {
            $urls = Permission::validateUrls($urls);
        }

        return $urls;
    }

    /**
     * @param $model
     * @param string $statusField Status database field of the model
     * @param string $statusByField If status updated person's user id should be updated, then that field's column name of the model
     * @param string $remarksField If remarks should be updated, then that field's column name of the model
     * @param string $reviewedOn If reviewed date should be updated, then that field's column name of the model
     * @return JsonResponse|RedirectResponse
     */
    public function updateStatus($model, $statusField, $statusByField = "", $remarksField = "", $reviewedOn = "")
    {
        if ($model) {

            $status = request()->post("status");

            if ($model->$statusField != $status) {

                if ($this->isStatusUpdateAllowed($model, $statusField, $status)) {

                    $model->$statusField = $status;
                    if ($statusByField != "") {

                        $model->$statusByField = auth("admin")->user()->admin_id;
                    }

                    if ($reviewedOn != "") {

                        $model->$reviewedOn = date("Y-m-d H:i:s", time());
                    }

                    if ($remarksField == "") {

                        $response = $this->saveModel($model);
                    } else {

                        $remarks = request()->post("remarks");

                        if ($remarks != "") {

                            $model->$remarksField = $remarks;
                            $response = $this->saveModel($model);

                            if ($response["notify"]["status"] == "success") {

                                $this->onUpdateStatusSuccess($model, $statusField, $status);
                            } else {

                                $this->onUpdateStatusFailed($model, $statusField, $status);
                            }
                        } else {

                            $notify = array();
                            $notify["status"] = "failed";
                            $notify["notify"][] = "Remarks required.";

                            $response["notify"] = $notify;
                        }
                    }
                } else {

                    if (is_array($this->errors) && count($this->errors) > 0) {

                        $notify["status"] = "failed";
                        $notify["notify"] = $this->errors;
                    } else {

                        $notify = array();
                        $notify["status"] = "failed";
                        $notify["notify"][] = "Details moving to trash was failed. Requested record does not allowed to delete.";
                    }

                    $response["notify"] = $notify;
                }
            } else {

                $notify = array();
                $notify["status"] = "failed";
                $notify["notify"][] = "Status remains same in the requested record.";

                $response["notify"] = $notify;
            }
        } else {

            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Status changing was failed. Requested record does not exist.";

            $response["notify"] = $notify;
        }

        return $this->handleResponse($response);
    }

    /**
     * Callback function after successful status update
     * @param $model
     * @param $statusField
     * @param $status
     * @param bool $allowed
     * @return boolean
     */
    protected function isStatusUpdateAllowed($model, $statusField, $status, bool $allowed = true): bool
    {
        return $allowed;
    }

    /**
     * Callback function after successful status update
     * @param $model
     * @param $statusField
     * @param $status
     * @return void
     */
    protected function onUpdateStatusSuccess($model, $statusField, $status)
    {

    }

    /**
     * Callback function after successful status update
     * @param $model
     * @param $statusField
     * @param $status
     * @return void
     */
    protected function onUpdateStatusFailed($model, $statusField, $status)
    {

    }

    /**
     * @param $model
     * @param string $statusField
     * @param array $statuses
     * @return false|array
     */
    public function getStatusInfo($model, $statusField = "", $statuses = [])
    {
        if ($statusField === "" && $this->statusField !== "") {

            $statusField = $this->statusField;
        }

        if (!is_array($statuses) || count($statuses) === 0) {

            $statuses = $this->statuses;
        }

        $data = false;
        if ($statusField !== "") {

            $modelStatus = $model->{$statusField};

            foreach ($statuses as $status) {

                if ($status["id"] == $modelStatus) {

                    $data = $status;
                }
            }
        }

        return $data;
    }

    /**
     * Move the record to trash
     * @param $model
     * @return JsonResponse|RedirectResponse
     */
    public function delete($model)
    {
        if ($model) {
            $deleteAllowed = $this->beforeDelete($model, true);
            if ($deleteAllowed) {

                $currModel = $model;
                if ($model->delete()) {

                    $this->afterDelete($currModel);

                    $notify = array();
                    $notify["status"] = "success";
                    $notify["notify"][] = "Successfully moved the record to trash.";

                } else {

                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Details moving to trash was failed. Unknown error occurred.";

                }
            } else {

                if (is_array($this->errors) && count($this->errors) > 0) {

                    $notify["status"] = "failed";
                    $notify["notify"] = $this->errors;
                } else {

                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Details moving to trash was failed. Requested record does not allowed to delete.";
                }

                $errorHeading = "Following errors occurred while deleting this record.<br>";
                array_unshift($notify["notify"], $errorHeading);

            }
        } else {

            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Details moving to trash was failed. Requested record does not exist.";

        }
        $dataResponse["notify"] = $notify;

        return $this->handleResponse($dataResponse);
    }

    /**
     * @param $model
     * @param bool $allowed
     * @return bool
     */
    protected function beforeDelete($model, $allowed): bool
    {
        return $allowed;
    }

    /**
     * @param $model
     * @return void
     */
    protected function afterDelete($model)
    {
        //do anything what ever needs
    }

    /**
     * @param $model
     * @param string $modelName Real world name of the model
     * @param string $relation
     * @param string $relationName Real world name of the relation model
     * @return bool
     */
    protected function checkRelationBeforeDelete($model, $modelName = "record", $relation = "", $relationName = ""): bool
    {
        $count = $model->$relation()->count();

        $error = "";
        $allowed = true;
        if ($count > 0) {

            $allowed = false;
            if ($count > 1) {
                $error = "There are " . $count . " " . Str::plural($relationName) . " under this " . $modelName . ".";
            } else {
                $error = "There is " . $count . " " . $relationName . " under this " . $modelName . ".";
            }
        }

        if (!$allowed) {
            $this->setErrors($error);
        }

        return $allowed;
    }

    /**
     * @param $model
     * @param string $modelName Real world name of the model
     * @param array $relations all relation details as an array
     * @param bool $waitForAll Wait for all messages to appear or process at first error
     * @return bool if allowed to delete or will be processed directly
     */
    protected function checkRelationsBeforeDelete($model, $modelName = "", $relations = [], $waitForAll = false): bool
    {
        $allowed = true;
        if (is_array($relations) && count($relations) > 0) {

            $relation = "";
            $relationName = "";
            foreach ($relations as $rel) {

                extract($rel);

                $isAllowed = $this->checkRelationBeforeDelete($model, $modelName, $relation, $relationName);
                if ($waitForAll) {

                    if (!$isAllowed) {
                        $allowed = false;
                    }
                } else {

                    if (!$isAllowed) {
                        $allowed = false;
                        break;
                    }
                }
            }
        }

        return $allowed;
    }

    /**
     * Restore record
     * @param $model
     * @return JsonResponse|RedirectResponse
     */
    public function restore($model)
    {
        if ($model) {

            $restoreAllowed = $this->beforeRestore($model, true);
            if ($restoreAllowed) {

                $currModel = $model;

                if ($model->restore()) {

                    $this->afterRestore($currModel);

                    $notify = array();
                    $notify["status"] = "success";
                    $notify["notify"][] = "Successfully restored the record from trash.";

                } else {

                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Details restoring from trash was failed. Unknown error occurred.";

                }
            } else {

                if (is_array($this->errors) && count($this->errors) > 0) {
                    $notify["status"] = "failed";
                    $notify["notify"] = $this->errors;
                } else {
                    $notify = array();
                    $notify["status"] = "failed";
                    $notify["notify"][] = "Details moving to trash was failed. Requested record does not allowed to delete.";
                }

                $errorHeading = "Following errors occurred while restoring this record.<br>";
                array_unshift($notify["notify"], $errorHeading);

            }
        } else {

            $notify = array();
            $notify["status"] = "failed";
            $notify["notify"][] = "Details restoring from trash was failed. Requested record does not exist.";

        }
        $dataResponse["notify"] = $notify;

        return $this->handleResponse($dataResponse);
    }

    /**
     * @param $model
     * @param bool $allowed
     * @return bool
     */
    protected function beforeRestore($model, $allowed): bool
    {
        return $allowed;
    }

    /**
     * @param $model
     * @return void
     */
    protected function afterRestore($model)
    {
        //do anything what ever needs
    }

    /**
     * @param $errors
     */
    public function setErrors($errors)
    {
        if (is_array($errors)) {

            if (count($errors) > 0) {

                foreach ($errors as $error) {

                    $this->errors[] = $error;
                }
            }
        } else {
            $this->errors[] = $errors;
        }
    }

    /**
     * @param Model $model Model instance
     * @param $modelHash
     * @param $id
     * @param array $options [
     * "title" => "Title for the record",
     * "suffix" => "Additional label for the title to appear after title",
     * "view" => "Custom view path to render record activity data"
     * ]
     * @return mixed
     */
    public function approvalHistory($model, $modelHash, $id, $options = [])
    {
        $model = $model->query()->find($id);

        if($model) {

            if ($this->isValidModel($model)) {

                $recordTitle = "";
                if (isset($model->name)) {

                    $recordTitle = $model->name;
                }

                if ($recordTitle === "") {

                    if (isset($options["title"])) {

                        $recordTitle = $options["title"];
                    }
                }

                if ($recordTitle !== "") {

                    if (isset($options["suffix"])) {

                        $recordTitle .= " | ". $options["suffix"];
                    }
                }

                $pageTitle = $recordTitle . " | System Approval History";

                $repository = new SystemApprovalRepository();

                return $repository->showApprovalHistory($id, $modelHash, $pageTitle);
            } else {

                abort(404);
            }
        } else {

            abort(404);
        }
    }

    /**
     * @param $model
     * @param $modelHash
     * @param $id
     * @param array $options [
     * "title" => "Title for the record",
     * "suffix" => "Additional label for the title to appear after title",
     * "view" => "Custom view path to render record activity data"
     * ]
     * @return mixed
     */
    public function recordHistory($model, $modelHash, $id, $options = []) {

        if (is_numeric($modelHash)) {

            //this means this is a activity record
            $activityId = $id;
            $modelId = $modelHash;

            $model = $model->query()->find($modelId);

            if($model) {

                if ($this->isValidModel($model)) {

                    $recordTitle = "";
                    if (isset($model->name)) {

                        $recordTitle = $model->name;
                    }

                    if ($recordTitle === "") {

                        if (isset($options["title"])) {

                            $recordTitle = $options["title"];
                        }
                    }

                    if ($recordTitle !== "") {

                        if (isset($options["suffix"])) {

                            $recordTitle .= " | ". $options["suffix"];
                        }
                    }

                    $viewPath = "";
                    if (isset($options["view"])) {

                        $viewPath = $options["view"];
                    }

                    $pageTitle = $recordTitle . " | Record Update | Activity Data";

                    $repository = new AdminActivityRepository();
                    return $repository->showRecordData($model, $activityId, $pageTitle, $viewPath);
                } else {

                    abort(404);
                }

            } else {

                abort(404);
            }
        } else {

            $model = $model->query()->find($id);

            if($model) {

                if ($this->isValidModel($model)) {


                    $recordTitle = "";
                    if (isset($model->name)) {

                        $recordTitle = $model->name;
                    }

                    if ($recordTitle === "") {

                        if (isset($options["title"])) {

                            $recordTitle = $options["title"];
                        }
                    }

                    if ($recordTitle !== "") {

                        if (isset($options["suffix"])) {

                            $recordTitle .= " | ". $options["suffix"];
                        }
                    }

                    $pageTitle = $recordTitle . " | Record Update History";

                    $repository = new AdminActivityRepository();
                    return $repository->showRecordHistory($model, $modelHash, $pageTitle);
                } else {

                    abort(404);
                }
            } else {

                abort(404);
            }
        }
    }

    /**
     * @param $model
     * @return bool
     */
    public function isValidModel($model): bool
    {
        return true;
    }

    /**
     * @param $controllerUrl
     * @param $model
     * @return string
     */
    public function getDefaultApprovalHistoryUrl($controllerUrl, $model): string
    {
        $modelName = $this->getClassName($model);
        $modelHash = $this->generateClassNameHash($modelName);

        $path = $controllerUrl . "/approval_history/" . $modelHash . "/";

        return URL::to($path);
    }

    /**
     * @param $controllerUrl
     * @param $model
     * @return string
     */
    public function getDefaultRecordHistoryUrl($controllerUrl, $model): string
    {
        $modelName = $this->getClassName($model);
        $modelHash = $this->generateClassNameHash($modelName);

        $path = $controllerUrl . "/record_history/" . $modelHash . "/";

        return URL::to($path);
    }
}
