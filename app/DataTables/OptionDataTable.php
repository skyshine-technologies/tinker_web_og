<?php

namespace App\DataTables;

use App\Models\CustomField;
use App\Models\Option;
use Barryvdh\DomPDF\Facade as PDF;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Services\DataTable;

class OptionDataTable extends DataTable
{
    /**
     * custom fields columns
     * @var array
     */
    public static $customFields = [];

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        $dataTable = new EloquentDataTable($query);
        $columns = array_column($this->getColumns(), 'data');
        $dataTable = $dataTable
            ->editColumn('image', function ($option) {
                return getMediaColumn($option, 'image');
            })
            ->editColumn('price', function ($option) {
                return getPriceColumn($option, 'price');
            })
            ->editColumn('product.name', function ($option) {
                return getLinksColumnByRouteName([$option->product->toArray()], 'products.edit','id','name');
            })
            ->editColumn('updated_at', function ($option) {
                return getDateColumn($option, 'updated_at');
            })
            ->addColumn('action', 'options.datatables_actions')
            ->rawColumns(array_merge($columns, ['action']));

        return $dataTable;
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        $columns = [
            [
                'data' => 'name',
                'title' => trans('lang.option_name'),

            ],
            [
                'data' => 'image',
                'title' => trans('lang.option_image'),
                'searchable' => false, 'orderable' => false, 'exportable' => false, 'printable' => false,
            ],
            [
                'data' => 'price',
                'title' => trans('lang.option_price'),

            ],
            [
                'data' => 'product.name',
                'title' => trans('lang.option_product_id'),

            ],
            [
                'data' => 'option_group.name',
                'name' => 'optionGroup.name',
                'title' => trans('lang.option_option_group_id'),

            ],
            [
                'data' => 'updated_at',
                'title' => trans('lang.option_updated_at'),
                'searchable' => false,
            ]
        ];

        $hasCustomField = in_array(Option::class, setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFieldsCollection = CustomField::where('custom_field_model', Option::class)->where('in_table', '=', true)->get();
            foreach ($customFieldsCollection as $key => $field) {
                array_splice($columns, $field->order - 1, 0, [[
                    'data' => 'custom_fields.' . $field->name . '.view',
                    'title' => trans('lang.option_' . $field->name),
                    'orderable' => false,
                    'searchable' => false,
                ]]);
            }
        }
        return $columns;
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Post $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Option $model)
    {
        return $model->newQuery()->with("product")->with("optionGroup");
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        return $this->builder()
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->addAction(['width' => '80px', 'printable' => false, 'responsivePriority' => '100'])
            ->parameters(array_merge(
                config('datatables-buttons.parameters'), [
                    'language' => json_decode(
                        file_get_contents(base_path('resources/lang/' . app()->getLocale() . '/datatable.json')
                        ), true)
                ]
            ));
    }

    /**
     * Export PDF using DOMPDF
     * @return mixed
     */
    public function pdf()
    {
        $data = $this->getDataForPrint();
        $pdf = PDF::loadView($this->printPreview, compact('data'));
        return $pdf->download($this->filename() . '.pdf');
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'optionsdatatable_' . time();
    }
}