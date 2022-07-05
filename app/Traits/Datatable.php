<?php

namespace App\Traits;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Admin\Services\Permission;
use App\Repositories\BaseRepository;

trait Datatable
{
    use Approval;

    public $model = null;
    public ?string $primaryKey = null;

    private ?array $tableColumns = null;
    private array $columns = [];
    private array $exportFormats = ["copy", "csv", "excel", "pdf", "print"];
    private array $operations = ["add", "edit", "list", "view", "delete", "trash", "trashList", "restore"]; //default operations
    private array $buttons = [];
    private array $rowActionBeforeButtons = [];
    private array $rowActionAfterButtons = [];
    private bool $showApprovalUrl = false;

    public ?string $fetchUrl = null;
    public ?object $viewData = null;
    public ?string $viewPath = "default.index";
    public ?string $extendViewPath = null;
    public ?string $controllerUrl = null;
    public ?array $customFilters = [];
    public ?array $defaultSearchFields = [];

    /**
     * @param $model
     * @param array $options
     */
    public function initDatatable($model, array $options = [])
    {
        $this->model = $model;

        //set variable, values
        $this->tableColumns = $this->getTableColumns();

        $this->viewData = (object)array();
        $this->_setOptions($options);
        $this->enableViewData("add", "edit")
            ->disableViewData("list", "view", "delete", "restore", "trash", "trashList", "export");

        if ($this->viewData->enableTrashList) {
            $this->enableViewData("trash")
                ->disableViewData("delete");
        }

        $this->viewData->exportFormats = $this->exportFormats;

        $this->_setIndexUrls();
    }

    private function _setOptions($options)
    {
        if (isset($options["fetchUrl"])) {

            $this->fetchUrl = $options["fetchUrl"];
        }
    }

    /**
     * @param mixed $model
     * @return array
     */
    public function getTableColumns($model = false): array
    {
        if (!$model) {
            $model = $this->model;
        }

        return $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
    }

    /**
     * Setting which columns should be showed in view
     * @param array $newColumns Comma separated columns list
     * @return Datatable
     */
    public function setColumns($newColumns = array())
    {
        $columns = $this->columns;

        if (is_array($newColumns) && count($newColumns) > 0) {
            foreach ($newColumns as $column) {
                $columns[$column] = $this->_buildDefaultColumn($column);
            }
        } else {
            $noa = func_num_args(); // number of argument passed,(Number of columns)

            for ($i = 0; $i < $noa; $i++) {
                $column = func_get_arg($i); // get each argument passed

                $columns[$column] = $this->_buildDefaultColumn($column);
            }
        }
        $this->columns = $columns;

        return $this;
    }

    /**
     * Setting which columns should be showed in view
     * @param array $newColumns Comma separated columns list
     * @return Datatable
     */
    public function setCustomFilters($newColumns = array())
    {
        $columns = $this->customFilters;

        if (is_array($newColumns) && count($newColumns) > 0) {
            foreach ($newColumns as $column) {
                $columns[$column] = $this->_buildDefaultColumn($column, true);
            }
        } else {
            $noa = func_num_args(); // number of argument passed,(Number of columns)

            for ($i = 0; $i < $noa; $i++) {
                $column = func_get_arg($i); // get each argument passed

                $columns[$column] = $this->_buildDefaultColumn($column, true);
            }
        }
        $this->customFilters = $columns;

        return $this;
    }

    /**
     * @param string $column
     * @param bool $isCustom
     * @return array
     */
    private function _buildDefaultColumn($column, $isCustom = false): array
    {
        $defaultColumn = [];

        $explode_del = "_";
        $implode_del = " ";
        $column_label = ucwords(implode($implode_del, explode($explode_del, $column)));

        //set field and field's label
        $defaultColumn["column"] = $column;
        $defaultColumn["label"] = $column_label;
        $defaultColumn["filterable"] = true;
        $defaultColumn["searchable"] = false;
        $defaultColumn["filterMethod"] = "text";
        $defaultColumn["filterOptions"] = array();
        $defaultColumn["filterPosition"] = "before";
        $defaultColumn["relation"] = ""; //for fields which is having ORM relationships
        $defaultColumn["relationField"] = ""; //for fields which is having ORM relationships
        $defaultColumn["relationOtherField"] = ""; //for fields which is having ORM relationships
        $defaultColumn["coRelation"] = ""; //for fields which is having ORM relationships which is having ORM relationships
        $defaultColumn["coRelationField"] = ""; //for fields which is having ORM relationships which is having ORM relationships
        $defaultColumn["coRelationOtherField"] = ""; //for fields which is having ORM relationships which is having ORM relationships
        $defaultColumn["coRelationDBField"] = ""; //for fields which is having ORM relationships which is having ORM relationships
        $defaultColumn["coRelationFKeyField"] = ""; //for fields which is having ORM relationships which is having ORM relationships

        if ($isCustom) {

            $defaultColumn["isCustom"] = true;
            $defaultColumn["after"] = true; //render after default filter columns
            $defaultColumn["dbField"] = $column;
            $defaultColumn["fKeyField"] = $column;
        } else {

            $defaultColumn["isCustom"] = false;
            $defaultColumn["orderable"] = true;
            $defaultColumn["exportable"] = true;
            $defaultColumn["filterable"] = false;

            if ($column === "id") {
                $this->primaryKey = $this->model->getKeyName();
                $defaultColumn["dbField"] = $this->primaryKey;
                $defaultColumn["fKeyField"] = "";
                $defaultColumn["order"] = "DESC";
            } else {
                $defaultColumn["searchable"] = true;
                $defaultColumn["order"] = "ASC";

                $defaultColumn["dbField"] = $column;
                $defaultColumn["fKeyField"] = $column;
            }
        }

        return $defaultColumn;
    }

    /**
     * Setting field labels to pass to the view
     * @param array $unsetColumns
     * @return Datatable
     */
    public function unsetColumns($unsetColumns = array())
    {
        $columns = $this->columns;

        if (is_array($unsetColumns) && count($unsetColumns) > 0) {
            foreach ($unsetColumns as $column) {
                if ($column !== "" && isset($columns[$column])) {
                    unset($columns[$column]);
                }
            }
        } else {
            $noa = func_num_args(); // number of argument passed,(Number of columns)

            for ($i = 0; $i < $noa; $i++) {
                $column = func_get_arg($i); // get each argument passed

                if ($column !== "" && isset($columns[$column])) {
                    unset($columns[$column]);
                }
            }
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Setting field labels to pass to the view
     * @param array $unsetColumns
     * @return Datatable
     */
    public function unsetCustomFilters($unsetColumns = array())
    {
        $columns = $this->customFilters;

        if (is_array($unsetColumns) && count($unsetColumns) > 0) {
            foreach ($unsetColumns as $column) {
                if ($column !== "" && isset($columns[$column])) {
                    unset($columns[$column]);
                }
            }
        } else {
            $noa = func_num_args(); // number of argument passed,(Number of columns)

            for ($i = 0; $i < $noa; $i++) {
                $column = func_get_arg($i); // get each argument passed

                if ($column !== "" && isset($columns[$column])) {
                    unset($columns[$column]);
                }
            }
        }

        $this->customFilters = $columns;

        return $this;
    }

    /**
     * Setting field labels to pass to the view
     * @param string $column
     * @param string $label
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnLabel($column = "", $label = "", $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        if ($column !== "" && $label !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["label"] = $label;
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting field searchable to pass to the view
     * @param string $column
     * @param bool $searchable
     * @return Datatable
     */
    public function setColumnSearchability($column = "", $searchable = true)
    {
        $columns = $this->columns;

        if ($column !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["searchable"] = $searchable;
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Setting field search type to pass to the view
     * @param string $column
     * @param string $filterMethod
     * @param array $filterOptions
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnFilterMethod($column = "", $filterMethod = "text", $filterOptions = array(), $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        $options = $filterOptions;
        $basedColumns = [];

        if (isset($filterOptions["basedColumns"])) {

            $options = $filterOptions["options"];
            $basedColumns = $filterOptions["basedColumns"];
        }

        if ($column !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["filterable"] = true;
            $columns[$column]["filterMethod"] = $filterMethod;
            $columns[$column]["filterOptions"] = $options;
            $columns[$column]["basedColumns"] = $basedColumns;

            //filterMethod can be : text, select
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting field orderable to pass to the view
     * @param string $column
     * @param bool $orderable
     * @param string $order
     * @return Datatable
     */
    public function setColumnOrderability($column = "", $orderable = true, $order = "ASC")
    {
        $columns = $this->columns;

        if ($column !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["orderable"] = $orderable;
            $columns[$column]["order"] = $order;
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Setting field searchable to pass to the view
     * @param string $column
     * @param string $position after or before
     * @return Datatable
     */
    public function setCustomFilterPosition($column = "", $position = "before")
    {
        $columns = $this->customFilters;

        if ($column !== "" && isset($columns[$column])) {

            $columns[$column]["filterPosition"] = $position;
        }

        $this->customFilters = $columns;

        return $this;
    }

    /**
     * Setting field exportable to pass to the view
     * @param string $column
     * @param bool $exportable
     * @return Datatable
     */
    public function setColumnExportability($column = "", $exportable = true)
    {
        $columns = $this->columns;

        if ($column !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["exportable"] = $exportable;
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Setting field orderable to pass to the view
     * @param string $column
     * @param string $dbField
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnDBField($column = "", $dbField = "", $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        if ($column !== "" && $dbField !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["dbField"] = $dbField;

            if ($columns[$column]["fKeyField"] == $column) {

                $columns[$column]["fKeyField"] = $dbField;
            }
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting field orderable to pass to the view
     * @param string $column
     * @param string $ownerKey
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnFKeyField($column = "", $ownerKey = "", $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        if ($column !== "" && $ownerKey !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["fKeyField"] = $ownerKey;
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting field relation to match with the ORM
     * @param string $column
     * @param string $relation
     * @param string $relationField
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnRelation($column, $relation, $relationField, $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        if ($column !== "" && $relation !== "" && $relationField !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["relation"] = Str::studly($relation);
            $columns[$column]["relationField"] = $relationField;
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting field relation to match with the ORM
     * @param string $column
     * @param string $relationOtherField
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnRelationOtherField($column, $relationOtherField, $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        if ($column !== "" && $relationOtherField !== "" && isset($columns[$column])) {

            $columns[$column]["relationOtherField"] = $relationOtherField;
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting field relation to match with the ORM
     * @param $column
     * @param $coRelation
     * @param $coRelationField
     * @param $coRelationFKeyField
     * @param string $coRelationDBField
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnCoRelation($column, $coRelation, $coRelationField, $coRelationFKeyField, $coRelationDBField = "id", $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        if ($column !== "" && $coRelation !== "" && $coRelationField !== "" && isset($columns[$column])) {
            //set field and field's label
            $columns[$column]["coRelation"] = Str::studly($coRelation);
            $columns[$column]["coRelationField"] = $coRelationField;
            $columns[$column]["coRelationDBField"] = $coRelationDBField;
            $columns[$column]["coRelationFKeyField"] = $coRelationFKeyField;
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting field relation to match with the ORM
     * @param string $column
     * @param string $coRelationOtherField
     * @param bool $customFilter
     * @return Datatable
     */
    public function setColumnCoRelationOtherField($column, $coRelationOtherField, $customFilter = false)
    {
        if ($customFilter) {

            $columns = $this->customFilters;
        } else {

            $columns = $this->columns;
        }

        if ($column !== "" && $coRelationOtherField !== "" && isset($columns[$column])) {

            $columns[$column]["coRelationOtherField"] = $coRelationOtherField;
        }

        if ($customFilter) {

            $this->customFilters = $columns;
        } else {

            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Setting fields, how to display in the output
     * @param string $column
     * @param $call_back
     * @param array $params
     * @return Datatable
     */
    public function setColumnDisplay($column, $call_back, $params = array())
    {
        $columns = $this->columns;

        if ($column !== "" && isset($columns[$column])) {
            //set fields display settings
            $columns[$column]["render"] = call_user_func_array($call_back, $params);
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Getting which fields to be filtered from the select query, that'll be executing
     * @return array
     */
    public function getColumns()
    {
        //check columns has been not set from controller
        if (count($this->columns) === 0) {
            //then set default table's columns
            $tableColumns = $this->tableColumns;

            $this->setColumns($tableColumns);
        }

        return $this->columns;
    }

    public function render($path)
    {
        $this->extendViewPath = $path;

        return $this;
    }

    /**
     * Generate columns to pass to the UI or read and serve data according to the request
     * @param $query
     * @return mixed
     */
    public function index($query)
    {
        if (isset($_POST["submit"])) {
            //create an instance of Form library
            $request = request();

            $draw = $request->post("draw");
            $start = $request->post("start");
            $length = $request->post("length");
            $columns = $request->post("columns");
            $order = $request->post("order");
            $searchGet = $request->post("search");
            $modelColumns = $this->getColumns();

            $mainSearchValue = "";
            if (isset($searchGet) && is_array($searchGet) && count($searchGet) > 0) {

                if ($searchGet["value"] !== "" && $searchGet["value"] !== null) {

                    $mainSearchValue = $searchGet["value"];
                }
            }

            $this->defaultSearchFields = [];

            if (is_array($this->customFilters) && count($this->customFilters) > 0) {

                foreach ($this->customFilters as $column) {

                    $query = $this->prepareFilter($query, $column, true);
                }
            }

            if (isset($columns) && is_array($columns) && count($columns) > 0) {

                foreach ($columns as $column) {

                    $query = $this->prepareFilter($query, $column);
                }

                //check if it has been set a default value for search
                if ($mainSearchValue !== "") {

                    $defaultSearchFields = $this->defaultSearchFields;

                    if (count($defaultSearchFields) > 0) {
                        $query->where(function ($query) use ($defaultSearchFields, $mainSearchValue, $modelColumns) {

                            foreach ($defaultSearchFields as $dsfKey => $field) {
                                $modelColumn = $modelColumns[$field];
                                $filterOptions = $modelColumn["filterOptions"];

                                if ($modelColumn["filterMethod"] === "text" && is_array($filterOptions) && count($filterOptions) > 0) {

                                    foreach ($filterOptions as $foKey => $filterField) {

                                        if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {
                                            $relation = $modelColumn["relation"];
                                            $relationField = $filterField;

                                            if (is_array($filterField) && isset($filterField["relation"]) && isset($filterField["fields"])) {

                                                $fields = $filterField["fields"];
                                                $fRelation = $filterField["relation"];

                                                if (is_array($fields) && count($fields) > 0) {


                                                    if ($dsfKey === 0) {

                                                        if ($foKey === 0) {
                                                            $query->whereHas($relation, function ($query) use ($mainSearchValue, $fRelation, $fields) {

                                                                $query->whereHas($fRelation, function ($query) use ($mainSearchValue, $fields) {

                                                                    foreach ($fields as $fKey => $field) {

                                                                        if ($fKey === 0) {

                                                                            $query->where(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                        } else {

                                                                            $query->orWhere(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                        }
                                                                    }
                                                                });
                                                            });
                                                        } else {
                                                            $query->orWhereHas($relation, function ($query) use ($mainSearchValue, $fRelation, $fields) {

                                                                $query->whereHas($fRelation, function ($query) use ($mainSearchValue, $fields) {

                                                                    foreach ($fields as $fKey => $field) {

                                                                        if ($fKey === 0) {

                                                                            $query->where(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                        } else {

                                                                            $query->orWhere(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                        }
                                                                    }
                                                                });
                                                            });
                                                        }
                                                    } else {
                                                        $query->orWhereHas($relation, function ($query) use ($mainSearchValue, $fRelation, $fields) {

                                                            $query->whereHas($fRelation, function ($query) use ($mainSearchValue, $fields) {

                                                                foreach ($fields as $fKey => $field) {

                                                                    if ($fKey === 0) {

                                                                        $query->where(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                    } else {

                                                                        $query->orWhere(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                    }
                                                                }
                                                            });
                                                        });
                                                    }
                                                }
                                            } else {

                                                if ($dsfKey === 0) {

                                                    if ($foKey === 0) {

                                                        $query->whereHas($relation, function ($query) use ($relationField, $mainSearchValue) {

                                                            $query->where($relationField, 'LIKE', '%' . $mainSearchValue . '%');
                                                        });
                                                    } else {

                                                        $query->orWhereHas($relation, function ($query) use ($relationField, $mainSearchValue) {

                                                            $query->where($relationField, 'LIKE', '%' . $mainSearchValue . '%');
                                                        });
                                                    }
                                                } else {

                                                    $query->orWhereHas($relation, function ($query) use ($relationField, $mainSearchValue) {

                                                        $query->where($relationField, 'LIKE', '%' . $mainSearchValue . '%');
                                                    });
                                                }
                                            }
                                        } else {

                                            if (is_array($filterField) && isset($filterField["relation"]) && isset($filterField["fields"])) {

                                                $fields = $filterField["fields"];
                                                $fRelation = $filterField["relation"];

                                                if (is_array($fields) && count($fields) > 0) {

                                                    if ($dsfKey === 0) {

                                                        if ($foKey === 0) {

                                                            $query->whereHas($fRelation, function ($query) use ($mainSearchValue, $fields) {

                                                                foreach ($fields as $fKey => $field) {

                                                                    if ($fKey === 0) {

                                                                        $query->where(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                    } else {

                                                                        $query->orWhere(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                    }
                                                                }
                                                            });
                                                        } else {

                                                            $query->orWhereHas($fRelation, function ($query) use ($mainSearchValue, $fields) {

                                                                foreach ($fields as $fKey => $field) {

                                                                    if ($fKey === 0) {

                                                                        $query->where(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                    } else {

                                                                        $query->orWhere(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                    }
                                                                }
                                                            });
                                                        }
                                                    } else {

                                                        $query->orWhereHas($fRelation, function ($query) use ($mainSearchValue, $fields) {

                                                            foreach ($fields as $fKey => $field) {

                                                                if ($fKey === 0) {

                                                                    $query->where(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                } else {

                                                                    $query->orWhere(DB::raw($field), "LIKE", "%" . $mainSearchValue . "%");
                                                                }
                                                            }
                                                        });
                                                    }
                                                }
                                            } else {

                                                if ($dsfKey === 0) {

                                                    if ($foKey === 0) {

                                                        $query->where(DB::raw($filterField), "LIKE", "%" . $mainSearchValue . "%");
                                                    } else {

                                                        $query->orWhere(DB::raw($filterField), "LIKE", "%" . $mainSearchValue . "%");
                                                    }
                                                } else {

                                                    $query->orWhere(DB::raw($filterField), "LIKE", "%" . $mainSearchValue . "%");
                                                }
                                            }
                                        }
                                    }
                                } else {

                                    if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {
                                        $relation = $modelColumn["relation"];
                                        $relationField = $modelColumn["relationField"];

                                        if ($modelColumn["relationOtherField"] !== "") {

                                            $relationField = $modelColumn["relationOtherField"];
                                        }

                                        if ($dsfKey === 0) {

                                            $query->whereHas($relation, function ($query) use ($relationField, $mainSearchValue) {

                                                $query->where($relationField, 'LIKE', '%' . $mainSearchValue . '%');
                                            });
                                        } else {

                                            $query->orWhereHas($relation, function ($query) use ($relationField, $mainSearchValue) {

                                                $query->where($relationField, 'LIKE', '%' . $mainSearchValue . '%');
                                            });
                                        }
                                    } else {

                                        $dbField = $modelColumn["dbField"];
                                        if ($dsfKey === 0) {

                                            $query->where(DB::raw($dbField), "LIKE", "%" . $mainSearchValue . "%");

                                        } else {

                                            $query->orWhere(DB::raw($dbField), "LIKE", "%" . $mainSearchValue . "%");
                                        }
                                    }
                                }
                            }
                        });
                    }
                }
            }

            //get count from sql query, because laravel is getting the count once after retrieving all the records according to the conditions
            //which has passed to the query builder. It consumes more memory and it's an unwanted operation
            //instead of that we will get the count of records directly from the database using db query
            $qBAll = $query->select(DB::raw("COUNT(DISTINCT " . $this->primaryKey . ") AS count"))->first();
            $allCount = $qBAll["count"];

            //changing select, because query builder only knows above select, but not what we want
            $query->select("*");

            $currFields = [];
            if (isset($order) && is_array($order) && count($order) > 0) {
                foreach ($order as $orderValue) {
                    $fieldOrder = $orderValue["dir"];
                    $fieldIndex = $orderValue["column"];

                    $field = $columns[$fieldIndex]["data"];
                    $modelColumn = $modelColumns[$field];

                    $orderField = $field;
                    if (isset($modelColumn["dbField"])) {
                        $orderField = $modelColumn["dbField"];
                    }

                    if ($orderField != "" && !in_array($orderField, $currFields)) {

                        $query->orderBy($orderField, $fieldOrder);
                        $currFields[] = $orderField;
                    }
                }
            }

            $results = $query->limit($length)->offset($start)->get();

            $dataOutput = [];

            $dataOutput["draw"] = $draw;
            if ($results) {

                $results = $this->_getPreparedData($results);

                $dataOutput["recordsTotal"] = $allCount;
                $dataOutput["recordsFiltered"] = $allCount;
                $dataOutput["data"] = $results;
            } else {
                $dataOutput["recordsTotal"] = 0;
                $dataOutput["recordsFiltered"] = 0;
                $dataOutput["data"] = [];
            }

            return json_encode($dataOutput);
        } else {
            $this->_prepareValidatedUrls();
            $this->viewData->columns = $this->getColumns();
            $this->viewData->customFilters = $this->customFilters;

            $viewData = $this->viewData;

            $module = $this->getCurrentModule();

            if ($module !== "") {
                $extendViewPath = config($module . ".datatable_template");
            } else {
                $extendViewPath = "layouts.app";
            }

            if ($this->extendViewPath !== "") {
                $extendViewPath = $this->extendViewPath;
            }

            $buttons = $this->buttons;
            $rowActionBeforeButtons = $this->rowActionBeforeButtons;
            $rowActionAfterButtons = $this->rowActionAfterButtons;

            return view($this->viewPath, compact("extendViewPath", "viewData", "buttons",
                "rowActionBeforeButtons", "rowActionAfterButtons"));
        }
    }

    private function prepareFilter($query, $column, $isCustom = false)
    {
        if ($isCustom) {

            $field = $column["column"];
            $modelColumn = $column;
        } else {

            $field = $column["data"];
            $modelColumns = $this->getColumns();

            $modelColumn = $modelColumns[$field];
        }

        $dbField = $modelColumn["dbField"];
        $filterable = $modelColumn["filterable"];
        $searchable = $modelColumn["searchable"];
        $filterOptions = $modelColumn["filterOptions"];
        $isCustom = $modelColumn["isCustom"];

        if ($filterable) {

            if ($isCustom) {

                $searchValue = request()->post("custom_" . $field);

                if ($searchValue) {

                    $searchValue = rawurldecode($searchValue);
                } else {
                    $searchValue = "";
                }
            } else {

                $search = $column["search"];
                $searchValue = rawurldecode($search["value"]);
            }

            if ($searchValue !== "") {
                $searchValueArr = @json_decode($searchValue, true);

                //check if this is a date filter first
                if (isset($searchValueArr["type"]) && $this->isDateFilter($searchValueArr["type"])) {

                    if ($searchValueArr["type"] === "date_between") {

                        $dateFrom = $searchValueArr["date_from"];
                        $dateTill = $searchValueArr["date_till"];

                        if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {

                            $relation = $modelColumn["relation"];
                            if ($modelColumn["coRelation"] === "") {

                                $relationField = $modelColumn["relationField"];
                                if ($modelColumn["relationOtherField"] !== "") {

                                    $relationField = $modelColumn["relationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($relationField, $dateFrom, $dateTill) {

                                    $query->whereDate($relationField, ">=", $dateFrom);
                                    $query->whereDate($relationField, "<=", $dateTill);
                                });
                            } else {
                                $coRelation = $modelColumn["coRelation"];
                                $relationField = $modelColumn["coRelationField"];

                                if ($modelColumn["coRelationOtherField"] !== "") {

                                    $relationField = $modelColumn["coRelationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($coRelation, $relationField, $dateFrom, $dateTill) {

                                    $query->whereHas($coRelation, function ($query) use ($relationField, $dateFrom, $dateTill) {

                                        $query->whereDate($relationField, ">=", $dateFrom);
                                        $query->whereDate($relationField, "<=", $dateTill);
                                    });
                                });
                            }
                        } else {

                            $query->whereDate($dbField, ">=", $dateFrom);
                            $query->whereDate($dbField, "<=", $dateTill);
                        }
                    } elseif ($searchValueArr["type"] === "date_after") {

                        $date = $searchValueArr["date"];

                        if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {

                            $relation = $modelColumn["relation"];
                            if ($modelColumn["coRelation"] === "") {

                                $relationField = $modelColumn["relationField"];
                                if ($modelColumn["relationOtherField"] !== "") {

                                    $relationField = $modelColumn["relationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($relationField, $date) {

                                    $query->where(DB::raw("LEFT(" . $relationField . ", 10)"), ">", $date);
                                });
                            } else {
                                $coRelation = $modelColumn["coRelation"];
                                $relationField = $modelColumn["coRelationField"];

                                if ($modelColumn["coRelationOtherField"] !== "") {

                                    $relationField = $modelColumn["coRelationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($coRelation, $relationField, $date) {

                                    $query->whereHas($coRelation, function ($query) use ($relationField, $date) {

                                        $query->where(DB::raw("LEFT(" . $relationField . ", 10)"), ">", $date);
                                    });
                                });
                            }
                        } else {

                            $query->where(DB::raw("LEFT(" . $dbField . ", 10)"), ">", $date);
                        }

                    } elseif ($searchValueArr["type"] === "date_before") {

                        $date = $searchValueArr["date"];

                        if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {

                            $relation = $modelColumn["relation"];
                            if ($modelColumn["coRelation"] === "") {

                                $relationField = $modelColumn["relationField"];
                                if ($modelColumn["relationOtherField"] !== "") {

                                    $relationField = $modelColumn["relationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($relationField, $date) {

                                    $query->where(DB::raw("LEFT(" . $relationField . ", 10)"), "<", $date);
                                });
                            } else {
                                $coRelation = $modelColumn["coRelation"];
                                $relationField = $modelColumn["coRelationField"];

                                if ($modelColumn["coRelationOtherField"] !== "") {

                                    $relationField = $modelColumn["coRelationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($coRelation, $relationField, $date) {

                                    $query->whereHas($coRelation, function ($query) use ($relationField, $date) {

                                        $query->where(DB::raw("LEFT(" . $relationField . ", 10)"), "<", $date);
                                    });
                                });
                            }
                        } else {

                            $query->where(DB::raw("LEFT(" . $dbField . ", 10)"), "<", $date);
                        }
                    } else {

                        $date = $searchValueArr["date"];

                        if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {

                            $relation = $modelColumn["relation"];
                            if ($modelColumn["coRelation"] === "") {

                                $relationField = $modelColumn["relationField"];
                                if ($modelColumn["relationOtherField"] !== "") {

                                    $relationField = $modelColumn["relationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($relationField, $date) {

                                    $query->where(DB::raw("LEFT(" . $relationField . ", 10)"), $date);
                                });
                            } else {
                                $coRelation = $modelColumn["coRelation"];
                                $relationField = $modelColumn["coRelationField"];

                                if ($modelColumn["coRelationOtherField"] !== "") {

                                    $relationField = $modelColumn["coRelationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($coRelation, $relationField, $date) {

                                    $query->whereHas($coRelation, function ($query) use ($relationField, $date) {

                                        $query->where(DB::raw("LEFT(" . $relationField . ", 10)"), $date);
                                    });
                                });
                            }
                        } else {

                            $query->where(DB::raw("LEFT(" . $dbField . ", 10)"), $date);
                        }
                    }
                } elseif (isset($modelColumn["filterMethod"]) && $modelColumn["filterMethod"] === "select") {
                    $svExpDel = ",";
                    $searchValueExp = explode($svExpDel, $searchValue);

                    if (is_array($searchValueExp) && count($searchValueExp) > 1) {

                        if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {

                            $relation = $modelColumn["relation"];

                            if ($modelColumn["coRelation"] === "") {

                                $relationField = $modelColumn["fKeyField"];
                                if ($modelColumn["relationOtherField"] !== "") {

                                    $relationField = $modelColumn["relationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($relationField, $searchValueExp) {

                                    $query->whereIn($relationField, $searchValueExp);
                                });
                            } else {

                                $coRelation = $modelColumn["coRelation"];
                                $relationField = $modelColumn["coRelationFKeyField"];

                                if ($modelColumn["coRelationOtherField"] !== "") {

                                    $relationField = $modelColumn["coRelationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($coRelation, $relationField, $searchValueExp) {

                                    $query->whereHas($coRelation, function ($query) use ($relationField, $searchValueExp) {

                                        $query->whereIn($relationField, $searchValueExp);
                                    });
                                });
                            }
                        } else {

                            $query->whereIn($dbField, $searchValueExp);
                        }
                    } else {

                        if ($modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {

                            $relation = $modelColumn["relation"];
                            if ($modelColumn["coRelation"] === "") {

                                $relationField = $modelColumn["fKeyField"];
                                if ($modelColumn["relationOtherField"] !== "") {

                                    $relationField = $modelColumn["relationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($relationField, $searchValue) {

                                    $query->where($relationField, $searchValue);
                                });
                            } else {

                                $coRelation = $modelColumn["coRelation"];
                                $relationField = $modelColumn["coRelationFKeyField"];

                                if ($modelColumn["coRelationOtherField"] !== "") {

                                    $relationField = $modelColumn["coRelationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($coRelation, $relationField, $searchValue) {

                                    $query->whereHas($coRelation, function ($query) use ($relationField, $searchValue) {

                                        $query->where($relationField, $searchValue);
                                    });
                                });
                            }
                        } else {

                            $query->where($dbField, $searchValue);
                        }
                    }
                } else {

                    if (is_array($filterOptions) && count($filterOptions) > 0) {

                        $query->where(function ($query) use ($modelColumn, $searchValue, $filterOptions) {

                            foreach ($filterOptions as $foKey => $filterField) {

                                if (isset($modelColumn["relation"]) && $modelColumn["relation"] !== "") {

                                    $relation = $modelColumn["relation"];
                                    $relationField = $filterField;

                                    if (is_array($filterField) && isset($filterField["relation"]) && isset($filterField["fields"])) {

                                        $fields = $filterField["fields"];
                                        $fRelation = $filterField["relation"];

                                        if (is_array($fields) && count($fields) > 0) {

                                            if ($foKey === 0) {

                                                $query->whereHas($relation, function ($query) use ($searchValue, $fRelation, $fields) {

                                                    $query->whereHas($fRelation, function ($query) use ($searchValue, $fields) {

                                                        foreach ($fields as $fKey => $field) {

                                                            if ($fKey === 0) {

                                                                $query->where(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                            } else {

                                                                $query->orWhere(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                            }
                                                        }
                                                    });
                                                });
                                            } else {

                                                $query->orWhereHas($relation, function ($query) use ($searchValue, $fRelation, $fields) {

                                                    $query->whereHas($fRelation, function ($query) use ($searchValue, $fields) {

                                                        foreach ($fields as $fKey => $field) {

                                                            if ($fKey === 0) {

                                                                $query->where(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                            } else {

                                                                $query->orWhere(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                            }
                                                        }
                                                    });
                                                });
                                            }
                                        }
                                    } else {

                                        if ($foKey === 0) {

                                            $query->whereHas($relation, function ($query) use ($relationField, $searchValue) {

                                                $query->where($relationField, 'LIKE', '%' . $searchValue . '%');
                                            });
                                        } else {

                                            $query->orWhereHas($relation, function ($query) use ($relationField, $searchValue) {

                                                $query->where($relationField, 'LIKE', '%' . $searchValue . '%');
                                            });
                                        }
                                    }
                                } else {

                                    if (is_array($filterField) && isset($filterField["relation"]) && isset($filterField["fields"])) {

                                        $fields = $filterField["fields"];
                                        $fRelation = $filterField["relation"];

                                        if (is_array($fields) && count($fields) > 0) {

                                            if ($foKey === 0) {

                                                $query->whereHas($fRelation, function ($query) use ($searchValue, $fields) {

                                                    foreach ($fields as $fKey => $field) {

                                                        if ($fKey === 0) {

                                                            $query->where(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                        } else {

                                                            $query->orWhere(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                        }
                                                    }
                                                });
                                            } else {
                                                $query->orWhereHas($fRelation, function ($query) use ($searchValue, $fields) {

                                                    foreach ($fields as $fKey => $field) {

                                                        if ($fKey === 0) {

                                                            $query->where(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                        } else {

                                                            $query->orWhere(DB::raw($field), "LIKE", "%" . $searchValue . "%");
                                                        }
                                                    }
                                                });
                                            }
                                        }
                                    } else {

                                        if ($foKey === 0) {

                                            $query->where(DB::raw($filterField), "LIKE", "%" . $searchValue . "%");
                                        } else {

                                            $query->orWhere(DB::raw($filterField), "LIKE", "%" . $searchValue . "%");
                                        }
                                    }
                                }
                            }
                        });
                    } else {

                        if (isset($modelColumn["relation"]) && $modelColumn["relation"] !== "" && $modelColumn["relationField"] !== "") {

                            $relation = $modelColumn["relation"];
                            if ($modelColumn["coRelation"] === "") {

                                $relationField = $modelColumn["relationField"];
                                if ($modelColumn["relationOtherField"] !== "") {

                                    $relationField = $modelColumn["relationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($relationField, $searchValue) {

                                    $query->where($relationField, 'LIKE', '%' . $searchValue . '%');
                                });
                            } else {

                                $coRelation = $modelColumn["coRelation"];
                                $relationField = $modelColumn["coRelationField"];

                                if ($modelColumn["coRelationOtherField"] !== "") {

                                    $relationField = $modelColumn["coRelationOtherField"];
                                }

                                $query->whereHas($relation, function ($query) use ($coRelation, $relationField, $searchValue) {

                                    $query->whereHas($coRelation, function ($query) use ($relationField, $searchValue) {

                                        $query->where($relationField, 'LIKE', '%' . $searchValue . '%');
                                    });
                                });
                            }
                        } else {

                            $query->where(DB::raw($dbField), "LIKE", "%" . $searchValue . "%");
                        }
                    }
                }
            } else {

                if (!$isCustom) {

                    $this->defaultSearchFields[] = $field;
                }
            }
        } elseif ($searchable) {

            if (!$isCustom) {

                $this->defaultSearchFields[] = $field;
            }
        }

        return $query;
    }

    /**
     * @param $searchType
     * @return bool
     */
    private function isDateFilter($searchType): bool
    {
        $del = "_";
        $searchTypeExp = explode($del, $searchType);

        if (count($searchTypeExp) > 0) {

            if ($searchTypeExp[0] === "date") {

                return true;
            }
        }

        return false;
    }

    /**
     * @param $results
     * @return array
     */
    private function _getPreparedData($results): array
    {
        $data = [];
        if ($results) {

            $columns = $this->columns;

            foreach ($results as $result) {

                $record = $result->toArray();
                foreach ($columns as $column => $modelColumn) {

                    if (!isset($record[$column])) {

                        $record[$column] = "";
                    } else {

                        if ($column === "id" && $modelColumn["dbField"] !== $this->primaryKey) {

                            if (isset($record[$modelColumn["dbField"]])) {

                                $record["id"] = $record[$modelColumn["dbField"]];
                            }
                        }
                    }
                }

                if ($this->showApprovalUrl) {

                    $record["approval_url"] = $this->_getApprovalUrl($result);
                }

                $record = $this->getRecordPrepared($record);

                $data[] = $record;
            }
        }

        return $data;
    }

    /**
     * Validate Urls and set to pass to the view
     * @return array
     */
    private function _prepareValidatedUrls()
    {
        $btnUrls = $this->_getButtonUrls();
        $beforeBtnUrls = $this->_getBeforeButtonUrls();
        $afterBtnUrls = $this->_getAfterButtonUrls();
        $viewUrls = $this->_getViewDataUrls();

        $urls = array_merge($btnUrls, $beforeBtnUrls, $afterBtnUrls, $viewUrls);

        $urls = $this->validateUrls($urls);
        $this->_setButtonUrls($urls);
        $this->_setBeforeButtonUrls($urls);
        $this->_setAfterButtonUrls($urls);
        $this->_setViewDataUrls($urls);

        return $urls;
    }

    /**
     * Set validated button URLs to pass to the view
     * @param $urls
     */
    private function _setButtonUrls($urls)
    {
        $buttons = $this->buttons;
        $validatedButtons = [];

        if (count($buttons) > 0) {
            foreach ($buttons as $key => $button) {
                if (isset($urls["btn_" . $key]) && $urls["btn_" . $key] !== "") {
                    $validatedButtons[] = $button;
                }
            }
        }

        $this->buttons = $validatedButtons;
    }

    /**
     * Return URLs of the additional buttons which have been set to pass to the view
     * @return array
     */
    private function _getButtonUrls()
    {
        $urls = [];

        $buttons = $this->buttons;

        if (count($buttons) > 0) {
            foreach ($buttons as $key => $button) {
                $urls["btn_" . $key] = $button["url"];
            }
        }

        return $urls;
    }

    /**
     * Set validated button URLs to pass to the view
     * @param $urls
     */
    private function _setBeforeButtonUrls($urls)
    {
        $buttons = $this->rowActionBeforeButtons;
        $validatedButtons = [];

        if (count($buttons) > 0) {
            foreach ($buttons as $key => $button) {
                if (isset($urls["btn_before_" . $key]) && $urls["btn_before_" . $key] !== "") {
                    $validatedButtons[] = $button;
                }
            }
        }

        $this->rowActionBeforeButtons = $validatedButtons;
    }

    /**
     * Return URLs of the additional buttons which have been set to pass to the view
     * @return array
     */
    private function _getBeforeButtonUrls()
    {
        $urls = [];

        $buttons = $this->rowActionBeforeButtons;

        if (count($buttons) > 0) {
            foreach ($buttons as $key => $button) {
                $urls["btn_before_" . $key] = $button["url"];
            }
        }

        return $urls;
    }

    /**
     * Set validated button URLs to pass to the view
     * @param $urls
     */
    private function _setAfterButtonUrls($urls)
    {
        $buttons = $this->rowActionAfterButtons;
        $validatedButtons = [];

        if (count($buttons) > 0) {
            foreach ($buttons as $key => $button) {
                if (isset($urls["btn_after_" . $key]) && $urls["btn_after_" . $key] !== "") {
                    $validatedButtons[] = $button;
                }
            }
        }

        $this->rowActionAfterButtons = $validatedButtons;
    }

    /**
     * Return URLs of the additional buttons which have been set to pass to the view
     * @return array
     */
    private function _getAfterButtonUrls()
    {
        $urls = [];

        $buttons = $this->rowActionAfterButtons;

        if (count($buttons) > 0) {
            foreach ($buttons as $key => $button) {
                $urls["btn_after_" . $key] = $button["url"];
            }
        }

        return $urls;
    }

    /**
     * Set validated button URLs to pass to the view
     * @param $urls
     */
    private function _setViewDataUrls($urls)
    {
        foreach ($this->operations as $operation) {
            $enabledOp = "enable" . ucfirst($operation);

            if ($this->viewData->$enabledOp) {
                $urlKey = $operation . "Url";

                if (!isset($urls[$urlKey]) || $urls[$urlKey] === "") {
                    $this->viewData->$enabledOp = false;
                }
            }
        }
    }

    /**
     * Return default URLs which have been set to pass to the view
     * @return array
     */
    private function _getViewDataUrls()
    {
        $urls = [];

        foreach ($this->operations as $operation) {
            $enabledOp = "enable" . ucfirst($operation);

            if ($this->viewData->$enabledOp) {
                $urlKey = $operation . "Url";
                $url = $this->viewData->$urlKey;

                $urls[$urlKey] = $url;
            }
        }

        return $urls;
    }

    /**
     * Build viewData variable to pass to the UI
     * @param string $uri
     * @return void
     */
    private function _setControllerUrl(string $uri)
    {
        $controllerUrl = $this->getRouteUri($uri);

        $this->controllerUrl = URL::to($controllerUrl);
    }

    /**
     * Build viewData variable to pass to the UI
     * @param string $url
     * @return string
     */
    private function _getPermittedUrl(string $url): string
    {
        if ($url !== "" && !Permission::haveUrlPermission($url)) {
            $url = "";
        }

        return $url;
    }

    private function _setFetchUrl($uri)
    {
        if ($this->fetchUrl) {

            $uri = $this->fetchUrl;
        }

        //set data fetch url
        $this->viewData->fetchUrl = URL::to($uri);
    }

    /**
     * Build viewData variable to pass to the UI
     * @return void
     */
    private function _setIndexUrls()
    {
        $uri = request()->getPathInfo();

        $this->_setFetchUrl($uri);
        $this->_setControllerUrl($uri);

        $listUrl = str_replace("/trash", "", $this->controllerUrl);
        $this->viewData->listUrl = $listUrl;
        $this->viewData->listUrlLabel = "View List";
        $this->viewData->listUrlIcon = "fa fa-list";
        $this->viewData->listUrlColumn = ""; //column name if it comes from a table column

        $this->viewData->addUrl = $listUrl . "/create";
        $this->viewData->addUrlLabel = "Add New";
        $this->viewData->addUrlIcon = "fa fa-plus";
        $this->viewData->addUrlColumn = "";

        $this->viewData->editUrl = $listUrl . "/edit/";
        $this->viewData->editUrlLabel = "Edit";
        $this->viewData->editUrlIcon = "fa fa-edit";
        $this->viewData->editUrlColumn = "";

        $this->viewData->viewUrl = $listUrl . "/view/";
        $this->viewData->viewUrlLabel = "view";
        $this->viewData->viewUrlIcon = "fa fa-list-alt";
        $this->viewData->viewUrlColumn = "";

        $this->viewData->deleteUrl = $listUrl . "/destroy/";
        $this->viewData->deleteUrlLabel = "Delete";
        $this->viewData->deleteUrlIcon = "fa fa-ban";
        $this->viewData->destroyUrlColumn = "";

        $this->viewData->trashUrl = $listUrl . "/delete/";
        $this->viewData->trashUrlLabel = "Trash";
        $this->viewData->trashUrlIcon = "fa fa-trash";
        $this->viewData->deleteUrlColumn = "";

        $this->viewData->trashListUrl = $listUrl . "/trash";
        $this->viewData->trashListUrlLabel = "View Trash";
        $this->viewData->trashListUrlIcon = "fa fa-trash";
        $this->viewData->trashUrlColumn = "";

        $this->viewData->restoreUrl = $listUrl . "/restore/";
        $this->viewData->restoreUrlLabel = "Restore";
        $this->viewData->restoreUrlIcon = "fas fa-trash-restore";
        $this->viewData->restoreUrlColumn = "";
    }

    /**
     * @return Datatable
     */
    public function enableViewData()
    {
        // number of argument passed,(Number of columns)
        $noa = func_num_args();

        for ($i = 0; $i < $noa; $i++) {
            //get each argument passed
            $action = func_get_arg($i);

            $property = "enable" . ucfirst($action);
            $this->viewData->$property = true;
        }

        return $this;
    }

    /**
     * @return Datatable
     */
    public function disableViewData()
    {
        // number of argument passed,(Number of columns)
        $noa = func_num_args();

        for ($i = 0; $i < $noa; $i++) {
            //get each argument passed
            $action = func_get_arg($i);

            $property = "enable" . ucfirst($action);
            $this->viewData->$property = false;
        }

        return $this;
    }

    /**
     * @param string $action
     * @param string $url
     * @return Datatable
     */
    public function setUrl($action, $url = "")
    {
        $property = $action . "Url";

        $this->viewData->$property = $url;

        return $this;
    }

    /**
     * Using this function it can set a custom controller url for selected operations
     * @param string $url Customer controller url
     * @param array $operations Operations which needs to be applied this url, if it needs to apply to all operations,
     * then send this parameter value as '[]' or null plus send $include parameter as true
     * @param bool $include To include operations set true or to exclude set false which comes in the $operations array
     * @return Datatable
     */
    public function setCustomControllerUrl($url, $operations = [], $include = true)
    {
        if ($include) {
            if (!is_array($operations) || count($operations) === 0) {
                $operations = $this->operations;
            }
        } else {
            if (count($operations) > 0 && count($this->operations) > 0) {
                $selectedOperations = [];
                foreach ($this->operations as $operation) {
                    if (!in_array($operation, $operations)) {
                        $selectedOperations[] = $operation;
                    }
                }
                $operations = $selectedOperations;
            }
        }

        if (is_array($operations) && count($operations) > 0) {
            $currUrl = $this->controllerUrl;
            foreach ($operations as $operation) {
                $property = $operation . "Url";

                if ($this->viewData->$property) {
                    $this->viewData->$property = str_replace($currUrl, $url, $this->viewData->$property);
                }
            }
        }

        return $this;
    }

    /**
     * Get current property value for the url
     * @param string $action
     * @return string
     */
    public function getUrl($action)
    {
        $property = $action . "Url";

        return $this->viewData->$property;
    }

    /**
     * @param string $action
     * @param string $label
     * @return Datatable
     */
    public function setUrlLabel($action, $label = "")
    {
        $property = $action . "UrlLabel";

        $this->viewData->$property = $label;

        return $this;
    }

    /**
     * @param string $action
     * @param string $column
     * @return Datatable
     */
    public function setUrlColumn($action, $column = "")
    {
        $property = $action . "UrlColumn";

        $this->viewData->$property = $column;

        return $this;
    }

    /**
     * Get current property value for the URL label
     * @param string $action
     * @return string
     */
    public function getUrlLabel($action)
    {
        $property = $action . "UrlLabel";

        return $this->viewData->$property;
    }

    /**
     * @param string $action
     * @param string $icon FontAwesome or any icon class/classes which is using in the theme
     * @return Datatable
     */
    public function setUrlIcon($action, $icon = "")
    {
        $property = $action . "UrlIcon";

        $this->viewData->$property = $icon;

        return $this;
    }

    /**
     * Get current property value for the URL icon
     * @param string $action
     * @return string
     */
    public function getUrlIcon($action)
    {
        $property = $action . "UrlIcon";

        return $this->viewData->$property;
    }

    /**
     * @param string $title Title for the datatable records list
     * @return Datatable
     */
    public function setTableTitle($title)
    {
        $this->viewData->tableTitle = $title;

        return $this;
    }

    /**
     * Get current property value for the table title
     * @return string
     */
    public function getTableTitle()
    {
        return $this->viewData->tableTitle;
    }

    /**
     * @param string $url
     * @param string $caption
     * @param string $buttonClasses
     * @param string $iconClasses
     * @return Datatable
     */
    public function setButton($url, $caption, $buttonClasses = "btn btn-info", $iconClasses = "")
    {
        $button = [];
        $button["url"] = $url;
        $button["caption"] = $caption;
        $button["buttonClasses"] = $buttonClasses;
        $button["iconClasses"] = $iconClasses;

        $this->buttons[] = $button;

        return $this;
    }

    /**
     * @param array $buttons button properties [url, caption, buttonClasses, iconClasses]
     * @return Datatable
     */
    public function setButtons($buttons = [])
    {
        if (is_array($buttons) && count($buttons) > 0) {
            foreach ($buttons as $button) {
                if (isset($button["url"]) && isset($button["caption"])) {
                    if (!isset($button["buttonClasses"])) {
                        $button["buttonClasses"] = "btn btn-info";
                    }

                    if (!isset($button["iconClasses"])) {
                        $button["iconClasses"] = "";
                    }

                    $this->buttons[] = $button;
                }
            }
        }

        return $this;
    }

    /**
     * @param string $url
     * @param string $caption
     * @param string $buttonClasses
     * @param string $iconClasses
     * @return Datatable
     */
    public function setRowActionBeforeButton($url, $caption, $buttonClasses = "btn btn-info", $iconClasses = "")
    {
        $button = [];
        $button["url"] = $url;
        $button["caption"] = $caption;
        $button["buttonClasses"] = $buttonClasses;
        $button["iconClasses"] = $iconClasses;

        $this->rowActionBeforeButtons[] = $button;

        return $this;
    }

    /**
     * @param array $buttons button properties [url, caption, buttonClasses, iconClasses]
     * @return Datatable
     */
    public function setRowActionBeforeButtons($buttons = [])
    {
        if (is_array($buttons) && count($buttons) > 0) {
            foreach ($buttons as $button) {
                if (isset($button["url"]) && isset($button["caption"])) {
                    if (!isset($button["buttonClasses"])) {
                        $button["buttonClasses"] = "";
                    }

                    if (!isset($button["iconClasses"])) {
                        $button["iconClasses"] = "";
                    }

                    $this->rowActionBeforeButtons[] = $button;
                }
            }
        }

        return $this;
    }

    /**
     * @param string $url
     * @param string $caption
     * @param string $buttonClasses
     * @param string $iconClasses
     * @return Datatable
     */
    public function setRowActionAfterButton($url, $caption, $buttonClasses = "btn btn-info", $iconClasses = "")
    {
        $button = [];
        $button["url"] = $url;
        $button["caption"] = $caption;
        $button["buttonClasses"] = $buttonClasses;
        $button["iconClasses"] = $iconClasses;

        $this->rowActionAfterButtons[] = $button;

        return $this;
    }

    /**
     * @param array $buttons button properties [url, caption, buttonClasses, iconClasses]
     * @return Datatable
     */
    public function setRowActionAfterButtons($buttons = [])
    {
        if (is_array($buttons) && count($buttons) > 0) {
            foreach ($buttons as $button) {
                if (isset($button["url"]) && isset($button["caption"])) {
                    if (!isset($button["buttonClasses"])) {
                        $button["buttonClasses"] = "";
                    }

                    if (!isset($button["iconClasses"])) {
                        $button["iconClasses"] = "";
                    }

                    $this->rowActionAfterButtons[] = $button;
                }
            }
        }

        return $this;
    }

    /**
     * Setting which columns should be showed in view
     * @param array $formats
     * @return Datatable
     */
    public function setExportFormats($formats = array())
    {
        $defaultFormats = $this->exportFormats;

        $exportFormats = [];
        if (is_array($formats) && count($formats) > 0) {
            foreach ($formats as $format) {
                if (in_array($format, $defaultFormats)) {
                    $exportFormats[] = $format;
                }
            }
        } else {
            $noa = func_num_args(); // number of argument passed,(Number of columns)

            for ($i = 0; $i < $noa; $i++) {
                $format = func_get_arg($i); // get each argument passed

                if (in_array($format, $defaultFormats)) {
                    $exportFormats[] = $format;
                }
            }
        }

        if (count($exportFormats) > 0) {
            $this->viewData->exportFormats = $exportFormats;
        }

        return $this;
    }

    /**
     * Show the ui for displaying record modified details
     * @param bool $enableHistory Show record history or not
     * @param string $url Custom URL to show record history
     * @return Factory|View
     */
    public function displayCreatedAtAs($enableHistory = false, $url = "")
    {
        if ($enableHistory) {

            if ($url === "") {

                $baseRepo = new BaseRepository();
                $url = $baseRepo->getDefaultRecordHistoryUrl($this->controllerUrl, $this->model);
            }

            if (!Permission::haveUrlPermission($url)) {
                $url = "";
            }
        } else {

            $url = "";
        }

        return view("default.common.created_modified_ui", compact('url'));
    }

    /**
     * @param array $states
     * @return Factory|View
     */
    public function displayStatusAs($states = [])
    {
        if (!is_array($states) || count($states) === 0) {
            //state value id, state name (Option), css class for label
            $states = array();
            $states[] = array("id" => "0", "name" => "Disabled", "label" => "danger");
            $states[] = array("id" => "1", "name" => "Enabled", "label" => "success");
        }

        return view("default.common.status_ui", compact('states'));
    }

    /**
     * @param array $states
     * @param string $url
     * @param string $prefix
     * @param false $remarks
     * @param string $urlColumn
     * @param string $urlIdColumn
     * @return Factory|View
     */

    public function displayStatusActionAs($states = [], $url = "", $prefix = "", $remarks = false, $urlColumn = "", $urlIdColumn = "id")
    {
        if ($url === "") {
            $url = $this->controllerUrl . "/change_status/";
        }

        if (!is_array($states) || count($states) === 0) {
            //state value id, state name (Option), css class for label
            $states = array();
            $states[] = array("id" => "0", "name" => "Disabled", "label" => "danger");
            $states[] = array("id" => "1", "name" => "Enabled", "label" => "success");
        }

        if ($urlColumn !== "" || Permission::haveUrlPermission($url)) {
            if ($prefix === "") {
                $prefix = "def";
            }

            return view("default.common.status_action_ui", compact('states', 'url', 'prefix',
                'remarks', 'urlColumn', 'urlIdColumn'));
        }

        return $this->displayStatusAs($states);
    }

    /**
     * @param array $options
     * @return Factory|View
     */
    public function displayArrayListAs($options = [], $separator = "")
    {
        if (!is_array($options)) {
            //state value id, state name (Option), css class for label
            $options = [];
        }

        return view("default.common.array_list_ui", compact('options', 'separator'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $column Relation name which is identifying in the corresponding datatable columns list
     * @param string $statusField Displaying relation status column (model attribute), default value will be status
     * @param array $states
     * @param array $relation If and only if column name differs from relation name
     * @return Factory|View
     */
    public function displayRelationStatusAs($column, $statusField = "", $states = [], $relation = "")
    {
        if (!is_array($states) || count($states) === 0) {
            //state value id, state name (Option), css class for label
            $states = array();
            $states[] = array("id" => "0", "name" => "Disabled", "label" => "danger");
            $states[] = array("id" => "1", "name" => "Enabled", "label" => "success");
        }

        if ($statusField === "") {
            $columns = $this->columns;

            if (isset($columns[$column]["relationField"]) && $columns[$column]["relationField"] !== "") {
                $statusField = $columns[$column]["relationField"];
            } else {
                $statusField = "status";
            }
        }

        if ($relation === "") {

            $relation = $column;
        }

        return view("default.common.relation_status_ui", compact('column', 'relation', 'statusField', 'states'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $column
     * @param string $idField Relation's id (primary key field) column (model attribute), default value will be id
     * @param mixed $nameField Displaying relation data belonging column (model attribute), default value will be name
     * @param string|array $options View page url of the corresponding relation model or more options
     * @return Factory|View
     */
    public function displayRelationAs($column, $idField = "", $nameField = "", $options = [])
    {
        $columns = $this->columns;

        if (is_array($options)) {

            $url = $options["url"] ?? "";
            $caption = $options["caption"] ?? "";
        } else {

            $url = $options;
            $caption = "";
        }

        $url = $this->_getPermittedUrl($url);

        if ($idField === "") {
            if (isset($columns[$column]["fKeyField"]) && $columns[$column]["fKeyField"] !== "") {
                $idField = $columns[$column]["fKeyField"];
            } else {
                $idField = "id";
            }
        }

        if ($nameField === "") {
            if (isset($columns[$column]["relationField"]) && $columns[$column]["relationField"] !== "") {
                $nameField = $columns[$column]["relationField"];
            } else {
                $nameField = "name";
            }
        }

        if (isset($columns[$column]["relation"]) && $columns[$column]["relation"] !== "") {

            $relation = Str::snake($columns[$column]["relation"]);
        } else {

            $relation = Str::snake($column);
        }

        return view("default.common.relation_ui", compact('relation', 'idField', 'nameField', 'url', 'caption'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $column
     * @param string $idField Relation's id (primary key field) column (model attribute), default value will be id
     * @param mixed $nameField Displaying relation data belonging column (model attribute), default value will be name
     * @param string $url View page url of the corresponding relation model
     * @return Factory|View
     */
    public function displayCoRelationAs($column, $idField = "", $nameField = "", $url = "")
    {
        $columns = $this->columns;

        $url = $this->_getPermittedUrl($url);

        if ($idField === "") {
            if (isset($columns[$column]["coRelationDBField"]) && $columns[$column]["coRelationDBField"] !== "") {
                $idField = $columns[$column]["coRelationDBField"];
            } else {
                $idField = "id";
            }
        }

        if ($nameField === "") {
            if (isset($columns[$column]["coRelationField"]) && $columns[$column]["coRelationField"] !== "") {
                $nameField = $columns[$column]["coRelationField"];
            } else {
                $nameField = "name";
            }
        }

        if (isset($columns[$column]["relation"]) && $columns[$column]["relation"] !== "") {

            $relation = Str::snake($columns[$column]["relation"]);
        } else {

            $relation = Str::snake($column);
        }

        if (isset($columns[$column]["coRelation"]) && $columns[$column]["coRelation"] !== "") {

            $coRelation = Str::snake($columns[$column]["coRelation"]);
        } else {

            $coRelation = Str::snake($column);
        }

        return view("default.common.co_relation_ui", compact('relation', 'coRelation', 'idField', 'nameField', 'url'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $states Statuses list
     * @param string $column
     * @param string $idField Relation's id (primary key field) column (model attribute), default value will be id
     * @param mixed $nameField Displaying relation data belonging column (model attribute), default value will be name
     * @param string $url View page url of the corresponding relation model
     * @return Factory|View
     */
    public function displayStatusRelationAs(string $states, string $column, string $idField = "",
                                            $nameField = "", string $url = "")
    {
        $columns = $this->columns;

        $url = $this->_getPermittedUrl($url);

        if ($idField === "") {
            if (isset($columns[$column]["fKeyField"]) && $columns[$column]["fKeyField"] !== "") {
                $idField = $columns[$column]["fKeyField"];
            } else {
                $idField = "id";
            }
        }

        if ($nameField === "") {
            if (isset($columns[$column]["relationField"]) && $columns[$column]["relationField"] !== "") {
                $nameField = $columns[$column]["relationField"];
            } else {
                $nameField = "name";
            }
        }

        if (isset($columns[$column]["relation"]) && $columns[$column]["relation"] !== "") {

            $relation = Str::snake($columns[$column]["relation"]);
        } else {

            $relation = Str::snake($column);
        }

        return view("default.common.status_relation_ui", compact('states', 'relation',
            'idField', 'nameField', 'url'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $column
     * @param string $manyRelation Co-relation name which comes with record values
     * @param string $idField Co-relation's id (primary key field) column (model attribute), default value will be id
     * @param string $nameField Displaying relation data belonging column (model attribute), default value will be name
     * @param string $url View page url of the corresponding relation model
     * @return Factory|View
     */
    public function displayRelationManyAs(string $column, string $manyRelation, string $idField = "",
                                          string $nameField = "", string $url = "")
    {
        $columns = $this->columns;

        $url = $this->_getPermittedUrl($url);

        if ($idField === "") {
            if (isset($columns[$column]["fKeyField"]) && $columns[$column]["fKeyField"] !== "") {
                $idField = $columns[$column]["fKeyField"];
            } else {
                $idField = "id";
            }
        }

        if ($nameField === "") {
            if (isset($columns[$column]["relationField"]) && $columns[$column]["relationField"] !== "") {
                $nameField = $columns[$column]["relationField"];
            } else {
                $nameField = "name";
            }
        }

        if (isset($columns[$column]["relation"]) && $columns[$column]["relation"] !== "") {

            $relation = Str::snake($columns[$column]["relation"]);
        } else {

            $relation = Str::snake($column);
        }

        $manyRelation = Str::snake($manyRelation);

        return view("default.common.relation_many_ui", compact('relation', 'manyRelation',
            'idField', 'nameField', 'url'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $column
     * @param string $manyRelation Co-relation's co relation name which comes with record values
     * @param string $idField Co relation's co relation id (primary key field) column (model attribute), default value will be id
     * @param string $nameField Displaying relation data belonging column (model attribute), default value will be name
     * @param string $url View page url of the corresponding relation model
     * @return Factory|View
     */
    public function displayCoRelationManyAs(string $column, string $manyRelation, string $idField = "",
                                            string $nameField = "", string $url = "")
    {
        $columns = $this->columns;

        $url = $this->_getPermittedUrl($url);

        if ($idField === "") {
            if (isset($columns[$column]["fKeyField"]) && $columns[$column]["fKeyField"] !== "") {
                $idField = $columns[$column]["fKeyField"];
            } else {
                $idField = "id";
            }
        }

        if ($nameField === "") {
            if (isset($columns[$column]["relationField"]) && $columns[$column]["relationField"] !== "") {
                $nameField = $columns[$column]["relationField"];
            } else {
                $nameField = "name";
            }
        }

        if (isset($columns[$column]["relation"]) && $columns[$column]["relation"] !== "") {

            $relation = Str::snake($columns[$column]["relation"]);
        } else {

            $relation = Str::snake($column);
        }

        $coRelation = $columns[$column]["coRelation"];
        $manyRelation = Str::snake($manyRelation);

        return view("default.common.co_relation_many_ui", compact('relation', 'coRelation',
            'manyRelation', 'idField', 'nameField', 'url'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $label Caption for the button
     * @param string $url View page url of the corresponding relation model
     * @return Factory|View
     */
    public function displayListButtonAs(string $label, string $url = "", $column = "")
    {
        $url = $this->_getPermittedUrl($url);

        if ($column === "") {

            $column = "id";
        }

        return view("default.common.list_ui", compact('label', 'url', 'column'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $jsonElem Column name which consists the JSON content
     * @param string $displayElem Displaying JSON array's element in JavaScript JSON element declaration format.
     * Should begin with array; Ex: array[faculty].name
     * @param string $url View page url of the corresponding relation model
     * @param string $urlElem JSON array's element in JavaScript JSON element declaration format of which defined for URL parameter.
     * Should begin with array; Ex: array[faculty].id
     * @return Factory|View
     */
    public function displayJSONAs(string $jsonElem, string $displayElem = "", string $url = "", string $urlElem = "")
    {
        $url = $this->_getPermittedUrl($url);

        return view("default.common.json_ui", compact('jsonElem', 'displayElem', 'url', 'urlElem'));
    }

    /**
     * Return column UI for the datatable of the model
     * @param string $jsonElem Column name which consists the JSON content
     * @param string $listContainerElem JSON list containing JSON variable. If inside a variable then it should begin with array;
     * Ex: array[faculty].list; array[faculties]. If there is no variable then leave it as empty
     * @param string $displayElem Displaying JSON array's element name within the list. Ex: faculty_name
     * @param string $separator List of elements separator. Ex 01: , . Ex:02: "<br>"
     * @param string $url View page url of the corresponding relation model
     * @param string $urlElem JSON array's element name for URL parameter. Ex: id
     * @return Factory|View
     */
    public function displayJSONListAs(string $jsonElem, string $listContainerElem = "", string $displayElem = "",
                                      string $separator = "<br>", string $url = "", string $urlElem = "")
    {
        $url = $this->_getPermittedUrl($url);

        return view("default.common.json_ui", compact('jsonElem', 'listContainerElem',
            'displayElem', 'url', 'urlElem', 'separator'));
    }

    /**
     * @param array $states
     * @param string $linkLabel
     * @param string $url
     * @param boolean $showApprovalUrl
     * @return Factory|View
     */
    public function displayApprovalStatusAs(array $states = array(), string $linkLabel = "View Approval History",
                                            string $url = "", bool $showApprovalUrl = true)
    {
        if (!is_array($states) || count($states) === 0) {
            //state value, state name (Option), css class for label
            $states = array();
            $states[] = array("id" => "", "name" => "Not Sent For Approval", "label" => "info");
            $states[] = array("id" => "0", "name" => "Pending Approval", "label" => "warning");
            $states[] = array("id" => "1", "name" => "Approved", "label" => "success");
            $states[] = array("id" => "2", "name" => "Declined", "label" => "danger");
        }

        if ($url === "") {

            $baseRepo = new BaseRepository();
            $url = $baseRepo->getDefaultApprovalHistoryUrl($this->controllerUrl, $this->model);
        }

        if (!Permission::haveUrlPermission($url)) {
            $url = "";
        }

        if ($linkLabel === "") {

            $linkLabel = "View Approval History";
        }

        if ($showApprovalUrl) {

            $this->showApprovalUrl = true;
            $showApprovalUrl = "Y";
        } else {

            $showApprovalUrl = "N";

            $this->showApprovalUrl = false;
        }

        return view("default.common.approval_status_ui", compact('states', 'linkLabel', 'url', 'showApprovalUrl'));
    }

    /**
     * @param $model
     * @return string
     */
    private function _getApprovalUrl($model): string
    {
        $url = $this->getApprovalUrl($model);

        if (!Permission::haveUrlPermission($url)) {
            $url = "";
        }

        return $url;
    }

    /**
     * @param string $imageUrl Base URL for the image or the storage path for the image
     * @param mixed $width 'auto' for auto width according to height or exact width
     * @param mixed $height 'auto' for auto height according to width or exact height
     * @param string $urlColumn If image will have a link then URL column for the link
     * @return Factory|View
     */
    public function displayImageAs(string $imageUrl, $width = 100, $height = 0, string $urlColumn = "")
    {
        if (filter_var($imageUrl, FILTER_VALIDATE_URL) === FALSE) {

            $prefix = "/";
            $imageUrl = ltrim($imageUrl, $prefix);

            $prefix = "storage/";
            $imageUrl = $prefix . ltrim($imageUrl, $prefix);

            $imageUrl = url($imageUrl);
        }

        if ($width === "" || $width === 0) {
            $width = 100;
        } elseif ($width === "auto") {

            if ($height === "auto" || $height === 0) {

                $width = 100;
            } else {
                $width = 0;
            }
        }

        if ($height === "auto") {
            $height = 0;
        }

        return view("default.common.image_ui", compact('imageUrl', 'width', 'height', 'urlColumn'));
    }

    /**
     * @param array $fields [["field" => "Column Name 1", "label" => "Label Name 1"], ["field" => "Column Name 2", "label" => "Label Name 2"]]
     * @return Factory|View
     */
    public function displayColumnStackAs(array $fields = [])
    {
        return view("default.common.column_stack_ui", compact('fields'));
    }
}
